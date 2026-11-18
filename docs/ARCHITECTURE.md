# Arsitektur Sistem

## Komponen

1. **ESP32**: membaca sensor dan melakukan enkripsi RSA menggunakan public key.
2. **PZEM-004T**: menyediakan nilai tegangan, arus, daya, dan energi.
3. **Apache/PHP di Laragon**: menyediakan public key, menerima ciphertext, menyimpan data, dan melakukan dekripsi.
4. **MySQL**: menyimpan ciphertext Base64 pada tabel `esp_1` dan `esp_1no`.
5. **OpenSSL (PHP)**: membuat pasangan RSA-2048 dan mendekripsi ciphertext dengan private key.
6. **Mbed TLS (ESP32)**: mem-parsing public key dan mengenkripsi data.

## Alur kunci

- `generate_keys.php` membuat pasangan RSA-2048 melalui PHP OpenSSL.
- `public_key.php` hanya mengirim public key kepada ESP32.
- ESP32 tidak pernah menerima atau menyimpan private key.
- PHP menyimpan private key di luar direktori web publik.

## Alur data

1. ESP32 membaca empat nilai sensor.
2. Setiap nilai diubah menjadi string angka.
3. ESP32 mengenkripsi string dengan RSAES-PKCS1-v1_5.
4. Ciphertext diubah ke Base64 dan URL-encoded.
5. ESP32 mengirim data ke dua endpoint PHP.
6. PHP memvalidasi bentuk Base64 dan panjang ciphertext RSA-2048.
7. PHP menyimpan ciphertext ke MySQL menggunakan prepared statement.
8. Endpoint pembacaan mengambil snapshot terbaru dan mendekripsinya dengan private key.

## Batas keamanan

Enkripsi RSA pada payload tidak menggantikan HTTPS, autentikasi perangkat, otorisasi API, maupun pemeriksaan integritas. Untuk deployment nyata, gunakan TLS, autentikasi device, rotasi kunci yang terencana, dan desain hybrid encryption untuk payload yang lebih besar.
