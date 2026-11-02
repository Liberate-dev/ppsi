<?php
// ============================================================
//  cron/member_tracker.php - Auto-tracker Masa Aktif & Reminder H-3
// ============================================================
require_once __DIR__ . '/../includes/koneksi.php';

function runMemberTracker($conn) {
    $today = date('Y-m-d');
    
    // 1. Otomatisasi: Nonaktifkan member yang sudah melewati tanggal_selesai
    $expired_stmt = $conn->query("
        SELECT m.id, m.user_id, m.level, m.tanggal_selesai, u.nama_lengkap, u.no_hp 
        FROM members m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.tanggal_selesai IS NOT NULL 
          AND m.tanggal_selesai < '$today' 
          AND m.status = 'Aktif'
    ");
    
    $expired_count = 0;
    $expired_members = [];

    if ($expired_stmt && $expired_stmt->num_rows > 0) {
        while ($m = $expired_stmt->fetch_assoc()) {
            $mid = (int)$m['id'];
            $uid = (int)$m['user_id'];
            $level = $m['level'];
            $tgl_selesai = date('d M Y', strtotime($m['tanggal_selesai']));
            $hp = $m['no_hp'];
            $nama = $m['nama_lengkap'];

            // Update status ke Nonaktif
            $conn->query("UPDATE members SET status = 'Nonaktif' WHERE id = $mid");
            $expired_count++;
            $expired_members[] = $nama . " ($level)";

            // Notifikasi in-app
            addNotification($conn, $uid, "Masa aktif keanggotaan paket {$level} Anda telah berakhir pada {$tgl_selesai}.", "/customer/membership.php");
            addNotification($conn, 0, "Member Expired: Paket {$level} customer {$nama} telah otomatis dinonaktifkan.", "/admin/members.php");

            // Notifikasi WA
            if ($hp) {
                $msg = "Halo {$nama},\n\nMasa aktif keanggotaan paket *{$level} Member* Anda di Footstepszs telah *berakhir pada {$tgl_selesai}*.\n\nKeuntungan kuota cuci bulanan dan diskon perawatan saat ini dinonaktifkan. Anda dapat memperpanjang kembali paket kapan saja melalui website atau hubungi WhatsApp kami.\n\nTerima kasih telah berlangganan di Footstepszs! 🙏";
                sendWhatsAppNotification($hp, $msg);
            }
        }
    }

    // 2. Reminder H-3: Member aktif dengan sisa masa aktif 1 - 3 hari
    $h3_stmt = $conn->query("
        SELECT m.id, m.user_id, m.level, m.tanggal_selesai, 
               DATEDIFF(m.tanggal_selesai, '$today') AS sisa_hari,
               u.nama_lengkap, u.no_hp 
        FROM members m 
        JOIN users u ON m.user_id = u.id 
        WHERE m.status = 'Aktif' 
          AND m.tanggal_selesai IS NOT NULL 
          AND DATEDIFF(m.tanggal_selesai, '$today') BETWEEN 1 AND 3
    ");

    $reminder_count = 0;
    $reminded_members = [];

    if ($h3_stmt && $h3_stmt->num_rows > 0) {
        while ($m = $h3_stmt->fetch_assoc()) {
            $uid = (int)$m['user_id'];
            $level = $m['level'];
            $sisa_hari = (int)$m['sisa_hari'];
            $tgl_selesai = date('d M Y', strtotime($m['tanggal_selesai']));
            $hp = $m['no_hp'];
            $nama = $m['nama_lengkap'];

            $reminder_count++;
            $reminded_members[] = "$nama ($level - H-$sisa_hari)";

            // Notifikasi in-app
            addNotification($conn, $uid, "⚠️ Peringatan H-{$sisa_hari}: Masa aktif paket {$level} Anda tersisa {$sisa_hari} hari lagi (s/d {$tgl_selesai}).", "/customer/membership.php");

            // Notifikasi WA Reminder
            if ($hp) {
                $msg = "Halo {$nama},\n\nKami ingin menginformasikan bahwa masa aktif paket *{$level} Member* Anda di Footstepszs tersisa *{$sisa_hari} hari lagi* (berakhir pada {$tgl_selesai}).\n\nAgar kuota cuci gratis dan potongan diskon tidak terhenti, yuk lakukan perpanjangan paket sebelum tanggal tersebut melalui website kami.\n\nInfo & Perpanjangan: https://wa.me/6289681276080\n\nTerima kasih!";
                sendWhatsAppNotification($hp, $msg);
            }
        }
    }

    return [
        'status' => 'success',
        'executed_at' => date('Y-m-d H:i:s'),
        'expired_count' => $expired_count,
        'expired_members' => $expired_members,
        'reminder_count' => $reminder_count,
        'reminded_members' => $reminded_members
    ];
}

if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $res = runMemberTracker($conn);
    if (php_sapi_name() === 'cli') {
        echo "Member Tracker Run at " . $res['executed_at'] . "\n";
        echo "Nonaktifkan (Expired): " . $res['expired_count'] . " member (" . implode(', ', $res['expired_members']) . ")\n";
        echo "Terkirim Reminder (H-3): " . $res['reminder_count'] . " member (" . implode(', ', $res['reminded_members']) . ")\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($res, JSON_PRETTY_PRINT);
    }
}
