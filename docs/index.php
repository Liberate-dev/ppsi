<?php
session_start();
require_once __DIR__ . '/../includes/koneksi.php';
$pageTitle = 'Dokumentasi Teknis';
include __DIR__ . '/../includes/header_public.php';
?>

<style>
:root {
 --doc-primary: #6366f1;
 --doc-secondary: #8b5cf6;
 --doc-dark: #0f0f23;
 --doc-card: #1a1a2e;
 --doc-border: rgba(99,102,241,0.2);
 --doc-text: #e2e8f0;
 --doc-muted: #94a3b8;
}

.doc-hero {
 background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%);
 padding: 5rem 0 3rem;
 text-align: center;
 position: relative;
 overflow: hidden;
}
.doc-hero::before {
 content: '';
 position: absolute;
 top: -50%;
 left: -50%;
 width: 200%;
 height: 200%;
 background: radial-gradient(circle at 30% 50%, rgba(99,102,241,0.15) 0%, transparent 50%),
 radial-gradient(circle at 70% 50%, rgba(139,92,246,0.1) 0%, transparent 50%);
 pointer-events: none;
}
.doc-hero-title {
 font-size: clamp(2rem, 5vw, 3.5rem);
 font-weight: 800;
 color: #fff;
 margin-bottom: 1rem;
 position: relative;
}
.doc-hero-title .accent { 
 background: linear-gradient(135deg, #6366f1, #8b5cf6);
 -webkit-background-clip: text;
 -webkit-text-fill-color: transparent;
}
.doc-hero-desc {
 color: var(--doc-muted);
 font-size: 1.1rem;
 max-width: 600px;
 margin: 0 auto 2rem;
 position: relative;
}
.doc-badges {
 display: flex;
 gap: 0.75rem;
 justify-content: center;
 flex-wrap: wrap;
 position: relative;
}
.doc-badge {
 background: rgba(99,102,241,0.2);
 border: 1px solid rgba(99,102,241,0.4);
 color: #a5b4fc;
 padding: 0.4rem 1rem;
 border-radius: 100px;
 font-size: 0.85rem;
 font-weight: 600;
}

.doc-nav {
 background: #fff;
 border-bottom: 1px solid #e5e7eb;
 position: sticky;
 top: 70px;
 z-index: 100;
}
.doc-nav-inner {
 max-width: 1100px;
 margin: 0 auto;
 padding: 0 2rem;
 display: flex;
 gap: 0;
 overflow-x: auto;
 scrollbar-width: none;
}
.doc-nav-inner::-webkit-scrollbar { display: none; }
.doc-nav-link {
 padding: 1rem 1.25rem;
 color: #64748b;
 text-decoration: none;
 font-size: 0.9rem;
 font-weight: 600;
 white-space: nowrap;
 border-bottom: 3px solid transparent;
 transition: all 0.2s;
 display: flex;
 align-items: center;
 gap: 0.5rem;
}
.doc-nav-link:hover { color: #6366f1; border-bottom-color: #c7d2fe; }
.doc-nav-link.active { color: #6366f1; border-bottom-color: #6366f1; }

.doc-body {
 max-width: 1100px;
 margin: 0 auto;
 padding: 3rem 2rem;
 display: grid;
 grid-template-columns: 260px 1fr;
 gap: 2.5rem;
 align-items: start;
}
.doc-sidebar {
 position: sticky;
 top: 130px;
}
.doc-toc {
 background: #f8fafc;
 border: 1px solid #e2e8f0;
 border-radius: 16px;
 padding: 1.5rem;
}
.doc-toc-title {
 font-size: 0.75rem;
 font-weight: 700;
 text-transform: uppercase;
 letter-spacing: 0.1em;
 color: #94a3b8;
 margin-bottom: 1rem;
}
.doc-toc a {
 display: block;
 padding: 0.4rem 0.75rem;
 color: #64748b;
 text-decoration: none;
 font-size: 0.875rem;
 border-radius: 8px;
 transition: all 0.15s;
 margin-bottom: 2px;
}
.doc-toc a:hover { background: #e2e8f0; color: #6366f1; }
.doc-toc a.active { background: #ede9fe; color: #6366f1; font-weight: 600; }

.doc-content { min-width: 0; }

.doc-section {
 margin-bottom: 4rem;
 scroll-margin-top: 140px;
}
.doc-section-title {
 font-size: 1.75rem;
 font-weight: 800;
 color: #0f172a;
 margin-bottom: 0.5rem;
 padding-bottom: 0.75rem;
 border-bottom: 2px solid #e2e8f0;
 display: flex;
 align-items: center;
 gap: 0.75rem;
}
.doc-section-title .icon {
 width: 40px;
 height: 40px;
 background: linear-gradient(135deg, #6366f1, #8b5cf6);
 border-radius: 10px;
 display: flex;
 align-items: center;
 justify-content: center;
 font-size: 1.1rem;
 flex-shrink: 0;
}
.doc-section-sub {
 font-size: 1.1rem;
 font-weight: 700;
 color: #1e293b;
 margin: 1.5rem 0 0.75rem;
}

.doc-table {
 width: 100%;
 border-collapse: collapse;
 font-size: 0.9rem;
 margin-bottom: 1.5rem;
 border-radius: 12px;
 overflow: hidden;
 box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.doc-table th {
 background: linear-gradient(135deg, #6366f1, #8b5cf6);
 color: white;
 padding: 0.875rem 1rem;
 text-align: left;
 font-weight: 700;
 font-size: 0.8rem;
 text-transform: uppercase;
 letter-spacing: 0.05em;
}
.doc-table td {
 padding: 0.75rem 1rem;
 border-bottom: 1px solid #f1f5f9;
 color: #374151;
 vertical-align: top;
}
.doc-table tr:last-child td { border-bottom: none; }
.doc-table tr:nth-child(even) td { background: #f8fafc; }
.doc-table tr:hover td { background: #ede9fe; }
.doc-table code {
 background: #e2e8f0;
 padding: 0.15rem 0.4rem;
 border-radius: 4px;
 font-size: 0.82rem;
 color: #6366f1;
 font-family: 'Courier New', monospace;
}

.doc-code-block {
 background: #0f172a;
 border-radius: 12px;
 overflow: hidden;
 margin: 1rem 0 1.5rem;
 box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}
.doc-code-header {
 background: #1e293b;
 padding: 0.6rem 1rem;
 display: flex;
 justify-content: space-between;
 align-items: center;
 font-size: 0.75rem;
 color: #64748b;
 font-family: monospace;
}
.doc-code-header .lang { color: #a5b4fc; font-weight: 700; }
.doc-code-block pre {
 margin: 0;
 padding: 1.25rem;
 color: #e2e8f0;
 font-size: 0.875rem;
 line-height: 1.7;
 overflow-x: auto;
 font-family: 'Courier New', monospace;
}
.doc-code-block .kw { color: #c084fc; }
.doc-code-block .fn { color: #60a5fa; }
.doc-code-block .str { color: #86efac; }
.doc-code-block .cm { color: #64748b; }
.doc-code-block .num { color: #fb923c; }

.feature-grid {
 display: grid;
 grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
 gap: 1.25rem;
 margin: 1.5rem 0;
}
.feature-card {
 background: linear-gradient(135deg, #f8fafc, #f1f5f9);
 border: 1px solid #e2e8f0;
 border-radius: 14px;
 padding: 1.5rem;
 transition: all 0.2s;
}
.feature-card:hover {
 border-color: #a5b4fc;
 transform: translateY(-2px);
 box-shadow: 0 8px 25px rgba(99,102,241,0.15);
}
.feature-card-icon { font-size: 2rem; margin-bottom: 0.75rem; }
.feature-card-title { font-weight: 700; color: #1e293b; margin-bottom: 0.5rem; }
.feature-card-desc { font-size: 0.875rem; color: #64748b; line-height: 1.6; }

.flow-steps {
 display: flex;
 flex-direction: column;
 gap: 0;
 margin: 1.5rem 0;
}
.flow-step {
 display: flex;
 gap: 1rem;
 align-items: flex-start;
}
.flow-step-connector {
 display: flex;
 flex-direction: column;
 align-items: center;
}
.flow-step-num {
 width: 36px;
 height: 36px;
 background: linear-gradient(135deg, #6366f1, #8b5cf6);
 border-radius: 50%;
 display: flex;
 align-items: center;
 justify-content: center;
 color: white;
 font-weight: 800;
 font-size: 0.9rem;
 flex-shrink: 0;
}
.flow-step-line {
 width: 2px;
 flex: 1;
 min-height: 30px;
 background: linear-gradient(to bottom, #a5b4fc, transparent);
 margin: 4px 0;
}
.flow-step-body {
 padding-bottom: 1.5rem;
 flex: 1;
}
.flow-step-title { font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; }
.flow-step-desc { font-size: 0.875rem; color: #64748b; line-height: 1.6; }

.install-step {
 display: flex;
 gap: 1.25rem;
 align-items: flex-start;
 padding: 1.25rem;
 background: #f8fafc;
 border: 1px solid #e2e8f0;
 border-radius: 12px;
 margin-bottom: 1rem;
}
.install-step-num {
 width: 32px;
 height: 32px;
 background: linear-gradient(135deg, #6366f1, #8b5cf6);
 border-radius: 50%;
 display: flex;
 align-items: center;
 justify-content: center;
 color: white;
 font-weight: 800;
 font-size: 0.85rem;
 flex-shrink: 0;
}
.install-step-title { font-weight: 700; color: #1e293b; margin-bottom: 0.25rem; font-size: 0.95rem; }
.install-step-desc { font-size: 0.85rem; color: #64748b; }

.alert-box {
 padding: 1rem 1.25rem;
 border-radius: 10px;
 margin: 1rem 0;
 display: flex;
 gap: 0.75rem;
 font-size: 0.9rem;
}
.alert-box.warning { background: #fef3c7; border: 1px solid #fbbf24; color: #92400e; }
.alert-box.info { background: #ede9fe; border: 1px solid #a5b4fc; color: #4c1d95; }
.alert-box.success { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; }

.dir-tree {
 background: #0f172a;
 border-radius: 12px;
 padding: 1.5rem;
 font-family: 'Courier New', monospace;
 font-size: 0.85rem;
 color: #94a3b8;
 line-height: 2;
 overflow-x: auto;
}
.dir-tree .dir { color: #60a5fa; font-weight: 700; }
.dir-tree .file { color: #e2e8f0; }
.dir-tree .comment { color: #4a5568; }
.dir-tree .highlight { color: #fbbf24; font-weight: 700; }

@media (max-width: 900px) {
 .doc-body { grid-template-columns: 1fr; }
 .doc-sidebar { display: none; }
}
</style>

<!-- Hero -->
<div class="doc-hero">
 <div class="container">
 <div class="doc-hero-title">📖 Dokumentasi <span class="accent">Teknis</span></div>
 <p class="doc-hero-desc">Panduan lengkap teknologi, database, fungsi, alur program, dan cara instalasi sistem manajemen Footstepszs.</p>
 <div class="doc-badges">
 <span class="doc-badge">PHP 8.0+</span>
 <span class="doc-badge">MySQL</span>
 <span class="doc-badge">XAMPP Ready</span>
 <span class="doc-badge">Fonnte WA API</span>
 <span class="doc-badge">DMS Built-in</span>
 </div>
 </div>
</div>

<!-- Sticky Nav -->
<nav class="doc-nav">
 <div class="doc-nav-inner">
 <a href="#teknologi" class="doc-nav-link active">💻 Teknologi</a>
 <a href="#database" class="doc-nav-link"> Database</a>
 <a href="#struktur" class="doc-nav-link"> Struktur</a>
 <a href="#fungsi" class="doc-nav-link"> Fungsi</a>
 <a href="#alur" class="doc-nav-link"> Alur Program</a>
 <a href="#fitur" class="doc-nav-link">✨ Fitur</a>
 <a href="#instalasi" class="doc-nav-link"> Instalasi</a>
 <a href="#hosting" class="doc-nav-link">🌐 Hosting</a>
 </div>
</nav>

<div class="doc-body">
 <!-- Sidebar TOC -->
 <aside class="doc-sidebar">
 <div class="doc-toc">
 <div class="doc-toc-title">Daftar Isi</div>
 <a href="#teknologi" class="active">💻 Teknologi</a>
 <a href="#database"> Database</a>
 <a href="#struktur"> Struktur Direktori</a>
 <a href="#fungsi"> Fungsi Penting</a>
 <a href="#alur"> Alur Program</a>
 <a href="#fitur">✨ Fitur Unggulan</a>
 <a href="#instalasi"> Instalasi Lokal</a>
 <a href="#hosting">🌐 Hosting</a>
 </div>
 </aside>

 <!-- Main Content -->
 <main class="doc-content">

 <!-- 1. Teknologi -->
 <section class="doc-section" id="teknologi">
 <h2 class="doc-section-title"><div class="icon">💻</div> Teknologi yang Digunakan</h2>
 <table class="doc-table">
 <tr><th>Kategori</th><th>Teknologi</th><th>Keterangan</th></tr>
 <tr><td>Backend</td><td><code>PHP 8.0+</code></td><td>MySQLi, Session, cURL</td></tr>
 <tr><td>Database</td><td><code>MySQL / MariaDB</code></td><td>10.4+ via XAMPP</td></tr>
 <tr><td>Frontend</td><td><code>HTML5, CSS3, JavaScript ES6</code></td><td>Vanilla, tanpa framework tambahan</td></tr>
 <tr><td>Server Lokal</td><td><code>XAMPP</code></td><td>Apache + MySQL bundled</td></tr>
 <tr><td>WA Notifikasi</td><td><code>Fonnte API</code></td><td><a href="https://fonnte.com" target="_blank">fonnte.com</a> REST API gratis</td></tr>
 <tr><td>Upload File</td><td><code>PHP $_FILES</code></td><td>Disimpan di <code>/uploads/documents/</code></td></tr>
 <tr><td>Autentikasi</td><td><code>PHP Session</code></td><td>Role: <code>admin</code> / <code>customer</code></td></tr>
 <tr><td>Dokumen</td><td><code>HTML Generate</code></td><td>Nota digital tersimpan sebagai <code>.html</code></td></tr>
 </table>
 </section>

 <!-- 2. Database -->
 <section class="doc-section" id="database">
 <h2 class="doc-section-title"><div class="icon"></div> Struktur Database</h2>
 <p style="color:#64748b;margin-bottom:1.5rem;">Database bernama <code style="background:#ede9fe;color:#6366f1;padding:0.15rem 0.5rem;border-radius:4px;">footstepszs</code> terdiri dari 6 tabel utama:</p>
 
 <h3 class="doc-section-sub">Tabel <code>users</code> Akun Admin & Customer</h3>
 <table class="doc-table">
 <tr><th>Kolom</th><th>Tipe</th><th>Keterangan</th></tr>
 <tr><td><code>id</code></td><td>INT PK AUTO_INCREMENT</td><td>Primary key</td></tr>
 <tr><td><code>username</code></td><td>VARCHAR(50) UNIQUE</td><td>Username login</td></tr>
 <tr><td><code>email</code></td><td>VARCHAR(100) UNIQUE</td><td>Email unik</td></tr>
 <tr><td><code>password</code></td><td>VARCHAR(255)</td><td>Hash bcrypt</td></tr>
 <tr><td><code>no_hp</code></td><td>VARCHAR(20)</td><td>Nomor WA (untuk notifikasi Fonnte)</td></tr>
 <tr><td><code>role</code></td><td>ENUM('admin','customer')</td><td>Hak akses sistem</td></tr>
 </table>

 <h3 class="doc-section-sub">Tabel <code>orders</code> Data Pesanan</h3>
 <table class="doc-table">
 <tr><th>Kolom</th><th>Tipe</th><th>Keterangan</th></tr>
 <tr><td><code>kode_order</code></td><td>VARCHAR(20) UNIQUE</td><td>e.g. <code>ORD-20250623-001</code></td></tr>
 <tr><td><code>items</code></td><td>TEXT (JSON)</td><td><code>[{type, qty, treatment, harga_satuan, subtotal}]</code></td></tr>
 <tr><td><code>jenis_waktu</code></td><td>ENUM</td><td>Regular (2 hari) / Fast Clean (1 hari)</td></tr>
 <tr><td><code>status</code></td><td>ENUM</td><td>Menunggu → Diterima → Proses → Selesai</td></tr>
 <tr><td><code>total_harga</code></td><td>DECIMAL(15,2)</td><td>Total pembayaran</td></tr>
 <tr><td><code>notif_admin</code></td><td>TINYINT</td><td>0 = belum dibaca admin</td></tr>
 </table>

 <h3 class="doc-section-sub">Tabel <code>members</code> Program Langganan</h3>
 <table class="doc-table">
 <tr><th>Kolom</th><th>Tipe</th><th>Keterangan</th></tr>
 <tr><td><code>level</code></td><td>ENUM('Bronze','Silver','Gold')</td><td>Level keanggotaan</td></tr>
 <tr><td><code>poin</code></td><td>INT</td><td>1 poin per Rp 10.000 transaksi</td></tr>
 <tr><td><code>total_transaksi</code></td><td>DECIMAL(15,2)</td><td>Akumulasi belanja (menentukan level)</td></tr>
 <tr><td><code>tanggal_selesai</code></td><td>DATE</td><td>Akhir masa aktif membership</td></tr>
 </table>

 <h3 class="doc-section-sub">Tabel <code>documents</code> DMS</h3>
 <table class="doc-table">
 <tr><th>Kolom</th><th>Tipe</th><th>Keterangan</th></tr>
 <tr><td><code>ref_type</code></td><td>ENUM</td><td>member / order / keuangan / general</td></tr>
 <tr><td><code>jenis</code></td><td>ENUM('Upload','Generated')</td><td>Upload manual atau generate otomatis</td></tr>
 <tr><td><code>doc_subtype</code></td><td>VARCHAR(50)</td><td>Sub-tipe dokumen (e.g. <code>tanda_terima</code>)</td></tr>
 <tr><td><code>file_path</code></td><td>VARCHAR(500)</td><td>Path relatif file di server</td></tr>
 </table>
 </section>

 <!-- 3. Struktur Direktori -->
 <section class="doc-section" id="struktur">
 <h2 class="doc-section-title"><div class="icon"></div> Struktur Direktori</h2>
 <div class="dir-tree">
 <span class="dir">footstepszs/</span><br>
 ├── <span class="file">index.php</span> <span class="comment"># Landing page publik</span><br>
 ├── <span class="highlight">pesan.php</span> <span class="comment"># Form pemesanan tamu (tanpa login)</span><br>
 ├── <span class="file">login.php</span> <span class="comment"># Halaman login</span><br>
 ├── <span class="highlight">database.sql</span> <span class="comment"># Skema database lengkap</span><br>
 ├── <span class="dir">includes/</span> <span class="comment"># Shared helpers & layout</span><br>
 │ ├── <span class="highlight">koneksi.php</span> <span class="comment"># ⭐ DB + semua helper function</span><br>
 │ ├── <span class="file">admin_header.php</span><br>
 │ └── <span class="file">customer_header.php</span><br>
 ├── <span class="dir">admin/</span> <span class="comment"># Panel admin</span><br>
 │ ├── <span class="highlight">order_detail.php</span><span class="comment"># ⭐ Kelola order, upload TT, kirim WA</span><br>
 │ ├── <span class="file">orders.php</span><br>
 │ ├── <span class="file">dms.php</span> <span class="comment"># Document Management System</span><br>
 │ └── <span class="file">keuangan.php</span><br>
 ├── <span class="dir">customer/</span> <span class="comment"># Dashboard customer</span><br>
 │ ├── <span class="highlight">order.php</span> <span class="comment"># Form pesan (customer login)</span><br>
 │ └── <span class="file">my_orders.php</span> <span class="comment"># Lacak & riwayat pesanan</span><br>
 ├── <span class="dir">templates/</span> <span class="comment"># Template dokumen/nota cetak</span><br>
 │ ├── <span class="file">invoice_in.php</span> <span class="comment"># Nota Masuk (TT-1)</span><br>
 │ └── <span class="file">invoice_out.php</span> <span class="comment"># Nota Keluar + before-after (TT-2)</span><br>
 ├── <span class="dir">api/</span> <span class="comment"># AJAX endpoints</span><br>
 │ └── <span class="file">get_notifications.php</span><br>
 ├── <span class="dir">assets/css/</span> <span class="comment"># Stylesheet</span><br>
 ├── <span class="dir">assets/js/</span> <span class="comment"># JavaScript</span><br>
 ├── <span class="dir">docs/</span> <span class="comment"># Dokumentasi teknis</span><br>
 └── <span class="dir">uploads/documents/</span> <span class="comment"># File upload & dokumen generated</span>
 </div>
 </section>

 <!-- 4. Fungsi Penting -->
 <section class="doc-section" id="fungsi">
 <h2 class="doc-section-title"><div class="icon"></div> Fungsi Penting</h2>
 <p style="color:#64748b;margin-bottom:1.5rem;">Semua fungsi helper tersimpan di <code style="background:#ede9fe;color:#6366f1;padding:0.15rem 0.5rem;border-radius:4px;">includes/koneksi.php</code></p>

 <table class="doc-table">
 <tr><th>Fungsi</th><th>Parameter</th><th>Kegunaan</th></tr>
 <tr><td><code>formatRupiah($angka)</code></td><td>int/float</td><td>Format angka ke "Rp 25.000"</td></tr>
 <tr><td><code>generateOrderCode($conn)</code></td><td>mysqli</td><td>Buat kode unik "ORD-YYYYMMDD-NNN"</td></tr>
 <tr><td><code>getEstimasiSelesai($jenis)</code></td><td>string</td><td>+1 hari Fast Clean / +2 Regular</td></tr>
 <tr><td><code>addNotification($conn, $uid, $pesan, $url)</code></td><td>-</td><td>Simpan notif in-app ke DB</td></tr>
 <tr><td><code>getUnreadNotifCount($conn, $uid)</code></td><td>-</td><td>Hitung notif belum dibaca</td></tr>
 <tr><td><code>isAdmin()</code></td><td>-</td><td>Cek apakah user adalah admin</td></tr>
 <tr><td><code>requireAdmin()</code></td><td>-</td><td>Redirect jika bukan admin</td></tr>
 <tr><td><code>getMemberInfo($conn, $uid)</code></td><td>-</td><td>Ambil data membership aktif</td></tr>
 <tr><td><code>updateMemberPoints($conn, $uid, $amount)</code></td><td>-</td><td>Update poin & level otomatis</td></tr>
 <tr><td><code>sendWhatsAppNotification($target, $msg)</code></td><td>-</td><td>Kirim WA via Fonnte API</td></tr>
 <tr><td><code>timeAgo($datetime)</code></td><td>string</td><td>Format "5 menit lalu" / "2 hari lalu"</td></tr>
 </table>

 <h3 class="doc-section-sub">Contoh: sendWhatsAppNotification</h3>
 <div class="doc-code-block">
 <div class="doc-code-header"><span class="lang">PHP</span><span>includes/koneksi.php</span></div>
 <pre><span class="kw">function</span> <span class="fn">sendWhatsAppNotification</span>($target, $message) {
 $token = <span class="fn">defined</span>(<span class="str">'FONNTE_TOKEN'</span>) ? FONNTE_TOKEN : <span class="str">''</span>;
 <span class="kw">if</span> (<span class="fn">empty</span>($token)) <span class="kw">return false</span>;

 <span class="cm">// Normalisasi: 08xxx → 628xxx</span>
 $target = <span class="fn">preg_replace</span>(<span class="str">'/[^0-9]/'</span>, <span class="str">''</span>, $target);
 <span class="kw">if</span> (<span class="fn">substr</span>($target, <span class="num">0</span>, <span class="num">1</span>) === <span class="str">'0'</span>)
 $target = <span class="str">'62'</span> . <span class="fn">substr</span>($target, <span class="num">1</span>);

 $curl = <span class="fn">curl_init</span>();
 <span class="fn">curl_setopt_array</span>($curl, [
 CURLOPT_URL => <span class="str">'https://api.fonnte.com/send'</span>,
 CURLOPT_POSTFIELDS => [
 <span class="str">'target'</span> => $target,
 <span class="str">'message'</span> => $message,
 ],
 CURLOPT_HTTPHEADER => [<span class="str">'Authorization: '</span> . $token],
 ]);
 <span class="kw">return</span> <span class="fn">curl_exec</span>($curl);
}</pre>
 </div>
 </section>

 <!-- 5. Alur Program -->
 <section class="doc-section" id="alur">
 <h2 class="doc-section-title"><div class="icon"></div> Alur Program</h2>

 <h3 class="doc-section-sub">Alur Pemesanan Customer</h3>
 <div class="flow-steps">
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">1</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Landing Page / Login</div><div class="flow-step-desc">Customer mengakses <code>index.php</code>, lalu daftar atau login. Tamu bisa langsung ke <code>pesan.php</code>.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">2</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Pilih Item & Treatment</div><div class="flow-step-desc">Pilih jenis item (sepatu/tas/topi/sandal), jumlah, treatment, dan jenis waktu. Harga dikalkulasi real-time via JavaScript.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">3</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Upload Foto Kondisi Awal</div><div class="flow-step-desc">Setiap item wajib difoto kondisi awalnya (jumlah input foto = jumlah qty). Preview langsung di browser.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">4</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Submit Order</div><div class="flow-step-desc">Order tersimpan ke tabel <code>orders</code>, foto tersimpan sebagai Tanda Terima 1 di tabel <code>documents</code>, kode order di-generate otomatis.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">5</div></div>
 <div class="flow-step-body"><div class="flow-step-title">Konfirmasi Sukses</div><div class="flow-step-desc">Customer diarahkan ke halaman sukses. Notifikasi baru muncul di panel admin.</div></div>
 </div>
 </div>

 <h3 class="doc-section-sub">Alur Pengerjaan Admin → Notifikasi WA Otomatis</h3>
 <div class="flow-steps">
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">1</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Lihat & Kelola Order</div><div class="flow-step-desc">Admin membuka <code>admin/orders.php</code> dan klik order untuk melihat detailnya.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">2</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Update Status</div><div class="flow-step-desc">Admin ubah status ke <em>Diterima</em> → <em>Proses</em>. Notifikasi WA terkirim otomatis ke customer via Fonnte.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">3</div><div class="flow-step-line"></div></div>
 <div class="flow-step-body"><div class="flow-step-title">Upload Foto Selesai (TT-2)</div><div class="flow-step-desc">Admin upload foto kondisi akhir per item. Sistem men-generate Nota OUT dengan perbandingan foto before-after.</div></div>
 </div>
 <div class="flow-step">
 <div class="flow-step-connector"><div class="flow-step-num">4</div></div>
 <div class="flow-step-body"><div class="flow-step-title">Selesai Otomatis + Kirim Nota</div><div class="flow-step-desc">Status otomatis berubah ke <em>Selesai</em>, pemasukan dicatat di keuangan, poin member diupdate, dan WA berisi link Nota OUT terkirim ke customer.</div></div>
 </div>
 </div>
 </section>

 <!-- 6. Fitur Unggulan -->
 <section class="doc-section" id="fitur">
 <h2 class="doc-section-title"><div class="icon">✨</div> Fitur Unggulan</h2>
 <div class="feature-grid">
 <div class="feature-card">
 <div class="feature-card-icon">📸</div>
 <div class="feature-card-title">Multi-Item + Foto Per Item</div>
 <div class="feature-card-desc">Pesan beberapa item sekaligus, upload foto kondisi awal per item, preview real-time langsung di form.</div>
 </div>
 <div class="feature-card">
 <div class="feature-card-icon">📲</div>
 <div class="feature-card-title">WA Otomatis via Fonnte</div>
 <div class="feature-card-desc">Notifikasi WA terkirim otomatis saat status berubah dan saat order selesai termasuk link Nota OUT.</div>
 </div>
 <div class="feature-card">
 <div class="feature-card-icon"></div>
 <div class="feature-card-title">Nota Digital (IN & OUT)</div>
 <div class="feature-card-desc">Generate Nota Masuk dan Nota Selesai dengan perbandingan foto before-after secara otomatis.</div>
 </div>
 <div class="feature-card">
 <div class="feature-card-icon">💎</div>
 <div class="feature-card-title">Membership Berjenjang</div>
 <div class="feature-card-desc">Bronze / Silver / Gold dengan poin reward. Level naik otomatis berdasarkan total transaksi.</div>
 </div>
 <div class="feature-card">
 <div class="feature-card-icon"></div>
 <div class="feature-card-title">DMS Terpadu</div>
 <div class="feature-card-desc">Kelola semua dokumen bisnis dalam satu tempat. Generate laporan keuangan dan order otomatis.</div>
 </div>
 <div class="feature-card">
 <div class="feature-card-icon"></div>
 <div class="feature-card-title">Keuangan Otomatis</div>
 <div class="feature-card-desc">Setiap order selesai langsung tercatat sebagai pemasukan. Laporan per bulan tersedia.</div>
 </div>
 </div>
 </section>

 <!-- 7. Instalasi -->
 <section class="doc-section" id="instalasi">
 <h2 class="doc-section-title"><div class="icon"></div> Cara Instalasi (Localhost XAMPP)</h2>

 <div class="install-step">
 <div class="install-step-num">1</div>
 <div>
 <div class="install-step-title">Salin project ke folder htdocs</div>
 <div class="install-step-desc">Ekstrak atau salin folder <code>footstepszs</code> ke <code>C:\xampp\htdocs\footstepszs\</code></div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">2</div>
 <div>
 <div class="install-step-title">Buat Database di phpMyAdmin</div>
 <div class="install-step-desc">Buka <code>http://localhost/phpmyadmin</code> → Buat database <code>footstepszs</code> → Import file <code>database.sql</code></div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">3</div>
 <div>
 <div class="install-step-title">Konfigurasi Koneksi DB</div>
 <div class="install-step-desc">Edit <code>includes/koneksi.php</code> sesuaikan username & password MySQL Anda.</div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">4</div>
 <div>
 <div class="install-step-title">Set Token Fonnte WA</div>
 <div class="install-step-desc">Di <code>includes/koneksi.php</code> baris 123: <code>define('FONNTE_TOKEN', 'TOKEN_ANDA');</code> Daftar di <a href="https://fonnte.com" target="_blank">fonnte.com</a></div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">5</div>
 <div>
 <div class="install-step-title">Jalankan XAMPP & Akses</div>
 <div class="install-step-desc">Start Apache & MySQL di XAMPP Control Panel → buka <code>http://localhost/</code></div>
 </div>
 </div>

 <div class="alert-box warning">
 <span>⚠️</span>
 <span><strong>Login Admin Default:</strong> Username: <code>admin</code> | Password: <code>admin123</code> Segera ganti setelah instalasi!</span>
 </div>
 <div class="alert-box info">
 <span></span>
 <span>Jika token Fonnte belum diisi, sistem akan menampilkan tombol WA Web manual sebagai fallback pesanan tetap bisa diproses.</span>
 </div>
 </section>

 <!-- 8. Hosting -->
 <section class="doc-section" id="hosting">
 <h2 class="doc-section-title"><div class="icon">🌐</div> Cara Hosting (Shared Hosting / VPS)</h2>

 <h3 class="doc-section-sub">Shared Hosting (cPanel)</h3>
 <div class="install-step">
 <div class="install-step-num">1</div>
 <div>
 <div class="install-step-title">Upload File via File Manager</div>
 <div class="install-step-desc">Zip folder <code>footstepszs</code>, upload ke <code>public_html/</code>, dan ekstrak.</div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">2</div>
 <div>
 <div class="install-step-title">Import Database</div>
 <div class="install-step-desc">cPanel → MySQL Databases → buat DB baru → phpMyAdmin → Import <code>database.sql</code></div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">3</div>
 <div>
 <div class="install-step-title">Update Konfigurasi DB & URL</div>
 <div class="install-step-desc">Edit <code>includes/koneksi.php</code> dengan kredensial hosting. Ganti semua <code>/</code> ke <code>/</code> jika di root domain.</div>
 </div>
 </div>
 <div class="install-step">
 <div class="install-step-num">4</div>
 <div>
 <div class="install-step-title">Set Permission Folder Upload</div>
 <div class="install-step-desc">Set izin folder <code>uploads/</code> dan <code>uploads/documents/</code> ke <code>755</code> via File Manager.</div>
 </div>
 </div>

 <div class="doc-code-block">
 <div class="doc-code-header"><span class="lang">BASH</span><span>VPS Ubuntu + Apache</span></div>
 <pre><span class="cm"># Install dependencies</span>
sudo apt install php8.1 php8.1-mysql php8.1-curl mysql-server apache2

<span class="cm"># Import database</span>
mysql -u root -p footstepszs < database.sql

<span class="cm"># Set permissions</span>
sudo chown -R www-data:www-data /var/www/html/uploads/
sudo chmod -R 755 /var/www/html/uploads/</pre>
 </div>

 <div class="alert-box success">
 <span></span>
 <span><strong>Checklist Go-Live:</strong> Token Fonnte ✓ · Kredensial DB ✓ · Password admin diganti ✓ · Folder uploads writable ✓ · HTTPS aktif ✓</span>
 </div>
 </section>

 </main>
</div>

<script>
// Active section tracking for sidebar & nav
const sections = document.querySelectorAll('.doc-section');
const tocLinks = document.querySelectorAll('.doc-toc a');
const navLinks = document.querySelectorAll('.doc-nav-link');

const observer = new IntersectionObserver((entries) => {
 entries.forEach(entry => {
 if (entry.isIntersecting) {
 const id = entry.target.id;
 tocLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
 navLinks.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + id));
 }
 });
}, { rootMargin: '-30% 0px -60% 0px' });

sections.forEach(s => observer.observe(s));
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
