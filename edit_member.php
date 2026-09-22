<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: member.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    header("Location: member.php");
    exit;
}

$success_msg = "";
$error = "";

if (isset($_GET['delete_doc'])) {
    $doc_id = $_GET['delete_doc'];
    // get file path
    $st = $conn->prepare("SELECT file_path FROM member_documents WHERE id = ? AND member_id = ?");
    $st->bind_param("ii", $doc_id, $id);
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $nama = $_POST['nama'];
        $no_hp = $_POST['no_hp'];
        
        $update_stmt = $conn->prepare("UPDATE members SET nama = ?, no_hp = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $nama, $no_hp, $id);
        
        if ($update_stmt->execute()) {
            $success_msg = "Profil berhasil diupdate!";
            // refresh member data
            $member['nama'] = $nama;
            $member['no_hp'] = $no_hp;
        } else {
            $error = "Gagal mengupdate member!";
        }
    } elseif (isset($_POST['upload_dokumen'])) {
        $nama_dokumen = $_POST['nama_dokumen'];
        $jenis_dokumen = $_POST['jenis_dokumen'];
        $tanggal_upload = date('Y-m-d');
        
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] == 0) {
            $file_name = time() . '_' . basename($_FILES['file_dokumen']['name']);
            $target_dir = "uploads/documents/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $target_file = $target_dir . str_replace(" ", "_", $file_name);
            
            if (move_uploaded_file($_FILES['file_dokumen']['tmp_name'], $target_file)) {
                $ins = $conn->prepare("INSERT INTO member_documents (member_id, nama_dokumen, jenis_dokumen, file_path, tanggal_upload) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("issss", $id, $nama_dokumen, $jenis_dokumen, $target_file, $tanggal_upload);
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
}

$doc_stmt = $conn->prepare("SELECT * FROM member_documents WHERE member_id = ? ORDER BY id DESC");
$doc_stmt->bind_param("i", $id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
            <h1>Edit Member</h1>
            <a href="member.php" class="btn btn-outline">Back</a>
        </div>

        <div class="card" style="max-width: 800px; margin-bottom: 2rem;">
            <?php if($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if($success_msg): ?>
                <div class="alert alert-success" style="background:var(--success-color); color:white; padding:1rem; border-radius:5px; margin-bottom:1rem;"><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>
            
            <form action="?id=<?= $id ?>" method="POST">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($member['nama']) ?>" required>
                </div>
                <div class="form-group">
                    <label>No HP / WhatsApp</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($member['no_hp']) ?>" required>
                </div>
                <!-- Status read-only -->
                <div class="form-group">
                    <label>Level Membership</label>
                    <input type="text" class="form-control" value="<?= $member['level'] ?>" disabled style="background:#e9ecef;">
                </div>
                <div class="form-group">
                    <label>Poin Terkumpul</label>
                    <input type="text" class="form-control" value="<?= $member['poin'] ?> pts" disabled style="background:#e9ecef;">
                </div>
                <div class="form-group" style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="cetak_member_card.php?id=<?= $id ?>" target="_blank" class="btn btn-outline" style="border-color: #d4af37; color: #d4af37;">Cetak E-Card</a>
                    <a href="cetak_riwayat_member.php?id=<?= $id ?>" target="_blank" class="btn btn-outline">Portfolio of Care</a>
                </div>
            </form>
        </div>

        <h2 style="font-size: 1.5rem; margin-bottom: 1rem;">Document Management System (DMS)</h2>
        <div style="display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap;">
            
            <!-- Upload Panel -->
            <div class="card" style="flex: 1; min-width: 300px;">
                <h3>Upload Dokumen Baru</h3>
                <form action="?id=<?= $id ?>" method="POST" enctype="multipart/form-data" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label>Nama Dokumen (Contoh: KTP)</label>
                        <input type="text" name="nama_dokumen" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Dokumen</label>
                        <select name="jenis_dokumen" class="form-control" required style="padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                            <option value="Identity">Identity (KTP/Passport)</option>
                            <option value="Agreement">Agreement / Consent Form</option>
                            <option value="Certificate">Certificate of Authenticity</option>
                            <option value="Other">Lainnya</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pilih File (PDF/Image)</label>
                        <input type="file" name="file_dokumen" class="form-control" required accept=".pdf,image/*" style="padding: 10px; width: 100%; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <button type="submit" name="upload_dokumen" class="btn btn-primary" style="margin-top: 1rem;">Unggah Dokumen</button>
                </form>
            </div>

            <!-- Documents List -->
            <div class="card" style="flex: 2; min-width: 400px; padding: 0;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: var(--surface-color); border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 15px; text-align: left;">Nama Dokumen</th>
                            <th style="padding: 15px; text-align: left;">Jenis</th>
                            <th style="padding: 15px; text-align: left;">Tanggal</th>
                            <th style="padding: 15px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($documents->num_rows > 0): ?>
                            <?php while($doc = $documents->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 15px; font-weight: 500;"><?= htmlspecialchars($doc['nama_dokumen']) ?></td>
                                <td style="padding: 15px;">
                                    <span style="background:var(--surface-color); padding: 5px 10px; border-radius:15px; font-size:12px; font-weight:600; text-transform:uppercase;">
                                        <?= htmlspecialchars($doc['jenis_dokumen']) ?>
                                    </span>
                                </td>
                                <td style="padding: 15px; color: #666; font-size: 14px;"><?= $doc['tanggal_upload'] ?></td>
                                <td style="padding: 15px; text-align: right;">
                                    <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-outline" style="padding: 5px 10px; font-size: 13px;">View / Unduh</a>
                                    <a href="?id=<?= $id ?>&delete_doc=<?= $doc['id'] ?>" class="btn btn-outline" style="border-color: #dc3545; color: #dc3545; padding: 5px 10px; font-size: 13px;" onclick="return confirm('Hapus dokumen ini?');">Hapus</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="padding: 20px; text-align: center; color: #666;">Belum ada dokumen yang diunggah untuk member ini.</td>
                            </tr>
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
