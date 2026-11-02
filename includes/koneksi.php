<?php
// ============================================================
//  includes/koneksi.php — Database Connection & Helpers
// ============================================================
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$db   = getenv('DB_NAME') ?: 'footstepszs';

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:2rem;color:red;'>
        <h2>⚠️ Database Connection Error</h2>
        <p>" . $conn->connect_error . "</p>
        <p>Pastikan XAMPP MySQL sudah berjalan dan database <strong>footstepszs</strong> sudah dibuat via phpMyAdmin dengan mengimpor file <code>database.sql</code>.</p>
    </div>");
}

// ---- AUTOMATIC SCHEMA UPGRADER (Safe & Non-Destructive) ----
function autoMigrateSchema($conn) {
    static $migrated = false;
    if ($migrated) return;
    $migrated = true;

    // Check if table orders exists
    $tblCheck = $conn->query("SHOW TABLES LIKE 'orders'");
    if (!$tblCheck || $tblCheck->num_rows === 0) return;

    // Check columns in orders
    $columns = [];
    $res = $conn->query("SHOW COLUMNS FROM orders");
    if ($res) {
        while ($col = $res->fetch_assoc()) {
            $columns[$col['Field']] = true;
        }
    }

    if (!isset($columns['metode_pembayaran'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN metode_pembayaran VARCHAR(30) DEFAULT 'COD' AFTER jenis_waktu");
    }
    if (!isset($columns['status_pembayaran'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN status_pembayaran ENUM('Belum Lunas','Lunas') DEFAULT 'Belum Lunas' AFTER metode_pembayaran");
    }
    if (!isset($columns['diskon_member'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN diskon_member DECIMAL(15,2) DEFAULT 0 AFTER status_pembayaran");
    }
    if (!isset($columns['tier_member'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN tier_member VARCHAR(50) DEFAULT NULL AFTER diskon_member");
    }
    if (!isset($columns['payment_deadline'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_deadline DATETIME DEFAULT NULL AFTER catatan");
    }
    if (!isset($columns['payment_confirmed_at'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN payment_confirmed_at DATETIME DEFAULT NULL AFTER payment_deadline");
    }
    if (!isset($columns['cancel_reason'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN cancel_reason TEXT DEFAULT NULL AFTER payment_confirmed_at");
    }
    if (!isset($columns['canceled_at'])) {
        $conn->query("ALTER TABLE orders ADD COLUMN canceled_at DATETIME DEFAULT NULL AFTER cancel_reason");
    }

    // Expand status ENUM to support both Kanban workflow and legacy statuses
    @$conn->query("ALTER TABLE orders MODIFY COLUMN status ENUM('Pending Payment','Cuci','Kering','Siap Kirim','Selesai','Canceled','Menunggu','Diterima','Proses','Diambil','Dibatalkan') DEFAULT 'Cuci'");
}

// Run auto migration
autoMigrateSchema($conn);

// ---- HELPERS -----------------------------------------------

function formatRupiah($angka) {
    return "Rp " . number_format((float)$angka, 0, ',', '.');
}

function generateOrderCode($conn) {
    $today = date('Ymd');
    $prefix = "ORD-" . $today . "-";
    $result = $conn->query("SELECT MAX(CAST(SUBSTRING(kode_order, 14) AS UNSIGNED)) as max_seq FROM orders WHERE kode_order LIKE '$prefix%'");
    $row = $result->fetch_assoc();
    $next_seq = ($row['max_seq'] ?? 0) + 1;
    return $prefix . str_pad($next_seq, 3, '0', STR_PAD_LEFT);
}

function getEstimasiSelesai($jenis_waktu) {
    $days = ($jenis_waktu === 'Fast Clean') ? 1 : 2;
    return date('Y-m-d H:i:s', strtotime("+{$days} days"));
}

function addNotification($conn, $user_id, $pesan, $url = '') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, pesan, url) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $user_id, $pesan, $url);
    $stmt->execute();
}

function getUnreadNotifCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['c'];
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isCustomer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'customer';
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: /login.php");
        exit;
    }
}

function requireCustomer() {
    if (!isCustomer()) {
        header("Location: /login.php");
        exit;
    }
}

function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit;
    }
}

function normalizePhoneNumber($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 2) === '62') {
        $phone = '0' . substr($phone, 2);
    } elseif (substr($phone, 0, 1) !== '0') {
        $phone = '0' . $phone;
    }
    return $phone;
}

/**
 * Otomatisasi sinkronisasi status expired member
 * Mengubah status ke Nonaktif jika sudah melewati tanggal_selesai
 */
function syncExpiredMembers($conn) {
    if (!$conn) return;
    $conn->query("UPDATE members SET status = 'Nonaktif' WHERE tanggal_selesai IS NOT NULL AND tanggal_selesai < CURDATE() AND status = 'Aktif'");
}

function getTierTotalQuota($tier) {
    $quotas = ['Bronze' => 4, 'Silver' => 8, 'Gold' => 16];
    return $quotas[$tier] ?? 4;
}

function getMemberQuotaDetails($conn, $user_id, $memberRow = null) {
    if (!$memberRow) {
        $memberRow = getMemberInfo($conn, $user_id);
    }
    if (!$memberRow) {
        return ['total_quota' => 0, 'used_quota' => 0, 'remaining_quota' => 0];
    }
    $tier = $memberRow['level'];
    $totalQuota = getTierTotalQuota($tier);
    $tgl_mulai = $memberRow['tanggal_mulai'] ?? date('Y-m-01');

    $stmt = $conn->prepare("SELECT items FROM orders WHERE user_id = ? AND status NOT IN ('Canceled', 'Dibatalkan') AND tanggal_order >= ?");
    if (!$stmt) {
        return ['total_quota' => $totalQuota, 'used_quota' => 0, 'remaining_quota' => $totalQuota];
    }
    $stmt->bind_param("is", $user_id, $tgl_mulai);
    $stmt->execute();
    $res = $stmt->get_result();
    $usedItems = 0;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $items = json_decode($row['items'], true) ?: [];
            foreach ($items as $it) {
                $usedItems += (int)($it['qty'] ?? 1);
            }
        }
    }
    $remainingQuota = max(0, $totalQuota - $usedItems);
    $discounts = ['Bronze' => 5, 'Silver' => 10, 'Gold' => 15];

    return [
        'is_member' => true,
        'tier' => $tier,
        'total_quota' => $totalQuota,
        'used_quota' => $usedItems,
        'remaining_quota' => $remainingQuota,
        'discount_percent' => $discounts[$tier] ?? 5
    ];
}

/**
 * Lookup Member status by WhatsApp phone number with tracker & H-3 reminder
 */
function lookupMemberByPhone($conn, $phone) {
    syncExpiredMembers($conn);

    $phoneNorm = normalizePhoneNumber($phone);
    $altPhone62 = '62' . substr($phoneNorm, 1);
    $cleanDigits = preg_replace('/[^0-9]/', '', $phone);

    // 1. Cek member aktif
    $stmt = $conn->prepare("
        SELECT m.*, u.nama_lengkap, u.no_hp, u.alamat 
        FROM members m 
        JOIN users u ON m.user_id = u.id 
        WHERE (u.no_hp = ? OR u.no_hp = ? OR REPLACE(REPLACE(u.no_hp, '-', ''), ' ', '') = ?)
          AND m.status = 'Aktif' 
          AND (m.tanggal_selesai IS NULL OR m.tanggal_selesai >= CURDATE())
        LIMIT 1
    ");
    $stmt->bind_param("sss", $phoneNorm, $altPhone62, $cleanDigits);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $m = $res->fetch_assoc();
        $tier = $m['level'] ?? 'Bronze';
        
        $discounts = [
            'Bronze' => 5,
            'Silver' => 10,
            'Gold'   => 15
        ];
        $percent = $discounts[$tier] ?? 5;

        // Ambil alamat terbaru jika u.alamat kosong
        $alamat = $m['alamat'] ?? '';
        if (empty($alamat)) {
            $addrStmt = $conn->prepare("SELECT pickup_address FROM orders WHERE user_id = ? AND pickup_address IS NOT NULL AND pickup_address != '' ORDER BY id DESC LIMIT 1");
            if ($addrStmt) {
                $addrStmt->bind_param("i", $m['user_id']);
                $addrStmt->execute();
                $addrRes = $addrStmt->get_result()->fetch_assoc();
                if ($addrRes && !empty($addrRes['pickup_address'])) {
                    $alamat = $addrRes['pickup_address'];
                }
            }
        }

        $quota = getMemberQuotaDetails($conn, $m['user_id'], $m);

        // Tracker sisa hari masa aktif
        $tgl_selesai = $m['tanggal_selesai'];
        $sisa_hari = $tgl_selesai ? max(0, (int)ceil((strtotime($tgl_selesai) - strtotime(date('Y-m-d'))) / 86400)) : 30;
        $is_h3 = ($sisa_hari <= 3 && $sisa_hari >= 0);

        return [
            'is_member' => true,
            'user_id' => $m['user_id'],
            'nama' => $m['nama_lengkap'],
            'no_hp' => $m['no_hp'],
            'alamat' => $alamat,
            'tier' => $tier,
            'discount_percent' => $percent,
            'poin' => (int)$m['poin'],
            'total_quota' => $quota['total_quota'],
            'used_quota' => $quota['used_quota'],
            'remaining_quota' => $quota['remaining_quota'],
            'tanggal_mulai' => $m['tanggal_mulai'],
            'tanggal_selesai' => $m['tanggal_selesai'],
            'tanggal_selesai_formatted' => $tgl_selesai ? date('d M Y', strtotime($tgl_selesai)) : '-',
            'sisa_hari' => $sisa_hari,
            'is_h3' => $is_h3,
            'was_member' => false
        ];
    }

    // 2. Jika bukan member aktif, cek apakah nomor ini pernah terdaftar di members (status Nonaktif / expired)
    $stmtPrev = $conn->prepare("
        SELECT m.*, u.nama_lengkap, u.no_hp, u.alamat 
        FROM members m 
        JOIN users u ON m.user_id = u.id 
        WHERE (u.no_hp = ? OR u.no_hp = ? OR REPLACE(REPLACE(u.no_hp, '-', ''), ' ', '') = ?)
        ORDER BY m.id DESC LIMIT 1
    ");
    $stmtPrev->bind_param("sss", $phoneNorm, $altPhone62, $cleanDigits);
    $stmtPrev->execute();
    $resPrev = $stmtPrev->get_result();
    $wasMember = false;
    $expiredTier = null;
    $expiredDate = null;
    $expiredDateFmt = null;
    $userName = '';
    $userAddr = '';

    if ($resPrev && $resPrev->num_rows > 0) {
        $mPrev = $resPrev->fetch_assoc();
        $wasMember = true;
        $expiredTier = $mPrev['level'];
        $expiredDate = $mPrev['tanggal_selesai'];
        $expiredDateFmt = $expiredDate ? date('d M Y', strtotime($expiredDate)) : '-';
        $userName = $mPrev['nama_lengkap'];
        $userAddr = $mPrev['alamat'] ?? '';
    } else {
        $uStmt = $conn->prepare("
            SELECT id, nama_lengkap, no_hp, alamat 
            FROM users 
            WHERE (no_hp = ? OR no_hp = ? OR REPLACE(REPLACE(no_hp, '-', ''), ' ', '') = ?)
            ORDER BY id DESC LIMIT 1
        ");
        if ($uStmt) {
            $uStmt->bind_param("sss", $phoneNorm, $altPhone62, $cleanDigits);
            $uStmt->execute();
            $uRes = $uStmt->get_result();
            if ($uRes && $uRes->num_rows > 0) {
                $uRow = $uRes->fetch_assoc();
                $userName = $uRow['nama_lengkap'];
                $userAddr = $uRow['alamat'] ?? '';
            }
        }
    }

    return [
        'is_member' => false,
        'user_id' => null,
        'nama' => $userName,
        'no_hp' => $phoneNorm,
        'alamat' => $userAddr,
        'tier' => null,
        'discount_percent' => 0,
        'poin' => 0,
        'total_quota' => 0,
        'used_quota' => 0,
        'remaining_quota' => 0,
        'sisa_hari' => 0,
        'is_h3' => false,
        'was_member' => $wasMember,
        'expired_tier' => $expiredTier,
        'expired_date' => $expiredDate,
        'expired_date_formatted' => $expiredDateFmt
    ];
}

function getMemberInfo($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE user_id = ? AND status = 'Aktif' AND (tanggal_selesai IS NULL OR tanggal_selesai >= CURDATE())");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateMemberPoints($conn, $user_id, $amount) {
    $stmt = $conn->prepare("SELECT id, total_transaksi FROM members WHERE user_id = ? AND status='Aktif' AND (tanggal_selesai IS NULL OR tanggal_selesai >= CURDATE())");
    if (!$stmt) return;
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) return;

    $new_total = $member['total_transaksi'] + $amount;
    $poin = floor($new_total / 10000); // 1 poin tiap transaksi 10rb

    // Proteksi: Level member berasal dari pengajuan/paket customer, tidak boleh diubah otomatis oleh kalkulasi poin
    $stmt2 = $conn->prepare("UPDATE members SET poin = ?, total_transaksi = ? WHERE id = ?");
    $stmt2->bind_param("idi", $poin, $new_total, $member['id']);
    $stmt2->execute();
}

function timeAgo($datetime) {
    if (!$datetime) return '-';
    $now  = new DateTime();
    $ago  = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->i < 1)     return 'Baru saja';
    if ($diff->i < 60)    return $diff->i . ' menit lalu';
    if ($diff->h < 24)    return $diff->h . ' jam lalu';
    if ($diff->d < 30)    return $diff->d . ' hari lalu';
    return $ago->format('d M Y');
}

// Fonnte WhatsApp API Helper
define('FONNTE_TOKEN', 'YOUR_FONNTE_TOKEN_HERE');

function sendWhatsAppNotification($target, $message) {
    $token = defined('FONNTE_TOKEN') ? FONNTE_TOKEN : '';
    if (empty($token) || $token === 'YOUR_FONNTE_TOKEN_HERE') {
        return false;
    }
    
    $target = preg_replace('/[^0-9]/', '', $target);
    if (substr($target, 0, 1) === '0') {
        $target = '62' . substr($target, 1);
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => array(
        'target' => $target,
        'message' => $message,
        'countryCode' => '62',
      ),
      CURLOPT_HTTPHEADER => array(
        'Authorization: ' . $token
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    
    return $response;
}
?>
