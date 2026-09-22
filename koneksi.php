<?php
// koneksi.php
$host = "localhost";
$user = "mcsd8819_carloo";
$pass = "carlo89898989Aa_";
$db   = "mcsd8819_footsteps";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Fungsi helper format angka Rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi update poin & membership level
function updateMembership($conn, $member_id) {
    // Hitung total transaksi dari pesanan yang berstatus 'Selesai' (opsional, atau semua status)
    // Untuk ini kita hitung semua yang sudah masuk
    $query = "SELECT SUM(total_bayar) as total FROM orders WHERE member_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $total_transaksi = $row['total'] ? $row['total'] : 0;

    // Hitung Poin (1 poin per 10.000)
    $poin = floor($total_transaksi / 10000);

    // Tentukan Level
    $level = 'Silver';
    if ($total_transaksi > 1000000) {
        $level = 'Platinum';
    } elseif ($total_transaksi > 500000) {
        $level = 'Gold';
    }

    // Update member record
    $update_query = "UPDATE members SET poin = ?, level = ?, total_transaksi = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("isdi", $poin, $level, $total_transaksi, $member_id);
    $update_stmt->execute();
}
?>
