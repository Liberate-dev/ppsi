<?php
session_start();
$pageTitle = 'Dashboard Saya';
require_once __DIR__ . '/../includes/customer_header.php';

$userId = (int)$_SESSION['user_id'];
$user   = $conn->query("SELECT * FROM users WHERE id=$userId")->fetch_assoc();

// Stats
$total_orders   = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$userId")->fetch_assoc()['c'];
$active_orders  = $conn->query("SELECT COUNT(*) as c FROM orders WHERE user_id=$userId AND status NOT IN ('Selesai','Dibatalkan')")->fetch_assoc()['c'];
$total_spent    = $conn->query("SELECT COALESCE(SUM(total_harga),0) as s FROM orders WHERE user_id=$userId AND status='Selesai'")->fetch_assoc()['s'];

// Latest notifications
$notifs = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 5");
$notifs->bind_param("i", $userId); $notifs->execute();
$resN = $notifs->get_result(); $notifList = []; while($r = $resN->fetch_assoc()) { $notifList[] = $r; }

$uNotif = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
$uNotif->bind_param("i", $userId); $uNotif->execute();

// Recent orders
$recent_orders_stmt = $conn->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC LIMIT 5");
$recent_orders_stmt->bind_param("i", $userId); $recent_orders_stmt->execute();
$resO = $recent_orders_stmt->get_result(); $recent_orders = []; while($r = $resO->fetch_assoc()) { $recent_orders[] = $r; }
?>

<div style="margin-bottom:2rem;">
    <h2 style="font-size:1.4rem;font-weight:800;">Halo, <?= htmlspecialchars(explode(' ', $custName)[0]) ?>! 👋</h2>
    <p style="color:var(--text-secondary)">Selamat datang di dashboard Footstepszs kamu.</p>
</div>

<!-- Stats -->
<div class="stats-row" style="margin-bottom:2rem;">
    <div class="stat-box">
        <div class="stat-icon purple">📦</div>
        <div><div class="stat-box-value"><?= $total_orders ?></div><div class="stat-box-label">Total Order</div></div>
    </div>
    <div class="stat-box">
        <div class="stat-icon amber">⏳</div>
        <div><div class="stat-box-value"><?= $active_orders ?></div><div class="stat-box-label">Sedang Diproses</div></div>
    </div>
    <div class="stat-box">
        <div class="stat-icon green">💰</div>
        <div><div class="stat-box-value" style="font-size:1.1rem"><?= formatRupiah($total_spent) ?></div><div class="stat-box-label">Total Pembayaran</div></div>
    </div>
</div>

<!-- Quick Action -->
<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,var(--navy),var(--primary-dark));color:white;border:none;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
    <div>
        <h3 style="color:white;font-size:1.2rem;">Mau cuci sepatu hari ini? 👟</h3>
        <p style="color:rgba(255,255,255,0.7);font-size:14px">Pilih treatment & jadwalkan pick up gratis di Malang Kota!</p>
    </div>
    <a href="/customer/order.php" class="btn btn-white">+ Pesan Sekarang →</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <!-- Recent Orders -->
    <div class="table-wrap">
        <div class="table-header">
            <span class="table-title">Order Terbaru</span>
            <a href="/customer/my_orders.php" class="btn btn-outline btn-sm">Lihat Semua</a>
        </div>
        <table class="data-table">
            <thead><tr><th>Kode</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($recent_orders)): ?>
                <tr><td colspan="3" style="text-align:center;padding:2rem;color:#9CA3AF">Belum ada order.</td></tr>
            <?php else: foreach ($recent_orders as $o): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['kode_order']) ?></strong><br><small style="color:#9CA3AF"><?= date('d M', strtotime($o['tanggal_order'])) ?></small></td>
                    <td><?= formatRupiah($o['total_harga']) ?></td>
                    <td><span class="badge badge-<?= strtolower(str_replace(' ','',$o['status'])) ?>"><?= $o['status'] ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Notifications -->
    <div class="table-wrap">
        <div class="table-header"><span class="table-title">Notifikasi</span></div>
        <div>
        <?php if (empty($notifList)): ?>
            <div style="padding:2rem;text-align:center;color:#9CA3AF;font-size:14px;">Tidak ada notifikasi.</div>
        <?php else: foreach ($notifList as $n): ?>
            <div style="padding:1rem 1.25rem;border-bottom:1px solid var(--gray-100);display:flex;gap:0.75rem;align-items:flex-start">
                <span style="font-size:20px">🔔</span>
                <div>
                    <div style="font-size:13px"><?= htmlspecialchars($n['pesan']) ?></div>
                    <div style="font-size:11px;color:#9CA3AF;margin-top:4px"><?= timeAgo($n['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
