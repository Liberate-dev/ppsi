<?php
// includes/header_public.php — Landing page + Auth header (no auth required)
$current = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $is_logged_in && ($_SESSION['role'] === 'admin');
$is_customer = $is_logged_in && ($_SESSION['role'] === 'customer');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="shortcut icon" href="/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Footstepszs — Laundry sepatu premium di Malang. Deep cleaning, unyellowing, dan repair. Free pick up & delivery area Malang Kota.">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — Footstepszs' : 'Footstepszs — Premium Shoe Laundry Malang' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<!-- Top Info Bar -->
<div class="top-bar">
    <div class="top-bar-inner">
        <div class="top-bar-left">
            <div class="top-bar-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.81 19.79 19.79 0 012 2.18 2 2 0 013.18 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L7.91 7.91a16 16 0 006.29 6.29l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14.92v2z"/></svg>
                <a href="https://wa.me/6289681276080" target="_blank">089681276080 (WhatsApp)</a>
            </div>
            <div class="top-bar-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                Jl. Cluster Tigris Raya No 29, Malang
            </div>
        </div>
        <div class="top-bar-right">
            <div class="top-bar-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                Senin – Sabtu: 08.00 – 20.00 WIB
            </div>
            <div class="top-bar-item">
                <a href="https://www.instagram.com/" target="_blank">📸 @footstepszs</a>
            </div>
        </div>
    </div>
</div>

<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-inner">
        <a href="/index.php" class="nav-logo">Foot<span class="step">Step</span>szs</a>

        <ul class="nav-menu">
            <li><a href="/index.php#services" class="<?= $current === 'index.php' ? 'active' : '' ?>">Layanan</a></li>
            <li><a href="/index.php#pricelist">Harga</a></li>
            <li><a href="/index.php#member">Member</a></li>
            <li><a href="/index.php#cara-kerja">Cara Kerja</a></li>
            <li><a href="/index.php#kontak">Kontak</a></li>
            <li><a href="/docs/index.php" class="<?= strpos($_SERVER['PHP_SELF'], '/docs/') !== false ? 'active' : '' ?>" style="display:flex;align-items:center;gap:5px;">📖 Dokumentasi</a></li>
        </ul>

        <div class="nav-actions">
            <?php if ($is_admin): ?>
                <a href="/admin/dashboard.php" class="btn btn-primary btn-sm">Admin Panel</a>
            <?php elseif ($is_customer): ?>
                <a href="/customer/dashboard.php" class="btn btn-outline btn-sm">Dashboard</a>
                <a href="/customer/order.php" class="btn btn-primary btn-sm">+ Pesan Sekarang</a>
            <?php else: ?>
                <a href="/login.php" class="btn btn-outline btn-sm">Masuk / Member</a>
                <a href="/pesan.php" class="btn btn-primary btn-sm">Pesan Sekarang</a>
            <?php endif; ?>
            <button class="hamburger" aria-label="Menu">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</nav>
