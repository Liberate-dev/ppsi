<?php
session_start();
require_once __DIR__ . '/includes/koneksi.php';

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
                $items_text_wa = "";

                foreach ($items_post as $idx => $item) {
                    $type = $item['type'] ?? '';
                    if (!isset($PRICES[$type]))
                        continue;
                    $qty = max(1, (int) ($item['qty'] ?? 1));
                    $treat = $item['treatment'] ?? 'Deep Cleaning';
                    $price = ($PRICES[$type] + ($TREAT_EXTRA[$treat] ?? 0) + $FAST_EXTRA);
                    $sub = $price * $qty;
                    $total += $sub;
                    $items_arr[] = ['type' => $type, 'qty' => $qty, 'treatment' => $treat, 'harga_satuan' => $price, 'subtotal' => $sub];
                    $items_text_wa .= "- $qty $type ($treat)\n";
                }

                $kode = generateOrderCode($conn);
                $items_json = json_encode($items_arr);
                $est = getEstimasiSelesai($jenis_waktu);

                $ins = $conn->prepare("INSERT INTO orders (user_id, kode_order, items, jenis_waktu, total_harga, pickup_address, catatan, tanggal_estimasi, status) VALUES (?,?,?,?,?,?,?,?,'Menunggu')");
                $ins->bind_param("isssdsss", $userId, $kode, $items_json, $jenis_waktu, $total, $pickup_addr, $catatan, $est);

                if ($ins->execute()) {
                    $order_id = $conn->insert_id;
                    
                    // Upload File TT-1
                    $target_dir = __DIR__ . '/uploads/documents/';
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
                            $insDoc = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, doc_subtype, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, ?, 'TandaTerima', 'tanda_terima', ?, 'Foto awal kondisi barang saat order dibuat (Guest)', CURDATE(), ?)");
                            $insDoc->bind_param("issi", $order_id, $doc_name, $file_path_db, $userId);
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
                    include __DIR__ . '/templates/invoice_in.php';
                    $html_content = ob_get_clean();
                    
                    $gen_dir = __DIR__ . '/uploads/documents/generated/';
                    if (!is_dir($gen_dir)) mkdir($gen_dir, 0777, true);
                    $nota_filename = 'NotaIN_' . $kode . '.html';
                    file_put_contents($gen_dir . $nota_filename, $html_content);
                    $nota_db_path = 'uploads/documents/generated/' . $nota_filename;
                    
                    $insNota = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, 'Nota Pesanan IN', 'Generated', ?, 'Nota Order Masuk', CURDATE(), ?)");
                    $insNota->bind_param("isi", $order_id, $nota_db_path, $userId);
                    $insNota->execute();

                    $notaUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $nota_db_path;

                    // Notify admin
                    addNotification($conn, 0, "Pesanan Baru (Guest) dari $nama — $kode", "/admin/order_detail.php?id=$order_id");

                    // Send automatic WA notification to Customer via Fonnte
                    $cust_pesan_wa = "Halo {$nama},\n\nTerima kasih telah memesan di Footstepszs!\n\nPesanan Anda dengan kode *{$kode}* telah berhasil kami terima.\n\nBerikut link detail Nota Pesanan (IN) Anda:\n👉 {$notaUrl}\n\nLayanan: {$jenis_waktu}\nEstimasi Selesai: " . date('d F Y', strtotime($est)) . "\nTotal Pembayaran: " . formatRupiah($total) . "\n\nKami akan segera menjemput / memproses pesanan Anda. Terima kasih! 👟";
                    sendWhatsAppNotification($hp, $cust_pesan_wa);

                    // Redirect ke WhatsApp Admin dengan format pesan detail & Link Nota IN
                    $admin_wa = "6289681276080";
                    $total_rp = "Rp " . number_format($total, 0, ',', '.');
                    $catatan_wa = $catatan ? $catatan : '-';
                    $pesan_wa = "Halo Admin Footstepszs!\n\nSaya baru saja membuat pesanan laundry:\n\n*Detail Pesanan:*\n- Kode: $kode\n- Nama: $nama\n- Alamat: $pickup_addr\n- Waktu: $jenis_waktu\n\n*Item:*\n$items_text_wa\n*Total: $total_rp*\n\nCatatan: $catatan_wa\n\nBerikut Nota Pesanan (IN) beserta bukti foto kondisi awal barang:\n👉 {$notaUrl}\n\nMohon segera diproses ya!";
                    $wa_link = "https://wa.me/$admin_wa?text=" . rawurlencode($pesan_wa);

                    header("Location: $wa_link");
                    exit;
                } else {
                    $error = "Gagal membuat pesanan. Coba lagi. Detail: " . $ins->error;
                }
            }
        }
    }
}

$pageTitle = 'Pesan Layanan';
include __DIR__ . '/includes/header_public.php';
?>

<style>
.file-upload-box {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 2rem 1rem;
    text-align: center;
    background: #f9fafb;
    cursor: pointer;
    transition: all 0.2s;
    position: relative;
    overflow: hidden;
}
.file-upload-box:hover { border-color: var(--primary); background: #f3f4f6; }
.file-upload-content { position: relative; z-index: 2; }
.file-input-hidden { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 20; }
.preview-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 1rem; position: relative; z-index: 12; }
.preview-container img { width: 80px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border); }

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

<div class="container" style="padding: 4rem 1rem; max-width: 800px; margin: 0 auto;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem;">Pesan Tanpa Ribet 🚀</h1>
        <p style="color: var(--text-secondary);">Isi data dan pesananmu, lalu kami yang jemput!</p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"
            style="margin-bottom: 1.5rem; background: #fee2e2; color: #b91c1c; padding: 1rem; border-radius: 8px;">⚠️
            <?= htmlspecialchars($error) ?>
        </div><?php endif; ?>

    <form method="POST" id="guestOrderForm" class="card" style="padding: 2rem;" enctype="multipart/form-data">
        
        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">1. Data Diri & Pengiriman</h3>
        <div class="responsive-grid" style="margin-bottom: 1rem;">
            <div>
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Nama Lengkap *</label>
                <input type="text" name="nama_lengkap" class="form-control"
                    style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
            </div>
            <div>
                <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">No HP / WA *</label>
                <input type="text" name="no_hp" class="form-control"
                    style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
            </div>
        </div>
        <div style="margin-bottom: 2rem;">
            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Alamat Lengkap (Pick Up & Delivery) *</label>
            <textarea name="pickup_address" class="form-control" rows="3"
                style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);"
                placeholder="Jalan, RT/RW, Patokan..." required></textarea>
        </div>

        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">2. Foto Kondisi Barang 📸</h3>
        <div style="margin-bottom: 2rem;">
            <p style="font-size:12px;color:var(--text-secondary);margin-bottom:1rem;">Wajib melampirkan foto sepatu/barang yang akan dicuci sebelum diserahkan kepada kami. Foto ini otomatis masuk ke Nota Anda.</p>
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

        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">3. Item Cucian</h3>
        <div id="itemsContainerGuest" style="margin-bottom: 1rem;">
            <div class="order-item-row">
                <div>
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;">Jenis Cucian</label>
                    <select name="items[0][type]" class="form-control item-type"
                        style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
                        <option value="sepatu">Sepatu / Sendal — Rp 25.000</option>
                        <option value="tas">Tas — Rp 25.000</option>
                        <option value="topi">Topi — Rp 15.000</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;">Jumlah</label>
                    <input type="number" name="items[0][qty]" class="form-control item-qty" value="1" min="1"
                        style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
                </div>
                <div>
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.9rem;">Treatment Tambahan</label>
                    <select name="items[0][treatment]" class="form-control item-treatment"
                        style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);" required>
                        <option value="Deep Cleaning">Deep Cleaning (Gratis)</option>
                        <option value="Unyellowing">Unyellowing (+Rp 10.000)</option>
                        <option value="Repair">Repair (+Rp 20.000)</option>
                    </select>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;">Subtotal</div>
                    <div class="item-subtotal" style="font-weight: 700; color: var(--primary);">Rp 25.000</div>
                </div>
            </div>
        </div>
        <button type="button" id="addGuestItemBtn" class="btn btn-outline"
            style="padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 2rem;">+ Tambah Barang Lagi</button>

        <h3 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 1rem;">4. Kecepatan Pengerjaan</h3>
        <div class="responsive-grid" style="margin-bottom: 1.5rem;">
            <label style="cursor: pointer;">
                <input type="radio" name="jenis_waktu" value="Regular" checked style="display: none;" class="guest-waktu-radio">
                <div class="guest-waktu-card"
                    style="border: 2px solid var(--primary); background: var(--primary-light); border-radius: 12px; padding: 1.25rem; text-align: center; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">📅</div>
                    <div style="font-weight: 800;">Regular (2 Hari)</div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Harga Normal</div>
                </div>
            </label>
            <label style="cursor: pointer;">
                <input type="radio" name="jenis_waktu" value="Fast Clean" style="display: none;" class="guest-waktu-radio">
                <div class="guest-waktu-card"
                    style="border: 2px solid var(--border); border-radius: 12px; padding: 1.25rem; text-align: center; transition: all 0.2s;">
                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">⚡</div>
                    <div style="font-weight: 800;">Fast Clean (1 Hari)</div>
                    <div style="font-size: 0.85rem; color: var(--warning);">+Rp 10.000/item</div>
                </div>
            </label>
        </div>

        <div class="estimation-box" style="margin-bottom: 2rem;">
            <div class="estimation-icon">📅</div>
            <div>
                <div class="estimation-label">Estimasi Selesai</div>
                <div class="estimation-value" id="estimasiTanggal">-</div>
            </div>
        </div>

        <div style="margin-bottom: 2rem;">
            <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-weight: 600;">Catatan Tambahan (Opsional)</label>
            <input type="text" name="catatan" class="form-control"
                style="width: 100%; padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border);"
                placeholder="Contoh: Tolong hati-hati pada logo...">
        </div>

        <div class="order-total-bar" style="margin-top: 2rem; margin-bottom: 1.5rem; position: sticky; bottom: 20px; z-index: 99; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <div>
                <div style="font-size: 12px; color: rgba(255,255,255,0.7)">Total Estimasi</div>
                <div id="guestGrandTotal" class="order-total-amount">Rp 25.000</div>
            </div>
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-weight: 800; box-shadow: 0 4px 6px rgba(0,0,0,0.3)">Pesan & Generate Nota 💬</button>
        </div>
    </form>
</div>

<script>
    // Harga dasar
    const BASE_PRICE = { 'sepatu': 25000, 'tas': 25000, 'topi': 15000, 'sendal': 25000 };
    const TREAT_PRICE = { 'Deep Cleaning': 0, 'Unyellowing': 10000, 'Repair': 20000 };
    let guestItemIdx = 0;

    function calcGuestTotal() {
        let grandTotal = 0;
        const isFast = document.querySelector('input[name="jenis_waktu"]:checked').value === 'Fast Clean';
        const fastPrice = isFast ? 10000 : 0;

        document.querySelectorAll('.order-item-row').forEach(row => {
            const type = row.querySelector('.item-type').value;
            const treat = row.querySelector('.item-treatment').value;
            const qty = parseInt(row.querySelector('.item-qty').value) || 1;

            const base = BASE_PRICE[type] || 0;
            const treatCost = TREAT_PRICE[treat] || 0;

            const pricePerItem = base + treatCost + fastPrice;
            const subtotal = pricePerItem * qty;

            row.querySelector('.item-subtotal').innerText = 'Rp ' + subtotal.toLocaleString('id-ID');
            grandTotal += subtotal;
        });

        document.getElementById('guestGrandTotal').innerText = 'Rp ' + grandTotal.toLocaleString('id-ID');

        // Update estimation date
        const days = isFast ? 1 : 2;
        const est = new Date();
        est.setDate(est.getDate() + days);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('estimasiTanggal').innerText = est.toLocaleDateString('id-ID', options);
    }

    // Event Listeners
    document.getElementById('itemsContainerGuest').addEventListener('change', calcGuestTotal);
    document.getElementById('itemsContainerGuest').addEventListener('input', calcGuestTotal);
    document.querySelectorAll('.guest-waktu-radio').forEach(r => {
        r.addEventListener('change', () => {
            document.querySelectorAll('.guest-waktu-card').forEach(c => {
                c.style.borderColor = 'var(--border)';
                c.style.background = 'white';
            });
            r.nextElementSibling.style.borderColor = 'var(--primary)';
            r.nextElementSibling.style.background = 'var(--primary-light)';
            calcGuestTotal();
        });
    });

    // Initialize on load
    calcGuestTotal();

    document.getElementById('addGuestItemBtn').addEventListener('click', () => {
        guestItemIdx++;
        const container = document.getElementById('itemsContainerGuest');
        const firstRow = container.querySelector('.order-item-row').cloneNode(true);
        
        firstRow.querySelector('.item-type').name = `items[${guestItemIdx}][type]`;
        firstRow.querySelector('.item-qty').name = `items[${guestItemIdx}][qty]`;
        firstRow.querySelector('.item-qty').value = 1;
        firstRow.querySelector('.item-treatment').name = `items[${guestItemIdx}][treatment]`;
        
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.innerText = 'Hapus';
        removeBtn.className = 'btn btn-outline btn-sm';
        removeBtn.style.gridColumn = '1 / -1';
        removeBtn.style.color = 'var(--danger)';
        removeBtn.style.borderColor = 'var(--danger)';
        
        removeBtn.onclick = function() {
            this.closest('.order-item-row').remove();
            calcGuestTotal();
        };
        
        firstRow.appendChild(removeBtn);
        container.appendChild(firstRow);
        calcGuestTotal();
    });

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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>