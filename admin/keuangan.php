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
        $jenis  = $_POST['jenis']; $kat = trim($_POST['kategori']);
        $jumlah = (float)str_replace(['.','Rp',' '], '', $_POST['jumlah']);
        $ket    = trim($_POST['keterangan']); $tgl = $_POST['tanggal'];
        $uid    = $_SESSION['user_id'];
        if ($jumlah <= 0) { $error = "Jumlah harus > 0."; }
        else {
            $ins = $conn->prepare("INSERT INTO keuangan (jenis, kategori, jumlah, keterangan, tanggal, created_by) VALUES (?,?,?,?,?,?)");
            $ins->bind_param("ssdssi", $jenis, $kat, $jumlah, $ket, $tgl, $uid);
            if ($ins->execute()) {
                header("Location: /admin/keuangan.php?success=" . urlencode("Data keuangan berhasil ditambahkan!"));
                exit;
            } else { $error = "Gagal: " . $conn->error; }
        }
    } elseif ($action === 'delete') {
        $kid = (int)$_POST['k_id'];
        $del = $conn->prepare("DELETE FROM keuangan WHERE id=?");
        $del->bind_param("i", $kid); 
        if ($del->execute()) {
            header("Location: /admin/keuangan.php?success=" . urlencode("Entri dihapus."));
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

if ($type) { $where[] = "jenis = ?"; $params[] = $type; $types .= 's'; }
$whereSQL = 'WHERE ' . implode(' AND ', $where);

$stmt = $conn->prepare("SELECT * FROM keuangan $whereSQL ORDER BY tanggal DESC, id DESC");
$stmt->bind_param($types, ...$params); $stmt->execute();
$res1 = $stmt->get_result();
$entries = [];
while($row = $res1->fetch_assoc()) { $entries[] = $row; }

$totals = $conn->prepare("SELECT jenis, SUM(jumlah) as total FROM keuangan $whereSQL GROUP BY jenis");
$totals->bind_param($types, ...$params); $totals->execute();
$res2 = $totals->get_result();
$total_rows = [];
while($row = $res2->fetch_assoc()) { $total_rows[] = $row; }

$t_in = $t_out = 0;
foreach ($total_rows as $tr) {
    if ($tr['jenis'] === 'Pemasukan')  $t_in  = $tr['total'];
    if ($tr['jenis'] === 'Pengeluaran') $t_out = $tr['total'];
}
$profit = $t_in - $t_out;

$kategori_list = ['Order Laundry','Bahan & Supplies','Transport','Gaji','Sewa','Lainnya'];
?>

<div style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;justify-content:space-between;margin-bottom:1.5rem;">
    <h2 style="font-size:1.1rem;font-weight:700;">Keuangan — <?= ($tgl_start && $tgl_end) ? "$tgl_start s/d $tgl_end" : date('F Y', strtotime($bulan . '-01')) ?></h2>
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
            <select name="type" class="form-control" style="max-width:120px" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="Pemasukan"  <?= $type==='Pemasukan' ? 'selected':'' ?>>Pemasukan</option>
                <option value="Pengeluaran" <?= $type==='Pengeluaran' ? 'selected':'' ?>>Pengeluaran</option>
            </select>
            <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap;">
                <input type="checkbox" id="checkRange" onchange="toggleFilterMode()" <?= ($tgl_start && $tgl_end) ? 'checked' : '' ?>> Rentang Tanggal
            </label>
        </form>
        <button class="btn btn-primary" onclick="document.getElementById('modalAddK').style.display='flex'">+ Input Transaksi</button>
    </div>
</div>

<script>
function toggleFilterMode() {
    const isRange = document.getElementById('checkRange').checked;
    document.getElementById('monthFilter').style.display = isRange ? 'none' : 'block';
    document.getElementById('rangeFilter').style.display = isRange ? 'flex' : 'none';
    if (!isRange) {
        // Clear range values so it defaults to month when unchecked
        document.getElementsByName('tgl_start')[0].value = '';
        document.getElementsByName('tgl_end')[0].value = '';
    }
}
</script>

<?php if ($success): ?><div class="alert alert-success" data-auto-close>✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  data-auto-close>⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;margin-bottom:1.5rem;">
    <div class="card" style="border-left:4px solid var(--success);">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-bottom:0.5rem;">Total Pemasukan</div>
        <div style="font-size:1.6rem;font-weight:800;color:var(--success);"><?= formatRupiah($t_in) ?></div>
    </div>
    <div class="card" style="border-left:4px solid var(--danger);">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-bottom:0.5rem;">Total Pengeluaran</div>
        <div style="font-size:1.6rem;font-weight:800;color:var(--danger);"><?= formatRupiah($t_out) ?></div>
    </div>
    <div class="card" style="border-left:4px solid <?= $profit >= 0 ? 'var(--primary)' : 'var(--warning)' ?>">
        <div style="font-size:12px;font-weight:700;text-transform:uppercase;color:#6B7280;margin-bottom:0.5rem;">Net Profit</div>
        <div style="font-size:1.6rem;font-weight:800;color:<?= $profit >= 0 ? 'var(--primary)' : 'var(--danger)' ?>"><?= formatRupiah(abs($profit)) ?><?= $profit < 0 ? ' 📉' : ' 📈' ?></div>
    </div>
</div>

<div class="table-wrap">
    <div class="table-header">
        <span class="table-title">Riwayat Transaksi (<?= count($entries) ?>)</span>
    </div>
    <table class="data-table">
        <thead><tr><th>Tanggal</th><th>Jenis</th><th>Kategori</th><th>Jumlah</th><th>Keterangan</th><th>Aksi</th></tr></thead>
        <tbody>
        <?php if (empty($entries)): ?>
            <tr><td colspan="6" style="text-align:center;padding:2rem;color:#9CA3AF">Tidak ada data untuk bulan ini.</td></tr>
        <?php else: foreach ($entries as $e): ?>
            <tr>
                <td style="font-size:12px"><?= date('d M Y', strtotime($e['tanggal'])) ?></td>
                <td><span class="badge badge-<?= strtolower($e['jenis']) ?>"><?= $e['jenis'] ?></span></td>
                <td><?= htmlspecialchars($e['kategori']) ?></td>
                <td style="font-weight:700;color:<?= $e['jenis']=='Pemasukan' ? 'var(--success)' : 'var(--danger)' ?>">
                    <?= $e['jenis'] === 'Pemasukan' ? '+' : '-' ?><?= formatRupiah($e['jumlah']) ?>
                </td>
                <td style="font-size:13px;color:#6B7280"><?= htmlspecialchars($e['keterangan'] ?: '-') ?></td>
                <td>
                    <form method="POST" style="margin:0" onsubmit="return confirm('Hapus entri ini?')">
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
    <div style="background:white;border-radius:20px;padding:2rem;width:100%;max-width:480px;">
        <h3 style="margin-bottom:1.5rem;">Input Transaksi Baru</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Jenis</label>
                    <select name="jenis" class="form-control">
                        <option value="Pemasukan">💰 Pemasukan</option>
                        <option value="Pengeluaran">💸 Pengeluaran</option>
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
                <label class="form-label">Jumlah (Rp)</label>
                <input type="number" name="jumlah" class="form-control" placeholder="50000" min="1" required>
            </div>
            <div class="form-group">
                <label class="form-label">Keterangan (opsional)</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Deskripsi singkat...">
            </div>
            <div style="display:flex;gap:0.75rem;">
                <button type="submit" class="btn btn-primary">Simpan</button>
                <button type="button" class="btn btn-outline" onclick="document.getElementById('modalAddK').style.display='none'">Batal</button>
            </div>
        </form>
    </div>
</div>
<script>
document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { el.style.transition='opacity 0.5s'; el.style.opacity='0'; setTimeout(()=>el.remove(),500); }, 4000);
});
</script>
<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
