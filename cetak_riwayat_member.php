<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID Member tidak ditemukan.");
}

$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    die("Member tidak ditemukan.");
}

$ord_stmt = $conn->prepare("SELECT * FROM orders WHERE member_id = ? ORDER BY tanggal_masuk DESC");
$ord_stmt->bind_param("i", $id);
$ord_stmt->execute();
$orders = $ord_stmt->get_result();

$member_code = "FS-" . str_pad($member['id'], 4, "0", STR_PAD_LEFT);

// Handle Save to DMS
if (isset($_POST['archive_report'])) {
    $nama_dokumen = "Portfolio of Care - " . date('M Y');
    $jenis_dokumen = "Report";
    $tanggal_upload = date('Y-m-d');
    
    // We'll store it as a 'Generated' type link in the future, 
    // but for now let's follow the dms_member structure.
    // We'll "mock" a file path that points to this print view for now, 
    // or better, we could save the rendered HTML if we wanted.
    // To keep it simple and consistent with dms_member.php:
    $file_path = "cetak_riwayat_member.php?id=" . $id; 
    
    $ins = $conn->prepare("INSERT INTO member_documents (member_id, nama_dokumen, jenis_dokumen, file_path, tanggal_upload) VALUES (?, ?, ?, ?, ?)");
    $ins->bind_param("issss", $id, $nama_dokumen, $jenis_dokumen, $file_path, $tanggal_upload);
    if ($ins->execute()) {
        $msg = "Report successfully archived to Gallery Records.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Portfolio of Care - <?= htmlspecialchars($member['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    <style>
        :root { --accent-color: #d4af37; }
        body { font-family: 'Inter', sans-serif; background: #f4f4f4; display: flex; justify-content: center; padding: 2rem 0; margin: 0; }
        .document { width: 210mm; min-height: 297mm; background: #fff; padding: 25mm; box-sizing: border-box; box-shadow: 0 0 40px rgba(0,0,0,0.1); position: relative; }
        .header { text-align: center; margin-bottom: 50px; border-bottom: 2px solid #111; padding-bottom: 20px; }
        .brand { font-family: 'Montserrat', sans-serif; font-size: 28px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; color: #111; margin-bottom: 5px; }
        .brand .step { color: var(--accent-color); font-style: italic; font-family: Georgia, serif; font-size: 1.25em; }
        .subtitle { font-size: 11px; text-transform: uppercase; letter-spacing: 3px; color: #666; font-weight: 500; }
        .doc-title { font-family: 'Playfair Display', serif; font-size: 20px; text-align: center; margin-bottom: 30px; letter-spacing: 1px; color: #111; }
        
        .member-info { display: flex; justify-content: space-between; margin-bottom: 30px; padding: 15px; background: #fafafa; border: 1px solid #eee; }
        .member-info div { font-size: 12px; line-height: 1.6; color: #333; }
        .member-info strong { width: 120px; display: inline-block; color: #111; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #111; color: #fff; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; font-weight: 600; }
        
        .footer { text-align: center; font-size: 10px; color: #999; margin-top: 50px; border-top: 1px solid #eee; padding-top: 20px; text-transform: uppercase; letter-spacing: 1px; }
        
        @media print {
            body { background: #fff; padding: 0; display: block; }
            .document { box-shadow: none; margin: 0; width: 100%; height: auto; padding: 10mm; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .header { border-bottom: 2px solid #111 !important; }
            th { background: #111 !important; color: #fff !important; }
            .no-print { display: none !important; }
        }
        .no-print { text-align: center; margin-bottom: 20px; }
        .btn-archive { background: #d4af37; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
        .btn-archive:hover { background: #b8962d; }
    </style>
</head>
<body>

<?php if(isset($msg)): ?>
    <div style="position:fixed; top:20px; right:20px; background: #4CAF50; color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); z-index: 9999;" class="no-print">
        <?= $msg ?>
    </div>
<?php endif; ?>

<div class="no-print" style="margin-top: 20px;">
    <form method="POST" style="display:inline;">
        <button type="submit" name="archive_report" class="btn-archive">📥 Archive to Gallery Records</button>
    </form>
    <button onclick="window.print()" class="btn-archive" style="background:#111; margin-left:10px;">🖨 Print Portfolio</button>
</div>

<div class="document">
    <div class="header">
        <div class="brand">Foot<span class="step" style="color:var(--accent-color); font-family:Georgia, serif; font-size:1.2em; font-style:italic;">Step</span>szs</div>
        <div class="subtitle">Boutique Gallery & Restoration</div>
    </div>

    <div class="doc-title">PORTFOLIO OF CARE</div>

    <div class="member-info">
        <div>
            <strong>MEMBER ID</strong> : <?= $member_code ?><br>
            <strong>NAMA KLIEN</strong> : <span style="text-transform: uppercase; font-weight: 600;"><?= htmlspecialchars($member['nama']) ?></span><br>
            <strong>KONTAK</strong> : <?= htmlspecialchars($member['no_hp']) ?>
        </div>
        <div style="text-align: right;">
            <strong>MEMBERSHIP</strong> : <span style="color: #d4af37; font-weight: 600; text-transform: uppercase;"><?= htmlspecialchars($member['level']) ?></span><br>
            <strong>TANGGAL CETAK</strong> : <?= date('d M Y') ?><br>
            <strong>TOTAL VALUE</strong> : <?= formatRupiah($member['total_transaksi']) ?>
        </div>
    </div>

    <p style="font-size: 13px; color: #444; line-height: 1.6; margin-bottom: 20px;">
        Dokumen ini merepresentasikan rekam jejak eksklusif (restorasi dan perawatan mendalam) atas seluruh koleksi yang telah dipercayakan kepada tim artisan Footstepszs Boutique Gallery.
    </p>

    <table>
        <thead>
            <tr>
                <th>TANGGAL MASUK</th>
                <th>JENIS BARANG</th>
                <th>LAYANAN</th>
                <th>STATUS</th>
                <th style="text-align: right;">INVESTMENT</th>
            </tr>
        </thead>
        <tbody>
            <?php if($orders->num_rows > 0): ?>
                <?php while($ord = $orders->fetch_assoc()): ?>
                <tr>
                    <td><?= date('d M Y', strtotime($ord['tanggal_masuk'])) ?></td>
                    <td><strong><?= htmlspecialchars($ord['jenis_barang']) ?></strong></td>
                    <td><?= htmlspecialchars($ord['layanan']) ?></td>
                    <td><?= htmlspecialchars($ord['status']) ?></td>
                    <td style="text-align: right;"><?= formatRupiah($ord['total_bayar']) ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 20px; color: #666;">Belum ada rekam jejak perawatan.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        FOOTSTEPSZS BOUTIQUE GALLERY &bull; CONFIDENTIAL &bull; DO NOT DISTRIBUTE UNLESS AUTHORIZED
    </div>
</div>

<script>
    // window.onload = function() { window.print(); }
</script>
</body>
</html>
