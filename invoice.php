<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    die("ID Order tidak ditemukan");
}

$query = "SELECT o.*, m.nama, m.no_hp, m.level, m.poin FROM orders o LEFT JOIN members m ON o.member_id = m.id WHERE o.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Data order tidak ditemukan");
}

$poin_didapat = floor($order['total_bayar'] / 10000);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Invoice #FS-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { background: #F8F9FB; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 2rem 0; }
        .invoice-page { max-width: 900px; width: 100%; }

        .invoice-header-actions { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .page-title { font-size: 28px; font-weight: 800; color: var(--primary-color); margin-bottom: 5px; letter-spacing: -1px; }
        .page-subtitle { font-size: 14px; color: var(--text-secondary); }

        .invoice-card { background: white; border-radius: 24px; padding: 4rem; box-shadow: 0 10px 40px rgba(0,0,0,0.02); margin-bottom: 3rem; }
        
        .ic-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 4rem; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem;}
        .ic-brand { font-size: 28px; font-weight: 800; color: var(--primary-color); }
        .status-badge { background: #E0E7FF; color: var(--accent-color); padding: 6px 16px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }

        .address-row { display: flex; justify-content: space-between; margin-bottom: 3rem; font-size: 13px; color: var(--text-secondary); line-height: 1.6; }
        .address-block strong { color: var(--primary-color); font-size: 14px; display: block; margin-bottom: 5px; font-weight: 700; }
        .label-sm { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-light); margin-bottom: 10px; }

        .meta-row { display: flex; justify-content: space-between; margin-bottom: 4rem; padding-bottom: 3rem; border-bottom: 1px solid var(--border-color); }
        .meta-col { flex: 1; }
        .meta-col .val { font-size: 14px; font-weight: 700; color: var(--primary-color); margin-top: 5px; }

        .inv-table { width: 100%; border-collapse: collapse; margin-bottom: 4rem; }
        .inv-table th { text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-light); padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .inv-table td { padding: 2rem 0; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        
        .item-title { font-weight: 700; color: var(--primary-color); font-size: 15px; margin-bottom: 8px; }
        .item-desc { font-size: 13px; color: var(--text-secondary); line-height: 1.5; max-width: 400px; }

        .inv-footer { display: flex; justify-content: space-between; margin-bottom: 4rem; }
        .pro-note { background: #F8F9FA; padding: 2rem; border-radius: 12px; max-width: 350px; }
        .pro-note-title { font-size: 12px; font-weight: 700; color: var(--primary-color); margin-bottom: 10px; }
        .pro-note-text { font-size: 13px; color: var(--text-secondary); font-style: italic; line-height: 1.6; }

        .summary-block { width: 300px; }
        .sum-row { display: flex; justify-content: space-between; font-size: 13px; color: var(--text-secondary); margin-bottom: 15px; }
        .sum-row.total { font-size: 16px; font-weight: 800; color: var(--primary-color); margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-color); }
        .sum-row.total .val { color: var(--accent-color); font-size: 20px; }

        .thank-you { text-align: center; color: var(--primary-color); }
        .ty-main { font-size: 18px; font-weight: 700; margin-bottom: 10px; }
        .ty-sub { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; color: var(--text-light); }
        .ty-divider { width: 40px; height: 2px; background: var(--accent-color); margin: 2rem auto; }

        .bottom-card { background: white; border-radius: 24px; display: flex; overflow: hidden; margin-bottom: 4rem; box-shadow: 0 10px 40px rgba(0,0,0,0.02); }
        .bc-image { flex: 1; min-height: 300px; position:relative; }
        .bc-image img { position:absolute; width:100%; height:100%; object-fit:cover; }
        .bc-content { flex: 1; padding: 3rem; display: flex; flex-direction: column; justify-content: center; }

        @media print {
            body { background: white; padding: 0; }
            .invoice-page { max-width: 100%; }
            .invoice-header-actions, .bottom-card, footer { display: none !important; }
            .invoice-card { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="invoice-page fade-in">
    <div class="invoice-header-actions">
        <div>
            <h1 class="page-title">Digital Invoice</h1>
            <div class="page-subtitle">Order reference: #FS-<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>-<?= date('y') ?></div>
        </div>
        <div style="display:flex; gap:10px;">
            <button onclick="window.print()" class="btn btn-outline" style="background:white;">🖨️ Print</button>
            <a href="#" class="btn btn-secondary">Download PDF</a>
        </div>
    </div>

    <div class="invoice-card">
        <div class="ic-header">
            <div class="ic-brand">Footstepszs</div>
            <div class="status-badge">STATUS: PAID</div>
        </div>

        <div class="address-row">
            <div class="address-block">
                <div class="label-sm">FROM</div>
                <strong>The Boutique Gallery</strong>
                12 Curator Street, Fashion District<br>
                New York, NY 10012<br>
                concierge@footstepszs.com
            </div>
            <div class="address-block" style="text-align:right;">
                <div class="label-sm">TO</div>
                <strong><?= htmlspecialchars($order['nama'] ?? 'Julianne Beaumont') ?></strong>
                Member: <?= $order['level'] ?? 'Guest' ?><br>
                <?= htmlspecialchars($order['no_hp'] ?? '-') ?><br>
                Loyalty Pts Earned: +<?= number_format($poin_didapat) ?>
            </div>
        </div>

        <div class="meta-row">
            <div class="meta-col">
                <div class="label-sm">INVOICE DATE</div>
                <div class="val"><?= date('M d, Y', strtotime($order['tanggal_masuk'])) ?></div>
            </div>
            <div class="meta-col">
                <div class="label-sm">SERVICE TIER</div>
                <div class="val">Premium Curator</div>
            </div>
            <div class="meta-col">
                <div class="label-sm">DUE DATE</div>
                <div class="val">Upon Receipt</div>
            </div>
            <div class="meta-col">
                <div class="label-sm">PAYMENT MODE</div>
                <div class="val">Visa **** 9921</div>
            </div>
        </div>

        <table class="inv-table">
            <thead>
                <tr>
                    <th>SERVICE DETAILS</th>
                    <th style="text-align:center;">QTY</th>
                    <th style="text-align:right;">AMOUNT</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-title"><?= htmlspecialchars($order['layanan']) ?></div>
                        <div class="item-desc">Full restorative cleaning for Designer <?= htmlspecialchars($order['jenis_barang']) ?> using pH-balanced organic solutions.</div>
                    </td>
                    <td style="text-align:center; font-weight:600; color:var(--primary-color);">01</td>
                    <td style="text-align:right; font-weight:700; color:var(--primary-color);"><?= formatRupiah($order['harga']) ?></td>
                </tr>
            </tbody>
        </table>

        <div class="inv-footer">
            <div class="pro-note">
                <div class="pro-note-title">Professional Note</div>
                <div class="pro-note-text">"Your footwear has been treated with the utmost precision. We recommend avoiding direct sunlight for the first 24 hours to ensure the Hydro-Shield cures perfectly."</div>
            </div>
            <div class="summary-block">
                <div class="sum-row">
                    <span>Subtotal</span>
                    <span style="font-weight:600; color:var(--primary-color);"><?= formatRupiah($order['harga']) ?></span>
                </div>
                <?php if($order['diskon'] > 0): ?>
                <div class="sum-row">
                    <span style="color:var(--success);">Discount (<?= $order['level'] ?>)</span>
                    <span style="color:var(--success);">- <?= formatRupiah($order['diskon']) ?></span>
                </div>
                <?php endif; ?>
                <div class="sum-row total">
                    <span>Total Amount</span>
                    <span class="val"><?= formatRupiah($order['total_bayar']) ?></span>
                </div>
            </div>
        </div>

        <div class="thank-you">
            <div class="ty-main">Thank you for trusting Footstepszs.</div>
            <div class="ty-sub">YOUR SHOES ARE OUR MASTERPIECE.</div>
            <div class="ty-divider"></div>
        </div>
    </div>

    <div class="bottom-card">
        <div class="bc-image">
            <img src="https://images.unsplash.com/photo-1514989940723-e8e51635b782?q=80&w=800&auto=format&fit=crop" alt="The Archival Standard">
        </div>
        <div class="bc-content">
            <h2 style="font-size:20px; font-weight:800; color:var(--primary-color); margin-bottom:1rem;">The Archival Standard</h2>
            <p style="font-size:14px; color:var(--text-secondary); line-height:1.6; margin-bottom:2rem;">Our cleaning process follows museum-grade preservation standards. Every stitch is inspected, and every material is treated with specific chemistry designed to extend its lifecycle without compromising the integrity of the design.</p>
            <div style="display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; color:var(--accent-color);">
                <span style="background:#E0E7FF; width:20px; height:20px; display:inline-flex; align-items:center; justify-content:center; border-radius:50%;">✓</span>
                Lifetime Workmanship Guarantee
            </div>
        </div>
    </div>
</main>

<footer class="site-footer">
    <div class="brand" style="font-size: 16px;">Foot<span class="step" style="color:var(--accent-color); font-family:Georgia, serif; font-size:1.2em; font-style:italic; margin:-4px 1px 0 1px;">Step</span>szs</div>
    <div class="links">
        <a href="#">PRIVACY POLICY</a>
        <a href="#">TERMS OF SERVICE</a>
        <a href="#">SUSTAINABILITY</a>
        <a href="#">CONTACT US</a>
    </div>
    <div>&copy; 2024 FOOTSTEPSZS BOUTIQUE GALLERY. ALL RIGHTS RESERVED.</div>
</footer>

</body>
</html>
