<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['admin_logged_in']);
?>
<nav class="top-navbar">
    <a href="/index.php" class="nav-brand">Foot<span class="step">Step</span>szs</a>
    
    <ul class="nav-menu">
        <li><a href="/index.php#services" class="<?= $currentPage == 'index.php' && !$is_logged_in ? 'active' : '' ?>">Layanan</a></li>
        
        <?php if ($is_logged_in): ?>
        <li><a href="/admin/dashboard.php">Dashboard</a></li>
        <li><a href="/admin/orders.php">Kelola Order</a></li>
        <li><a href="/admin/members.php">Members</a></li>
        <li><a href="/admin/dms.php">DMS</a></li>
        <li><a href="/admin/reports.php">Laporan</a></li>
        <?php else: ?>
        <li><a href="/index.php#pricing">Harga</a></li>
        <li><a href="/index.php#tracking">Lacak Order</a></li>
        <li><a href="/index.php#support">Kontak</a></li>
        <?php endif; ?>
    </ul>
    
    <div class="nav-actions">
        <?php if ($is_logged_in): ?>
            <a href="/logout.php" class="btn btn-outline btn-sm">Logout</a>
        <?php else: ?>
            <a href="/login.php" class="btn btn-primary btn-sm">Masuk / Daftar</a>
        <?php endif; ?>
    </div>
</nav>
