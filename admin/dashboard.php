<?php
session_start();
$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require_once __DIR__ . '/../includes/admin_header.php';

// ---- Stats ----
$stats = [];
$stats['total_orders']  = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$stats['pending']       = $conn->query("SELECT COUNT(*) as c FROM orders WHERE status IN ('Menunggu', 'Pending Payment', 'Diterima')")->fetch_assoc()['c'];
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
<div class="stats-row" style="margin-bottom:2rem;">
    <div class="stat-box">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        </div>
        <div>
            <div class="stat-box-value"><?= number_format($stats['total_orders']) ?></div>
            <div class="stat-box-label">Total Order</div>
            <div class="stat-box-change" style="color:var(--primary);font-weight:600;"><?= $stats['pending'] ?> perlu diproses</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="font-size:1.25rem"><?= formatRupiah($stats['month_income']) ?></div>
            <div class="stat-box-label">Pemasukan Bulan Ini</div>
            <div class="stat-box-change">Biaya: <?= formatRupiah($stats['month_expense']) ?></div>
        </div>
    </div>

    <div class="stat-box">
        <div class="stat-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="font-size:1.25rem"><?= formatRupiah($stats['today_income']) ?></div>
            <div class="stat-box-label">Pemasukan Hari Ini</div>
            <div class="stat-box-change"><?= $stats['total_customers'] ?> customer terdaftar</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-bottom:2rem;">
    <a href="/admin/orders.php" class="btn btn-primary">Kelola Kanban Order</a>
    <a href="/admin/order_create.php" class="btn btn-outline">+ Order Walk-In</a>
    <a href="/admin/customers.php" class="btn btn-outline">Akun Customer</a>
    <a href="/admin/keuangan.php" class="btn btn-outline">Catat Keuangan</a>
    <a href="/admin/dms.php" class="btn btn-outline">DMS Dokumen</a>
</div>

<!-- Recent Orders -->
<div class="table-wrap">
    <div class="table-header">
        <span class="table-title">Order Terbaru</span>
        <a href="/admin/orders.php" class="btn btn-outline btn-sm">Lihat Semua di Kanban</a>
    </div>
    <table class="data-table">
        <thead><tr>
            <th>Kode Order</th><th>Customer</th><th>Kecepatan</th><th>Total Tagihan</th><th>Status Order</th><th>Tanggal Masuk</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($recent_orders)): ?>
            <tr><td colspan="7" style="text-align:center;padding:2rem;color:#9CA3AF;">Belum ada pesanan masuk.</td></tr>
        <?php else: foreach ($recent_orders as $o): ?>
            <tr>
                <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
                <td><?= htmlspecialchars($o['nama_lengkap']) ?></td>
                <td><span class="badge badge-silver"><?= htmlspecialchars($o['jenis_waktu']) ?></span></td>
                <td><strong><?= formatRupiah($o['total_harga']) ?></strong></td>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','',($o['status']))) ?>"><?= htmlspecialchars($o['status']) ?></span></td>
                <td style="font-size:13px;color:#6B7280;"><?= date('d M Y H:i', strtotime($o['tanggal_order'])) ?></td>
                <td><a href="/admin/order_detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">Detail</a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
