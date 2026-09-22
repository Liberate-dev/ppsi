<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['admin_logged_in']);
?>
<nav class="top-navbar">
    <a href="index.php" class="nav-brand">Foot<span class="step">Step</span>szs</a>
    
    <ul class="nav-menu">
        <li><a href="index.php#services" class="<?= $currentPage == 'index.php' && !$is_logged_in ? 'active' : '' ?>">Services</a></li>
        
        <?php if ($is_logged_in): ?>
        <li><a href="dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a></li>
        <li><a href="order.php" class="<?= in_array($currentPage, ['order.php', 'tambah_order.php']) ? 'active' : '' ?>">Order Registry</a></li>
        <li><a href="member.php" class="<?= in_array($currentPage, ['member.php', 'tambah_member.php', 'edit_member.php']) ? 'active' : '' ?>">Members</a></li>
        <li><a href="dms_member.php" class="<?= $currentPage == 'dms_member.php' ? 'active' : '' ?>">Member DMS</a></li>
        <li><a href="laporan_member.php" class="<?= $currentPage == 'laporan_member.php' ? 'active' : '' ?>">Reports</a></li>
        <?php else: ?>
        <li><a href="index.php#pricing">Pricing</a></li>
        <li><a href="index.php#tracking">Order Tracking</a></li>
        <li><a href="index.php#support">Support</a></li>
        <?php endif; ?>
    </ul>
    
    <div class="nav-actions">
        <span class="nav-icon">💼</span>
        <?php if ($is_logged_in): ?>
            <a href="logout.php" class="btn btn-primary">Logout</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-primary">Get Started</a>
        <?php endif; ?>
    </div>
</nav>
