<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isCustomer()) {
    header('Location: /login.php');
    exit;
}

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId       = (int)$_SESSION['user_id'];
    $jenis_waktu  = $_POST['jenis_waktu'] === 'Fast Clean' ? 'Fast Clean' : 'Regular';
    $pickup_addr  = trim($_POST['pickup_address']);
    $catatan      = trim($_POST['catatan']);
    $items_post   = $_POST['items'] ?? [];

    if (empty($items_post)) { $error = "Tambahkan minimal 1 item cucian."; }
    elseif (empty($pickup_addr)) { $error = "Alamat pick up wajib diisi!"; }
    elseif (empty($_FILES['foto_awal']['name']) || (is_array($_FILES['foto_awal']['name']) && empty($_FILES['foto_awal']['name'][0]))) { $error = "Foto kondisi awal sepatu wajib diupload!"; }
    else {
        // Upload Foto
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
            $PRICES     = ['sepatu' => 25000, 'sendal' => 25000, 'tas' => 25000, 'topi' => 15000];
            $TREAT_EXTRA = ['Deep Cleaning' => 0, 'Unyellowing' => 10000, 'Repair' => 20000];
            $FAST_EXTRA  = ($jenis_waktu === 'Fast Clean') ? 10000 : 0;

            $items_arr  = [];
            $total      = 0;

            foreach ($items_post as $idx => $item) {
                $type = $item['type'] ?? '';
                if (!isset($PRICES[$type])) continue;
                $qty   = max(1, (int)($item['qty'] ?? 1));
                $treat = $item['treatment'] ?? 'Deep Cleaning';
                $price = ($PRICES[$type] + ($TREAT_EXTRA[$treat] ?? 0) + $FAST_EXTRA);
                $sub   = $price * $qty;
                $total += $sub;
                $items_arr[] = ['type' => $type, 'qty' => $qty, 'treatment' => $treat, 'harga_satuan' => $price, 'subtotal' => $sub];
            }

            $kode       = generateOrderCode($conn);
            $items_json = json_encode($items_arr);
            $est        = getEstimasiSelesai($jenis_waktu);

            $ins = $conn->prepare("INSERT INTO orders (user_id, kode_order, items, jenis_waktu, total_harga, pickup_address, catatan, tanggal_estimasi, status) VALUES (?,?,?,?,?,?,?,?,'Menunggu')");
            $ins->bind_param("isssdsss", $userId, $kode, $items_json, $jenis_waktu, $total, $pickup_addr, $catatan, $est);

            if ($ins->execute()) {
                $order_id = $conn->insert_id;
                
                // Process File Upload for TT-1
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
                        $insDoc = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, doc_subtype, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, ?, 'TandaTerima', 'tanda_terima', ?, 'Foto awal kondisi barang saat order dibuat', CURDATE(), ?)");
                        $insDoc->bind_param("issi", $order_id, $doc_name, $file_path_db, $userId);
                        $insDoc->execute();
                    }
                }
                
                // For backward compatibility in templates
                $foto_awal_url = !empty($foto_awal_urls) ? $foto_awal_urls[0] : '';

                // Generate Nota IN
                $customer = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();
                $order = [
                    'kode_order' => $kode,
                    'tanggal_order' => date('Y-m-d H:i:s'),
                    'nama_lengkap' => $customer['nama_lengkap'],
                    'no_hp' => $customer['no_hp'],
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
                
                // Save Nota IN to documents so it shows in DMS
                $insNota = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, 'Nota Pesanan IN', 'Generated', ?, 'Nota Order Masuk', CURDATE(), ?)");
                $insNota->bind_param("isi", $order_id, $nota_db_path, $userId);
                $insNota->execute();

                // Notify admin
                addNotification($conn, 0, "Order baru dari " . $_SESSION['nama'] . " — $kode (" . formatRupiah($total) . ")", "/admin/order_detail.php?id=$order_id");
                
                // Send automatic WA notification to Customer via Fonnte
                $notaUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $nota_db_path;
                $cust_pesan_wa = "Halo {$customer['nama_lengkap']},\n\nTerima kasih telah memesan di Footstepszs!\n\nPesanan Anda dengan kode *{$kode}* telah berhasil kami terima.\n\nBerikut link detail Nota Pesanan (IN) Anda:\n👉 {$notaUrl}\n\nLayanan: {$jenis_waktu}\nEstimasi Selesai: " . date('d F Y', strtotime($est)) . "\nTotal Pembayaran: " . formatRupiah($total) . "\n\nKami akan segera menjemput / memproses pesanan Anda. Terima kasih! 👟";
                sendWhatsAppNotification($customer['no_hp'], $cust_pesan_wa);

                // Redirect to success page
                header("Location: /customer/order_success.php?kode=" . urlencode($kode) . "&nota=" . urlencode($nota_db_path));
                exit;
            } else {
                $error = "Gagal membuat order. Coba lagi. Detail: " . $ins->error;
            }
        }
    }
}

$pageTitle = 'Buat Pesanan';
require_once __DIR__ . '/../includes/customer_header.php';

// Prefill alamat dari profil
$userAddr = $conn->query("SELECT alamat FROM users WHERE id=" . (int)$_SESSION['user_id'])->fetch_assoc()['alamat'] ?? '';
?>

<style>
/* Responsive CSS Overrides */
.order-container { max-width: 800px; margin: 0 auto; }
.item-row { background: var(--gray-50); padding: 15px; border-radius: 8px; margin-bottom: 15px; border: 1px solid var(--gray-200); position: relative; }
.item-row .remove-item-btn { position: absolute; top: 10px; right: 10px; background: none; border: none; color: var(--danger); cursor: pointer; font-size: 18px; display: none; }
.item-row:not(:first-child) .remove-item-btn { display: block; }
.item-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }

@media (min-width: 640px) {
    .item-grid { grid-template-columns: 2fr 1fr 2fr; }
    .waktu-grid { grid-template-columns: 1fr 1fr; }
}
.file-upload-box {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    background: var(--gray-50);
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}
.file-upload-box:hover { border-color: var(--primary); background: var(--primary-light); }
.file-upload-content { position: relative; z-index: 2; }
.file-input-hidden { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 20; }
.preview-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 1rem; position: relative; z-index: 12; }
.preview-container img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }
</style>

<div class="order-container">
    <div style="margin-bottom:1.5rem;">
        <h2 style="font-size:1.4rem;font-weight:800;">Buat Pesanan Baru</h2>
        <p style="color:var(--text-secondary);font-size:14px">Isi form di bawah ini dengan lengkap untuk memesan layanan.</p>
    </div>

    <?php if ($error): ?><div class="alert alert-danger" data-auto-close>⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST" id="orderForm" enctype="multipart/form-data">
        
        <!-- Section 1: Upload Foto -->
        <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:15px;margin-bottom:1rem;color:var(--primary);font-weight:700;">1. Foto Kondisi Barang 📸</h3>
            <p style="font-size:12px;color:var(--text-secondary);margin-bottom:1rem;">Wajib melampirkan foto sepatu/barang yang akan dicuci sebelum diserahkan kepada kami. Foto ini akan masuk ke dalam Nota Anda.</p>
            
            <div class="file-upload-box" id="fotoBox">
                <input type="file" name="foto_awal[]" accept="image/jpeg,image/png,image/webp" class="file-input-hidden" id="fotoInput" multiple required>
                <div class="file-upload-content" id="fotoContent">
                    <div style="font-size:2rem;margin-bottom:0.5rem">📷</div>
                    <div style="font-weight:700;font-size:14px;color:var(--text-primary)">Klik atau Tap untuk Upload Foto</div>
                    <div style="font-size:12px;color:var(--text-light);margin-top:4px">Format: JPG, PNG, WEBP (Bisa pilih lebih dari 1 foto)</div>
                </div>
                <div id="fotoPreviewContainer" class="preview-container" style="display:none;"></div>
            </div>
        </div>

        <!-- Section 2: Items -->
        <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:15px;margin-bottom:1rem;color:var(--primary);font-weight:700;">2. Item Cucian 🛒</h3>
            <div id="itemsContainer">
                <div class="item-row" data-idx="0">
                    <button type="button" class="remove-item-btn">✖</button>
                    <div class="item-grid">
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Jenis Cucian</label>
                            <select name="items[0][type]" class="form-control item-type" required>
                                <option value="sepatu">Sepatu / Sendal — Rp 25.000</option>
                                <option value="tas">Tas — Rp 25.000</option>
                                <option value="topi">Topi — Rp 15.000</option>
                            </select>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Jumlah</label>
                            <input type="number" name="items[0][qty]" class="form-control item-qty" value="1" min="1" max="50" required>
                        </div>
                        <div class="form-group" style="margin:0">
                            <label class="form-label">Treatment</label>
                            <select name="items[0][treatment]" class="form-control item-treatment" required>
                                <option value="Deep Cleaning">Deep Cleaning (termasuk)</option>
                                <option value="Unyellowing">Unyellowing (+Rp 10.000)</option>
                                <option value="Repair">Repair (+Rp 20.000)</option>
                            </select>
                        </div>
                    </div>
                    <div style="text-align:right;margin-top:10px;padding-top:10px;border-top:1px dashed #E5E7EB;">
                        <span class="form-label" style="display:inline;margin-right:10px;">Subtotal:</span>
                        <span class="item-subtotal" style="font-weight:800;font-size:15px;color:var(--primary)">Rp 25.000</span>
                    </div>
                </div>
            </div>
            <button type="button" id="addItemBtn" class="btn btn-outline w-100" style="margin-top:0.75rem;border-style:dashed;">+ Tambah Item Lain</button>
        </div>

        <!-- Section 3: Time Option -->
        <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:15px;margin-bottom:1rem;color:var(--primary);font-weight:700;">3. Pilihan Waktu ⏱</h3>
            <div class="waktu-grid" style="display:grid;gap:1rem;">
                <label style="cursor:pointer">
                    <input type="radio" name="jenis_waktu" id="waktu_regular" value="Regular" checked style="display:none" class="waktu-radio">
                    <div class="waktu-card" style="border:2px solid var(--primary);background:var(--primary-light);border-radius:12px;padding:1.25rem;text-align:center;transition:all 0.2s">
                        <div style="font-size:2rem;margin-bottom:0.5rem">📅</div>
                        <div style="font-weight:800;font-size:16px">Regular</div>
                        <div style="font-size:13px;color:var(--text-secondary);margin:0.25rem 0">Estimasi selesai <strong>2 hari</strong></div>
                        <div style="font-size:13px;font-weight:700;color:var(--primary)">Harga Normal</div>
                    </div>
                </label>
                <label style="cursor:pointer">
                    <input type="radio" name="jenis_waktu" id="waktu_fast" value="Fast Clean" style="display:none" class="waktu-radio">
                    <div class="waktu-card" style="border:2px solid var(--border);border-radius:12px;padding:1.25rem;text-align:center;transition:all 0.2s">
                        <div style="font-size:2rem;margin-bottom:0.5rem">⚡</div>
                        <div style="font-weight:800;font-size:16px">Fast Clean</div>
                        <div style="font-size:13px;color:var(--text-secondary);margin:0.25rem 0">Estimasi selesai <strong>1 hari</strong></div>
                        <div style="font-size:13px;font-weight:700;color:var(--warning)">+Rp 10.000/item</div>
                    </div>
                </label>
            </div>
            
            <div class="estimation-box" style="margin-top:1.5rem;">
                <div class="estimation-icon">📅</div>
                <div>
                    <div class="estimation-label">Estimasi Selesai</div>
                    <div class="estimation-value" id="estimasiTanggal">-</div>
                </div>
            </div>
        </div>

        <!-- Section 4: Pickup Info -->
        <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:15px;margin-bottom:1rem;color:var(--primary);font-weight:700;">4. Informasi Pick Up 📍</h3>
            <div class="form-group">
                <label class="form-label">Alamat Lengkap *</label>
                <textarea name="pickup_address" class="form-control" rows="3" placeholder="Masukkan alamat lengkap untuk penjemputan / pengiriman..." required><?= htmlspecialchars($userAddr) ?></textarea>
                <span class="form-hint" style="color:var(--success)">💡 Free pick up & delivery untuk area Malang Kota</span>
            </div>
            <div class="form-group">
                <label class="form-label">Catatan (Opsional)</label>
                <input type="text" name="catatan" class="form-control" placeholder="Contoh: Tolong prioritas sol yang kuning">
            </div>
        </div>

        <!-- Total & Submit -->
        <div class="order-total-bar" style="margin-bottom:1.5rem;position:sticky;bottom:20px;z-index:99;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
            <div>
                <div style="font-size:12px;color:rgba(255,255,255,0.7)">Estimasi Pembayaran</div>
                <div class="order-total-amount" id="grandTotal" style="font-size:1.4rem;font-weight:800;">Rp 25.000</div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:10px 20px;font-weight:800;box-shadow:0 4px 6px rgba(0,0,0,0.3)">Buat Pesanan 🚀</button>
        </div>
    </form>
</div>

<style>
.waktu-radio:checked + .waktu-card { border-color: var(--primary) !important; background: var(--primary-light) !important; }
</style>
<script>
// Image Preview
document.getElementById('fotoInput').addEventListener('change', function(e) {
    const container = document.getElementById('fotoPreviewContainer');
    container.innerHTML = '';
    if(this.files && this.files.length > 0) {
        document.getElementById('fotoContent').style.display = 'none';
        container.style.display = 'flex';
        for (let i = 0; i < this.files.length; i++) {
            const img = document.createElement('img');
            img.style.maxHeight = '100px';
            img.style.margin = '5px';
            img.style.borderRadius = '4px';
            container.appendChild(img);

            let reader = new FileReader();
            reader.onload = function(event) {
                img.src = event.target.result;
            }
            reader.readAsDataURL(this.files[i]);
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
        document.getElementById('fotoContent').style.display = 'block';
        container.style.display = 'none';
    }
});

// Dynamic Items
let itemIdx = 0;
const PRICES = {'sepatu':25000, 'sendal':25000, 'tas':25000, 'topi':15000};
const TREAT_EXTRA = {'Deep Cleaning':0, 'Unyellowing':10000, 'Repair':20000};

function calcTotal() {
    let total = 0;
    const isFast = document.getElementById('waktu_fast').checked;
    const fastExtra = isFast ? 10000 : 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const type = row.querySelector('.item-type').value;
        const qty = parseInt(row.querySelector('.item-qty').value) || 1;
        const treat = row.querySelector('.item-treatment').value;
        
        let price = (PRICES[type] || 0) + (TREAT_EXTRA[treat] || 0) + fastExtra;
        let sub = price * qty;
        total += sub;
        row.querySelector('.item-subtotal').innerText = 'Rp ' + sub.toLocaleString('id-ID');
    });
    
    document.getElementById('grandTotal').innerText = 'Rp ' + total.toLocaleString('id-ID');

    // Update estimation date
    const days = isFast ? 1 : 2;
    const est = new Date();
    est.setDate(est.getDate() + days);
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('estimasiTanggal').innerText = est.toLocaleDateString('id-ID', options);
}

document.getElementById('itemsContainer').addEventListener('change', calcTotal);
document.getElementById('itemsContainer').addEventListener('input', calcTotal);

document.querySelectorAll('.waktu-radio').forEach(r => {
    r.addEventListener('change', () => {
        document.querySelectorAll('.waktu-card').forEach(c => {
            c.style.borderColor = 'var(--border)';
            c.style.background  = 'white';
        });
        r.nextElementSibling.style.borderColor = 'var(--primary)';
        r.nextElementSibling.style.background  = 'var(--primary-light)';
        calcTotal();
    });
});

// Initialize estimation date on load
calcTotal();

document.getElementById('addItemBtn').addEventListener('click', () => {
    itemIdx++;
    const container = document.getElementById('itemsContainer');
    const firstRow = container.querySelector('.item-row').cloneNode(true);
    firstRow.dataset.idx = itemIdx;
    
    // Update names
    firstRow.querySelector('.item-type').name = `items[${itemIdx}][type]`;
    firstRow.querySelector('.item-qty').name = `items[${itemIdx}][qty]`;
    firstRow.querySelector('.item-qty').value = 1;
    firstRow.querySelector('.item-treatment').name = `items[${itemIdx}][treatment]`;
    
    // Attach remove event
    firstRow.querySelector('.remove-item-btn').addEventListener('click', function() {
        firstRow.remove();
        calcTotal();
    });
    
    container.appendChild(firstRow);
    calcTotal();
});

document.querySelectorAll('.remove-item-btn').forEach(btn => {
    btn.addEventListener('click', function() { this.closest('.item-row').remove(); calcTotal(); });
});
</script>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
