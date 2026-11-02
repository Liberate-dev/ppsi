<?php
// ============================================================
//  api/check_member.php - Lookup membership by WA phone number
//  Mendukung tracker masa aktif, peringatan H-3 & status expired
// ============================================================
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/koneksi.php';

$phone = trim($_REQUEST['no_hp'] ?? $_REQUEST['hp'] ?? '');

if (empty($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'Nomor WhatsApp tidak boleh kosong.',
        'is_member' => false
    ]);
    exit;
}

$member = lookupMemberByPhone($conn, $phone);

echo json_encode([
    'success' => true,
    'is_member' => $member['is_member'],
    'nama' => $member['nama'],
    'no_hp' => $member['no_hp'],
    'alamat' => $member['alamat'] ?? '',
    'tier' => $member['tier'] ?? null,
    'discount_percent' => $member['discount_percent'] ?? 0,
    'poin' => $member['poin'] ?? 0,
    'total_quota' => $member['total_quota'] ?? 0,
    'used_quota' => $member['used_quota'] ?? 0,
    'remaining_quota' => $member['remaining_quota'] ?? 0,
    'tanggal_mulai' => $member['tanggal_mulai'] ?? null,
    'tanggal_selesai' => $member['tanggal_selesai'] ?? null,
    'tanggal_selesai_formatted' => $member['tanggal_selesai_formatted'] ?? null,
    'sisa_hari' => $member['sisa_hari'] ?? 0,
    'is_h3' => $member['is_h3'] ?? false,
    'was_member' => $member['was_member'] ?? false,
    'expired_tier' => $member['expired_tier'] ?? null,
    'expired_date_formatted' => $member['expired_date_formatted'] ?? null
]);
