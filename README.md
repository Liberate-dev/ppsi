# 👟 Footstepszs — Laundry Management System

<div align="center">

![Footstepszs](https://img.shields.io/badge/Footstepszs-Laundry%20System-6366f1?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-10.4+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![XAMPP](https://img.shields.io/badge/XAMPP-Ready-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)

**Sistem manajemen laundry sepatu premium berbasis web**  
*Deep Cleaning · Unyellowing · Artisan Repair — Free Pick Up & Delivery Malang*

[🚀 Demo Lokal](#instalasi) • [📖 Dokumentasi Teknis](docs/technical_documentation.md) • [💬 WhatsApp](https://wa.me/6289681276080)

</div>

---

## ✨ Fitur Utama

| Fitur | Deskripsi |
|---|---|
| 🛒 **Multi-Item Order** | Pesan beberapa item sekaligus dengan kalkulasi harga real-time |
| 📸 **Foto Per Item** | Upload & preview foto kondisi awal per item (sesuai jumlah qty) |
| 📲 **Notif WA Otomatis** | Kirim notifikasi WhatsApp via Fonnte API saat status order berubah |
| 📄 **Nota Digital** | Generate Nota IN & OUT (HTML) dengan perbandingan foto before-after |
| 💎 **Program Member** | Membership berjenjang Bronze / Silver / Gold dengan poin reward |
| 📊 **DMS** | Document Management System untuk semua dokumen bisnis |
| 💰 **Keuangan** | Pencatatan pemasukan & pengeluaran otomatis terintegrasi |
| 🔔 **Notifikasi In-App** | Badge real-time untuk admin dan customer |

---

## 🛠️ Teknologi

- **Backend**: PHP 8.0+ (MySQLi, Session, cURL)
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3 Vanilla, JavaScript ES6
- **WA API**: [Fonnte](https://fonnte.com) (gratis)
- **Server Lokal**: XAMPP

---

## ⚡ Instalasi Cepat (XAMPP)

```bash
# 1. Salin project ke htdocs
C:\xampp\htdocs\footstepszs\

# 2. Buat database 'footstepszs' di phpMyAdmin
# 3. Import file database.sql
# 4. Akses di browser
http://localhost/footstepszs/
```

**Login Admin Default:**
```
Username: admin
Password: admin123
```

> 📖 Lihat [Dokumentasi Teknis](docs/technical_documentation.md) untuk panduan instalasi lengkap, hosting, dan konfigurasi Fonnte.

---

## 📁 Struktur Singkat

```
footstepszs/
├── index.php           # Landing page publik
├── pesan.php           # Form pemesanan tamu
├── login.php           # Halaman login
├── database.sql        # Skema database
├── includes/           # Helper & koneksi DB
├── admin/              # Panel admin
├── customer/           # Dashboard customer
├── templates/          # Template nota & dokumen
├── api/                # Endpoint AJAX
├── assets/             # CSS & JS
├── uploads/            # File upload & dokumen generated
└── docs/               # Dokumentasi
```

---

## 🗄️ Skema Database (Ringkas)

```
users ──────────┬── members       (program langganan)
                ├── orders        (data pesanan)
                ├── notifications (notif in-app)
                └── documents     (DMS)
                
orders ─────────└── keuangan      (pemasukan otomatis)
```

---

## 📲 Konfigurasi WhatsApp (Fonnte)

Edit `includes/koneksi.php`:
```php
define('FONNTE_TOKEN', 'TOKEN_FONNTE_ANDA_DISINI');
```
1. Daftar di [fonnte.com](https://fonnte.com)
2. Hubungkan nomor WhatsApp Anda
3. Salin token dan tempel di konfigurasi di atas

---

## 📞 Kontak

| | |
|---|---|
| 📍 Alamat | Jl. Cluster Tigris Raya No. 29, Malang |
| 📱 WhatsApp | [089681276080](https://wa.me/6289681276080) |
| 📸 Instagram | [@footstepszs](https://www.instagram.com/footstepszs/) |
| 🕐 Jam Ops | Senin – Sabtu: 08.00 – 20.00 WIB |

---

<div align="center">
Made with ❤️ for Footstepszs — Malang
</div>
