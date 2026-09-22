<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$search = $_GET['q'] ?? '';
$whereClause = "";
$params = [];
$types = "";

if ($search) {
    $whereClause = "WHERE m.nama LIKE ? OR o.id = ?";
    $searchTerm = "%$search%";
    $searchID = intval(str_replace('#FS-', '', $search));
    $params = [$searchTerm, $searchID];
    $types = "si";
}

$query = "SELECT o.*, m.nama as member_name, m.level as member_level FROM orders o LEFT JOIN members m ON o.member_id = m.id $whereClause ORDER BY o.id DESC";
$stmt = $conn->prepare($query);

if ($search) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders = $stmt->get_result();

$total_revenue = $conn->query("SELECT SUM(total_bayar) as s FROM orders")->fetch_assoc()['s'];
$total_orders = $conn->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];

// Handle Status Update
if (isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];
    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $order_id);
    $stmt->execute();
    header("Location: order.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Registry - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .date-picker { background: #F3F4F6; padding: 0.8rem 1.2rem; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--text-secondary); display: flex; align-items: center; gap: 8px; }

        .stat-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 1.5rem; margin-bottom: 3rem; }
        .stat-card-blue { background: var(--primary-color); color: white; padding: 2rem; border-radius: 16px; position: relative; overflow: hidden; }
        .stat-card-white { background: white; padding: 2rem; border-radius: 16px; border: 1px solid var(--border-color); }
        .stat-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem; }
        .stat-card-blue .stat-label { color: rgba(255,255,255,0.7); }
        .stat-card-white .stat-label { color: var(--text-secondary); }
        .stat-count { font-size: 32px; font-weight: 800; letter-spacing: -1px; }

        .orders-table-wrapper { background: white; border-radius: 20px; padding: 2rem; border: 1px solid var(--border-color); margin-bottom: 3rem;}
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .table-header h2 { font-size: 18px; font-weight: 700; color: var(--text-primary); }
        .search-area { display: flex; gap: 10px; background: #F9FAFB; padding: 0.5rem 1rem; border-radius: 8px; align-items: center; }
        .search-area input { border: none; background: transparent; outline: none; font-size: 14px; width: 250px; }

        .product-cell { display: flex; align-items: center; gap: 1rem; }
        .product-icon { width: 40px; height: 40px; border-radius: 8px; background: #F3F4F6; display:flex; align-items:center; justify-content:center; overflow:hidden;}
        .product-icon img { width:100%; height:100%; object-fit:cover; }

        .bottom-section { display: grid; grid-template-columns: 1fr 1.5fr; gap: 2rem; margin-bottom: 4rem;}
        .review-queue { background: #F9FAFB; padding: 3rem; border-radius: 20px; display: flex; flex-direction: column; justify-content: center; }
        .quality-card { background: var(--primary-color); border-radius: 20px; position: relative; overflow: hidden; min-height: 250px;}
        .quality-card img { position:absolute; width:100%; height:100%; object-fit:cover; opacity: 0.6; }
        .qc-content { position: absolute; top:50%; left:50%; transform: translate(-50%, -50%); background: white; padding: 1.5rem 2rem; border-radius: 12px; text-align: center; width: 60%; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="page-header">
        <div>
            <div class="portal-label" style="font-size: 11px; font-weight: 700; color: var(--accent-color); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.5rem;">MANAGEMENT CONSOLE</div>
            <h1 class="page-title">Order Registry</h1>
        </div>
        <div class="date-picker">
            📅 Oct 1 - Oct 31, 2024
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card-blue">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-count"><?= formatRupiah($total_revenue ?? 0) ?></div>
            <div style="font-size:13px; color:rgba(255,255,255,0.8); margin-top:10px;">📈 +12.5% from last month</div>
        </div>
        <div class="stat-card-white">
            <div class="stat-label">Active Services</div>
            <div class="stat-count"><?= number_format($total_orders) ?></div>
            <div style="display:flex; align-items:center; gap:10px; margin-top:10px; font-size:13px; color:var(--text-secondary);">
                👥 +12 New customers today
            </div>
        </div>
        <div class="stat-card-white">
            <div class="stat-label">Avg. Fulfillment</div>
            <div class="stat-count">4.2 Days</div>
            <div style="margin-top:15px; width:100%; height:6px; background:#F3F4F6; border-radius:3px; overflow:hidden;">
                <div style="width:85%; height:100%; background:var(--accent-color);"></div>
            </div>
            <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:var(--text-light); margin-top:8px; letter-spacing:1px;">Efficiency Target: 90%</div>
        </div>
    </div>

    <div class="orders-table-wrapper">
        <div class="table-header">
            <h2>Recent Orders</h2>
            <form class="search-area" method="GET">
                <span style="color:var(--text-light);">🔍</span>
                <input type="text" name="q" placeholder="Search orders..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" style="background:#E5E7EB; border:none; padding:5px 10px; border-radius:6px; cursor:pointer;">&equiv;</button>
            </form>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>ORDER DETAILS</th>
                    <th>SERVICE TYPE</th>
                    <th>STATUS</th>
                    <th>REVENUE</th>
                    <th style="text-align:right;">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if($orders->num_rows > 0): ?>
                    <?php while($row = $orders->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <div class="product-icon">👟</div>
                                <div>
                                    <div style="font-weight:700; color:var(--primary-color);">#FS-<?= str_pad($row['id'], 4, '0', STR_PAD_LEFT) ?></div>
                                    <div style="font-size:13px; color:var(--text-secondary); margin-top:2px;"><?= htmlspecialchars($row['member_name'] ?? 'Guest') ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="badge-service"><?= htmlspecialchars($row['layanan']) ?></span>
                        </td>
                        <td>
                            <?php 
                                $sClass = 'status-received';
                                if($row['status'] == 'Proses') $sClass = 'status-in-progress';
                                if($row['status'] == 'Selesai') $sClass = 'status-completed';
                            ?>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="order_id" value="<?= $row['id'] ?>">
                                <select name="new_status" onchange="this.form.submit()" style="border:none; background:transparent; font-size:10px; font-weight:700; text-transform:uppercase; cursor:pointer;" name="update_status">
                                    <?php 
                                    $statuses = ['Menunggu', 'Diterima', 'Proses', 'Selesai', 'Dibatalkan'];
                                    foreach($statuses as $st): 
                                    ?>
                                        <option value="<?= $st ?>" <?= ($row['status'] == $st) ? 'selected' : '' ?>><?= $st ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="update_status" value="1">
                            </form>
                            <div class="status-dot <?= $sClass ?>" style="margin-top:5px;">
                                <?= strtoupper($row['status'] == 'Proses' ? 'IN PROGRESS' : $row['status']) ?>
                            </div>
                        </td>
                        <td>
                            <div style="font-weight:600;"><?= formatRupiah($row['total_bayar']) ?></div>
                        </td>
                        <td style="text-align:right;">
                            <a href="invoice.php?id=<?= $row['id'] ?>" class="btn btn-outline" style="padding: 5px 15px; font-size:12px;">Invoice</a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-secondary);">No active orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="bottom-section">
        <div class="review-queue">
            <h2 style="font-size:20px; font-weight:700; color:var(--primary-color); margin-bottom:1rem;">Artisan Review Queue</h2>
            <p style="font-size:14px; color:var(--text-secondary); line-height:1.6; margin-bottom:2rem;">Our specialists are currently processing high-priority restoration requests. Average review time for new orders is under 14 minutes.</p>
            <div>
                <a href="tambah_order.php" class="btn" style="background:white; color:var(--primary-color); border:1px solid var(--border-color); padding: 0.8rem 1.5rem; border-radius: 8px;">View Queue</a>
            </div>
        </div>
        <div class="quality-card">
            <img src="https://images.unsplash.com/photo-1549298916-b41d501d3772?q=80&w=800&auto=format&fit=crop" alt="Quality Work">
            <div class="qc-content">
                <div style="font-size:24px; margin-bottom:10px;">🛡️</div>
                <h3 style="font-size:16px; font-weight:800; color:var(--primary-color); margin-bottom:5px;">Quality Guaranteed</h3>
                <div style="font-size:10px; font-weight:700; text-transform:uppercase; color:var(--text-secondary); letter-spacing:1px;">FOOTSTEPSZS GOLD STANDARD</div>
            </div>
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
