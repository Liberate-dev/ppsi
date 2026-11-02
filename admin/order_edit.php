<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Edit Order';
$activePage = 'orders';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: orders.php');
    exit;
}

// Fetch order and user
$stmt = $conn->prepare("SELECT o.*, u.nama_lengkap, u.no_hp FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    header('Location: orders.php');
    exit;
}

$items = json_decode($order['items'], true) ?? [];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama        = htmlspecialchars(strip_tags(trim($_POST['nama_lengkap'] ?? '')), ENT_QUOTES, 'UTF-8');
    $hp_raw      = trim($_POST['no_hp'] ?? '');
    $hp          = preg_replace('/[^0-9]/', '', $hp_raw);
    $pickup_addr = htmlspecialchars(strip_tags(trim($_POST['pickup_address'] ?? '')), ENT_QUOTES, 'UTF-8');
    $jenis_waktu = ($_POST['jenis_waktu'] ?? '') === 'Fast Clean' ? 'Fast Clean' : 'Regular';
    $catatan     = htmlspecialchars(strip_tags(trim($_POST['catatan'] ?? '')), ENT_QUOTES, 'UTF-8');
    $metode_bayar= trim($_POST['metode_pembayaran'] ?? ($order['metode_pembayaran'] ?? 'COD'));
    if (!in_array($metode_bayar, ['COD', 'Transfer Bank', 'QRIS'])) {
        $metode_bayar = 'COD';
    }
    $status_bayar= trim($_POST['status_pembayaran'] ?? ($order['status_pembayaran'] ?? 'Belum Bayar'));
    if (!in_array($status_bayar, ['Belum Bayar', 'Lunas'])) {
        $status_bayar = 'Belum Bayar';
    }
    $status_order= trim($_POST['status'] ?? $order['status']);
    $allowed_statuses = ['Menunggu', 'Pending Payment', 'Diterima', 'Cuci', 'Kering', 'Siap Kirim', 'Selesai', 'Dibatalkan'];
    if (!in_array($status_order, $allowed_statuses)) {
        $status_order = $order['status'];
    }

    $items_post  = $_POST['items'] ?? [];

    if (empty($nama) || empty($hp) || empty($pickup_addr)) {
        $error = "Nama, No HP, dan Alamat wajib diisi.";
    } elseif (strlen($hp) < 10 || strlen($hp) > 15) {
        $error = "Nomor HP / WhatsApp harus berupa 10 - 15 digit angka.";
    } elseif (empty($items_post) || !is_array($items_post)) {
        $error = "Tambahkan minimal 1 item cucian.";
    } else {
        // Update user data if guest
        $user_id = (int)$order['user_id'];
        $checkUser = $conn->query("SELECT username FROM users WHERE id=$user_id")->fetch_assoc();
        if ($checkUser && strpos($checkUser['username'], 'guest_') === 0) {
            $updUser = $conn->prepare("UPDATE users SET nama_lengkap=?, no_hp=?, alamat=? WHERE id=?");
            $updUser->bind_param("sssi", $nama, $hp, $pickup_addr, $user_id);
            $updUser->execute();
        }

        // Calculate items
        $PRICES      = ['sepatu' => 25000, 'sendal' => 25000, 'tas' => 25000, 'topi' => 15000];
        $TREAT_EXTRA = ['Deep Cleaning' => 0, 'Unyellowing' => 10000, 'Repair' => 20000];
        $FAST_EXTRA  = ($jenis_waktu === 'Fast Clean') ? 10000 : 0;

        $items_arr = [];
        $subtotal  = 0;

        foreach ($items_post as $item) {
            $type = $item['type'] ?? '';
            if (!isset($PRICES[$type])) continue;
            $qty = max(1, min(50, (int) ($item['qty'] ?? 1)));
            $treat = in_array($item['treatment'] ?? '', ['Deep Cleaning', 'Unyellowing', 'Repair']) ? $item['treatment'] : 'Deep Cleaning';
            $price = ($PRICES[$type] + ($TREAT_EXTRA[$treat] ?? 0) + $FAST_EXTRA);
            $sub = $price * $qty;
            $subtotal += $sub;
            $items_arr[] = [
                'type' => $type,
                'qty' => $qty,
                'treatment' => $treat,
                'harga_satuan' => $price,
                'subtotal' => $sub
            ];
        }

        if (empty($items_arr)) {
            $error = "Item cucian tidak valid.";
        } else {
            // Apply member discount if exists
            $diskonMember = (float)($order['diskon_member'] ?? 0);
            $tierMember = $order['tier_member'] ?? null;
            if ($tierMember) {
                $discounts = ['Bronze' => 0.05, 'Silver' => 0.10, 'Gold' => 0.15];
                $rate = $discounts[$tierMember] ?? 0.05;
                $diskonMember = round($subtotal * $rate);
            }
            $total = max(0, $subtotal - $diskonMember);

            $items_json = json_encode($items_arr);
            $est = getEstimasiSelesai($jenis_waktu);

            $confirmed_at = ($status_bayar === 'Lunas' && empty($order['payment_confirmed_at'])) ? date('Y-m-d H:i:s') : $order['payment_confirmed_at'];

            $updOrder = $conn->prepare("UPDATE orders SET items=?, jenis_waktu=?, total_harga=?, pickup_address=?, catatan=?, tanggal_estimasi=?, status=?, metode_pembayaran=?, status_pembayaran=?, diskon_member=?, payment_confirmed_at=? WHERE id=?");
            $updOrder->bind_param("ssdssssssdssi", $items_json, $jenis_waktu, $total, $pickup_addr, $catatan, $est, $status_order, $metode_bayar, $status_bayar, $diskonMember, $confirmed_at, $id);

            if ($updOrder->execute()) {
                // Regenerate Nota IN if it exists
                $kode = $order['kode_order'];
                $notaFile = __DIR__ . "/../uploads/documents/generated/NotaIN_{$kode}.html";
                if (file_exists($notaFile)) {
                    $order['nama_lengkap'] = $nama;
                    $order['no_hp'] = $hp;
                    $order['jenis_waktu'] = $jenis_waktu;
                    $order['tanggal_estimasi'] = $est;
                    $order['total_harga'] = $total;
                    $order['metode_pembayaran'] = $metode_bayar;
                    $order['status_pembayaran'] = $status_bayar;
                    $order['diskon_member'] = $diskonMember;
                    $order['tier_member'] = $tierMember;
                    $items = $items_arr;

                    // Get all foto_awal_urls
                    $tt1_res = $conn->query("SELECT file_path FROM documents WHERE ref_type='order' AND ref_id={$id} AND nama_dokumen LIKE 'Tanda Terima 1%' ORDER BY id ASC");
                    $foto_awal_urls = [];
                    while ($row = $tt1_res->fetch_assoc()) {
                        $foto_awal_urls[] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $row['file_path'];
                    }
                    $foto_awal_url = !empty($foto_awal_urls) ? $foto_awal_urls[0] : '';
                    ob_start();
                    include __DIR__ . '/../templates/invoice_in.php';
                    $html_content = ob_get_clean();
                    file_put_contents($notaFile, $html_content);
                }

                $_SESSION['flash_success'] = "Data order #{$order['kode_order']} berhasil diperbarui.";
                header("Location: order_detail.php?id=$id");
                exit;
            } else {
                $error = "Gagal mengupdate order: " . $conn->error;
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="max-width: 860px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size:1.35rem; font-weight:800; margin-bottom:0.25rem;">Edit Order #<?= htmlspecialchars($order['kode_order']) ?></h2>
            <p style="font-size:0.9rem; color:var(--text-secondary); margin:0;">Perbarui rincian pelanggan, item cucian, atau status pembayaran.</p>
        </div>
        <a href="order_detail.php?id=<?= $id ?>" class="btn btn-outline btn-sm">Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:1.25rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card" style="box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
        <!-- 1. Customer Data -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                1. Data Pelanggan
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($order['nama_lengkap']) ?>" required>
                </div>
                <div>
                    <label class="form-label">No HP / WhatsApp (Hanya Angka) *</label>
                    <input type="text" name="no_hp" id="inputNoHp" class="form-control" value="<?= htmlspecialchars($order['no_hp']) ?>" inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                </div>
            </div>
            <div>
                <label class="form-label">Alamat / Lokasi Pick-up *</label>
                <textarea name="pickup_address" class="form-control" rows="2" required><?= htmlspecialchars($order['pickup_address']) ?></textarea>
            </div>
        </div>

        <!-- 2. Items -->
        <div style="margin-bottom: 2rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem;">
                <div style="font-size: 1.05rem; font-weight: 700; color: var(--navy);">2. Item Cucian</div>
                <button type="button" id="addItemBtn" class="btn btn-outline btn-sm">+ Tambah Item</button>
            </div>

            <div id="itemsContainer">
                <?php foreach ($items as $idx => $item): ?>
                <div class="order-item-card" data-idx="<?= $idx ?>" style="background:#FAFAFA; border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-bottom:1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <span class="order-item-card-title" style="font-weight:700; font-size:14px; color:var(--navy);">Item #<?= $idx + 1 ?></span>
                        <button type="button" class="btn-del-item btn btn-outline btn-sm" style="<?= $idx === 0 ? 'display:none;' : '' ?> color:var(--danger); border-color:var(--danger); padding:4px 10px; font-size:12px;" onclick="this.closest('.order-item-card').remove(); calcTotal();">Hapus</button>
                    </div>
                    <div class="order-item-grid" style="display:grid; grid-template-columns: 1.8fr 1.8fr 0.8fr 1.2fr; gap:0.75rem; align-items:flex-end;">
                        <div>
                            <label class="form-label" style="font-size:12px;">Kategori</label>
                            <select name="items[<?= $idx ?>][type]" class="form-control item-type" required>
                                <option value="sepatu" <?= ($item['type'] ?? '') === 'sepatu' ? 'selected' : '' ?>>Sepatu / Sendal</option>
                                <option value="tas" <?= ($item['type'] ?? '') === 'tas' ? 'selected' : '' ?>>Tas</option>
                                <option value="topi" <?= ($item['type'] ?? '') === 'topi' ? 'selected' : '' ?>>Topi</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size:12px;">Treatment</label>
                            <select name="items[<?= $idx ?>][treatment]" class="form-control item-treatment" required>
                                <option value="Deep Cleaning" <?= ($item['treatment'] ?? '') === 'Deep Cleaning' ? 'selected' : '' ?>>Deep Cleaning</option>
                                <option value="Unyellowing" <?= ($item['treatment'] ?? '') === 'Unyellowing' ? 'selected' : '' ?>>Unyellowing (+Rp 10.000)</option>
                                <option value="Repair" <?= ($item['treatment'] ?? '') === 'Repair' ? 'selected' : '' ?>>Repair (+Rp 20.000)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size:12px;">Jumlah</label>
                            <input type="number" name="items[<?= $idx ?>][qty]" class="form-control item-qty" value="<?= (int)($item['qty'] ?? 1) ?>" min="1" max="50" required>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size:11px; color:var(--text-secondary); margin-bottom:4px;">Subtotal</div>
                            <div class="item-subtotal" style="font-weight: 700; font-size:15px; color: var(--primary);">Rp <?= number_format($item['subtotal'] ?? 0, 0, ',', '.') ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 3. Kecepatan Pengerjaan -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                3. Kecepatan Pengerjaan
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <label style="cursor:pointer; margin:0;">
                    <div class="waktu-card" style="border: 1.5px solid <?= $order['jenis_waktu'] !== 'Fast Clean' ? 'var(--primary)' : 'var(--border)' ?>; background: <?= $order['jenis_waktu'] !== 'Fast Clean' ? 'var(--primary-light)' : '#FFF' ?>; border-radius: 10px; padding: 1rem; transition: var(--transition);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                            <input type="radio" name="jenis_waktu" value="Regular" class="waktu-radio" <?= $order['jenis_waktu'] !== 'Fast Clean' ? 'checked' : '' ?>>
                            <strong style="font-size:14px; color:var(--navy);">Regular (2 Hari)</strong>
                        </div>
                        <div style="font-size:12px; color:var(--text-secondary); padding-left:1.5rem;">Harga standar pengerjaan bersih maksimal.</div>
                    </div>
                </label>
                <label style="cursor:pointer; margin:0;">
                    <div class="waktu-card" style="border: 1.5px solid <?= $order['jenis_waktu'] === 'Fast Clean' ? 'var(--primary)' : 'var(--border)' ?>; background: <?= $order['jenis_waktu'] === 'Fast Clean' ? 'var(--primary-light)' : '#FFF' ?>; border-radius: 10px; padding: 1rem; transition: var(--transition);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                            <input type="radio" name="jenis_waktu" value="Fast Clean" class="waktu-radio" <?= $order['jenis_waktu'] === 'Fast Clean' ? 'checked' : '' ?>>
                            <strong style="font-size:14px; color:var(--navy);">Fast Clean (1 Hari)</strong>
                        </div>
                        <div style="font-size:12px; color:var(--text-secondary); padding-left:1.5rem;">Prioritas kilat, tambahan +Rp 10.000 / item.</div>
                    </div>
                </label>
            </div>

            <div class="estimation-box" style="display:flex; align-items:center; gap:0.75rem; background:var(--off-white); border:1px solid var(--border); border-radius:10px; padding:0.85rem 1.25rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px; height:20px; stroke:var(--primary);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <div>
                    <div style="font-size:11px; color:var(--text-secondary);">Estimasi Selesai</div>
                    <div style="font-weight:700; font-size:14px; color:var(--navy);" id="estimasiTanggal"><?= $order['tanggal_estimasi'] ? date('l, d F Y', strtotime($order['tanggal_estimasi'])) : '-' ?></div>
                </div>
            </div>
        </div>

        <!-- 4. Status & Pembayaran -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                4. Status Order & Pembayaran
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <label class="form-label">Status Cucian</label>
                    <select name="status" class="form-control">
                        <option value="Menunggu" <?= $order['status'] === 'Menunggu' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="Pending Payment" <?= $order['status'] === 'Pending Payment' ? 'selected' : '' ?>>Pending Payment</option>
                        <option value="Diterima" <?= $order['status'] === 'Diterima' ? 'selected' : '' ?>>Diterima</option>
                        <option value="Cuci" <?= $order['status'] === 'Cuci' ? 'selected' : '' ?>>Cuci</option>
                        <option value="Kering" <?= $order['status'] === 'Kering' ? 'selected' : '' ?>>Kering</option>
                        <option value="Siap Kirim" <?= $order['status'] === 'Siap Kirim' ? 'selected' : '' ?>>Siap Kirim</option>
                        <option value="Selesai" <?= $order['status'] === 'Selesai' ? 'selected' : '' ?>>Selesai</option>
                        <option value="Dibatalkan" <?= $order['status'] === 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="metode_pembayaran" class="form-control">
                        <option value="COD" <?= ($order['metode_pembayaran'] ?? 'COD') === 'COD' ? 'selected' : '' ?>>COD / Tunai</option>
                        <option value="Transfer Bank" <?= ($order['metode_pembayaran'] ?? '') === 'Transfer Bank' ? 'selected' : '' ?>>Transfer Bank</option>
                        <option value="QRIS" <?= ($order['metode_pembayaran'] ?? '') === 'QRIS' ? 'selected' : '' ?>>QRIS</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status Pembayaran</label>
                    <select name="status_pembayaran" class="form-control">
                        <option value="Belum Bayar" <?= ($order['status_pembayaran'] ?? 'Belum Bayar') === 'Belum Bayar' ? 'selected' : '' ?>>Belum Bayar</option>
                        <option value="Lunas" <?= ($order['status_pembayaran'] ?? '') === 'Lunas' ? 'selected' : '' ?>>Lunas</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 5. Catatan -->
        <div style="margin-bottom: 2rem;">
            <label class="form-label">Catatan Tambahan (Opsional)</label>
            <input type="text" name="catatan" class="form-control" value="<?= htmlspecialchars($order['catatan']) ?>">
        </div>

        <!-- Summary & Submit -->
        <div style="border-top: 1px solid var(--border); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">Total Harga</div>
                <div id="grandTotal" style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">Rp <?= number_format($order['total_harga'], 0, ',', '.') ?></div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2.25rem; font-size: 1rem; border-radius: 8px;">Perbarui Order</button>
        </div>
    </form>
</div>

<script>
    const BASE_PRICE = { 'sepatu': 25000, 'tas': 25000, 'topi': 15000, 'sendal': 25000 };
    const TREAT_PRICE = { 'Deep Cleaning': 0, 'Unyellowing': 10000, 'Repair': 20000 };
    let itemCardIndex = <?= count($items) ?>;
    const discountRate = <?= !empty($order['tier_member']) ? ($order['tier_member'] === 'Gold' ? 0.15 : ($order['tier_member'] === 'Silver' ? 0.10 : 0.05)) : 0 ?>;

    function calcTotal() {
        let subtotal = 0;
        const isFast = document.querySelector('input[name="jenis_waktu"]:checked').value === 'Fast Clean';
        const fastPrice = isFast ? 10000 : 0;

        document.querySelectorAll('.order-item-card').forEach(card => {
            const type = card.querySelector('.item-type').value;
            const treat = card.querySelector('.item-treatment').value;
            const qty = Math.max(1, parseInt(card.querySelector('.item-qty').value) || 1);
            const pricePerItem = (BASE_PRICE[type] || 0) + (TREAT_PRICE[treat] || 0) + fastPrice;
            const itemSub = pricePerItem * qty;
            card.querySelector('.item-subtotal').innerText = 'Rp ' + itemSub.toLocaleString('id-ID');
            subtotal += itemSub;
        });

        const diskon = Math.round(subtotal * discountRate);
        const grandTotal = Math.max(0, subtotal - diskon);

        document.getElementById('grandTotal').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');

        // Update estimation date
        const days = isFast ? 1 : 2;
        const est = new Date();
        est.setDate(est.getDate() + days);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('estimasiTanggal').innerText = est.toLocaleDateString('id-ID', options);
    }

    const itemsCont = document.getElementById('itemsContainer');
    itemsCont.addEventListener('change', calcTotal);
    itemsCont.addEventListener('input', calcTotal);

    document.querySelectorAll('.waktu-radio').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelectorAll('.waktu-card').forEach(c => {
                c.style.borderColor = 'var(--border)';
                c.style.background = '#FFF';
            });
            const card = this.closest('label').querySelector('.waktu-card');
            card.style.borderColor = 'var(--primary)';
            card.style.background = 'var(--primary-light)';
            calcTotal();
        });
    });

    document.getElementById('addItemBtn').addEventListener('click', () => {
        itemCardIndex++;
        const cards = itemsCont.querySelectorAll('.order-item-card');
        const firstCard = cards[0].cloneNode(true);
        firstCard.setAttribute('data-idx', itemCardIndex);
        firstCard.querySelector('.order-item-card-title').innerText = 'Item #' + (itemCardIndex + 1);

        const typeSelect = firstCard.querySelector('.item-type');
        typeSelect.name = `items[${itemCardIndex}][type]`;
        typeSelect.selectedIndex = 0;

        const treatSelect = firstCard.querySelector('.item-treatment');
        treatSelect.name = `items[${itemCardIndex}][treatment]`;
        treatSelect.selectedIndex = 0;

        const qtyInput = firstCard.querySelector('.item-qty');
        qtyInput.name = `items[${itemCardIndex}][qty]`;
        qtyInput.value = 1;

        const delBtn = firstCard.querySelector('.btn-del-item');
        delBtn.style.display = 'inline-block';
        delBtn.onclick = function() {
            firstCard.remove();
            calcTotal();
        };

        itemsCont.appendChild(firstCard);
        calcTotal();
    });

    calcTotal();
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
