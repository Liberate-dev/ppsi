<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nama = $_POST['nama'];
    $no_hp = $_POST['no_hp'];
    $tanggal_daftar = date('Y-m-d'); // Tanggal hari ini
    
    $stmt = $conn->prepare("INSERT INTO members (nama, no_hp, tanggal_daftar) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nama, $no_hp, $tanggal_daftar);
    
    if ($stmt->execute()) {
        header("Location: member.php");
        exit;
    } else {
        $error = "Gagal menambah member!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Member - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
            <h1>Add New Member</h1>
            <a href="member.php" class="btn btn-outline">Back</a>
        </div>

        <div class="card" style="max-width: 600px;">
            <?php if(isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <form action="" method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>No HP / WhatsApp</label>
                    <input type="text" name="no_hp" class="form-control" required>
                </div>
                <div class="form-group" style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">Simpan Member</button>
                </div>
            </form>
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
