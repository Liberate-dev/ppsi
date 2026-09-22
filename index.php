<?php
session_start();
require_once __DIR__ . '/includes/koneksi.php';
$pageTitle = 'Beranda';
include __DIR__ . '/includes/header_public.php';
?>

<!-- ========= HERO ========= -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-text">
            <div class="hero-label">✨ Free Pick Up & Delivery — Malang Kota</div>
            <h1 class="hero-title">
                Sepatu Bersih,<br>
                Tampil <span class="accent">Lebih Keren.</span>
            </h1>
            <p class="hero-desc">
                Layanan laundry sepatu premium di Malang. Deep cleaning, unyellowing, hingga repair — kami merawat setiap pasang seperti karya seni.
            </p>
            <div class="hero-btns">
                <a href="/pesan.php" class="btn btn-primary btn-lg">
                    🚀 Pesan Sekarang
                </a>
                <a href="#services" class="btn btn-white btn-lg">
                    Lihat Layanan
                </a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num" data-count="500">0</span>
                    <span class="hero-stat-label">Pelanggan Puas</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num" data-count="2000">0</span>
                    <span class="hero-stat-label">Sepatu Dicuci</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num" data-count="3">0</span>
                    <span class="hero-stat-label">Tahun Pengalaman</span>
                </div>
            </div>
        </div>
        <div class="hero-right">
            <div class="hero-card float-anim">
                <div class="hero-card-icon">🧼</div>
                <div>
                    <div class="hero-card-title">Deep Cleaning</div>
                    <div class="hero-card-desc">Pembersihan menyeluruh dengan formula khusus untuk semua jenis material.</div>
                </div>
            </div>
            <div class="hero-card float-anim" style="animation-delay:0.5s">
                <div class="hero-card-icon">☀️</div>
                <div>
                    <div class="hero-card-title">Unyellowing</div>
                    <div class="hero-card-desc">Kembalikan sol sepatu ke warna aslinya yang icy & bersih.</div>
                </div>
            </div>
            <div class="hero-card float-anim" style="animation-delay:1s">
                <div class="hero-card-icon">🚚</div>
                <div>
                    <div class="hero-card-title">Free Pick Up & Delivery</div>
                    <div class="hero-card-desc">Kami jemput & antar gratis di seluruh area Malang Kota.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= SERVICES ========= -->
<section id="services" class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Layanan Kami</div>
            <h2 class="section-title reveal">Treatment Profesional untuk Sepatumu</h2>
            <p class="section-subtitle reveal" data-delay="100">Kami menggunakan produk & teknik terbaik untuk memastikan setiap sepatu kembali dalam kondisi prima.</p>
        </div>
        <div class="services-grid">
            <div class="service-card reveal" data-delay="100">
                <div class="service-card-img">
                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=600&auto=format&fit=crop" alt="Deep Cleaning Sepatu">
                </div>
                <div class="service-card-body">
                    <div class="service-card-icon">🧼</div>
                    <h3 class="service-card-title">Deep Cleaning</h3>
                    <p class="service-card-desc">Proses pembersihan menyeluruh dari ujung sol hingga lidah sepatu. Menggunakan enzim pH-balanced yang aman untuk suede, leather, canvas, dan mesh.</p>
                    <a href="/pesan.php" class="btn btn-outline btn-sm">Pesan Sekarang →</a>
                </div>
            </div>
            <div class="service-card reveal" data-delay="200">
                <div class="service-card-img">
                    <img src="https://images.unsplash.com/photo-1616406432452-07bc5938759d?q=80&w=600&auto=format&fit=crop" alt="Unyellowing Sepatu">
                </div>
                <div class="service-card-body">
                    <div class="service-card-icon">☀️</div>
                    <h3 class="service-card-title">Unyellowing</h3>
                    <p class="service-card-desc">Kembalikan sol sepatu yang kuning & kusam ke warna icy translucent. Proses UV-cure yang aman & efektif untuk semua jenis sole material.</p>
                    <a href="/pesan.php" class="btn btn-outline btn-sm">Pesan Sekarang →</a>
                </div>
            </div>
            <div class="service-card reveal" data-delay="300">
                <div class="service-card-img">
                    <img src="https://images.unsplash.com/photo-1491553895911-0055eca6402d?q=80&w=600&auto=format&fit=crop" alt="Repair Sepatu">
                </div>
                <div class="service-card-body">
                    <div class="service-card-icon">⚙️</div>
                    <h3 class="service-card-title">Artisan Repair</h3>
                    <p class="service-card-desc">Perbaikan sol lepas, jahitan rusak, hingga penggantian insole. Tim pengrajin kami memastikan sepatu kesayanganmu kembali sempurna.</p>
                    <a href="/pesan.php" class="btn btn-outline btn-sm">Pesan Sekarang →</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= STATS ========= -->
<section class="section section-dark">
    <div class="container">
        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="stat-num" data-count="500">0</div>
                <div class="stat-label">Pelanggan Aktif</div>
            </div>
            <div class="stat-card reveal" data-delay="100">
                <div class="stat-num" data-count="2000">0</div>
                <div class="stat-label">Sepatu Berhasil Dicuci</div>
            </div>
            <div class="stat-card reveal" data-delay="200">
                <div class="stat-num" data-count="98">0</div>
                <div class="stat-label">% Kepuasan Pelanggan</div>
            </div>
            <div class="stat-card reveal" data-delay="300">
                <div class="stat-num" data-count="3">0</div>
                <div class="stat-label">Tahun Pengalaman</div>
            </div>
        </div>
    </div>
</section>

<!-- ========= PRICELIST ========= -->
<section id="pricelist" class="section section-gray">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Harga Terjangkau</div>
            <h2 class="section-title reveal">Pricelist Transparan</h2>
            <p class="section-subtitle reveal" data-delay="100">Harga tetap, tanpa biaya tersembunyi. Sudah termasuk pick up & delivery area Malang Kota.</p>
        </div>
        <div class="pricing-grid">
            <div class="pricing-card reveal" data-delay="100">
                <h3 class="pricing-title">Regular Clean</h3>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">Estimasi selesai 2 hari</div>
                <hr class="pricing-divider">
                <div class="pricing-feature">Sepatu / Sendal — <strong>Rp 25.000</strong></div>
                <div class="pricing-feature">Tas — <strong>Rp 25.000</strong></div>
                <div class="pricing-feature">Topi — <strong>Rp 15.000</strong></div>
                <div class="pricing-feature">Free pick up & delivery</div>
                <div class="pricing-feature">Laporan foto before–after</div>
                <div class="pricing-cta">
                    <a href="/pesan.php" class="btn btn-outline w-100">Pesan Sekarang</a>
                </div>
            </div>
            <div class="pricing-card featured reveal" data-delay="200">
                <div class="pricing-badge">⭐ POPULER</div>
                <h3 class="pricing-title">Fast Clean</h3>
                <div style="font-size:13px;opacity:0.75;margin-bottom:0.75rem;">Estimasi selesai 1 hari</div>
                <div class="pricing-amount">+Rp 10k<span>/item</span></div>
                <hr class="pricing-divider" style="border-color:rgba(255,255,255,0.2)">
                <div class="pricing-feature" style="color:white">Sepatu / Sendal — Rp 35.000</div>
                <div class="pricing-feature" style="color:white">Tas — Rp 35.000</div>
                <div class="pricing-feature" style="color:white">Topi — Rp 25.000</div>
                <div class="pricing-feature" style="color:white">Prioritas antrian khusus</div>
                <div class="pricing-feature" style="color:white">Notifikasi real-time status</div>
                <div class="pricing-cta">
                    <a href="/pesan.php" class="btn btn-white w-100">Pilih Fast Clean</a>
                </div>
            </div>
            <div class="pricing-card reveal" data-delay="300">
                <h3 class="pricing-title">Treatment Add-On</h3>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">Tambahan per item</div>
                <hr class="pricing-divider">
                <div class="pricing-feature">Deep Cleaning — <strong>included</strong></div>
                <div class="pricing-feature">Unyellowing — <strong>+Rp 10.000</strong></div>
                <div class="pricing-feature">Artisan Repair — <strong>+Rp 20.000</strong></div>
                <div class="pricing-feature">Bisa dikombinasikan</div>
                <div class="pricing-feature">Konsultasi treatment gratis</div>
                <div class="pricing-cta">
                    <a href="https://wa.me/6289681276080" target="_blank" class="btn btn-outline w-100">Tanya Dulu</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= MEMBER BULANAN ========= -->
<section id="member" class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Program Hemat</div>
            <h2 class="section-title reveal">Member Bulanan — Makin Banyak Cuci Makin Hemat!</h2>
            <p class="section-subtitle reveal" data-delay="100">Langganan bulanan dengan kuota sepatu & harga super hemat. Cocok untuk kamu yang koleksi sepatunya banyak!</p>
        </div>
        <div class="member-grid">
            <div class="member-card member-bronze reveal" data-delay="100">
                <div style="font-size:2.5rem;margin-bottom:0.5rem;">🥉</div>
                <div class="member-level">BRONZE</div>
                <div class="member-price">160<span>rb/bln</span></div>
                <div class="member-quota">Cuci 4 Sepatu per Bulan</div>
                <div class="member-savings">Hemat Rp 40.000</div>
                <div style="margin-top:1.5rem;">
                    <a href="/register.php" class="btn btn-white btn-sm" style="width:100%;">Daftar Bronze</a>
                </div>
            </div>
            <div class="member-card member-silver reveal" data-delay="200" style="transform:scale(1.05)">
                <div style="font-size:2.5rem;margin-bottom:0.5rem;">🥈</div>
                <div class="member-level">SILVER</div>
                <div class="member-price">300<span>rb/bln</span></div>
                <div class="member-quota">Cuci 8 Sepatu per Bulan</div>
                <div class="member-savings">Hemat Rp 100.000</div>
                <div style="margin-top:1.5rem;">
                    <a href="/register.php" class="btn btn-white btn-sm" style="width:100%;">Daftar Silver</a>
                </div>
            </div>
            <div class="member-card member-gold reveal" data-delay="300">
                <div style="font-size:2.5rem;margin-bottom:0.5rem;">🥇</div>
                <div class="member-level">GOLD</div>
                <div class="member-price">590<span>rb/bln</span></div>
                <div class="member-quota">Cuci 16 Sepatu per Bulan</div>
                <div class="member-savings">Hemat Rp 210.000</div>
                <div style="margin-top:1.5rem;">
                    <a href="/register.php" class="btn btn-white btn-sm" style="width:100%;">Daftar Gold</a>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <p style="color:var(--text-secondary);font-size:14px;">* Member berlaku selama 1 bulan penuh. Daftar & kelola membership langsung dari dashboard pelanggan.</p>
        </div>
    </div>
</section>

<!-- ========= CARA KERJA ========= -->
<section id="cara-kerja" class="section section-gray">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Mudah & Praktis</div>
            <h2 class="section-title reveal">Cara Kerja Footstepszs</h2>
            <p class="section-subtitle reveal" data-delay="100">Hanya 4 langkah dan sepatu kamu sudah bersih kembali!</p>
        </div>
        <div class="steps-grid">
            <div class="step-item reveal" data-delay="100">
                <div class="step-num">1</div>
                <h4 class="step-title">Daftar & Login</h4>
                <p class="step-desc">Buat akun pelanggan Footstepszs secara gratis. Proses cepat, hanya 1 menit.</p>
            </div>
            <div class="step-item reveal" data-delay="200">
                <div class="step-num">2</div>
                <h4 class="step-title">Pilih Layanan & Pesan</h4>
                <p class="step-desc">Pilih jenis cucian, treatment, dan waktu pengerjaan (Regular/Fast Clean).</p>
            </div>
            <div class="step-item reveal" data-delay="300">
                <div class="step-num">3</div>
                <h4 class="step-title">Antar atau Dijemput</h4>
                <p class="step-desc">Tim kami menjemput sepatu di alamatmu. Gratis untuk area Malang Kota!</p>
            </div>
            <div class="step-item reveal" data-delay="400">
                <div class="step-num">4</div>
                <h4 class="step-title">Selesai & Diantar</h4>
                <p class="step-desc">Sepatu bersih kami kembalikan ke alamatmu. Terima notifikasi saat siap!</p>
            </div>
        </div>
    </div>
</section>

<!-- ========= TESTIMONIALS ========= -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Ulasan Pelanggan</div>
            <h2 class="section-title reveal">Apa Kata Mereka?</h2>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card reveal" data-delay="100">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Jordan 1 putih saya yang udah kuning banget, setelah unyellowing di Footstepszs balik jadi icy lagi! Keren banget hasilnya. Highly recommended!"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">👟</div>
                    <div>
                        <div class="testimonial-name">Rizky Aditya</div>
                        <div class="testimonial-role">Member Gold — Malang</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card reveal" data-delay="200">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Pelayanan ramah, pick up & delivery tepat waktu. Sepatuku bersih banget dan wangi. Udah 3 bulan langganan dan ga mau pindah ke tempat lain!"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">👗</div>
                    <div>
                        <div class="testimonial-name">Sinta Maharani</div>
                        <div class="testimonial-role">Member Silver — Malang Kota</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card reveal" data-delay="300">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">"Pertama kali coba, langsung puas! Order via web gampang banget, tinggal pilih treatment dan selesai. Sepatu Adidas Stan Smith ku kaya baru lagi!"</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">⚽</div>
                    <div>
                        <div class="testimonial-name">Budi Santoso</div>
                        <div class="testimonial-role">Pelanggan Setia — Lowokwaru</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= KONTAK ========= -->
<section id="kontak" class="section section-gray">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Hubungi Kami</div>
            <h2 class="section-title reveal">Ada Pertanyaan? Kami Siap Membantu!</h2>
            <p class="section-subtitle reveal" data-delay="100">Hubungi kami via WhatsApp atau kunjungi langsung workshop kami di Malang.</p>
        </div>
        <div class="contact-grid">
            <div class="reveal">
                <div class="contact-info-item">
                    <div class="contact-info-icon">📞</div>
                    <div>
                        <div class="contact-info-label">WhatsApp / Telepon</div>
                        <div class="contact-info-value"><a href="https://wa.me/6289681276080" target="_blank">089681276080 — Carlo</a></div>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="contact-info-icon">📍</div>
                    <div>
                        <div class="contact-info-label">Alamat Workshop</div>
                        <div class="contact-info-value">Jl. Cluster Tigris Raya No 29, Malang</div>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="contact-info-icon">📸</div>
                    <div>
                        <div class="contact-info-label">Instagram</div>
                        <div class="contact-info-value"><a href="https://www.instagram.com/" target="_blank">@footstepszs</a></div>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="contact-info-icon">🕐</div>
                    <div>
                        <div class="contact-info-label">Jam Operasional</div>
                        <div class="contact-info-value">Senin – Sabtu: 08.00 – 20.00 WIB</div>
                    </div>
                </div>
            </div>
            <div class="reveal" data-delay="200">
                <div class="card" style="height:100%;display:flex;flex-direction:column;justify-content:center;gap:1.5rem;text-align:center;padding:3rem;">
                    <div style="font-size:4rem;">💬</div>
                    <h3 style="font-size:1.5rem;">Chat dengan Kami di WhatsApp</h3>
                    <p style="color:var(--text-secondary);">Dapatkan konsultasi gratis tentang treatment terbaik untuk sepatumu. Tim kami siap membantu!</p>
                    <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20mau%20tanya%20tentang%20layanan%20laundry%20sepatu" 
                       target="_blank" class="btn btn-primary btn-lg">
                        💬 Chat WhatsApp
                    </a>
                    <a href="/register.php" class="btn btn-outline">
                        Daftar & Order Online
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= CTA ========= -->
<section class="cta-section">
    <div class="container cta-inner reveal">
        <h2 class="cta-title">Siap Bikin Sepatumu<br>Bersih Lagi? 👟✨</h2>
        <p class="cta-desc">Bergabung dengan ratusan pelanggan yang sudah mempercayakan sepatu kesayangan mereka kepada Footstepszs.</p>
        <div class="hero-btns justify-center">
            <a href="/pesan.php" class="btn btn-primary btn-lg">Daftar Gratis Sekarang</a>
            <a href="https://wa.me/6289681276080" target="_blank" class="btn btn-white btn-lg">💬 Tanya via WhatsApp</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
