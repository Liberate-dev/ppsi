<?php
require_once 'includes/koneksi.php';
$res = $conn->query("DESCRIBE documents");
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
