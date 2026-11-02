<?php
// ============================================================
//  koneksi.php — Root Bridge to includes/koneksi.php
// ============================================================
require_once __DIR__ . '/includes/koneksi.php';

if (!function_exists('updateMembership')) {
    function updateMembership($conn, $member_id) {
        $stmt = $conn->prepare("SELECT user_id, total_transaksi FROM members WHERE id = ?");
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            updateMemberPoints($conn, $row['user_id'], 0);
        }
    }
}
?>