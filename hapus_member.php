<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'] ?? null;
if ($id) {
    // Delete handling. 
    // Karena orders member_id adalah SET NULL pada saat delete referensi, maka orders tidak akan hilang,
    // tapi member_id nya jadi NULL.
    $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header("Location: member.php");
exit;
?>
