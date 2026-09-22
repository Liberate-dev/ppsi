<?php
// ============================================================
//  includes/koneksi.php — Database Connection & Helpers
// ============================================================
$host = "localhost";
$user = "mcsd8819_carloo";
$pass = "carlo89898989Aa_";
$db   = "mcsd8819_footsteps";

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:2rem;color:red;'>
        <h2>⚠️ Database Connection Error</h2>
        <p>" . $conn->connect_error . "</p>
        <p>Pastikan XAMPP MySQL sudah berjalan dan database <strong>footstepszs</strong> sudah dibuat via phpMyAdmin dengan mengimpor file <code>database.sql</code>.</p>
    </div>");
}

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

function getMemberInfo($conn, $user_id) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE user_id = ? AND status = 'Aktif' AND tanggal_selesai >= CURDATE()");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function updateMemberPoints($conn, $user_id, $amount) {
    // Check if active member exists
    $stmt = $conn->prepare("SELECT id, total_transaksi FROM members WHERE user_id = ? AND status='Aktif' AND tanggal_selesai >= CURDATE()");
    if (!$stmt) return; // Prevent fatal error if members table does not exist
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    if (!$member) return;

    $new_total = $member['total_transaksi'] + $amount;
    $poin = floor($new_total / 10000); // 1 point per 10k transaction
    $level = 'Bronze';
    if ($new_total >= 500000) $level = 'Gold';
    elseif ($new_total >= 200000) $level = 'Silver';

    $stmt2 = $conn->prepare("UPDATE members SET poin = ?, level = ?, total_transaksi = ? WHERE id = ?");
    $stmt2->bind_param("isdi", $poin, $level, $new_total, $member['id']);
    $stmt2->execute();
}

function timeAgo($datetime) {
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
define('FONNTE_TOKEN', 'YOUR_FONNTE_TOKEN_HERE'); // Ganti dengan token Fonnte Anda

function sendWhatsAppNotification($target, $message) {
    $token = defined('FONNTE_TOKEN') ? FONNTE_TOKEN : '';
    if (empty($token) || $token === 'YOUR_FONNTE_TOKEN_HERE') {
        return false;
    }
    
    // Normalize target number
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
