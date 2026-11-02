<?php
session_start();
require_once __DIR__ . '/includes/koneksi.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . ($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/customer/dashboard.php'));
    exit;
}

$error = '';
$tab   = $_GET['tab'] ?? 'login'; // login | register
$success_msg = '';

// =====================
//  REGISTER
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $nama   = htmlspecialchars(strip_tags(trim($_POST['nama_lengkap'] ?? '')), ENT_QUOTES, 'UTF-8');
    $email  = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $user   = preg_replace('/[^a-zA-Z0-9_]/', '', trim($_POST['username'] ?? ''));
    $hp_raw = trim($_POST['no_hp'] ?? '');
    $hp     = preg_replace('/[^0-9]/', '', $hp_raw);
    $alamat = htmlspecialchars(strip_tags(trim($_POST['alamat'] ?? '')), ENT_QUOTES, 'UTF-8');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';
    $tab    = 'register';

    if (empty($nama) || empty($email) || empty($user) || empty($pass)) {
        $error = 'Semua field wajib diisi.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($user) < 3 || strlen($user) > 30) {
        $error = 'Username harus 3 - 30 karakter (huruf, angka, underscore).';
    } elseif (!empty($hp) && (strlen($hp) < 10 || strlen($hp) > 15)) {
        $error = 'Nomor HP/WA harus berupa 10 - 15 digit angka.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password minimal 6 karakter.';
    } elseif ($pass !== $pass2) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        // Check duplicate
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $chk->bind_param("ss", $email, $user);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email atau username sudah terdaftar.';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $ins  = $conn->prepare("INSERT INTO users (username, email, password, nama_lengkap, no_hp, alamat, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
            $ins->bind_param("ssssss", $user, $email, $hash, $nama, $hp, $alamat);
            if ($ins->execute()) {
                $success_msg = 'Akun berhasil dibuat! Silakan masuk dengan akun Anda.';
                $tab = 'login';
            } else {
                $error = 'Gagal membuat akun. Silakan coba kembali.';
            }
        }
    }
}

// =====================
//  LOGIN
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $identifier = trim($_POST['identifier'] ?? '');
    $pass       = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE (email = ? OR username = ?) LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['role']         = $user['role'];
        $_SESSION['nama']         = $user['nama_lengkap'];
        $_SESSION['username']     = $user['username'];
        $_SESSION['admin_logged_in'] = ($user['role'] === 'admin'); // backward compat

        if ($user['role'] === 'admin') {
            header('Location: /admin/dashboard.php');
        } else {
            header('Location: /customer/dashboard.php');
        }
        exit;
    } else {
        $error = 'Email/username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="shortcut icon" href="/favicon.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk / Daftar - Footstepszs</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { background: var(--navy); }
        .auth-tabs { display: flex; gap: 0; margin-bottom: 2rem; border-bottom: 2px solid var(--border); }
        .auth-tab {
            padding: 0.8rem 1.5rem;
            font-size: 14px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: var(--transition);
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }
        .auth-tab.active { color: var(--primary); border-bottom-color: var(--primary); }
        .auth-panel { display: none; }
        .auth-panel.active { display: block; }
    </style>
</head>
<body>
<div class="auth-page">
    <!-- LEFT PANEL -->
    <div class="auth-left">
        <div class="auth-left-content">
            <a href="/index.php" style="display:inline-flex;align-items:baseline;font-family:'Montserrat',sans-serif;font-size:28px;font-weight:900;color:white;letter-spacing:-1px;margin-bottom:2.5rem;text-decoration:none;">
                Foot<span style="color:var(--primary);font-style:italic;font-family:Georgia,serif;font-size:36px;">Step</span>szs
            </a>
            <h2 style="font-size:2rem;color:white;font-weight:800;margin-bottom:1rem;line-height:1.2;">
                Perawatan Sepatu & Tas Premium di Malang
            </h2>
            <p style="color:rgba(255,255,255,0.7);font-size:15px;line-height:1.7;margin-bottom:2.5rem;">
                Layanan laundry spesialis sepatu, tas, dan topi. Deep cleaning, unyellowing, dan repair dengan standar higienis dan bahan material terbaik. Free pick up & delivery area Malang Kota.
            </p>
            <div style="display:flex;flex-direction:column;gap:1rem;">
                <div style="display:flex;align-items:center;gap:0.75rem;color:rgba(255,255,255,0.85);font-size:14px;">
                    <span style="width:28px;height:28px;background:rgba(124,58,237,0.3);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;">✓</span>
                    Free Pick Up & Delivery Malang Kota
                </div>
                <div style="display:flex;align-items:center;gap:0.75rem;color:rgba(255,255,255,0.85);font-size:14px;">
                    <span style="width:28px;height:28px;background:rgba(124,58,237,0.3);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;">✓</span>
                    Pelacakan status pengerjaan secara real-time
                </div>
                <div style="display:flex;align-items:center;gap:0.75rem;color:rgba(255,255,255,0.85);font-size:14px;">
                    <span style="width:28px;height:28px;background:rgba(124,58,237,0.3);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;">✓</span>
                    Program membership hemat hingga 15%
                </div>
                <div style="display:flex;align-items:center;gap:0.75rem;color:rgba(255,255,255,0.85);font-size:14px;">
                    <span style="width:28px;height:28px;background:rgba(124,58,237,0.3);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:white;font-weight:700;">✓</span>
                    Dokumentasi foto awal (Tanda Terima 1) & laporan hasil
                </div>
            </div>
            <div style="margin-top:3rem;padding-top:2rem;border-top:1px solid rgba(255,255,255,0.1);">
                <div style="font-size:13px;color:rgba(255,255,255,0.5);">Kontak: 089681276080 | @footstepszs</div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL (Form) -->
    <div class="auth-right">
        <div class="auth-box">
            <!-- TABS -->
            <div class="auth-tabs">
                <button type="button" class="auth-tab <?= $tab === 'login' ? 'active' : '' ?>" onclick="switchTab('login')">Masuk</button>
                <button type="button" class="auth-tab <?= $tab === 'register' ? 'active' : '' ?>" onclick="switchTab('register')">Daftar</button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" data-auto-close><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success_msg): ?>
                <div class="alert alert-success" data-auto-close><?= htmlspecialchars($success_msg) ?></div>
            <?php endif; ?>

            <!-- LOGIN FORM -->
            <div class="auth-panel <?= $tab === 'login' ? 'active' : '' ?>" id="panelLogin">
                <h2 class="auth-title">Selamat Datang</h2>
                <p class="auth-subtitle">Masuk ke akun Footstepszs kamu.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label class="form-label">Email atau Username</label>
                        <input type="text" name="identifier" class="form-control" placeholder="email atau username" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Password akun" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Masuk</button>
                </form>
                <div class="auth-footer">
                    Belum punya akun? <a href="#" onclick="switchTab('register')" style="color:var(--primary);font-weight:600;">Daftar sekarang</a>
                </div>
            </div>

            <!-- REGISTER FORM -->
            <div class="auth-panel <?= $tab === 'register' ? 'active' : '' ?>" id="panelRegister">
                <h2 class="auth-title">Buat Akun Baru</h2>
                <p class="auth-subtitle">Daftar untuk mulai memesan layanan laundry sepatu.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="form-group">
                        <label class="form-label">Nama Lengkap *</label>
                        <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama Lengkap" required value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" placeholder="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No HP / WA (Angka) *</label>
                            <input type="text" name="no_hp" class="form-control" placeholder="08xxxxxxxxxx" inputmode="numeric" pattern="[0-9]{10,15}" maxlength="15" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="email@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alamat Lengkap</label>
                        <textarea name="alamat" class="form-control" placeholder="Alamat untuk pick up & delivery..." rows="2"><?= htmlspecialchars($_POST['alamat'] ?? '') ?></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 6 karakter" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password *</label>
                            <input type="password" name="password2" class="form-control" placeholder="Ulangi password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Daftar Akun</button>
                </form>
                <div class="auth-footer">
                    Sudah punya akun? <a href="#" onclick="switchTab('login')" style="color:var(--primary);font-weight:600;">Masuk sekarang</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.auth-tab').forEach((t, i) => {
        t.classList.toggle('active', (i === 0 && tab === 'login') || (i === 1 && tab === 'register'));
    });
    document.querySelectorAll('.auth-panel').forEach(p => p.classList.remove('active'));
    document.getElementById('panel' + tab.charAt(0).toUpperCase() + tab.slice(1)).classList.add('active');
}
// Auto-close alerts
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition = 'opacity 0.5s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 500); }, 4000);
});
</script>
</body>
</html>
