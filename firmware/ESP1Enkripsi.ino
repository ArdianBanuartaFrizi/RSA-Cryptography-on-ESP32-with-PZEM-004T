#include <WiFi.h>
#include <HTTPClient.h>
#include <PZEM004Tv30.h>
#include <stdlib.h>
#include <string.h>
#include "mbedtls/pk.h"
#include "mbedtls/rsa.h"
#include "mbedtls/ctr_drbg.h"
#include "mbedtls/entropy.h"
#include "mbedtls/base64.h"
#include "secrets.h"

// ======================== KONFIGURASI ========================
#define PZEM_RX_PIN 16
#define PZEM_TX_PIN 17
#define PZEM_SERIAL Serial1

const unsigned long SEND_INTERVAL_MS = 3000; // 3 detik; ubah sesuai kebutuhan.
const char* PUBLIC_KEY_PATH = "/public_key.php";
const char* HISTORY_PATH = "/updateESP1data.php";
const char* LATEST_PATH = "/updateESP1no.php";

PZEM004Tv30 pzem(PZEM_SERIAL, PZEM_RX_PIN, PZEM_TX_PIN);
String publicKey = "";
unsigned long previousMillis = 0;

String buildUrl(const char* path) {
  String base = String(SERVER_BASE_URL);
  while (base.endsWith("/")) base.remove(base.length() - 1);
  return base + String(path);
}

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;

  Serial.printf("Menghubungkan ke Wi-Fi: %s\n", WIFI_SSID);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  unsigned long started = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - started < 20000) {
    delay(500);
    Serial.print(".");
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWi-Fi terhubung.");
    Serial.print("IP ESP32: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nWi-Fi belum terhubung. Akan dicoba kembali.");
  }
}

bool getPublicKey() {
  if (WiFi.status() != WL_CONNECTED) return false;

  HTTPClient http;
  const String url = buildUrl(PUBLIC_KEY_PATH);
  http.setTimeout(10000);

  if (!http.begin(url)) {
    Serial.println("Gagal memulai HTTP request untuk public key.");
    return false;
  }

  const int code = http.GET();
  if (code == HTTP_CODE_OK) {
    String received = http.getString();
    received.trim();
    if (received.indexOf("-----BEGIN PUBLIC KEY-----") >= 0 &&
        received.indexOf("-----END PUBLIC KEY-----") >= 0) {
      publicKey = received;
      Serial.println("Public key RSA berhasil diterima.");
      http.end();
      return true;
    }
  }

  Serial.printf("Gagal mengambil public key. HTTP code: %d\n", code);
  http.end();
  return false;
}

// RSAES-PKCS1-v1_5 dipakai agar kompatibel dengan OPENSSL_PKCS1_PADDING di PHP.
String encryptRSA(const String& plainText) {
  if (publicKey.isEmpty()) return "";

  int ret = 0;
  String encoded = "";
  unsigned char* cipher = nullptr;
  unsigned char* base64Buffer = nullptr;
  size_t keyLength = 0;
  size_t cipherLength = 0;
  size_t base64Capacity = 0;
  size_t base64Length = 0;

  mbedtls_pk_context pk;
  mbedtls_entropy_context entropy;
  mbedtls_ctr_drbg_context ctrDrbg;
  mbedtls_pk_init(&pk);
  mbedtls_entropy_init(&entropy);
  mbedtls_ctr_drbg_init(&ctrDrbg);

  const char* personalization = "esp32_rsa_pzem";
  ret = mbedtls_ctr_drbg_seed(
    &ctrDrbg,
    mbedtls_entropy_func,
    &entropy,
    reinterpret_cast<const unsigned char*>(personalization),
    strlen(personalization)
  );
  if (ret != 0) {
    Serial.printf("[RSA] Gagal inisialisasi random generator: -0x%04X\n", -ret);
    goto cleanup;
  }

  ret = mbedtls_pk_parse_public_key(
    &pk,
    reinterpret_cast<const unsigned char*>(publicKey.c_str()),
    publicKey.length() + 1
  );
  if (ret != 0) {
    Serial.printf("[RSA] Public key tidak dapat dibaca: -0x%04X\n", -ret);
    goto cleanup;
  }

  if (!mbedtls_pk_can_do(&pk, MBEDTLS_PK_RSA)) {
    Serial.println("[RSA] Public key bukan RSA.");
    goto cleanup;
  }

  // Samakan skema padding dengan PHP OpenSSL.
  mbedtls_rsa_set_padding(mbedtls_pk_rsa(pk), MBEDTLS_RSA_PKCS_V15, 0);

  keyLength = mbedtls_pk_get_len(&pk); // 256 byte untuk RSA-2048.
  if (plainText.length() > keyLength - 11) {
    Serial.println("[RSA] Plaintext terlalu panjang untuk RSA PKCS#1 v1.5.");
    goto cleanup;
  }

  cipher = static_cast<unsigned char*>(malloc(keyLength));
  if (cipher == nullptr) {
    Serial.println("[RSA] Alokasi buffer ciphertext gagal.");
    goto cleanup;
  }

  ret = mbedtls_pk_encrypt(
    &pk,
    reinterpret_cast<const unsigned char*>(plainText.c_str()),
    plainText.length(),
    cipher,
    &cipherLength,
    keyLength,
    mbedtls_ctr_drbg_random,
    &ctrDrbg
  );
  if (ret != 0) {
    Serial.printf("[RSA] Enkripsi gagal: -0x%04X\n", -ret);
    goto cleanup;
  }

  // Panjang Base64 maksimum yang diperlukan: 4 * ceil(cipherLength / 3).
  base64Capacity = 4 * ((cipherLength + 2) / 3);
  base64Buffer = static_cast<unsigned char*>(malloc(base64Capacity + 1));
  if (base64Buffer == nullptr) {
    Serial.println("[RSA] Alokasi buffer Base64 gagal.");
    goto cleanup;
  }

  ret = mbedtls_base64_encode(base64Buffer, base64Capacity, &base64Length, cipher, cipherLength);
  if (ret != 0) {
    Serial.printf("[RSA] Encoding Base64 gagal: -0x%04X\n", -ret);
    goto cleanup;
  }

  base64Buffer[base64Length] = '\0';
  encoded = String(reinterpret_cast<char*>(base64Buffer));

cleanup:
  if (cipher != nullptr) free(cipher);
  if (base64Buffer != nullptr) free(base64Buffer);
  mbedtls_pk_free(&pk);
  mbedtls_ctr_drbg_free(&ctrDrbg);
  mbedtls_entropy_free(&entropy);
  return encoded;
}

String urlEncode(const String& value) {
  const char hex[] = "0123456789ABCDEF";
  String result;
  result.reserve(value.length() * 3);

  for (size_t i = 0; i < value.length(); i++) {
    const uint8_t c = static_cast<uint8_t>(value[i]);
    const bool unreserved =
      (c >= 'A' && c <= 'Z') || (c >= 'a' && c <= 'z') ||
      (c >= '0' && c <= '9') || c == '-' || c == '_' || c == '.' || c == '~';

    if (unreserved) {
      result += static_cast<char>(c);
    } else {
      result += '%';
      result += hex[(c >> 4) & 0x0F];
      result += hex[c & 0x0F];
    }
  }
  return result;
}

bool postForm(const String& url, const String& body, const char* label) {
  HTTPClient http;
  http.setTimeout(10000);
  if (!http.begin(url)) {
    Serial.printf("[%s] Tidak dapat memulai koneksi HTTP.\n", label);
    return false;
  }

  http.addHeader("Content-Type", "application/x-www-form-urlencoded");
  const int code = http.POST(body);
  const String response = http.getString();

  Serial.printf("[%s] HTTP: %d | Response: %s\n", label, code, response.c_str());
  http.end();
  return code >= 200 && code < 300;
}

void sendMeasurements() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Wi-Fi terputus; data belum dikirim.");
    return;
  }

  if (publicKey.isEmpty() && !getPublicKey()) {
    Serial.println("Public key belum tersedia; pengiriman dibatalkan.");
    return;
  }

  const float voltage = pzem.voltage();
  const float current = pzem.current();
  const float power = pzem.power();
  const float energy = pzem.energy(); // PZEM004Tv30 umumnya melaporkan energi dalam kWh.

  if (isnan(voltage) || isnan(current) || isnan(power) || isnan(energy)) {
    Serial.println("Pembacaan PZEM tidak valid. Periksa sensor dan kabel serial.");
    return;
  }

  Serial.println("\n=== Pembacaan PZEM-004T ===");
  Serial.printf("Tegangan : %.2f V\n", voltage);
  Serial.printf("Arus     : %.2f A\n", current);
  Serial.printf("Daya     : %.2f W\n", power);
  Serial.printf("Energi   : %.3f kWh\n", energy);

  // Enkripsi satu kali; ciphertext yang sama dikirim ke dua tabel.
  const String encVoltage = encryptRSA(String(voltage, 2));
  const String encCurrent = encryptRSA(String(current, 2));
  const String encPower = encryptRSA(String(power, 2));
  const String encEnergy = encryptRSA(String(energy, 3));

  if (encVoltage.isEmpty() || encCurrent.isEmpty() || encPower.isEmpty() || encEnergy.isEmpty()) {
    Serial.println("Enkripsi tidak lengkap. Data tidak dikirim.");
    return;
  }

  const String historyBody =
    "tegangan_1=" + urlEncode(encVoltage) +
    "&arus_1=" + urlEncode(encCurrent) +
    "&daya_1=" + urlEncode(encPower) +
    "&energi_1=" + urlEncode(encEnergy);

  const String latestBody =
    "tegangan_1no=" + urlEncode(encVoltage) +
    "&arus_1no=" + urlEncode(encCurrent) +
    "&daya_1no=" + urlEncode(encPower) +
    "&energi_1no=" + urlEncode(encEnergy);

  postForm(buildUrl(HISTORY_PATH), historyBody, "HISTORY");
  postForm(buildUrl(LATEST_PATH), latestBody, "LATEST");
}

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\nRSA Database Security - ESP32 + PZEM-004T");

  connectWiFi();
  if (WiFi.status() == WL_CONNECTED) {
    getPublicKey();
    sendMeasurements();
  }
  previousMillis = millis();
}

void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    connectWiFi();
  }

  const unsigned long now = millis();
  if (now - previousMillis >= SEND_INTERVAL_MS) {
    previousMillis = now;
    sendMeasurements();
  }
  delay(10);
}
