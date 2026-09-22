<?php
session_start();
$pageTitle  = 'Kelola Order';
$activePage = 'orders';
require_once __DIR__ . '/../includes/admin_header.php';

// Mark notifications as read
$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=0");

$success = '';
$error = '';

// Handle Delete Order
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    $del_id = (int)$_POST['delete_order_id'];
    if ($conn->query("DELETE FROM orders WHERE id=$del_id")) {
        $conn->query("DELETE FROM documents WHERE ref_type='order' AND ref_id=$del_id");
        $conn->query("DELETE FROM notifications WHERE link LIKE '%id=$del_id%'");
        $success = "Order berhasil dihapus beserta data dokumennya.";
    } else {
        $error = "Gagal menghapus order.";
    }
}

// Filter
$status_filter = $_GET['status'] ?? '';
$search        = $_GET['q']      ?? '';
$type_filter   = $_GET['type']   ?? ''; // 'walkin' | 'member' | ''
$where   = [];
$params  = [];
$types   = '';

if ($status_filter) { $where[] = "o.status = ?"; $params[] = $status_filter; $types .= 's'; }
if ($search) {
    $where[] = "(o.kode_order LIKE ? OR u.nama_lengkap LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss';
}
if ($type_filter === 'walkin')  { $where[] = "u.username LIKE 'guest\_%'"; }
if ($type_filter === 'member')  { $where[] = "u.username NOT LIKE 'guest\_%'"; }

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $conn->prepare("SELECT o.*, u.nama_lengkap, u.no_hp, u.username FROM orders o JOIN users u ON o.user_id=u.id $whereSQL ORDER BY o.id DESC");
if ($params) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while($row = $result->fetch_assoc()) { $orders[] = $row; }

$statuses = ['', 'Menunggu', 'Diterima', 'Proses', 'Selesai', 'Diambil', 'Dibatalkan'];
$status_labels = ['' => 'Semua Status', 'Menunggu' => 'Menunggu', 'Diterima' => 'Diterima', 'Proses' => 'Proses', 'Selesai' => 'Selesai', 'Diambil' => 'Diambil', 'Dibatalkan' => 'Dibatalkan'];
?>

<?php if ($success): ?><div class="alert alert-success" data-auto-close><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger" data-auto-close><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div style="display:flex; align-items:center; gap: 1rem;">
        <h2 style="font-size:1.1rem;font-weight:700;">Semua Order (<?= count($orders) ?>)</h2>
        <a href="order_create.php" class="btn btn-primary btn-sm" style="font-weight:700;">+ Buat Order Manual</a>
    </div>
    <div style="margin-left:auto;display:flex;gap:0.5rem;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" style="max-width:180px" placeholder="Cari kode / customer...">
            <select name="status" class="form-control" style="max-width:150px" onchange="this.form.submit()">
                <?php foreach ($status_labels as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $status_filter === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <select name="type" class="form-control" style="max-width:150px" onchange="this.form.submit()">
                <option value="" <?= $type_filter===''?'selected':'' ?>>Semua Tipe</option>
                <option value="walkin" <?= $type_filter==='walkin'?'selected':'' ?>>🚶 Walk-In</option>
                <option value="member" <?= $type_filter==='member'?'selected':'' ?>>👤 Member Terdaftar</option>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($search || $status_filter || $type_filter): ?>
                <a href="orders.php" class="btn btn-outline btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead><tr>
            <th>Kode Order</th><th>Customer</th><th>Kontak</th><th>Waktu</th><th>Total</th><th>Status</th><th>Estimasi</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php if (empty($orders)): ?>
            <tr><td colspan="8" style="text-align:center;padding:2rem;color:#9CA3AF;">Tidak ada order ditemukan.</td></tr>
        <?php else: foreach ($orders as $o): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($o['kode_order']) ?></strong>
                    <?php if (isset($o['username']) && strpos($o['username'], 'guest_') === 0): ?>
                        <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:9px;margin-left:4px;">🚶 Walk-In</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($o['nama_lengkap']) ?></td>
                <td style="font-size:12px"><?= htmlspecialchars($o['no_hp'] ?? '-') ?></td>
                <td><?= $o['jenis_waktu'] ?></td>
                <td><strong><?= formatRupiah($o['total_harga']) ?></strong></td>
                <td><span class="badge badge-<?= strtolower(str_replace(' ','',($o['status']))) ?>"><?= $o['status'] ?></span></td>
                <td style="font-size:12px"><?= $o['tanggal_estimasi'] ? date('d M Y', strtotime($o['tanggal_estimasi'])) : '-' ?></td>
                <td>
                    <div style="display:flex;gap:4px;">
                        <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
                        <a href="order_edit.php?id=<?= $o['id'] ?>" class="btn btn-outline btn-sm" style="color:var(--primary);border-color:var(--primary);">Edit</a>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Yakin ingin menghapus order ini secara permanen?');">
                            <input type="hidden" name="delete_order_id" value="<?= $o['id'] ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:var(--danger);">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
