<?php
session_start();
require_once __DIR__ . '/includes/koneksi.php';
$pageTitle = 'Perawatan Sepatu Premium Malang';
include __DIR__ . '/includes/header_public.php';
?>

<!-- ========= HERO ========= -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-text">
            <div class="hero-label">Antar Jemput Gratis - Area Malang Kota</div>
            <h1 class="hero-title">
                Perawatan Sepatu Presisi.<br>
                Bersih <span class="accent">Maksimal.</span>
            </h1>
            <p class="hero-desc">
                Layanan laundry dan restorasi sepatu profesional di Malang. Deep cleaning menyeluruh, unyellowing bergaransi, hingga perbaikan artisan untuk menjaga koleksi terbaik Anda.
            </p>
            <div class="hero-btns">
                <a href="/pesan.php" class="btn btn-primary btn-lg">
                    Pesan Layanan
                </a>
                <a href="#services" class="btn btn-white btn-lg">
                    Eksplor Layanan
                </a>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="hero-stat-num" data-count="500">0</span>
                    <span class="hero-stat-label">Pelanggan Aktif</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num" data-count="2000">0</span>
                    <span class="hero-stat-label">Sepatu Dikerjakan</span>
                </div>
                <div class="hero-stat">
                    <span class="hero-stat-num" data-count="3">0</span>
                    <span class="hero-stat-label">Tahun Dedikasi</span>
                </div>
            </div>
        </div>
        <div class="hero-right">
            <div class="hero-card float-anim">
                <div class="hero-card-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div>
                    <div class="hero-card-title">Deep Cleaning</div>
                    <div class="hero-card-desc">Formula pembersih pH-balanced khusus untuk suede, leather, canvas, dan mesh.</div>
                </div>
            </div>
            <div class="hero-card float-anim" style="animation-delay:0.4s">
                <div class="hero-card-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                </div>
                <div>
                    <div class="hero-card-title">Unyellowing Bergaransi</div>
                    <div class="hero-card-desc">Mengembalikan sol sepatu yang menguning ke warna cerah dan bersih alami.</div>
                </div>
            </div>
            <div class="hero-card float-anim" style="animation-delay:0.8s">
                <div class="hero-card-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                </div>
                <div>
                    <div class="hero-card-title">Free Pick Up & Delivery</div>
                    <div class="hero-card-desc">Layanan jemput dan antar gratis tanpa minimum pesanan di area Malang Kota.</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= SERVICES ========= -->
<section id="services" class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Spesialisasi</div>
            <h2 class="section-title reveal">Treatment Profesional untuk Setiap Material</h2>
            <p class="section-subtitle reveal" data-delay="100">Teknik pembersihan terstandarisasi dengan bahan premium yang ramah terhadap struktur dan material sepatu.</p>
        </div>
        <div class="services-grid">
            <div class="service-card reveal" data-delay="100">
                <div class="service-card-img">
                    <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=600&auto=format&fit=crop" alt="Deep Cleaning Sepatu">
                </div>
                <div class="service-card-body">
                    <h3 class="service-card-title">Deep Cleaning</h3>
                    <p class="service-card-desc">Pembersihan mendalam dari outsole, midsole, insole, hingga detail upper. Menggunakan larutan enzim organik tanpa merusak tekstur suede atau kulit.</p>
                    <a href="/pesan.php" class="btn btn-outline btn-sm">Pesan Sekarang →</a>
                </div>
            </div>
            <div class="service-card reveal" data-delay="200">
                <div class="service-card-img">
                    <img src="https://images.unsplash.com/photo-1616406432452-07bc5938759d?q=80&w=600&auto=format&fit=crop" alt="Unyellowing Sepatu">
                </div>
                <div class="service-card-body">
                    <h3 class="service-card-title">Unyellowing</h3>
                    <p class="service-card-desc">Penanganan oksidasi pada sol karet dengan hidrogen peroksida terukur dan aktivasi sinar UV terkalibrasi untuk mengembalikan warna asli.</p>
                    <a href="/pesan.php" class="btn btn-outline btn-sm">Pesan Sekarang →</a>
                </div>
            </div>
            <div class="service-card reveal" data-delay="300">
                <div class="service-card-img">
                    <img src="https://images.unsplash.com/photo-1491553895911-0055eca6402d?q=80&w=600&auto=format&fit=crop" alt="Artisan Repair Sepatu">
                </div>
                <div class="service-card-body">
                    <h3 class="service-card-title">Artisan Repair</h3>
                    <p class="service-card-desc">Rekonstruksi sol lepas, penjahitan ulang, perbaikan insole, dan penanganan kerusakan fisik oleh tenaga artisan berpengalaman.</p>
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
                <div class="stat-label">Pelanggan Setia</div>
            </div>
            <div class="stat-card reveal" data-delay="100">
                <div class="stat-num" data-count="2000">0</div>
                <div class="stat-label">Pasang Sepatu Tuntas</div>
            </div>
            <div class="stat-card reveal" data-delay="200">
                <div class="stat-num" data-count="99">0</div>
                <div class="stat-label">% Tingkat Kepuasan</div>
            </div>
            <div class="stat-card reveal" data-delay="300">
                <div class="stat-num" data-count="3">0</div>
                <div class="stat-label">Tahun Beroperasi</div>
            </div>
        </div>
    </div>
</section>

<!-- ========= PRICELIST ========= -->
<section id="pricelist" class="section section-gray">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Transparansi</div>
            <h2 class="section-title reveal">Pricelist Layanan</h2>
            <p class="section-subtitle reveal" data-delay="100">Harga tetap dan terukur tanpa biaya tersembunyi. Sudah mencakup fasilitas antar jemput untuk wilayah Malang Kota.</p>
        </div>
        <div class="pricing-grid">
            <div class="pricing-card reveal" data-delay="100">
                <h3 class="pricing-title">Regular Clean</h3>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">Estimasi pengerjaan: 2 hari kerja</div>
                <hr class="pricing-divider">
                <div class="pricing-feature">Sepatu / Sendal: <strong>Rp 25.000</strong></div>
                <div class="pricing-feature">Tas: <strong>Rp 25.000</strong></div>
                <div class="pricing-feature">Topi: <strong>Rp 15.000</strong></div>
                <div class="pricing-feature">Gratis antar jemput Malang Kota</div>
                <div class="pricing-feature">Laporan dokumentasi kondisi awal & akhir</div>
                <div class="pricing-cta">
                    <a href="/pesan.php" class="btn btn-outline w-100">Pilih Regular</a>
                </div>
            </div>
            <div class="pricing-card featured reveal" data-delay="200">
                <div class="pricing-badge">PRIORITAS</div>
                <h3 class="pricing-title">Fast Clean</h3>
                <div style="font-size:13px;opacity:0.85;margin-bottom:0.75rem;">Estimasi pengerjaan: 1 hari kerja</div>
                <div class="pricing-amount">+Rp 10.000<span>/item</span></div>
                <hr class="pricing-divider" style="border-color:rgba(255,255,255,0.2)">
                <div class="pricing-feature" style="color:white">Sepatu / Sendal: Rp 35.000</div>
                <div class="pricing-feature" style="color:white">Tas: Rp 35.000</div>
                <div class="pricing-feature" style="color:white">Topi: Rp 25.000</div>
                <div class="pricing-feature" style="color:white">Prioritas antrean pengerjaan</div>
                <div class="pricing-feature" style="color:white">Notifikasi status real-time via WhatsApp</div>
                <div class="pricing-cta">
                    <a href="/pesan.php" class="btn btn-white w-100">Pilih Fast Clean</a>
                </div>
            </div>
            <div class="pricing-card reveal" data-delay="300">
                <h3 class="pricing-title">Treatment Tambahan</h3>
                <div style="font-size:13px;color:var(--text-secondary);margin-bottom:0.75rem;">Opsi treatment per item</div>
                <hr class="pricing-divider">
                <div class="pricing-feature">Deep Cleaning: <strong>Termasuk</strong></div>
                <div class="pricing-feature">Unyellowing: <strong>+Rp 10.000</strong></div>
                <div class="pricing-feature">Artisan Repair: <strong>+Rp 20.000</strong></div>
                <div class="pricing-feature">Dapat dikombinasikan sesuai kebutuhan</div>
                <div class="pricing-feature">Konsultasi kondisi sepatu gratis</div>
                <div class="pricing-cta">
                    <a href="https://wa.me/6289681276080" target="_blank" class="btn btn-outline w-100">Konsultasi Dulu</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= MEMBERSHIP ========= -->
<section id="member" class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Program Keanggotaan</div>
            <h2 class="section-title reveal">Paket Langganan Bulanan</h2>
            <p class="section-subtitle reveal" data-delay="100">Solusi hemat bagi pemilik koleksi sepatu banyak. Kuota cuci bulanan dengan prioritas penanganan dan diskon eksklusif.</p>
        </div>
        <div class="member-grid">
            <div class="member-card member-bronze reveal" data-delay="100">
                <div class="member-level">TIER BRONZE</div>
                <div class="member-price">160<span>rb/bln</span></div>
                <div class="member-quota">Kuota 4 Sepatu per Bulan</div>
                <div class="member-savings">Diskon 5% untuk order tambahan</div>
                <div style="margin-top:1.5rem;">
                    <a href="/register.php" class="btn btn-white btn-sm" style="width:100%;">Daftar Bronze</a>
                </div>
            </div>
            <div class="member-card member-silver reveal" data-delay="200" style="transform:scale(1.03)">
                <div class="member-level">TIER SILVER</div>
                <div class="member-price">300<span>rb/bln</span></div>
                <div class="member-quota">Kuota 8 Sepatu per Bulan</div>
                <div class="member-savings">Diskon 10% untuk order tambahan</div>
                <div style="margin-top:1.5rem;">
                    <a href="/register.php" class="btn btn-white btn-sm" style="width:100%;">Daftar Silver</a>
                </div>
            </div>
            <div class="member-card member-gold reveal" data-delay="300">
                <div class="member-level">TIER GOLD</div>
                <div class="member-price">590<span>rb/bln</span></div>
                <div class="member-quota">Kuota 16 Sepatu per Bulan</div>
                <div class="member-savings">Diskon 15% untuk order tambahan</div>
                <div style="margin-top:1.5rem;">
                    <a href="/register.php" class="btn btn-white btn-sm" style="width:100%;">Daftar Gold</a>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <p style="color:var(--text-secondary);font-size:13px;">* Keanggotaan otomatis terhubung ke nomor WhatsApp Anda saat melakukan pemesanan online.</p>
        </div>
    </div>
</section>

<!-- ========= CARA KERJA ========= -->
<section id="cara-kerja" class="section section-gray">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Alur Sistem</div>
            <h2 class="section-title reveal">Empat Langkah Pemesanan</h2>
            <p class="section-subtitle reveal" data-delay="100">Proses pemesanan transparan tanpa login rumit, langsung terhubung ke sistem workshop.</p>
        </div>
        <div class="steps-grid">
            <div class="step-item reveal" data-delay="100">
                <div class="step-num">1</div>
                <h4 class="step-title">Isi Form Online</h4>
                <p class="step-desc">Akses formulir pemesanan publik, isi data alamat penjemputan, dan pilih jenis treatment.</p>
            </div>
            <div class="step-item reveal" data-delay="200">
                <div class="step-num">2</div>
                <h4 class="step-title">Unggah Foto & Pilih Bayar</h4>
                <p class="step-desc">Lampirkan foto kondisi fisik awal barang dan tentukan metode pembayaran (COD, Transfer, atau QRIS).</p>
            </div>
            <div class="step-item reveal" data-delay="300">
                <div class="step-num">3</div>
                <h4 class="step-title">Jemput & Pengerjaan</h4>
                <p class="step-desc">Tim kurir menjemput barang. Perkembangan status cuci dan pengeringan terpantau via sistem.</p>
            </div>
            <div class="step-item reveal" data-delay="400">
                <div class="step-num">4</div>
                <h4 class="step-title">Selesai & Pengantaran</h4>
                <p class="step-desc">Sepatu yang telah bersih dan higienis dikirim kembali bersama nota pelunasan resmi.</p>
            </div>
        </div>
    </div>
</section>

<!-- ========= TESTIMONIALS ========= -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-tag">Ulasan Klien</div>
            <h2 class="section-title reveal">Kepercayaan Pelanggan Kami</h2>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card reveal" data-delay="100">
                <div style="color:#F59E0B;font-size:14px;margin-bottom:0.75rem;">★★★★★</div>
                <p class="testimonial-text">"Air Jordan 1 yang midsole-nya menguning kembali cerah dan bersih sempurna setelah treatment unyellowing. Penjemputan tepat waktu dan komunikasi sangat jelas."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar" style="background:#EDE9FE;color:#5B21B6;font-weight:700;display:flex;align-items:center;justify-content:center;">RA</div>
                    <div>
                        <div class="testimonial-name">Rizky Aditya</div>
                        <div class="testimonial-role">Member Gold (Malang)</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card reveal" data-delay="200">
                <div style="color:#F59E0B;font-size:14px;margin-bottom:0.75rem;">★★★★★</div>
                <p class="testimonial-text">"Pelayanan rapi, sepatu suede yang terkena noda kopi bisa bersih tanpa mengubah tekstur bulunya. Nota digital dan foto before-after sangat profesional."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar" style="background:#DBEAFE;color:#1D4ED8;font-weight:700;display:flex;align-items:center;justify-content:center;">SM</div>
                    <div>
                        <div class="testimonial-name">Sinta Maharani</div>
                        <div class="testimonial-role">Member Silver (Malang Kota)</div>
                    </div>
                </div>
            </div>
            <div class="testimonial-card reveal" data-delay="300">
                <div style="color:#F59E0B;font-size:14px;margin-bottom:0.75rem;">★★★★★</div>
                <p class="testimonial-text">"Proses order via website sangat praktis tanpa perlu buat akun. Status pengerjaan jelas dari antrean cuci hingga pengeringan. Sangat direkomendasikan."</p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar" style="background:#D1FAE5;color:#047857;font-weight:700;display:flex;align-items:center;justify-content:center;">BS</div>
                    <div>
                        <div class="testimonial-name">Budi Santoso</div>
                        <div class="testimonial-role">Pelanggan Reguler (Lowokwaru)</div>
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
            <div class="section-tag">Kontak & Lokasi</div>
            <h2 class="section-title reveal">Hubungi Workshop Kami</h2>
            <p class="section-subtitle reveal" data-delay="100">Konsultasikan kebutuhan perawatan sepatu Anda langsung dengan tim spesialis kami.</p>
        </div>
        <div class="contact-grid">
            <div class="reveal">
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 8.81 19.79 19.79 0 012 2.18 2 2 0 013.18 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L7.91 7.91a16 16 0 006.29 6.29l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 14.92v2z"/></svg>
                    </div>
                    <div>
                        <div class="contact-info-label">WhatsApp Resmi</div>
                        <div class="contact-info-value"><a href="https://wa.me/6289681276080" target="_blank">089681276080</a></div>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div>
                        <div class="contact-info-label">Alamat Workshop</div>
                        <div class="contact-info-value">Jl. Cluster Tigris Raya No 29, Malang</div>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    </div>
                    <div>
                        <div class="contact-info-label">Instagram</div>
                        <div class="contact-info-value"><a href="https://www.instagram.com/" target="_blank">@footstepszs</a></div>
                    </div>
                </div>
                <div class="contact-info-item">
                    <div class="contact-info-icon">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <div class="contact-info-label">Jam Operasional</div>
                        <div class="contact-info-value">Senin - Sabtu: 08.00 - 20.00 WIB</div>
                    </div>
                </div>
            </div>
            <div class="reveal" data-delay="200">
                <div class="card" style="height:100%;display:flex;flex-direction:column;justify-content:center;gap:1.25rem;text-align:center;padding:2.5rem;">
                    <h3 style="font-size:1.4rem;color:var(--navy);">Konsultasi Perawatan</h3>
                    <p style="color:var(--text-secondary);font-size:14px;">Kirimkan foto sepatu Anda untuk mendapatkan estimasi penanganan dan rekomendasi treatment terbaik dari spesialis kami.</p>
                    <a href="https://wa.me/6289681276080?text=Halo%20Footstepszs,%20saya%20ingin%20konsultasi%20layanan%20laundry%20sepatu" 
                       target="_blank" class="btn btn-primary btn-lg">
                        Hubungi via WhatsApp
                    </a>
                    <a href="/pesan.php" class="btn btn-outline">
                        Form Self-Order Publik
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ========= CTA ========= -->
<section class="cta-section">
    <div class="container cta-inner reveal">
        <h2 class="cta-title">Siap Mengembalikan Kebersihan<br>Sepatu Kesayangan Anda?</h2>
        <p class="cta-desc">Layanan penjemputan gratis di seluruh area Malang Kota siap menjemput sepatu Anda hari ini.</p>
        <div class="hero-btns justify-center">
            <a href="/pesan.php" class="btn btn-primary btn-lg">Pesan Sekarang</a>
            <a href="https://wa.me/6289681276080" target="_blank" class="btn btn-white btn-lg">Konsultasi WhatsApp</a>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
