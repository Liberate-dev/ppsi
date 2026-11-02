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
    <div>
        <h2 style="font-size:1.3rem;font-weight:800;color:var(--navy);margin-bottom:0.25rem;">
            Laporan Kinerja - <?= date('F Y', strtotime($bulan . '-01')) ?>
        </h2>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:0;">Ringkasan omset pendapatan, volume pesanan, dan tren operasional.</p>
    </div>
    <div style="display:flex;gap:0.5rem;align-items:center;">
        <form method="GET" style="display:flex;gap:0.5rem;">
            <input type="month" name="bulan" value="<?= $bulan ?>" class="form-control" style="max-width:160px" onchange="this.form.submit()">
        </form>
        <a href="/admin/dms.php" class="btn btn-primary btn-sm">Cetak Laporan (DMS)</a>
    </div>
</div>

<!-- Summary Cards -->
<div class="stats-row" style="margin-bottom:2rem;">
    <div class="stat-box">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="font-size:1.15rem;color:var(--success);"><?= formatRupiah($t_in) ?></div>
            <div class="stat-box-label">Pemasukan Bulan Ini</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="font-size:1.15rem;color:var(--danger);"><?= formatRupiah($t_out) ?></div>
            <div class="stat-box-label">Pengeluaran Bulan Ini</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="font-size:1.15rem;color:<?= ($t_in-$t_out)>=0 ? 'var(--primary)' : 'var(--danger)' ?>;"><?= formatRupiah($t_in - $t_out) ?></div>
            <div class="stat-box-label">Laba Bersih</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        </div>
        <div>
            <div class="stat-box-value"><?= number_format($total_orders_month) ?></div>
            <div class="stat-box-label">Total Order Bulan Ini</div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem;margin-bottom:2rem;">
    <!-- Order table -->
    <div class="table-wrap">
        <div class="table-header"><span class="table-title">Order Berdasarkan Status</span></div>
        <table class="data-table">
            <thead><tr><th>Status</th><th>Jumlah Order</th><th>Total Nilai</th></tr></thead>
            <tbody>
            <?php if (empty($order_stats)): ?>
                <tr><td colspan="3" style="text-align:center;padding:2rem;color:#9CA3AF">Tidak ada order pada periode ini.</td></tr>
            <?php else: foreach ($order_stats as $os): ?>
            <tr>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','',$os['status'])) ?>"><?= htmlspecialchars($os['status']) ?></span></td>
                <td><?= $os['c'] ?></td>
                <td><?= formatRupiah($os['total']) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Member table -->
    <div class="table-wrap">
        <div class="table-header"><span class="table-title">Member Aktif per Level</span></div>
        <table class="data-table">
            <thead><tr><th>Level</th><th>Jumlah</th><th>Revenue Bulanan</th></tr></thead>
            <tbody>
            <?php 
            $lp = ['Bronze'=>160000,'Silver'=>300000,'Gold'=>590000];
            if (empty($member_stats)): ?>
                <tr><td colspan="3" style="text-align:center;padding:2rem;color:#9CA3AF">Belum ada member aktif.</td></tr>
            <?php else: foreach ($member_stats as $ms): ?>
            <tr>
                <td><span class="badge badge-silver"><?= htmlspecialchars($ms['level']) ?></span></td>
                <td><?= $ms['c'] ?></td>
                <td><?= formatRupiah($ms['c'] * ($lp[$ms['level']]??0)) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Trend Chart -->
<div class="card">
    <h3 style="font-size:15px;margin-bottom:1.5rem;color:var(--navy);font-weight:700;">Tren Keuangan 6 Bulan Terakhir</h3>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead><tr><th>Bulan</th><th>Pemasukan</th><th>Pengeluaran</th><th>Laba Bersih</th><th>Perbandingan</th></tr></thead>
            <tbody>
            <?php 
            $max_val = max(array_map(fn($t) => max($t['masuk'], $t['keluar']), $trend_data)) ?: 1;
            foreach ($trend_data as $td):
                $w_in  = round(($td['masuk'] / $max_val) * 100);
                $w_out = round(($td['keluar'] / $max_val) * 100);
                $net   = $td['masuk'] - $td['keluar'];
            ?>
            <tr>
                <td><strong><?= $td['bulan'] ?></strong></td>
                <td style="color:var(--success);font-weight:600"><?= formatRupiah($td['masuk']) ?></td>
                <td style="color:var(--danger);font-weight:600"><?= formatRupiah($td['keluar']) ?></td>
                <td style="font-weight:700;color:<?= $net>=0 ? 'var(--primary)' : 'var(--danger)' ?>"><?= formatRupiah($net) ?></td>
                <td style="min-width:180px">
                    <div style="display:flex;flex-direction:column;gap:3px">
                        <div style="background:var(--success);height:7px;border-radius:4px;width:<?= $w_in ?>%" title="Pemasukan: <?= formatRupiah($td['masuk']) ?>"></div>
                        <div style="background:var(--danger);height:7px;border-radius:4px;width:<?= $w_out ?>%" title="Pengeluaran: <?= formatRupiah($td['keluar']) ?>"></div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
