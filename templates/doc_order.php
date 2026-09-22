<?php
/**
 * templates/doc_order.php
 * Laporan Order — identitas perusahaan, footer pencetak, nomor halaman, timestamp
 */
$admin_name = $adminName ?? $_SESSION['nama'] ?? 'Administrator';
$print_time = date('d F Y, H:i:s');
$total_all  = array_sum(array_column($rows, 'total_harga'));
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Order — <?= isset($tgl_start) && $tgl_start ? date('d M Y', strtotime($tgl_start)).' – '.date('d M Y', strtotime($tgl_end)) : date('F Y', strtotime($bulan.'-01')) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; padding: 2.5rem; color: #1a1a1a; font-size: 13px; }

.company-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #7C3AED;
    padding-bottom: 1.25rem;
    margin-bottom: 1.5rem;
}
.company-logo-block .logo { font-size: 26px; font-weight: 900; color: #0A142A; letter-spacing: -1px; }
.company-logo-block .logo span { color: #7C3AED; font-style: italic; }
.company-logo-block .tagline { font-size: 11px; color: #7C3AED; margin-top: 3px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
.company-info-block { text-align: right; font-size: 11.5px; color: #555; line-height: 1.7; }
.company-info-block strong { color: #0A142A; font-size: 13px; display: block; margin-bottom: 4px; }

.doc-title-block { text-align: center; margin-bottom: 2rem; }
.doc-title { font-size: 18px; font-weight: 800; color: #7C3AED; text-transform: uppercase; letter-spacing: 1px; }
.doc-period { font-size: 13px; color: #555; margin-top: 4px; }

/* Summary boxes */
.summary-row { display: flex; gap: 1rem; margin-bottom: 2rem; }
.sum-box { flex: 1; background: #EDE9FE; border: 1px solid #C4B5FD; border-radius: 8px; padding: 1rem; text-align: center; }
.sum-label { font-size: 10px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; }
.sum-value { font-size: 20px; font-weight: 900; color: #5B21B6; margin-top: 4px; }

table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
th { background: #7C3AED; color: white; padding: 9px 12px; text-align: left; font-size: 11px; letter-spacing: 0.4px; }
td { padding: 8px 12px; border-bottom: 1px solid #E5E7EB; vertical-align: middle; }
tr:nth-child(even) td { background: #F9FAFB; }
tfoot td { background: #EDE9FE !important; font-weight: 800; font-size: 13px; color: #5B21B6; }

.status-badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; }
.status-menunggu { background: #FEF3C7; color: #92400E; }
.status-diterima { background: #DBEAFE; color: #1E40AF; }
.status-proses { background: #EDE9FE; color: #5B21B6; }
.status-selesai { background: #D1FAE5; color: #065F46; }
.status-dibatalkan { background: #FEE2E2; color: #991B1B; }
.status-diambil { background: #D1FAE5; color: #065F46; }

.doc-footer {
    margin-top: 3rem;
    padding-top: 1rem;
    border-top: 2px solid #E5E7EB;
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    font-size: 11px;
    color: #6B7280;
}
.doc-footer .page-info { font-weight: 700; color: #374151; }

@media print { body { padding: 1.5cm; } }
</style>
</head>
<body>

<div class="company-header">
    <div class="company-logo-block">
        <div class="logo">Foot<span>Step</span>szs</div>
        <div class="tagline">Premium Shoe Laundry — Malang</div>
    </div>
    <div class="company-info-block">
        <strong>Footstepszs Boutique Gallery</strong>
        Jl. Cluster Tigris Raya No. 29, Malang<br>
        WA / Telp: 089681276080<br>
        Instagram: @footstepszs
    </div>
</div>

<div class="doc-title-block">
    <div class="doc-title">Laporan Order Laundry</div>
    <div class="doc-period">
        Periode: <?= isset($tgl_start) && $tgl_start
            ? date('d F Y', strtotime($tgl_start)) . ' — ' . date('d F Y', strtotime($tgl_end))
            : date('F Y', strtotime($bulan.'-01')) ?>
    </div>
</div>

<div class="summary-row">
    <div class="sum-box" style="background:#EDE9FE;border-color:#C4B5FD">
        <div class="sum-label">Total Order</div>
        <div class="sum-value" style="color:#5B21B6"><?= count($rows) ?></div>
    </div>
    <div class="sum-box" style="background:#D1FAE5;border-color:#6EE7B7">
        <div class="sum-label">Total Pendapatan</div>
        <div class="sum-value" style="color:#065F46;font-size:16px">Rp <?= number_format($total_all, 0, ',', '.') ?></div>
    </div>
    <div class="sum-box" style="background:#FEF3C7;border-color:#FCD34D">
        <div class="sum-label">Rata-rata per Order</div>
        <div class="sum-value" style="color:#92400E;font-size:15px">
            Rp <?= count($rows) > 0 ? number_format($total_all / count($rows), 0, ',', '.') : '0' ?>
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Kode Order</th>
            <th>Customer</th>
            <th>Waktu</th>
            <th>Total</th>
            <th>Status</th>
            <th>Tanggal Order</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $o): ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong></td>
        <td><?= htmlspecialchars($o['nama_lengkap']) ?></td>
        <td><?= $o['jenis_waktu'] ?></td>
        <td style="font-weight:700;white-space:nowrap">Rp <?= number_format($o['total_harga'], 0, ',', '.') ?></td>
        <td>
            <span class="status-badge status-<?= strtolower(str_replace(' ', '', $o['status'])) ?>">
                <?= $o['status'] ?>
            </span>
        </td>
        <td style="white-space:nowrap"><?= date('d M Y', strtotime($o['tanggal_order'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" style="text-align:right">TOTAL (<?= count($rows) ?> order)</td>
            <td colspan="3">Rp <?= number_format($total_all, 0, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>

<div class="doc-footer">
    <div>
        Dicetak oleh: <strong><?= htmlspecialchars($admin_name) ?></strong><br>
        Waktu cetak: <?= $print_time ?><br>
        Footstepszs © <?= date('Y') ?> — Data bersifat rahasia
    </div>
    <div style="text-align:right">
        <div class="page-info">Halaman 1 dari 1</div>
        Dokumen digenerate otomatis oleh Sistem Footstepszs
    </div>
</div>

</body>
</html>
