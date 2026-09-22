<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Laporan Top Members berdasarkan Total Transaksi
$top_members = $conn->query("SELECT * FROM members ORDER BY total_transaksi DESC LIMIT 10");

// Laporan Agregat Transaksi per Level
$stats = $conn->query("SELECT level, COUNT(id) as total_users, SUM(total_transaksi) as sum_transaksi FROM members GROUP BY level ORDER BY FIELD(level, 'Platinum', 'Gold', 'Silver')");

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Member - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
            <h1>Membership Report</h1>
            <button onclick="window.print()" class="btn btn-outline btn-print">🖨️ Print Report</button>
        </div>

        <div class="stat-grid">
            <?php while($s = $stats->fetch_assoc()): ?>
            <div class="stat-card" style="background:var(--card-bg);">
                <div class="stat-title">Level <?= $s['level'] ?></div>
                <div class="stat-value" style="font-size:20px;"><?= $s['total_users'] ?> Members</div>
                <div style="font-size:13px; color:var(--text-secondary); margin-top:5px;">Rev: <?= formatRupiah($s['sum_transaksi']) ?></div>
            </div>
            <?php endwhile; ?>
        </div>

        <div class="card">
            <h2 style="font-size:18px; margin-bottom: 1.5rem; color:var(--primary-color);">Top 10 Customers</h2>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Nama Customer</th>
                            <th>Level Membership</th>
                            <th>Poin Terkumpul</th>
                            <th>Total Transaksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        if($top_members->num_rows > 0): 
                            while($row = $top_members->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><strong>#<?= $rank++ ?></strong></td>
                            <td>
                                <div style="font-weight:600;"><?= htmlspecialchars($row['nama']) ?></div>
                                <div style="font-size:12px; color:var(--text-secondary);"><?= htmlspecialchars($row['no_hp']) ?></div>
                            </td>
                            <td>
                                <?php 
                                    $bdg = 'badge-silver';
                                    if($row['level'] == 'Gold') $bdg = 'badge-gold';
                                    if($row['level'] == 'Platinum') $bdg = 'badge-platinum';
                                ?>
                                <span class="badge <?= $bdg ?>"><?= $row['level'] ?></span>
                            </td>
                            <td><strong><?= number_format($row['poin']) ?> pts</strong></td>
                            <td><strong style="color:var(--primary-color);"><?= formatRupiah($row['total_transaksi']) ?></strong></td>
                        </tr>
                        <?php 
                            endwhile;
                        else: 
                        ?>
                            <tr><td colspan="5" style="text-align: center;">Belum ada data transaksi member.</td></tr>
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
