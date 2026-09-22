<?php
/**
 * templates/invoice_in.php
 * Nota IN (Order Masuk) - Dikirim ke WA saat awal pemesanan
 */
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nota Masuk — <?= htmlspecialchars($order['kode_order']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: #F3F4F6; color: #1F2937; line-height: 1.5; padding: 20px; }
    .nota-container { max-width: 600px; margin: 0 auto; background: #FFF; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); overflow: hidden; border-top: 6px solid #7C3AED; }
    
    .header { padding: 30px; border-bottom: 2px dashed #E5E7EB; text-align: center; }
    .logo { font-size: 28px; font-weight: 800; color: #111827; letter-spacing: -1px; margin-bottom: 5px; }
    .logo span { color: #7C3AED; font-style: italic; }
    .company-info { font-size: 13px; color: #6B7280; }
    
    .title-box { background: #F9FAFB; padding: 15px 30px; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center; }
    .nota-title { font-size: 18px; font-weight: 800; color: #7C3AED; text-transform: uppercase; letter-spacing: 1px; }
    .badge-in { background: #FEF3C7; color: #92400E; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; }
    
    .content { padding: 30px; }
    
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .info-box h4 { font-size: 11px; text-transform: uppercase; color: #9CA3AF; letter-spacing: 0.5px; margin-bottom: 4px; }
    .info-box p { font-size: 14px; font-weight: 600; color: #111827; }
    .info-box p.light { font-weight: 400; font-size: 13px; color: #4B5563; }
    
    .table { width: 100%; border-collapse: collapse; margin-bottom: 25px; font-size: 13px; }
    .table th { background: #F3F4F6; padding: 10px; text-align: left; font-weight: 600; color: #4B5563; border-bottom: 2px solid #E5E7EB; }
    .table td { padding: 12px 10px; border-bottom: 1px solid #E5E7EB; vertical-align: middle; }
    .table td strong { color: #111827; }
    .table tfoot td { font-weight: 700; font-size: 15px; color: #7C3AED; background: #F9FAFB; }
    
    .photo-box { border: 2px dashed #D1D5DB; border-radius: 8px; padding: 15px; text-align: center; margin-bottom: 20px; background: #F9FAFB; }
    .photo-box h4 { font-size: 13px; color: #4B5563; margin-bottom: 10px; }
    .photo-box img { max-width: 100%; max-height: 250px; border-radius: 6px; border: 1px solid #E5E7EB; object-fit: cover; }
    
    .footer { padding: 20px 30px; background: #F9FAFB; border-top: 1px solid #E5E7EB; text-align: center; font-size: 12px; color: #6B7280; }
    
    @media (max-width: 480px) {
        .info-grid { grid-template-columns: 1fr; gap: 15px; }
        .nota-container { border-radius: 0; }
        body { padding: 0; }
    }
    @media print {
        body { background: white; padding: 0; }
        .nota-container { box-shadow: none; border-top: none; max-width: 100%; }
    }
</style>
</head>
<body>

<div class="nota-container">
    <div class="header">
        <div class="logo">Foot<span>Step</span>szs</div>
        <div class="company-info">
            Jl. Cluster Tigris Raya No. 29, Malang<br>
            WA: 089681276080 | IG: @footstepszs
        </div>
    </div>
    
    <div class="title-box">
        <div class="nota-title">NOTA PESANAN</div>
        <div class="badge-in">ORDER MASUK</div>
    </div>
    
    <div class="content">
        <div class="info-grid">
            <div class="info-box">
                <h4>Kode Order</h4>
                <p><?= htmlspecialchars($order['kode_order']) ?></p>
            </div>
            <div class="info-box" style="text-align: right;">
                <h4>Tanggal Masuk</h4>
                <p><?= date('d F Y, H:i', strtotime($order['tanggal_order'])) ?></p>
            </div>
            <div class="info-box">
                <h4>Informasi Pelanggan</h4>
                <p><?= htmlspecialchars($order['nama_lengkap']) ?></p>
                <p class="light"><?= htmlspecialchars($order['no_hp']) ?></p>
            </div>
            <div class="info-box" style="text-align: right;">
                <h4>Layanan & Estimasi</h4>
                <p><?= htmlspecialchars($order['jenis_waktu']) ?></p>
                <p class="light">Selesai: <?= date('d F Y', strtotime($order['tanggal_estimasi'])) ?></p>
            </div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>Item & Treatment</th>
                    <th style="text-align:center">Qty</th>
                    <th style="text-align:right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <strong><?= ucfirst($item['type'] ?? '-') ?></strong><br>
                        <span style="color:#6B7280;font-size:11px"><?= htmlspecialchars($item['treatment'] ?? '-') ?></span>
                    </td>
                    <td style="text-align:center"><?= $item['qty'] ?? 1 ?></td>
                    <td style="text-align:right">Rp <?= number_format($item['subtotal'] ?? 0, 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right">TOTAL PEMBAYARAN</td>
                    <td style="text-align:right">Rp <?= number_format($order['total_harga'], 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
        
        <?php if (!empty($foto_awal_urls) && is_array($foto_awal_urls)): ?>
        <div class="photo-box">
            <h4>📸 Kondisi Awal Saat Diserahkan</h4>
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; margin-top: 10px;">
                <?php foreach ($foto_awal_urls as $idx => $url): ?>
                    <div style="text-align: center;">
                        <img src="<?= htmlspecialchars($url) ?>" alt="Foto Awal <?= $idx+1 ?>" style="width: 120px; height: 120px; object-fit: cover; border-radius: 6px; border: 1px solid #E5E7EB;">
                        <div style="font-size: 10px; color: #6B7280; margin-top: 4px;">Foto #<?= $idx+1 ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php elseif (!empty($foto_awal_url)): ?>
        <div class="photo-box">
            <h4>📸 Kondisi Awal Saat Diserahkan</h4>
            <img src="<?= htmlspecialchars($foto_awal_url) ?>" alt="Foto Awal Sepatu">
        </div>
        <?php endif; ?>        
        <div style="font-size:12px; color:#4B5563; background:#FEF3C7; padding:12px; border-radius:6px; border-left:4px solid #F59E0B;">
            <strong>Catatan:</strong> Pembayaran dilakukan saat barang selesai dan diserahkan/dikirim kembali ke tangan Anda. Simpan nota ini (digital) sebagai bukti pemesanan.
        </div>
    </div>
    
    <div class="footer">
        Diterima oleh sistem otomatis Footstepszs<br>
        Waktu Cetak: <?= date('d M Y, H:i:s') ?>
    </div>
</div>

</body>
</html>
