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
    $nama = trim($_POST['nama_lengkap']);
    $hp = trim($_POST['no_hp']);
    $pickup_addr = trim($_POST['pickup_address']);
    $jenis_waktu = $_POST['jenis_waktu'] === 'Fast Clean' ? 'Fast Clean' : 'Regular';
    $catatan = trim($_POST['catatan']);
    $items_post = $_POST['items'] ?? [];

    if (empty($nama) || empty($hp) || empty($pickup_addr)) {
        $error = "Nama, No HP, dan Alamat wajib diisi!";
    } elseif (empty($_FILES['foto_awal']['name']) || (is_array($_FILES['foto_awal']['name']) && empty($_FILES['foto_awal']['name'][0]))) {
        $error = "Foto kondisi awal sepatu wajib diupload!";
    } else {
        // Cek/Buat User (Guest)
        $stmt = $conn->prepare("SELECT id FROM users WHERE no_hp = ?");
        $stmt->bind_param("s", $hp);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows > 0) {
            $userId = $res->fetch_assoc()['id'];
        } else {
            // Buat user guest
            $email_guest = "guest_" . preg_replace('/[^0-9]/', '', $hp) . "@footstepszs.local";
            $user_guest = "guest_" . preg_replace('/[^0-9]/', '', $hp);
            $pass_guest = password_hash(uniqid(), PASSWORD_BCRYPT);

            $ins_user = $conn->prepare("INSERT INTO users (username, email, password, nama_lengkap, no_hp, alamat, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
            $ins_user->bind_param("ssssss", $user_guest, $email_guest, $pass_guest, $nama, $hp, $pickup_addr);
            if ($ins_user->execute()) {
                $userId = $conn->insert_id;
            } else {
                $error = "Gagal memproses data pelanggan.";
            }
        }

        if (empty($error)) {
            // Proses Upload Foto
            $files = $_FILES['foto_awal'];
            $uploaded_count = is_array($files['name']) ? count($files['name']) : 1;
            $allowed   = ['jpg','jpeg','png','webp'];
            $invalid_format = false;

            for ($i = 0; $i < $uploaded_count; $i++) {
                $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed)) {
                    $invalid_format = true;
                    break;
                }
            }

            if ($invalid_format) {
                $error = "Format foto harus JPG, PNG, atau WEBP.";
            } else {
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

                $kode = generateOrderCode($conn);
                $items_json = json_encode($items_arr);
                $est = getEstimasiSelesai($jenis_waktu);

                $ins = $conn->prepare("INSERT INTO orders (user_id, kode_order, items, jenis_waktu, total_harga, pickup_address, catatan, tanggal_estimasi, status) VALUES (?,?,?,?,?,?,?,?,'Diterima')");
                $ins->bind_param("isssdsss", $userId, $kode, $items_json, $jenis_waktu, $total, $pickup_addr, $catatan, $est);

                if ($ins->execute()) {
                    $order_id = $conn->insert_id;
                    $adminId = $_SESSION['user_id'];
                    
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
                            
                            // Insert to documents as TT-1
                            $doc_name = "Tanda Terima 1 - Barang Masuk (#" . ($i + 1) . ")";
                            $insDoc = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, doc_subtype, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, ?, 'TandaTerima', 'tanda_terima', ?, 'Foto awal barang (Dibuat Manual)', CURDATE(), ?)");
                            $insDoc->bind_param("issi", $order_id, $doc_name, $file_path_db, $adminId);
                            $insDoc->execute();
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
                        'total_harga' => $total
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

                    $_SESSION['flash_success'] = "Order manual berhasil dibuat!";
                    header("Location: order_detail.php?id=$order_id");
                    exit;
                } else {
                    $error = "Gagal membuat order: " . $conn->error;
                }
            }
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
        <h2 style="font-size:1.4rem; font-weight:800;">Buat Order Manual (Walk-In)</h2>
        <a href="orders.php" class="btn btn-outline btn-sm">⬅ Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:1rem;"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="card">
        <!-- 1. Customer Data -->
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">1. Data Pelanggan</h3>
        <div class="responsive-grid" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label">Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" class="form-control" required>
            </div>
            <div>
                <label class="form-label">No HP / WA *</label>
                <input type="text" name="no_hp" class="form-control" required>
            </div>
        </div>
        <div style="margin-bottom: 2rem;">
            <label class="form-label">Alamat / Keterangan Walk-in *</label>
            <textarea name="pickup_address" class="form-control" rows="2" placeholder="Cth: Datang ke toko langsung..." required></textarea>
        </div>

        <div style="margin-bottom: 2rem;">
            <div style="border: 2px dashed var(--border); border-radius: 8px; padding: 1.5rem; text-align: center; cursor: pointer;" onclick="if(event.target.id !== 'fotoInput') document.getElementById('fotoInput').click()">
                <input type="file" name="foto_awal[]" id="fotoInput" accept="image/*" style="display:none;" multiple required onchange="previewTT(this)">
                <div id="fotoPreviewContainer" style="display:none; flex-wrap:wrap; justify-content:center; gap:10px; margin-bottom:10px;"></div>
                <div id="fotoText">📷 Klik untuk mengambil/upload foto awal (bisa pilih beberapa foto)</div>
            </div>
        </div>
        <!-- 3. Items -->
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem;">3. Item Cucian</h3>
        <div id="itemsContainer" style="margin-bottom: 1rem;">
            <div class="order-item-row">
                <div>
                    <label class="form-label">Jenis Cucian</label>
                    <select name="items[0][type]" class="form-control item-type" required>
                        <option value="sepatu">Sepatu / Sendal</option>
                        <option value="tas">Tas</option>
                        <option value="topi">Topi</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Qty</label>
                    <input type="number" name="items[0][qty]" class="form-control item-qty" value="1" min="1" required>
                </div>
                <div>
                    <label class="form-label">Treatment</label>
                    <select name="items[0][treatment]" class="form-control item-treatment" required>
                        <option value="Deep Cleaning">Deep Cleaning</option>
                        <option value="Unyellowing">Unyellowing</option>
                        <option value="Repair">Repair</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <div class="item-subtotal" style="font-weight: 700; color: var(--primary);">Rp 25.000</div>
                </div>
            </div>
        </div>
        <button type="button" id="addItemBtn" class="btn btn-outline btn-sm mb-3">+ Tambah Item</button>

        <!-- 4. Pilihan Waktu -->
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.5rem; margin-top:1rem;">4. Kecepatan Pengerjaan</h3>
        <div class="responsive-grid" style="margin-bottom: 1.5rem;">
            <label style="cursor:pointer;"><input type="radio" name="jenis_waktu" value="Regular" class="waktu-radio" checked> Regular (Harga Normal)</label>
            <label style="cursor:pointer;"><input type="radio" name="jenis_waktu" value="Fast Clean" class="waktu-radio"> Fast Clean (+Rp 10rb/item)</label>
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
            <input type="text" name="catatan" class="form-control" placeholder="Cth: Tolong hati-hati pada tali sepatu...">
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border); padding-top: 1.5rem;">
            <div>
                <div style="font-size: 0.9rem; color: var(--text-secondary);">Total Harga</div>
                <div id="grandTotal" style="font-size: 1.5rem; font-weight: 800; color: var(--primary);">Rp 25.000</div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 0.8rem 2rem; font-size: 1.1rem; border-radius: 8px;">Simpan Order</button>
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
                img.style.maxHeight = '100px';
                img.style.margin = '5px';
                img.style.borderRadius = '4px';
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
            helper.style.fontWeight = '700';
            helper.style.marginTop = '10px';
            helper.style.textAlign = 'center';
            helper.innerText = '🔄 Klik/Tap di sini untuk memilih ulang foto';
            container.appendChild(helper);
        } else {
            container.style.display = 'none';
            document.getElementById('fotoText').style.display = 'block';
        }
    }

    const BASE_PRICE = { 'sepatu': 25000, 'tas': 25000, 'topi': 15000, 'sendal': 25000 };
    const TREAT_PRICE = { 'Deep Cleaning': 0, 'Unyellowing': 10000, 'Repair': 20000 };
    let itemIdx = 0;

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
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.innerText = 'Hapus';
        removeBtn.className = 'btn btn-outline btn-sm';
        removeBtn.style.gridColumn = '1 / -1';
        removeBtn.style.color = 'var(--danger)';
        removeBtn.style.borderColor = 'var(--danger)';
        removeBtn.onclick = function() { this.closest('.order-item-row').remove(); calcTotal(); };
        
        firstRow.appendChild(removeBtn);
        container.appendChild(firstRow);
        calcTotal();
    });
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
