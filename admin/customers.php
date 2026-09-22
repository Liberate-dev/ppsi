<?php
session_start();
$pageTitle  = 'Akun Customer';
$activePage = 'customers';
require_once __DIR__ . '/../includes/admin_header.php';

$success = '';
$error   = '';

// CRUD Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $nama = trim($_POST['nama_lengkap']); $email = trim($_POST['email']);
        $user = trim($_POST['username']); $hp = trim($_POST['no_hp']); $alamat = trim($_POST['alamat']);
        $pass = $_POST['password'];
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $chk = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
        $chk->bind_param("ss", $email, $user); $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = "Email atau username sudah ada.";
        } else {
            $ins = $conn->prepare("INSERT INTO users (username,email,password,nama_lengkap,no_hp,alamat,role) VALUES (?,?,?,?,?,?,'customer')");
            $ins->bind_param("ssssss", $user, $email, $hash, $nama, $hp, $alamat);
            $ins->execute() ? $success = "Customer berhasil ditambahkan!" : ($error = "Gagal menambahkan.");
        }
    } elseif ($action === 'edit') {
        $uid  = (int)$_POST['user_id'];
        $nama = trim($_POST['nama_lengkap']); $hp = trim($_POST['no_hp']); $alamat = trim($_POST['alamat']);
        $upd  = $conn->prepare("UPDATE users SET nama_lengkap=?, no_hp=?, alamat=? WHERE id=? AND role='customer'");
        $upd->bind_param("sssi", $nama, $hp, $alamat, $uid);
        $upd->execute() ? $success = "Data customer diperbarui!" : ($error = "Gagal update.");
    } elseif ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        $del = $conn->prepare("DELETE FROM users WHERE id=? AND role='customer'");
        $del->bind_param("i", $uid); $del->execute();
        $success = "Customer dihapus.";
    } elseif ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $pass = password_hash($_POST['new_password'], PASSWORD_BCRYPT);
        $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $upd->bind_param("si", $pass, $uid); $upd->execute();
        $success = "Password berhasil direset.";
    }
}

$res_c = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) as total_orders FROM users u WHERE u.role='customer' ORDER BY u.id DESC");
$customers = [];
if ($res_c) {
    while($row = $res_c->fetch_assoc()) { $customers[] = $row; }
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div class="table-title">Daftar Customer (<?= count($customers) ?>)</div>
    <button class="btn btn-primary" onclick="document.getElementById('modalAdd').style.display='flex'">+ Tambah Customer</button>
</div>

<?php if ($success): ?><div class="alert alert-success" data-auto-close>✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  data-auto-close>⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>ID</th><th>Nama</th><th>Username</th><th>Email</th><th>HP</th><th>Total Order</th><th>Daftar</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:#9CA3AF">Belum ada customer.</td></tr>
        <?php else: foreach ($customers as $c): ?>
            <tr>
                <td><strong>#<?= $c['id'] ?></strong></td>
                <td><?= htmlspecialchars($c['nama_lengkap']) ?></td>
                <td><?= htmlspecialchars($c['username']) ?></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= htmlspecialchars($c['no_hp'] ?? '-') ?></td>
                <td><span class="badge badge-diterima"><?= $c['total_orders'] ?> order</span></td>
                <td style="font-size:12px"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <button class="btn btn-outline btn-sm" onclick="editCustomer(<?= htmlspecialchars(json_encode($c)) ?>)">Edit</button>
                        <button class="btn btn-outline btn-sm" onclick="resetPass(<?= $c['id'] ?>, '<?= htmlspecialchars($c['nama_lengkap']) ?>')">Reset PW</button>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Hapus customer ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Add -->
<div id="modalAdd" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:20px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;">
        <h3 style="margin-bottom:1.5rem;">Tambah Customer Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama_lengkap" class="form-control" required></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" required></div>
                <div class="form-group"><label class="form-label">No HP</label><input type="text" name="no_hp" class="form-control"></div>
            </div>
            <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2"></textarea></div>
            <div class="form-group"><label class="form-label">Password *</label><input type="text" name="password" class="form-control" value="12345678" required></div>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalAdd').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:20px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;">
        <h3 style="margin-bottom:1.5rem;">Edit Customer</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="form-group"><label class="form-label">Nama Lengkap</label><input type="text" name="nama_lengkap" id="editNama" class="form-control" required></div>
            <div class="form-group"><label class="form-label">No HP</label><input type="text" name="no_hp" id="editHp" class="form-control"></div>
            <div class="form-group"><label class="form-label">Alamat</label><textarea name="alamat" id="editAlamat" class="form-control" rows="2"></textarea></div>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Update</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalEdit').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset PW -->
<div id="modalResetPw" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:20px;padding:2rem;width:100%;max-width:400px;">
        <h3 style="margin-bottom:0.25rem;">Reset Password</h3>
        <p style="font-size:13px;color:#6B7280;margin-bottom:1.5rem;" id="resetPwName"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetPwId">
            <div class="form-group"><label class="form-label">Password Baru</label><input type="text" name="new_password" class="form-control" value="12345678" required></div>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Reset</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalResetPw').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCustomer(c) {
    document.getElementById('editUserId').value = c.id;
    document.getElementById('editNama').value = c.nama_lengkap;
    document.getElementById('editHp').value = c.no_hp || '';
    document.getElementById('editAlamat').value = c.alamat || '';
    document.getElementById('modalEdit').style.display = 'flex';
}
function resetPass(id, nama) {
    document.getElementById('resetPwId').value = id;
    document.getElementById('resetPwName').textContent = 'Customer: ' + nama;
    document.getElementById('modalResetPw').style.display = 'flex';
}
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
