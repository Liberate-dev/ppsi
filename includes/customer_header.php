<?php
// includes/customer_header.php
require_once __DIR__ . '/koneksi.php';
if (!isCustomer()) {
    header('Location: /login.php');
    exit;
}
$custName = $_SESSION['nama'] ?? 'Customer';
$custId = (int) $_SESSION['user_id'];
$unreadC = getUnreadNotifCount($conn, $custId);
$memberInfo = getMemberInfo($conn, $custId);
$current = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="shortcut icon" href="/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> — Footstepszs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .cust-layout {
            display: flex;
            min-height: 100vh;
        }

        .cust-sidebar {
            width: 240px;
            background: var(--navy);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
        }

        .cust-content {
            margin-left: 240px;
            flex: 1;
            min-height: 100vh;
            background: var(--off-white);
            display: flex;
            flex-direction: column;
            width: calc(100% - 240px);
        }

        @media(max-width:992px) {
            .cust-sidebar {
                transform: translateX(-100%);
            }

            .cust-sidebar.open {
                transform: translateX(0);
                box-shadow: 10px 0 30px rgba(0, 0, 0, 0.5);
            }

            .cust-content {
                margin-left: 0 !important;
                width: 100%;
                max-width: 100vw;
            }

            .mobile-menu-btn {
                display: block !important;
            }
        }
    </style>
</head>

<body>
    <div class="cust-layout">
        <aside class="cust-sidebar">
            <div class="sidebar-logo">
                <div class="sidebar-logo-text" style="font-size:16px">Foot<span class="step">Step</span>szs</div>
            </div>

            <!-- Member Card -->
            <div style="padding:1rem;border-bottom:1px solid rgba(255,255,255,0.08);">
                <div
                    style="background:<?= $memberInfo ? 'linear-gradient(135deg,#' . ($memberInfo['level'] === 'Gold' ? '92400E,#D97706' : ($memberInfo['level'] === 'Silver' ? '374151,#6B7280' : '5B21B6,#7C3AED')) . ')' : 'rgba(255,255,255,0.06)' ?>;border-radius:12px;padding:1rem;text-align:center;">
                    <?php if ($memberInfo): ?>
                        <div style="font-size:1.5rem;font-weight:900;color:white;"><?= $memberInfo['level'] ?></div>
                        <div style="font-size:11px;color:rgba(255,255,255,0.7);">Member Aktif</div>
                        <div style="font-size:11px;color:rgba(255,255,255,0.6);margin-top:4px">s/d
                            <?= date('d M Y', strtotime($memberInfo['tanggal_selesai'])) ?></div>
                    <?php else: ?>
                        <div style="font-size:12px;color:rgba(255,255,255,0.5);">Belum Member</div>
                        <a href="/customer/membership.php"
                            style="display:block;margin-top:6px;font-size:11px;font-weight:700;color:var(--primary);">Daftar
                            Member →</a>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="sidebar-section-label">Menu</div>
                <a href="/customer/dashboard.php"
                    class="sidebar-link <?= $current == 'dashboard.php' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" />
                        <rect x="14" y="3" width="7" height="7" />
                        <rect x="14" y="14" width="7" height="7" />
                        <rect x="3" y="14" width="7" height="7" />
                    </svg>
                    Dashboard
                </a>
                <a href="/customer/order.php" class="sidebar-link <?= $current == 'order.php' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                        <line x1="3" y1="6" x2="21" y2="6" />
                        <path d="M16 10a4 4 0 01-8 0" />
                    </svg>
                    Pesan Sekarang
                </a>
                <a href="/customer/my_orders.php"
                    class="sidebar-link <?= $current == 'my_orders.php' ? 'active' : '' ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 11l3 3L22 4" />
                        <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" />
                    </svg>
                    Riwayat Order
                </a>
            </nav>

            <div class="sidebar-footer">
                <div class="sidebar-user">
                    <div class="sidebar-avatar"><?= strtoupper(substr($custName, 0, 1)) ?></div>
                    <div>
                        <div class="sidebar-user-name"><?= htmlspecialchars($custName) ?></div>
                        <div class="sidebar-user-role">Customer</div>
                    </div>
                </div>
                <a href="/logout.php" class="sidebar-link"
                    style="margin-top:0.5rem;font-size:13px;color:rgba(255,255,255,0.4);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px">
                        <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                    </svg>
                    Logout
                </a>
            </div>
        </aside>

        <div class="cust-content">
            <div class="sidebar-overlay" id="custSidebarOverlay"></div>
            <header class="admin-topbar">
                <div style="display:flex;align-items:center;gap:1rem;">
                    <button class="btn btn-outline mobile-menu-btn" id="custMobileMenuBtn" style="padding: 6px 10px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                            style="width:20px;height:20px;">
                            <line x1="3" y1="12" x2="21" y2="12" />
                            <line x1="3" y1="6" x2="21" y2="6" />
                            <line x1="3" y1="18" x2="21" y2="18" />
                        </svg>
                    </button>
                    <div>
                        <div class="admin-page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></div>
                        <div class="admin-breadcrumb">Footstepszs · <?= htmlspecialchars($custName) ?></div>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:0.75rem;">
                    <a href="/customer/order.php" class="btn btn-primary btn-sm">+ Pesan Sekarang</a>
                    <a href="/index.php" class="btn btn-outline btn-sm">🏠 Beranda</a>
                </div>
            </header>
            <main class="admin-main">