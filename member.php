<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$search = $_GET['q'] ?? '';
$whereClause = "";
$params = [];
$types = "";

if ($search) {
    $whereClause = "WHERE nama LIKE ? OR no_hp LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
    $types = "ss";
}

$query = "SELECT * FROM members $whereClause ORDER BY id DESC";
$stmt = $conn->prepare($query);

if ($search) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$members = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-bar input { flex: 1; max-width: 300px; }
    </style>
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
            <h1>Membership Registry</h1>
            <a href="tambah_member.php" class="btn btn-primary">+ Add Member</a>
        </div>

        <div class="card">
            <form class="search-bar" method="GET">
                <input type="text" name="q" class="form-control" placeholder="Search name or phone..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-accent">Search</button>
                <?php if($search): ?>
                    <a href="member.php" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nama</th>
                            <th>No HP</th>
                            <th>Tgl Daftar</th>
                            <th>Level</th>
                            <th>Poin</th>
                            <th>Transaksi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($members->num_rows > 0): ?>
                            <?php while($row = $members->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#M-<?= str_pad($row['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                                <td><?= date('d M Y', strtotime($row['tanggal_daftar'])) ?></td>
                                <td>
                                    <?php 
                                        $bdg = 'badge-silver';
                                        if($row['level'] == 'Gold') $bdg = 'badge-gold';
                                        if($row['level'] == 'Platinum') $bdg = 'badge-platinum';
                                    ?>
                                    <span class="badge <?= $bdg ?>"><?= $row['level'] ?></span>
                                </td>
                                <td><strong><?= number_format($row['poin']) ?> pts</strong></td>
                                <td><?= formatRupiah($row['total_transaksi']) ?></td>
                                <td>
                                    <div class="action-btns" style="display:flex; gap:5px; flex-wrap:wrap;">
                                        <a href="edit_member.php?id=<?= $row['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                        <a href="cetak_member_card.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline btn-sm">ID Card</a>
                                        <a href="cetak_riwayat_member.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-outline btn-sm">Portfolio</a>
                                        <a href="dms_member.php?member_id=<?= $row['id'] ?>" class="btn btn-outline btn-sm">Docs</a>
                                        <a href="hapus_member.php?id=<?= $row['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);" onclick="return confirm('Hapus member ini?')">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align: center;">Tidak ada data member.</td></tr>
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
