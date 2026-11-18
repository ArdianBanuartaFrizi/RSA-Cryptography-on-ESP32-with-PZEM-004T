<?php
/**
 * Konfigurasi aplikasi RSA + ESP32 + PZEM-004T.
 * Nilai default disiapkan untuk instalasi Laragon lokal.
 * Untuk penggunaan publik/produksi, gunakan environment variables dan kredensial DB khusus.
 */

date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Jakarta');

if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', (int) (getenv('DB_PORT') ?: 3306));
    define('DB_NAME', getenv('DB_NAME') ?: 'rsa_pzem_db');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
    define('DB_CHARSET', 'utf8mb4');

    // Default untuk Laragon: C:\\laragon\\private\\rsa-pzem (di luar www/public web root).
    // Ubah RSA_KEY_DIR melalui environment variable jika lokasi instalasi berbeda.
    $defaultKeyDir = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'rsa-pzem';
    define('RSA_KEY_DIR', getenv('RSA_KEY_DIR') ?: $defaultKeyDir);
    define('RSA_PUBLIC_KEY_FILE', RSA_KEY_DIR . DIRECTORY_SEPARATOR . 'rsa_public_esp1.pem');
    define('RSA_PRIVATE_KEY_FILE', RSA_KEY_DIR . DIRECTORY_SEPARATOR . 'rsa_private_esp1.pem');

    // Sesuai perilaku program asli: tabel riwayat hanya menyimpan data hari ini.
    define('KEEP_ONLY_TODAY', true);
}
