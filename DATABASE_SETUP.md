# Pengaturan Database MySQL (Laragon)

Dokumen ini menjelaskan cara membuat database dan tabel yang dipakai oleh proyek.

## 1. Jalankan MySQL di Laragon

1. Buka Laragon.
2. Klik **Start All** atau pastikan Apache dan MySQL sudah berjalan.
3. Klik **Database** untuk membuka HeidiSQL (jika tersedia).
4. Gunakan koneksi lokal dengan pengaturan default Laragon:

| Parameter | Nilai default |
|---|---|
| Host | `127.0.0.1` atau `localhost` |
| Port | `3306` |
| User | `root` |
| Password | kosong (default instalasi Laragon) |

Jika password MySQL di instalasi Laragon kamu berbeda, gunakan password yang benar pada `server/config.php`.

## 2. Buat database dan tabel

Cara termudah:

1. Buka HeidiSQL dari Laragon.
2. Hubungkan ke MySQL lokal.
3. Buka file `database/schema.sql` dari folder proyek.
4. Salin seluruh isi file ke tab Query di HeidiSQL.
5. Klik tombol **Run** (atau tekan F9).
6. Refresh daftar database. Database `rsa_pzem_db` dan tabel `esp_1` serta `esp_1no` akan muncul.

Alternatif phpMyAdmin:

1. Buka `http://localhost/phpmyadmin` jika phpMyAdmin terpasang.
2. Pilih tab **Import**.
3. Pilih file `database/schema.sql`.
4. Klik **Import/Go**.

File SQL sudah berisi perintah `CREATE DATABASE`, `USE`, dan `CREATE TABLE`, sehingga database tidak perlu dibuat manual terlebih dahulu.

## 3. Struktur database

### Tabel `esp_1`

Menyimpan riwayat pengukuran terenkripsi.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | ID record |
| `tegangan_1` | VARCHAR(512) | Ciphertext tegangan dalam Base64 |
| `arus_1` | VARCHAR(512) | Ciphertext arus dalam Base64 |
| `daya_1` | VARCHAR(512) | Ciphertext daya dalam Base64 |
| `energi_1` | VARCHAR(512) | Ciphertext energi dalam Base64 |
| `waktu_1` | DATETIME | Waktu penyimpanan server |

Endpoint `updateESP1data.php` menghapus record yang tanggalnya sebelum hari ini, kemudian memasukkan pengukuran baru. Ini mempertahankan perilaku aplikasi sumber. Jika ingin menyimpan riwayat lintas hari, ubah `KEEP_ONLY_TODAY` menjadi `false` di `server/config.php`.

### Tabel `esp_1no`

Menyimpan satu pengukuran paling baru.

| Kolom | Tipe | Keterangan |
|---|---|---|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | ID record |
| `tegangan_1no` | VARCHAR(512) | Ciphertext tegangan dalam Base64 |
| `arus_1no` | VARCHAR(512) | Ciphertext arus dalam Base64 |
| `daya_1no` | VARCHAR(512) | Ciphertext daya dalam Base64 |
| `energi_1no` | VARCHAR(512) | Ciphertext energi dalam Base64 |
| `waktu_1no` | DATETIME | Waktu penyimpanan server |

Endpoint `updateESP1no.php` menghapus record sebelumnya dan memasukkan satu record terbaru.

## 4. Mengapa kolom pengukuran menggunakan VARCHAR?

Database tidak menyimpan angka sensor secara langsung. ESP32 mengenkripsi setiap angka menggunakan RSA-2048, kemudian mengubah ciphertext menjadi Base64 agar dapat dikirim melalui HTTP form.

Ciphertext RSA-2048 berukuran 256 byte dan representasi Base64-nya sekitar 344 karakter. Karena itu, `VARCHAR(512)` cukup untuk menyimpan setiap ciphertext dengan ruang tambahan.

Jangan mengubah kolom tersebut menjadi `FLOAT`, `DOUBLE`, atau `DECIMAL`, karena isi kolom bukan angka biasa sebelum proses dekripsi.

## 5. Konfigurasi koneksi PHP

Buka `server/config.php`. Nilai default sudah sesuai instalasi Laragon yang umum:

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'rsa_pzem_db');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Pada file proyek, konfigurasi ini dibuat dengan environment variables dan fallback ke nilai default tersebut. Jika MySQL kamu memakai password, isi `DB_PASS` melalui environment variable atau ubah konfigurasi lokal yang tidak akan diunggah ke GitHub.

## 6. (Opsional) Buat user database khusus

Untuk demo lokal, konfigurasi default Laragon `root` tanpa password biasanya paling sederhana. Untuk pemisahan hak akses yang lebih baik, kamu dapat membuat user khusus aplikasi. Jalankan sebagai admin MySQL dan ganti password contoh dengan password kuat milikmu:

```sql
CREATE USER IF NOT EXISTS 'rsa_pzem_user'@'localhost' IDENTIFIED BY 'GANTI_DENGAN_PASSWORD_KUAT';
GRANT SELECT, INSERT, DELETE ON rsa_pzem_db.* TO 'rsa_pzem_user'@'localhost';
FLUSH PRIVILEGES;
```

Kemudian ubah konfigurasi lokal PHP menjadi `DB_USER = rsa_pzem_user` dan `DB_PASS = password yang kamu buat`. Jangan commit password tersebut ke repository publik.

## 7. Query pemeriksaan database

Setelah ESP32 mengirim data, jalankan query berikut di HeidiSQL:

```sql
USE rsa_pzem_db;
SHOW TABLES;
DESCRIBE esp_1;
DESCRIBE esp_1no;

SELECT id, LENGTH(tegangan_1) AS ciphertext_length, waktu_1
FROM esp_1 ORDER BY id DESC LIMIT 10;

SELECT id, LENGTH(tegangan_1no) AS ciphertext_length, waktu_1no
FROM esp_1no ORDER BY id DESC LIMIT 5;
```

Nilai `ciphertext_length` untuk RSA-2048 Base64 normalnya sekitar 344 karakter.

## 8. Pemeriksaan setelah setup

Setelah server dan kunci RSA disiapkan:

- Buka `http://localhost/rsa-esp32-pzem/server/public_key.php`. Browser harus menampilkan teks PEM yang diawali `-----BEGIN PUBLIC KEY-----`.
- Setelah ESP32 mengirim data, tabel `esp_1` akan berisi record pengukuran hari ini.
- Tabel `esp_1no` seharusnya berisi satu record terbaru.
- Buka `http://localhost/rsa-esp32-pzem/server/gettegesp_1.php` untuk melihat data terbaru setelah didekripsi oleh PHP.

Jika belum ada data, respons JSON dari endpoint dekripsi berupa array kosong (`[]`).
