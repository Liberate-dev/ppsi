<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';
if (!isCustomer()) { header('Location: /login.php'); exit; }

$userId = (int)$_SESSION['user_id'];
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['daftar_member'])) {
    $level = $_POST['level'];
    // Cek jika sudah ada member aktif atau pending
    $chk = $conn->prepare("SELECT id, status FROM members WHERE user_id=? AND status IN ('Aktif', 'Menunggu Pembayaran')");
    $chk->bind_param("i", $userId); $chk->execute();
    $existing = $chk->get_result()->fetch_assoc();

    if ($existing) {
        $error = ($existing['status'] === 'Aktif') ? "Anda sudah memiliki member aktif." : "Pendaftaran Anda sedang menunggu konfirmasi admin.";
    } else {
        $tgl_mulai = date('Y-m-d');
        $tgl_selesai = date('Y-m-d', strtotime('+1 month')); // Default 1 month, will be updated on approval
        $ins = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Menunggu Pembayaran')");
        $ins->bind_param("isss", $userId, $level, $tgl_mulai, $tgl_selesai);
        if ($ins->execute()) {
            $success = "Pendaftaran berhasil! Silakan lakukan pembayaran dan tunggu konfirmasi admin.";
        } else { $error = "Gagal mendaftar: " . $conn->error; }
    }
}

$member = getMemberInfo($conn, $userId);
// Jika tidak ada member aktif, cek apakah ada yang pending
if (!$member) {
    $pending_stmt = $conn->prepare("SELECT * FROM members WHERE user_id=? AND status='Menunggu Pembayaran'");
    $pending_stmt->bind_param("i", $userId); $pending_stmt->execute();
    $member_pending = $pending_stmt->get_result()->fetch_assoc();
} else { $member_pending = null; }

$pageTitle = 'Membership';
require_once __DIR__ . '/../includes/customer_header.php';

$level_info = [
    'Bronze' => ['price' => 160000, 'quota' => 4,  'savings' => 40000,  'icon' => '🥉', 'color' => '#92400E'],
    'Silver' => ['price' => 300000, 'quota' => 8,  'savings' => 100000, 'icon' => '🥈', 'color' => '#374151'],
    'Gold'   => ['price' => 590000, 'quota' => 16, 'savings' => 210000, 'icon' => '🥇', 'color' => '#D97706'],
];
?>

<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:1.5rem;">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  style="margin-bottom:1.5rem;">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($member): ?>
<!-- Active Member Card ... (already handled above) ... -->
<?php elseif ($member_pending): ?>
<div class="card" style="margin-bottom:2rem;border-left:4px solid #D97706;background:#FFFBEB;">
    <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
        <span style="font-size:2rem">⏳</span>
        <div>
            <div style="font-weight:700;color:#92400E">Pendaftaran Member Sedang Diproses</div>
            <div style="font-size:13px;color:#92400E">Anda telah memilih paket <strong><?= $member_pending['level'] ?></strong>. Mohon tunggu tim admin kami mengkonfirmasi pembayaran Anda.</div>
        </div>
        <div style="margin-left:auto;text-align:right">
             <div style="font-size:11px;font-weight:700;background:#F59E0B;color:white;padding:4px 12px;border-radius:100px;">MENUNGGU KONFIRMASI</div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:2rem;border-left:4px solid var(--primary);background:var(--primary-light);">
    <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
        <span style="font-size:2rem">💡</span>
        <div>
            <div style="font-weight:700">Kamu belum menjadi member.</div>
            <div style="font-size:13px;color:var(--text-secondary)">Pilih paket di bawah ini untuk menikmati layanan premium kami!</div>
        </div>
        <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="document.querySelector('.member-grid').scrollIntoView({behavior:'smooth'})">Lihat Paket ↓</button>
    </div>
</div>
<?php endif; ?>

<!-- Plans -->
<div class="member-grid">
    <?php foreach ($level_info as $level => $info): 
        $is_current = $member && $member['level'] === $level;
        $is_pending = $member_pending && $member_pending['level'] === $level;
    ?>
    <div class="member-card member-<?= strtolower($level) ?>" style="<?= $is_current ? 'outline:3px solid white;outline-offset:4px;' : '' ?>">
        <?php if ($is_current): ?>
            <div style="font-size:11px;font-weight:700;background:white;color:<?= $info['color'] ?>;padding:4px 12px;border-radius:100px;margin-bottom:0.75rem;display:inline-block;">PAKET AKTIF</div><br>
        <?php elseif ($is_pending): ?>
            <div style="font-size:11px;font-weight:700;background:rgba(255,255,255,0.2);color:white;padding:4px 12px;border-radius:100px;margin-bottom:0.75rem;display:inline-block;">MENUNGGU</div><br>
        <?php endif; ?>

        <div style="font-size:2.5rem;margin-bottom:0.5rem"><?= $info['icon'] ?></div>
        <div class="member-level"><?= $level ?></div>
        <div class="member-price"><?= number_format($info['price']/1000) ?>rb<span>/bln</span></div>
        <div class="member-quota">Cuci <?= $info['quota'] ?> Sepatu per Bulan</div>
        <div class="member-savings">Hemat <?= formatRupiah($info['savings']) ?></div>
        
        <div style="margin-top:1.5rem;">
            <?php if ($is_current): ?>
                <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20mau%20perpanjang%20member%20<?= $level ?>" target="_blank" class="btn btn-white btn-sm" style="width:100%;">🔄 Perpanjang via WA</a>
            <?php elseif ($member || $member_pending): ?>
                <button class="btn btn-white btn-sm" style="width:100%;opacity:0.6;cursor:not-allowed" disabled>Daftar <?= $level ?></button>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="level" value="<?= $level ?>">
                    <button type="submit" name="daftar_member" class="btn btn-white btn-sm" style="width:100%;"> Daftar <?= $level ?> Sekarang</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:2rem;text-align:center;">
    <h3 style="margin-bottom:0.75rem">💬 Ada Pertanyaan tentang Member?</h3>
    <p style="color:var(--text-secondary);font-size:14px;margin-bottom:1.5rem">Hubungi kami via WhatsApp, tim kami siap membantu!</p>
    <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20ingin%20tanya%20tentang%20program%20member" target="_blank" class="btn btn-primary">💬 Chat WhatsApp — 089681276080</a>
</div>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
