# 📖 Dokumentasi Teknis — Footstepszs Laundry Management System

> Versi: 1.0 | Bahasa: PHP 8+, MySQL, HTML/CSS/JS | Platform: XAMPP (Windows/Linux)

---

## 📑 Daftar Isi

1. [Teknologi yang Digunakan](#teknologi-yang-digunakan)
2. [Struktur Database](#struktur-database)
3. [Struktur Direktori](#struktur-direktori)
4. [Fungsi Penting](#fungsi-penting)
5. [Alur Program](#alur-program)
6. [Fitur Unggulan](#fitur-unggulan)
7. [Cara Instalasi (XAMPP / Localhost)](#cara-instalasi)
8. [Cara Hosting (Shared Hosting / VPS)](#cara-hosting)
9. [Cara Build / Deploy](#cara-build--deploy)

---

## 1. Teknologi yang Digunakan

| Kategori | Teknologi | Versi / Catatan |
|---|---|---|
| Backend | PHP | 8.0+ (PDO / MySQLi) |
| Database | MySQL / MariaDB | 10.4+ |
| Frontend | HTML5, CSS3 (Vanilla), JavaScript (ES6) | Tanpa framework tambahan |
| Server Lokal | XAMPP | v8.x (Apache + MySQL) |
| Notifikasi WA | **Fonnte API** | `https://api.fonnte.com/send` — REST API gratis |
| Upload File | PHP `$_FILES` + `move_uploaded_file()` | Disimpan di `/uploads/documents/` |
| Session Auth | PHP Session (`$_SESSION`) | Role: `admin` / `customer` |
| PDF / Dokumen | HTML-to-file generate | Tersimpan sebagai `.html` di server |
| Icon & Emoji | Unicode Emoji | Native (tanpa library ikon) |

---

## 2. Struktur Database

Database bernama `footstepszs`. Terdiri dari **6 tabel utama**:

### 2.1 Tabel `users`
Menyimpan seluruh akun pengguna (admin dan customer).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | Primary key |
| `username` | VARCHAR(50) UNIQUE | Username login |
| `email` | VARCHAR(100) UNIQUE | Email pengguna |
| `password` | VARCHAR(255) | Hash bcrypt |
| `nama_lengkap` | VARCHAR(100) | Nama tampil |
| `no_hp` | VARCHAR(20) | Nomor WhatsApp (digunakan untuk notifikasi) |
| `alamat` | TEXT | Alamat customer |
| `role` | ENUM('admin','customer') | Hak akses |
| `avatar` | VARCHAR(255) | Path foto profil (opsional) |
| `created_at` | TIMESTAMP | Waktu daftar |

### 2.2 Tabel `members`
Program langganan bulanan pelanggan (Bronze / Silver / Gold).

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | Primary key |
| `user_id` | INT FK → users.id | Pemilik membership |
| `level` | ENUM('Bronze','Silver','Gold') | Level keanggotaan |
| `tanggal_mulai` | DATE | Mulai aktif |
| `tanggal_selesai` | DATE | Akhir masa aktif |
| `poin` | INT | Poin reward (1 poin = Rp 10.000) |
| `total_transaksi` | DECIMAL(15,2) | Akumulasi belanja |
| `status` | ENUM('Aktif','Nonaktif') | Status keanggotaan |

### 2.3 Tabel `orders`
Data pesanan cuci sepatu.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | Primary key |
| `user_id` | INT FK → users.id | Customer pemesan |
| `kode_order` | VARCHAR(20) UNIQUE | Kode unik order (e.g. `ORD-20250623-001`) |
| `items` | TEXT (JSON) | Array item yang dicuci: `[{type, qty, treatment, harga_satuan, subtotal}]` |
| `jenis_waktu` | ENUM('Regular','Fast Clean') | Reguler 2 hari / Fast Clean 1 hari |
| `total_harga` | DECIMAL(15,2) | Total pembayaran |
| `status` | ENUM('Menunggu','Diterima','Proses','Selesai','Dibatalkan') | Status pengerjaan |
| `pickup_address` | TEXT | Alamat jemput barang |
| `catatan` | TEXT | Catatan tambahan customer |
| `tanggal_order` | DATETIME | Waktu pesan |
| `tanggal_estimasi` | DATETIME | Estimasi selesai |
| `tanggal_selesai` | DATETIME | Waktu selesai aktual |
| `notif_admin` | TINYINT | Flag notifikasi baru untuk admin (0=unread) |

### 2.4 Tabel `keuangan`
Pencatatan pemasukan dan pengeluaran bisnis.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | Primary key |
| `jenis` | ENUM('Pemasukan','Pengeluaran') | Jenis transaksi |
| `kategori` | VARCHAR(100) | Kategori (contoh: 'Order Laundry', 'Operasional') |
| `jumlah` | DECIMAL(15,2) | Nominal rupiah |
| `keterangan` | TEXT | Deskripsi |
| `tanggal` | DATE | Tanggal transaksi |
| `order_id` | INT NULL | Referensi ke order (jika terkait order) |
| `created_by` | INT | ID admin yang mencatat |

### 2.5 Tabel `notifications`
Notifikasi in-app untuk customer.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | Primary key |
| `user_id` | INT | Penerima (0 = admin) |
| `pesan` | TEXT | Isi notifikasi |
| `url` | VARCHAR(255) | Link target klik |
| `is_read` | TINYINT | 0=belum dibaca |
| `created_at` | TIMESTAMP | Waktu dibuat |

### 2.6 Tabel `documents` (DMS)
Manajemen dokumen digital terpusat.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | INT PK | Primary key |
| `ref_type` | ENUM('member','order','keuangan','general') | Kategori dokumen |
| `ref_id` | INT NULL | ID referensi data terkait |
| `nama_dokumen` | VARCHAR(255) | Nama dokumen |
| `jenis` | ENUM('Upload','Generated') | Upload manual / generate otomatis |
| `doc_subtype` | VARCHAR(50) | Sub-tipe (e.g. `tanda_terima`) |
| `file_path` | VARCHAR(500) | Path relatif file |
| `keterangan` | TEXT | Keterangan tambahan |
| `tanggal` | DATE | Tanggal dokumen |
| `created_by` | INT | ID admin pembuat |

---

## 3. Struktur Direktori

```
footstepszs/
├── index.php                  # Landing page publik
├── login.php                  # Halaman login
├── pesan.php                  # Form pemesanan TAMU (tanpa login)
├── koneksi.php                # Koneksi DB (versi lama)
├── database.sql               # Skema database lengkap
│
├── includes/                  # Shared helpers & layout
│   ├── koneksi.php            # ⭐ Koneksi DB + semua helper function
│   ├── header_public.php      # Header untuk halaman publik
│   ├── footer.php             # Footer publik
│   ├── admin_header.php       # Header panel admin
│   ├── admin_footer.php       # Footer panel admin
│   ├── customer_header.php    # Header dashboard customer
│   └── customer_footer.php    # Footer dashboard customer
│
├── admin/                     # Panel admin (role: admin)
│   ├── dashboard.php          # Ringkasan statistik
│   ├── orders.php             # Daftar semua order
│   ├── order_detail.php       # ⭐ Detail & kelola order (upload TT, kirim WA)
│   ├── order_create.php       # Buat order baru (manual)
│   ├── order_edit.php         # Edit order
│   ├── customers.php          # Data pelanggan
│   ├── members.php            # Manajemen membership
│   ├── keuangan.php           # Laporan keuangan
│   ├── reports.php            # Laporan & statistik
│   └── dms.php                # ⭐ Document Management System
│
├── customer/                  # Dashboard customer (role: customer)
│   ├── dashboard.php          # Ringkasan akun customer
│   ├── order.php              # Form pesan online (customer login)
│   ├── my_orders.php          # Riwayat & lacak pesanan
│   ├── membership.php         # Info & daftar membership
│   └── order_success.php      # Konfirmasi sukses pesan
│
├── templates/                 # Template dokumen cetak/generate
│   ├── invoice_in.php         # Nota Masuk (Tanda Terima 1)
│   ├── invoice_out.php        # Nota Keluar (Tanda Terima 2 - Selesai)
│   ├── doc_order.php          # Dokumen ringkasan order
│   ├── doc_member.php         # Dokumen kartu member
│   └── doc_keuangan.php       # Dokumen laporan keuangan
│
├── api/
│   └── get_notifications.php  # API endpoint notifikasi (AJAX)
│
├── assets/
│   ├── css/                   # Stylesheet
│   └── js/                    # JavaScript
│
└── uploads/
    └── documents/             # Penyimpanan file upload & generated HTML
        └── generated/         # Nota/dokumen yang di-generate otomatis
```

---

## 4. Fungsi Penting

Semua fungsi helper tersimpan di [`includes/koneksi.php`](../includes/koneksi.php).

### `formatRupiah($angka)`
Mengubah angka ke format mata uang Rupiah.
```php
formatRupiah(25000); // → "Rp 25.000"
```

### `generateOrderCode($conn)`
Membuat kode order unik berbasis tanggal dan urutan harian.
```php
generateOrderCode($conn); // → "ORD-20250623-001"
```

### `getEstimasiSelesai($jenis_waktu)`
Menghitung tanggal estimasi selesai (+1 hari Fast Clean, +2 hari Regular).
```php
getEstimasiSelesai('Fast Clean'); // → "2025-06-24 08:00:00"
```

### `addNotification($conn, $user_id, $pesan, $url)`
Menyimpan notifikasi in-app ke tabel `notifications`.
```php
addNotification($conn, 5, "Pesanan Anda selesai!", "/customer/my_orders.php");
```

### `getUnreadNotifCount($conn, $user_id)`
Menghitung jumlah notifikasi belum dibaca milik user tertentu.

### `isAdmin()` / `isCustomer()` / `requireAdmin()`
Pengecekan dan penjagaan role berbasis `$_SESSION['role']`.

### `getMemberInfo($conn, $user_id)`
Mengambil data membership aktif pelanggan (jika ada).

### `updateMemberPoints($conn, $user_id, $amount)`
Menghitung dan memperbarui poin & level membership setelah transaksi selesai.
- Bronze: < Rp 200.000 total transaksi
- Silver: Rp 200.000 – Rp 499.999
- Gold: ≥ Rp 500.000

### `sendWhatsAppNotification($target, $message)`
Mengirim pesan WhatsApp otomatis via Fonnte API.
```php
sendWhatsAppNotification('081234567890', "Pesanan Anda selesai!");
```
- Konversi otomatis format `08xxx` → `628xxx`
- Jika token Fonnte belum diisi, akan mengembalikan `false` (fallback ke WA Web manual)

### `timeAgo($datetime)`
Mengubah datetime ke format relatif (e.g., "5 menit lalu", "2 hari lalu").

---

## 5. Alur Program

### 5.1 Alur Pemesanan Customer

```
[Landing Page / index.php]
       │
       ▼
[Daftar / Login]
  pesan.php (tamu) │ customer/order.php (login)
       │
       ▼
[Pilih item, qty, treatment, jenis waktu]
 (JavaScript kalkulasi real-time harga)
       │
       ▼
[Upload foto awal per item (qty kamera)]
       │
       ▼
[Submit → Simpan ke tabel orders + documents (TT-1)]
 kode_order di-generate otomatis
       │
       ▼
[customer/order_success.php]
 Notifikasi in-app terkirim ke admin (notif_admin=0)
```

### 5.2 Alur Pengerjaan Admin

```
[admin/orders.php] — Lihat daftar order masuk
       │
       ▼
[admin/order_detail.php?id=X]
       │
       ├── Update Status → Menunggu / Diterima / Proses
       │     └── Notifikasi WA otomatis ke customer via Fonnte
       │
       ├── Upload TT-2 (Foto Selesai per item)
       │     ├── Generate Nota OUT (invoice_out.php → HTML)
       │     ├── Status otomatis → Selesai
       │     ├── Catat pemasukan di tabel keuangan
       │     ├── Update poin membership customer
       │     └── Kirim WA + link Nota OUT ke customer via Fonnte
       │
       └── Upload TT-3 (Bukti Diambil)
             └── Status → Diambil
```

### 5.3 Alur Notifikasi WhatsApp (Fonnte)

```
[Admin klik "Selesai" atau ubah status]
       │
       ▼
[sendWhatsAppNotification($no_hp, $pesan)]
 di includes/koneksi.php
       │
       ▼
[cURL POST → https://api.fonnte.com/send]
 Header: Authorization: {FONNTE_TOKEN}
 Body:   target=62xxx, message=..., countryCode=62
       │
       ▼
[Pesan WA terkirim ke nomor customer]
```

### 5.4 Alur DMS (Document Management System)

```
[admin/dms.php]
       │
       ├── Upload dokumen manual (PDF/Gambar)
       │     └── Simpan ke uploads/documents/ + records di tabel documents
       │
       └── Generate dokumen otomatis:
             ├── Laporan Keuangan (doc_keuangan.php)
             ├── Laporan Order (doc_order.php)
             └── Kartu Member (doc_member.php)
                   └── File HTML tersimpan di uploads/documents/generated/
```

---

## 6. Fitur Unggulan

### 🚀 Multi-Item Order dengan Foto Per Item
- Customer dapat memesan beberapa item sekaligus (sepatu, tas, topi, sendal)
- Setiap item memiliki input foto kondisi awal (sesuai jumlah qty)
- Admin wajib upload foto selesai per item sebelum status bisa "Selesai"

### 📲 Notifikasi WhatsApp Otomatis via Fonnte
- Terintegrasi dengan Fonnte API (gratis, tanpa biaya per pesan)
- Notifikasi dikirim otomatis ketika:
  - Status order berubah (Diterima, Proses, Selesai, Diambil)
  - Order selesai — termasuk link Nota OUT untuk pelanggan
- Fallback ke WA Web manual jika token Fonnte belum dikonfigurasi

### 📄 Nota Digital (Invoice IN & OUT)
- **Nota IN (TT-1)**: Bukti penerimaan barang + foto kondisi awal
- **Nota OUT (TT-2)**: Bukti penyelesaian + perbandingan foto awal vs akhir
- Nota di-generate sebagai file HTML dan dapat dibuka via link

### 💎 Program Membership Berjenjang
- **Bronze** (Rp 160.000/bln): 4 sepatu/bulan
- **Silver** (Rp 300.000/bln): 8 sepatu/bulan
- **Gold** (Rp 590.000/bln): 16 sepatu/bulan
- Poin reward otomatis (1 poin per Rp 10.000)
- Level membership naik otomatis berdasarkan total transaksi

### 📊 Document Management System (DMS)
- Kelola semua dokumen bisnis dalam satu tempat
- Generate laporan keuangan, laporan order, dan kartu member otomatis
- Preview dokumen langsung di browser

### 💰 Pencatatan Keuangan Otomatis
- Setiap order selesai otomatis tercatat sebagai "Pemasukan" di modul keuangan
- Admin bisa menambah pengeluaran secara manual
- Laporan keuangan per bulan / per rentang tanggal

### 🔔 Notifikasi In-App Real-Time
- Badge notifikasi di navbar customer dan admin
- Polling AJAX setiap 30 detik via `/api/get_notifications.php`
- Klik notifikasi langsung diarahkan ke halaman terkait

---

## 7. Cara Instalasi

### Prasyarat
- XAMPP versi 8.x (Windows/Linux/Mac)
- PHP 8.0+
- MySQL / MariaDB 10.4+

### Langkah Instalasi

**1. Clone / Copy project ke folder XAMPP**
```bash
# Salin folder footstepszs ke:
C:\xampp\htdocs\footstepszs\   (Windows)
/opt/lampp/htdocs/footstepszs/ (Linux)
```

**2. Buat Database**
- Buka phpMyAdmin: `http://localhost/phpmyadmin`
- Klik **New** → buat database bernama `footstepszs`
- Klik tab **Import** → pilih file `footstepszs/database.sql` → **Go**

**3. Konfigurasi Koneksi Database**
Edit file `includes/koneksi.php`:
```php
$host = "localhost";
$user = "root";       // username MySQL Anda
$pass = "";           // password MySQL Anda (kosong jika XAMPP default)
$db   = "footstepszs";
```

**4. Konfigurasi Token Fonnte (WhatsApp API)**
Di file `includes/koneksi.php` baris 123:
```php
define('FONNTE_TOKEN', 'MASUKKAN_TOKEN_FONNTE_ANDA_DISINI');
```
> Daftarkan perangkat WA Anda di [https://fonnte.com](https://fonnte.com) untuk mendapatkan token gratis.

**5. Buat Folder Uploads (jika belum ada)**
```bash
mkdir footstepszs/uploads/documents/generated
chmod 777 footstepszs/uploads/documents/  (Linux)
```

**6. Jalankan XAMPP**
- Start **Apache** dan **MySQL** dari XAMPP Control Panel
- Akses: `http://localhost/footstepszs/`

**7. Login Admin Default**
```
URL:      http://localhost/footstepszs/login.php
Username: admin
Password: admin123
```
> ⚠️ Segera ganti password admin setelah instalasi pertama!

---

## 8. Cara Hosting (Shared Hosting / VPS)

### Shared Hosting (cPanel)

**1. Upload File**
- Zip seluruh folder `footstepszs/`
- Login ke cPanel → **File Manager** → masuk ke `public_html/`
- Upload dan ekstrak zip ke `public_html/footstepszs/`

**2. Import Database**
- cPanel → **MySQL Databases** → buat database baru (e.g., `user_footstepszs`)
- cPanel → **phpMyAdmin** → pilih database → Import → upload `database.sql`

**3. Update Konfigurasi**
Edit `includes/koneksi.php`:
```php
$host = "localhost";
$user = "cpanel_username_db";   // username DB dari cPanel
$pass = "password_db_anda";
$db   = "cpanel_username_footstepszs";
```

**4. Update URL di Kode**
Cari dan ganti semua referensi `/footstepszs/` menjadi `/` jika di-deploy di root domain:
```bash
# Gunakan find & replace di editor (VS Code, dll)
Cari:    /footstepszs/
Ganti:   /
```

**5. Izin Folder Upload**
```bash
chmod 755 uploads/
chmod 755 uploads/documents/
chmod 755 uploads/documents/generated/
```

### VPS (Ubuntu + Nginx/Apache)

```bash
# Install PHP & MySQL
sudo apt update
sudo apt install php8.1 php8.1-mysql php8.1-curl mysql-server apache2

# Enable mod_rewrite
sudo a2enmod rewrite

# Upload file ke /var/www/html/footstepszs/
# Import database
mysql -u root -p footstepszs < database.sql

# Set permissions
sudo chown -R www-data:www-data /var/www/html/footstepszs/uploads/
sudo chmod -R 755 /var/www/html/footstepszs/uploads/
```

---

## 9. Cara Build / Deploy

Aplikasi ini adalah **PHP native** — tidak memerlukan proses build (compile/transpile).  
Namun untuk memastikan kesiapan produksi:

### Checklist Sebelum Go-Live

- [ ] Ganti `FONNTE_TOKEN` dengan token asli Fonnte Anda
- [ ] Ganti kredensial database di `includes/koneksi.php`
- [ ] Ganti password admin default (`admin123`)
- [ ] Pastikan folder `uploads/documents/` writable oleh server
- [ ] Uji fitur upload foto (TT-1 dan TT-2)
- [ ] Uji pengiriman notifikasi WhatsApp
- [ ] Konfigurasi HTTPS (SSL certificate) di hosting

### Verifikasi Instalasi
Buka `http://yourdomain.com/footstepszs/check_db.php` untuk memverifikasi koneksi database.

---

*Dibuat oleh: Tim Footstepszs | Jl. Cluster Tigris Raya No. 29, Malang | WA: 089681276080*
