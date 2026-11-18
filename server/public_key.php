<?php
require_once __DIR__ . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Method Not Allowed');
}

if (!is_file(RSA_PUBLIC_KEY_FILE) || !is_readable(RSA_PUBLIC_KEY_FILE)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Public key belum tersedia. Jalankan server/generate_keys.php melalui PHP CLI.\n");
}

header('Content-Type: application/x-pem-file; charset=utf-8');
header('Cache-Control: no-store');
readfile(RSA_PUBLIC_KEY_FILE);
