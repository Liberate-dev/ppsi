<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isCustomer()) {
    header('Location: /login.php');
    exit;
}

$kode = trim($_GET['kode'] ?? '');
$nota_db_path = trim($_GET['nota'] ?? '');

if (!$kode || !$nota_db_path) {
    header('Location: /customer/dashboard.php');
    exit;
}

// Handle Demo Payment Simulator
$demo_triggered = false;
if (isset($_GET['demo_pay']) && $_GET['demo_pay'] == '1') {
    if (simulateOrderPayment($conn, $kode)) {
        $demo_triggered = true;
    }
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

$pesanWa = "Halo Admin Footstepszs,\n\nSaya telah membuat pesanan laundry:\n\n*Detail Pesanan:*\n- Kode: {$kode}\n- Nama: {$nama_cust}\n- Waktu: {$order_data['jenis_waktu']}\n- Metode: {$order_data['metode_pembayaran']}\n- Status Bayar: {$order_data['status_pembayaran']}\n\n*Item:*\n{$items_text_wa}*Total: {$total_rp}*\n\nCatatan: {$catatan_wa}\n\nBerikut link Nota IN dan foto kondisi awal:\n{$notaUrl}\n\nMohon konfirmasinya. Terima kasih!";
$waLink = "https://wa.me/{$waNumber}?text=" . rawurlencode($pesanWa);

$is_lunas = ($order_data['status_pembayaran'] === 'Lunas');
$metode = $order_data['metode_pembayaran'] ?? 'COD';
?>

<div style="max-width:640px; margin:2.5rem auto; text-align:center;">
    <div style="width:64px; height:64px; background:#ECFDF5; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.25rem;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:32px; height:32px; stroke:var(--success);"><polyline points="20 6 9 17 4 12"/></svg>
    </div>
    <h2 style="font-size:1.6rem; font-weight:800; color:var(--navy); margin-bottom:0.5rem;">Pesanan Berhasil Dibuat</h2>
    <p style="color:var(--text-secondary); margin-bottom:1.75rem; font-size:14.5px; line-height:1.6;">
        Pesanan Anda dengan kode <strong><?= htmlspecialchars($kode) ?></strong> telah tersimpan di sistem. Nota IN dan dokumentasi foto awal telah diterbitkan.
    </p>

    <!-- Ringkasan Tagihan & Metode -->
    <div class="card" style="background:#FFF; border:1px solid var(--border); margin-bottom:1.5rem; text-align:left; padding:1.25rem 1.5rem; border-radius:12px;">
        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; font-size:13.5px;">
            <span style="color:var(--text-secondary);">Metode Pembayaran</span>
            <strong><?= htmlspecialchars($metode) ?></strong>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; font-size:13.5px;">
            <span style="color:var(--text-secondary);">Status Pembayaran</span>
            <span>
                <?php if ($is_lunas): ?>
                    <span class="badge" style="background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; font-weight:700;">LUNAS</span>
                <?php else: ?>
                    <span class="badge" style="background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; font-weight:700;">Menunggu Pembayaran</span>
                <?php endif; ?>
            </span>
        </div>
        <div style="display:flex; justify-content:space-between; font-size:15px; border-top:1px solid var(--border); padding-top:0.75rem; font-weight:800;">
            <span>Total Tagihan</span>
            <span style="color:var(--primary);"><?= $total_rp ?></span>
        </div>
    </div>

    <!-- Kondisi 1: SUDAH LUNAS (Baik via Demo maupun sistem) -->
    <?php if ($is_lunas): ?>
        <div style="background:#ECFDF5; border:1.5px solid #10B981; border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; text-align:center;">
            <div style="width:40px; height:40px; border-radius:50%; background:#10B981; color:white; display:inline-flex; align-items:center; justify-content:center; margin-bottom:0.6rem;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:22px; height:22px;"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#047857; margin-bottom:4px;">Status Terverifikasi</div>
            <h3 style="font-size:17px; font-weight:800; color:#065F46; margin-bottom:6px;">Pembayaran Terkonfirmasi LUNAS</h3>
            <p style="font-size:13px; color:#047857; margin-bottom:1rem; line-height:1.5;">
                Pembayaran <?= htmlspecialchars($metode) ?> telah diterima. Pesanan Anda saat ini sedang diproses ke tahap <strong>Cuci (Antrean Pencucian)</strong>.
            </p>
            <div style="display:flex; gap:0.5rem; justify-content:center; flex-wrap:wrap;">
                <span style="font-size:12px; background:#D1FAE5; color:#065F46; padding:5px 12px; border-radius:100px; font-weight:700;">Status: LUNAS</span>
                <span style="font-size:12px; background:#DBEAFE; color:#1E40AF; padding:5px 12px; border-radius:100px; font-weight:700;">Tahap: Cuci</span>
            </div>
        </div>
    <?php else: ?>
        <!-- Kondisi 2: BELUM LUNAS - Tampilkan Instruksi Bayar -->
        <?php if ($metode === 'Transfer Bank'): ?>
            <div style="background:#EFF6FF; border:1px solid #BFDBFE; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem; text-align:left;">
                <h4 style="font-weight:700; color:#1E40AF; margin-bottom:0.5rem; font-size:14px;">Instruksi Transfer Bank</h4>
                <div style="background:#FFF; padding:1rem; border-radius:8px; border:1px solid #DBEAFE; font-size:13.5px; line-height:1.6;">
                    <div>Bank: <strong>BCA (Bank Central Asia)</strong></div>
                    <div>No. Rekening: <strong style="font-size:1.15rem; color:#1D4ED8;">1234-5678-90</strong></div>
                    <div>Atas Nama: <strong>Footstepszs Studio</strong></div>
                    <div>Batas Waktu Bayar: <strong>24 Jam</strong></div>
                </div>
            </div>
        <?php elseif ($metode === 'QRIS'): ?>
            <div style="background:#FDF2F8; border:1px solid #FBCFE8; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem; text-align:center;">
                <h4 style="font-weight:700; color:#9D174D; margin-bottom:0.5rem; font-size:14px;">Scan Pembayaran QRIS</h4>
                <p style="font-size:13px; color:#831843; margin-bottom:0.75rem;">Scan menggunakan GoPay, OVO, Dana, ShopeePay, atau m-Banking:</p>
                <div style="background:#FFF; display:inline-block; padding:10px; border-radius:10px; border:1px solid #F472B6; margin-bottom:0.75rem;">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=<?= urlencode("QRIS_FOOTSTEPSZS_" . $kode . "_" . $order_data['total_harga']) ?>" alt="QRIS Code" style="display:block;">
                </div>
                <div style="font-size:12px; color:#831843;">Batas Waktu: <strong>24 Jam</strong></div>
            </div>
        <?php endif; ?>

        <!-- FITUR DEMO MODE SIMULATOR -->
        <?php if (in_array($metode, ['Transfer Bank', 'QRIS'])): ?>
            <div style="background:#F0FDF4; border:2px dashed #16A34A; border-radius:12px; padding:1.35rem; margin-bottom:1.5rem; text-align:center;">
                <div style="font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#166534; margin-bottom:4px;">Simulasi Pembayaran (Demo Mode)</div>
                <h4 style="font-size:15px; font-weight:700; color:#14532D; margin-bottom:6px;">Uji Coba Alur Pembayaran <?= htmlspecialchars($metode) ?></h4>
                <p style="font-size:12.5px; color:#166534; margin-bottom:1rem; line-height:1.4;">
                    Tekan tombol di bawah ini untuk mensimulasikan kondisi pembayaran langsung lunas tanpa transfer sungguhan. Sistem akan memperbarui status order ke LUNAS dan meneruskan pesanan ke antrean cuci.
                </p>
                <a href="/customer/order_success.php?demo_pay=1&kode=<?= urlencode($kode) ?>&nota=<?= urlencode($nota_db_path) ?>" class="btn" style="background:#16A34A; color:white; padding:11px 22px; font-weight:800; font-size:14px; border-radius:8px; display:inline-flex; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(22, 163, 74, 0.25);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg>
                    Simulasikan Pembayaran Berhasil (Demo)
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Konfirmasi WA -->
    <div class="card" style="background:#FFF; border:1px solid var(--border); margin-bottom:1.5rem; text-align:left; padding:1.25rem 1.5rem; border-radius:12px;">
        <h3 style="font-size:14px; color:var(--navy); margin-bottom:0.5rem; font-weight:700;">Konfirmasi Pesanan ke Admin</h3>
        <p style="font-size:13px; color:var(--text-secondary); margin-bottom:1rem; line-height:1.5;">
            Kirimkan rincian pesanan dan Nota IN ke WhatsApp admin untuk memverifikasi jadwal penjemputan atau pengantaran.
        </p>
        
        <a href="<?= $waLink ?>" target="_blank" class="btn" style="background:#16A34A; color:white; display:flex; align-items:center; justify-content:center; gap:8px; font-weight:700; padding:11px 18px; font-size:14px; border-radius:8px; width:100%;">
            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
            Kirim Rincian ke WhatsApp Admin
        </a>
    </div>

    <div style="display:flex; gap:0.75rem; justify-content:center; flex-wrap:wrap;">
        <a href="<?= $notaUrl ?>" target="_blank" class="btn btn-outline btn-sm">Buka Nota IN</a>
        <a href="/customer/my_orders.php" class="btn btn-primary btn-sm">Lihat Riwayat Order</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
