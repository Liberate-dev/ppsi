<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Buat Order Manual';
$activePage = 'orders';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama        = htmlspecialchars(strip_tags(trim($_POST['nama_lengkap'] ?? '')), ENT_QUOTES, 'UTF-8');
    $hp_raw      = trim($_POST['no_hp'] ?? '');
    $hp          = preg_replace('/[^0-9]/', '', $hp_raw);
    $pickup_addr = htmlspecialchars(strip_tags(trim($_POST['pickup_address'] ?? '')), ENT_QUOTES, 'UTF-8');
    $jenis_waktu = ($_POST['jenis_waktu'] ?? '') === 'Fast Clean' ? 'Fast Clean' : 'Regular';
    $metode_bayar= trim($_POST['metode_pembayaran'] ?? 'COD');
    if (!in_array($metode_bayar, ['COD', 'Transfer Bank', 'QRIS'])) {
        $metode_bayar = 'COD';
    }
    $status_bayar= trim($_POST['status_pembayaran'] ?? 'Belum Bayar');
    if (!in_array($status_bayar, ['Belum Bayar', 'Lunas'])) {
        $status_bayar = 'Belum Bayar';
    }
    $catatan     = htmlspecialchars(strip_tags(trim($_POST['catatan'] ?? '')), ENT_QUOTES, 'UTF-8');
    $items_post  = $_POST['items'] ?? [];

    if (empty($nama) || empty($hp) || empty($pickup_addr)) {
        $error = "Nama, No HP, dan Alamat wajib diisi.";
    } elseif (strlen($hp) < 10 || strlen($hp) > 15) {
        $error = "Nomor HP / WhatsApp harus berupa 10 - 15 digit angka.";
    } elseif (empty($_FILES['foto_awal']['name']) || (is_array($_FILES['foto_awal']['name']) && empty($_FILES['foto_awal']['name'][0]))) {
        $error = "Foto kondisi awal sepatu wajib diupload.";
    } elseif (empty($items_post) || !is_array($items_post)) {
        $error = "Tambahkan minimal satu item cucian.";
    } else {
        // Cek / Buat User (Guest jika belum ada)
        $stmt = $conn->prepare("SELECT id FROM users WHERE no_hp = ?");
        $stmt->bind_param("s", $hp);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $userId = (int) $res->fetch_assoc()['id'];
        } else {
            $email_guest = "guest_" . $hp . "@footstepszs.local";
            $user_guest = "guest_" . $hp;
            $pass_guest = password_hash(uniqid(), PASSWORD_BCRYPT);

            $ins_user = $conn->prepare("INSERT INTO users (username, email, password, nama_lengkap, no_hp, alamat, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
            $ins_user->bind_param("ssssss", $user_guest, $email_guest, $pass_guest, $nama, $hp, $pickup_addr);
            if ($ins_user->execute()) {
                $userId = $conn->insert_id;
            } else {
                $error = "Gagal memproses data pelanggan: " . $conn->error;
            }
        }

        if (empty($error)) {
            // Validasi & Upload Foto
            $files = $_FILES['foto_awal'];
            $uploaded_count = is_array($files['name']) ? count($files['name']) : 1;
            $allowed   = ['jpg', 'jpeg', 'png', 'webp'];
            $invalid_format = false;
            $max_size = 5 * 1024 * 1024; // 5MB

            for ($i = 0; $i < $uploaded_count; $i++) {
                $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmp_name  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $file_err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];

                if ($file_err !== 0 || $file_size > $max_size) {
                    $invalid_format = true;
                    break;
                }
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    $invalid_format = true;
                    break;
                }
            }

            if ($invalid_format) {
                $error = "Format foto harus JPG, PNG, atau WEBP dan ukuran maksimal 5MB per file.";
            } else {
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
                    // Check member discount
                    $memberData = lookupMemberByPhone($conn, $hp);
                    $diskonMember = 0;
                    $tierMember = null;
                    if ($memberData) {
                        $tierMember = $memberData['level'];
                        $discounts = ['Bronze' => 0.05, 'Silver' => 0.10, 'Gold' => 0.15];
                        $rate = $discounts[$tierMember] ?? 0.05;
                        $diskonMember = round($subtotal * $rate);
                    }
                    $total = max(0, $subtotal - $diskonMember);

                    $kode = generateOrderCode($conn);
                    $items_json = json_encode($items_arr);
                    $est = getEstimasiSelesai($jenis_waktu);

                    // If status_bayar == Lunas or COD, initial order status can be Diterima / Cuci
                    $initial_status = ($status_bayar === 'Lunas') ? 'Cuci' : (($metode_bayar === 'COD') ? 'Diterima' : 'Pending Payment');
                    $confirmed_at = ($status_bayar === 'Lunas') ? date('Y-m-d H:i:s') : null;

                    $ins = $conn->prepare("INSERT INTO orders (user_id, kode_order, items, jenis_waktu, total_harga, pickup_address, catatan, tanggal_estimasi, status, metode_pembayaran, status_pembayaran, diskon_member, tier_member, payment_confirmed_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $ins->bind_param("isssdsssssdsss", $userId, $kode, $items_json, $jenis_waktu, $total, $pickup_addr, $catatan, $est, $initial_status, $metode_bayar, $status_bayar, $diskonMember, $tierMember, $confirmed_at);

                    if ($ins->execute()) {
                        $order_id = $conn->insert_id;
                        $adminId = (int)$_SESSION['user_id'];

                        // If lunas, record to keuangan as Pemasukan
                        if ($status_bayar === 'Lunas') {
                            $insK = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES ('Pemasukan', 'Order Laundry', ?, ?, CURDATE(), ?)");
                            $ketK = "Pembayaran Order Manual #{$kode} ({$metode_bayar})";
                            $insK->bind_param("dsi", $total, $ketK, $adminId);
                            $insK->execute();
                        }

                        // Upload File TT-1
                        $target_dir = __DIR__ . '/../uploads/documents/';
                        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                        $foto_awal_urls = [];
                        for ($i = 0; $i < $uploaded_count; $i++) {
                            $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                            $tmp_name  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                            $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                            $new_name = 'TT1_' . $kode . '_' . time() . '_' . $i . '.' . $ext;

                            if (move_uploaded_file($tmp_name, $target_dir . $new_name)) {
                                $file_path_db = 'uploads/documents/' . $new_name;
                                $foto_awal_urls[] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $file_path_db;

                                $doc_name = "Tanda Terima 1 - Barang Masuk (#" . ($i + 1) . ")";
                                $ket_tt1 = "Foto awal barang (Order Manual)";
                                insertTandaTerimaDoc($conn, $order_id, $doc_name, $file_path_db, $ket_tt1, $adminId);
                            }
                        }

                        $foto_awal_url = !empty($foto_awal_urls) ? $foto_awal_urls[0] : '';

                        // Generate Nota IN
                        $order = [
                            'kode_order' => $kode,
                            'tanggal_order' => date('Y-m-d H:i:s'),
                            'nama_lengkap' => $nama,
                            'no_hp' => $hp,
                            'jenis_waktu' => $jenis_waktu,
                            'tanggal_estimasi' => $est,
                            'total_harga' => $total,
                            'metode_pembayaran' => $metode_bayar,
                            'status_pembayaran' => $status_bayar,
                            'diskon_member' => $diskonMember,
                            'tier_member' => $tierMember
                        ];
                        $items = $items_arr;

                        ob_start();
                        include __DIR__ . '/../templates/invoice_in.php';
                        $html_content = ob_get_clean();

                        $gen_dir = __DIR__ . '/../uploads/documents/generated/';
                        if (!is_dir($gen_dir)) mkdir($gen_dir, 0777, true);
                        $nota_filename = 'NotaIN_' . $kode . '.html';
                        file_put_contents($gen_dir . $nota_filename, $html_content);
                        $nota_db_path = 'uploads/documents/generated/' . $nota_filename;

                        $insNota = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, 'Nota Pesanan IN', 'Generated', ?, 'Nota Order Masuk', CURDATE(), ?)");
                        $insNota->bind_param("isi", $order_id, $nota_db_path, $adminId);
                        $insNota->execute();

                        $_SESSION['flash_success'] = "Order manual #$kode berhasil dibuat.";
                        header("Location: order_detail.php?id=$order_id");
                        exit;
                    } else {
                        $error = "Gagal membuat order: " . $conn->error;
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/admin_header.php';
?>

<div style="max-width: 860px; margin: 0 auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
        <div>
            <h2 style="font-size:1.35rem; font-weight:800; margin-bottom:0.25rem;">Buat Order Manual (Walk-In)</h2>
            <p style="font-size:0.9rem; color:var(--text-secondary); margin:0;">Input transaksi langsung pelanggan toko atau pemesanan offline.</p>
        </div>
        <a href="orders.php" class="btn btn-outline btn-sm">Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:1.25rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card" style="box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
        <!-- 1. Customer Data -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                1. Data Pelanggan
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Nama Lengkap *</label>
                    <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama pelanggan" required value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
                </div>
                <div>
                    <label class="form-label">No HP / WhatsApp (Hanya Angka) *</label>
                    <input type="text" name="no_hp" id="inputNoHp" class="form-control" placeholder="08xxxxxxxxxx" inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, ''); checkMemberLive(this.value);" required value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
                    <div id="memberBadgeNotice" style="display:none; font-size:12px; margin-top:6px; color:var(--primary); font-weight:600;"></div>
                </div>
            </div>
            <div>
                <label class="form-label">Alamat / Lokasi Toko *</label>
                <textarea name="pickup_address" class="form-control" rows="2" placeholder="Cth: Walk-in counter toko Footstepszs Malang..." required><?= htmlspecialchars($_POST['pickup_address'] ?? 'Walk-in Toko Footstepszs') ?></textarea>
            </div>
        </div>

        <!-- 2. Foto Awal -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                2. Foto Kondisi Awal (Tanda Terima 1) *
            </div>
            <div style="border: 2px dashed var(--border); border-radius: 12px; padding: 1.75rem; text-align: center; cursor: pointer; background: var(--off-white);" onclick="if(event.target.id !== 'fotoInput') document.getElementById('fotoInput').click()">
                <input type="file" name="foto_awal[]" id="fotoInput" accept="image/*" style="display:none;" multiple required onchange="previewTT(this)">
                <div id="fotoPreviewContainer" style="display:none; flex-wrap:wrap; justify-content:center; gap:10px; margin-bottom:10px;"></div>
                <div id="fotoText">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:32px; height:32px; margin-bottom:0.5rem; stroke:var(--text-secondary);"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <div style="font-weight:600; color:var(--navy); font-size:14px;">Klik untuk upload foto kondisi awal sepatu/barang</div>
                    <div style="font-size:12px; color:var(--text-secondary); margin-top:4px;">Format: JPG, PNG, WEBP (Maksimal 5MB per foto, dapat memilih beberapa foto)</div>
                </div>
            </div>
        </div>

        <!-- 3. Item Cucian -->
        <div style="margin-bottom: 2rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-bottom: 1rem;">
                <div style="font-size: 1.05rem; font-weight: 700; color: var(--navy);">3. Item Cucian</div>
                <button type="button" id="addItemBtn" class="btn btn-outline btn-sm">+ Tambah Item</button>
            </div>
            
            <div id="itemsContainer">
                <div class="order-item-card" data-idx="0" style="background:#FAFAFA; border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-bottom:1rem;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                        <span class="order-item-card-title" style="font-weight:700; font-size:14px; color:var(--navy);">Item #1</span>
                        <button type="button" class="btn-del-item btn btn-outline btn-sm" style="display:none; color:var(--danger); border-color:var(--danger); padding:4px 10px; font-size:12px;">Hapus</button>
                    </div>
                    <div class="order-item-grid" style="display:grid; grid-template-columns: 1.8fr 1.8fr 0.8fr 1.2fr; gap:0.75rem; align-items:flex-end;">
                        <div>
                            <label class="form-label" style="font-size:12px;">Kategori</label>
                            <select name="items[0][type]" class="form-control item-type" required>
                                <option value="sepatu">Sepatu / Sendal</option>
                                <option value="tas">Tas</option>
                                <option value="topi">Topi</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size:12px;">Treatment</label>
                            <select name="items[0][treatment]" class="form-control item-treatment" required>
                                <option value="Deep Cleaning">Deep Cleaning</option>
                                <option value="Unyellowing">Unyellowing (+Rp 10.000)</option>
                                <option value="Repair">Repair (+Rp 20.000)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size:12px;">Jumlah</label>
                            <input type="number" name="items[0][qty]" class="form-control item-qty" value="1" min="1" max="50" required>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size:11px; color:var(--text-secondary); margin-bottom:4px;">Subtotal</div>
                            <div class="item-subtotal" style="font-weight: 700; font-size:15px; color: var(--primary);">Rp 25.000</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Kecepatan Pengerjaan -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                4. Kecepatan Pengerjaan
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                <label style="cursor:pointer; margin:0;">
                    <div class="waktu-card" style="border: 1.5px solid var(--primary); background: var(--primary-light); border-radius: 10px; padding: 1rem; transition: var(--transition);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                            <input type="radio" name="jenis_waktu" value="Regular" class="waktu-radio" checked>
                            <strong style="font-size:14px; color:var(--navy);">Regular (2 Hari)</strong>
                        </div>
                        <div style="font-size:12px; color:var(--text-secondary); padding-left:1.5rem;">Harga standar pengerjaan bersih maksimal.</div>
                    </div>
                </label>
                <label style="cursor:pointer; margin:0;">
                    <div class="waktu-card" style="border: 1.5px solid var(--border); background: #FFF; border-radius: 10px; padding: 1rem; transition: var(--transition);">
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                            <input type="radio" name="jenis_waktu" value="Fast Clean" class="waktu-radio">
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
                    <div style="font-weight:700; font-size:14px; color:var(--navy);" id="estimasiTanggal">-</div>
                </div>
            </div>
        </div>

        <!-- 5. Pembayaran -->
        <div style="margin-bottom: 2rem;">
            <div style="font-size: 1.05rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; color: var(--navy);">
                5. Metode & Status Pembayaran
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem;">
                <div>
                    <label class="form-label">Metode Pembayaran</label>
                    <select name="metode_pembayaran" class="form-control" id="selectMetode">
                        <option value="COD">Tunai / Walk-in Cash</option>
                        <option value="Transfer Bank">Transfer Bank (BCA / Mandiri)</option>
                        <option value="QRIS">QRIS / E-Wallet</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Status Pembayaran</label>
                    <select name="status_pembayaran" class="form-control" id="selectStatusBayar">
                        <option value="Belum Bayar">Belum Bayar (Pending)</option>
                        <option value="Lunas">Lunas (Sudah Diterima)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- 6. Catatan -->
        <div style="margin-bottom: 2rem;">
            <label class="form-label">Catatan Tambahan (Opsional)</label>
            <input type="text" name="catatan" class="form-control" placeholder="Cth: Kondisi sol lepas sebelah kanan, noda tinta di tali..." value="<?= htmlspecialchars($_POST['catatan'] ?? '') ?>">
        </div>

        <!-- Summary & Submit -->
        <div style="border-top: 1px solid var(--border); padding-top: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <div style="font-size: 0.85rem; color: var(--text-secondary);">Total Tagihan</div>
                <div id="grandTotal" style="font-size: 1.6rem; font-weight: 800; color: var(--primary);">Rp 25.000</div>
                <div id="diskonNotice" style="display:none; font-size:12px; color:var(--success); font-weight:600;"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2.25rem; font-size: 1rem; border-radius: 8px;">Simpan & Terbitkan Nota IN</button>
        </div>
    </form>
</div>

<script>
    function previewTT(input) {
        const container = document.getElementById('fotoPreviewContainer');
        container.innerHTML = '';
        if (input.files && input.files.length > 0) {
            container.style.display = 'flex';
            document.getElementById('fotoText').style.display = 'none';
            for (let i = 0; i < input.files.length; i++) {
                const img = document.createElement('img');
                img.style.width = '75px';
                img.style.height = '75px';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '6px';
                img.style.border = '1px solid var(--border)';
                container.appendChild(img);

                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                };
                reader.readAsDataURL(input.files[i]);
            }

            const helper = document.createElement('div');
            helper.style.width = '100%';
            helper.style.fontSize = '12px';
            helper.style.color = 'var(--primary)';
            helper.style.fontWeight = '600';
            helper.style.marginTop = '8px';
            helper.style.textAlign = 'center';
            helper.innerText = 'Klik area ini untuk memilih ulang foto';
            container.appendChild(helper);
        } else {
            container.style.display = 'none';
            document.getElementById('fotoText').style.display = 'block';
        }
    }

    const BASE_PRICE = { 'sepatu': 25000, 'tas': 25000, 'topi': 15000, 'sendal': 25000 };
    const TREAT_PRICE = { 'Deep Cleaning': 0, 'Unyellowing': 10000, 'Repair': 20000 };
    let currentDiscountRate = 0;
    let itemCardIndex = 0;

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

        const diskon = Math.round(subtotal * currentDiscountRate);
        const grandTotal = Math.max(0, subtotal - diskon);

        document.getElementById('grandTotal').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');

        const diskonNotice = document.getElementById('diskonNotice');
        if (diskon > 0) {
            diskonNotice.style.display = 'block';
            diskonNotice.innerText = 'Hemat member: -Rp ' + diskon.toLocaleString('id-ID');
        } else {
            diskonNotice.style.display = 'none';
        }

        // Estimasi Selesai
        const days = isFast ? 1 : 2;
        const est = new Date();
        est.setDate(est.getDate() + days);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('estimasiTanggal').innerText = est.toLocaleDateString('id-ID', options);
    }

    // Live Member Check
    let debounceTimer = null;
    function checkMemberLive(val) {
        clearTimeout(debounceTimer);
        const notice = document.getElementById('memberBadgeNotice');
        if (!val || val.length < 10) {
            currentDiscountRate = 0;
            notice.style.display = 'none';
            calcTotal();
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('/api/check_member.php?phone=' + encodeURIComponent(val))
                .then(r => r.json())
                .then(data => {
                    if (data.is_member) {
                        currentDiscountRate = (data.discount || 0) / 100;
                        notice.style.display = 'block';
                        notice.innerText = 'Terverifikasi Member ' + data.tier + ' (Diskon ' + data.discount + '%)';
                    } else {
                        currentDiscountRate = 0;
                        notice.style.display = 'none';
                    }
                    calcTotal();
                })
                .catch(() => {
                    currentDiscountRate = 0;
                    calcTotal();
                });
        }, 350);
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
        const firstCard = itemsCont.querySelector('.order-item-card').cloneNode(true);
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
    const phoneInput = document.getElementById('inputNoHp');
    if (phoneInput && phoneInput.value.length >= 10) {
        checkMemberLive(phoneInput.value);
    }
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
