<?php



session_start();



require_once __DIR__ . '/includes/koneksi.php';







$success = '';



$error = '';



$submitted_order = null;







// TRIGGER DEMO PEMBAYARAN (QRIS / TRANSFER BANK)



if (isset($_GET['demo_pay']) && !empty($_GET['demo_pay'])) {



    $demoKode = trim($_GET['demo_pay']);



    $simResult = simulateOrderPayment($conn, $demoKode);



    if ($simResult['success']) {



        $chkOrder = $conn->prepare("SELECT o.*, u.nama_lengkap, u.no_hp FROM orders o JOIN users u ON o.user_id = u.id WHERE o.kode_order = ? LIMIT 1");



        $chkOrder->bind_param("s", $demoKode);



        $chkOrder->execute();



        $freshOrder = $chkOrder->get_result()->fetch_assoc();



        if ($freshOrder) {



            $genDoc = $conn->query("SELECT file_path FROM documents WHERE ref_type='order' AND ref_id={$freshOrder['id']} AND (nama_dokumen LIKE 'Nota Masuk%' OR nama_dokumen='Nota Pesanan IN') LIMIT 1")->fetch_assoc();



            $notaUrl = $genDoc ? '/' . $genDoc['file_path'] : '#';



            $submitted_order = [



                'id' => $freshOrder['id'],



                'kode' => $freshOrder['kode_order'],



                'nama' => $freshOrder['nama_lengkap'],



                'hp' => $freshOrder['no_hp'],



                'alamat' => $freshOrder['pickup_address'],



                'jenis_waktu' => $freshOrder['jenis_waktu'],



                'metode_bayar' => $freshOrder['metode_pembayaran'],



                'status_pembayaran' => 'Lunas',



                'subtotal' => $freshOrder['total_harga'] + ($freshOrder['diskon_member'] ?? 0),



                'diskon_member' => $freshOrder['diskon_member'] ?? 0,



                'member_tier' => $freshOrder['tier_member'] ?? null,



                'total' => $freshOrder['total_harga'],



                'nota_url' => $notaUrl,



                'deadline' => $freshOrder['payment_deadline'] ?? date('Y-m-d H:i:s', strtotime('+24 hours')),



                'items_text' => '',



                'demo_activated' => true



            ];



        }



    }



}







if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    // Sanitasi input teks



    $nama = htmlspecialchars(strip_tags(trim($_POST['nama_lengkap'] ?? '')));



    $raw_hp = trim($_POST['no_hp'] ?? '');



    $hp = preg_replace('/[^0-9]/', '', $raw_hp);



    $pickup_addr = htmlspecialchars(strip_tags(trim($_POST['pickup_address'] ?? '')));



    $jenis_waktu = ($_POST['jenis_waktu'] ?? '') === 'Fast Clean' ? 'Fast Clean' : 'Regular';



    $metode_bayar = trim($_POST['metode_pembayaran'] ?? 'COD');



    if (!in_array($metode_bayar, ['COD', 'Transfer Bank', 'QRIS'])) {



        $metode_bayar = 'COD';



    }



    $catatan = htmlspecialchars(strip_tags(trim($_POST['catatan'] ?? '')));



    $items_post = $_POST['items'] ?? [];







    // Validasi ketat



    if (empty($nama) || mb_strlen($nama) < 3) {



        $error = "Nama lengkap wajib diisi minimal 3 karakter.";



    } elseif (empty($hp) || strlen($hp) < 10 || strlen($hp) > 15) {



        $error = "Nomor WhatsApp wajib berupa angka dengan panjang 10 sampai 15 digit (contoh: 081234567890).";



    } elseif (empty($pickup_addr) || mb_strlen($pickup_addr) < 5) {



        $error = "Alamat penjemputan & pengantaran wajib diisi minimal 5 karakter.";



    } elseif (empty($_FILES['foto_awal']['name']) || (is_array($_FILES['foto_awal']['name']) && empty($_FILES['foto_awal']['name'][0]))) {



        $error = "Foto kondisi awal sepatu wajib diunggah sebagai bukti kondisi awal barang.";



    } elseif (empty($items_post) || !is_array($items_post)) {



        $error = "Tambahkan minimal 1 item cucian.";



    } else {



        // Cek/Buat User (Guest)



        $clean_hp = preg_replace('/[^0-9]/', '', $hp);



        $phone_norm = normalizePhoneNumber($clean_hp);



        $hp_alt = '62' . substr($phone_norm, 1);



        $user_guest = "guest_" . $clean_hp;







        $stmt = $conn->prepare("SELECT id FROM users WHERE no_hp = ? OR no_hp = ? OR no_hp = ? OR username = ? LIMIT 1");



        $stmt->bind_param("ssss", $clean_hp, $phone_norm, $hp_alt, $user_guest);



        $stmt->execute();



        $res = $stmt->get_result();







        if ($res->num_rows > 0) {



            $userId = $res->fetch_assoc()['id'];



        } else {



            // Buat user guest



            $email_guest = "guest_" . $hp . "@footstepszs.local";



            $user_guest = "guest_" . $hp;



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



            // Update nama lengkap & alamat user jika ada perubahan



            if ($userId) {



                $updUser = $conn->prepare("UPDATE users SET nama_lengkap = ?, alamat = ? WHERE id = ?");



                if ($updUser) {



                    $updUser->bind_param("ssi", $nama, $pickup_addr, $userId);



                    $updUser->execute();



                }



            }







            // Cek status membership via WhatsApp number



            $memberData = lookupMemberByPhone($conn, $hp);



            $isMember = $memberData['is_member'];



            $memberTier = $isMember ? $memberData['tier'] : null;



            $discountPercent = $isMember ? $memberData['discount_percent'] : 0;



            $isNewMember = false;







            // Jika belum berstatus member dan memilih mendaftar melalui form pemesanan



            $reqMemberTier = trim($_POST['daftar_member_tier'] ?? '');



            if (!$isMember && in_array($reqMemberTier, ['Bronze', 'Silver', 'Gold'])) {



                if ($metode_bayar === 'COD') {



                    $error = "Pembelian paket member tidak dapat menggunakan metode pembayaran COD. Silakan gunakan Transfer Bank atau QRIS.";



                } else {



                    $isMember = true;



                    $memberTier = $reqMemberTier;



                    $tierDiscounts = ['Bronze' => 5, 'Silver' => 10, 'Gold' => 15];



                    $discountPercent = $tierDiscounts[$reqMemberTier] ?? 5;



                    $isNewMember = true;







                    // Daftarkan ke tabel members dengan status Menunggu Pembayaran sampai order dilunasi



                    $tgl_mulai = date('Y-m-d');



                    $tgl_selesai = date('Y-m-d', strtotime('+1 month'));



                    $chkM = $conn->prepare("SELECT id FROM members WHERE user_id = ?");



                    $chkM->bind_param("i", $userId);



                    $chkM->execute();



                    $mExists = $chkM->get_result()->fetch_assoc();



                    $initMemberStatus = 'Nonaktif';



                    if ($mExists) {



                        $updM = $conn->prepare("UPDATE members SET level = ?, status = ?, tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");



                        $updM->bind_param("ssssi", $reqMemberTier, $initMemberStatus, $tgl_mulai, $tgl_selesai, $mExists['id']);



                        $updM->execute();



                    } else {



                        $insM = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?)");



                        $insM->bind_param("issss", $userId, $reqMemberTier, $tgl_mulai, $tgl_selesai, $initMemberStatus);



                        $insM->execute();



                    }



                }



            }







            // Validasi & Proses Upload Foto



            $files = $_FILES['foto_awal'];



            $uploaded_count = is_array($files['name']) ? count($files['name']) : 1;



            $allowed = ['jpg','jpeg','png','webp'];



            $max_size = 5 * 1024 * 1024; // 5 MB







            for ($i = 0; $i < $uploaded_count; $i++) {



                $file_name = is_array($files['name']) ? $files['name'][$i] : $files['name'];



                $tmp_name  = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];



                $file_size = is_array($files['size']) ? $files['size'][$i] : $files['size'];



                $file_err  = is_array($files['error']) ? $files['error'][$i] : $files['error'];







                if ($file_err !== 0) {



                    $error = "Gagal mengunggah file foto. Silakan coba kembali.";



                    break;



                }



                if ($file_size > $max_size) {



                    $error = "Ukuran file foto terlalu besar. Maksimal 5MB per foto.";



                    break;



                }







                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));



                if (!in_array($ext, $allowed)) {



                    $error = "Format foto hanya diperbolehkan JPG, PNG, atau WEBP.";



                    break;



                }







                if (function_exists('finfo_open')) {



                    $finfo = finfo_open(FILEINFO_MIME_TYPE);



                    $mime = finfo_file($finfo, $tmp_name);



                    finfo_close($finfo);



                    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {



                        $error = "File yang diunggah harus berupa gambar valid.";



                        break;



                    }



                }



            }







            if (empty($error)) {



                $PRICES = ['sepatu' => 25000, 'sendal' => 25000, 'tas' => 25000, 'topi' => 15000];



                $TREAT_EXTRA = ['Deep Cleaning' => 0, 'Unyellowing' => 10000, 'Repair' => 20000];



                $FAST_EXTRA = ($jenis_waktu === 'Fast Clean') ? 10000 : 0;







                // Ambil sisa kuota member yang tersedia



                $remainingQuota = 0;



                if ($isMember) {



                    if ($isNewMember) {



                        $remainingQuota = getTierTotalQuota($memberTier);



                    } else {



                        $qDetails = getMemberQuotaDetails($conn, $userId);



                        $remainingQuota = $qDetails['remaining_quota'] ?? 0;



                    }



                }







                $quotaPool = $remainingQuota;



                $items_arr = [];



                $subtotal_gross = 0;



                $total_item_cost = 0;



                $total_potongan_kuota = 0;



                $items_covered_by_quota = 0;



                $items_text_wa = "";







                foreach ($items_post as $idx => $item) {



                    $type = trim($item['type'] ?? '');



                    if (!isset($PRICES[$type])) continue;



                    $qty = max(1, min(50, (int)filter_var($item['qty'] ?? 1, FILTER_SANITIZE_NUMBER_INT)));



                    $treat = trim($item['treatment'] ?? 'Deep Cleaning');



                    if (!isset($TREAT_EXTRA[$treat])) $treat = 'Deep Cleaning';







                    $basePrice = $PRICES[$type];



                    $treatExtra = $TREAT_EXTRA[$treat] ?? 0;



                    $fastExtra = ($jenis_waktu === 'Fast Clean') ? 10000 : 0;



                    $fullUnitPrice = $basePrice + $treatExtra + $fastExtra;







                    $itemPayTotal = 0;



                    $coveredQty = 0;







                    for ($q = 0; $q < $qty; $q++) {



                        if ($isMember && $quotaPool > 0) {



                            $quotaPool--;



                            $items_covered_by_quota++;



                            $coveredQty++;



                            $total_potongan_kuota += $basePrice; // Cuci dasar 100% GRATIS dicover kuota







                            // Ekstra treatment / fast clean dikenai diskon member



                            $extra = $treatExtra + $fastExtra;



                            if ($discountPercent > 0 && $extra > 0) {



                                $disc = round(($extra * $discountPercent) / 100);



                                $itemPayTotal += ($extra - $disc);



                            } else {



                                $itemPayTotal += $extra;



                            }



                        } else {



                            // Di luar kuota: bayar normal minus diskon tier



                            $unitCost = $fullUnitPrice;



                            if ($isMember && $discountPercent > 0) {



                                $disc = round(($unitCost * $discountPercent) / 100);



                                $unitCost -= $disc;



                            }



                            $itemPayTotal += $unitCost;



                        }



                    }







                    $subtotal_gross += ($fullUnitPrice * $qty);



                    $total_item_cost += $itemPayTotal;







                    $items_arr[] = [



                        'type' => htmlspecialchars($type),



                        'qty' => $qty,



                        'treatment' => htmlspecialchars($treat),



                        'harga_satuan' => $fullUnitPrice,



                        'subtotal' => $itemPayTotal,



                        'covered_by_quota' => $coveredQty



                    ];







                    $covText = ($coveredQty > 0) ? " (Dicover Kuota: {$coveredQty})" : "";



                    $items_text_wa .= "• $qty " . ucfirst($type) . " ($treat){$covText}\n";



                }







                if (empty($items_arr)) {



                    $error = "Minimal satu jenis item cucian yang valid harus dipilih.";



                } else {



                    $membership_fee = 0;



                    if ($isNewMember) {



                        $tierPrices = ['Bronze' => 160000, 'Silver' => 300000, 'Gold' => 590000];



                        $membership_fee = $tierPrices[$memberTier] ?? 0;



                    }







                    $total_akhir = $membership_fee + $total_item_cost;



                    $diskon_member = $total_potongan_kuota + ($subtotal_gross - ($total_item_cost + $total_potongan_kuota));







                    // Penentuan status awal dan batas waktu pembayaran



                    if ($total_akhir <= 0) {



                        // 100% DICOVER OLEH KUOTA MEMBER: Langsung Lunas & Cuci



                        $order_status = 'Cuci';



                        $status_bayar = 'Lunas';



                        $metode_bayar = 'Kuota Member';



                        $payment_deadline = null;



                    } elseif ($metode_bayar === 'COD') {



                        $order_status = 'Cuci';



                        $status_bayar = 'Belum Lunas';



                        $payment_deadline = null;



                    } else {



                        $order_status = 'Pending Payment';



                        $status_bayar = 'Belum Lunas';



                        $payment_deadline = date('Y-m-d H:i:s', strtotime('+24 hours'));



                    }







                    $kode = generateOrderCode($conn);



                    $items_json = json_encode($items_arr);



                    $est = getEstimasiSelesai($jenis_waktu);







                    $ins = $conn->prepare("



                        INSERT INTO orders 



                        (user_id, kode_order, items, jenis_waktu, metode_pembayaran, status_pembayaran, diskon_member, tier_member, total_harga, pickup_address, catatan, payment_deadline, tanggal_estimasi, status) 



                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)



                    ");



                    $ins->bind_param("isssssdsdsssss", 



                        $userId, $kode, $items_json, $jenis_waktu, $metode_bayar, $status_bayar, 



                        $diskon_member, $memberTier, $total_akhir, $pickup_addr, $catatan, 



                        $payment_deadline, $est, $order_status



                    );







                    if ($ins->execute()) {



                        $order_id = $conn->insert_id;







                        // Simpan file Tanda Terima 1 (TT-1)



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







                                // Insert ke documents



                                $doc_name = "Tanda Terima 1 - Barang Masuk (#" . ($i + 1) . ")";



                                $insDoc = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, doc_subtype, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, ?, 'TandaTerima', 'tanda_terima', ?, 'Foto awal kondisi barang saat order dibuat (Self-Order)', CURDATE(), ?)");



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



                            'metode_pembayaran' => $metode_bayar,



                            'diskon_member' => $diskon_member,



                            'tier_member' => $memberTier,



                            'tanggal_estimasi' => $est,



                            'total_harga' => $total_akhir



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







                        // Notifikasi admin



                        addNotification($conn, 0, "Pesanan Baru dari {$nama} ({$kode}) - Metode: {$metode_bayar}", "/admin/order_detail.php?id={$order_id}");







                        // Kirim WhatsApp Otomatis ke Pelanggan



                        $cust_pesan_wa = "Halo {$nama},\n\nTerima kasih telah melakukan pemesanan di Footstepszs!\n\nPesanan Anda dengan kode *{$kode}* telah tercatat di sistem kami.\n\nDetail Pesanan:\n- Layanan: {$jenis_waktu}\n- Metode Bayar: {$metode_bayar}\n- Total Tagihan: " . formatRupiah($total_akhir);



                        if ($diskon_member > 0) {



                            $cust_pesan_wa .= " (Diskon Member {$memberTier}: " . formatRupiah($diskon_member) . ")";



                        }



                        if ($metode_bayar === 'Transfer Bank') {



                            $cust_pesan_wa .= "\n\nInstruksi Pembayaran:\nBank BCA: 1234-5678-90 a.n. Footstepszs Studio\nBatas Waktu Bayar: 24 Jam (sebelum " . date('d M Y, H:i', strtotime($payment_deadline)) . ")";



                                                } elseif ($metode_bayar === 'QRIS') {

                            $cust_pesan_wa .= "

Instruksi Pembayaran:
Silakan lakukan scan QRIS pada tautan nota digital.
Batas Waktu Bayar: 24 Jam (sebelum " . date('d M Y, H:i', strtotime($payment_deadline)) . ")";

                        } else {

                            $cust_pesan_wa .= "

Pembayaran dilakukan secara tunai (COD) saat kurir menjemput atau mengantar barang Anda.";

                        }

                        $cust_pesan_wa .= "

Nota Digital Anda:
👉 {$notaUrl}

Terima kasih!";

                        sendWhatsAppNotification($hp, $cust_pesan_wa);







                        // Siapkan konfirmasi



                        $submitted_order = [



                            'id' => $order_id,



                            'kode' => $kode,



                            'nama' => $nama,



                            'hp' => $hp,



                            'alamat' => $pickup_addr,



                            'jenis_waktu' => $jenis_waktu,



                            'metode_bayar' => $metode_bayar,



                            'status_pembayaran' => $status_bayar,



                            'subtotal' => $subtotal_gross,



                            'diskon_member' => $diskon_member,



                            'potongan_kuota' => $total_potongan_kuota,



                            'items_covered' => $items_covered_by_quota,



                            'remaining_quota' => max(0, $remainingQuota - $items_covered_by_quota),



                            'member_tier' => $memberTier,



                            'is_new_member' => $isNewMember,



                            'membership_fee' => $membership_fee,



                            'total' => $total_akhir,



                            'nota_url' => $notaUrl,



                            'deadline' => $payment_deadline,



                            'items_text' => $items_text_wa



                        ];



                    } else {



                        $error = "Gagal membuat pesanan. Detail: " . $ins->error;



                    }



                }



            }



        }



    }



}







$pageTitle = 'Pesan Layanan';



include __DIR__ . '/includes/header_public.php';



?>







<style>



.order-container {



    padding: 3rem 1.5rem 5rem;



    max-width: 860px;



    margin: 0 auto;



}



.order-header {



    text-align: center;



    margin-bottom: 2.5rem;



}



.order-header h1 {



    font-size: 2rem;



    font-weight: 800;



    letter-spacing: -0.02em;



    color: var(--navy);



    margin-bottom: 0.5rem;



}



.order-header p {



    color: var(--text-secondary);



    font-size: 0.95rem;



}



.order-card {



    background: #FFFFFF;



    border: 1px solid var(--border);



    border-radius: 16px;



    padding: 2.25rem;



    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);



    margin-bottom: 2rem;



}



.section-title-clean {



    font-size: 1.15rem;



    font-weight: 700;



    color: var(--navy);



    display: flex;



    align-items: center;



    gap: 0.75rem;



    margin-bottom: 1.25rem;



    padding-bottom: 0.75rem;



    border-bottom: 1px solid var(--gray-200);



}



.step-number {



    display: inline-flex;



    align-items: center;



    justify-content: center;



    width: 26px;



    height: 26px;



    border-radius: 50%;



    background: var(--primary);



    color: #FFF;



    font-size: 13px;



    font-weight: 700;



}



.member-badge-alert {



    display: none;



    background: #EDE9FE;



    border: 1px solid #C4B5FD;



    color: #5B21B6;



    padding: 0.75rem 1rem;



    border-radius: 10px;



    font-size: 13px;



    font-weight: 600;



    margin-top: 0.5rem;



}



.member-badge-alert.show {



    display: flex;



    align-items: center;



    gap: 8px;



}



.payment-grid {



    display: grid;



    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));



    gap: 1rem;



}



.payment-card {



    border: 2px solid var(--border);



    border-radius: 12px;



    padding: 1.25rem;



    cursor: pointer;



    transition: all 0.2s ease;



    background: #FAFAFA;



}



.payment-card:hover {



    border-color: var(--primary-light);



    background: #FFF;



}



.payment-card.active {



    border-color: var(--primary);



    background: #F5F3FF;



}



.payment-card-title {



    font-weight: 700;



    font-size: 15px;



    color: var(--navy);



    margin-bottom: 4px;



}



.payment-card-desc {



    font-size: 12px;



    color: var(--text-secondary);



    line-height: 1.4;



}



.file-upload-box {



    border: 2px dashed var(--gray-400);



    border-radius: 12px;



    padding: 2rem 1.5rem;



    text-align: center;



    background: #F9FAFB;



    cursor: pointer;



    position: relative;



    transition: all 0.2s ease;



}



.file-upload-box:hover {



    border-color: var(--primary);



    background: #F5F3FF;



}



.file-input-hidden {



    position: absolute;



    top: 0;



    left: 0;



    width: 100%;



    height: 100%;



    opacity: 0;



    cursor: pointer;



    z-index: 20;



}



.preview-container {



    display: flex;



    flex-wrap: wrap;



    justify-content: center;



    gap: 10px;



    margin-top: 1rem;



}



.preview-container img {



    width: 80px;



    height: 80px;



    object-fit: cover;



    border-radius: 8px;



    border: 1px solid var(--border);



}



.speed-grid {



    display: grid;



    grid-template-columns: 1fr 1fr;



    gap: 1rem;



}



.speed-card {



    border: 2px solid var(--border);



    border-radius: 12px;



    padding: 1.25rem;



    cursor: pointer;



    transition: all 0.2s ease;



    text-align: center;



}



.speed-card.active {



    border-color: var(--primary);



    background: #F5F3FF;



}



.bill-summary {



    background: #F9FAFB;



    border: 1px solid var(--border);



    border-radius: 12px;



    padding: 1.25rem;



    margin-top: 1.5rem;



}



.bill-row {



    display: flex;



    justify-content: space-between;



    font-size: 14px;



    color: var(--text-secondary);



    margin-bottom: 0.5rem;



}



.bill-row.total-row {



    margin-top: 0.75rem;



    padding-top: 0.75rem;



    border-top: 1px dashed var(--gray-200);



    font-size: 18px;



    font-weight: 800;



    color: var(--navy);



}



.bill-row.discount-row {



    color: #059669;



    font-weight: 600;



}



.submit-dock {



    position: sticky;



    bottom: 20px;



    background: #FFFFFF;



    border: 1px solid var(--border);



    border-radius: 16px;



    padding: 1.25rem 1.75rem;



    display: flex;



    justify-content: space-between;



    align-items: center;



    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.12);



    z-index: 99;



}



.item-subtotal-val {



    font-weight: 800;



    font-size: 15px;



    color: var(--primary);



    padding: 0.6rem 0;



    text-align: right;



}



@media (max-width: 640px) {



    .speed-grid { grid-template-columns: 1fr; }



    .submit-dock { flex-direction: column; gap: 1rem; text-align: center; }



}



.member-opt-card {



    border: 2px solid var(--border);



    border-radius: 12px;



    background: #FFFFFF;



    cursor: pointer;



    position: relative;



    transition: all 0.2s ease;



    display: block;



    user-select: none;



    box-sizing: border-box;



}



.member-opt-card:hover {



    border-color: #CBD5E1;



}



.member-opt-card.active {



    border-color: var(--primary);



    background: #F5F3FF;



    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.12);



}



.member-tier-radio {



    position: absolute;



    top: 12px;



    right: 12px;



    accent-color: var(--primary);



    margin: 0;



    width: 16px;



    height: 16px;



}



.member-opt-inner {



    padding: 1.1rem 0.9rem;



}



.btn-snk-link {



    background: transparent;



    border: 1px solid var(--border);



    border-radius: 6px;



    font-size: 11px;



    font-weight: 600;



    color: var(--primary);



    padding: 5px 8px;



    cursor: pointer;



    transition: all 0.15s;



    width: 100%;



    margin-top: 6px;



    text-align: center;



}



.btn-snk-link:hover {



    background: var(--primary);



    color: #FFFFFF;



    border-color: var(--primary);



}



.autofill-indicator {



    display: none;



    font-size: 11px;



    font-weight: 600;



    color: #059669;



    margin-left: 8px;



    background: #D1FAE5;



    padding: 2px 7px;



    border-radius: 4px;



}



@media (max-width: 640px) {



    #memberTierCards { grid-template-columns: 1fr !important; }



}



</style>







<div class="order-container">







    <?php if ($submitted_order): ?>



        <!-- ================= KONFIRMASI PEMESANAN ================= -->



        <div class="order-card" style="border-top: 6px solid var(--primary); text-align: center; padding: 3rem 2rem;">



            <div style="width: 60px; height: 60px; border-radius: 50%; background: #D1FAE5; color: #059669; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">



                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:32px;height:32px;"><polyline points="20 6 9 17 4 12"/></svg>



            </div>



            <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--navy); margin-bottom: 0.5rem;">Pesanan Berhasil Tercatat</h2>



            <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Nomor Pesanan: <strong style="color: var(--primary); font-size: 1.15rem;"><?= htmlspecialchars($submitted_order['kode']) ?></strong></p>







            <?php if (!empty($submitted_order['is_new_member'])): ?>



                <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 12px; padding: 1rem 1.25rem; max-width: 600px; margin: 0 auto 1.5rem; display: flex; align-items: center; gap: 12px; text-align: left;">



                    <div style="width: 36px; height: 36px; border-radius: 50%; background: #16A34A; color: white; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">



                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:20px;height:20px;"><polyline points="20 6 9 17 4 12"/></svg>



                    </div>



                    <div>



                        <div style="font-size: 14px; font-weight: 700; color: #166534;">Pendaftaran Member <?= htmlspecialchars($submitted_order['member_tier']) ?> Berhasil Diaktifkan!</div>



                        <div style="font-size: 12.5px; color: #15803D;">Keanggotaan Anda aktif tanpa perlu verifikasi manual admin. Diskon otomatis diterapkan pada tagihan ini.</div>



                    </div>



                </div>



            <?php endif; ?>







            <div style="background: #F9FAFB; border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; text-align: left; max-width: 600px; margin: 0 auto 2rem;">



                <div class="bill-row"><span>Nama Pelanggan</span><strong><?= htmlspecialchars($submitted_order['nama']) ?></strong></div>



                <div class="bill-row"><span>Nomor WhatsApp</span><strong><?= htmlspecialchars($submitted_order['hp']) ?></strong></div>



                <div class="bill-row"><span>Metode Pembayaran</span><strong style="color: var(--primary);"><?= htmlspecialchars($submitted_order['metode_bayar']) ?></strong></div>



                <div class="bill-row">



                    <span>Status Pembayaran</span>



                    <?php if (($submitted_order['status_pembayaran'] ?? '') === 'Lunas'): ?>



                        <span class="badge" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; font-weight:700;">LUNAS (Dicover Kuota Member)</span>



                    <?php else: ?>



                        <span class="badge" style="background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; font-weight:700;">Menunggu Pembayaran</span>



                    <?php endif; ?>



                </div>



                <?php if (!empty($submitted_order['potongan_kuota']) && $submitted_order['potongan_kuota'] > 0): ?>



                    <div class="bill-row" style="color:#059669; font-weight:700;">



                        <span>Gratis Kuota Cuci <?= !empty($submitted_order['is_new_member']) ? 'Member Baru ' : '' ?>(<?= $submitted_order['items_covered'] ?> Item)</span>



                        <span>- <?= formatRupiah($submitted_order['potongan_kuota']) ?></span>



                    </div>



                <?php endif; ?>



                <?php if (!empty($submitted_order['is_new_member']) && !empty($submitted_order['membership_fee'])): ?>



                    <div class="bill-row" style="color:var(--primary); font-weight:700;">



                        <span>Biaya Paket Member <?= htmlspecialchars($submitted_order['member_tier']) ?></span>



                        <span>+ <?= formatRupiah($submitted_order['membership_fee']) ?></span>



                    </div>



                <?php endif; ?>



                <?php if ($submitted_order['diskon_member'] > 0): ?>



                    <div class="bill-row discount-row"><span>Diskon Member (<?= htmlspecialchars($submitted_order['member_tier']) ?>)</span><span>- <?= formatRupiah($submitted_order['diskon_member']) ?></span></div>



                <?php endif; ?>



                <div class="bill-row total-row"><span>Total Tagihan</span><span><?= formatRupiah($submitted_order['total']) ?></span></div>



            </div>







            <?php if (($submitted_order['status_pembayaran'] ?? '') === 'Lunas'): ?>



                <div style="background: #ECFDF5; border: 2px solid #10B981; border-radius: 12px; padding: 1.5rem; max-width: 600px; margin: 0 auto 2rem; text-align: center;">



                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #10B981; color: white; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.75rem;">



                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:24px;height:24px;"><polyline points="20 6 9 17 4 12"/></svg>



                    </div>



                    <div style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #047857; margin-bottom: 4px;">Status Terverifikasi</div>



                    <h3 style="font-size: 18px; font-weight: 800; color: #065F46; margin-bottom: 6px;">Pembayaran <?= htmlspecialchars($submitted_order['metode_bayar']) ?> LUNAS</h3>



                    <p style="font-size: 13.5px; color: #047857; margin-bottom: 1rem; line-height: 1.5;">



                        Simulasi pembayaran berhasil! Pesanan Anda telah diverifikasi lunas dan otomatis dialihkan ke status <strong>Cuci (Sedang Dikerjakan)</strong>.



                    </p>



                    <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">



                        <span style="font-size: 12px; background: #D1FAE5; color: #065F46; padding: 5px 14px; border-radius: 100px; font-weight: 700;">Status Bayar: LUNAS</span>



                        <span style="font-size: 12px; background: #DBEAFE; color: #1E40AF; padding: 5px 14px; border-radius: 100px; font-weight: 700;">Tahap: Cuci</span>



                    </div>



                </div>



            <?php elseif ($submitted_order['metode_bayar'] === 'Transfer Bank'): ?>



                <div style="background: #EFF6FF; border: 1px solid #BFDBFE; border-radius: 12px; padding: 1.5rem; max-width: 600px; margin: 0 auto 2rem; text-align: left;">



                    <h4 style="font-weight: 700; color: #1E40AF; margin-bottom: 0.5rem;">Instruksi Transfer Bank</h4>



                    <p style="font-size: 13px; color: #1E3A8A; margin-bottom: 1rem;">Mohon lakukan transfer sebelum batas waktu 24 jam agar pesanan tidak kedaluwarsa.</p>



                    <div style="background: #FFF; padding: 1rem; border-radius: 8px; border: 1px solid #DBEAFE; font-size: 14px;">



                        <div>Bank: <strong>BCA (Bank Central Asia)</strong></div>



                        <div>No. Rekening: <strong style="font-size: 1.1rem; color: #1D4ED8;">1234-5678-90</strong></div>



                        <div>Atas Nama: <strong>Footstepszs Studio</strong></div>



                        <div>Batas Waktu: <strong><?= date('d M Y, H:i', strtotime($submitted_order['deadline'])) ?> WIB</strong></div>



                    </div>



                    <?php if (($submitted_order['status_pembayaran'] ?? '') !== 'Lunas'): ?>



                        <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px dashed rgba(30, 64, 175, 0.25); text-align: center;">



                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; color: #1E40AF; margin-bottom: 6px;">Uji Coba Sistem / Evaluasi Demo:</div>



                            <a href="/pesan.php?demo_pay=<?= urlencode($submitted_order['kode']) ?>" class="btn btn-success" style="padding: 0.65rem 1.35rem; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">



                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>



                                Simulasikan Pembayaran Berhasil (Demo)



                            </a>



                        </div>



                    <?php endif; ?>



                </div>



            <?php elseif ($submitted_order['metode_bayar'] === 'QRIS'): ?>



                <div style="background: #FDF2F8; border: 1px solid #FBCFE8; border-radius: 12px; padding: 1.5rem; max-width: 600px; margin: 0 auto 2rem; text-align: center;">



                    <h4 style="font-weight: 700; color: #9D174D; margin-bottom: 0.5rem;">Pembayaran QRIS Statis</h4>



                    <p style="font-size: 13px; color: #831843; margin-bottom: 1rem;">Scan kode QRIS di bawah ini menggunakan GoPay, OVO, Dana, BCA, atau m-Banking Anda:</p>



                    <div style="background: #FFF; display: inline-block; padding: 12px; border-radius: 10px; border: 1px solid #F472B6; margin-bottom: 1rem;">



                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=<?= urlencode("QRIS_FOOTSTEPSZS_" . $submitted_order['kode'] . "_" . $submitted_order['total']) ?>" alt="QRIS Code" style="display: block;">



                    </div>



                    <div style="font-size: 13px; color: #831843;">Batas Waktu: <strong><?= date('d M Y, H:i', strtotime($submitted_order['deadline'])) ?> WIB</strong></div>



                    <?php if (($submitted_order['status_pembayaran'] ?? '') !== 'Lunas'): ?>



                        <div style="margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px dashed rgba(157, 23, 77, 0.25); text-align: center;">



                            <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; color: #9D174D; margin-bottom: 6px;">Uji Coba Sistem / Evaluasi Demo:</div>



                            <a href="/pesan.php?demo_pay=<?= urlencode($submitted_order['kode']) ?>" class="btn btn-success" style="padding: 0.65rem 1.35rem; font-size: 13.5px; font-weight: 700; display: inline-flex; align-items: center; gap: 8px;">



                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>



                                Simulasikan Pembayaran Berhasil (Demo)



                            </a>



                        </div>



                    <?php endif; ?>



                </div>



            <?php else: ?>



                <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 12px; padding: 1.25rem; max-width: 600px; margin: 0 auto 2rem; text-align: left; font-size: 14px; color: #065F46;">



                    <strong>Metode Cash on Delivery (COD):</strong> Pembayaran akan dilakukan secara tunai kepada staf kurir kami saat penjemputan atau pengantaran barang.



                </div>



            <?php endif; ?>







            <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">



                <a href="<?= htmlspecialchars($submitted_order['nota_url']) ?>" target="_blank" class="btn btn-outline" style="padding: 0.8rem 1.5rem;">



                    Buka Nota Digital (HTML)



                </a>



                <?php



                $wa_admin = "6289681276080";



                $wa_text = "Halo Admin Footstepszs! Saya telah memesan laundry dengan kode: {$submitted_order['kode']}. Total tagihan: " . formatRupiah($submitted_order['total']) . " (" . $submitted_order['metode_bayar'] . "). Berikut tautan nota saya: " . $submitted_order['nota_url'];



                ?>



                <a href="https://wa.me/<?= $wa_admin ?>?text=<?= rawurlencode($wa_text) ?>" target="_blank" class="btn btn-primary" style="padding: 0.8rem 1.5rem;">



                    Hubungi Admin via WhatsApp



                </a>



                <a href="/index.php" class="btn btn-outline" style="padding: 0.8rem 1.5rem;">



                    Kembali ke Beranda



                </a>



            </div>



        </div>







    <?php else: ?>







        <!-- ================= FORM PEMESANAN ================= -->



        <div class="order-header">



            <h1>Formulir Pemesanan Layanan</h1>



            <p>Pemesanan langsung tanpa wajib login. Penjemputan dan pengantaran terintegrasi se-Malang Kota.</p>



        </div>







        <?php if ($error): ?>



            <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: #FEE2E2; color: #991B1B; padding: 1rem; border-radius: 8px;">



                <?= htmlspecialchars($error) ?>



            </div>



        <?php endif; ?>







        <form method="POST" id="guestOrderForm" class="order-card" enctype="multipart/form-data">



            



            <!-- SEKSI 1: DATA DIRI -->



            <div class="section-title-clean">



                <span class="step-number">1</span> Data Pelanggan & Pengiriman



            </div>



            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">



                <div>



                    <label style="display: flex; align-items: center; margin-bottom: 0.4rem; font-size: 13px; font-weight: 600;">



                        <span>Nama Lengkap *</span>



                        <span id="namaAutofillBadge" class="autofill-indicator">Otomatis Terisi</span>



                    </label>



                    <input type="text" 



                           name="nama_lengkap" 



                           id="inputNama" 



                           class="form-control" 



                           maxlength="100" 



                           required 



                           placeholder="Contoh: David Augusto"



                           value="<?= isset($_POST['nama_lengkap']) ? htmlspecialchars($_POST['nama_lengkap']) : '' ?>">



                </div>



                <div>



                    <label style="display: block; margin-bottom: 0.4rem; font-size: 13px; font-weight: 600;">Nomor WhatsApp * (Hanya Angka)</label>



                    <input type="tel" 



                           name="no_hp" 



                           id="inputNoHp" 



                           class="form-control" 



                           inputmode="numeric" 



                           pattern="[0-9]{10,15}" 



                           maxlength="15" 



                           required 



                           placeholder="081234567890"



                           autocomplete="tel"



                           value="<?= isset($_POST['no_hp']) ? preg_replace('/[^0-9]/', '', $_POST['no_hp']) : '' ?>"



                           oninput="this.value = this.value.replace(/[^0-9]/g, '');"



                           onkeypress="return (event.charCode >= 48 && event.charCode <= 57)">



                    <div id="memberBadgeAlert" class="member-badge-alert" style="display:none; margin-top: 0.5rem; background: #ECFDF5; border: 1px solid #10B981; border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 12.5px; color: #065F46;">

                        <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">

                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;stroke:#10B981;"><polyline points="20 6 9 17 4 12"/></svg>

                            <span>Status Member: <strong id="memberTierText">-</strong> <span style="background:#10B981;color:white;font-size:10px;font-weight:700;padding:2px 8px;border-radius:100px;margin-left:4px;">Aktif</span></span>

                        </div>

                        <div id="memberQuotaBadgeText" style="color: #047857; font-size: 12px;">

                            Kuota Aktif: <strong id="memberQuotaCount">-</strong> item gratis cuci • Diskon treatment <strong id="memberDiscountText">-</strong>

                        </div>

                        <div id="memberTrackerText" style="color: #047857; font-size: 12px; margin-top: 4px; font-weight: 600;">

                            ⏱️ Tracker Masa Aktif: Tersisa <span id="memberDaysLeft" style="color:#059669;font-weight:700;">-</span> (s/d <span id="memberExpiryDate">-</span>)

                        </div>

                        <div id="memberH3Warning" style="display: none; margin-top: 6px; background: #FFFBEB; border: 1px solid #F59E0B; border-radius: 6px; padding: 6px 10px; font-size: 11.5px; color: #B45309; font-weight: 700;">

                            ⚠️ Peringatan H-<span id="memberH3Days">3</span>: Masa aktif member Anda tersisa <span id="memberH3DaysText">3</span> hari lagi! Segera lakukan perpanjangan agar kuota cuci gratis & diskon tidak terhenti.

                        </div>

                    </div>



                    <!-- Notifikasi Jika Nomor Pernah Member tapi Sudah Expired (Nonaktif) -->

                    <div id="memberExpiredAlert" style="display:none; margin-top: 0.5rem; background: #FEF2F2; border: 1px solid #F87171; border-radius: 8px; padding: 0.65rem 0.85rem; font-size: 12px; color: #991B1B;">

                        <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">

                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;stroke:#DC2626;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>

                            <span>Membership Sebelumnya Telah Berakhir</span>

                        </div>

                        <div style="font-size: 12px; color: #7F1D1D; margin-top: 2px;">

                            Nomor WhatsApp ini sebelumnya terdaftar sebagai Member <strong id="expiredTierText">-</strong> yang telah berakhir pada <strong id="expiredDateText">-</strong>. Silakan pilih paket member di bawah ini untuk mengaktifkan kembali keanggotaan Anda.

                        </div>

                    </div>



                </div>



            </div>



            <div style="margin-bottom: 1.5rem;">



                <label style="display: flex; align-items: center; margin-bottom: 0.4rem; font-size: 13px; font-weight: 600;">



                    <span>Alamat Lengkap (Penjemputan & Pengantaran) *</span>



                    <span id="alamatAutofillBadge" class="autofill-indicator">Otomatis Terisi</span>



                </label>



                <textarea name="pickup_address" 



                          id="inputAlamat" 



                          class="form-control" 



                          rows="2" 



                          maxlength="300" 



                          placeholder="Nama Jalan, Nomor Rumah, RT/RW, Patokan Area..." 



                          required><?= isset($_POST['pickup_address']) ? htmlspecialchars($_POST['pickup_address']) : '' ?></textarea>



            </div>







            <!-- SEKSI OPSIONAL: DAFTAR MEMBER LANGSUNG LEWAT FORM -->



            <div id="membershipSignupSection" style="margin-bottom: 2rem; background: #F8FAFC; border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem;">



                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 0.85rem;">



                    <div>



                        <div style="font-size: 14px; font-weight: 700; color: var(--navy);">Daftar Program Member Footstepszs (Opsional)</div>



                        <div style="font-size: 12px; color: var(--text-secondary);">Pilih paket member untuk mendapatkan diskon langsung pesanan ini dan kuota perawatan bulanan tanpa perlu didaftarkan admin terlebih dahulu.</div>



                    </div>



                    <button type="button" class="btn btn-outline btn-sm" id="btnResetMember" style="font-size: 11px; padding: 4px 10px; display: none;" onclick="clearMemberSelection()">



                        Batal Pilih Member



                    </button>



                </div>







                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;" id="memberTierCards">



                    <!-- Bronze -->



                    <label class="member-opt-card" id="cardMemberBronze" onclick="handleCardClick('Bronze', event)">



                        <input type="radio" name="daftar_member_tier" value="Bronze" class="member-tier-radio" id="radioBronze" onchange="onSelectMemberTier('Bronze')">



                        <div class="member-opt-inner">



                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding-right: 20px;">



                                <span class="badge" style="background: #FEF3C7; color: #92400E; font-weight: 700; font-size: 11px;">Bronze</span>



                                <span style="font-size: 11px; font-weight: 700; color: #059669;">Diskon 5%</span>



                            </div>



                            <div style="font-size: 15px; font-weight: 800; color: var(--navy); margin-bottom: 2px;">Rp 160.000<span style="font-size: 11px; font-weight: 500; color: var(--text-light);">/bln</span></div>



                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Kuota 4 Sepatu/Bulan</div>



                            <button type="button" class="btn-snk-link" onclick="openSnkModal('Bronze', event)">Detail S&K</button>



                        </div>



                    </label>







                    <!-- Silver -->



                    <label class="member-opt-card" id="cardMemberSilver" onclick="handleCardClick('Silver', event)">



                        <input type="radio" name="daftar_member_tier" value="Silver" class="member-tier-radio" id="radioSilver" onchange="onSelectMemberTier('Silver')">



                        <div class="member-opt-inner">



                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding-right: 20px;">



                                <span class="badge" style="background: #E5E7EB; color: #374151; font-weight: 700; font-size: 11px;">Silver</span>



                                <span style="font-size: 11px; font-weight: 700; color: #059669;">Diskon 10%</span>



                            </div>



                            <div style="font-size: 15px; font-weight: 800; color: var(--navy); margin-bottom: 2px;">Rp 300.000<span style="font-size: 11px; font-weight: 500; color: var(--text-light);">/bln</span></div>



                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Kuota 8 Sepatu/Bulan</div>



                            <button type="button" class="btn-snk-link" onclick="openSnkModal('Silver', event)">Detail S&K</button>



                        </div>



                    </label>







                    <!-- Gold -->



                    <label class="member-opt-card" id="cardMemberGold" onclick="handleCardClick('Gold', event)">



                        <input type="radio" name="daftar_member_tier" value="Gold" class="member-tier-radio" id="radioGold" onchange="onSelectMemberTier('Gold')">



                        <div class="member-opt-inner">



                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; padding-right: 20px;">



                                <span class="badge" style="background: #FEF3C7; color: #D97706; font-weight: 700; font-size: 11px;">Gold</span>



                                <span style="font-size: 11px; font-weight: 700; color: #059669;">Diskon 15%</span>



                            </div>



                            <div style="font-size: 15px; font-weight: 800; color: var(--navy); margin-bottom: 2px;">Rp 590.000<span style="font-size: 11px; font-weight: 500; color: var(--text-light);">/bln</span></div>



                            <div style="font-size: 12px; color: var(--text-secondary); margin-bottom: 8px;">Kuota 16 Sepatu/Bulan</div>



                            <button type="button" class="btn-snk-link" onclick="openSnkModal('Gold', event)">Detail S&K</button>



                        </div>



                    </label>



                </div>



            </div>







            <!-- SEKSI 2: FOTO KONDISI AWAL -->



            <div class="section-title-clean">



                <span class="step-number">2</span> Dokumentasi Kondisi Fisik Sepatu



            </div>



            <div style="margin-bottom: 2rem;">



                <p style="font-size: 13px; color: var(--text-secondary); margin-bottom: 0.75rem;">Unggah foto fisik sepatu sebelum diserahkan. Foto ini menjadi bukti transparansi kondisi awal barang Anda.</p>



                <div class="file-upload-box" id="fotoBox">



                    <input type="file" name="foto_awal[]" accept="image/jpeg,image/png,image/webp" class="file-input-hidden" id="fotoInput" multiple required>



                    <div id="fotoContent">



                        <div style="font-size: 1.05rem; font-weight: 700; color: var(--navy); margin-bottom: 4px;">Pilih File Foto atau Jepret Langsung</div>



                        <div style="font-size: 12px; color: var(--text-light);">Format: JPG, PNG, WEBP (Maksimal 5MB per foto)</div>



                    </div>



                    <div id="fotoPreviewContainer" class="preview-container" style="display: none;"></div>



                </div>



            </div>







            <!-- SEKSI 3: ITEM CUCIAN -->



            <div class="section-title-clean">



                <span class="step-number">3</span> Rincian Item Cucian



            </div>



            <div id="itemsContainer" style="margin-bottom: 1rem;">



                <!-- Item Card 1 -->



                <div class="order-item-card" data-idx="0">



                    <div class="order-item-card-header">



                        <span class="order-item-card-title">Item #1</span>



                        <button type="button" class="btn btn-outline btn-sm btn-del-item" style="display:none; color:#DC2626; border-color:#FCA5A5; padding:2px 8px; font-size:11px;">Hapus</button>



                    </div>



                    <div class="order-item-grid">



                        <div>



                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Kategori Barang</label>



                            <select name="items[0][type]" class="form-control item-type" required>



                                <option value="sepatu">Sepatu / Sendal</option>



                                <option value="tas">Tas</option>



                                <option value="topi">Topi</option>



                            </select>



                        </div>



                        <div>



                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Treatment Tambahan</label>



                            <select name="items[0][treatment]" class="form-control item-treatment" required>



                                <option value="Deep Cleaning">Deep Cleaning</option>



                                <option value="Unyellowing">Unyellowing (+Rp 10.000)</option>



                                <option value="Repair">Artisan Repair (+Rp 20.000)</option>



                            </select>



                        </div>



                        <div>



                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Jumlah</label>



                            <input type="number" 



                                   name="items[0][qty]" 



                                   class="form-control item-qty" 



                                   value="1" 



                                   min="1" 



                                   max="50" 



                                   step="1"



                                   inputmode="numeric" 



                                   required



                                   oninput="this.value = this.value.replace(/[^0-9]/g, ''); if(parseInt(this.value) > 50) this.value = 50; if(parseInt(this.value) < 1 && this.value !== '') this.value = 1;">



                        </div>



                        <div style="text-align: right;">



                            <label style="display: block; font-size: 12px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Subtotal</label>



                            <div class="item-subtotal-val item-subtotal">Rp 25.000</div>



                        </div>



                    </div>



                </div>



            </div>



            <button type="button" id="addItemBtn" class="btn btn-outline btn-sm" style="margin-bottom: 2rem;">+ Tambah Item Lain</button>







            <!-- SEKSI 4: KECEPATAN PENGERJAAN -->



            <div class="section-title-clean">



                <span class="step-number">4</span> Kecepatan Layanan



            </div>



            <div class="speed-grid" style="margin-bottom: 2rem;">



                <label style="cursor: pointer;">



                    <input type="radio" name="jenis_waktu" value="Regular" checked style="display: none;" class="waktu-radio">



                    <div class="speed-card active">



                        <div style="font-weight: 700; font-size: 15px; color: var(--navy);">Regular (2 Hari)</div>



                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 2px;">Waktu pengerjaan standar</div>



                    </div>



                </label>



                <label style="cursor: pointer;">



                    <input type="radio" name="jenis_waktu" value="Fast Clean" style="display: none;" class="waktu-radio">



                    <div class="speed-card">



                        <div style="font-weight: 700; font-size: 15px; color: var(--navy);">Fast Clean (1 Hari)</div>



                        <div style="font-size: 12px; color: var(--warning); margin-top: 2px;">+Rp 10.000 per item</div>



                    </div>



                </label>



            </div>







            <!-- SEKSI 5: METODE PEMBAYARAN -->



            <div class="section-title-clean">



                <span class="step-number">5</span> Pilihan Metode Pembayaran



            </div>



            <div class="payment-grid" style="margin-bottom: 2rem;">



                <label style="cursor: pointer;" id="labelPayCOD">



                    <input type="radio" name="metode_pembayaran" value="COD" checked style="display: none;" class="payment-radio" id="radioPayCOD">



                    <div class="payment-card active" id="cardPayCOD">



                        <div class="payment-card-title" id="titlePayCOD">Cash on Delivery (COD)</div>



                        <div class="payment-card-desc" id="descPayCOD">Bayar tunai di tempat saat penjemputan atau pengantaran barang.</div>



                    </div>



                </label>



                <label style="cursor: pointer;" id="labelPayTransfer">



                    <input type="radio" name="metode_pembayaran" value="Transfer Bank" style="display: none;" class="payment-radio" id="radioPayTransfer">



                    <div class="payment-card" id="cardPayTransfer">



                        <div class="payment-card-title">Transfer Bank Manual</div>



                        <div class="payment-card-desc">BCA 1234-5678-90. Batas waktu konfirmasi pembayaran 24 jam.</div>



                    </div>



                </label>



                <label style="cursor: pointer;" id="labelPayQRIS">



                    <input type="radio" name="metode_pembayaran" value="QRIS" style="display: none;" class="payment-radio" id="radioPayQRIS">



                    <div class="payment-card" id="cardPayQRIS">



                        <div class="payment-card-title">QRIS Statis</div>



                        <div class="payment-card-desc">Scan via GoPay, OVO, Dana, ShopeePay, BCA. Batas waktu 24 jam.</div>



                    </div>



                </label>



            </div>







            <!-- SEKSI 6: CATATAN & REKAP HARGA -->



            <div style="margin-bottom: 1.5rem;">



                <label style="display: block; margin-bottom: 0.4rem; font-size: 13px; font-weight: 600;">Catatan Tambahan (Opsional)</label>



                <input type="text" name="catatan" class="form-control" maxlength="200" style="width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border);" placeholder="Contoh: Hati-hati bagian suede sisi kiri...">



            </div>







            <div class="bill-summary">



                <div class="bill-row"><span>Subtotal Item (Harga Normal)</span><span id="txtSubtotal">Rp 25.000</span></div>



                <div class="bill-row" id="rowQuotaCover" style="display: none; color: #059669; font-weight: 700;">



                    <span id="txtQuotaCoverLabel">Gratis Kuota Cuci Member (<span id="txtQuotaItemsUsed">0</span> item)</span>



                    <span id="txtQuotaDeduction">- Rp 0</span>



                </div>



                <div class="bill-row" id="rowNewMemberFee" style="display: none; color: var(--primary); font-weight: 700;">



                    <span>Biaya Paket Member (<span id="txtNewMemberTier">-</span>)</span>



                    <span id="txtNewMemberFee">+ Rp 0</span>



                </div>



                <div class="bill-row" id="rowDiskon" style="display: none; color: #059669; font-weight: 600;">



                    <span>Diskon Treatment / Item (<span id="txtMemberTier">-</span>)</span>



                    <span id="txtDiskon">- Rp 0</span>



                </div>



                <div class="bill-row"><span>Ongkos Kirim (Malang Kota)</span><span style="color: #059669; font-weight: 600;">Gratis</span></div>



                <div class="bill-row"><span>Estimasi Selesai</span><strong id="txtEstimasi">-</strong></div>



                <div class="bill-row total-row"><span>Total Akhir</span><span id="txtTotalAkhir">Rp 25.000</span></div>



            </div>







            <!-- Modal Detail S&K Member -->



            <div id="snkModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">



                <div style="background: white; border-radius: 16px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 20px 40px rgba(0,0,0,0.25);">



                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">



                        <h3 id="snkModalTitle" style="font-size: 1.15rem; font-weight: 800; color: var(--navy); margin: 0;">Detail & Ketentuan Member</h3>



                        <button type="button" onclick="closeSnkModal()" style="background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text-light); line-height: 1;">&times;</button>



                    </div>



                    <div id="snkModalContent" style="font-size: 13.5px; line-height: 1.7; color: var(--text-secondary);">



                        <!-- Konten dimuat dinamis via JavaScript -->



                    </div>



                    <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 1rem;">



                        <button type="button" class="btn btn-outline btn-sm" onclick="closeSnkModal()">Tutup</button>



                        <button type="button" class="btn btn-primary btn-sm" id="btnPilihPaketModal" onclick="confirmSelectTierFromModal()">Pilih Paket Ini</button>



                    </div>



                </div>



            </div>







            <div class="submit-dock">



                <div>



                    <div style="font-size: 11px; text-transform: uppercase; color: var(--text-light); letter-spacing: 0.5px;">Total Pembayaran</div>



                    <div id="dockGrandTotal" style="font-size: 22px; font-weight: 800; color: var(--navy);">Rp 25.000</div>



                </div>



                <button type="submit" id="btnSubmitOrder" class="btn btn-primary" style="padding: 0.85rem 2rem; font-size: 15px; font-weight: 700;">



                    Kirim Pesanan



                </button>



            </div>



        </form>







    <?php endif; ?>







</div>







<script>



const BASE_PRICE = { 'sepatu': 25000, 'tas': 25000, 'topi': 15000, 'sendal': 25000 };



const TREAT_PRICE = { 'Deep Cleaning': 0, 'Unyellowing': 10000, 'Repair': 20000 };



let currentDiscountPercent = 0;



let currentMemberTier = '';



let itemCardIndex = 0;







let currentRemainingQuota = 0;



let currentTotalQuota = 0;



let currentUsedQuota = 0;



let selectedNewMemberTier = null;



let selectedNewMemberPrice = 0;







function recalculateBill() {



    const isFast = document.querySelector('input[name="jenis_waktu"]:checked')?.value === 'Fast Clean';



    const fastCost = isFast ? 10000 : 0;







    let quotaPool = currentRemainingQuota;



    let subtotalGross = 0;



    let quotaDeduction = 0;



    let extraDiscount = 0;



    let totalItemsCovered = 0;



    let washItemPayTotal = 0;







    document.querySelectorAll('.order-item-card').forEach(card => {



        const type = card.querySelector('.item-type').value;



        const treat = card.querySelector('.item-treatment').value;



        const qty = parseInt(card.querySelector('.item-qty').value) || 1;







        const base = BASE_PRICE[type] || 0;



        const treatCost = TREAT_PRICE[treat] || 0;



        const fullUnitPrice = base + treatCost + fastCost;







        let itemFinalPay = 0;



        let coveredQty = 0;







        for (let q = 0; q < qty; q++) {



            if (quotaPool > 0) {



                quotaPool--;



                totalItemsCovered++;



                coveredQty++;



                // Cuci dasar 100% GRATIS dicover kuota



                quotaDeduction += base;







                // Biaya treatment tambahan atau fast clean dapat diskon member



                let extra = treatCost + fastCost;



                if (currentDiscountPercent > 0 && extra > 0) {



                    let disc = Math.round((extra * currentDiscountPercent) / 100);



                    extraDiscount += disc;



                    itemFinalPay += (extra - disc);



                } else {



                    itemFinalPay += extra;



                }



            } else {



                // Di luar kuota: bayar normal minus diskon member



                let unitCost = fullUnitPrice;



                if (currentDiscountPercent > 0) {



                    let disc = Math.round((unitCost * currentDiscountPercent) / 100);



                    extraDiscount += disc;



                    itemFinalPay += (unitCost - disc);



                } else {



                    itemFinalPay += unitCost;



                }



            }



        }







        subtotalGross += (fullUnitPrice * qty);



        washItemPayTotal += itemFinalPay;







        const subEl = card.querySelector('.item-subtotal');



        if (coveredQty === qty && treatCost === 0 && !isFast) {



            const labelCover = selectedNewMemberTier ? 'Gratis (Dicover Kuota Member Baru)' : 'Rp 0 (Dicover Kuota Member)';



            subEl.innerHTML = `<span style="text-decoration:line-through;color:#9CA3AF;font-size:12px;margin-right:4px;">Rp ${(fullUnitPrice * qty).toLocaleString('id-ID')}</span><strong style="color:#059669;">${labelCover}</strong>`;



        } else if (coveredQty > 0) {



            subEl.innerHTML = `<span style="font-size:11.5px;color:#059669;margin-right:6px;">${coveredQty} kuota gratis</span><strong>Rp ${itemFinalPay.toLocaleString('id-ID')}</strong>`;



        } else {



            subEl.innerText = 'Rp ' + itemFinalPay.toLocaleString('id-ID');



        }



    });







    // Tampilkan / Sembunyikan baris Kuota Member



    const rowQuota = document.getElementById('rowQuotaCover');



    if (quotaDeduction > 0) {



        rowQuota.style.display = 'flex';



        const labelText = selectedNewMemberTier ? `Gratis Kuota Cuci Member Baru (${totalItemsCovered} item)` : `Gratis Kuota Cuci Member (${totalItemsCovered} item)`;



        const labelEl = document.getElementById('txtQuotaCoverLabel');



        if (labelEl) labelEl.innerText = labelText;



        document.getElementById('txtQuotaDeduction').innerText = '- Rp ' + quotaDeduction.toLocaleString('id-ID');



    } else {



        rowQuota.style.display = 'none';



    }







    // Tampilkan / Sembunyikan baris Biaya Paket Member Baru



    const rowNewMember = document.getElementById('rowNewMemberFee');



    if (rowNewMember) {



        if (selectedNewMemberPrice > 0 && selectedNewMemberTier) {



            rowNewMember.style.display = 'flex';



            document.getElementById('txtNewMemberTier').innerText = selectedNewMemberTier;



            document.getElementById('txtNewMemberFee').innerText = '+ Rp ' + selectedNewMemberPrice.toLocaleString('id-ID');



        } else {



            rowNewMember.style.display = 'none';



        }



    }







    // Tampilkan / Sembunyikan baris Diskon Tambahan



    const rowDiskon = document.getElementById('rowDiskon');



    if (extraDiscount > 0) {



        rowDiskon.style.display = 'flex';



        document.getElementById('txtMemberTier').innerText = currentMemberTier;



        document.getElementById('txtDiskon').innerText = '- Rp ' + extraDiscount.toLocaleString('id-ID');



    } else {



        rowDiskon.style.display = 'none';



    }







    // Total Akhir: Jika daftar member baru -> biaya paket member + item pay total



    const totalAkhir = Math.max(0, selectedNewMemberPrice + washItemPayTotal);







    document.getElementById('txtSubtotal').innerText = 'Rp ' + subtotalGross.toLocaleString('id-ID');



    document.getElementById('txtTotalAkhir').innerText = 'Rp ' + totalAkhir.toLocaleString('id-ID');



    



    const dockEl = document.getElementById('dockGrandTotal');



    const submitBtn = document.getElementById('btnSubmitOrder') || document.querySelector('.submit-dock button[type="submit"]');







    if (selectedNewMemberPrice > 0 && selectedNewMemberTier) {



        dockEl.innerHTML = `<span style="font-size:22px;font-weight:800;color:var(--navy);">Rp ${totalAkhir.toLocaleString('id-ID')}</span> <div style="font-size:11px;font-weight:700;color:var(--primary);margin-top:2px;">Biaya Paket Member ${selectedNewMemberTier} (Cuci Gratis)</div>`;



        if (submitBtn) submitBtn.innerText = `Daftar Member & Bayar (Rp ${totalAkhir.toLocaleString('id-ID')})`;



    } else if (totalAkhir === 0 && totalItemsCovered > 0) {



        dockEl.innerHTML = `<span style="color:#059669;">Rp 0</span> <span style="font-size:13px;font-weight:600;color:#059669;">(LUNAS KUOTA)</span>`;



        if (submitBtn) submitBtn.innerText = 'Kirim Pesanan (Gratis dengan Kuota)';



    } else {



        dockEl.innerText = 'Rp ' + totalAkhir.toLocaleString('id-ID');



        if (submitBtn) submitBtn.innerText = 'Kirim Pesanan';



    }







    // Estimasi tanggal



    const days = isFast ? 1 : 2;



    const estDate = new Date();



    estDate.setDate(estDate.getDate() + days);



    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };



    document.getElementById('txtEstimasi').innerText = estDate.toLocaleDateString('id-ID', options);



}







// Data Detail & S&K Member



const MEMBER_TIER_DETAILS = {



    'Bronze': {



        'nama': 'Bronze Member',



        'harga': 'Rp 160.000 / bulan',



        'diskon': '5%',



        'kuota': '4 Pasang Sepatu per Bulan',



        'hemat': 'Hemat Rp 40.000 per bulan',



        'manfaat': [



            'Diskon 5% otomatis untuk order ini dan seluruh order perawatan selama 30 hari',



            'Kuota gratis cuci 4 pasang sepatu setiap bulan',



            'Gratis ongkir penjemputan & pengantaran (area Malang Kota)',



            'Update transparansi kondisi dan progres cuci otomatis via WhatsApp'



        ],



        'snk': [



            'Masa aktif berlaku selama 30 hari kalender sejak tanggal pendaftaran.',



            'Kuota cuci bulanan tidak dapat diakumulasikan ke periode bulan berikutnya.',



            'Diskon 5% otomatis berlaku untuk seluruh jenis treatment tambahan (Deep Cleaning, Unyellowing, Repair).',



            'Biaya membership dapat dibayarkan langsung bersamaan dengan pembayaran pesanan ini.'



        ]



    },



    'Silver': {



        'nama': 'Silver Member',



        'harga': 'Rp 300.000 / bulan',



        'diskon': '10%',



        'kuota': '8 Pasang Sepatu / Tas per Bulan',



        'hemat': 'Hemat Rp 100.000 per bulan',



        'manfaat': [



            'Diskon 10% otomatis untuk order ini dan seluruh order perawatan selama 30 hari',



            'Kuota gratis cuci 8 pasang sepatu atau tas per bulan',



            'Prioritas pengerjaan reguler dibanding non-member',



            'Gratis ongkir penjemputan & pengantaran (area Malang Kota)',



            'Garansi cuci ulang jika hasil kurang bersih dalam 24 jam'



        ],



        'snk': [



            'Masa aktif berlaku selama 30 hari kalender sejak tanggal pendaftaran.',



            'Kuota cuci bulanan berlaku fleksibel untuk sepatu maupun tas.',



            'Diskon 10% berlaku tanpa minimum transaksi.',



            'Biaya membership dapat dibayarkan langsung bersamaan dengan pembayaran pesanan ini.'



        ]



    },



    'Gold': {



        'nama': 'Gold Member',



        'harga': 'Rp 590.000 / bulan',



        'diskon': '15%',



        'kuota': '16 Pasang Sepatu / Tas / Topi per Bulan',



        'hemat': 'Hemat Rp 210.000 per bulan',



        'manfaat': [



            'Diskon 15% otomatis untuk order ini dan seluruh order perawatan selama 30 hari',



            'Kuota gratis cuci 16 pasang item (sepatu, tas, atau topi) per bulan',



            'Prioritas antrean pengerjaan Fast Clean ekspres tanpa biaya tambahan',



            'Gratis ongkir penjemputan & pengantaran VIP prioritas area Malang Raya',



            'Konsultasi gratis perawatan bahan khusus (kulit asli, nubuck, suede)',



            'Garansi cuci ulang premium tanpa syarat'



        ],



        'snk': [



            'Masa aktif berlaku selama 30 hari kalender sejak tanggal pendaftaran.',



            'Kuota item dapat digunakan secara fleksibel untuk sepatu, sendal, tas, atau topi.',



            'Prioritas Fast Clean disesuaikan dengan kapasitas pengerjaan harian.',



            'Biaya membership dapat dibayarkan langsung bersamaan dengan pembayaran pesanan ini.'



        ]



    }



};







let currentModalTier = null;







function handleCardClick(tier, event) {



    // If the click came from the detail S&K button, ignore card toggle



    if (event.target.classList.contains('btn-snk-link')) {



        return;



    }



    const radio = document.getElementById('radio' + tier);



    if (radio) {



        radio.checked = true;



        onSelectMemberTier(tier);



    }



}







function updatePaymentMethodForMember(isMemberSelected) {



    const radioCod = document.getElementById('radioPayCOD');



    const cardCod = document.getElementById('cardPayCOD');



    const descCod = document.getElementById('descPayCOD');



    const radioTransfer = document.getElementById('radioPayTransfer');



    const cardTransfer = document.getElementById('cardPayTransfer');







    if (isMemberSelected) {



        if (radioCod && cardCod) {



            radioCod.disabled = true;



            cardCod.style.opacity = '0.45';



            cardCod.style.filter = 'grayscale(0.8)';



            cardCod.style.cursor = 'not-allowed';



            cardCod.classList.remove('active');



            if (descCod) {



                descCod.innerHTML = '<span style="color:#DC2626;font-weight:700;">Tidak tersedia untuk pembelian paket member</span>';



            }



        }



        // Jika COD sedang dipilih, otomatis pindahkan ke Transfer Bank atau QRIS



        if (radioCod && radioCod.checked) {



            radioCod.checked = false;



            if (radioTransfer && cardTransfer) {



                radioTransfer.checked = true;



                document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('active'));



                cardTransfer.classList.add('active');



            }



        }



    } else {



        if (radioCod && cardCod) {



            radioCod.disabled = false;



            cardCod.style.opacity = '1';



            cardCod.style.filter = 'none';



            cardCod.style.cursor = 'pointer';



            if (descCod) {



                descCod.innerText = 'Bayar tunai di tempat saat penjemputan atau pengantaran barang.';



            }



        }



    }



}







function onSelectMemberTier(tier) {



    document.querySelectorAll('.member-opt-card').forEach(c => c.classList.remove('active'));



    const activeCard = document.getElementById('cardMember' + tier);



    if (activeCard) activeCard.classList.add('active');







    const discounts = { 'Bronze': 5, 'Silver': 10, 'Gold': 15 };



    const quotas = { 'Bronze': 4, 'Silver': 8, 'Gold': 16 };



    const prices = { 'Bronze': 160000, 'Silver': 300000, 'Gold': 590000 };







    selectedNewMemberTier = tier;



    selectedNewMemberPrice = prices[tier] || 0;



    currentDiscountPercent = discounts[tier] || 0;



    currentMemberTier = tier + ' (Daftar Baru)';



    currentRemainingQuota = quotas[tier] || 4;



    currentTotalQuota = quotas[tier] || 4;







    const btnReset = document.getElementById('btnResetMember');



    if (btnReset) btnReset.style.display = 'inline-block';







    updatePaymentMethodForMember(true);



    recalculateBill();



}







function clearMemberSelection(triggerRecalc = true) {



    document.querySelectorAll('.member-tier-radio').forEach(r => r.checked = false);



    document.querySelectorAll('.member-opt-card').forEach(c => c.classList.remove('active'));



    const btnReset = document.getElementById('btnResetMember');



    if (btnReset) btnReset.style.display = 'none';







    selectedNewMemberTier = null;



    selectedNewMemberPrice = 0;







    // If verified member alert is active, restore verified member discount



    const alertBox = document.getElementById('memberBadgeAlert');



    if (alertBox && alertBox.style.display !== 'none') {



        const tier = document.getElementById('memberTierText').innerText;



        const discounts = { 'Bronze': 5, 'Silver': 10, 'Gold': 15 };



        currentDiscountPercent = discounts[tier] || 0;



        currentMemberTier = tier;



    } else {



        currentDiscountPercent = 0;



        currentMemberTier = '';



        currentRemainingQuota = 0;



    }







    updatePaymentMethodForMember(false);



    if (triggerRecalc) recalculateBill();



}







function openSnkModal(tier, event) {



    if (event) {



        event.stopPropagation();



        event.preventDefault();



    }



    currentModalTier = tier;



    const data = MEMBER_TIER_DETAILS[tier];



    if (!data) return;







    document.getElementById('snkModalTitle').innerText = 'Syarat & Ketentuan — ' + data.nama;



    



    let html = `



        <div style="background: #F8FAFC; border: 1px solid var(--border); border-radius: 10px; padding: 1rem; margin-bottom: 1.25rem;">



            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">



                <span style="font-size: 13px; font-weight: 700; color: var(--navy);">${data.nama}</span>



                <span style="font-size: 13px; font-weight: 800; color: #059669;">Diskon Langsung ${data.diskon}</span>



            </div>



            <div style="font-size: 16px; font-weight: 800; color: var(--primary);">${data.harga}</div>



            <div style="font-size: 12px; color: var(--text-light); margin-top: 2px;">${data.kuota} (${data.hemat})</div>



        </div>







        <div style="margin-bottom: 1rem;">



            <div style="font-size: 13px; font-weight: 700; color: var(--navy); margin-bottom: 6px;">Keuntungan Paket:</div>



            <ul style="padding-left: 1.2rem; margin: 0; font-size: 13px;">



                ${data.manfaat.map(m => `<li style="margin-bottom: 4px;">${m}</li>`).join('')}



            </ul>



        </div>







        <div>



            <div style="font-size: 13px; font-weight: 700; color: var(--navy); margin-bottom: 6px;">Syarat & Ketentuan (S&K):</div>



            <ol style="padding-left: 1.2rem; margin: 0; font-size: 12.5px; color: var(--text-secondary);">



                ${data.snk.map(s => `<li style="margin-bottom: 4px;">${s}</li>`).join('')}



            </ol>



        </div>



    `;







    document.getElementById('snkModalContent').innerHTML = html;



    const modal = document.getElementById('snkModal');



    modal.style.display = 'flex';



}







function closeSnkModal() {



    const modal = document.getElementById('snkModal');



    if (modal) modal.style.display = 'none';



}







function confirmSelectTierFromModal() {



    if (currentModalTier) {



        const radio = document.getElementById('radio' + currentModalTier);



        if (radio) {



            radio.checked = true;



            onSelectMemberTier(currentModalTier);



        }



    }



    closeSnkModal();



}







// WhatsApp Lookup, Auto-fill & Numeric Sanitization



let phoneTimeout = null;



const phoneInput = document.getElementById('inputNoHp');



if (phoneInput) {



    phoneInput.addEventListener('input', function() {



        // Enforce numeric only



        this.value = this.value.replace(/[^0-9]/g, '');







        clearTimeout(phoneTimeout);



        const val = this.value.trim();



        if (val.length >= 10) {



            phoneTimeout = setTimeout(() => {



                fetch('/api/check_member.php?no_hp=' + encodeURIComponent(val))



                    .then(r => r.json())



                    .then(data => {



                        const alertBox = document.getElementById('memberBadgeAlert');



                        const signupBox = document.getElementById('membershipSignupSection');



                        const namaInput = document.getElementById('inputNama');



                        const alamatInput = document.getElementById('inputAlamat');



                        const namaBadge = document.getElementById('namaAutofillBadge');



                        const alamatBadge = document.getElementById('alamatAutofillBadge');







                        const expiredBox = document.getElementById('memberExpiredAlert');



                        if (data.success && data.is_member) {

                            if (expiredBox) expiredBox.style.display = 'none';

                            currentDiscountPercent = data.discount_percent;

                            currentMemberTier = data.tier;

                            currentRemainingQuota = parseInt(data.remaining_quota) || 0;

                            currentTotalQuota = parseInt(data.total_quota) || 0;

                            currentUsedQuota = parseInt(data.used_quota) || 0;



                            document.getElementById('memberTierText').innerText = data.tier;

                            document.getElementById('memberDiscountText').innerText = data.discount_percent + '%';

                            const quotaCountEl = document.getElementById('memberQuotaCount');

                            if (quotaCountEl) {

                                quotaCountEl.innerText = `${currentRemainingQuota} dari ${currentTotalQuota}`;

                            }



                            // Tracker Masa Aktif & Sisa Hari

                            const trackerDays = document.getElementById('memberDaysLeft');

                            const trackerDate = document.getElementById('memberExpiryDate');

                            if (trackerDays && trackerDate) {

                                trackerDays.innerText = (data.sisa_hari !== undefined ? data.sisa_hari : 0) + ' hari lagi';

                                trackerDate.innerText = data.tanggal_selesai_formatted || '-';

                            }



                            // Peringatan H-3

                            const h3Box = document.getElementById('memberH3Warning');

                            if (h3Box) {

                                if (data.is_h3) {

                                    h3Box.style.display = 'block';

                                    const h3Days = document.getElementById('memberH3Days');

                                    const h3DaysText = document.getElementById('memberH3DaysText');

                                    if (h3Days) h3Days.innerText = data.sisa_hari;

                                    if (h3DaysText) h3DaysText.innerText = data.sisa_hari;

                                } else {

                                    h3Box.style.display = 'none';

                                }

                            }



                            alertBox.style.display = 'block';

                            alertBox.classList.add('show');



                            // Sembunyikan opsi pendaftaran karena sudah menjadi member aktif

                            if (signupBox) {

                                signupBox.style.display = 'none';

                                clearMemberSelection(false);

                            }

                        } else {

                            alertBox.style.display = 'none';

                            alertBox.classList.remove('show');



                            // Tampilkan peringatan jika nomor sebelumnya pernah menjadi member (Nonaktif)

                            if (data.was_member && expiredBox) {

                                expiredBox.style.display = 'block';

                                const expTier = document.getElementById('expiredTierText');

                                const expDate = document.getElementById('expiredDateText');

                                if (expTier) expTier.innerText = data.expired_tier || '-';

                                if (expDate) expDate.innerText = data.expired_date_formatted || '-';

                            } else if (expiredBox) {

                                expiredBox.style.display = 'none';

                            }



                            if (signupBox) signupBox.style.display = 'block';







                            // Cek apakah ada opsi member baru yang sedang dipilih



                            const selectedTierRadio = document.querySelector('input[name="daftar_member_tier"]:checked');



                            if (selectedTierRadio) {



                                const discounts = { 'Bronze': 5, 'Silver': 10, 'Gold': 15 };



                                currentDiscountPercent = discounts[selectedTierRadio.value] || 0;



                                currentMemberTier = selectedTierRadio.value + ' (Daftar Baru)';



                            } else {



                                currentDiscountPercent = 0;



                                currentMemberTier = '';



                            }



                        }







                        // AUTOFILL NAMA LENGKAP



                        if (data.nama && namaInput) {



                            if (!namaInput.value.trim() || namaInput.dataset.autofilled === "true") {



                                namaInput.value = data.nama;



                                namaInput.dataset.autofilled = "true";



                                if (namaBadge) namaBadge.style.display = 'inline-block';



                            }



                        }







                        // AUTOFILL ALAMAT



                        if (data.alamat && alamatInput) {



                            if (!alamatInput.value.trim() || alamatInput.dataset.autofilled === "true") {



                                alamatInput.value = data.alamat;



                                alamatInput.dataset.autofilled = "true";



                                if (alamatBadge) alamatBadge.style.display = 'inline-block';



                            }



                        }







                        recalculateBill();



                    })



                    .catch(() => {});



            }, 300);



        } else {



            const signupBox = document.getElementById('membershipSignupSection');



            if (signupBox) signupBox.style.display = 'block';







            const selectedTierRadio = document.querySelector('input[name="daftar_member_tier"]:checked');



            if (selectedTierRadio) {



                const discounts = { 'Bronze': 5, 'Silver': 10, 'Gold': 15 };



                currentDiscountPercent = discounts[selectedTierRadio.value] || 0;



                currentMemberTier = selectedTierRadio.value + ' (Daftar Baru)';



            } else {



                currentDiscountPercent = 0;



                currentMemberTier = '';



            }



            const mba = document.getElementById('memberBadgeAlert');



            if (mba) {



                mba.style.display = 'none';



                mba.classList.remove('show');



            }



            recalculateBill();



        }



    });



}







// Reset flag autofill saat user mengedit nama/alamat secara manual



const inputNamaEl = document.getElementById('inputNama');



if (inputNamaEl) {



    inputNamaEl.addEventListener('input', function() {



        delete this.dataset.autofilled;



        const b = document.getElementById('namaAutofillBadge');



        if (b) b.style.display = 'none';



    });



}



const inputAlamatEl = document.getElementById('inputAlamat');



if (inputAlamatEl) {



    inputAlamatEl.addEventListener('input', function() {



        delete this.dataset.autofilled;



        const b = document.getElementById('alamatAutofillBadge');



        if (b) b.style.display = 'none';



    });



}







// Payment Selection



document.querySelectorAll('.payment-radio').forEach(radio => {



    radio.addEventListener('change', function() {



        if (this.disabled) return;



        document.querySelectorAll('.payment-card').forEach(c => c.classList.remove('active'));



        this.closest('label').querySelector('.payment-card').classList.add('active');



    });



});







const labelCodEl = document.getElementById('labelPayCOD');



if (labelCodEl) {



    labelCodEl.addEventListener('click', function(e) {



        const radioCod = document.getElementById('radioPayCOD');



        if (radioCod && radioCod.disabled) {



            e.preventDefault();



            alert('Pembelian paket membership tidak dapat menggunakan metode COD. Silakan pilih Transfer Bank atau QRIS.');



        }



    });



}







// Speed Selection



document.querySelectorAll('.waktu-radio').forEach(radio => {



    radio.addEventListener('change', function() {



        document.querySelectorAll('.speed-card').forEach(c => c.classList.remove('active'));



        this.closest('label').querySelector('.speed-card').classList.add('active');



        recalculateBill();



    });



});







// Dynamic Items



const itemsCont = document.getElementById('itemsContainer');



if (itemsCont) {



    itemsCont.addEventListener('change', recalculateBill);



    itemsCont.addEventListener('input', recalculateBill);







    document.getElementById('addItemBtn').addEventListener('click', function() {



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



            recalculateBill();



        };







        itemsCont.appendChild(firstCard);



        recalculateBill();



    });



}







// Photo Preview



const fotoInp = document.getElementById('fotoInput');



if (fotoInp) {



    fotoInp.addEventListener('change', function() {



        const preview = document.getElementById('fotoPreviewContainer');



        preview.innerHTML = '';



        if (this.files && this.files.length > 0) {



            document.getElementById('fotoContent').style.display = 'none';



            preview.style.display = 'flex';



            for (let i = 0; i < this.files.length; i++) {



                const img = document.createElement('img');



                const reader = new FileReader();



                reader.onload = e => img.src = e.target.result;



                reader.readAsDataURL(this.files[i]);



                preview.appendChild(img);



            }



        } else {



            document.getElementById('fotoContent').style.display = 'block';



            preview.style.display = 'none';



        }



    });



}







// Initial calculation



if (document.getElementById('itemsContainer')) {



    recalculateBill();



}



</script>







<?php include __DIR__ . '/includes/footer.php'; ?>



