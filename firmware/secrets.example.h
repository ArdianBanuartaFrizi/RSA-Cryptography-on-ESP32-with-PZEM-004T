#pragma once

// Salin file ini menjadi secrets.h, lalu isi sesuai jaringan dan IP komputer.
// Jangan commit secrets.h ke GitHub.
#define WIFI_SSID "NAMA_WIFI"
#define WIFI_PASSWORD "PASSWORD_WIFI"

// Ganti dengan IPv4 komputer yang menjalankan Laragon.
// Contoh: http://192.168.1.10/rsa-esp32-pzem/server
// Jangan gunakan localhost atau 127.0.0.1 pada ESP32.
#define SERVER_BASE_URL "http://192.168.1.10/rsa-esp32-pzem/server"
