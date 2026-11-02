<?php
ob_start();
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

// --- SINKRONISASI DATA DARI GET ---
$preOrderID  = (int)($_GET['order_id'] ?? 0);
$preMemberID = (int)($_GET['member_id'] ?? 0);
$preType     = 'general';
if ($preOrderID)  $preType = 'order';
if ($preMemberID) $preType = 'member';

// Check if POST is empty but content length exists (means file too large)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $error = "Ukuran file terlalu besar. Batas maksimal server terlampaui.";
}

// Auto-generate handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    $gen_type  = $_POST['gen_type'];
    $bulan     = $_POST['gen_bulan'] ?? date('Y-m');
    $tgl_start = $_POST['tgl_start'] ?? '';
    $tgl_end   = $_POST['tgl_end']   ?? '';
    $ref_id    = (int)($_POST['ref_id'] ?? 0);
    $nama_dok  = trim($_POST['nama_dokumen']);
    $uid       = (int)$_SESSION['user_id'];
    $adminName = $_SESSION['nama'] ?? 'Administrator'; // Untuk footer dokumen

    $token     = md5(uniqid($gen_type, true));
    $file_path = "generated/{$gen_type}_{$token}.html";
    $full_path = __DIR__ . '/../uploads/documents/' . $file_path;
    
    if (!is_dir(dirname($full_path))) mkdir(dirname($full_path), 0777, true);

    $content = '';
    if ($gen_type === 'keuangan_report') {
        $where = "DATE_FORMAT(tanggal,'%Y-%m')=?";
        $p = [$bulan]; $ts = 's';
        if ($tgl_start && $tgl_end) { $where = "tanggal BETWEEN ? AND ?"; $p = [$tgl_start, $tgl_end]; $ts = 'ss'; }
        
        $stmt = $conn->prepare("SELECT * FROM keuangan WHERE $where ORDER BY tanggal ASC");
        if ($stmt) {
            $stmt->bind_param($ts, ...$p); $stmt->execute();
            $res = $stmt->get_result();
            $rows = []; while($r = $res->fetch_assoc()) { $rows[] = $r; }
            
            $t_in = 0; $t_out = 0;
            foreach ($rows as $row) {
                if ($row['jenis'] === 'Pemasukan') $t_in += $row['jumlah'];
                if ($row['jenis'] === 'Pengeluaran') $t_out += $row['jumlah'];
            }
            ob_start();
            include __DIR__ . '/../templates/doc_keuangan.php';
            $content = ob_get_clean();
        }
    } elseif ($gen_type === 'member_report') {
        $res2 = $conn->query("SELECT m.*, u.nama_lengkap, u.email, u.no_hp FROM members m JOIN users u ON m.user_id=u.id ORDER BY m.level");
        $rows = []; while($r = $res2->fetch_assoc()) { $rows[] = $r; }
        ob_start();
        include __DIR__ . '/../templates/doc_member.php';
        $content = ob_get_clean();
    } elseif ($gen_type === 'order_report') {
        $where = "DATE_FORMAT(o.tanggal_order,'%Y-%m')=?";
        $p = [$bulan]; $ts = 's';
        if ($tgl_start && $tgl_end) { $where = "o.tanggal_order BETWEEN ? AND ?"; $p = [$tgl_start, $tgl_end]; $ts = 'ss'; }
        
        $stmt3 = $conn->prepare("SELECT o.*, u.nama_lengkap FROM orders o JOIN users u ON o.user_id=u.id WHERE $where ORDER BY o.tanggal_order ASC");
        if ($stmt3) {
            $stmt3->bind_param($ts, ...$p); $stmt3->execute();
            $res3 = $stmt3->get_result();
            $rows = []; while($r = $res3->fetch_assoc()) { $rows[] = $r; }
            ob_start();
            include __DIR__ . '/../templates/doc_order.php';
            $content = ob_get_clean();
        }
    }

    if ($content) {
        file_put_contents($full_path, $content);
        $fp_stored = 'uploads/documents/' . $file_path;
        $ket = "Dicetak oleh: $adminName | Generated: $gen_type - " . ($tgl_start ?: $bulan);
        $ref_type_val = ($gen_type === 'keuangan_report') ? 'keuangan' : ($gen_type === 'member_report' ? 'member' : 'order');
        
        $ins = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES (?, ?, ?, 'Generated', ?, ?, CURDATE(), ?)");
        $ins->bind_param("sisssi", $ref_type_val, $ref_id, $nama_dok, $fp_stored, $ket, $uid);
        if ($ins->execute()) {
            header("Location: /admin/dms.php?success=" . urlencode("Dokumen berhasil digenerate oleh $adminName!"));
            exit;
        } else { $error = "Gagal menyimpan ke DB: " . $conn->error; }
    } else if (!$error) { 
        $error = "Konten laporan kosong atau gagal dimuat."; 
    }
}

// Upload handling (Supports multiple files & Template)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload'])) {
    $ref_type = $_POST['ref_type'];
    $ref_id   = (int)($_POST['ref_id'] ?? 0);
    if ($ref_type === 'member') $ref_id = (int)($_POST['ref_id_member'] ?? 0);
    
    $nama_dok_base = trim($_POST['nama_dokumen']);
    $keterangan    = trim($_POST['keterangan'] ?? '');
    $uid           = (int)$_SESSION['user_id'];
    $input_method  = $_POST['input_method'] ?? 'upload';

    if ($input_method === 'template') {
        $template_content = trim($_POST['template_content'] ?? '');
        if ($template_content === '') {
            $error = "Isi dokumen template tidak boleh kosong.";
        } else {
            $token = md5(uniqid('tpl', true));
            $file_path = "generated/doc_tpl_{$token}.html";
            $full_path = __DIR__ . '/../uploads/documents/' . $file_path;
            if (!is_dir(dirname($full_path))) mkdir(dirname($full_path), 0777, true);
            
            $html = "<!DOCTYPE html>
<html>
<head>
<meta charset='utf-8'>
<title>" . htmlspecialchars($nama_dok_base) . "</title>
<style>
body { font-family: 'Inter', Arial, sans-serif; line-height: 1.6; padding: 40px; max-width: 800px; margin: 0 auto; color: #333; }
.header { text-align: center; border-bottom: 2px solid #6366f1; padding-bottom: 15px; margin-bottom: 30px; }
.header h2 { margin: 0 0 5px 0; color: #1e1e1e; }
.footer { margin-top: 50px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; text-align: center; color: #6b7280; }
</style>
</head>
<body>
    <div class='header'>
        <h2>" . htmlspecialchars($nama_dok_base) . "</h2>
        <div>Footstepszs Boutique Gallery</div>
    </div>
    <div class='content'>
        " . nl2br(htmlspecialchars($template_content)) . "
    </div>
    <div class='footer'>
        Dokumen ini dihasilkan secara otomatis oleh Sistem Footstepszs pada " . date('d M Y H:i') . "
    </div>
</body>
</html>";
            
            if (file_put_contents($full_path, $html)) {
                $fp = 'uploads/documents/' . $file_path;
                $ins = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES (?, ?, ?, 'Template', ?, ?, CURDATE(), ?)");
                $ins->bind_param("sisssi", $ref_type, $ref_id, $nama_dok_base, $fp, $keterangan, $uid);
                if ($ins->execute()) {
                    header("Location: /admin/dms.php?success=" . urlencode("Dokumen template berhasil dibuat!"));
                    exit;
                } else { $error = "Gagal simpan DB: " . $conn->error; }
            } else { $error = "Gagal membuat file template."; }
        }
    } elseif ($input_method === 'link') {
        $link_url = trim($_POST['link_url'] ?? '');
        if (empty($link_url) || !filter_var($link_url, FILTER_VALIDATE_URL)) {
            $error = "URL tidak valid. Harap masukkan link yang benar (dimulai dengan http/https).";
        } else {
            $ins = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES (?, ?, ?, 'Link', ?, ?, CURDATE(), ?)");
            $ins->bind_param("sisssi", $ref_type, $ref_id, $nama_dok_base, $link_url, $keterangan, $uid);
            if ($ins->execute()) {
                header("Location: /admin/dms.php?success=" . urlencode("Link dokumen berhasil disimpan!"));
                exit;
            } else { $error = "Gagal simpan DB: " . $conn->error; }
        }
    } else {
        if (!empty($_FILES['file_dok']['name'][0])) {
            $files = $_FILES['file_dok'];
            $count = count($files['name']);
            $success_count = 0;
            
            for ($i = 0; $i < $count; $i++) {
                if ($files['error'][$i] === 0) {
                    $clean_name = preg_replace("/[^a-zA-Z0-9._-]/", "_", $files['name'][$i]);
                    $fn = time() . '_' . $i . '_' . $clean_name;
                    $dir = __DIR__ . '/../uploads/documents/';
                    if (!is_dir($dir)) mkdir($dir, 0777, true);
                    $target = $dir . $fn;
                    
                    if (move_uploaded_file($files['tmp_name'][$i], $target)) {
                        $fp = 'uploads/documents/' . $fn;
                        $nama_dok = $count > 1 ? "$nama_dok_base (" . ($i+1) . ")" : $nama_dok_base;
                        
                        $ins = $conn->prepare("INSERT INTO documents (ref_type, ref_id, nama_dokumen, jenis, file_path, keterangan, tanggal, created_by) VALUES (?, ?, ?, 'Upload', ?, ?, CURDATE(), ?)");
                        $ins->bind_param("sisssi", $ref_type, $ref_id, $nama_dok, $fp, $keterangan, $uid);
                        
                        if ($ins->execute()) $success_count++;
                        else { $error = "Gagal simpan DB: " . $conn->error; break; }
                    } else { $error = "Gagal memindahkan file ke folder uploads."; break; }
                } else {
                    $error = "Error upload file ke-" . ($i+1) . " (Code: " . $files['error'][$i] . ")";
                    break;
                }
            }
            if ($success_count > 0 && !$error) {
                header("Location: /admin/dms.php?success=" . urlencode("$success_count dokumen berhasil diupload!"));
                exit;
            }
        } else { $error = "Pilih minimal satu file yang valid."; }
    }
}

// Delete
if (isset($_GET['del'])) {
    $did = (int)$_GET['del'];
    $filter_ref = $_GET['ref'] ?? 'all';
    $d = $conn->prepare("SELECT file_path FROM documents WHERE id=?");
    $d->bind_param("i", $did); $d->execute();
    $doc = $d->get_result()->fetch_assoc();
    if ($doc) { @unlink(__DIR__ . '/../' . $doc['file_path']); }
    $del2 = $conn->prepare("DELETE FROM documents WHERE id=?");
    $del2->bind_param("i", $did); $del2->execute();
    header("Location: /admin/dms.php?success=" . urlencode("Dokumen dihapus.") . "&ref=" . urlencode($filter_ref));
    exit;
}

// Fetch data Compatibility Fix (No fetch_all)
$all_orders = [];
$res_o = $conn->query("SELECT id, kode_order FROM orders ORDER BY id DESC");
while($row = $res_o->fetch_assoc()) { $all_orders[] = $row; }

$all_members_list = [];
$res_m = $conn->query("SELECT m.id, u.nama_lengkap FROM members m JOIN users u ON m.user_id=u.id ORDER BY u.nama_lengkap");
while($row = $res_m->fetch_assoc()) { $all_members_list[] = $row; }

$filter_ref = $_GET['ref'] ?? 'all';
$sql = "SELECT d.*, u.nama_lengkap as creator FROM documents d LEFT JOIN users u ON d.created_by = u.id";
$where_clauses = [];
if ($filter_ref === 'tanda_terima') {
    $where_clauses[] = "d.doc_subtype = 'tanda_terima'";
} elseif ($filter_ref !== 'all') {
    $ref_val = $conn->real_escape_string($filter_ref === 'others' ? 'general' : $filter_ref);
    $where_clauses[] = "d.ref_type = '$ref_val'";
    $where_clauses[] = "(d.doc_subtype IS NULL OR d.doc_subtype != 'tanda_terima')";
}
if (!empty($where_clauses)) $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
$sql .= " ORDER BY d.id DESC";

$documents = [];
$res_d = $conn->query($sql);
while($row = $res_d->fetch_assoc()) { $documents[] = $row; }

$pageTitle = 'DMS - Manajemen Dokumen';
$activePage = 'dms';
include __DIR__ . '/../includes/admin_header.php';
?>
<style>
.dms-wrapper {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    margin-bottom: 2rem;
}
.dms-sidebar { width: 100%; }
.dms-main { width: 100%; }

@media (min-width: 992px) {
    .dms-wrapper {
        flex-direction: row;
        align-items: flex-start;
    }
    .dms-sidebar { flex: 0 0 380px; }
    .dms-main { flex: 1; min-width: 0; }
}
</style>

<div class="dms-wrapper">
    <div class="dms-sidebar">
        <div class="card" style="margin-bottom:1.5rem;">
            <h3 style="font-size:15px;margin-bottom:1rem;">Upload Dokumen</h3>
            <form method="POST" enctype="multipart/form-data" action="dms.php">
                <div class="form-group">
                    <label class="form-label">Nama Dokumen</label>
                    <input type="text" name="nama_dokumen" class="form-control" placeholder="Contoh: Bukti Transfer #FS001" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Kategori Referensi</label>
                    <select name="ref_type" class="form-control" id="uploadRefType" onchange="updateRefId()">
                        <option value="general" <?= $preType==='general'?'selected':'' ?>>Umum</option>
                        <option value="order" <?= $preType==='order'?'selected':'' ?>>Berkaitan Order</option>
                        <option value="member" <?= $preType==='member'?'selected':'' ?>>Berkaitan Member</option>
                        <option value="keuangan" <?= $preType==='keuangan'?'selected':'' ?>>Berkaitan Keuangan</option>
                    </select>
                </div>
                <div class="form-group" id="uploadRefIdWrap" style="display: <?= $preType==='order'?'block':'none' ?>">
                    <label class="form-label">Pilih Order</label>
                    <select name="ref_id" id="uploadRefId" class="form-control">
                        <option value="0">-- Pilih --</option>
                        <?php foreach ($all_orders as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= $preOrderID===$o['id']?'selected':'' ?>><?= htmlspecialchars($o['kode_order']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" id="uploadMemberIdWrap" style="display: <?= $preType==='member'?'block':'none' ?>">
                    <label class="form-label">Pilih Member</label>
                    <select name="ref_id_member" id="uploadMemberId" class="form-control">
                        <option value="0">-- Pilih --</option>
                        <?php foreach ($all_members_list as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= $preMemberID===$m['id']?'selected':'' ?>><?= htmlspecialchars($m['nama_lengkap']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px; background: #f9fafb; padding: 10px; border-radius: 5px; border: 1px solid #e5e7eb;">
                    <label class="form-label" style="margin-bottom: 8px; display: block; font-weight: 600;">Metode Dokumen</label>
                    <div style="display:flex;gap:15px;flex-wrap:wrap;">
                        <label style="cursor:pointer; display:flex; align-items:center; gap:5px;">
                            <input type="radio" name="input_method" value="upload" checked onchange="toggleUploadMethod()"> Upload File
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:5px;">
                            <input type="radio" name="input_method" value="template" onchange="toggleUploadMethod()"> Gunakan Template
                        </label>
                        <label style="cursor:pointer; display:flex; align-items:center; gap:5px;">
                            <input type="radio" name="input_method" value="link" onchange="toggleUploadMethod()"> Masukkan Link
                        </label>
                    </div>
                </div>

                <div class="form-group" id="uploadFileWrap">
                    <label class="form-label">Pilih File (PDF, Image, Word)</label>
                    <input type="file" name="file_dok[]" id="fileDokInput" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" multiple required>
                </div>

                <div class="form-group" id="uploadLinkWrap" style="display:none; background: #eff6ff; padding: 12px; border-radius: 5px; border: 1px solid #bfdbfe;">
                    <label class="form-label" style="color: #1e3a8a;">Tautan Dokumen (Google Drive, Dropbox, dll)</label>
                    <input type="url" name="link_url" id="linkUrlInput" class="form-control" placeholder="https://..." style="border-color: #93c5fd;">
                    <small style="color: #3b82f6;">Pastikan link dapat diakses secara publik atau oleh pihak berkepentingan.</small>
                </div>

                <div class="form-group" id="templateTextWrap" style="display:none; background: #fefce8; padding: 12px; border-radius: 5px; border: 1px solid #fef08a;">
                    <label class="form-label" style="color: #854d0e;">Pilih Template Teks</label>
                    <select class="form-control" id="templateSelector" onchange="applyTemplate()" style="margin-bottom: 10px; border-color: #fde047;">
                        <option value="">-- Template Kosong --</option>
                        <option value="bukti_serah_terima">Bukti Serah Terima Sepatu (Pickup/Delivery)</option>
                        <option value="invoice_laundry">Invoice Tagihan Cuci Sepatu</option>
                        <option value="disclaimer_sepatu">Surat Keterangan Kondisi Sepatu (Disclaimer)</option>
                        <option value="peringatan_pengambilan">Surat Pengingat Pengambilan Sepatu</option>
                        <option value="memo_internal">Memo Internal / Karyawan</option>
                    </select>
                    <label class="form-label" style="color: #854d0e;">Isi Dokumen</label>
                    <textarea name="template_content" id="templateContent" class="form-control" rows="8" placeholder="Ketik isi dokumen secara manual atau pilih template di atas..." style="border-color: #fde047; resize: vertical;"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Keterangan (Opsional)</label>
                    <textarea name="keterangan" class="form-control" placeholder="Tambahkan catatan jika perlu..."></textarea>
                </div>
                <button type="submit" name="upload" class="btn btn-primary w-100">Upload Dokumen</button>
            </form>
        </div>

        <div class="card">
            <h3 style="font-size:15px;margin-bottom:0.25rem;">Generate Dokumen Otomatis</h3>
            <form method="POST" action="dms.php">
                <div class="form-group">
                    <label class="form-label">Nama Dokumen</label>
                    <input type="text" name="nama_dokumen" class="form-control" placeholder="Laporan Keuangan April 2025" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jenis Laporan</label>
                    <select name="gen_type" class="form-control" id="genType" onchange="toggleGenOptions()">
                        <option value="keuangan_report">Laporan Keuangan Bulanan</option>
                        <option value="order_report">Laporan Order Bulanan</option>
                    </select>
                </div>
                <button type="submit" name="generate" class="btn btn-primary w-100">Generate & Simpan</button>
            </form>
        </div>
    </div>

    <div class="dms-main">
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
                <h3 style="font-size:16px;margin:0;">Daftar Dokumen (<?= count($documents) ?>)</h3>
                <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                    <a href="?ref=all" class="btn <?= $filter_ref==='all'?'btn-primary':'btn-outline' ?> btn-sm">Semua</a>
                    <a href="?ref=order" class="btn <?= $filter_ref==='order'?'btn-primary':'btn-outline' ?> btn-sm">Order</a>
                    <a href="?ref=keuangan" class="btn <?= $filter_ref==='keuangan'?'btn-primary':'btn-outline' ?> btn-sm">Keuangan</a>
                    <a href="?ref=tanda_terima" class="btn <?= $filter_ref==='tanda_terima'?'btn-primary':'btn-outline' ?> btn-sm">Tanda Terima</a>
                </div>
            </div>

            <?php if ($success): ?><div class="alert alert-success" data-auto-close><?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($error):   ?><div class="alert alert-danger"  data-auto-close><?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div style="overflow-x:auto; background:#fff; border-radius:5px;">
                <table class="data-table" style="font-size:13px; width:100%; min-width:650px;">
                    <thead><tr><th style="width:60px">Foto</th><th>Nama Dokumen</th><th>Jenis</th><th>Ref</th><th>Tanggal</th><th>Dicetak Oleh</th><th>Aksi</th></tr></thead>
                    <tbody>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:3rem;color:#9CA3AF">Belum ada dokumen.</td></tr>
                    <?php else: foreach ($documents as $doc):
                        $is_image = in_array(strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION)), ['jpg','jpeg','png','webp','gif']);
                        $is_tt    = $doc['doc_subtype'] === 'tanda_terima';
                    ?>
                        <tr>
                            <td style="padding:0.5rem;">
                                <?php if ($is_image && $doc['jenis'] !== 'Link'): ?>
                                    <img src="/<?= htmlspecialchars($doc['file_path']) ?>"
                                         style="width:52px;height:52px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:pointer;"
                                         onclick="window.open('/<?= htmlspecialchars($doc['file_path']) ?>','_blank')"
                                         alt="Preview">
                                <?php elseif ($doc['jenis'] === 'Generated'): ?>
                                    <div style="width:52px;height:52px;background:var(--primary-light);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:22px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;stroke:var(--primary);"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
                                <?php elseif ($doc['jenis'] === 'Link'): ?>
                                    <div style="width:52px;height:52px;background:#DBEAFE;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:22px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;stroke:#2563EB;"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg></div>
                                <?php else: ?>
                                    <div style="width:52px;height:52px;background:var(--gray-100);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:22px;"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;stroke:#6B7280;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:600"><?= htmlspecialchars($doc['nama_dokumen']) ?></div>
                                <?php if ($is_tt): ?>
                                    <span style="font-size:10px;background:#FEF3C7;color:#92400E;padding:1px 6px;border-radius:10px;font-weight:700;">Tanda Terima</span>
                                <?php endif; ?>
                                <?php if ($doc['keterangan']): ?>
                                    <small style="color:#9CA3AF;display:block;margin-top:3px;"><?= htmlspecialchars(substr($doc['keterangan'],0,60)) . (strlen($doc['keterangan'])>60?'...':'') ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $doc['jenis']==='Generated' ? 'badge-diterima' : ($doc['jenis']==='Link' ? 'badge-diterima' : ($doc['jenis']==='TandaTerima'?'badge-bronze':'badge-proses')) ?>">
                                    <?= $doc['jenis'] ?>
                                </span>
                            </td>
                            <td><span class="badge badge-silver"><?= $doc['ref_type'] ?></span></td>
                            <td style="font-size:12px;white-space:nowrap;"><?= date('d M Y', strtotime($doc['tanggal'])) ?></td>
                            <td style="font-size:12px;white-space:nowrap;"><?= htmlspecialchars($doc['creator'] ?? 'System') ?></td>
                            <td>
                                <div style="display:flex;gap:4px;flex-wrap:wrap;min-width:100px;">
                                    <?php if ($doc['jenis'] === 'Link'): ?>
                                        <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm">Buka Link</a>
                                    <?php else: ?>
                                        <a href="/<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="btn btn-outline btn-sm">Lihat Dokumen</a>
                                    <?php endif; ?>
                                    <a href="?del=<?= $doc['id'] ?>&ref=<?= $filter_ref ?>" class="btn btn-danger btn-sm" data-confirm="Hapus dokumen ini?">Hapus</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const docTemplates = {
    'bukti_serah_terima': `BUKTI SERAH TERIMA SEPATU\n\nTanggal: [TANGGAL]\n\nBerikut kami serahkan / terima sepatu untuk layanan Footstepszs Boutique Gallery dengan rincian sebagai berikut:\n\n1. Merk/Warna Sepatu: [ISI MERK DAN WARNA]\n   Layanan: [TULIS JENIS LAYANAN, cth: Deep Clean / Unyellowing]\n   Kondisi Awal: [TULIS KONDISI AWAL JIKA ADA KERUSAKAN]\n\n2. Merk/Warna Sepatu: [ISI MERK DAN WARNA]\n   Layanan: [TULIS JENIS LAYANAN]\n   Kondisi Awal: [TULIS KONDISI AWAL]\n\nDemikian bukti serah terima ini dibuat agar dapat dipergunakan sebagaimana mestinya.\n\nPihak Footstepszs,\n\n\n( _________________ )\nAdmin`,
    'invoice_laundry': `INVOICE LAYANAN CUCI SEPATU\n\nTanggal: [TANGGAL]\nNo. Invoice: INV-[KODE_ORDER]\n\nKepada Yth,\n[NAMA CUSTOMER]\n\nBerikut kami sampaikan rincian tagihan atas layanan perawatan sepatu di Footstepszs:\n\n1. Layanan: [NAMA LAYANAN] - Rp [NOMINAL]\n2. Layanan: [NAMA LAYANAN] - Rp [NOMINAL]\n-------------------------------------------------\nTotal Tagihan: Rp [TOTAL NOMINAL]\nStatus: [LUNAS / BELUM LUNAS]\n\nMohon melakukan pembayaran melalui transfer ke rekening resmi kami atau pembayaran tunai di kasir.\n\nTerima kasih telah mempercayakan perawatan sepatu Anda pada Footstepszs.`,
    'disclaimer_sepatu': `SURAT KETERANGAN KONDISI SEPATU (DISCLAIMER)\n\nTanggal: [TANGGAL]\n\nDengan hormat,\nBersama surat ini, pihak Footstepszs ingin menginformasikan kondisi sepatu Bapak/Ibu sebelum proses pengerjaan (laundry / repair) dilakukan:\n\nMerk/Warna Sepatu: [MERK/WARNA SEPATU]\nKondisi Ditemukan: \n- [DESKRIPSIKAN KERUSAKAN: Misal sole lepas, warna pudar, kulit terkelupas]\n\nSesuai standar operasional kami, pengerjaan tetap akan dilakukan sebaik mungkin, namun kami tidak bertanggung jawab apabila terjadi kerusakan lanjutan yang diakibatkan oleh kondisi bawaan sepatu tersebut.\n\nDemikian surat keterangan ini kami sampaikan sebagai bentuk transparansi layanan kami.\n\nHormat kami,\nTim Footstepszs`,
    'peringatan_pengambilan': `SURAT PENGINGAT PENGAMBILAN SEPATU\n\nTanggal: [TANGGAL]\n\nKepada Yth,\n[NAMA CUSTOMER]\n\nKami menginformasikan bahwa sepatu Anda telah selesai diproses dan siap untuk diambil di Footstepszs Boutique Gallery sejak tanggal [TANGGAL SELESAI].\n\nSesuai dengan Syarat dan Ketentuan layanan kami, sepatu yang tidak diambil lebih dari 30 hari sejak pemberitahuan selesai, maka risiko kehilangan atau kerusakan berada di luar tanggung jawab pihak Footstepszs, dan dapat dikenakan biaya penitipan.\n\nMohon segera mengatur jadwal pengambilan atau hubungi admin kami untuk opsi pengiriman (Delivery).\n\nTerima kasih atas perhatian Anda.`,
    'memo_internal': `MEMO INTERNAL FOOTSTEPSZS\n\nTanggal: [TANGGAL]\nPerihal: [ISI_PERIHAL]\n\nKepada seluruh tim / staf Footstepszs,\n\n[TULIS ISI PESAN / INSTRUKSI DI SINI]\n\nHarap diperhatikan dan dilaksanakan dengan baik.\n\nMengetahui,\nManajemen Footstepszs`
};

function applyTemplate() {
    const sel = document.getElementById('templateSelector').value;
    const textarea = document.getElementById('templateContent');
    if (docTemplates[sel]) {
        textarea.value = docTemplates[sel].replace(/\[TANGGAL\]/g, new Date().toLocaleDateString('id-ID', {day:'numeric', month:'long', year:'numeric'}));
    } else {
        textarea.value = '';
    }
}

function toggleUploadMethod() {
    const method = document.querySelector('input[name="input_method"]:checked').value;
    const fileInput = document.getElementById('fileDokInput');
    const linkInput = document.getElementById('linkUrlInput');
    
    const uploadWrap = document.getElementById('uploadFileWrap');
    const templateWrap = document.getElementById('templateTextWrap');
    const linkWrap = document.getElementById('uploadLinkWrap');
    
    // Hide all
    uploadWrap.style.display = 'none';
    templateWrap.style.display = 'none';
    linkWrap.style.display = 'none';
    
    fileInput.removeAttribute('required');
    linkInput.removeAttribute('required');
    
    if (method === 'template') {
        templateWrap.style.display = 'block';
    } else if (method === 'link') {
        linkWrap.style.display = 'block';
        linkInput.setAttribute('required', 'required');
    } else {
        uploadWrap.style.display = 'block';
        fileInput.setAttribute('required', 'required');
    }
}

function updateRefId() {
    const type = document.getElementById('uploadRefType').value;
    document.getElementById('uploadRefIdWrap').style.display = (type === 'order') ? 'block' : 'none';
    document.getElementById('uploadMemberIdWrap').style.display = (type === 'member') ? 'block' : 'none';
}
function toggleGenOptions() {
    const type = document.getElementById('genType').value;
    // ... logic simplifies for now ...
}
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 4000);
});
document.querySelectorAll('[data-confirm]').forEach(btn => {
    btn.addEventListener('click', e => { if (!confirm(btn.dataset.confirm)) e.preventDefault(); });
});
</script>

<?php include __DIR__ . '/../includes/admin_header.php'; ?>
