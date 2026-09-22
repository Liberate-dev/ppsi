<?php
require_once __DIR__ . '/includes/koneksi.php';

echo "=== TABLE: documents ===\n";
$r = $conn->query('DESCRIBE documents');
while($row = $r->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | ' . $row['Default'] . "\n";
}

echo "\n=== TABLE: members (status) ===\n";
$r2 = $conn->query("SHOW COLUMNS FROM members LIKE 'status'");
$row2 = $r2->fetch_assoc();
echo $row2['Type'] . "\n";

echo "\n=== TABLE: orders (status) ===\n";
$r3 = $conn->query("SHOW COLUMNS FROM orders LIKE 'status'");
$row3 = $r3->fetch_assoc();
echo $row3['Type'] . "\n";

echo "\nDone.\n";
