-- ================================================
--  FOOTSTEPSZS DATABASE — Full System Schema
-- ================================================

CREATE DATABASE IF NOT EXISTS footstepszs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE footstepszs;

-- ========================================
-- 1. USERS (Admin + Customer)
-- ========================================
CREATE TABLE IF NOT EXISTS users (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    email          VARCHAR(100) NOT NULL UNIQUE,
    password       VARCHAR(255) NOT NULL,
    nama_lengkap   VARCHAR(100) NOT NULL,
    no_hp          VARCHAR(20),
    alamat         TEXT,
    role           ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    avatar         VARCHAR(255),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account (password: admin123)
INSERT INTO users (username, email, password, nama_lengkap, role)
SELECT 'admin','admin@footstepszs.com','$2y$10$mNUEK6v7cOnxSA5KMvv.OeKTVaFBz1ZZPT3rP4KX8edwP.kikSW4O','Administrator Footstepszs','admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- ========================================
-- 2. MEMBERS (Langganan Bulanan)
-- ========================================
CREATE TABLE IF NOT EXISTS members (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT NOT NULL UNIQUE,
    level          ENUM('Bronze','Silver','Gold') DEFAULT 'Bronze',
    tanggal_mulai  DATE,
    tanggal_selesai DATE,
    poin           INT DEFAULT 0,
    total_transaksi DECIMAL(15,2) DEFAULT 0,
    status         ENUM('Aktif','Nonaktif') DEFAULT 'Aktif',
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ========================================
-- 3. ORDERS
-- ========================================
CREATE TABLE IF NOT EXISTS orders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    kode_order      VARCHAR(20) NOT NULL UNIQUE,
    items           TEXT NOT NULL COMMENT 'JSON: [{type,qty,treatment,harga_satuan,subtotal}]',
    jenis_waktu     ENUM('Regular','Fast Clean') DEFAULT 'Regular',
    total_harga     DECIMAL(15,2) NOT NULL,
    status          ENUM('Menunggu','Diterima','Proses','Selesai','Dibatalkan') DEFAULT 'Menunggu',
    pickup_address  TEXT,
    catatan         TEXT,
    tanggal_order   DATETIME DEFAULT CURRENT_TIMESTAMP,
    tanggal_estimasi DATETIME,
    tanggal_selesai DATETIME,
    notif_admin     TINYINT DEFAULT 0 COMMENT '0=unread by admin',
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ========================================
-- 4. KEUANGAN (Pemasukan & Pengeluaran)
-- ========================================
CREATE TABLE IF NOT EXISTS keuangan (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    jenis       ENUM('Pemasukan','Pengeluaran') NOT NULL,
    kategori    VARCHAR(100) NOT NULL,
    jumlah      DECIMAL(15,2) NOT NULL,
    keterangan  TEXT,
    tanggal     DATE NOT NULL,
    order_id    INT DEFAULT NULL COMMENT 'linked order if pemasukan from order',
    created_by  INT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 5. NOTIFICATIONS
-- ========================================
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT COMMENT 'recipient: 0 = admin',
    pesan      TEXT NOT NULL,
    url        VARCHAR(255),
    is_read    TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ========================================
-- 6. DOCUMENTS (DMS)
-- ========================================
CREATE TABLE IF NOT EXISTS documents (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ref_type     ENUM('member','order','keuangan','general') NOT NULL,
    ref_id       INT DEFAULT NULL,
    nama_dokumen VARCHAR(255) NOT NULL,
    jenis        ENUM('Upload','Generated') DEFAULT 'Upload',
    file_path    VARCHAR(500),
    keterangan   TEXT,
    tanggal      DATE NOT NULL,
    created_by   INT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
