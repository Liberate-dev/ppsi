<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

if (!isAdmin()) {
    header('Location: /login.php');
    exit;
}

// Sinkronkan status otomatis
syncExpiredMembers($conn);

$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $mid = (int)$_POST['member_id'];
        $del = $conn->prepare("DELETE FROM members WHERE id=?");
        $del->bind_param("i", $mid); 
        if ($del->execute()) {
            header("Location: /admin/members.php?success=" . urlencode("Data member berhasil dihapus."));
            exit;
        } else {
            $error = "Gagal menghapus data member: " . $conn->error;
        }
    }
}

$success = $_GET['success'] ?? '';
$pageTitle  = 'Member Bulanan';
$activePage = 'members';
require_once __DIR__ . '/../includes/admin_header.php';

$res_m = $conn->query("
    SELECT m.*, u.nama_lengkap, u.email, u.no_hp, u.alamat 
    FROM members m 
    JOIN users u ON m.user_id=u.id 
    ORDER BY m.id DESC
");
$members = []; 
while($row = $res_m->fetch_assoc()) { 
    $members[] = $row; 
}

$level_prices = ['Bronze' => 160000, 'Silver' => 300000, 'Gold' => 590000];
$level_quotas = ['Bronze' => 4, 'Silver' => 8, 'Gold' => 16];

// Tab filter: Semua, Aktif, Nonaktif
$active_tab = $_GET['status'] ?? 'Semua';
$filtered_members = array_filter($members, function($m) use ($active_tab) {
    $is_active = ($m['status'] === 'Aktif' && (empty($m['tanggal_selesai']) || $m['tanggal_selesai'] >= date('Y-m-d')));
    if ($active_tab === 'Aktif') return $is_active;
    if ($active_tab === 'Nonaktif') return !$is_active;
    return true;
});
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
    <div style="display:flex;gap:1rem;align-items:center;">
        <div>
            <div class="table-title">Member Bulanan (<?= count($members) ?>)</div>
            <p style="font-size:0.85rem;color:var(--text-secondary);margin:0;">Monitoring keanggotaan otomatis & tracker masa aktif customer.</p>
        </div>
        <div style="display:flex;background:#F3F4F6;padding:4px;border-radius:10px;gap:4px;">
            <a href="?status=Semua" class="btn btn-sm <?= $active_tab === 'Semua' ? 'btn-primary' : 'btn-outline' ?>" style="border:none">Semua</a>
            <a href="?status=Aktif" class="btn btn-sm <?= $active_tab === 'Aktif' ? 'btn-primary' : 'btn-outline' ?>" style="border:none">Aktif</a>
            <a href="?status=Nonaktif" class="btn btn-sm <?= $active_tab === 'Nonaktif' ? 'btn-primary' : 'btn-outline' ?>" style="border:none">Nonaktif</a>
        </div>
    </div>
</div>

<div class="alert alert-info" style="margin-bottom:1.5rem;font-size:13px;line-height:1.6;background:#F0FDF4;border:1px solid #BBF7D0;color:#166534;border-radius:10px;padding:12px 16px;">
    <strong>ℹ️ Sistem Otomatisasi Keanggotaan:</strong> Level dan status member dikelola otomatis oleh sistem berdasarkan transaksi & masa aktif (1 bulan). Status otomatis menjadi <strong>Nonaktif</strong> ketika masa berlaku berakhir. Level tidak dapat diubah sembarangan oleh admin dan harus melalui pengajuan/transaksi perpanjangan dari customer.
</div>

<?php if ($success): ?><div class="alert alert-success" data-auto-close><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-danger"  data-auto-close><?= htmlspecialchars($error) ?></div><?php endif; ?>

<!-- Member Level Summary -->
<div class="stats-row" style="margin-bottom:1.5rem;">
    <?php foreach (['Bronze','Silver','Gold'] as $lvl):
        $cnt = count(array_filter($members, function($m) use ($lvl) { 
            return $m['level'] === $lvl && $m['status'] === 'Aktif' && (empty($m['tanggal_selesai']) || $m['tanggal_selesai'] >= date('Y-m-d')); 
        }));
    ?>
    <div class="stat-box">
        <div class="stat-icon <?= strtolower($lvl) === 'gold' ? 'amber' : (strtolower($lvl) === 'silver' ? 'blue' : 'purple') ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="7"/><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"/></svg>
        </div>
        <div>
            <div class="stat-box-value"><?= $cnt ?></div>
            <div class="stat-box-label">Member <?= $lvl ?> Aktif</div>
            <div class="stat-box-change"><?= formatRupiah($level_prices[$lvl]) ?> &bull; Kuota <?= $level_quotas[$lvl] ?> item/bln</div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Level</th>
                <th>Status</th>
                <th>Masa Aktif & Tracker</th>
                <th>Poin</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($filtered_members)): ?>
            <tr><td colspan="6" style="text-align:center;padding:3rem;color:#9CA3AF">Tidak ada data member dalam kategori ini.</td></tr>
        <?php else: foreach ($filtered_members as $m): 
            $tgl_selesai = $m['tanggal_selesai'];
            $is_active = ($m['status'] === 'Aktif' && (empty($tgl_selesai) || $tgl_selesai >= date('Y-m-d')));
            $sisa_hari = $tgl_selesai ? (int)ceil((strtotime($tgl_selesai) - strtotime(date('Y-m-d'))) / 86400) : 0;
            $is_h3 = ($is_active && $sisa_hari <= 3 && $sisa_hari >= 0);
            
            // Kuota details
            $quota = getMemberQuotaDetails($conn, $m['user_id'], $m);
        ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($m['nama_lengkap']) ?></div>
                    <div style="font-size:11px;color:#9CA3AF"><?= htmlspecialchars($m['no_hp'] ?: '-') ?> &bull; <?= htmlspecialchars($m['email']) ?></div>
                </td>
                <td>
                    <span class="badge badge-<?= strtolower($m['level']) ?>"><?= htmlspecialchars($m['level']) ?></span>
                </td>
                <td>
                    <?php if ($is_active): ?>
                        <?php if ($is_h3): ?>
                            <span class="badge" style="background:#FEF3C7;color:#92400E;font-weight:700;">H-<?= $sisa_hari ?> (Segera Berakhir)</span>
                        <?php else: ?>
                            <span class="badge badge-diterima">Aktif</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-dibatalkan">Nonaktif (Expired)</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="font-size:12px;color:var(--navy);font-weight:600;">
                        <?= $m['tanggal_mulai'] ? date('d M Y', strtotime($m['tanggal_mulai'])) : '-' ?> s/d <?= $tgl_selesai ? date('d M Y', strtotime($tgl_selesai)) : '-' ?>
                    </div>
                    <div style="font-size:11px;margin-top:2px;">
                        <?php if ($is_active): ?>
                            <span style="color:<?= $is_h3 ? '#D97706' : '#059669' ?>;font-weight:700;">
                                ⏱️ Sisa <?= $sisa_hari ?> hari lagi
                            </span>
                        <?php else: ?>
                            <span style="color:#DC2626;font-weight:600;">Masa aktif berakhir</span>
                        <?php endif; ?>
                        &bull; Kuota: <?= $quota['remaining_quota'] ?>/<?= $quota['total_quota'] ?>
                    </div>
                </td>
                <td style="font-weight:700"><?= number_format($m['poin']) ?></td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <button class="btn btn-outline btn-sm" onclick='showMemberDetail(<?= json_encode([
                            "nama" => $m["nama_lengkap"],
                            "no_hp" => $m["no_hp"],
                            "email" => $m["email"],
                            "alamat" => $m["alamat"] ?? "-",
                            "level" => $m["level"],
                            "status" => $is_active ? "Aktif" : "Nonaktif",
                            "is_h3" => $is_h3,
                            "sisa_hari" => $sisa_hari,
                            "tanggal_mulai" => $m["tanggal_mulai"] ? date("d M Y", strtotime($m["tanggal_mulai"])) : "-",
                            "tanggal_selesai" => $tgl_selesai ? date("d M Y", strtotime($tgl_selesai)) : "-",
                            "total_quota" => $quota["total_quota"],
                            "used_quota" => $quota["used_quota"],
                            "remaining_quota" => $quota["remaining_quota"],
                            "poin" => $m["poin"],
                            "total_transaksi" => formatRupiah($m["total_transaksi"])
                        ]) ?>)'>Detail</button>
                        <form method="POST" style="margin:0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data member ini?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="member_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Detail Tracker Member (Read-Only) -->
<div id="modalDetailM" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:10000;align-items:center;justify-content:center;padding:1rem;">
    <div style="background:white;border-radius:16px;padding:2rem;width:100%;max-width:520px;box-shadow:0 10px 30px rgba(0,0,0,0.2);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <h3 style="font-size:1.2rem;color:var(--navy);margin:0;">Informasi Keanggotaan Member</h3>
            <button type="button" onclick="document.getElementById('modalDetailM').style.display='none'" style="background:none;border:none;font-size:22px;cursor:pointer;color:#9CA3AF;">&times;</button>
        </div>
        
        <div style="background:#F8FAFC;border:1px solid var(--border);border-radius:10px;padding:1rem;margin-bottom:1.25rem;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                <span id="dtNama" style="font-size:1.05rem;font-weight:700;color:var(--navy);">-</span>
                <span id="dtLevelBadge" class="badge badge-gold">-</span>
            </div>
            <div style="font-size:12px;color:var(--text-secondary);" id="dtKontak">-</div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1.25rem;font-size:13px;">
            <div style="background:var(--gray-50);padding:10px;border-radius:8px;">
                <div style="font-size:11px;color:#64748B;">Status Otomatis</div>
                <div id="dtStatusText" style="font-weight:700;margin-top:2px;">-</div>
            </div>
            <div style="background:var(--gray-50);padding:10px;border-radius:8px;">
                <div style="font-size:11px;color:#64748B;">Tracker Masa Aktif</div>
                <div id="dtSisaHariText" style="font-weight:700;margin-top:2px;">-</div>
            </div>
            <div style="background:var(--gray-50);padding:10px;border-radius:8px;">
                <div style="font-size:11px;color:#64748B;">Periode Masa Berlaku</div>
                <div id="dtPeriodeText" style="font-weight:600;margin-top:2px;">-</div>
            </div>
            <div style="background:var(--gray-50);padding:10px;border-radius:8px;">
                <div style="font-size:11px;color:#64748B;">Sisa Kuota Cuci</div>
                <div id="dtQuotaText" style="font-weight:700;color:#059669;margin-top:2px;">-</div>
            </div>
            <div style="background:var(--gray-50);padding:10px;border-radius:8px;">
                <div style="font-size:11px;color:#64748B;">Total Poin</div>
                <div id="dtPoinText" style="font-weight:700;margin-top:2px;">-</div>
            </div>
            <div style="background:var(--gray-50);padding:10px;border-radius:8px;">
                <div style="font-size:11px;color:#64748B;">Total Transaksi</div>
                <div id="dtTotalTxText" style="font-weight:600;margin-top:2px;">-</div>
            </div>
        </div>

        <div style="font-size:11.5px;color:#64748B;line-height:1.5;background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:8px 12px;margin-bottom:1.5rem;">
            🔒 <em>Tingkat level member terkunci dan tidak dapat diubah langsung oleh admin. Customer harus mengajukan atau menyelesaikan transaksi paket untuk perubahan tier.</em>
        </div>

        <div style="display:flex;justify-content:flex-end;">
            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('modalDetailM').style.display='none'">Tutup</button>
        </div>
    </div>
</div>

<script>
function showMemberDetail(data) {
    document.getElementById('dtNama').innerText = data.nama;
    document.getElementById('dtKontak').innerText = data.no_hp + ' • ' + data.email;
    document.getElementById('dtLevelBadge').innerText = data.level;
    document.getElementById('dtLevelBadge').className = 'badge badge-' + data.level.toLowerCase();
    
    document.getElementById('dtStatusText').innerHTML = data.status === 'Aktif' 
        ? '<span style="color:#059669">● Aktif</span>' 
        : '<span style="color:#DC2626">● Nonaktif (Expired)</span>';
        
    document.getElementById('dtSisaHariText').innerText = data.status === 'Aktif' 
        ? (data.sisa_hari + ' Hari Lagi' + (data.is_h3 ? ' (H-3)' : ''))
        : 'Kedaluwarsa';
        
    document.getElementById('dtPeriodeText').innerText = data.tanggal_mulai + ' s/d ' + data.tanggal_selesai;
    document.getElementById('dtQuotaText').innerText = data.remaining_quota + ' dari ' + data.total_quota + ' item';
    document.getElementById('dtPoinText').innerText = Number(data.poin).toLocaleString('id-ID') + ' poin';
    document.getElementById('dtTotalTxText').innerText = data.total_transaksi;
    
    document.getElementById('modalDetailM').style.display = 'flex';
}

document.querySelectorAll('[data-auto-close]').forEach(el => {
    setTimeout(() => { 
        el.style.transition='opacity 0.5s'; 
        el.style.opacity='0'; 
        setTimeout(() => el.remove(), 500); 
    }, 4000);
});
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
