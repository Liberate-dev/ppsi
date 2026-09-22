<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Get stats
$total_members = $conn->query("SELECT COUNT(*) as c FROM members")->fetch_assoc()['c'];
$total_orders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$total_revenue = $conn->query("SELECT SUM(total_bayar) as s FROM orders")->fetch_assoc()['s'];

// Get recent orders
$recent_orders = $conn->query("SELECT o.*, m.nama as member_name FROM orders o LEFT JOIN members m ON o.member_id = m.id ORDER BY o.id DESC LIMIT 5");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="header">
            <h1>Management Console</h1>
            <div class="user-info">
                Hi, <strong><?= htmlspecialchars($_SESSION['admin_name']) ?></strong>
            </div>
        </div>

        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-title">Total Revenue</div>
                <div class="stat-value"><?= formatRupiah($total_revenue ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Active Members</div>
                <div class="stat-value"><?= number_format($total_members) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-title">Total Orders processed</div>
                <div class="stat-value"><?= number_format($total_orders) ?></div>
            </div>
        </div>

        <div class="card">
            <div class="header" style="margin-bottom: 1rem;">
                <h2 style="font-size: 18px; color: var(--primary-color);">Recent Orders</h2>
                <a href="order.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Member</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($recent_orders->num_rows > 0): ?>
                            <?php while($row = $recent_orders->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#FS-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= htmlspecialchars($row['member_name'] ?? 'Guest') ?></td>
                                <td><?= htmlspecialchars($row['layanan']) ?> <br> <small style="color:var(--text-secondary)"><?= htmlspecialchars($row['jenis_barang']) ?></small></td>
                                <td><?= date('M d, Y', strtotime($row['tanggal_masuk'])) ?></td>
                                <td><span class="badge badge-status"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td><strong><?= formatRupiah($row['total_bayar']) ?></strong></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="text-align: center; color: var(--text-secondary);">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<footer class="site-footer">
    <div class="brand" style="font-size: 16px;">Foot<span class="step" style="color:var(--accent-color); font-family:Georgia, serif; font-size:1.2em; font-style:italic; margin:-4px 1px 0 1px;">Step</span>szs</div>
    <div class="links">
        <a href="#">PRIVACY POLICY</a>
        <a href="#">TERMS OF SERVICE</a>
        <a href="#">SUSTAINABILITY</a>
        <a href="#">CONTACT US</a>
    </div>
    <div>&copy; 2024 FOOTSTEPSZS BOUTIQUE GALLERY. ALL RIGHTS RESERVED.</div>
</footer>

</body>
</html>
