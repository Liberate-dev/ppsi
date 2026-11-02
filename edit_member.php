<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
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
    $doc_id = (int)$_GET['delete_doc'];
    $st = $conn->prepare("SELECT file_path FROM member_documents WHERE id = ? AND member_id = ?");
    $st->bind_param("ii", $doc_id, $id);
    $st->execute();
    $d = $st->get_result()->fetch_assoc();
    if ($d) {
        @unlink($d['file_path']);
        $del = $conn->prepare("DELETE FROM member_documents WHERE id = ?");
        $del->bind_param("i", $doc_id);
        $del->execute();
        $success_msg = "Dokumen berhasil dihapus.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $nama = htmlspecialchars(strip_tags(trim($_POST['nama'] ?? '')), ENT_QUOTES, 'UTF-8');
        $no_hp = preg_replace('/[^0-9]/', '', trim($_POST['no_hp'] ?? ''));
        
        if (empty($nama) || strlen($no_hp) < 10 || strlen($no_hp) > 15) {
            $error = "Nama wajib diisi dan No HP harus 10 - 15 digit angka.";
        } else {
            $update_stmt = $conn->prepare("UPDATE members SET nama = ?, no_hp = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $nama, $no_hp, $id);
            
            if ($update_stmt->execute()) {
                $success_msg = "Profil member berhasil diperbarui.";
                $member['nama'] = $nama;
                $member['no_hp'] = $no_hp;
            } else {
                $error = "Gagal memperbarui data member.";
            }
        }
    } elseif (isset($_POST['upload_dokumen'])) {
        $nama_dokumen = htmlspecialchars(strip_tags(trim($_POST['nama_dokumen'] ?? '')), ENT_QUOTES, 'UTF-8');
        $jenis_dokumen = htmlspecialchars(strip_tags(trim($_POST['jenis_dokumen'] ?? '')), ENT_QUOTES, 'UTF-8');
        $tanggal_upload = date('Y-m-d');
        
        if (isset($_FILES['file_dokumen']) && $_FILES['file_dokumen']['error'] == 0) {
            $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($_FILES['file_dokumen']['name']));
            $target_dir = "uploads/documents/";
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($_FILES['file_dokumen']['tmp_name'], $target_file)) {
                $ins = $conn->prepare("INSERT INTO member_documents (member_id, nama_dokumen, jenis_dokumen, file_path, tanggal_upload) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("issss", $id, $nama_dokumen, $jenis_dokumen, $target_file, $tanggal_upload);
                if ($ins->execute()) {
                    $success_msg = "Dokumen berhasil diunggah.";
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
        <h1>Edit Member Profil</h1>
        <a href="member.php" class="btn btn-outline">Back</a>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr; gap: 2rem;">
        <!-- Profil Card -->
        <div class="card">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem;">Informasi Member</h2>
            <form action="" method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($member['nama']) ?>" required>
                </div>
                <div class="form-group">
                    <label>No HP / WhatsApp (Hanya Angka)</label>
                    <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($member['no_hp']) ?>" inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                </div>
                <div class="form-group" style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">Perbarui Data</button>
                </div>
            </form>
        </div>

        <!-- Dokumen DMS Card -->
        <div class="card">
            <h2 style="font-size: 1.25rem; margin-bottom: 1.5rem;">Arsip Dokumen (DMS)</h2>
            <form action="" method="POST" enctype="multipart/form-data" style="margin-bottom: 2rem;">
                <input type="hidden" name="upload_dokumen" value="1">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Nama Dokumen</label>
                        <input type="text" name="nama_dokumen" class="form-control" placeholder="Cth: KTP Member" required>
                    </div>
                    <div class="form-group">
                        <label>Jenis Dokumen</label>
                        <select name="jenis_dokumen" class="form-control">
                            <option value="KTP">KTP / Identitas</option>
                            <option value="Formulir">Formulir Pendaftaran</option>
                            <option value="Bukti Pembayaran">Bukti Pembayaran</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>File Dokumen (PDF, JPG, PNG)</label>
                    <input type="file" name="file_dokumen" class="form-control" required>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-outline">Upload Dokumen</button>
                </div>
            </form>

            <h3 style="font-size: 1.1rem; margin-bottom: 1rem;">Daftar Dokumen Terunggah</h3>
            <table class="table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Nama Dokumen</th>
                        <th>Jenis</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($documents->num_rows > 0): ?>
                        <?php while($d = $documents->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['nama_dokumen']) ?></td>
                                <td><span class="badge" style="background:#f3f4f6; color:#374151;"><?= htmlspecialchars($d['jenis_dokumen']) ?></span></td>
                                <td><?= htmlspecialchars($d['tanggal_upload']) ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline">Buka</a>
                                    <a href="edit_member.php?id=<?= $id ?>&delete_doc=<?= $d['id'] ?>" onclick="return confirm('Hapus dokumen ini?')" class="btn btn-sm btn-danger">Hapus</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: var(--text-muted); padding: 1.5rem;">Belum ada dokumen yang diunggah.</td>
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
    <div>&copy; <?= date('Y') ?> FOOTSTEPSZS BOUTIQUE GALLERY. ALL RIGHTS RESERVED.</div>
</footer>

</body>
</html>
