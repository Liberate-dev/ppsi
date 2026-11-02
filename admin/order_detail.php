<?php

/**

 * admin/order_detail.php - versi lengkap dengan Tanda Terima 3 tahap,

 * preview foto, notifikasi WA otomatis, dan identitas perusahaan

 */

session_start();

require_once __DIR__ . '/../includes/koneksi.php';



if (!isAdmin()) {

 header('Location: /login.php');

 exit;

}



$id = (int)($_GET['id'] ?? 0);

if (!$id) { header('Location: orders.php'); exit; }



// Ambil data order

$stmt = $conn->prepare("SELECT o.*, u.nama_lengkap, u.email, u.no_hp, u.alamat, u.username FROM orders o JOIN users u ON o.user_id=u.id WHERE o.id=?");

$stmt->bind_param("i", $id);

$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) { header('Location: orders.php'); exit; }



$success = $_GET['success'] ?? '';

$error = $_GET['error'] ?? '';

$wa_notif = ''; // Tombol WA notif (muncul setelah upload TT-2)



// =========================================================

// UPLOAD TANDA TERIMA

// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jenis_tt'])) {

 $jenis_tt = (int)$_POST['jenis_tt'];

 $nama_map = [

 1 => 'Tanda Terima 1 - Barang Masuk',

 2 => 'Tanda Terima 2 - Barang Selesai',

 3 => 'Tanda Terima 3 - Barang Diambil',

 ];

 $nama_tt = $nama_map[$jenis_tt] ?? "Tanda Terima $jenis_tt";



 $items = json_decode($order['items'], true) ?? [];

 $total_qty = 0;

 foreach ($items as $item) {

 $total_qty += (int)($item['qty'] ?? 1);

 }



 if ($jenis_tt === 2) {

 if (!in_array($order['status'], ['Siap Kirim', 'Selesai', 'Diambil'])) {

 $upload_errors = ["Foto akhir pengerjaan hanya dapat diunggah saat status pesanan sudah 'Siap Kirim'. (Status saat ini: " . htmlspecialchars($order['status']) . ")"];

 } else {

 $uploaded_files = [];

 $upload_errors = [];

 for ($i = 0; $i < $total_qty; $i++) {

 $input_name = "tanda_terima_file_$i";

 if (empty($_FILES[$input_name]['name'])) {

 $upload_errors[] = "Foto Item #" . ($i + 1) . " wajib diupload!";

 } else {

 $file = $_FILES[$input_name];

 $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

 $finfo = finfo_open(FILEINFO_MIME_TYPE);

 $mime = finfo_file($finfo, $file['tmp_name']);

 finfo_close($finfo);



 if ($file['error'] !== 0) {

 $upload_errors[] = "Gagal upload Foto Item #" . ($i + 1) . ". Code: " . $file['error'];

 } elseif (!in_array($mime, $allowed_mime)) {

 $upload_errors[] = "Foto Item #" . ($i + 1) . " harus berupa gambar (JPG, PNG, WEBP).";

 } else {

 $uploaded_files[$i] = $file;

 }

 }

 }

 }



 if (!empty($upload_errors)) {

 $error = implode("<br>", $upload_errors);

 } else {

 // Hapus TT lama (replace)

 $del_old = $conn->prepare("DELETE FROM documents WHERE ref_type='order' AND ref_id=? AND nama_dokumen LIKE 'Tanda Terima 2%' AND doc_subtype='tanda_terima'");

 $del_old->bind_param("i", $id);

 $del_old->execute();



 $target_dir = __DIR__ . '/../uploads/documents/';

 if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);



 $foto_akhir_urls = [];

 $uid = (int)$_SESSION['user_id'];

 $success_save = true;



 for ($i = 0; $i < $total_qty; $i++) {

 $file = $uploaded_files[$i];

 $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

 $fn = time() . '_tt2_ord' . $id . '_' . $i . '.' . strtolower($ext);

 $target = $target_dir . $fn;



 if (move_uploaded_file($file['tmp_name'], $target)) {

 $fp = 'uploads/documents/' . $fn;

 $foto_akhir_urls[] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $fp;



 $doc_name = "Tanda Terima 2 - Barang Selesai (#" . ($i + 1) . ")";

 if (!insertTandaTerimaDoc($conn, $id, $doc_name, $fp, 'Bukti Tanda Terima', $uid)) {

 $success_save = false;

 $error = "Gagal menyimpan ke database: " . $conn->error;

 break;

 }

 } else {

 $success_save = false;

 $error = "Gagal memindahkan file ke folder uploads.";

 break;

 }

 }



 if ($success_save) {

 // Siapkan WA & Laporan

 addNotification($conn, $order['user_id'],

 "Sepatu Anda ({$order['kode_order']}) sudah selesai dikerjakan dan siap diambil! Silakan hubungi kami untuk pengiriman atau pengambilan.",

 '/customer/my_orders.php'

 );



 // Ambil semua TT1 untuk Nota OUT

 $tt1_res = $conn->query("SELECT file_path FROM documents WHERE ref_type='order' AND ref_id={$id} AND nama_dokumen LIKE 'Tanda Terima 1%' ORDER BY id ASC");

 $foto_awal_urls = [];

 while ($row = $tt1_res->fetch_assoc()) {

 $foto_awal_urls[] = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $row['file_path'];

 }

 $foto_awal_url = !empty($foto_awal_urls) ? $foto_awal_urls[0] : '';

 $foto_akhir_url = !empty($foto_akhir_urls) ? $foto_akhir_urls[0] : '';



 // Generate Nota OUT

 ob_start();

 $adminName = $_SESSION['nama'] ?? 'Administrator';

 include __DIR__ . '/../templates/invoice_out.php';

 $html_content = ob_get_clean();



 $gen_dir = __DIR__ . '/../uploads/documents/generated/';

 if (!is_dir($gen_dir)) mkdir($gen_dir, 0777, true);

 $nota_filename = 'NotaOUT_' . $order['kode_order'] . '.html';

 file_put_contents($gen_dir . $nota_filename, $html_content);

 $nota_db_path = 'uploads/documents/generated/' . $nota_filename;



 // Save Nota OUT to documents

 $insNota = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES ('order', ?, 'Nota Pesanan OUT', 'Generated', ?, 'Nota Bukti Pengambilan / Selesai', CURDATE(), ?)");

 $insNota->bind_param("isi", $id, $nota_db_path, $uid);

 $insNota->execute();



 $notaOutUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . $nota_db_path;



 // AUTO UPDATE STATUS TO 'Selesai' AND RECORD FINANCES

 $conn->query("UPDATE orders SET status='Selesai', tanggal_selesai=NOW() WHERE id=$id");

 $order['status'] = 'Selesai';

 $order_ref = "Order Lunas #" . $order['kode_order'];

 $chkFin = $conn->prepare("SELECT id FROM keuangan WHERE keterangan = ? AND jenis = 'Pemasukan'");

 $chkFin->bind_param("s", $order_ref); $chkFin->execute();

 if ($chkFin->get_result()->num_rows == 0) {

 $insFin = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES ('Pemasukan', 'Order Laundry', ?, ?, CURDATE(), ?)");

 $total = (float)$order['total_harga'];

 $insFin->bind_param("dsi", $total, $order_ref, $uid);

 $insFin->execute();

 updateMemberPoints($conn, $order['user_id'], $order['total_harga']);

 }



 // Send automatic Fonnte WA notification to Customer

 $cust_pesan_wa = "Halo {$order['nama_lengkap']},\n\nKabar baik! Sepatu Anda dengan nomor pesanan *{$order['kode_order']}* telah *selesai dikerjakan* dan siap diambil atau dikirim!\n\nBerikut link detail Nota Selesai (Nota OUT) beserta perbandingan kondisinya:\n{$notaOutUrl}\n\nSilakan konfirmasi apakah ingin diambil langsung ke store atau dikirimkan (delivery).\n\nTerima kasih telah mencuci di Footstepszs! ";

 sendWhatsAppNotification($order['no_hp'], $cust_pesan_wa);



 $wa_hp = preg_replace('/[^0-9]/', '', $order['no_hp'] ?? '');

 if ($wa_hp && substr($wa_hp, 0, 1) === '0') $wa_hp = '62' . substr($wa_hp, 1);

 $wa_link = '';

 

 // Fallback manual WA Web jika token Fonnte belum diset

 $token_check = defined('FONNTE_TOKEN') ? FONNTE_TOKEN : '';

 if ($wa_hp && (empty($token_check) || $token_check === 'YOUR_FONNTE_TOKEN_HERE')) {

 $pesan_wa = "Halo {$order['nama_lengkap']},\n\nSepatu Anda dengan nomor pesanan *{$order['kode_order']}* telah *selesai dikerjakan* dan siap diambil atau dikirim!\n\nBerikut adalah BUKTI SELESAI (Nota OUT) beserta perbandingan kondisinya:\n{$notaOutUrl}\n\nLokasi: Footstepszs Boutique Gallery\nJl. Cluster Tigris Raya No. 29, Malang\nWA/Telp: 089681276080\n\nSilakan konfirmasi apakah ingin diambil langsung atau dikirimkan (delivery).\n\nTerima kasih!";

 $wa_link = "https://wa.me/$wa_hp?text=" . rawurlencode($pesan_wa);

 }



 $uId = (int)$order['user_id'];
 if ($uId > 0 && !empty($order['tier_member'])) {
     $tgl_m = date('Y-m-d');
     $tgl_s = date('Y-m-d', strtotime('+1 month'));
     $tier = $order['tier_member'];
     $chkM = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
     $chkM->bind_param("i", $uId);
     $chkM->execute();
     $mExists = $chkM->get_result()->fetch_assoc();
     if ($mExists) {
         $updM = $conn->prepare("UPDATE members SET level = ?, status = 'Aktif', tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
         $updM->bind_param("sssi", $tier, $tgl_m, $tgl_s, $mExists['id']);
         $updM->execute();
     } else {
         $insM = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Aktif')");
         $insM->bind_param("isss", $uId, $tier, $tgl_m, $tgl_s);
         $insM->execute();
     }
 }
 header("Location: order_detail.php?id=$id&success=" . urlencode("$nama_tt berhasil diupload! Status otomatis diubah menjadi Selesai.") . ($wa_link ? "&open_wa=" . urlencode($wa_link) : ""));

 exit;

 }

 }

 } else {

 // jenis_tt selain 2 (cth: TT-3)

 if (!empty($_FILES['tanda_terima_file']['name'])) {

 $file = $_FILES['tanda_terima_file'];

 $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

 $finfo = finfo_open(FILEINFO_MIME_TYPE);

 $mime = finfo_file($finfo, $file['tmp_name']);

 finfo_close($finfo);



 if ($file['error'] !== 0) {

 $error = "Gagal upload foto. Code: " . $file['error'];

 } elseif (!in_array($mime, $allowed_mime)) {

 $error = "Gagal: File harus berupa gambar (JPG, PNG, WEBP).";

 } else {

 $ext = pathinfo($file['name'], PATHINFO_EXTENSION);

 $fn = time() . '_tt' . $jenis_tt . '_ord' . $id . '.' . strtolower($ext);

 $dir = __DIR__ . '/../uploads/documents/';

 if (!is_dir($dir)) mkdir($dir, 0777, true);

 $target = $dir . $fn;



 if (move_uploaded_file($file['tmp_name'], $target)) {

 $fp = 'uploads/documents/' . $fn;

 $uid = (int)$_SESSION['user_id'];



 $del_old = $conn->prepare("DELETE FROM documents WHERE ref_type='order' AND ref_id=? AND nama_dokumen=?");

 $del_old->bind_param("is", $id, $nama_tt);

 $del_old->execute();



 if (insertTandaTerimaDoc($conn, $id, $nama_tt, $fp, 'Bukti Tanda Terima', $uid)) {

 if ($jenis_tt === 3) {

 $conn->query("UPDATE orders SET status='Diambil' WHERE id=$id");

 $order['status'] = 'Diambil';

 addNotification($conn, $order['user_id'],

 "Order {$order['kode_order']} telah selesai. Terima kasih sudah menggunakan layanan Footstepszs! ",

 '/customer/my_orders.php'

 );

 }

 $uId = (int)$order['user_id'];
 if ($uId > 0 && !empty($order['tier_member'])) {
     $tgl_m = date('Y-m-d');
     $tgl_s = date('Y-m-d', strtotime('+1 month'));
     $tier = $order['tier_member'];
     $chkM = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
     $chkM->bind_param("i", $uId);
     $chkM->execute();
     $mExists = $chkM->get_result()->fetch_assoc();
     if ($mExists) {
         $updM = $conn->prepare("UPDATE members SET level = ?, status = 'Aktif', tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
         $updM->bind_param("sssi", $tier, $tgl_m, $tgl_s, $mExists['id']);
         $updM->execute();
     } else {
         $insM = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Aktif')");
         $insM->bind_param("isss", $uId, $tier, $tgl_m, $tgl_s);
         $insM->execute();
     }
 }
 header("Location: order_detail.php?id=$id&success=" . urlencode("$nama_tt berhasil diupload!"));

 exit;

 } else {

 $error = "Gagal menyimpan ke database: " . $conn->error;

 }

 } else {

 $error = "Gagal memindahkan file ke folder uploads.";

 }

 }

 } else {

 $error = " Pilih file foto terlebih dahulu sebelum menekan Simpan.";

 }

 }

}



// =========================================================

// ACTION: SET LUNAS MANUAL

// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_lunas') {

 $now = date('Y-m-d H:i:s');

 $new_status = ($order['status'] === 'Pending Payment') ? 'Cuci' : $order['status'];

 $upd = $conn->prepare("UPDATE orders SET status_pembayaran='Lunas', payment_confirmed_at=?, status=? WHERE id=?");

 $upd->bind_param("ssi", $now, $new_status, $id);

 if ($upd->execute()) {

 $order_ref = "Order Lunas #" . $order['kode_order'];

 $chkFin = $conn->prepare("SELECT id FROM keuangan WHERE keterangan = ? AND jenis = 'Pemasukan'");

 $chkFin->bind_param("s", $order_ref); $chkFin->execute();

 if ($chkFin->get_result()->num_rows == 0) {

 $adminId = $_SESSION['user_id'] ?? 1;

 $ins = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES ('Pemasukan', 'Order Laundry', ?, ?, CURDATE(), ?)");

 $total = (float)$order['total_harga'];

 $ins->bind_param("dsi", $total, $order_ref, $adminId);

 $ins->execute();

 }

 $uId = (int)$order['user_id'];
 if ($uId > 0 && !empty($order['tier_member'])) {
     $tgl_m = date('Y-m-d');
     $tgl_s = date('Y-m-d', strtotime('+1 month'));
     $tier = $order['tier_member'];
     $chkM = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
     $chkM->bind_param("i", $uId);
     $chkM->execute();
     $mExists = $chkM->get_result()->fetch_assoc();
     if ($mExists) {
         $updM = $conn->prepare("UPDATE members SET level = ?, status = 'Aktif', tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
         $updM->bind_param("sssi", $tier, $tgl_m, $tgl_s, $mExists['id']);
         $updM->execute();
     } else {
         $insM = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Aktif')");
         $insM->bind_param("isss", $uId, $tier, $tgl_m, $tgl_s);
         $insM->execute();
     }
 }
 header("Location: order_detail.php?id=$id&success=" . urlencode("Status pembayaran berhasil diubah menjadi LUNAS!"));

 exit;

 }

}



// =========================================================

// UPDATE STATUS ORDER

// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {

 $newStatus = trim($_POST['status']);

 $valid = ['Pending Payment', 'Cuci', 'Kering', 'Siap Kirim', 'Selesai', 'Canceled', 'Menunggu', 'Diterima', 'Proses', 'Diambil', 'Dibatalkan'];



 if ($newStatus && in_array($newStatus, $valid)) {

 if ($newStatus === $order['status']) {

 $success = "Status tetap: $newStatus (tidak ada perubahan).";

 } else {

 try {

 $conn->begin_transaction();

 $selesai_date = ($newStatus === 'Selesai') ? date('Y-m-d H:i:s') : null;

 $stmt2 = $conn->prepare("UPDATE orders SET status=?, tanggal_selesai=? WHERE id=?");

 $stmt2->bind_param("ssi", $newStatus, $selesai_date, $id);

 if (!$stmt2->execute()) throw new Exception("Gagal update order: " . $stmt2->error);



 if ($newStatus === 'Selesai') {

 $order_ref = "Order Lunas #" . $order['kode_order'];

 $chkFin = $conn->prepare("SELECT id FROM keuangan WHERE keterangan = ? AND jenis = 'Pemasukan'");

 $chkFin->bind_param("s", $order_ref); $chkFin->execute();

 if ($chkFin->get_result()->num_rows == 0) {

 $adminId = $_SESSION['user_id'] ?? 0;

 $ins = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES ('Pemasukan', 'Order Laundry', ?, ?, CURDATE(), ?)");

 $total = (float)$order['total_harga'];

 $ins->bind_param("dsi", $total, $order_ref, $adminId);

 if (!$ins->execute()) throw new Exception("Gagal catat keuangan: " . $ins->error);

 updateMemberPoints($conn, $order['user_id'], $order['total_harga']);

 }

 }



 $msg = "Status order {$order['kode_order']} sekarang: $newStatus";

 addNotification($conn, $order['user_id'], $msg, '/customer/my_orders.php');



 // Send automatic Fonnte WA notification to Customer

 $pesan_wa = "Halo {$order['nama_lengkap']},\n\nInformasi terbaru mengenai pesanan Anda *{$order['kode_order']}*:\n\nStatus pesanan Anda telah diperbarui menjadi: *{$newStatus}*\n\n";

 if ($newStatus === 'Diterima') {

 $pesan_wa .= "Sepatu Anda telah kami terima di store dan masuk antrean pengerjaan. Terima kasih! ";

 } elseif ($newStatus === 'Proses') {

 $pesan_wa .= "Sepatu Anda saat ini sedang dikerjakan oleh tim artisan kami dengan penuh kehati-hatian. Kami akan mengabari Anda jika sudah selesai! ";

 } elseif ($newStatus === 'Selesai') {

 $nota_res = $conn->query("SELECT file_path FROM documents WHERE ref_type='order' AND ref_id={$id} AND nama_dokumen='Nota Pesanan OUT' LIMIT 1")->fetch_assoc();

 $notaOutUrl = $nota_res ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . $nota_res['file_path'] : '';

 $pesan_wa .= "Sepatu Anda telah selesai dikerjakan dan siap diambil atau dikirim kembali!\n\n";

 if ($notaOutUrl) {

 $pesan_wa .= "Berikut link detail Nota Selesai (Nota OUT) Anda:\n{$notaOutUrl}\n\n";

 }

 $pesan_wa .= "Silakan hubungi kami untuk konfirmasi pengambilan / pengiriman. Terima kasih!";

 } elseif ($newStatus === 'Diambil') {

 $pesan_wa .= "Sepatu Anda telah diserahkan / dikirim kembali ke Anda. Terima kasih banyak telah mempercayakan sepatu Anda kepada Footstepszs! Sampai jumpa di order berikutnya! ";

 } elseif ($newStatus === 'Dibatalkan') {

 $pesan_wa .= "Pesanan Anda telah dibatalkan. Jika ada pertanyaan, silakan hubungi kami.";

 } else {

 $pesan_wa .= "Pesanan Anda sedang menunggu diproses.";

 }

 sendWhatsAppNotification($order['no_hp'], $pesan_wa);



 $conn->commit();

 $uId = (int)$order['user_id'];
 if ($uId > 0 && !empty($order['tier_member'])) {
     $tgl_m = date('Y-m-d');
     $tgl_s = date('Y-m-d', strtotime('+1 month'));
     $tier = $order['tier_member'];
     $chkM = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
     $chkM->bind_param("i", $uId);
     $chkM->execute();
     $mExists = $chkM->get_result()->fetch_assoc();
     if ($mExists) {
         $updM = $conn->prepare("UPDATE members SET level = ?, status = 'Aktif', tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
         $updM->bind_param("sssi", $tier, $tgl_m, $tgl_s, $mExists['id']);
         $updM->execute();
     } else {
         $insM = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Aktif')");
         $insM->bind_param("isss", $uId, $tier, $tgl_m, $tgl_s);
         $insM->execute();
     }
 }
 header("Location: order_detail.php?id=$id&success=" . urlencode("Status berhasil diperbarui ke $newStatus!"));

 exit;

 } catch (Throwable $e) {

 $conn->rollback();

 $error = "Terjadi kesalahan: " . $e->getMessage();

 }

 }

 }

}



// =========================================================

// AMBIL DATA TANDA TERIMA

// =========================================================

$docs_tt1 = [];

$docs_tt2 = [];

$docs_tt3 = [];

$res_docs = $conn->query("SELECT * FROM documents WHERE ref_type='order' AND ref_id=$id AND doc_subtype='tanda_terima' ORDER BY id ASC");

while ($d = $res_docs->fetch_assoc()) {

 if (strpos($d['nama_dokumen'], 'Tanda Terima 1') !== false) $docs_tt1[] = $d;

 if (strpos($d['nama_dokumen'], 'Tanda Terima 2') !== false) $docs_tt2[] = $d;

 if (strpos($d['nama_dokumen'], 'Tanda Terima 3') !== false) $docs_tt3[] = $d;

}

// Cek apakah walk-in (guest)

$is_walkin = isset($order['username']) && strpos($order['username'], 'guest_') === 0;



$pageTitle = 'Detail Order';

$activePage = 'orders';

require_once __DIR__ . '/../includes/admin_header.php';



$items = json_decode($order['items'], true) ?: [];

$isKanban = in_array($order['status'], ['Pending Payment', 'Cuci', 'Kering', 'Siap Kirim', 'Selesai', 'Canceled']);

$trackerSteps = $isKanban ? ['Pending Payment', 'Cuci', 'Kering', 'Siap Kirim', 'Selesai'] : ['Menunggu', 'Diterima', 'Proses', 'Selesai', 'Diambil'];

$currentStepIdx = array_search($order['status'], $trackerSteps);

?>



<style>

.tt-panel {

 display: grid;

 grid-template-columns: repeat(3, 1fr);

 gap: 1rem;

 margin-top: 1.5rem;

}

@media(max-width:768px) { .tt-panel { grid-template-columns: 1fr; } }



.tt-card {

 border: 2px solid var(--border);

 border-radius: var(--radius-lg);

 padding: 1.25rem;

 text-align: center;

 position: relative;

 transition: var(--transition);

}

.tt-card.done { border-color: var(--success); background: #F0FDF4; }

.tt-card.pending { border-color: var(--border); background: var(--gray-50); }

.tt-card-num {

 width: 36px; height: 36px; border-radius: 50%;

 background: var(--border);

 color: #9CA3AF;

 font-weight: 800; font-size: 16px;

 display: flex; align-items: center; justify-content: center;

 margin: 0 auto 0.75rem;

}

.tt-card.done .tt-card-num { background: var(--success); color: white; }

.tt-card-title { font-size: 13px; font-weight: 700; margin-bottom: 4px; color: var(--text-primary); }

.tt-card-sub { font-size: 11px; color: var(--text-secondary); margin-bottom: 0.75rem; }

.tt-photo-preview {

 width: 100%;

 aspect-ratio: 4/3;

 object-fit: cover;

 border-radius: 8px;

 border: 1px solid var(--border);

 margin-bottom: 0.75rem;

 cursor: pointer;

}

.tt-upload-btn {

 width: 100%;

 background: white;

 border: 2px dashed var(--border);

 border-radius: 8px;

 padding: 1rem;

 cursor: pointer;

 font-size: 12px;

 color: var(--text-secondary);

 text-align: center;

 transition: var(--transition);

 display: block;

}

.tt-upload-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }

.tt-time { font-size: 10px; color: var(--text-light); margin-top: 4px; }



/* Modal preview foto */

.photo-modal {

 display: none;

 position: fixed; inset: 0;

 background: rgba(0,0,0,0.85);

 z-index: 9999;

 align-items: center;

 justify-content: center;

}

.photo-modal.open { display: flex; }

.photo-modal img { max-width: 90vw; max-height: 90vh; border-radius: 12px; object-fit: contain; }

.photo-modal-close { position: absolute; top: 20px; right: 24px; font-size: 32px; color: white; cursor: pointer; background: none; border: none; }

</style>



<!-- Photo Modal -->

<div class="photo-modal" id="photoModal" onclick="document.getElementById('photoModal').classList.remove('open')">

 <button class="photo-modal-close">×</button>

 <img src="" id="photoModalImg" alt="Preview">

</div>



<!-- Top Bar -->

<div style="display:flex;gap:1rem;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;">

 <a href="orders.php" class="btn btn-outline btn-sm">← Kembali</a>

 <h2 style="font-size:1.1rem;font-weight:700;"><?= htmlspecialchars($order['kode_order']) ?></h2>

 <span class="badge badge-<?= strtolower(str_replace(' ','',$order['status'])) ?>"><?= $order['status'] ?></span>

 <?php if ($is_walkin): ?>

 <span class="badge" style="background:#FEF3C7;color:#92400E">Walk-In</span>

 <?php endif; ?>

 <a href="/admin/dms.php?order_id=<?= $id ?>" class="btn btn-outline btn-sm" style="margin-left:auto;"> DMS Dokumen</a>

</div>



<?php if (!empty($_GET['open_wa'])): ?>

<script>

 setTimeout(function() {

 window.open('<?= htmlspecialchars($_GET['open_wa']) ?>', '_blank');

 }, 500);

</script>

<?php endif; ?>



<?php if ($success): ?><div class="alert alert-success" data-auto-close> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if ($error): ?><div class="alert alert-danger" style="border:2px solid red; font-weight:bold; font-size:15px; padding:15px;"><?= htmlspecialchars($error) ?></div><?php endif; ?>



<!-- ORDER TRACKER -->

<div class="card" style="margin-bottom:1.5rem;">

 <div class="order-tracker">

 <?php foreach ($trackerSteps as $idx => $step):

 $cls = '';

 if ($currentStepIdx !== false && $idx < $currentStepIdx) $cls = 'done';

 elseif ($currentStepIdx !== false && $idx === $currentStepIdx) $cls = 'active';

 ?>

 <div class="tracker-step <?= $cls ?>">

 <div class="tracker-circle"><?= $cls === 'done' ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:14px;height:14px;display:inline;"><polyline points="20 6 9 17 4 12"/></svg>' : ($idx + 1) ?></div>

 <div class="tracker-label"><?= $step ?></div>

 </div>

 <?php endforeach; ?>

 </div>

</div>



<!-- ORDER + CUSTOMER INFO -->

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">

 <div class="card">

 <h3 style="font-size:16px;margin-bottom:1.25rem;">Detail Order</h3>

 <div style="font-size:14px;line-height:2.2;">

 <div><strong>Kode Order:</strong> <?= htmlspecialchars($order['kode_order']) ?></div>

 <div><strong>Tanggal Order:</strong> <?= date('d M Y H:i', strtotime($order['tanggal_order'])) ?></div>

 <div><strong>Jenis Waktu:</strong> <?= $order['jenis_waktu'] ?></div>

 <div><strong>Metode Bayar:</strong> <?= htmlspecialchars($order['metode_pembayaran'] ?? 'COD') ?></div>

 <div><strong>Status Bayar:</strong> 

 <span class="badge" style="background:<?= ($order['status_pembayaran'] ?? '') === 'Lunas' ? '#D1FAE5;color:#065F46' : '#FEE2E2;color:#991B1B' ?>"><?= htmlspecialchars($order['status_pembayaran'] ?? 'Belum Lunas') ?></span>

 <?php if (($order['status_pembayaran'] ?? 'Belum Lunas') !== 'Lunas'): ?>

 <form method="POST" style="display:inline;margin-left:6px;" onsubmit="return confirm('Konfirmasi pembayaran lunas?')">

 <input type="hidden" name="action" value="set_lunas">

 <button type="submit" class="btn btn-primary btn-sm" style="padding:2px 8px;font-size:11px;">Set Lunas</button>

 </form>

 <?php endif; ?>

 </div>

 <?php if (!empty($order['diskon_member']) && $order['diskon_member'] > 0): ?>

 <div><strong>Diskon Member (<?= htmlspecialchars($order['tier_member'] ?? '') ?>):</strong> <span style="color:#059669">- <?= formatRupiah($order['diskon_member']) ?></span></div>

 <?php endif; ?>

 <div><strong>Estimasi Selesai:</strong> <?= $order['tanggal_estimasi'] ? date('d M Y', strtotime($order['tanggal_estimasi'])) : '-' ?></div>

 <div><strong>Tanggal Selesai:</strong> <?= $order['tanggal_selesai'] ? date('d M Y H:i', strtotime($order['tanggal_selesai'])) : '-' ?></div>

 <div><strong>Alamat Pickup:</strong> <?= htmlspecialchars($order['pickup_address'] ?: '-') ?></div>

 <div><strong>Catatan:</strong> <?= htmlspecialchars($order['catatan'] ?: '-') ?></div>

 </div>

 </div>

 <div class="card">

 <h3 style="font-size:16px;margin-bottom:1.25rem;">Informasi Customer</h3>

 <?php if ($is_walkin): ?>

 <div style="display:inline-flex;align-items:center;gap:6px;background:#FEF3C7;color:#92400E;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-bottom:1rem;">

 Walk-In Customer (Tanpa Akun)

 </div>

 <?php endif; ?>

 <div style="font-size:14px;line-height:2.2;">

 <div><strong>Nama:</strong> <?= htmlspecialchars($order['nama_lengkap']) ?></div>

 <div><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></div>

 <div><strong>No HP:</strong> <?= htmlspecialchars($order['no_hp'] ?? '-') ?></div>

 <div><strong>Alamat:</strong> <?= htmlspecialchars($order['alamat'] ?? '-') ?></div>

 <?php if ($is_walkin): ?>

 <div><strong>Tipe:</strong> <span style="color:#92400E;font-weight:700">Walk-In / Guest</span></div>

 <?php else: ?>

 <div><strong>Tipe:</strong> <span style="color:#065F46;font-weight:700">Member Terdaftar</span></div>

 <?php endif; ?>

 </div>

 <?php if ($order['no_hp']): ?>

 <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$order['no_hp']) ?>" target="_blank" class="btn btn-success btn-sm" style="margin-top:1rem;"> WhatsApp Customer</a>

 <?php endif; ?>

 </div>

</div>



<!-- ITEMS TABLE -->

<div class="card" style="margin-top:1.5rem;">

 <h3 style="font-size:16px;margin-bottom:1.25rem;">Item Cucian</h3>

 <table class="data-table">

 <thead><tr><th>Jenis</th><th>Treatment</th><th>Qty</th><th>Harga Satuan</th><th>Subtotal</th></tr></thead>

 <tbody>

 <?php if (empty($items)): ?>

 <tr><td colspan="5" style="text-align:center;color:#9CA3AF">Tidak ada item.</td></tr>

 <?php else: foreach ($items as $item): ?>

 <tr>

 <td><?= ucfirst($item['type'] ?? '-') ?></td>

 <td><?= htmlspecialchars($item['treatment'] ?? '-') ?></td>

 <td><?= $item['qty'] ?? 0 ?></td>

 <td><?= formatRupiah($item['harga_satuan'] ?? 0) ?></td>

 <td><strong><?= formatRupiah($item['subtotal'] ?? 0) ?></strong></td>

 </tr>

 <?php endforeach; endif; ?>

 </tbody>

 <tfoot>

 <tr>

 <td colspan="4" style="text-align:right;font-weight:700;padding:1rem 1.25rem;">TOTAL</td>

 <td style="font-weight:800;font-size:16px;color:var(--primary);padding:1rem 1.25rem;"><?= formatRupiah($order['total_harga']) ?></td>

 </tr>

 </tfoot>

 </table>

</div>



<!-- UPDATE STATUS -->

<div style="display:grid;grid-template-columns:1fr;gap:1.5rem;margin-top:1.5rem;">

<div class="card">

 <h3 style="font-size:16px;margin-bottom:1.25rem;"> Update Status Order</h3>

 <form method="POST" action="order_detail.php?id=<?= $id ?>" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap;">

 <input type="hidden" name="id" value="<?= $id ?>">

 <div class="form-group" style="margin:0;flex:1;min-width:200px;">

 <label class="form-label">Status Baru</label>

 <select name="status" class="form-control">

 <?php 

 $optStatuses = ['Pending Payment', 'Cuci', 'Kering', 'Siap Kirim', 'Selesai', 'Canceled'];

 if (!in_array($order['status'], $optStatuses)) {

 $optStatuses = array_merge($optStatuses, ['Menunggu', 'Diterima', 'Proses', 'Diambil', 'Dibatalkan']);

 }

 foreach ($optStatuses as $s): ?>

 <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>

 <?php endforeach; ?>

 </select>

 </div>

 <button type="submit" name="update_status" value="1" class="btn btn-primary">Simpan Perubahan</button>

 </form>

 <p style="font-size:12px;color:#9CA3AF;margin-top:0.75rem;">

 Saat status diubah ke <strong>Selesai</strong>, sistem otomatis mencatat pemasukan keuangan.

 Status <strong>Diambil</strong> otomatis diset saat status diubah menjadi Diambil.

 </p>

</div>

</div>



<!-- ========================================================

 TANDA TERIMA 3 TAHAP

 ======================================================== -->

<div class="card" style="margin-top:1.5rem;">

 <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem;">

 <h3 style="font-size:16px;margin:0;"> Bukti Tanda Terima</h3>

 <div style="font-size:12px;color:var(--text-secondary);">

 <?= (!empty($docs_tt2) ? '1/1' : '0/1') ?> tahap selesai

 </div>

 </div>

 <p style="font-size:13px;color:var(--text-secondary);margin-bottom:1.5rem;">

 Admin hanya perlu mengupload <strong>Foto Selesai Dikerjakan</strong>. Foto awal otomatis terlampir dari pesanan customer.

 </p>



 <?php 

 $has_tt2 = !empty($docs_tt2); 

 $total_qty = 0;

 foreach ($items as $item) {

     $total_qty += (int)($item['qty'] ?? 1);

 }

 $can_upload_tt2 = in_array($order['status'], ['Siap Kirim', 'Selesai', 'Diambil']);

 $show_tt2 = $can_upload_tt2 || $has_tt2;

 ?>

 <div class="tt-panel" style="grid-template-columns: <?= $show_tt2 ? '1fr 1fr' : '1fr' ?>; gap: 1.5rem;">

 

 <!-- TT 1: FOTO AWAL (Read Only) -->

 <div class="tt-card <?= !empty($docs_tt1) ? 'done' : 'pending' ?>">

 <div class="tt-card-num">1</div>

 <div class="tt-card-title"> Foto Awal (Barang Masuk)</div>

 <div class="tt-card-sub">Kondisi saat diserahkan customer</div>



 <?php if (!empty($docs_tt1)): ?>

 <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; margin-top: 1rem;">

 <?php foreach ($docs_tt1 as $doc1): ?>

 <div style="text-align:center;">

 <img src="/<?= htmlspecialchars($doc1['file_path']) ?>"

 class="tt-photo-preview"

 onclick="openPhotoModal('/<?= htmlspecialchars($doc1['file_path']) ?>')"

 alt="Tanda Terima 1"

 title="Klik untuk perbesar" style="margin-bottom:4px;">

 <a href="/<?= htmlspecialchars($doc1['file_path']) ?>" target="_blank" style="font-size:10px;" class="btn btn-outline btn-sm">View</a>

 </div>

 <?php endforeach; ?>

 </div>

 <div class="tt-time" style="margin-top:8px;"><?= date('d M Y, H:i', strtotime($docs_tt1[0]['uploaded_at'] ?? $docs_tt1[0]['created_at'] ?? $docs_tt1[0]['tanggal'])) ?></div>

 <?php else: ?>

 <div style="padding:2rem 1rem; text-align:center; color:#9CA3AF; font-size:13px; border:2px dashed #E5E7EB; border-radius:8px; margin-top:1rem;">

 Tidak ada foto awal yang dilampirkan.

 </div>

 <?php endif; ?>

 </div>



 <!-- TT 2: FOTO AKHIR (Upload Admin - Hanya muncul saat status Siap Kirim / Selesai) -->

 <?php if ($show_tt2): ?>

 <div class="tt-card <?= $has_tt2 ? 'done' : 'pending' ?>">

 <div class="tt-card-num"><?= $has_tt2 ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="width:16px;height:16px;display:inline;"><polyline points="20 6 9 17 4 12"/></svg>' : '2' ?></div>

 <div class="tt-card-title">Foto Akhir (Selesai Dikerjakan)</div>

 <div class="tt-card-sub">Upload bukti <?= $total_qty ?> item sudah bersih.</div>



 <?php if ($has_tt2): ?>

 <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; margin-top: 1rem;">

 <?php foreach ($docs_tt2 as $doc2): ?>

 <div style="text-align:center;">

 <img src="/<?= htmlspecialchars($doc2['file_path']) ?>"

 class="tt-photo-preview"

 onclick="openPhotoModal('/<?= htmlspecialchars($doc2['file_path']) ?>')"

 alt="Tanda Terima 2" style="margin-bottom:4px;">

 <a href="/<?= htmlspecialchars($doc2['file_path']) ?>" target="_blank" style="font-size:10px;" class="btn btn-outline btn-sm">View</a>

 </div>

 <?php endforeach; ?>

 </div>

 <div class="tt-time" style="margin-top:8px;"><?= date('d M Y, H:i', strtotime($docs_tt2[0]['uploaded_at'] ?? $docs_tt2[0]['created_at'] ?? $docs_tt2[0]['tanggal'])) ?></div>

 

 <?php if ($can_upload_tt2): ?>

 <div style="margin-top:0.75rem;display:flex;gap:6px;justify-content:center;flex-wrap:wrap;">

 <button onclick="document.getElementById('ttForm2').classList.toggle('hidden')" class="btn btn-outline btn-sm">Ganti Semua Foto</button>

 </div>

 <?php endif; ?>

 

 <?php if ($order['no_hp']): ?>

 <?php

 $wa_hp2 = preg_replace('/[^0-9]/', '', $order['no_hp']);

 if (substr($wa_hp2, 0, 1) === '0') $wa_hp2 = '62' . substr($wa_hp2, 1);

 

 // Fetch nota link

 $nota_res = $conn->query("SELECT file_path FROM documents WHERE ref_type='order' AND ref_id={$id} AND nama_dokumen='Nota Pesanan OUT' LIMIT 1")->fetch_assoc();

 $notaOutUrl = $nota_res ? 'http://' . $_SERVER['HTTP_HOST'] . '/' . $nota_res['file_path'] : '';

 

 $pesan2 = "Halo {$order['nama_lengkap']},\n\nSepatu Anda dengan nomor pesanan *{$order['kode_order']}* telah *selesai dikerjakan* dan siap diambil atau dikirim!\n\nBerikut adalah BUKTI SELESAI (Nota OUT) beserta perbandingan kondisinya:\n{$notaOutUrl}\n\nLokasi: Footstepszs Boutique Gallery\nJl. Cluster Tigris Raya No. 29, Malang\nWA/Telp: 089681276080\n\nSilakan konfirmasi apakah ingin diambil langsung atau dikirimkan (delivery).\n\nTerima kasih!";

 ?>

 <a href="https://wa.me/<?= $wa_hp2 ?>?text=<?= rawurlencode($pesan2) ?>" target="_blank" class="btn btn-success btn-sm w-100" style="margin-top:0.5rem;">

 Kirim Nota OUT ke WhatsApp

 </a>

 <?php endif; ?>

 <?php endif; ?>



 <?php if ($can_upload_tt2): ?>

 <form method="POST" enctype="multipart/form-data" id="ttForm2" class="<?= $has_tt2 ? 'hidden' : '' ?>" style="margin-top:1rem;">

 <input type="hidden" name="jenis_tt" value="2">

 

 <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:12px;">

 <?php for ($i = 0; $i < $total_qty; $i++): ?>

 <div style="text-align:left; background:var(--gray-50); padding:10px; border-radius:8px; border:1px solid var(--border);">

 <label class="form-label" style="font-weight:700; margin-bottom:4px; font-size:12px; display:block;">Foto Item #<?= ($i + 1) ?> *</label>

 <div class="tt-upload-btn" onclick="document.getElementById('ttFile2_<?= $i ?>').click()" style="padding: 8px;">

 Pilih Foto

 <input type="file" id="ttFile2_<?= $i ?>" name="tanda_terima_file_<?= $i ?>" accept="image/*" style="display:none" onchange="previewTT2(this, <?= $i ?>)" required>

 </div>

 <div id="ttPreview2_<?= $i ?>" style="display:none;margin-top:0.5rem; text-align:center;">

 <img id="ttPreviewImg2_<?= $i ?>" src="" style="max-height:80px;border-radius:4px;aspect-ratio:4/3;object-fit:cover;border:1px solid var(--border);">

 </div>

 </div>

 <?php endfor; ?>

 </div>



 <button type="submit" name="upload_tanda_terima" value="1" class="btn btn-primary w-100" style="font-size:13px;">

 Simpan & Generate Nota OUT

 </button>

 </form>

 <?php endif; ?>

 </div>

 <?php endif; ?>

 </div>



 <div style="margin-top:1.5rem;padding:1rem;background:var(--gray-50);border-radius:var(--radius);font-size:13px;color:var(--text-secondary);">

 <strong style="color:var(--text-primary);">Keterangan:</strong>

 Untuk menandai order diambil oleh customer, silakan ubah status pada kotak <strong>Update Status Order</strong> di atas menjadi <strong>Diambil</strong>.

 </div>

</div>



<script>

// Preview foto sebelum upload

function previewTT(input, num) {

 if (input.files && input.files[0]) {

 const reader = new FileReader();

 reader.onload = function(e) {

 document.getElementById('ttPreview' + num).style.display = 'block';

 document.getElementById('ttPreviewImg' + num).src = e.target.result;

 };

 reader.readAsDataURL(input.files[0]);

 }

}



function previewTT2(input, index) {

 if (input.files && input.files[0]) {

 const reader = new FileReader();

 reader.onload = function(e) {

 document.getElementById('ttPreview2_' + index).style.display = 'block';

 document.getElementById('ttPreviewImg2_' + index).src = e.target.result;

 };

 reader.readAsDataURL(input.files[0]);

 }

}



// Modal preview foto besar

function openPhotoModal(src) {

 document.getElementById('photoModalImg').src = src;

 document.getElementById('photoModal').classList.add('open');

}



document.getElementById('photoModal').addEventListener('click', function(e) {

 if (e.target === this) this.classList.remove('open');

});



// Auto-close alerts

document.querySelectorAll('[data-auto-close]').forEach(el => {

 setTimeout(() => {

 el.style.transition = 'opacity 0.5s'; el.style.opacity = '0';

 setTimeout(() => el.remove(), 500);

 }, 6000);

});



// Confirm delete

document.querySelectorAll('[data-confirm]').forEach(btn => {

 btn.addEventListener('click', e => { if (!confirm(btn.dataset.confirm)) e.preventDefault(); });

});



// Helper: toggle hidden class

document.querySelectorAll('.hidden').forEach(el => el.style.display = 'none');

</script>



<style>

.hidden { display: none !important; }

</style>



<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

