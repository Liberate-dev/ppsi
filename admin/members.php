<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $user_id  = (int)$_POST['user_id'];
        $level    = $_POST['level'];
        $tgl_mulai = $_POST['tanggal_mulai'];
        $tgl_selesai = date('Y-m-d', strtotime($tgl_mulai . ' +1 month'));

        $ins = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Aktif')");
        $ins->bind_param("isss", $user_id, $level, $tgl_mulai, $tgl_selesai);
        if ($ins->execute()) {
            header("Location: /admin/members.php?success=" . urlencode("Member berhasil ditambahkan!"));
            exit;
        } else { $error = "Gagal: " . $conn->error; }
    } elseif ($action === 'approve') {
        $mid = (int)$_POST['member_id'];
        $tgl_mulai = date('Y-m-d');
        $tgl_selesai = date('Y-m-d', strtotime('+1 month'));
        
        $upd = $conn->prepare("UPDATE members SET status='Aktif', tanggal_mulai=?, tanggal_selesai=? WHERE id=?");
        $upd->bind_param("ssi", $tgl_mulai, $tgl_selesai, $mid);
        if ($upd->execute()) {
            header("Location: /admin/members.php?success=" . urlencode("Member telah disetujui dan aktif!"));
            exit;
        } else { $error = "Gagal: " . $conn->error; }
    } elseif ($action === 'edit') {
        $mid = (int)$_POST['member_id'];
        $level = $_POST['level']; $status = $_POST['status'];
        $tgl_mulai = $_POST['tanggal_mulai']; $tgl_selesai = $_POST['tanggal_selesai'];
        $upd = $conn->prepare("UPDATE members SET level=?, status=?, tanggal_mulai=?, tanggal_selesai=? WHERE id=?");
        $upd->bind_param("ssssi", $level, $status, $tgl_mulai, $tgl_selesai, $mid);
        if ($upd->execute()) {
            header("Location: /admin/members.php?success=" . urlencode("Member diperbarui!"));
            exit;
        } else { $error = "Gagal: " . $conn->error; }
    } elseif ($action === 'delete') {
        $mid = (int)$_POST['member_id'];
        $del = $conn->prepare("DELETE FROM members WHERE id=?");
        $del->bind_param("i", $mid); 
        if ($del->execute()) {
            header("Location: /admin/members.php?success=" . urlencode("Member dihapus!"));
            exit;
        }
    }
}

$success = $_GET['success'] ?? '';
$pageTitle  = 'Member Bulanan';
$activePage = 'members';
require_once __DIR__ . '/../includes/admin_header.php';

$res_m = $conn->query("SELECT m.*, u.nama_lengkap, u.email, u.no_hp FROM members m JOIN users u ON m.user_id=u.id ORDER BY m.id DESC");
$members = []; while($row = $res_m->fetch_assoc()) { $members[] = $row; }

$res_c = $conn->query("SELECT id, nama_lengkap FROM users WHERE role='customer' ORDER BY nama_lengkap");
$all_customers = []; while($row = $res_c->fetch_assoc()) { $all_customers[] = $row; }
$level_prices = ['Bronze' => 160000, 'Silver' => 300000, 'Gold' => 590000];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div style="display:flex;gap:1rem;align-items:center;">
        <div class="table-title">Member Bulanan (<?= count($members) ?>)</div>
        <div style="display:flex;background:#F3F4F6;padding:4px;border-radius:10px;gap:4px;">
            <a href="?status=Aktif" class="btn btn-sm <?= ($_GET['status']??'Aktif') === 'Aktif' ? 'btn-primary' : 'btn-outline' ?>" style="border:none">Aktif</a>
            <a href="?status=Menunggu" class="btn btn-sm <?= ($_GET['status']??'') === 'Menunggu' ? 'btn-primary' : 'btn-outline' ?>" style="border:none">Menunggu</a>
        </div>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalAddM').style.display='flex'">+ Tambah Member</button>
</div>

<?php 
$active_tab = $_GET['status'] ?? 'Aktif';
$filtered_members = array_filter($members, function($m) use ($active_tab) {
    if ($active_tab === 'Menunggu') return $m['status'] === 'Menunggu Pembayaran';
    return $m['status'] === 'Aktif';
});
?>

<?php if ($success): ?><div class="alert alert-success" data-auto-close>✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  data-auto-close>⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Member Level Summary -->
<div class="stats-row" style="margin-bottom:1.5rem;">
    <?php foreach (['Bronze','Silver','Gold'] as $lvl):
        $cnt = count(array_filter($members, function($m) use ($lvl) { return $m['level'] === $lvl && $m['status'] === 'Aktif'; }));
        $icons = ['Bronze' => '🥉', 'Silver' => '🥈', 'Gold' => '🥇'];
    ?>
    <div class="stat-box">
        <div class="stat-icon <?= strtolower($lvl) === 'gold' ? 'amber' : (strtolower($lvl) === 'silver' ? 'blue' : 'purple') ?>"><?= $icons[$lvl] ?></div>
        <div>
            <div class="stat-box-value"><?= $cnt ?></div>
            <div class="stat-box-label">Member <?= $lvl ?> Aktif</div>
            <div class="stat-box-change"><?= formatRupiah($level_prices[$lvl]) ?>/bln</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>Customer</th><th>Level</th><th>Status</th><th>Masa Aktif</th><th>Poin</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($filtered_members)): ?>
            <tr><td colspan="6" style="text-align:center;padding:3rem;color:#9CA3AF">Tidak ada data member dalam kategori ini.</td></tr>
        <?php else: foreach ($filtered_members as $m): 
            $is_exp = strtotime($m['tanggal_selesai']) < time() && $m['status'] === 'Aktif';
        ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($m['nama_lengkap']) ?></div>
                    <div style="font-size:11px;color:#9CA3AF"><?= htmlspecialchars($m['no_hp']) ?>/<?= htmlspecialchars($m['email']) ?></div>
                </td>
                <td><span class="badge badge-silver"><?= $m['level'] ?></span></td>
                <td>
                    <?php if ($m['status'] === 'Aktif'): ?>
                        <span class="badge <?= $is_exp ? 'badge-dibatalkan' : 'badge-diterima' ?>"><?= $is_exp ? 'Expired' : 'Aktif' ?></span>
                    <?php else: ?>
                        <span class="badge badge-proses"><?= $m['status'] ?></span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px">
                    <?php if ($m['status'] === 'Aktif'): ?>
                        <?= date('d/m/y', strtotime($m['tanggal_mulai'])) ?> — <?= date('d/m/y', strtotime($m['tanggal_selesai'])) ?>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
                <td style="font-weight:700"><?= number_format($m['poin']) ?></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <?php if ($m['status'] === 'Menunggu Pembayaran'): ?>
                            <form method="POST" style="display:inline">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Setujui ✅</button>
                            </form>
                        <?php endif; ?>
                        <button class="btn btn-outline btn-sm" onclick="editMember(<?= htmlspecialchars(json_encode($m)) ?>)">Edit</button>
                        <a href="dms.php?ref=member&member_id=<?= $m['id'] ?>" class="btn btn-navy btn-sm">📎 DMS</a>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Hapus data member ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Add Member -->
<div id="modalAddM" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:20px;padding:2rem;width:100%;max-width:480px;">
        <h3 style="margin-bottom:1.5rem;">Tambah Member Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label">Pilih Customer</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Pilih --</option>
                    <?php foreach ($all_customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nama_lengkap']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Level Member</label>
                <select name="level" class="form-control">
                    <option value="Bronze">🥉 Bronze — Rp 160.000/bln (4 sepatu)</option>
                    <option value="Silver">🥈 Silver — Rp 300.000/bln (8 sepatu)</option>
                    <option value="Gold">🥇 Gold — Rp 590.000/bln (16 sepatu)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <p style="font-size:12px;color:#6B7280;margin-bottom:1rem;">* Tanggal selesai otomatis dihitung 1 bulan dari tanggal mulai.</p>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalAddM').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Member -->
<div id="modalEditM" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:20px;padding:2rem;width:100%;max-width:480px;">
        <h3 style="margin-bottom:0.25rem;">Edit Member</h3>
        <p style="font-size:13px;color:#6B7280;margin-bottom:1.5rem;" id="editMemberName"></p>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="member_id" id="editMemberId">
            <div class="form-group">
                <label class="form-label">Level</label>
                <select name="level" id="editMemberLevel" class="form-control">
                    <option value="Bronze">🥉 Bronze</option>
                    <option value="Silver">🥈 Silver</option>
                    <option value="Gold">🥇 Gold</option>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label class="form-label">Tgl Mulai</label><input type="date" name="tanggal_mulai" id="editMulai" class="form-control"></div>
                <div class="form-group"><label class="form-label">Tgl Selesai</label><input type="date" name="tanggal_selesai" id="editSelesai" class="form-control"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="editMemberStatus" class="form-control">
                    <option value="Aktif">Aktif</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalEditM').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editMember(m) {
    document.getElementById('editMemberId').value = m.id;
    document.getElementById('editMemberName').textContent = 'Customer: ' + m.nama_lengkap;
    document.getElementById('editMemberLevel').value = m.level;
    document.getElementById('editMemberStatus').value = m.status;
    document.getElementById('editMulai').value = m.tanggal_mulai;
    document.getElementById('editSelesai').value = m.tanggal_selesai;
    document.getElementById('modalEditM').style.display = 'flex';
}
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
