<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

require_post();
$fields = ['tegangan_1no', 'arus_1no', 'daya_1no', 'energi_1no'];
$data = [];
foreach ($fields as $field) {
    $cipher = validate_rsa_ciphertext($_POST[$field] ?? null);
    if ($cipher === null) {
        json_response([
            'success' => false,
            'message' => "Field {$field} tidak ada atau ciphertext Base64 RSA-2048 tidak valid."
        ], 400);
    }
    $data[$field] = $cipher;
}

$conn = null;
try {
    $conn = db_connect();
    $conn->begin_transaction();
    $conn->query('DELETE FROM esp_1no');

    $stmt = $conn->prepare(
        'INSERT INTO esp_1no (tegangan_1no, arus_1no, daya_1no, energi_1no, waktu_1no) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('ssss', $data['tegangan_1no'], $data['arus_1no'], $data['daya_1no'], $data['energi_1no']);
    $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();

    $conn->commit();
    json_response(['success' => true, 'message' => 'Latest encrypted measurement updated.', 'id' => $insertId]);
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('[updateESP1no] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Gagal memperbarui data terbaru. Periksa konfigurasi database dan tabel.'], 500);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
