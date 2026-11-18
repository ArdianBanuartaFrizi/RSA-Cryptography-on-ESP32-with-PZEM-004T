<?php
require_once __DIR__ . '/config.php';

// Pembuatan kunci hanya boleh dijalankan melalui terminal/CLI, bukan browser.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Akses ditolak. Jalankan file ini melalui Laragon Terminal / PHP CLI.\n");
}

if (!extension_loaded('openssl')) {
    fwrite(STDERR, "Ekstensi PHP OpenSSL belum aktif.\n");
    exit(1);
}

$force = in_array('--force', $argv ?? [], true);
$publicFile = RSA_PUBLIC_KEY_FILE;
$privateFile = RSA_PRIVATE_KEY_FILE;

if (!is_dir(RSA_KEY_DIR) && !mkdir(RSA_KEY_DIR, 0700, true) && !is_dir(RSA_KEY_DIR)) {
    fwrite(STDERR, "Gagal membuat folder kunci: " . RSA_KEY_DIR . "\n");
    exit(1);
}

if ((file_exists($publicFile) || file_exists($privateFile)) && !$force) {
    fwrite(STDERR, "File kunci sudah ada. Tidak ditimpa. Gunakan --force hanya jika memang ingin membuat pasangan kunci baru.\n");
    exit(1);
}

$config = [
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

$key = openssl_pkey_new($config);
if ($key === false) {
    fwrite(STDERR, "Gagal membuat RSA key pair: " . (openssl_error_string() ?: 'OpenSSL error') . "\n");
    exit(1);
}

$privatePem = '';
if (!openssl_pkey_export($key, $privatePem)) {
    fwrite(STDERR, "Gagal mengekspor private key.\n");
    exit(1);
}

$details = openssl_pkey_get_details($key);
if (!is_array($details) || empty($details['key'])) {
    fwrite(STDERR, "Gagal memperoleh public key.\n");
    exit(1);
}

if (file_put_contents($privateFile, $privatePem, LOCK_EX) === false) {
    fwrite(STDERR, "Gagal menyimpan private key.\n");
    exit(1);
}
if (file_put_contents($publicFile, $details['key'], LOCK_EX) === false) {
    @unlink($privateFile);
    fwrite(STDERR, "Gagal menyimpan public key.\n");
    exit(1);
}

@chmod($privateFile, 0600);
@chmod($publicFile, 0644);

fwrite(STDOUT, "RSA-2048 key pair berhasil dibuat.\n");
fwrite(STDOUT, "Public key : {$publicFile}\n");
fwrite(STDOUT, "Private key: {$privateFile}\n");
fwrite(STDOUT, "Jangan unggah private key ke GitHub atau folder web publik.\n");
