<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/koneksi.php';
if (!isAdmin()) { echo json_encode(['unread' => 0, 'notifications' => []]); exit; }

$unread = getUnreadNotifCount($conn, 0);
$stmt   = $conn->query("SELECT *, DATE_FORMAT(created_at,'%d %b %Y %H:%i') as fmt_time FROM notifications WHERE user_id=0 ORDER BY id DESC LIMIT 15");
$rows = [];
while($row = $stmt->fetch_assoc()) { $rows[] = $row; }

// Add time_ago
foreach ($rows as &$r) { $r['time_ago'] = timeAgo($r['created_at']); }

echo json_encode(['unread' => (int)$unread, 'notifications' => $rows]);
