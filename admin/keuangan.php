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
        $jenis  = in_array($_POST['jenis'] ?? '', ['Pemasukan', 'Pengeluaran']) ? $_POST['jenis'] : 'Pemasukan';
        $kat    = htmlspecialchars(strip_tags(trim($_POST['kategori'] ?? '')), ENT_QUOTES, 'UTF-8');
        $jumlah_raw = preg_replace('/[^0-9]/', '', $_POST['jumlah'] ?? '0');
        $jumlah = (float)$jumlah_raw;
        $ket    = htmlspecialchars(strip_tags(trim($_POST['keterangan'] ?? '')), ENT_QUOTES, 'UTF-8');
        $tgl    = $_POST['tanggal'] ?? date('Y-m-d');
        $uid    = (int)$_SESSION['user_id'];

        if ($jumlah <= 0) {
            $error = "Jumlah nominal harus lebih dari 0.";
        } else {
            $ins = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES (?,?,?,?,?,?)");
            $ins->bind_param("ssdssi", $jenis, $kat, $jumlah, $ket, $tgl, $uid);
            if ($ins->execute()) {
                header("Location: /admin/keuangan.php?success=" . urlencode("Data transaksi keuangan berhasil dicatat."));
                exit;
            } else { 
                $error = "Gagal mencatat transaksi: " . $conn->error; 
            }
        }
    } elseif ($action === 'delete') {
        $kid = (int)$_POST['k_id'];
        $del = $conn->prepare("DELETE FROM keuangan WHERE id=?");
        $del->bind_param("i", $kid); 
        if ($del->execute()) {
            header("Location: /admin/keuangan.php?success=" . urlencode("Entri keuangan berhasil dihapus."));
            exit;
        }
    }
}

$success = $_GET['success'] ?? '';
$pageTitle  = 'Pemasukan & Pengeluaran';
$activePage = 'keuangan';
require_once __DIR__ . '/../includes/admin_header.php';

// Filter
$bulan = $_GET['bulan'] ?? date('Y-m');
$tgl_start = $_GET['tgl_start'] ?? '';
$tgl_end   = $_GET['tgl_end']   ?? '';
$type  = $_GET['type']  ?? '';

$where = []; $params = []; $types = '';

if ($tgl_start && $tgl_end) {
    $where[] = "tanggal BETWEEN ? AND ?";
    $params = [$tgl_start, $tgl_end];
    $types = 'ss';
} else {
    $where[] = "DATE_FORMAT(tanggal, '%Y-%m') = ?";
    $params = [$bulan];
    $types = 's';
}

if ($type && in_array($type, ['Pemasukan', 'Pengeluaran'])) {
    $where[] = "jenis = ?"; 
    $params[] = $type; 
    $types .= 's'; 
}
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare("SELECT * FROM keuangan $whereSQL ORDER BY tanggal DESC, id DESC");
$stmt->bind_param($types, ...$params); 
$stmt->execute();
$res1 = $stmt->get_result();
$entries = [];
while($row = $res1->fetch_assoc()) { $entries[] = $row; }

$totals = $conn->prepare("SELECT jenis, SUM(jumlah) as total FROM keuangan $whereSQL GROUP BY jenis");
$totals->bind_param($types, ...$params); 
$totals->execute();
$res2 = $totals->get_result();
$total_rows = [];
while($row = $res2->fetch_assoc()) { $total_rows[] = $row; }

$t_in = $t_out = 0;
foreach ($total_rows as $tr) {
    if ($tr['jenis'] === 'Pemasukan')  $t_in  = (float)$tr['total'];
    if ($tr['jenis'] === 'Pengeluaran') $t_out = (float)$tr['total'];
}
$profit = $t_in - $t_out;

$kategori_list = ['Order Laundry','Bahan & Supplies','Transport','Gaji','Sewa','Lainnya'];
?>

<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.5rem;">
    <div>
        <h2 style="font-size:1.3rem;font-weight:800;color:var(--navy);margin-bottom:0.25rem;">
            Keuangan - <?= ($tgl_start && $tgl_end) ? "$tgl_start s/d $tgl_end" : date('F Y', strtotime($bulan . '-01')) ?>
        </h2>
        <p style="font-size:0.85rem;color:var(--text-secondary);margin:0;">Laporan arus kas masuk dan operasional pengeluaran usaha.</p>
    </div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:center;">
        <form method="GET" style="display:flex;gap:0.5rem;align-items:center;" id="filterForm">
            <div id="monthFilter" style="display: <?= ($tgl_start && $tgl_end) ? 'none' : 'block' ?>">
                <input type="month" name="bulan" value="<?= $bulan ?>" class="form-control" style="max-width:160px" onchange="this.form.submit()">
            </div>
            <div id="rangeFilter" style="display: <?= ($tgl_start && $tgl_end) ? 'flex' : 'none' ?>; gap:0.5rem;">
                <input type="date" name="tgl_start" value="<?= $tgl_start ?>" class="form-control" style="max-width:150px">
                <input type="date" name="tgl_end" value="<?= $tgl_end ?>" class="form-control" style="max-width:150px">
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </div>
            <select name="type" class="form-control" style="max-width:140px" onchange="this.form.submit()">
                <option value="">Semua Jenis</option>
                <option value="Pemasukan" <?= $type==='Pemasukan'?'selected':'' ?>>Pemasukan</option>
                <option value="Pengeluaran" <?= $type==='Pengeluaran'?'selected':'' ?>>Pengeluaran</option>
            </select>
        </form>
        <button class="btn btn-outline btn-sm" onclick="toggleRange()">Rentang Tanggal</button>
        <button class="btn btn-primary" onclick="document.getElementById('modalAddK').style.display='flex'">+ Transaksi Baru</button>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success" data-auto-close><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  data-auto-close><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Summary Cards -->
<div class="stats-row" style="margin-bottom:1.5rem;">
    <div class="stat-box">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="color:var(--success);font-size:1.3rem;"><?= formatRupiah($t_in) ?></div>
            <div class="stat-box-label">Total Pemasukan</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="color:var(--danger);font-size:1.3rem;"><?= formatRupiah($t_out) ?></div>
            <div class="stat-box-label">Total Pengeluaran</div>
        </div>
    </div>
    <div class="stat-box">
        <div class="stat-icon purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
        </div>
        <div>
            <div class="stat-box-value" style="color:<?= $profit>=0 ? 'var(--primary)' : 'var(--danger)' ?>;font-size:1.3rem;">
                <?= formatRupiah($profit) ?>
            </div>
            <div class="stat-box-label">Laba Bersih</div>
        </div>
    </div>
</div>

<div class="table-wrap">
    <div class="table-header">
        <span class="table-title">Rincian Transaksi (<?= count($entries) ?>)</span>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Jenis</th>
                <th>Kategori</th>
                <th>Jumlah</th>
                <th>Keterangan</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($entries)): ?>
            <tr><td colspan="6" style="text-align:center;padding:2rem;color:#9CA3AF">Tidak ada data transaksi untuk periode ini.</td></tr>
        <?php else: foreach ($entries as $e): ?>
            <tr>
                <td style="font-size:13px;color:#6B7280;"><?= date('d M Y', strtotime($e['tanggal'])) ?></td>
                <td><span class="badge badge-<?= strtolower($e['jenis']) ?>"><?= htmlspecialchars($e['jenis']) ?></span></td>
                <td><strong><?= htmlspecialchars($e['kategori']) ?></strong></td>
                <td style="font-weight:700;color:<?= $e['jenis']=='Pemasukan' ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $e['jenis'] === 'Pemasukan' ? '+' : '-' ?><?= formatRupiah($e['jumlah']) ?>
                </td>
                <td style="font-size:13px;color:#6B7280"><?= htmlspecialchars($e['keterangan'] ?: '-') ?></td>
                <td>
                    <form method="POST" style="margin:0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus entri ini?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="k_id" value="<?= $e['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Add -->
<div id="modalAddK" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:16px;padding:2rem;width:100%;max-width:480px;box-shadow:0 10px 30px rgba(0,0,0,0.15);">
        <h3 style="margin-bottom:1.25rem;font-size:1.2rem;color:var(--navy);">Input Transaksi Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Jenis</label>
                    <select name="jenis" class="form-control">
                        <option value="Pemasukan">Pemasukan (+)</option>
                        <option value="Pengeluaran">Pengeluaran (-)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Kategori</label>
                <select name="kategori" class="form-control">
                    <?php foreach ($kategori_list as $k): ?><option value="<?= $k ?>"><?= $k ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jumlah (Rp) *</label>
                <input type="text" name="jumlah" class="form-control" placeholder="50000" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan (Opsional)</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Deskripsi singkat transaksi...">
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary">Simpan Transaksi</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalAddK').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleRange() {
    const mf = document.getElementById('monthFilter');
    const rf = document.getElementById('rangeFilter');
    if (mf.style.display !== 'none') {
        mf.style.display = 'none';
        rf.style.display = 'flex';
    } else {
        mf.style.display = 'block';
        rf.style.display = 'none';
    }
}
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
