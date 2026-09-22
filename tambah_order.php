<?php
session_start();
require_once 'koneksi.php';

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $member_id = $_POST['member_id'];
    $layanan = $_POST['layanan'];
    $jenis_barang = $_POST['jenis_barang'];
    $harga = floatval($_POST['harga']);
    $tanggal_masuk = date('Y-m-d');
    $status = 'Proses';
    
    // Get member details to apply discount logic
    $stmt = $conn->prepare("SELECT level FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();
    
    $diskon = 0;
    if ($member) {
        if ($member['level'] == 'Platinum') {
            $diskon = $harga * 0.10; // 10%
        } elseif ($member['level'] == 'Gold') {
            $diskon = $harga * 0.05; // 5%
        }
    }
    
    $total_bayar = $harga - $diskon;
    
    $insert_stmt = $conn->prepare("INSERT INTO orders (member_id, layanan, jenis_barang, harga, diskon, total_bayar, tanggal_masuk, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $insert_stmt->bind_param("issdddss", $member_id, $layanan, $jenis_barang, $harga, $diskon, $total_bayar, $tanggal_masuk, $status);
    
    if ($insert_stmt->execute()) {
        updateMembership($conn, $member_id);
        header("Location: order.php");
        exit;
    } else {
        $error = "Gagal membuat order baru!";
    }
}

$members = $conn->query("SELECT id, nama, no_hp, level FROM members ORDER BY nama ASC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Service Intake - Footstepszs</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .intake-layout { display: flex; gap: 4rem; margin-top: 1rem; }
        .intake-left { flex: 0.8; }
        .intake-right { flex: 1.2; background: #F4F5F7; border-radius: 24px; padding: 3rem; }
        
        .portal-label { font-size: 11px; font-weight: 700; color: var(--accent-color); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem; }
        .intake-image-card { background: var(--primary-color); border-radius: 24px; overflow: hidden; position: relative; height: 600px; display: flex; flex-direction: column; justify-content: flex-end; margin-top: 2rem;}
        .intake-image-card img { position: absolute; top:0; left:0; width: 100%; height: 100%; object-fit: cover; opacity: 0.8; }
        .intake-caption { position: relative; z-index: 1; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); color: white; margin: 2rem; padding: 1.5rem; border-radius: 12px; font-size: 13px; line-height: 1.6; }
        
        .form-row { display: flex; gap: 3rem; margin-bottom: 2.5rem; }
        .form-col { flex: 1; }
        
        /* Modern Select styling */
        select.form-control { appearance: none; background: url('data:image/svg+xml;utf8,<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>') no-repeat right 10px center; background-size: 16px; background-color: transparent; }
        
        /* Priority Box */
        .priority-box { background: #EEF2FF; padding: 1.5rem; border-radius: 12px; display: flex; align-items: center; gap: 1rem; margin-bottom: 2rem; }
        .pb-icon { background: var(--accent-color); color: white; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    </style>
</head>
<body>

<?php include 'top_navbar.php'; ?>

<main class="container main-content fade-in">
    <div class="portal-label">Boutique Service Portal</div>
    <h1 class="page-title">New Service Intake</h1>
    <p class="page-subtitle">Transforming restoration into an art form. Please provide the details of the item for our curators.</p>

    <div class="intake-layout">
        <div class="intake-left">
            <div class="intake-image-card">
                <img src="https://images.unsplash.com/photo-1549298916-b41d501d3772?q=80&w=800&auto=format&fit=crop" alt="Premium Care">
                <div class="intake-caption">
                    "Precision is our signature. Every item is treated with gallery-level care."
                </div>
            </div>
        </div>

        <div class="intake-right">
            <?php if(isset($error)): ?>
                <div style="background:#FEE2E2; color:#991B1B; padding:1rem; border-radius:8px; margin-bottom:1.5rem; font-size:14px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label">Customer Name</label>
                        <select name="member_id" class="form-control" required style="font-weight: 500;">
                            <option value="">Select Customer...</option>
                            <?php while($m = $members->fetch_assoc()): ?>
                                <option value="<?= $m['id'] ?>">
                                    <?= htmlspecialchars($m['nama']) ?> (<?= $m['level'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Price Quote (IDR)</label>
                        <div style="display:flex; align-items:center;">
                            <span style="font-size:16px; font-weight:600; margin-right:10px;">Rp</span>
                            <input type="number" name="harga" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-col">
                        <label class="form-label" style="margin-bottom: 1.5rem;">Item Category</label>
                        <div class="radio-group" style="grid-template-columns: 1fr 1fr; gap:0.8rem;">
                            <label class="radio-card">
                                <input type="radio" name="jenis_barang" value="Sepatu" required>
                                <span class="label-content">Shoes</span>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="jenis_barang" value="Tas">
                                <span class="label-content">Bag</span>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="jenis_barang" value="Topi">
                                <span class="label-content">Hat</span>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="jenis_barang" value="Sandal">
                                <span class="label-content">Sandals</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-col">
                        <label class="form-label">Service Type</label>
                        <select name="layanan" class="form-control" required style="background-color: white; padding: 1rem 1.5rem; border-radius: 8px; border:none; margin-top: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
                            <option value="">Select restoration type</option>
                            <option value="Deep Cleaning">Deep Cleaning</option>
                            <option value="Repair Shoes">Artisan Repair</option>
                            <option value="Unyellowing">Unyellowing</option>
                            <option value="Bersihkan Jamur">Mould Removal</option>
                        </select>

                        <div class="info-box" style="margin-top: 1.5rem;">
                            <i>i</i>
                            <div>All services include a complimentary moisture protection finish and aromatic treatment.</div>
                        </div>
                    </div>
                </div>

                <div style="display:flex; align-items:flex-end; justify-content:space-between; margin-top:3rem;">
                    <div class="priority-box" style="margin:0; width: 60%;">
                        <div class="pb-icon">✓</div>
                        <div>
                            <div style="font-size:14px; font-weight:700;">Priority Handling</div>
                            <div style="font-size:12px; color:var(--text-secondary); margin-top:2px;">Estimated completion: 3-5 days</div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" style="padding: 1.2rem 2rem; border-radius:12px; font-size:16px;">Create Order &rarr;</button>
                </div>
            </form>
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
