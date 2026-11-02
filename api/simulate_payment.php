<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';

$kode = trim($_REQUEST['kode'] ?? '');
$redirect = trim($_REQUEST['redirect'] ?? '');
$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_REQUEST['format']) && $_REQUEST['format'] === 'json');

if (empty($kode)) {
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Kode order tidak valid.']);
        exit;
    }
    header('Location: /index.php');
    exit;
}

$success = simulateOrderPayment($conn, $kode);

if ($is_ajax) {
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Simulasi berhasil! Pembayaran pesanan terkonfirmasi LUNAS dan diteruskan ke tahap Cuci.',
            'kode' => $kode
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Gagal memproses simulasi pembayaran.'
        ]);
    }
    exit;
}

if (!empty($redirect)) {
    $sep = strpos($redirect, '?') !== false ? '&' : '?';
    header("Location: {$redirect}{$sep}paid=1&kode=" . urlencode($kode));
    exit;
}

header("Location: /pesan.php?demo_pay=1&kode=" . urlencode($kode));
exit;
