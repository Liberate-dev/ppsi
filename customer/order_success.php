<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isCustomer()) {
    header('Location: /login.php');
    exit;
}

$kode = $_GET['kode'] ?? '';
$nota_db_path = $_GET['nota'] ?? '';

if (!$kode || !$nota_db_path) {
    header('Location: /customer/dashboard.php');
    exit;
}

$order_res = $conn->query("SELECT * FROM orders WHERE kode_order='" . $conn->real_escape_string($kode) . "'");
$order_data = $order_res->fetch_assoc();

if (!$order_data) {
    header('Location: /customer/dashboard.php');
    exit;
}

$pageTitle = 'Order Berhasil';
require_once __DIR__ . '/../includes/customer_header.php';

$notaUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/' . ltrim($nota_db_path, '/');
$waNumber = '6289681276080';

// Format items for WA text
$items_arr = json_decode($order_data['items'], true) ?? [];
$items_text_wa = "";
foreach ($items_arr as $item) {
    $qty = $item['qty'] ?? 1;
    $type = $item['type'] ?? '-';
    $treat = $item['treatment'] ?? '-';
    $items_text_wa .= "- $qty $type ($treat)\n";
}
$total_rp = "Rp " . number_format($order_data['total_harga'], 0, ',', '.');
$catatan_wa = $order_data['catatan'] ? $order_data['catatan'] : '-';
$nama_cust = $_SESSION['nama'] ?? 'Customer';

$pesanWa = "Halo Admin Footstepszs!\n\nSaya baru saja membuat pesanan laundry:\n\n*Detail Pesanan:*\n- Kode: {$kode}\n- Nama: {$nama_cust}\n- Waktu: {$order_data['jenis_waktu']}\n\n*Item:*\n{$items_text_wa}*Total: {$total_rp}*\n\nCatatan: {$catatan_wa}\n\nBerikut Nota Pesanan (IN) beserta bukti foto kondisi awal barang:\n👉 {$notaUrl}\n\nMohon segera diproses ya!";
$waLink = "https://wa.me/{$waNumber}?text=" . rawurlencode($pesanWa);
?>

<div style="max-width:600px; margin:2rem auto; text-align:center;">
    <div style="font-size:4rem; margin-bottom:1rem;">🎉</div>
    <h2 style="font-size:1.8rem; font-weight:800; color:var(--text-primary); margin-bottom:0.5rem;">Hore! Pesanan Berhasil Dibuat</h2>
    <p style="color:var(--text-secondary); margin-bottom:2rem; font-size:15px; line-height:1.6;">
        Pesanan Anda dengan nomor kode <strong><?= htmlspecialchars($kode) ?></strong> telah masuk ke sistem kami. 
        Nota IN (Struk Order Masuk) sudah selesai dibuat otomatis.
    </p>

    <div class="card" style="background:var(--gray-50); border:1px solid var(--border); margin-bottom:2rem;">
        <h3 style="font-size:14px; color:var(--text-primary); margin-bottom:1rem; font-weight:700;">Langkah Selanjutnya:</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:1.5rem;">
            Mohon klik tombol di bawah ini untuk mengirimkan Nota IN pesanan Anda langsung ke WhatsApp Admin agar kami bisa segera mengatur penjemputan/pengiriman.
        </p>
        
        <a href="<?= $waLink ?>" target="_blank" class="btn" style="background:#25D366; color:white; display:flex; align-items:center; justify-content:center; gap:10px; font-weight:800; padding:12px; font-size:15px;">
            <svg viewBox="0 0 24 24" width="24" height="24" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            Kirim Nota IN ke WA Admin
        </a>
    </div>

    <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
        <a href="<?= $notaUrl ?>" target="_blank" class="btn btn-outline">📄 Lihat Nota IN</a>
        <a href="/customer/my_orders.php" class="btn btn-primary">Lihat Daftar Order</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
