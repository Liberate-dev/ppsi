<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Edit Order';
$activePage = 'orders';

$id = $_GET['id'] ?? 0;
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
    $nama = trim($_POST['nama_lengkap']);
    $hp = trim($_POST['no_hp']);
    $pickup_addr = trim($_POST['pickup_address']);
    $jenis_waktu = $_POST['jenis_waktu'] === 'Fast Clean' ? 'Fast Clean' : 'Regular';
    $catatan = trim($_POST['catatan']);
    $items_post = $_POST['items'] ?? [];

    if (empty($nama) || empty($hp) || empty($pickup_addr)) {
        $error = "Nama, No HP, dan Alamat wajib diisi!";
    } elseif (empty($items_post)) {
        $error = "Tambahkan minimal 1 item cucian.";
    } else {
        // Update user data (only if guest)
        $user_id = $order['user_id'];
        $checkUser = $conn->query("SELECT username FROM users WHERE id=$user_id")->fetch_assoc();
        if (strpos($checkUser['username'], 'guest_') === 0) {
            $updUser = $conn->prepare("UPDATE users SET nama_lengkap=?, no_hp=?, alamat=? WHERE id=?");
            $updUser->bind_param("sssi", $nama, $hp, $pickup_addr, $user_id);
            $updUser->execute();
        }

        // Calculate items
        $PRICES = ['sepatu' => 25000, 'sendal' => 25000, 'tas' => 25000, 'topi' => 15000];
        $TREAT_EXTRA = ['Deep Cleaning' => 0, 'Unyellowing' => 10000, 'Repair' => 20000];
        $FAST_EXTRA = ($jenis_waktu === 'Fast Clean') ? 10000 : 0;

        $items_arr = [];
        $total = 0;

        foreach ($items_post as $idx => $item) {
            $type = $item['type'] ?? '';
            if (!isset($PRICES[$type])) continue;
            $qty = max(1, (int) ($item['qty'] ?? 1));
            $treat = $item['treatment'] ?? 'Deep Cleaning';
            $price = ($PRICES[$type] + ($TREAT_EXTRA[$treat] ?? 0) + $FAST_EXTRA);
            $sub = $price * $qty;
            $total += $sub;
            $items_arr[] = ['type' => $type, 'qty' => $qty, 'treatment' => $treat, 'harga_satuan' => $price, 'subtotal' => $sub];
        }

        $items_json = json_encode($items_arr);
        $est = getEstimasiSelesai($jenis_waktu);

        $updOrder = $conn->prepare("UPDATE orders SET items=?, jenis_waktu=?, total_harga=?, pickup_address=?, catatan=?, tanggal_estimasi=? WHERE id=?");
        $updOrder->bind_param("ssdsssi", $items_json, $jenis_waktu, $total, $pickup_addr, $catatan, $est, $id);

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
            
            $_SESSION['flash_success'] = "Data order berhasil diperbarui!";
            header("Location: order_detail.php?id=$id");
            exit;
        } else {
            $error = "Gagal mengupdate order: " . $conn->error;
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<style>
.responsive-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}
@media (min-width: 640px) {
    .responsive-grid {
        grid-template-columns: 1fr 1fr;
    }
}
</style>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
        <h2 style="font-size:1.4rem; font-weight:800;">Edit Order #<?= htmlspecialchars($order['kode_order']) ?></h2>
        <a href="orders.php" class="btn btn-outline btn-sm">⬅ Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">1. Data Pelanggan</h3>
        <div class="responsive-grid" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($order['nama_lengkap']) ?>" required>
            </div>
            <div>
                <label class="form-label">No HP / WA *</label>
                <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($order['no_hp']) ?>" required>
            </div>
        </div>
        <div style="margin-bottom: 2rem;">
            <label class="form-label">Alamat / Keterangan Pick-up *</label>
            <textarea name="pickup_address" class="form-control" rows="2" required><?= htmlspecialchars($order['pickup_address']) ?></textarea>
        </div>

        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">2. Item Cucian</h3>
        <div id="itemsContainer" style="margin-bottom: 1rem;">
            <?php foreach ($items as $idx => $item): ?>
            <div class="order-item-row">
                <div>
                    <label class="form-label">Jenis Cucian</label>
                    <select name="items[<?= $idx ?>][type]" class="form-control item-type" required>
                        <option value="sepatu" <?= ($item['type']??'')=='sepatu'?'selected':'' ?>>Sepatu / Sendal</option>
                        <option value="tas" <?= ($item['type']??'')=='tas'?'selected':'' ?>>Tas</option>
                        <option value="topi" <?= ($item['type']??'')=='topi'?'selected':'' ?>>Topi</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Qty</label>
                    <input type="number" name="items[<?= $idx ?>][qty]" class="form-control item-qty" value="<?= $item['qty']??1 ?>" min="1" required>
                </div>
                <div>
                    <label class="form-label">Treatment</label>
                    <select name="items[<?= $idx ?>][treatment]" class="form-control item-treatment" required>
                        <option value="Deep Cleaning" <?= ($item['treatment']??'')=='Deep Cleaning'?'selected':'' ?>>Deep Cleaning</option>
                        <option value="Unyellowing" <?= ($item['treatment']??'')=='Unyellowing'?'selected':'' ?>>Unyellowing</option>
                        <option value="Repair" <?= ($item['treatment']??'')=='Repair'?'selected':'' ?>>Repair</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <div class="item-subtotal" style="font-weight: 700; color: var(--primary);">Rp <?= number_format($item['subtotal']??0,0,',','.') ?></div>
                    <?php if ($idx > 0): ?>
                    <button type="button" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);margin-top:5px;width:100%;" onclick="this.closest('.order-item-row').remove(); calcTotal();">Hapus</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="addItemBtn" class="btn btn-outline btn-sm mb-3">+ Tambah Item</button>

        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-top:1rem;">3. Kecepatan Pengerjaan</h3>
        <div class="responsive-grid" style="margin-bottom: 1.5rem;">
            <label style="cursor:pointer;"><input type="radio" name="jenis_waktu" value="Regular" class="waktu-radio" <?= $order['jenis_waktu']!=='Fast Clean'?'checked':'' ?>> Regular (Harga Normal)</label>
            <label style="cursor:pointer;"><input type="radio" name="jenis_waktu" value="Fast Clean" class="waktu-radio" <?= $order['jenis_waktu']==='Fast Clean'?'checked':'' ?>> Fast Clean (+Rp 10rb/item)</label>
        </div>

        <div class="estimation-box" style="margin-bottom: 2rem;">
            <div class="estimation-icon">📅</div>
            <div>
                <div class="estimation-label">Estimasi Selesai</div>
                <div class="estimation-value" id="estimasiTanggal">-</div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <label class="form-label">Catatan Tambahan (Opsional)</label>
            <input type="text" name="catatan" class="form-control" value="<?= htmlspecialchars($order['catatan']) ?>">
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 1.5rem;">
            <div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">Total Harga</div>
                <div id="grandTotal" style="font-size: 1.5rem; font-weight: 800; color: var(--primary);">Rp <?= number_format($order['total_harga'],0,',','.') ?></div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem; font-size: 1.1rem; border-radius: 8px;">Update Order</button>
        </div>
    </form>
</div>

<script>
    const BASE_PRICE = { 'sepatu': 25000, 'tas': 25000, 'topi': 15000, 'sendal': 25000 };
    const TREAT_PRICE = { 'Deep Cleaning': 0, 'Unyellowing': 10000, 'Repair': 20000 };
    let itemIdx = <?= count($items) ?>;

    function calcTotal() {
        let grandTotal = 0;
        const isFast = document.querySelector('input[name="jenis_waktu"]:checked').value === 'Fast Clean';
        const fastPrice = isFast ? 10000 : 0;

        document.querySelectorAll('.order-item-row').forEach(row => {
            const type = row.querySelector('.item-type').value;
            const treat = row.querySelector('.item-treatment').value;
            const qty = parseInt(row.querySelector('.item-qty').value) || 1;
            const pricePerItem = (BASE_PRICE[type] || 0) + (TREAT_PRICE[treat] || 0) + fastPrice;
            const subtotal = pricePerItem * qty;
            row.querySelector('.item-subtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
            grandTotal += subtotal;
        });

        document.getElementById('grandTotal').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');

        // Update estimation date
        const days = isFast ? 1 : 2;
        const est = new Date();
        est.setDate(est.getDate() + days);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('estimasiTanggal').innerText = est.toLocaleDateString('id-ID', options);
    }

    document.getElementById('itemsContainer').addEventListener('change', calcTotal);
    document.getElementById('itemsContainer').addEventListener('input', calcTotal);
    document.querySelectorAll('.waktu-radio').forEach(r => r.addEventListener('change', calcTotal));

    // Initialize date estimation on load
    calcTotal();

    document.getElementById('addItemBtn').addEventListener('click', () => {
        itemIdx++;
        const container = document.getElementById('itemsContainer');
        const firstRow = container.querySelector('.order-item-row').cloneNode(true);
        firstRow.querySelector('.item-type').name = `items[${itemIdx}][type]`;
        firstRow.querySelector('.item-qty').name = `items[${itemIdx}][qty]`;
        firstRow.querySelector('.item-qty').value = 1;
        firstRow.querySelector('.item-treatment').name = `items[${itemIdx}][treatment]`;
        
        let removeBtn = firstRow.querySelector('button');
        if (!removeBtn) {
            removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.innerText = 'Hapus';
            removeBtn.className = 'btn btn-outline btn-sm';
            removeBtn.style.width = '100%';
            removeBtn.style.marginTop = '5px';
            removeBtn.style.color = 'var(--danger)';
            removeBtn.style.borderColor = 'var(--danger)';
            removeBtn.onclick = function() { this.closest('.order-item-row').remove(); calcTotal(); };
            firstRow.querySelector('.item-subtotal').parentElement.appendChild(removeBtn);
        } else {
            removeBtn.onclick = function() { this.closest('.order-item-row').remove(); calcTotal(); };
        }
        
        container.appendChild(firstRow);
        calcTotal();
    });
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
