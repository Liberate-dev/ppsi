<?php
// includes/admin_header.php
require_once __DIR__ . '/koneksi.php';
if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}
$adminName   = $_SESSION['nama'] ?? 'Admin';
$unreadCount = (int)getUnreadNotifCount($conn, 0);

// Fetch recent notifications for dropdown
$resNotif   = $conn->query("SELECT *, DATE_FORMAT(created_at,'%d %b %Y %H:%i') as fmt_time FROM notifications WHERE user_id = 0 ORDER BY id DESC LIMIT 10");
$notifRows = [];
if ($resNotif) {
    while($row = $resNotif->fetch_assoc()) { $notifRows[] = $row; }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="shortcut icon" href="/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — Footstepszs Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="admin-layout">
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<!-- SIDEBAR -->
<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-logo">
        <a href="/admin/dashboard.php" style="color:white;text-decoration:none;">
            <div class="sidebar-logo-text">Foot<span class="step">Step</span>szs</div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="sidebar-section-label">Utama</div>
        <a href="/admin/dashboard.php" class="sidebar-link <?= ($activePage??'')==='dashboard' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            Dashboard
        </a>
        <a href="/admin/orders.php" class="sidebar-link <?= ($activePage??'')==='orders' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
            Kelola Order
            <?php if($unreadCount > 0): ?>
            <span class="notif-badge" id="sidebarNotifBadge"><?= $unreadCount ?></span>
            <?php endif; ?>
        </a>

        <div class="sidebar-section-label">Pelanggan</div>
        <a href="/admin/customers.php" class="sidebar-link <?= ($activePage??'')==='customers' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Akun Customer
        </a>

        <div class="sidebar-section-label">Keuangan</div>
        <a href="/admin/keuangan.php" class="sidebar-link <?= ($activePage??'')==='keuangan' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            Pemasukan & Pengeluaran
        </a>
        <a href="/admin/reports.php" class="sidebar-link <?= ($activePage??'')==='reports' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Laporan
        </a>

        <div class="sidebar-section-label">DMS</div>
        <a href="/admin/dms.php" class="sidebar-link <?= ($activePage??'')==='dms' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
            Manajemen Dokumen
        </a>

        <div class="sidebar-section-label">Sistem</div>
        <a href="/docs/index.php" class="sidebar-link <?= strpos($_SERVER['PHP_SELF'], '/docs/') !== false ? 'active' : '' ?>" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg>
            Dokumentasi Teknis
        </a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars($adminName) ?></div>
                <div class="sidebar-user-role">Administrator</div>
            </div>
        </div>
        <a href="/logout.php" class="sidebar-link" style="margin-top:0.5rem;font-size:13px;color:rgba(255,255,255,0.4);">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Logout
        </a>
    </div>
</aside>

<!-- MAIN CONTENT WRAPPER -->
<div class="admin-content">
    <header class="admin-topbar">
        <div class="admin-topbar-left">
            <button class="btn btn-outline mobile-menu-btn" id="mobileMenuBtn" style="padding: 6px 10px;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <div>
                <div class="admin-page-title"><?= htmlspecialchars($pageTitle ?? 'Admin') ?></div>
                <div class="admin-breadcrumb">Footstepszs Admin &middot; <?= date('d M Y') ?></div>
            </div>
        </div>
        <div class="admin-topbar-right">
            <div style="position:relative;">
                <button class="topbar-notif-btn" id="notifBtn" title="Notifikasi">
                    🔔
                    <span class="topbar-notif-badge" id="adminNotifBadge" style="display:<?= $unreadCount > 0 ? 'flex' : 'none' ?>"><?= $unreadCount ?></span>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-header">
                        <strong style="font-size:14px;">Notifikasi</strong>
                        <a href="/admin/orders.php" style="font-size:12px;color:var(--primary);">Lihat Semua</a>
                    </div>
                    <div class="notif-list" id="notifList">
                        <?php if (empty($notifRows)): ?>
                            <div style="padding:2rem;text-align:center;color:#9CA3AF;font-size:14px;">Tidak ada notifikasi baru.</div>
                        <?php else: foreach ($notifRows as $n): ?>
                            <div class="notif-item <?= ($n['is_read'] ?? 1) == 0 ? 'unread' : '' ?>">
                                <div class="notif-item-icon">🔔</div>
                                <div>
                                    <div class="notif-item-text"><?= htmlspecialchars($n['pesan']) ?></div>
                                    <div class="notif-item-time"><?= $n['fmt_time'] ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
            <a href="/index.php" class="btn btn-outline btn-sm">Lihat Website ↗</a>
        </div>
    </header>
    <main class="admin-main">

<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('adminSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    
    if (btn && sidebar && overlay) {
        btn.addEventListener('click', function() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
        });
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
});
</script>
