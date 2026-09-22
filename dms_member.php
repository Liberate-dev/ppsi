<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$success_msg = "";
$error = "";

// Handle Delete
if (isset($_GET['delete_doc'])) {
    $doc_id = (int)$_GET['delete_doc'];
    $st = $conn->prepare("SELECT file_path FROM member_documents WHERE id = ?");
    $st->bind_param("i", $doc_id);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    if ($d) {
        @unlink($d['file_path']);
        $del = $conn->prepare("DELETE FROM member_documents WHERE id = ?");
        $del->bind_param("i", $doc_id);
        $del->execute();
        $success_msg = "Dokumen berhasil dihapus!";
    }
}

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_dokumen'])) {
    $member_id = (int)$_POST['member_id'];
    $nama_dokumen = $_POST['nama_dokumen'];
    $jenis_dokumen = $_POST['jenis_dokumen'];
    $tanggal_upload = date('Y-m-d');
    
    if ($member_id == 0) {
        $error = "Pilih member terlebih dahulu.";
    } elseif (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] == 0) {
        $file_name = time() . '_' . basename($_FILES['file_dokumen']['name']);
        $target_dir = "uploads/documents/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $target_file = $target_dir . str_replace(" ", "_", $file_name);
        
        if (move_uploaded_file($_FILES['file_dokumen']['tmp_name'], $target_file)) {
            $ins = $conn->prepare("INSERT INTO member_documents (member_id, nama_dokumen, jenis_dokumen, file_path, tanggal_upload) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("issss", $member_id, $nama_dokumen, $jenis_dokumen, $target_file, $tanggal_upload);
            if ($ins->execute()) {
                $success_msg = "Dokumen berhasil diunggah!";
            } else {
                $error = "Gagal menyimpan data dokumen ke database.";
                @unlink($target_file);
            }
        } else {
            $error = "Gagal mengunggah file.";
        }
    } else {
        $error = "Pilih file yang valid untuk diunggah.";
    }
}

// Search functionality
$search = $_GET['q'] ?? '';
$whereClause = "";
$params = [];
$types = "";

if ($search) {
    $whereClause = "WHERE m.nama LIKE ? OR md.nama_dokumen LIKE ?";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm];
    $types = "ss";
} elseif (isset($_GET['member_id'])) {
    $mid_filter = (int)$_GET['member_id'];
    $whereClause = "WHERE md.member_id = ?";
    $params = [$mid_filter];
    $types = "i";
}

$query = "SELECT md.*, m.nama as member_name 
          FROM member_documents md 
          JOIN members m ON md.member_id = m.id 
          $whereClause 
          ORDER BY md.id DESC";

$stmt = $conn->prepare($query);
if ($search) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$documents = $stmt->get_result();

// Get all members for dropdown
$members_query = $conn->query("SELECT id, nama FROM members ORDER BY nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member DMS - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .search-bar { display: flex; gap: 10px; margin-bottom: 20px; }
        .search-bar input { flex: 1; max-width: 300px; }
        .grid-dms { display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: start; }
        @media (max-width: 768px) {
            .grid-dms { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <h1>Document Management System</h1>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if($success_msg): ?>
        <div class="alert alert-success" style="background:var(--success-color); color:white; padding:1rem; border-radius:5px; margin-bottom:1rem;"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <div class="grid-dms">
        
        <!-- Upload Form -->
        <div class="card">
            <h3 style="margin-bottom: 1rem;">Document Input</h3>
            <form action="dms_member.php" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Pilih Member</label>
                    <select name="member_id" class="form-control" required style="padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                        <?php 
                        $selected_mid = (int)($_GET['member_id'] ?? 0);
                        ?>
                        <option value="">-- Pilih Member --</option>
                        <?php while($m = $members_query->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>" <?= ($m['id'] == $selected_mid) ? 'selected' : '' ?>><?= htmlspecialchars($m['nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Judul / Nama Dokumen</label>
                    <input type="text" name="nama_dokumen" class="form-control" placeholder="Contoh: KTP, KK, Form Persetujuan" required>
                </div>
                <div class="form-group">
                    <label>Jenis Dokumen</label>
                    <select name="jenis_dokumen" class="form-control" required style="padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="Identity">Identity (KTP/Passport)</option>
                        <option value="Agreement">Agreement / Consent Form</option>
                        <option value="Certificate">Certificate of Authenticity</option>
                        <option value="Report">Report / Laporan</option>
                        <option value="Invoice">Invoice / Nota</option>
                        <option value="Other">Lainnya</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Pilih File</label>
                    <input type="file" name="file_dokumen" class="form-control" required accept=".pdf,image/*,.doc,.docx" style="padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                    <small style="color: #666; margin-top: 5px; display: block;">Format didukung: PDF, Image, Word.</small>
                </div>
                <button type="submit" name="upload_dokumen" class="btn btn-primary" style="margin-top: 1rem; width: 100%;">Upload Dokumen</button>
            </form>
        </div>

        <!-- Document List / Output -->
        <div class="card">
            <h3 style="margin-bottom: 1rem;">Document Output / Database</h3>
            
            <form class="search-bar" method="GET" action="dms_member.php">
                <input type="text" name="q" class="form-control" placeholder="Cari nama member atau dokumen..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="btn btn-accent">Search</button>
                <?php if($search): ?>
                    <a href="dms_member.php" class="btn btn-outline">Clear</a>
                <?php endif; ?>
            </form>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Nama Dokumen</th>
                            <th>Jenis</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($documents->num_rows > 0): ?>
                            <?php while($doc = $documents->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($doc['member_name']) ?></strong></td>
                                <td><?= htmlspecialchars($doc['nama_dokumen']) ?></td>
                                <td>
                                    <span style="background:#f3f4f6; color:#4b5563; padding: 4px 8px; border-radius: 4px; font-size:11px; font-weight:600; text-transform:uppercase;">
                                        <?= htmlspecialchars($doc['jenis_dokumen']) ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y', strtotime($doc['tanggal_upload'])) ?></td>
                                <td>
                                    <div class="action-btns">
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm">View / Download</a>
                                        <a href="?delete_doc=<?= $doc['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);" onclick="return confirm('Hapus dokumen ini?')">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="text-align: center; color: #666; padding: 20px;">Tidak ada dokumen yang ditemukan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</main>

<footer class="site-footer" style="margin-top: auto;">
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
