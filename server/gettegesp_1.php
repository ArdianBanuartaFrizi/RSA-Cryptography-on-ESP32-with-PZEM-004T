<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    json_response(['success' => false, 'message' => 'Gunakan HTTP GET.'], 405);
}

$privateKey = load_private_key();
if ($privateKey === false) {
    json_response(['success' => false, 'message' => 'Private key tidak ditemukan atau tidak valid.'], 503);
}

$conn = null;
try {
    $conn = db_connect();
    $result = $conn->query(
        'SELECT waktu_1no, tegangan_1no, arus_1no, daya_1no, energi_1no FROM esp_1no ORDER BY id DESC LIMIT 1'
    );

    $response = [];
    if ($row = $result->fetch_assoc()) {
        [$voltage, $voltageStatus] = decrypt_rsa_value($row['tegangan_1no'], $privateKey);
        [$current, $currentStatus] = decrypt_rsa_value($row['arus_1no'], $privateKey);
        [$power, $powerStatus] = decrypt_rsa_value($row['daya_1no'], $privateKey);
        [$energy, $energyStatus] = decrypt_rsa_value($row['energi_1no'], $privateKey);

        $response[] = [
            'waktu_1no' => $row['waktu_1no'],
            'tegangan_1no' => $voltage,
            'arus_1no' => $current,
            'daya_1no' => $power,
            'energi_1no' => $energy,
            'status' => [
                'tegangan' => $voltageStatus,
                'arus' => $currentStatus,
                'daya' => $powerStatus,
                'energi' => $energyStatus,
            ],
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('[gettegesp_1] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Gagal mengambil data. Periksa konfigurasi database dan tabel.'], 500);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
