<?php
// Quick test: simulate DMS page access
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role']    = 'admin';
$_SESSION['nama']    = 'Test Admin';

require_once __DIR__ . '/includes/koneksi.php';

// Test generate section
$adminName = $_SESSION['nama'] ?? 'Administrator';
echo "Admin name: $adminName\n";

// Test document query with new filter
$filter_ref = 'tanda_terima';
$sql = "SELECT d.*, u.nama_lengkap as creator FROM documents d LEFT JOIN users u ON d.created_by = u.id";
$where_clauses = [];
if ($filter_ref === 'tanda_terima') {
    $where_clauses[] = "d.doc_subtype = 'tanda_terima'";
} elseif ($filter_ref !== 'all') {
    $ref_val = $conn->real_escape_string($filter_ref === 'others' ? 'general' : $filter_ref);
    $where_clauses[] = "d.ref_type = '$ref_val'";
    $where_clauses[] = "(d.doc_subtype IS NULL OR d.doc_subtype != 'tanda_terima')";
}
if (!empty($where_clauses)) $sql .= ' WHERE ' . implode(' AND ', $where_clauses);
$sql .= " ORDER BY d.id DESC";

echo "SQL: $sql\n";

$res_d = $conn->query($sql);
if (!$res_d) {
    echo "ERROR: " . $conn->error . "\n";
} else {
    $docs = [];
    while($row = $res_d->fetch_assoc()) { $docs[] = $row; }
    echo "Total tanda_terima docs: " . count($docs) . "\n";
}

// Test orders walk-in query
$sqlOrders = "SELECT o.*, u.nama_lengkap, u.no_hp, u.username FROM orders o JOIN users u ON o.user_id=u.id WHERE u.username LIKE 'guest_%' ORDER BY o.id DESC LIMIT 5";
$res = $conn->query($sqlOrders);
$wc = 0;
while($res->fetch_assoc()) { $wc++; }
echo "Walk-in orders found: $wc\n";

echo "\nAll checks passed!\n";
