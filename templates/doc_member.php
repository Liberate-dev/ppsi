<?php
/**
 * templates/doc_member.php
 * Laporan Daftar Member — identitas perusahaan, footer pencetak, timestamp
 */
$admin_name = $adminName ?? $_SESSION['nama'] ?? 'Administrator';
$print_time = date('d F Y, H:i:s');
$total_members = count($rows);
$aktif = count(array_filter($rows, fn($r) => $r['status'] === 'Aktif'));
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Laporan Member — Footstepszs</title>
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
.doc-period { font-size: 12px; color: #6B7280; margin-top: 4px; }

.summary-row { display: flex; gap: 1rem; margin-bottom: 2rem; }
.sum-box { flex: 1; border-radius: 8px; padding: 1rem; text-align: center; }
.sum-label { font-size: 10px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px; }
.sum-value { font-size: 22px; font-weight: 900; margin-top: 4px; }

table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
th { background: #7C3AED; color: white; padding: 9px 12px; text-align: left; font-size: 11px; }
td { padding: 8px 12px; border-bottom: 1px solid #E5E7EB; vertical-align: middle; }
tr:nth-child(even) td { background: #F9FAFB; }

.badge { padding: 2px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; display: inline-block; }
.bronze { background: #FEF3C7; color: #92400E; }
.silver { background: #F3F4F6; color: #374151; }
.gold   { background: #FEF9C3; color: #854D0E; }
.aktif  { background: #D1FAE5; color: #065F46; }
.nonaktif { background: #FEE2E2; color: #991B1B; }
.menunggu { background: #FEF3C7; color: #92400E; }

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
    <div class="doc-title">Laporan Daftar Member</div>
    <div class="doc-period">Digenerate: <?= $print_time ?> | Total: <?= $total_members ?> member</div>
</div>

<div class="summary-row">
    <div class="sum-box" style="background:#EDE9FE;border:1px solid #C4B5FD">
        <div class="sum-label">Total Member</div>
        <div class="sum-value" style="color:#5B21B6"><?= $total_members ?></div>
    </div>
    <div class="sum-box" style="background:#D1FAE5;border:1px solid #6EE7B7">
        <div class="sum-label">Member Aktif</div>
        <div class="sum-value" style="color:#065F46"><?= $aktif ?></div>
    </div>
    <div class="sum-box" style="background:#FEF3C7;border:1px solid #FCD34D">
        <div class="sum-label">Bronze</div>
        <div class="sum-value" style="color:#92400E"><?= count(array_filter($rows, fn($r) => $r['level'] === 'Bronze')) ?></div>
    </div>
    <div class="sum-box" style="background:#F3F4F6;border:1px solid #D1D5DB">
        <div class="sum-label">Silver</div>
        <div class="sum-value" style="color:#374151"><?= count(array_filter($rows, fn($r) => $r['level'] === 'Silver')) ?></div>
    </div>
    <div class="sum-box" style="background:#FEF9C3;border:1px solid #FDE68A">
        <div class="sum-label">Gold</div>
        <div class="sum-value" style="color:#854D0E"><?= count(array_filter($rows, fn($r) => $r['level'] === 'Gold')) ?></div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Nama Customer</th>
            <th>Kontak</th>
            <th>Level</th>
            <th>Mulai</th>
            <th>Selesai</th>
            <th>Status</th>
            <th>Poin</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $i => $m): ?>
    <tr>
        <td><?= $i + 1 ?></td>
        <td>
            <strong><?= htmlspecialchars($m['nama_lengkap']) ?></strong><br>
            <small style="color:#9CA3AF"><?= htmlspecialchars($m['email']) ?></small>
        </td>
        <td style="color:#555"><?= htmlspecialchars($m['no_hp'] ?? '-') ?></td>
        <td><span class="badge <?= strtolower($m['level']) ?>"><?= $m['level'] ?></span></td>
        <td style="white-space:nowrap"><?= date('d M Y', strtotime($m['tanggal_mulai'])) ?></td>
        <td style="white-space:nowrap"><?= date('d M Y', strtotime($m['tanggal_selesai'])) ?></td>
        <td>
            <span class="badge <?= strtolower(str_replace(' ', '', $m['status'])) ?>">
                <?= $m['status'] ?>
            </span>
        </td>
        <td><?= number_format($m['poin']) ?> pts</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
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
