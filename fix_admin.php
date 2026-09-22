<?php
// Fix admin password and verify
require_once __DIR__ . '/includes/koneksi.php';
$hash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $conn->prepare("UPDATE users SET password=? WHERE username='admin'");
$stmt->bind_param("s", $hash);
if ($stmt->execute()) {
    echo "Password updated successfully!\n";
    echo "New hash: $hash\n";
    // Verify
    $check = $conn->query("SELECT password FROM users WHERE username='admin'")->fetch_assoc();
    echo "Verify: " . (password_verify('admin123', $check['password']) ? "✅ CORRECT!" : "❌ WRONG!") . "\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
