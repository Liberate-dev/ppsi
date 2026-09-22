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

// Generate member id like FS-000X
$member_code = "FS-" . str_pad($member['id'], 4, "0", STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Member Card - <?= htmlspecialchars($member['nama']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Playfair+Display:ital,wght@0,700;1,700&display=swap" rel="stylesheet">
    <style>
        :root { --accent-color: #d4af37; }
        body {
            background: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .card-container {
            width: 85.6mm;
            height: 53.98mm; /* standard id card size */
            background: #111;
            color: #d4af37;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            padding: 20px;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .card-container::before {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; left: 0;
            background: linear-gradient(135deg, rgba(212,175,55,0.1) 0%, rgba(17,17,17,0) 100%);
            z-index: 1;
        }
        .header {
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(212,175,55,0.3);
            padding-bottom: 5px;
        }
        .brand {
            font-family: 'Playfair Display', serif;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        .level {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #d4af37;
            color: #111;
            padding: 3px 8px;
            border-radius: 10px;
            font-weight: 600;
        }
        .content {
            position: relative;
            z-index: 2;
        }
        .name {
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0 0 5px 0;
            color: #fff;
        }
        .member-id {
            font-size: 12px;
            font-family: monospace;
            letter-spacing: 2px;
            color: rgba(255,255,255,0.7);
        }
        .footer {
            position: relative;
            z-index: 2;
            font-size: 8px;
            text-align: right;
            color: rgba(212,175,55,0.6);
            letter-spacing: 1px;
        }
        .qr-placeholder {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: #fff;
            z-index: 2;
            padding: 2px;
        }
        @media print {
            body { background: #fff; height: auto; display: block; margin: 0; }
            .card-container { margin: 20px; box-shadow: none; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="card-container">
    <div class="header">
        <div class="brand">Foot<span class="step" style="color:var(--accent-color); font-family:Georgia, serif; font-size:1.2em; font-style:italic;">Step</span>szs</div>
        <div class="level"><?= htmlspecialchars($member['level']) ?></div>
    </div>
    <div class="content">
        <div class="name"><?= htmlspecialchars($member['nama']) ?></div>
        <div class="member-id"><?= $member_code ?></div>
    </div>
    <!-- QR Code Placeholder -->
    <div class="qr-placeholder">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=40x40&data=<?= urlencode($member_code) ?>" alt="QR" style="width:100%;height:100%;">
    </div>
    <div class="footer">
        THE BOUTIQUE GALLERY
    </div>
</div>

<script>
    // window.onload = function() { window.print(); }
</script>
</body>
</html>
