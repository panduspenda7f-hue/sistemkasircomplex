# Sistem Kasir dan Manajemen Stok Berbasis Web

Aplikasi Point of Sale (POS) dan Manajemen Inventori berbasis web yang dibangun menggunakan **PHP 8.x Native**, **MySQL/MariaDB (PDO)**, **Bootstrap 5**, **Chart.js**, **PhpSpreadsheet**, dan **Dompdf**.

## Ruang Lingkup Fitur (Modul A – I)
- **A. Autentikasi & Manajemen Pengguna**: Login/logout, hashing password (`password_hash`), manajemen user, RBAC (`admin` & `kasir`), proteksi CSRF & XSS.
- **B. Dashboard**: Ringkasan omzet harian, jumlah transaksi, jumlah produk, notifikasi stok menipis, grafik penjualan 7 hari (Chart.js), dan transaksi terbaru.
- **C. Manajemen Produk**: CRUD produk, pencarian nama/SKU/barcode, harga modal & harga jual, satuan (`pcs`, `kg`, `liter`, `dus`, `pack`, dll.), dan batas minimum stok.
- **D. Manajemen Kategori**: CRUD kategori dengan validasi larangan hapus kategori yang masih memiliki produk.
- **E. Sistem Kasir & Transaksi Penjualan**: Pencarian/scan barcode produk, keranjang belanja interaktif, pengubahan qty, diskon nominal/persen, pembayaran Tunai, Transfer, dan QRIS, kalkulasi kembalian, serta cetak struk thermal.
- **F. Manajemen Stok**: Pencatatan stok masuk, stok keluar, penyesuaian stok (stock opname), pengurangan stok otomatis saat transaksi POS, riwayat pergerakan stok, dan notifikasi stok minimum.
- **G. Manajemen Pelanggan & Supplier**: CRUD dan pencarian data pelanggan serta data supplier/pemasok.
- **H. Laporan**: Laporan penjualan harian, bulanan, rentang tanggal, keuntungan kotor (HPP vs omzet), produk terlaris, persediaan barang, serta ekspor ke **Excel (.xlsx)** dan **PDF (.pdf)**.
- **I. Pengaturan Toko**: Identitas toko, upload logo toko, dan kustomisasi header/footer serta elemen tampilan struk.

## Cara Instalasi
1. Ekstrak folder `sistem-kasir-stok` ke direktori web server Apache Anda (misalnya `htdocs/` pada XAMPP atau `www/` pada Laragon).
2. Buat database baru bernama `db_kasir_stok` di phpMyAdmin / MySQL, lalu import file `database.sql`.
3. Sesuaikan kredensial database pada file `config/database.php` jika berbeda dari default (`root` tanpa password).
4. *(Opsional namun direkomendasikan untuk ekspor `.xlsx` & `.pdf` native)* Jalankan perintah Composer di dalam folder proyek:
   ```bash
   composer install
   ```
   *(Catatan: Jika `composer install` belum dijalankan, fitur ekspor tetap berjalan menggunakan fallback CSV dan tampilan cetak PDF browser).*
5. Buka browser dan akses `http://localhost/sistem-kasir-stok/`.

## Akun Login Default
- **Admin**: `admin` / `admin123`
- **Kasir**: `kasir` / `kasir123`
