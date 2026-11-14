-- Database: db_kasir_stok
-- Sistem Kasir dan Manajemen Stok Berbasis Web (PHP 8.x & MySQL/MariaDB)

CREATE DATABASE IF NOT EXISTS `db_kasir_stok`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `db_kasir_stok`;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Tabel Pengguna (Modul A)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. Tabel Kategori Produk (Modul D)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. Tabel Supplier / Pemasok (Modul G)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `contact_person` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. Tabel Pelanggan (Modul G)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Tabel Produk (Modul C)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id` INT UNSIGNED NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `barcode` VARCHAR(80) DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
  `harga_modal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `harga_jual` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `stok` INT NOT NULL DEFAULT 0,
  `stok_minimum` INT NOT NULL DEFAULT 5,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_sku` (`sku`),
  KEY `idx_products_barcode` (`barcode`),
  KEY `idx_products_name` (`name`),
  KEY `fk_products_category` (`category_id`),
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Tabel Transaksi Penjualan (Modul E)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number` VARCHAR(40) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED DEFAULT NULL,
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_type` ENUM('nominal', 'percent') NOT NULL DEFAULT 'nominal',
  `discount_value` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `discount_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `grand_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('tunai', 'transfer', 'qris') NOT NULL DEFAULT 'tunai',
  `payment_reference` VARCHAR(100) DEFAULT NULL,
  `amount_paid` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `change_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_transactions_invoice` (`invoice_number`),
  KEY `idx_transactions_created_at` (`created_at`),
  KEY `fk_transactions_user` (`user_id`),
  KEY `fk_transactions_customer` (`customer_id`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Tabel Detail Item Transaksi (Modul E & H)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `transaction_items`;
CREATE TABLE `transaction_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `transaction_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `product_name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NOT NULL,
  `unit` VARCHAR(30) NOT NULL,
  `harga_modal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `harga_jual` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `qty` INT NOT NULL DEFAULT 1,
  `subtotal` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_items_transaction` (`transaction_id`),
  KEY `fk_items_product` (`product_id`),
  CONSTRAINT `fk_items_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Tabel Riwayat Pergerakan Stok (Modul F)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED DEFAULT NULL,
  `type` ENUM('in', 'out', 'adjustment', 'sale') NOT NULL,
  `qty_change` INT NOT NULL,
  `stock_before` INT NOT NULL,
  `stock_after` INT NOT NULL,
  `reference_code` VARCHAR(100) DEFAULT NULL,
  `notes` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_stock_created_at` (`created_at`),
  KEY `fk_stock_product` (`product_id`),
  KEY `fk_stock_user` (`user_id`),
  KEY `fk_stock_supplier` (`supplier_id`),
  CONSTRAINT `fk_stock_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_stock_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Tabel Pengaturan Toko (Modul I)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `store_settings`;
CREATE TABLE `store_settings` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `store_name` VARCHAR(120) NOT NULL DEFAULT 'TOKO MAJU JAYA',
  `store_address` TEXT NOT NULL,
  `store_phone` VARCHAR(40) NOT NULL DEFAULT '0812-3456-7890',
  `store_logo` VARCHAR(255) DEFAULT NULL,
  `receipt_header` VARCHAR(255) DEFAULT 'Melayani dengan Sepenuh Hati',
  `receipt_footer` VARCHAR(255) DEFAULT 'Terima kasih telah berbelanja! Barang yang sudah dibeli tidak dapat ditukar.',
  `show_logo` TINYINT(1) NOT NULL DEFAULT 1,
  `show_cashier` TINYINT(1) NOT NULL DEFAULT 1,
  `show_customer` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ========================================================
-- DATA AWAL (SEED DATA)
-- ========================================================

-- Akun Default:
-- Admin: username = admin | password = admin123
-- Kasir: username = kasir | password = kasir123
INSERT INTO `users` (`id`, `name`, `username`, `password`, `role`, `is_active`) VALUES
(1, 'Administrator Utama', 'admin', '$2y$10$SEED_DEFAULT_ADMIN_HASH_AUTO_UPGRADE', 'admin', 1),
(2, 'Kasir Shift Pagi', 'kasir', '$2y$10$SEED_DEFAULT_KASIR_HASH_AUTO_UPGRADE', 'kasir', 1);

INSERT INTO `categories` (`id`, `name`, `description`) VALUES
(1, 'Makanan & Minuman', 'Produk makanan kemasan, snack, dan minuman'),
(2, 'Sembako', 'Kebutuhan pokok seperti beras, minyak, gula, tepung'),
(3, 'Alat Tulis Kantor (ATK)', 'Perlengkapan sekolah dan kantor'),
(4, 'Kebersihan & Rumah Tangga', 'Sabun, deterjen, tisu, dan perlengkapan rumah');

INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`) VALUES
(1, 'PT Sumber Pangan Nusantara', 'Budi Santoso', '0812-8899-1122', 'order@sumberpangan.co.id', 'Jl. Raya Industri No. 45, Tangerang'),
(2, 'CV Mitra ATK Sejahtera', 'Siti Aminah', '0813-7766-5544', 'sales@mitraatk.com', 'Jl. Merdeka No. 12, Jakarta Barat');

INSERT INTO `customers` (`id`, `name`, `phone`, `address`, `notes`) VALUES
(1, 'Ahmad Fauzi', '0856-1122-3344', 'Perumahan Griya Indah Blok B2 No. 10', 'Pelanggan tetap'),
(2, 'Rina Marlina', '0878-9988-7766', 'Jl. Melati No. 8', 'Member grosir');

INSERT INTO `products` (`id`, `category_id`, `sku`, `barcode`, `name`, `unit`, `harga_modal`, `harga_jual`, `stok`, `stok_minimum`) VALUES
(1, 2, 'PRD-001', '8991001000011', 'Beras Premium Pandan Wangi 5kg', 'pcs', 62000.00, 72000.00, 25, 5),
(2, 2, 'PRD-002', '8991001000028', 'Minyak Goreng Sawit 2 Liter', 'pcs', 31000.00, 36000.00, 30, 8),
(3, 2, 'PRD-003', '8991001000035', 'Gula Pasir Putih 1kg', 'kg', 14500.00, 17500.00, 4, 10),
(4, 1, 'PRD-004', '8991001000042', 'Air Mineral Botol 600ml (1 Dus)', 'dus', 38000.00, 48000.00, 15, 4),
(5, 1, 'PRD-005', '8991001000059', 'Kopi Susu Instan Sachet (Pack 10)', 'pack', 12000.00, 15000.00, 3, 6),
(6, 3, 'PRD-006', '8991001000066', 'Buku Tulis 58 Lembar (Pack 10)', 'pack', 28000.00, 35000.00, 18, 5),
(7, 4, 'PRD-007', '8991001000073', 'Deterjen Bubuk 800gr', 'pcs', 16500.00, 21000.00, 20, 5);

INSERT INTO `store_settings` (`id`, `store_name`, `store_address`, `store_phone`, `store_logo`, `receipt_header`, `receipt_footer`, `show_logo`, `show_cashier`, `show_customer`) VALUES
(1, 'TOKO SERBA ADA MANDIRI', 'Jl. Jend. Sudirman No. 88, Kota Cilegon, Banten', '0254-391234 / 0812-9000-8000', NULL, 'Harga Hemat, Belanja Nyaman', 'Terima kasih atas kunjungan Anda! Simpan struk ini sebagai bukti pembayaran.', 1, 1, 1);
