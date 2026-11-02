<?php
// ============================================================
//  cron/auto_cancel.php - Auto-Cancel Expired Orders (> 24 Jam)
// ============================================================
require_once __DIR__ . '/../includes/koneksi.php';

function runAutoCancelScheduler($conn) {
    $now = date('Y-m-d H:i:s');
    
    // Cari order Pending Payment yang melewati deadline atau > 24 jam
    $sql = "
        SELECT o.*, u.nama_lengkap, u.no_hp as user_hp 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE (o.status = 'Pending Payment' OR (o.metode_pembayaran IN ('Transfer Bank', 'QRIS') AND o.status_pembayaran = 'Belum Lunas' AND o.status NOT IN ('Selesai', 'Canceled', 'Dibatalkan')))
          AND (
                (o.payment_deadline IS NOT NULL AND o.payment_deadline <= '$now')
                OR 
                (o.tanggal_order <= DATE_SUB('$now', INTERVAL 24 HOUR))
              )
    ";

    $result = $conn->query($sql);
    $canceled_count = 0;
    $canceled_orders = [];

    if ($result && $result->num_rows > 0) {
        while ($order = $result->fetch_assoc()) {
            $order_id = (int)$order['id'];
            $kode = $order['kode_order'];
            $cust_name = $order['nama_lengkap'];
            $cust_hp = $order['no_hp'] ?: $order['user_hp'];
            $reason = "Batas waktu pembayaran (24 jam) telah berakhir.";

            // Update status ke Canceled
            $upd = $conn->prepare("
                UPDATE orders 
                SET status = 'Canceled', 
                    cancel_reason = ?, 
                    canceled_at = NOW() 
                WHERE id = ?
            ");
            $upd->bind_param("si", $reason, $order_id);
            if ($upd->execute()) {
                $canceled_count++;
                $canceled_orders[] = $kode;

                // Log notifikasi in-app
                addNotification($conn, $order['user_id'], 
                    "Pesanan {$kode} otomatis dibatalkan karena tidak ada konfirmasi pembayaran dalam 24 jam.", 
                    "/customer/my_orders.php"
                );
                addNotification($conn, 0, 
                    "Auto-Cancel: Pesanan {$kode} ({$cust_name}) dibatalkan otomatis oleh sistem (timeout 24 jam).", 
                    "/admin/order_detail.php?id={$order_id}"
                );

                // Kirim notifikasi WA pembatalan
                $wa_msg = "Halo {$cust_name},\n\nMohon maaf, pesanan Anda dengan kode *{$kode}* di Footstepszs telah otomatis dibatalkan oleh sistem karena melewati batas waktu pembayaran 24 jam tanpa konfirmasi.\n\nJika Anda masih ingin melakukan perawatan sepatu, silakan buat pesanan baru melalui website kami. Terima kasih!";
                sendWhatsAppNotification($cust_hp, $wa_msg);
            }
        }
    }

    return [
        'status' => 'success',
        'canceled_count' => $canceled_count,
        'canceled_orders' => $canceled_orders,
        'executed_at' => $now
    ];
}

// Check execution context: CLI or HTTP
if (php_sapi_name() === 'cli' || isset($_GET['run'])) {
    $res = runAutoCancelScheduler($conn);
    if (php_sapi_name() === 'cli') {
        echo "Auto-Cancel Run at " . $res['executed_at'] . "\n";
        echo "Canceled " . $res['canceled_count'] . " orders: " . implode(', ', $res['canceled_orders']) . "\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode($res);
    }
}
