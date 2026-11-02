<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';
if (!isCustomer()) { header('Location: /login.php'); exit; }

$userId = (int)$_SESSION['user_id'];
$success = ''; $error = '';

// Sinkronkan status otomatis
syncExpiredMembers($conn);

$member = getMemberInfo($conn, $userId);
$sisa_hari = 0;
$is_h3 = false;

if ($member) {
    $tgl_selesai = $member['tanggal_selesai'];
    $sisa_hari = $tgl_selesai ? max(0, (int)ceil((strtotime($tgl_selesai) - strtotime(date('Y-m-d'))) / 86400)) : 30;
    $is_h3 = ($sisa_hari <= 3 && $sisa_hari >= 0);
    $prev_member = null;
} else {
    $prev_stmt = $conn->prepare("SELECT * FROM members WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $prev_stmt->bind_param("i", $userId);
    $prev_stmt->execute();
    $prev_member = $prev_stmt->get_result()->fetch_assoc();
}

$pageTitle = 'Membership';
require_once __DIR__ . '/../includes/customer_header.php';

$level_info = [
    'Bronze' => ['price' => 160000, 'quota' => 4,  'savings' => 40000,  'color' => '#92400E', 'discount' => '5%'],
    'Silver' => ['price' => 300000, 'quota' => 8,  'savings' => 100000, 'color' => '#374151', 'discount' => '10%'],
    'Gold'   => ['price' => 590000, 'quota' => 16, 'savings' => 210000, 'color' => '#D97706', 'discount' => '15%'],
];
?>

<div style="margin-bottom:1.75rem;">
    <h2 style="font-size:1.35rem;font-weight:800;color:var(--navy);margin-bottom:0.25rem;">Program Membership Bulanan</h2>
    <p style="font-size:0.9rem;color:var(--text-secondary);margin:0;">Hemat perawatan sepatu & tas secara rutin dengan kuota bulanan dan diskon langsung.</p>
</div>

<?php if ($success): ?><div class="alert alert-success" style="margin-bottom:1.5rem;"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  style="margin-bottom:1.5rem;"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if ($member): ?>
    <?php if ($is_h3): ?>
    <div class="card" style="margin-bottom:1.25rem;border-left:4px solid #F59E0B;background:#FFFBEB;border-radius:12px;padding:1rem 1.25rem;">
        <div style="display:flex;gap:0.75rem;align-items:center;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;stroke:#D97706;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <div style="font-weight:700;color:#92400E;font-size:14px;">⚠️ Peringatan Masa Aktif (H-<?= $sisa_hari ?>)</div>
                <div style="font-size:13px;color:#B45309;margin-top:2px;">Masa aktif membership Anda tersisa <strong><?= $sisa_hari ?> hari lagi</strong> (berakhir pada <?= date('d F Y', strtotime($member['tanggal_selesai'])) ?>). Segera lakukan perpanjangan agar kuota dan potongan diskon tidak terputus.</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" style="margin-bottom:2rem;background:linear-gradient(135deg,var(--navy),#1e1b4b);color:white;border:none;border-radius:14px;padding:1.75rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1.25rem;">
            <div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                    <span style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--primary-light);">Membership</span>
                    <span style="background:#10B981;color:white;font-size:10px;font-weight:800;padding:2px 8px;border-radius:100px;">AKTIF</span>
                </div>
                <div style="font-size:1.6rem;font-weight:800;"><?= htmlspecialchars($member['level']) ?> Member</div>
                <div style="font-size:13px;color:rgba(255,255,255,0.75);margin-top:4px;">
                    Periode: <?= date('d M Y', strtotime($member['tanggal_mulai'])) ?> s/d <?= date('d M Y', strtotime($member['tanggal_selesai'])) ?>
                </div>
                <div style="margin-top:8px;display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,0.12);padding:4px 12px;border-radius:8px;font-size:12.5px;font-weight:600;">
                    <span>⏱️ Tracker Masa Aktif:</span>
                    <strong style="color:<?= $is_h3 ? '#FCD34D' : '#6EE7B7' ?>;">Tersisa <?= $sisa_hari ?> Hari Lagi</strong>
                </div>
            </div>
            <div>
                <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20mau%20perpanjang%20atau%20upgrade%20member%20<?= urlencode($member['level']) ?>%20(Sisa%20<?= $sisa_hari ?>%20hari)" target="_blank" class="btn btn-white btn-sm" style="font-weight:700;">Perpanjang / Upgrade via WA</a>
            </div>
        </div>
    </div>
<?php elseif ($prev_member): ?>
    <div class="card" style="margin-bottom:2rem;border-left:4px solid #DC2626;background:#FEF2F2;border-radius:12px;padding:1.25rem;">
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;stroke:#DC2626;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <div>
                <div style="font-weight:700;color:#991B1B;">Membership Sebelumnya Telah Berakhir (Nonaktif)</div>
                <div style="font-size:13px;color:#7F1D1D;margin-top:2px;">Paket <strong><?= htmlspecialchars($prev_member['level']) ?></strong> Anda telah kedaluwarsa pada <?= date('d F Y', strtotime($prev_member['tanggal_selesai'])) ?>. Pilih paket di bawah ini untuk mengaktifkan kembali keuntungan Anda.</div>
            </div>
            <div style="margin-left:auto;">
                <span class="badge badge-dibatalkan" style="padding:6px 12px;">NONAKTIF</span>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card" style="margin-bottom:2rem;border-left:4px solid var(--primary);background:var(--primary-light);border-radius:12px;padding:1.25rem;">
        <div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:24px;height:24px;stroke:var(--primary);flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <div>
                <div style="font-weight:700;color:var(--navy);">Kamu belum memiliki membership aktif.</div>
                <div style="font-size:13px;color:var(--text-secondary);">Pilih paket di bawah ini untuk menikmati kuota cuci bulanan dan potongan harga otomatis.</div>
            </div>
            <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="document.querySelector('.member-grid').scrollIntoView({behavior:'smooth'})">Lihat Paket</button>
        </div>
    </div>
<?php endif; ?>

<!-- Plans -->
<div class="member-grid">
    <?php foreach ($level_info as $level => $info): 
        $is_current = $member && $member['level'] === $level;
    ?>
    <div class="member-card member-<?= strtolower($level) ?>" style="<?= $is_current ? 'outline:3px solid white;outline-offset:4px;' : '' ?>">
        <?php if ($is_current): ?>
            <div style="font-size:11px;font-weight:700;background:white;color:<?= $info['color'] ?>;padding:4px 12px;border-radius:100px;margin-bottom:0.75rem;display:inline-block;">PAKET AKTIF</div><br>
        <?php endif; ?>

        <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,0.7);margin-bottom:4px;">Diskon <?= $info['discount'] ?></div>
        <div class="member-level"><?= $level ?></div>
        <div class="member-price"><?= number_format($info['price']/1000) ?>rb<span>/bln</span></div>
        <div class="member-quota">Cuci <?= $info['quota'] ?> Sepatu per Bulan</div>
        <div class="member-savings">Hemat <?= formatRupiah($info['savings']) ?></div>
        
        <div style="margin-top:1.5rem;">
            <?php if ($is_current): ?>
                <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20mau%20perpanjang%20member%20<?= $level ?>%20(Sisa%20<?= $sisa_hari ?>%20hari)" target="_blank" class="btn btn-white btn-sm" style="width:100%;">Perpanjang via WA</a>
            <?php else: ?>
                <a href="/pesan.php" class="btn btn-white btn-sm" style="width:100%;">Pilih & Checkout Paket</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="card" style="margin-top:2rem;text-align:center;padding:2rem;">
    <h3 style="margin-bottom:0.5rem;color:var(--navy);font-size:1.15rem;">Ada Pertanyaan tentang Program Member?</h3>
    <p style="color:var(--text-secondary);font-size:14px;margin-bottom:1.25rem;">Hubungi admin Footstepszs melalui WhatsApp untuk konsultasi kuota, perpanjangan, atau aktivasi paket.</p>
    <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20ingin%20tanya%20tentang%20program%20member" target="_blank" class="btn btn-primary">Chat WhatsApp (089681276080)</a>
</div>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
