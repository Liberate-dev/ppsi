<?php

session_start();

$pageTitle  = 'Manajemen Order';

$activePage = 'orders';

require_once __DIR__ . '/../includes/admin_header.php';

require_once __DIR__ . '/../cron/auto_cancel.php';



// Jalankan auto-cancel scheduler untuk mengecek order timeout 24 jam

@runAutoCancelScheduler($conn);



// Mark notifications as read

$conn->query("UPDATE notifications SET is_read=1 WHERE user_id=0");



$success = '';

$error = '';



// Handle Set Lunas

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_lunas') {

    $order_id = (int)($_POST['order_id'] ?? 0);

    if ($order_id > 0) {

        $stmtOrd = $conn->prepare("SELECT * FROM orders WHERE id=?");

        $stmtOrd->bind_param("i", $order_id);

        $stmtOrd->execute();

        $ordData = $stmtOrd->get_result()->fetch_assoc();



        if ($ordData) {

            $new_status = ($ordData['status'] === 'Pending Payment') ? 'Cuci' : $ordData['status'];

            $now = date('Y-m-d H:i:s');

            $upd = $conn->prepare("

                UPDATE orders 

                SET status_pembayaran = 'Lunas', 

                    payment_confirmed_at = ?, 

                    status = ? 

                WHERE id = ?

            ");

            $upd->bind_param("ssi", $now, $new_status, $order_id);

            if ($upd->execute()) {

                // Catat ke keuangan sebagai Cash-In

                $order_ref = "Order Lunas #" . $ordData['kode_order'];

                $chkFin = $conn->prepare("SELECT id FROM keuangan WHERE keterangan = ? AND jenis = 'Pemasukan'");

                $chkFin->bind_param("s", $order_ref);

                $chkFin->execute();

                if ($chkFin->get_result()->num_rows == 0) {

                    $adminId = $_SESSION['user_id'] ?? 1;

                    $insFin = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES ('Pemasukan', 'Order Laundry', ?, ?, CURDATE(), ?)");

                    $total = (float)$ordData['total_harga'];

                    $insFin->bind_param("dsi", $total, $order_ref, $adminId);

                    $insFin->execute();

                }



                $uId = (int)$ordData['user_id'];
                if ($uId > 0 && !empty($ordData['tier_member'])) {
                    $tgl_m = date('Y-m-d');
                    $tgl_s = date('Y-m-d', strtotime('+1 month'));
                    $tier = $ordData['tier_member'];
                    $chkM = $conn->prepare("SELECT id FROM members WHERE user_id = ?");
                    $chkM->bind_param("i", $uId);
                    $chkM->execute();
                    $mExists = $chkM->get_result()->fetch_assoc();
                    if ($mExists) {
                        $updM = $conn->prepare("UPDATE members SET level = ?, status = 'Aktif', tanggal_mulai = ?, tanggal_selesai = ? WHERE id = ?");
                        $updM->bind_param("sssi", $tier, $tgl_m, $tgl_s, $mExists['id']);
                        $updM->execute();
                    } else {
                        $insM = $conn->prepare("INSERT INTO members (user_id, level, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, 'Aktif')");
                        $insM->bind_param("isss", $uId, $tier, $tgl_m, $tgl_s);
                        $insM->execute();
                    }
                }
                // Notifikasi WA Pelanggan

                $stmtCust = $conn->query("SELECT no_hp, nama_lengkap FROM users WHERE id=" . (int)$ordData['user_id'])->fetch_assoc();

                if ($stmtCust) {

                    $pesan_wa = "Halo {$stmtCust['nama_lengkap']},\n\nPembayaran untuk pesanan *{$ordData['kode_order']}* telah berhasil kami konfirmasi (Status: LUNAS).\n\nSepatu Anda saat ini berada dalam antrean pengerjaan (Cuci). Terima kasih!";

                    sendWhatsAppNotification($stmtCust['no_hp'], $pesan_wa);

                }



                $success = "Pesanan {$ordData['kode_order']} berhasil dikonfirmasi LUNAS dan masuk ke tahap Cuci.";

            } else {

                $error = "Gagal memperbarui status pembayaran: " . $conn->error;

            }

        }

    }

}



// Handle Quick Status Advance

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'advance_status') {

    $order_id = (int)($_POST['order_id'] ?? 0);

    $target_status = trim($_POST['target_status'] ?? '');

    $valid_statuses = ['Pending Payment', 'Cuci', 'Kering', 'Siap Kirim', 'Selesai', 'Canceled', 'Dibatalkan'];



    if ($order_id > 0 && in_array($target_status, $valid_statuses)) {

        $selesai_date = ($target_status === 'Selesai') ? date('Y-m-d H:i:s') : null;

        $stmtUpd = $conn->prepare("UPDATE orders SET status=?, tanggal_selesai=? WHERE id=?");

        $stmtUpd->bind_param("ssi", $target_status, $selesai_date, $order_id);

        if ($stmtUpd->execute()) {

            if ($target_status === 'Selesai') {

                $ordRes = $conn->query("SELECT * FROM orders WHERE id=$order_id")->fetch_assoc();

                if ($ordRes) {

                    $order_ref = "Order Lunas #" . $ordRes['kode_order'];

                    $chkFin = $conn->prepare("SELECT id FROM keuangan WHERE keterangan = ? AND jenis = 'Pemasukan'");

                    $chkFin->bind_param("s", $order_ref);

                    $chkFin->execute();

                    if ($chkFin->get_result()->num_rows == 0) {

                        $adminId = $_SESSION['user_id'] ?? 1;

                        $insFin = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES ('Pemasukan', 'Order Laundry', ?, ?, CURDATE(), ?)");

                        $total = (float)$ordRes['total_harga'];

                        $insFin->bind_param("dsi", $total, $order_ref, $adminId);

                        $insFin->execute();

                    }

                    updateMemberPoints($conn, $ordRes['user_id'], $ordRes['total_harga']);

                }

            }

            $success = "Status order berhasil diubah menjadi: $target_status.";

        } else {

            $error = "Gagal mengubah status: " . $conn->error;

        }

    }

}



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



// Filter & Search

$status_filter = $_GET['status'] ?? '';

$search        = $_GET['q']      ?? '';

$type_filter   = $_GET['type']   ?? '';

$view_mode     = $_GET['view']   ?? 'kanban'; // 'kanban' | 'table'



$where   = [];

$params  = [];

$types   = '';



if ($status_filter) { 

    $where[] = "o.status = ?"; 

    $params[] = $status_filter; 

    $types .= 's'; 

}

if ($search) {

    $where[] = "(o.kode_order LIKE ? OR u.nama_lengkap LIKE ? OR u.no_hp LIKE ?)";

    $params[] = "%$search%"; 

    $params[] = "%$search%"; 

    $params[] = "%$search%"; 

    $types .= 'sss';

}

if ($type_filter === 'walkin') { $where[] = "u.username LIKE 'guest\_%'"; }

if ($type_filter === 'member') { $where[] = "u.username NOT LIKE 'guest\_%'"; }



$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';



$stmt = $conn->prepare("

    SELECT o.*, u.nama_lengkap, u.no_hp, u.username 

    FROM orders o 

    JOIN users u ON o.user_id=u.id 

    $whereSQL 

    ORDER BY o.id DESC

");

if ($params) { $stmt->bind_param($types, ...$params); }

$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while($row = $result->fetch_assoc()) { 

    $orders[] = $row; 

}



// Group orders into Kanban buckets

function mapToKanbanStage($status) {

    switch ($status) {

        case 'Pending Payment':

        case 'Menunggu':

            return 'pending';

        case 'Cuci':

        case 'Diterima':

            return 'cuci';

        case 'Kering':

        case 'Proses':

            return 'kering';

        case 'Siap Kirim':

            return 'siap_kirim';

        case 'Selesai':

        case 'Diambil':

            return 'selesai';

        case 'Canceled':

        case 'Dibatalkan':

            return 'canceled';

        default:

            return 'cuci';

    }

}



$kanbanStages = [

    'pending' => [

        'title' => 'Pending Payment',

        'badge' => '#F59E0B',

        'bg' => '#FEF3C7',

        'desc' => 'Menunggu transfer / QRIS',

        'items' => []

    ],

    'cuci' => [

        'title' => 'Cuci',

        'badge' => '#3B82F6',

        'bg' => '#DBEAFE',

        'desc' => 'Pencucian & deep cleaning',

        'items' => []

    ],

    'kering' => [

        'title' => 'Kering',

        'badge' => '#8B5CF6',

        'bg' => '#EDE9FE',

        'desc' => 'Proses pengeringan & treatment',

        'items' => []

    ],

    'siap_kirim' => [

        'title' => 'Siap Kirim',

        'badge' => '#10B981',

        'bg' => '#D1FAE5',

        'desc' => 'Packing & siap diambil/antar',

        'items' => []

    ],

    'selesai' => [

        'title' => 'Selesai',

        'badge' => '#059669',

        'bg' => '#ECFDF5',

        'desc' => 'Order tuntas & lunas',

        'items' => []

    ]

];



$canceled_orders = [];



foreach ($orders as $o) {

    $stage = mapToKanbanStage($o['status']);

    if ($stage === 'canceled') {

        $canceled_orders[] = $o;

    } elseif (isset($kanbanStages[$stage])) {

        $kanbanStages[$stage]['items'][] = $o;

    }

}

?>



<style>

/* Kanban Board Professional Styles */

.kanban-toolbar {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 1rem;

    flex-wrap: wrap;

    margin-bottom: 1.5rem;

}

.view-toggle {

    display: flex;

    border: 1px solid var(--border);

    border-radius: 8px;

    overflow: hidden;

    background: #FFF;

}

.view-toggle a {

    padding: 6px 14px;

    font-size: 13px;

    font-weight: 600;

    color: var(--text-secondary);

    text-decoration: none;

}

.view-toggle a.active {

    background: var(--navy);

    color: #FFF;

}

.kanban-container {

    display: grid;

    grid-template-columns: repeat(5, minmax(280px, 1fr));

    gap: 1.25rem;

    overflow-x: auto;

    padding-bottom: 2rem;

    align-items: start;

}

.kanban-col {

    background: #F8F9FA;

    border: 1px solid var(--border);

    border-radius: 14px;

    display: flex;

    flex-direction: column;

    min-height: 480px;

    box-shadow: 0 1px 4px rgba(0,0,0,0.03);

}

.kanban-col-header {

    padding: 1rem;

    border-bottom: 1px solid var(--border);

    display: flex;

    justify-content: space-between;

    align-items: center;

    border-top-left-radius: 14px;

    border-top-right-radius: 14px;

    background: #FFFFFF;

}

.kanban-col-title {

    font-size: 14px;

    font-weight: 700;

    color: var(--navy);

    display: flex;

    align-items: center;

    gap: 8px;

}

.kanban-count {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding: 2px 8px;

    border-radius: 12px;

    font-size: 11px;

    font-weight: 800;

}

.kanban-cards-wrapper {

    padding: 0.85rem;

    display: flex;

    flex-direction: column;

    gap: 0.85rem;

    flex-grow: 1;

}

.kanban-card {

    background: #FFFFFF;

    border: 1px solid var(--border);

    border-radius: 10px;

    padding: 1rem;

    box-shadow: 0 2px 6px rgba(0,0,0,0.04);

    transition: transform 0.15s ease, box-shadow 0.15s ease;

}

.kanban-card:hover {

    transform: translateY(-2px);

    box-shadow: 0 6px 14px rgba(0,0,0,0.08);

}

.card-header-line {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    margin-bottom: 0.5rem;

}

.card-code {

    font-weight: 800;

    font-size: 13px;

    color: var(--navy);

}

.card-customer {

    font-weight: 600;

    font-size: 13px;

    color: var(--text-primary);

    margin-bottom: 2px;

}

.card-phone {

    font-size: 12px;

    color: var(--text-secondary);

    display: inline-flex;

    align-items: center;

    gap: 4px;

}

.card-items-box {

    margin: 0.6rem 0;

    padding: 0.5rem;

    background: #F9FAFB;

    border-radius: 6px;

    font-size: 12px;

    color: var(--text-secondary);

}

.card-price-line {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-top: 0.6rem;

    padding-top: 0.6rem;

    border-top: 1px dashed var(--gray-200);

}

.card-total {

    font-weight: 800;

    font-size: 14px;

    color: var(--navy);

}

.card-actions {

    display: flex;

    gap: 0.4rem;

    margin-top: 0.75rem;

}

.btn-quick {

    flex: 1;

    padding: 6px 10px;

    font-size: 11px;

    font-weight: 700;

    border-radius: 6px;

    border: none;

    cursor: pointer;

    text-align: center;

    text-decoration: none;

    display: inline-block;

}

.btn-lunas {

    background: #10B981;

    color: #FFF;

}

.btn-lunas:hover { background: #059669; }

.btn-advance {

    background: var(--primary);

    color: #FFF;

}

.btn-advance:hover { background: var(--primary-dark); }

.btn-detail {

    background: #F3F4F6;

    color: var(--text-primary);

    border: 1px solid var(--border);

}

.btn-detail:hover { background: #E5E7EB; }

.badge-pay {

    font-size: 10px;

    padding: 2px 6px;

    border-radius: 4px;

    font-weight: 700;

}

.badge-lunas { background: #D1FAE5; color: #065F46; }

.badge-belum { background: #FEE2E2; color: #991B1B; }

</style>



<?php if ($success): ?><div class="alert alert-success" data-auto-close><?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if ($error): ?><div class="alert alert-danger" data-auto-close><?= htmlspecialchars($error) ?></div><?php endif; ?>



<div class="kanban-toolbar">

    <div style="display:flex; align-items:center; gap: 1rem;">

        <h2 style="font-size:1.2rem; font-weight:800; color:var(--navy);">Kelola Pesanan (<?= count($orders) ?>)</h2>

        <a href="order_create.php" class="btn btn-primary btn-sm" style="font-weight:700;">+ Buat Order Manual</a>

    </div>



    <div style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; margin-left:auto;">

        <form method="GET" style="display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">

            <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">

            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" class="form-control" style="max-width:170px" placeholder="Cari kode/nama...">

            <select name="type" class="form-control" style="max-width:140px" onchange="this.form.submit()">

                <option value="" <?= $type_filter===''?'selected':'' ?>>Semua Tipe</option>

                <option value="walkin" <?= $type_filter==='walkin'?'selected':'' ?>>Walk-In / Guest</option>

                <option value="member" <?= $type_filter==='member'?'selected':'' ?>>Member</option>

            </select>

            <button type="submit" class="btn btn-primary btn-sm">Filter</button>

            <?php if ($search || $type_filter || $status_filter): ?>

                <a href="orders.php?view=<?= $view_mode ?>" class="btn btn-outline btn-sm">Reset</a>

            <?php endif; ?>

        </form>



        <div class="view-toggle">

            <a href="orders.php?view=kanban<?= $search ? '&q='.urlencode($search) : '' ?>" class="<?= $view_mode === 'kanban' ? 'active' : '' ?>">Kanban Board</a>

            <a href="orders.php?view=table<?= $search ? '&q='.urlencode($search) : '' ?>" class="<?= $view_mode === 'table' ? 'active' : '' ?>">Tabel Data</a>

        </div>

    </div>

</div>



<?php if ($view_mode === 'kanban'): ?>

    <!-- ================= KANBAN BOARD VIEW ================= -->

    <div class="kanban-container">

        <?php foreach ($kanbanStages as $stageKey => $stage): ?>

            <div class="kanban-col">

                <div class="kanban-col-header">

                    <div class="kanban-col-title">

                        <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?= $stage['badge'] ?>;"></span>

                        <?= htmlspecialchars($stage['title']) ?>

                    </div>

                    <span class="kanban-count" style="background:<?= $stage['bg'] ?>; color:<?= $stage['badge'] ?>;">

                        <?= count($stage['items']) ?>

                    </span>

                </div>



                <div class="kanban-cards-wrapper">

                    <?php if (empty($stage['items'])): ?>

                        <div style="text-align:center; padding:2rem 1rem; color:var(--text-light); font-size:12px;">

                            Tidak ada pesanan

                        </div>

                    <?php else: foreach ($stage['items'] as $ord): 

                        $itemsData = json_decode($ord['items'], true) ?: [];

                        $isGuest = (strpos($ord['username'] ?? '', 'guest_') === 0);

                        $isLunas = (($ord['status_pembayaran'] ?? 'Belum Lunas') === 'Lunas');

                        $metode = $ord['metode_pembayaran'] ?? 'COD';

                    ?>

                        <div class="kanban-card">

                            <div class="card-header-line">

                                <div>

                                    <div class="card-code"><?= htmlspecialchars($ord['kode_order']) ?></div>

                                    <div style="font-size:10px; color:var(--text-light);"><?= timeAgo($ord['tanggal_order']) ?></div>

                                </div>

                                <span class="badge-pay <?= $isLunas ? 'badge-lunas' : 'badge-belum' ?>">

                                    <?= $isLunas ? 'LUNAS' : 'BELUM LUNAS' ?>

                                </span>

                            </div>



                            <div class="card-customer"><?= htmlspecialchars($ord['nama_lengkap']) ?></div>

                            <div class="card-phone">

                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ord['no_hp']) ?>" target="_blank" style="color:var(--primary); text-decoration:none;">

                                    <?= htmlspecialchars($ord['no_hp'] ?: '-') ?>

                                </a>

                                <?php if ($isGuest): ?>

                                    <span style="background:#F3F4F6; color:#4B5563; font-size:9px; padding:1px 5px; border-radius:3px;">Guest</span>

                                <?php elseif (!empty($ord['tier_member'])): ?>

                                    <span style="background:#EDE9FE; color:#5B21B6; font-size:9px; padding:1px 5px; border-radius:3px;"><?= htmlspecialchars($ord['tier_member']) ?></span>

                                <?php endif; ?>

                            </div>



                            <div class="card-items-box">

                                <?php 

                                foreach (array_slice($itemsData, 0, 2) as $it): 

                                ?>

                                    <div>• <?= (int)($it['qty'] ?? 1) ?>x <?= ucfirst($it['type'] ?? 'Item') ?> (<?= htmlspecialchars($it['treatment'] ?? 'Clean') ?>)</div>

                                <?php endforeach; ?>

                                <?php if (count($itemsData) > 2): ?>

                                    <div style="font-size:11px; color:var(--text-light);">+<?= count($itemsData) - 2 ?> item lainnya</div>

                                <?php endif; ?>

                            </div>



                            <div class="card-price-line">

                                <span style="font-size:11px; color:var(--text-secondary);"><?= htmlspecialchars($metode) ?></span>

                                <span class="card-total"><?= formatRupiah($ord['total_harga']) ?></span>

                            </div>



                            <!-- Action Buttons -->

                            <div class="card-actions">

                                <?php if ($stageKey === 'pending' && !$isLunas): ?>

                                    <form method="POST" style="flex:1;">

                                        <input type="hidden" name="action" value="set_lunas">

                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">

                                        <button type="submit" class="btn-quick btn-lunas" style="width:100%;" onclick="return confirm('Konfirmasi bahwa pesanan <?= $ord['kode_order'] ?> sudah lunas?')">

                                            Set Lunas

                                        </button>

                                    </form>

                                <?php elseif ($stageKey === 'cuci'): ?>

                                    <form method="POST" style="flex:1;">

                                        <input type="hidden" name="action" value="advance_status">

                                        <input type="hidden" name="target_status" value="Kering">

                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">

                                        <button type="submit" class="btn-quick btn-advance" style="width:100%;">

                                            Kering →

                                        </button>

                                    </form>

                                <?php elseif ($stageKey === 'kering'): ?>

                                    <form method="POST" style="flex:1;">

                                        <input type="hidden" name="action" value="advance_status">

                                        <input type="hidden" name="target_status" value="Siap Kirim">

                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">

                                        <button type="submit" class="btn-quick btn-advance" style="width:100%;">

                                            Siap Kirim →

                                        </button>

                                    </form>

                                <?php elseif ($stageKey === 'siap_kirim'): ?>

                                    <form method="POST" style="flex:1;">

                                        <input type="hidden" name="action" value="advance_status">

                                        <input type="hidden" name="target_status" value="Selesai">

                                        <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">

                                        <button type="submit" class="btn-quick btn-lunas" style="width:100%;">

                                            Selesaikan

                                        </button>

                                    </form>

                                <?php endif; ?>



                                <a href="order_detail.php?id=<?= $ord['id'] ?>" class="btn-quick btn-detail">

                                    Detail

                                </a>

                            </div>

                        </div>

                    <?php endforeach; endif; ?>

                </div>

            </div>

        <?php endforeach; ?>

    </div>



    <?php if (!empty($canceled_orders)): ?>

        <div style="margin-top:2rem; background:#FFF; border:1px solid var(--border); border-radius:12px; padding:1.5rem;">

            <h3 style="font-size:14px; font-weight:700; color:#DC2626; margin-bottom:1rem;">Pesanan Dibatalkan / Timeout (<?= count($canceled_orders) ?>)</h3>

            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1rem;">

                <?php foreach ($canceled_orders as $cOrd): ?>

                    <div style="background:#FEF2F2; border:1px solid #FECACA; padding:0.85rem; border-radius:8px; font-size:13px;">

                        <div style="display:flex; justify-content:space-between;">

                            <strong><?= htmlspecialchars($cOrd['kode_order']) ?></strong>

                            <span style="color:#DC2626; font-size:11px;">Canceled</span>

                        </div>

                        <div style="color:var(--text-secondary); margin-top:3px;"><?= htmlspecialchars($cOrd['nama_lengkap']) ?> - <?= formatRupiah($cOrd['total_harga']) ?></div>

                        <div style="font-size:11px; color:#B91C1C; margin-top:4px;"><?= htmlspecialchars($cOrd['cancel_reason'] ?: 'Dibatalkan') ?></div>

                        <div style="margin-top:6px;"><a href="order_detail.php?id=<?= $cOrd['id'] ?>" style="font-size:12px; color:var(--primary);">Buka Detail →</a></div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    <?php endif; ?>



<?php else: ?>



    <!-- ================= TABLE DATA VIEW ================= -->

    <div class="table-wrap">

        <table class="data-table">

            <thead>

                <tr>

                    <th>Kode Order</th>

                    <th>Pelanggan</th>

                    <th>Kontak</th>

                    <th>Metode</th>

                    <th>Status Bayar</th>

                    <th>Total</th>

                    <th>Tahap</th>

                    <th>Aksi</th>

                </tr>

            </thead>

            <tbody>

            <?php if (empty($orders)): ?>

                <tr><td colspan="8" style="text-align:center;padding:2rem;color:#9CA3AF;">Tidak ada order ditemukan.</td></tr>

            <?php else: foreach ($orders as $o): 

                $isLunas = (($o['status_pembayaran'] ?? 'Belum Lunas') === 'Lunas');

            ?>

                <tr>

                    <td>

                        <strong><?= htmlspecialchars($o['kode_order']) ?></strong>

                        <?php if (isset($o['username']) && strpos($o['username'], 'guest_') === 0): ?>

                            <span class="badge" style="background:#FEF3C7;color:#92400E;font-size:9px;margin-left:4px;">Guest</span>

                        <?php endif; ?>

                    </td>

                    <td><?= htmlspecialchars($o['nama_lengkap']) ?></td>

                    <td style="font-size:12px">

                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $o['no_hp']) ?>" target="_blank" style="color:var(--primary);">

                            <?= htmlspecialchars($o['no_hp'] ?: '-') ?>

                        </a>

                    </td>

                    <td style="font-size:12px;"><?= htmlspecialchars($o['metode_pembayaran'] ?? 'COD') ?></td>

                    <td>

                        <span class="badge-pay <?= $isLunas ? 'badge-lunas' : 'badge-belum' ?>">

                            <?= $isLunas ? 'Lunas' : 'Belum Lunas' ?>

                        </span>

                    </td>

                    <td><strong><?= formatRupiah($o['total_harga']) ?></strong></td>

                    <td>

                        <span class="badge" style="background:#F3F4F6;color:var(--navy);font-size:11px;font-weight:700;">

                            <?= htmlspecialchars($o['status']) ?>

                        </span>

                    </td>

                    <td>

                        <div style="display:flex;gap:4px;">

                            <a href="order_detail.php?id=<?= $o['id'] ?>" class="btn btn-primary btn-sm" style="padding:4px 8px;font-size:11px;">Detail</a>

                            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus order ini?')">

                                <input type="hidden" name="delete_order_id" value="<?= $o['id'] ?>">

                                <button type="submit" class="btn btn-outline btn-sm" style="padding:4px 8px;font-size:11px;color:#EF4444;border-color:#EF4444;">Hapus</button>

                            </form>

                        </div>

                    </td>

                </tr>

            <?php endforeach; endif; ?>

            </tbody>

        </table>

    </div>

<?php endif; ?>



<?php include __DIR__ . '/../includes/admin_footer.php'; ?>

