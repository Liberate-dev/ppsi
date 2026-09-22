<?php
session_start();
$pageTitle = 'Riwayat Order';
require_once __DIR__ . '/../includes/customer_header.php';

$userId = (int)$_SESSION['user_id'];
$new_kode = $_GET['kode'] ?? '';

$orders = $conn->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC");
$orders->bind_param("i", $userId); $orders->execute();
$orders_res = $orders->get_result();
$orders_list = [];
while($row = $orders_res->fetch_assoc()) { $orders_list[] = $row; }
$orders = $orders_list;

$tracker_map = ['Menunggu' => 0, 'Diterima' => 1, 'Proses' => 2, 'Selesai' => 3, 'Dibatalkan' => -1];
$tracker_steps = ['Menunggu', 'Diterima', 'Proses', 'Selesai'];
?>

<?php if ($new_kode): ?>
<div class="alert alert-success" data-auto-close>
    🎉 <strong>Order berhasil dibuat!</strong> Kode order: <strong><?= htmlspecialchars($new_kode) ?></strong>. Tim kami akan segera mengkonfirmasi dan menjemput sepatu Anda!
</div>
<?php endif; ?>

<div style="margin-bottom:1.5rem;">
    <h2 style="font-size:1.2rem;font-weight:800;">Riwayat Order Saya (<?= count($orders) ?>)</h2>
</div>

<?php if (empty($orders)): ?>
<div class="card" style="text-align:center;padding:4rem;">
    <div style="font-size:4rem;margin-bottom:1rem;">👟</div>
    <h3>Belum ada order</h3>
    <p style="color:var(--text-secondary);margin:1rem 0">Pesan layanan laundry sepatu pertama kamu sekarang!</p>
    <a href="/customer/order.php" class="btn btn-primary">+ Pesan Sekarang</a>
</div>
<?php else: foreach ($orders as $o):
    $items = json_decode($o['items'], true) ?: [];
    $step  = $tracker_map[$o['status']] ?? 0;
    $is_done = $o['status'] === 'Selesai';
    $is_cancelled = $o['status'] === 'Dibatalkan';
?>
<div class="card" style="margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1.25rem;">
        <div>
            <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
                <strong style="font-size:16px"><?= htmlspecialchars($o['kode_order']) ?></strong>
                <span class="badge badge-<?= strtolower(str_replace(' ','',$o['status'])) ?>"><?= $o['status'] ?></span>
                <span class="badge badge-silver"><?= $o['jenis_waktu'] ?></span>
            </div>
            <div style="font-size:13px;color:#9CA3AF;margin-top:4px">
                Dipesan: <?= date('d F Y H:i', strtotime($o['tanggal_order'])) ?>
                <?php if ($o['tanggal_estimasi']): ?>
                | Estimasi: <strong><?= date('d F Y', strtotime($o['tanggal_estimasi'])) ?></strong>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right">
            <div style="font-size:1.25rem;font-weight:800;color:var(--primary)"><?= formatRupiah($o['total_harga']) ?></div>
            <?php if ($is_done): ?>
                <div style="font-size:12px;color:var(--success)">✓ Selesai <?= date('d M Y', strtotime($o['tanggal_selesai'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tracker -->
    <?php if (!$is_cancelled): ?>
    <div class="order-tracker" style="margin-bottom:1.25rem;">
        <?php foreach ($tracker_steps as $idx => $ts):
            $cls = '';
            if ($idx < $step) $cls = 'done';
            elseif ($idx == $step && !$is_done) $cls = 'active';
            elseif ($is_done) $cls = 'done';
        ?>
        <div class="tracker-step <?= $cls ?>">
            <div class="tracker-circle"><?= ($cls === 'done') ? '✓' : ($idx + 1) ?></div>
            <div class="tracker-label"><?= $ts ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-danger" style="margin-bottom:1rem;">❌ Order ini dibatalkan.</div>
    <?php endif; ?>

    <!-- Items Summary -->
    <div style="background:var(--gray-50);border-radius:var(--radius);padding:1rem;display:flex;flex-wrap:wrap;gap:0.5rem;">
        <?php foreach ($items as $item): ?>
        <div style="background:white;border:1px solid var(--border);border-radius:8px;padding:6px 12px;font-size:13px;">
            <?= ucfirst($item['type']) ?> ×<?= $item['qty'] ?> — <?= $item['treatment'] ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($o['pickup_address']): ?>
    <div style="font-size:13px;color:var(--text-secondary);margin-top:0.75rem;">
        📍 <?= htmlspecialchars($o['pickup_address']) ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; endif; ?>

<script>
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 6000);
});
</script>

<?php include __DIR__ . '/../includes/customer_footer.php'; ?>
