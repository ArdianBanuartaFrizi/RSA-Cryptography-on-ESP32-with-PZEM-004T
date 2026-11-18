-- RSA + ESP32 + PZEM-004T
-- Jalankan file ini melalui HeidiSQL atau phpMyAdmin.
-- Catatan: kolom pengukuran bertipe VARCHAR karena berisi ciphertext RSA Base64.

CREATE DATABASE IF NOT EXISTS rsa_pzem_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE rsa_pzem_db;

-- Riwayat pengukuran. Endpoint updateESP1data.php menghapus data dari hari sebelumnya
-- sebelum menyimpan data baru, sesuai perilaku program sumber.
CREATE TABLE IF NOT EXISTS esp_1 (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tegangan_1 VARCHAR(512) NOT NULL,
  arus_1 VARCHAR(512) NOT NULL,
  daya_1 VARCHAR(512) NOT NULL,
  energi_1 VARCHAR(512) NOT NULL,
  waktu_1 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_esp1_waktu (waktu_1)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Snapshot/latest measurement. Endpoint updateESP1no.php menghapus isi tabel
-- lalu memasukkan satu pengukuran terbaru.
CREATE TABLE IF NOT EXISTS esp_1no (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tegangan_1no VARCHAR(512) NOT NULL,
  arus_1no VARCHAR(512) NOT NULL,
  daya_1no VARCHAR(512) NOT NULL,
  energi_1no VARCHAR(512) NOT NULL,
  waktu_1no DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_esp1no_waktu (waktu_1no)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Tidak ada data dummy karena ciphertext harus dihasilkan oleh ESP32
-- menggunakan public key yang berpasangan dengan private key server.
