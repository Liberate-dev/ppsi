<?php
session_start();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/admin_header.php';

// ---- Stats ----
$stats = [];
$stats['total_orders']  = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$stats['pending']       = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status='Menunggu'")->fetch_assoc()['c'];
$stats['today_income']  = $conn->query("SELECT COALESCE(SUM(jumlah),0) as s FROM keuangan WHERE jenis='Pemasukan' AND tanggal=CURDATE()")->fetch_assoc()['s'];
$stats['total_customers']= $conn->query("SELECT COUNT(*) as c FROM users WHERE role='customer'")->fetch_assoc()['c'];

$stats['month_income']  = $conn->query("SELECT COALESCE(SUM(jumlah),0) as s FROM keuangan WHERE jenis='Pemasukan' AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetch_assoc()['s'];
$stats['month_expense'] = $conn->query("SELECT COALESCE(SUM(jumlah),0) as s FROM keuangan WHERE jenis='Pengeluaran' AND MONTH(tanggal)=MONTH(CURDATE()) AND YEAR(tanggal)=YEAR(CURDATE())")->fetch_assoc()['s'];

// Mark all admin notifications as read when visiting dashboard
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=0");

// Recent orders
$res_ro = $conn->query("SELECT o.*, u.nama_lengkap FROM orders o JOIN users u ON o.user_id=u.id ORDER BY o.id DESC LIMIT 8");
$recent_orders = [];
if ($res_ro) {
    while($row = $res_ro->fetch_assoc()) { $recent_orders[] = $row; }
}
?>

<!-- Stats Row -->
<div class="stats-row">
    <div class="stat-box">
        <div class="stat-icon purple">📦</div>
        <div>
            <div class="stat-box-value"><?= number_format($stats['total_orders']) ?></div>
            <div class="stat-box-label">Total Order</div>
            <div class="stat-box-change">↑ <?= $stats['pending'] ?> menunggu</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon green">💰</div>
        <div>
            <div class="stat-box-value" style="font-size:1.2rem"><?= formatRupiah($stats['month_income']) ?></div>
            <div class="stat-box-label">Pemasukan Bulan Ini</div>
            <div class="stat-box-change">Pengeluaran: <?= formatRupiah($stats['month_expense']) ?></div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-icon amber">💵</div>
        <div>
            <div class="stat-box-value" style="font-size:1.2rem"><?= formatRupiah($stats['today_income']) ?></div>
            <div class="stat-box-label">Pemasukan Hari Ini</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:2rem;">
    <a href="/admin/orders.php" class="btn btn-primary">📦 Kelola Order</a>
    <a href="/admin/customers.php" class="btn btn-outline">👥 Tambah Customer</a>
    <a href="/admin/keuangan.php" class="btn btn-outline">💰 Input Keuangan</a>
    <a href="/admin/dms.php" class="btn btn-outline">📁 DMS Dokumen</a>
</div>

<!-- Recent Orders -->
<div class="table-wrap">
    <div class="table-header">
        <span class="table-title">Order Terbaru</span>
        <a href="/admin/orders.php" class="btn btn-outline btn-sm">Lihat Semua →</a>
    </div>
    <table class="data-table">
        <thead><tr>
            <th>Kode Order</th><th>Customer</th><th>Waktu</th><th>Total</th><th>Status</th><th>Tanggal</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($recent_orders)): ?>
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:#9CA3AF;">Belum ada order.</td></tr>
        <?php else: foreach ($recent_orders as $o): ?>
            <tr>
                <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
                <td><?= htmlspecialchars($o['nama_lengkap']) ?></td>
                <td><?= $o['jenis_waktu'] ?></td>
                <td><strong><?= formatRupiah($o['total_harga']) ?></strong></td>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','',($o['status']))) ?>"><?= $o['status'] ?></span></td>
                <td><?= date('d M Y', strtotime($o['tanggal_order'])) ?></td>
                <td><a href="/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
