<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'KasirPro POS & Stok');
define('APP_VERSION', '1.0.0');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_LOGO_DIR', BASE_PATH . '/uploads/logo/');
define('UPLOAD_LOGO_URL', 'uploads/logo/');

// Konfigurasi keamanan session sebelum session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
