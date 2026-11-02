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
        $nama   = htmlspecialchars(strip_tags(trim($_POST['nama_lengkap'] ?? '')), ENT_QUOTES, 'UTF-8');
        $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $user   = preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['username'] ?? ''));
        $hp_raw = trim($_POST['no_hp'] ?? '');
        $hp     = preg_replace('/[^0-9]/', '', $hp_raw);
        $alamat = htmlspecialchars(strip_tags(trim($_POST['alamat'] ?? '')), ENT_QUOTES, 'UTF-8');
        $pass   = $_POST['password'] ?? '12345678';

        if (empty($nama) || empty($email) || empty($user) || empty($pass)) {
            $error = "Semua field bertanda * wajib diisi.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Format email tidak valid.";
        } elseif (!empty($hp) && (strlen($hp) < 10 || strlen($hp) > 15)) {
            $error = "Nomor HP / WhatsApp harus 10 - 15 digit angka.";
        } else {
            $chk = $conn->prepare("SELECT id FROM users WHERE email=? OR username=?");
            $chk->bind_param("ss", $email, $user); 
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $error = "Email atau username sudah digunakan.";
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $ins = $conn->prepare("INSERT INTO users (username,email,password,nama_lengkap,no_hp,alamat,role) VALUES (?,?,?,?,?,?,'customer')");
                $ins->bind_param("ssssss", $user, $email, $hash, $nama, $hp, $alamat);
                $ins->execute() ? $success = "Customer baru berhasil ditambahkan." : ($error = "Gagal menambahkan customer.");
            }
        }
    } elseif ($action === 'edit') {
        $uid    = (int)$_POST['user_id'];
        $nama   = htmlspecialchars(strip_tags(trim($_POST['nama_lengkap'] ?? '')), ENT_QUOTES, 'UTF-8');
        $hp_raw = trim($_POST['no_hp'] ?? '');
        $hp     = preg_replace('/[^0-9]/', '', $hp_raw);
        $alamat = htmlspecialchars(strip_tags(trim($_POST['alamat'] ?? '')), ENT_QUOTES, 'UTF-8');

        if (empty($nama)) {
            $error = "Nama lengkap tidak boleh kosong.";
        } elseif (!empty($hp) && (strlen($hp) < 10 || strlen($hp) > 15)) {
            $error = "Nomor HP / WhatsApp harus 10 - 15 digit angka.";
        } else {
            $upd = $conn->prepare("UPDATE users SET nama_lengkap=?, no_hp=?, alamat=? WHERE id=? AND role='customer'");
            $upd->bind_param("sssi", $nama, $hp, $alamat, $uid);
            $upd->execute() ? $success = "Data customer berhasil diperbarui." : ($error = "Gagal memperbarui data customer.");
        }
    } elseif ($action === 'delete') {
        $uid = (int)$_POST['user_id'];
        $del = $conn->prepare("DELETE FROM users WHERE id=? AND role='customer'");
        $del->bind_param("i", $uid);
        $del->execute() ? $success = "Customer berhasil dihapus." : ($error = "Gagal menghapus customer.");
    } elseif ($action === 'reset_password') {
        $uid  = (int)$_POST['user_id'];
        $new_pass = $_POST['new_password'] ?? '';
        if (strlen($new_pass) < 6) {
            $error = "Password baru minimal 6 karakter.";
        } else {
            $pass = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd  = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $pass, $uid);
            $upd->execute() ? $success = "Password customer berhasil direset." : ($error = "Gagal mereset password.");
        }
    }
}

$res_c = $conn->query("SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id=u.id) as total_orders FROM users u WHERE u.role='customer' ORDER BY u.id DESC");
$customers = [];
if ($res_c) {
    while($row = $res_c->fetch_assoc()) { $customers[] = $row; }
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div>
        <div class="table-title">Daftar Customer (<?= count($customers) ?>)</div>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:0;">Kelola data pelanggan terdaftar dan riwayat pesanannya.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('modalAdd').style.display='flex'">+ Tambah Customer</button>
</div>

<?php if ($success): ?><div class="alert alert-success" data-auto-close><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  data-auto-close><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr><th>ID</th><th>Nama</th><th>Username</th><th>Email</th><th>No HP / WA</th><th>Total Order</th><th>Terdaftar</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($customers)): ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:#9CA3AF">Belum ada pelanggan terdaftar.</td></tr>
        <?php else: foreach ($customers as $c): ?>
            <tr>
                <td><strong>#<?= $c['id'] ?></strong></td>
                <td><strong><?= htmlspecialchars($c['nama_lengkap']) ?></strong></td>
                <td><code><?= htmlspecialchars($c['username']) ?></code></td>
                <td><?= htmlspecialchars($c['email']) ?></td>
                <td><?= htmlspecialchars($c['no_hp'] ?: '-') ?></td>
                <td><span class="badge badge-diterima"><?= $c['total_orders'] ?> order</span></td>
                <td style="font-size:12px;color:#6B7280;"><?= date('d M Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        <button class="btn btn-outline btn-sm" onclick="editCustomer(<?= htmlspecialchars(json_encode($c)) ?>)">Edit</button>
                        <button class="btn btn-outline btn-sm" onclick="resetPass(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['nama_lengkap'])) ?>')">Reset PW</button>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus customer ini?')">
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
    <div style="background:white;border-radius:16px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 10px 30px rgba(0,0,0,0.15);">
        <h3 style="margin-bottom:1.25rem;font-size:1.2rem;color:var(--navy);">Tambah Customer Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap pelanggan" required></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label class="form-label">Username *</label><input type="text" name="username" class="form-control" placeholder="username" required></div>
                <div class="form-group">
                    <label class="form-label">No HP / WA (Angka) *</label>
                    <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" placeholder="email@example.com" required></div>
            <div class="form-group"><label class="form-label">Alamat Lengkap</label><textarea name="alamat" class="form-control" rows="2" placeholder="Alamat penjemputan..."></textarea></div>
            <div class="form-group"><label class="form-label">Password Default *</label><input type="text" name="password" class="form-control" value="12345678" required></div>
            <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary">Simpan Customer</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalAdd').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div id="modalEdit" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:16px;padding:2rem;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 10px 30px rgba(0,0,0,0.15);">
        <h3 style="margin-bottom:1.25rem;font-size:1.2rem;color:var(--navy);">Edit Customer</h3>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="form-group"><label class="form-label">Nama Lengkap *</label><input type="text" name="nama_lengkap" id="editNama" class="form-control" required></div>
            <div class="form-group">
                <label class="form-label">No HP / WA (Angka) *</label>
                <input type="text" name="no_hp" id="editHp" class="form-control" placeholder="08xxxxxxxxxx" inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
            </div>
            <div class="form-group"><label class="form-label">Alamat Lengkap</label><textarea name="alamat" id="editAlamat" class="form-control" rows="2"></textarea></div>
            <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary">Update Data</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalEdit').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Reset PW -->
<div id="modalResetPw" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:16px;padding:2rem;width:100%;max-width:400px;box-shadow:0 10px 30px rgba(0,0,0,0.15);">
        <h3 style="margin-bottom:0.25rem;font-size:1.2rem;color:var(--navy);">Reset Password</h3>
        <p style="font-size:13px;color:#6B7280;margin-bottom:1.5rem;" id="resetPwName"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetPwId">
            <div class="form-group"><label class="form-label">Password Baru</label><input type="text" name="new_password" class="form-control" value="12345678" required></div>
            <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary">Reset Password</button>
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
