<?php
session_start();
$pageTitle  = 'Laporan';
$activePage = 'reports';
require_once __DIR__ . '/../includes/admin_header.php';

$bulan = $_GET['bulan'] ?? date('Y-m');

// Income data
$stmt = $conn->prepare("SELECT jenis, SUM(jumlah) as total FROM keuangan WHERE DATE_FORMAT(tanggal,'%Y-%m')=? GROUP BY jenis");
$stmt->bind_param('s', $bulan); $stmt->execute();
$res1 = $stmt->get_result(); $fin = []; while($r = $res1->fetch_assoc()) { $fin[] = $r; }
$t_in = $t_out = 0;
foreach ($fin as $f) { if ($f['jenis']==='Pemasukan') $t_in=$f['total']; else $t_out=$f['total']; }

// Order stats
$stmt2 = $conn->prepare("SELECT status, COUNT(*) as c, SUM(total_harga) as total FROM orders WHERE DATE_FORMAT(tanggal_order,'%Y-%m')=? GROUP BY status");
$stmt2->bind_param('s', $bulan); $stmt2->execute();
$res2 = $stmt2->get_result(); $order_stats = []; while($r = $res2->fetch_assoc()) { $order_stats[] = $r; }
$total_orders_month = array_sum(array_column($order_stats, 'c'));

// Members count by level
$res3 = $conn->query("SELECT level, COUNT(*) as c FROM members WHERE status='Aktif' AND tanggal_selesai>=CURDATE() GROUP BY level");
$member_stats = []; while($r = $res3->fetch_assoc()) { $member_stats[] = $r; }

// Monthly trend (last 6 months)
$trend_data = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $sl = $conn->prepare("SELECT COALESCE(SUM(CASE WHEN jenis='Pemasukan' THEN jumlah ELSE 0 END),0) as masuk, COALESCE(SUM(CASE WHEN jenis='Pengeluaran' THEN jumlah ELSE 0 END),0) as keluar FROM keuangan WHERE DATE_FORMAT(tanggal,'%Y-%m')=?");
    $sl->bind_param('s', $m); $sl->execute();
    $row = $sl->get_result()->fetch_assoc();
    $trend_data[] = ['bulan' => date('M Y', strtotime($m . '-01')), 'masuk' => (float)$row['masuk'], 'keluar' => (float)$row['keluar']];
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <h2 style="font-size:1.1rem;font-weight:700;">Laporan — <?= date('F Y', strtotime($bulan . '-01')) ?></h2>
    <div style="display:flex;gap:0.5rem;">
        <form method="GET" style="display:flex;gap:0.5rem;">
            <input type="month" name="bulan" value="<?= $bulan ?>" class="form-control" style="max-width:160px" onchange="this.form.submit()">
        </form>
        <a href="/admin/dms.php" class="btn btn-primary btn-sm">⚡ Generate PDF Laporan</a>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-row" style="margin-bottom:2rem;">
    <div class="stat-box"><div class="stat-icon green">💰</div><div><div class="stat-box-value" style="font-size:1.1rem"><?= formatRupiah($t_in) ?></div><div class="stat-box-label">Pemasukan Bulan Ini</div></div></div>
    <div class="stat-box"><div class="stat-icon amber">💸</div><div><div class="stat-box-value" style="font-size:1.1rem"><?= formatRupiah($t_out) ?></div><div class="stat-box-label">Pengeluaran Bulan Ini</div></div></div>
    <div class="stat-box"><div class="stat-icon purple">📈</div><div><div class="stat-box-value" style="font-size:1.1rem"><?= formatRupiah($t_in - $t_out) ?></div><div class="stat-box-label">Net Profit</div></div></div>
    <div class="stat-box"><div class="stat-icon blue">📦</div><div><div class="stat-box-value"><?= $total_orders_month ?></div><div class="stat-box-label">Total Order Bulan Ini</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:2rem;">
    <!-- Order table -->
    <div class="table-wrap">
        <div class="table-header"><span class="table-title">Order per Status</span></div>
        <table class="data-table">
            <thead><tr><th>Status</th><th>Jumlah Order</th><th>Total Nilai</th></tr></thead>
            <tbody>
            <?php foreach ($order_stats as $os): ?>
            <tr>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','',$os['status'])) ?>"><?= $os['status'] ?></span></td>
                <td><?= $os['c'] ?></td>
                <td><?= formatRupiah($os['total']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Member table -->
    <div class="table-wrap">
        <div class="table-header"><span class="table-title">Member Aktif per Level</span></div>
        <table class="data-table">
            <thead><tr><th>Level</th><th>Jumlah</th><th>Revenue/Bulan</th></tr></thead>
            <tbody>
            <?php 
            $lp = ['Bronze'=>160000,'Silver'=>300000,'Gold'=>590000];
            foreach ($member_stats as $ms): ?>
            <tr>
                <td><span class="badge badge-<?= strtolower($ms['level']) ?>"><?= $ms['level'] ?></span></td>
                <td><?= $ms['c'] ?></td>
                <td><?= formatRupiah($ms['c'] * ($lp[$ms['level']]??0)) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Trend Chart -->
<div class="card">
    <h3 style="font-size:15px;margin-bottom:1.5rem;">📊 Tren Keuangan 6 Bulan Terakhir</h3>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr><th>Bulan</th><th>Pemasukan</th><th>Pengeluaran</th><th>Net Profit</th><th>Grafik</th></tr></thead>
            <tbody>
            <?php 
            $max_val = max(array_map(fn($t) => max($t['masuk'], $t['keluar']), $trend_data)) ?: 1;
            foreach ($trend_data as $td): 
                $profit_month = $td['masuk'] - $td['keluar'];
            ?>
            <tr>
                <td style="font-weight:600"><?= $td['bulan'] ?></td>
                <td style="color:var(--success)"><?= formatRupiah($td['masuk']) ?></td>
                <td style="color:var(--danger)"><?= formatRupiah($td['keluar']) ?></td>
                <td style="color:<?= $profit_month>=0?'var(--primary)':'var(--danger)' ?>;font-weight:700"><?= formatRupiah(abs($profit_month)) ?></td>
                <td style="min-width:120px">
                    <div style="height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden">
                        <div style="height:100%;width:<?= round($td['masuk']/$max_val*100) ?>%;background:var(--success);border-radius:4px"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
