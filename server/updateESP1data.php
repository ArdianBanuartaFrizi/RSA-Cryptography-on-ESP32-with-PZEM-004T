<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

require_post();
$fields = ['tegangan_1', 'arus_1', 'daya_1', 'energi_1'];
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

    // Menjaga perilaku proyek awal: hapus data dari tanggal sebelumnya.
    if (KEEP_ONLY_TODAY) {
        $conn->query('DELETE FROM esp_1 WHERE waktu_1 < CURDATE()');
    }

    $stmt = $conn->prepare(
        'INSERT INTO esp_1 (tegangan_1, arus_1, daya_1, energi_1, waktu_1) VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->bind_param('ssss', $data['tegangan_1'], $data['arus_1'], $data['daya_1'], $data['energi_1']);
    $stmt->execute();
    $insertId = $stmt->insert_id;
    $stmt->close();

    $conn->commit();
    json_response(['success' => true, 'message' => 'Encrypted measurement saved.', 'id' => $insertId]);
} catch (Throwable $e) {
    if ($conn instanceof mysqli) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
    }
    error_log('[updateESP1data] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Gagal menyimpan data. Periksa konfigurasi database dan tabel.'], 500);
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
