<?php
require_once __DIR__ . '/config.php';

function json_response(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        json_response(['success' => false, 'message' => 'Gunakan HTTP POST untuk endpoint ini.'], 405);
    }
}

/**
 * Validasi ciphertext Base64 RSA-2048.
 * Satu ciphertext RSA-2048 memiliki panjang 256 byte atau 344 karakter Base64.
 */
function validate_rsa_ciphertext($value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    // Antisipasi tanda '+' yang mungkin berubah menjadi spasi pada form-urlencoded.
    $value = str_replace(' ', '+', trim($value));
    $raw = base64_decode($value, true);

    if ($raw === false || strlen($raw) !== 256) {
        return null;
    }

    return base64_encode($raw);
}

function load_private_key()
{
    if (!is_file(RSA_PRIVATE_KEY_FILE) || !is_readable(RSA_PRIVATE_KEY_FILE)) {
        return false;
    }

    $pem = file_get_contents(RSA_PRIVATE_KEY_FILE);
    if ($pem === false) {
        return false;
    }

    return openssl_pkey_get_private($pem);
}

function decrypt_rsa_value(?string $cipherText, $privateKey): array
{
    if ($cipherText === null || trim($cipherText) === '') {
        return [null, 'empty'];
    }

    $cipherText = str_replace(' ', '+', trim($cipherText));
    $cipherRaw = base64_decode($cipherText, true);
    if ($cipherRaw === false) {
        return [null, 'invalid-base64'];
    }

    $plainText = '';
    if (!openssl_private_decrypt($cipherRaw, $plainText, $privateKey, OPENSSL_PKCS1_PADDING)) {
        return [null, 'decryption-failed'];
    }

    $plainText = trim($plainText);
    if (!is_numeric($plainText)) {
        return [null, 'non-numeric'];
    }

    return [(float) $plainText, 'success'];
}
