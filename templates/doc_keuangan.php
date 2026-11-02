<?php
/**
 * templates/doc_keuangan.php
 * Laporan Keuangan - dengan identitas perusahaan, footer pencetak, timestamp
 */
$admin_name = $adminName ?? $_SESSION['nama'] ?? 'Administrator';
$page_total = 1; // single page
$page_now   = 1;
$print_time = date('d F Y, H:i:s');
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Keuangan - <?= isset($tgl_start) && $tgl_start ? date('d M Y', strtotime($tgl_start)).' - '.date('d M Y', strtotime($tgl_end)) : date('F Y', strtotime($bulan.'-01')) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Segoe UI', Arial, sans-serif; padding: 2.5rem; color: #1a1a1a; font-size: 13px; }

/* ---- HEADER IDENTITAS PERUSAHAAN ---- */
.company-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #7C3AED;
    padding-bottom: 1.25rem;
    margin-bottom: 1.5rem;
}
.company-logo-block .logo {
    font-size: 26px;
    font-weight: 900;
    color: #0A142A;
    letter-spacing: -1px;
    font-family: Arial Black, sans-serif;
}
.company-logo-block .logo span { color: #7C3AED; font-style: italic; }
.company-logo-block .tagline { font-size: 11px; color: #7C3AED; margin-top: 2px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
.company-info-block { text-align: right; font-size: 11.5px; color: #555; line-height: 1.7; }
.company-info-block strong { color: #0A142A; font-size: 13px; display: block; margin-bottom: 4px; }

/* ---- DOC TITLE ---- */
.doc-title-block { text-align: center; margin-bottom: 2rem; }
.doc-title { font-size: 18px; font-weight: 800; color: #7C3AED; text-transform: uppercase; letter-spacing: 1px; }
.doc-period { font-size: 13px; color: #555; margin-top: 4px; }

/* ---- SUMMARY GRID ---- */
.summary-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 2rem; }
.summary-box { padding: 1.25rem; border-radius: 8px; text-align: center; }
.summary-box.income { background: #D1FAE5; border: 1px solid #6EE7B7; }
.summary-box.expense { background: #FEE2E2; border: 1px solid #FCA5A5; }
.summary-box.profit { background: #EDE9FE; border: 1px solid #C4B5FD; }
.summary-label { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #6B7280; letter-spacing: 0.5px; }
.summary-value { font-size: 20px; font-weight: 900; color: #1a1a1a; margin-top: 5px; }

/* ---- TABLE ---- */
table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 12.5px; }
th { background: #7C3AED; color: white; padding: 9px 12px; text-align: left; font-size: 11px; letter-spacing: 0.5px; }
td { padding: 8px 12px; border-bottom: 1px solid #E5E7EB; vertical-align: top; }
tr:nth-child(even) td { background: #F9FAFB; }
.income-row td:first-child::before { content: '▲ '; color: #10B981; }
.expense-row td:first-child::before { content: '▼ '; color: #EF4444; }

/* ---- FOOTER ---- */
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
.doc-footer .footer-left { line-height: 1.7; }
.doc-footer .footer-right { text-align: right; line-height: 1.7; }
.doc-footer .page-info { font-weight: 700; color: #374151; }

@media print {
    body { margin: 0; padding: 1.5cm; }
    .no-print { display: none; }
}
</style>
</head>
<body>

<!-- HEADER IDENTITAS PERUSAHAAN -->
<div class="company-header">
    <div class="company-logo-block">
        <div class="logo">Foot<span>Step</span>szs</div>
        <div class="tagline">Premium Shoe Laundry - Malang</div>
    </div>
    <div class="company-info-block">
        <strong>Footstepszs Boutique Gallery</strong>
        Jl. Cluster Tigris Raya No. 29, Malang<br>
        WA / Telp: 089681276080<br>
        Instagram: @footstepszs<br>
        Jam Operasional: Senin–Sabtu 08.00–20.00 WIB
    </div>
</div>

<!-- JUDUL DOKUMEN -->
<div class="doc-title-block">
    <div class="doc-title">Laporan Keuangan</div>
    <div class="doc-period">
        Periode: <?= isset($tgl_start) && $tgl_start
            ? date('d F Y', strtotime($tgl_start)) . ' - ' . date('d F Y', strtotime($tgl_end))
            : date('F Y', strtotime($bulan.'-01')) ?>
    </div>
</div>

<!-- SUMMARY -->
<div class="summary-grid">
    <div class="summary-box income">
        <div class="summary-label">Total Pemasukan</div>
        <div class="summary-value">Rp <?= number_format($t_in, 0, ',', '.') ?></div>
    </div>
    <div class="summary-box expense">
        <div class="summary-label">Total Pengeluaran</div>
        <div class="summary-value">Rp <?= number_format($t_out, 0, ',', '.') ?></div>
    </div>
    <div class="summary-box profit">
        <div class="summary-label">Net Profit / Rugi</div>
        <div class="summary-value" style="color:<?= ($t_in-$t_out)>=0?'#7C3AED':'#EF4444' ?>">
            <?= ($t_in-$t_out) >= 0 ? '' : '- ' ?>Rp <?= number_format(abs($t_in-$t_out), 0, ',', '.') ?>
        </div>
    </div>
</div>

<!-- TABEL DATA -->
<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Jenis</th>
            <th>Kategori</th>
            <th>Jumlah</th>
            <th>Keterangan</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $r): ?>
    <tr class="<?= $r['jenis']==='Pemasukan' ? 'income-row' : 'expense-row' ?>">
        <td><?= $i+1 ?></td>
        <td style="white-space:nowrap"><?= date('d M Y', strtotime($r['tanggal'])) ?></td>
        <td><?= $r['jenis'] ?></td>
        <td><?= htmlspecialchars($r['kategori']) ?></td>
        <td style="font-weight:700;color:<?= $r['jenis']==='Pemasukan'?'#10B981':'#EF4444' ?>;white-space:nowrap">
            <?= $r['jenis']==='Pemasukan'?'+':'-' ?>Rp <?= number_format($r['jumlah'], 0, ',', '.') ?>
        </td>
        <td style="color:#6B7280"><?= htmlspecialchars($r['keterangan'] ?: '-') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4" style="text-align:right;font-weight:700;font-size:13px;padding:10px 12px;">
                TOTAL (<?= count($rows) ?> transaksi)
            </td>
            <td style="font-weight:800;font-size:14px;color:#7C3AED;white-space:nowrap">
                Rp <?= number_format($t_in - $t_out, 0, ',', '.') ?>
            </td>
            <td></td>
        </tr>
    </tfoot>
</table>

<!-- FOOTER DOKUMEN -->
<div class="doc-footer">
    <div class="footer-left">
        Dicetak oleh: <strong><?= htmlspecialchars($admin_name) ?></strong><br>
        Waktu cetak: <?= $print_time ?><br>
        Footstepszs © <?= date('Y') ?> - Semua data bersifat rahasia
    </div>
    <div class="footer-right">
        <div class="page-info">Halaman <?= $page_now ?> dari <?= $page_total ?></div>
        Dokumen ini digenerate otomatis<br>
        oleh Sistem Footstepszs
    </div>
</div>

</body>
</html>
