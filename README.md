# RSA-Based Database Security for ESP32 & PZEM-004T

An IoT database-security prototype that encrypts electrical measurements on an ESP32 before sending them to a PHP server and storing them in MySQL. The project uses RSA-2048 public-key encryption, Mbed TLS on ESP32, and PHP OpenSSL for server-side decryption.

**Stack:** ESP32 · PZEM-004T · RSA-2048 · Mbed TLS · PHP · OpenSSL · MySQL · Laragon

## Overview

The ESP32 reads voltage, current, power, and energy from a PZEM-004T module. It retrieves an RSA public key from the PHP server, encrypts each measurement, Base64-encodes the ciphertext, and sends the encrypted values to two PHP endpoints over HTTP.

The PHP application stores ciphertext in MySQL. The corresponding private key remains on the server and is used by `gettegesp_1.php` to decrypt the latest stored measurement when requested.

> This is an educational/local-network prototype. The included HTTP endpoints are not intended to be exposed directly to the public internet. Use HTTPS, authentication, access controls, and stronger key-management practices before any production deployment.

## Features

- RSA-2048 public-key encryption on ESP32.
- PHP OpenSSL private-key decryption.
- PZEM-004T measurement: voltage, current, power, and energy.
- MySQL storage of Base64-encoded ciphertext.
- Separate history and latest-value tables.
- Laragon-compatible local PHP/Apache/MySQL setup.
- Private RSA keys stored outside the Laragon `www` web root by default.

## How the system works

```text
PZEM-004T
    │ UART
    ▼
ESP32 ── GET public_key.php ──► PHP server
    │                              │
    │ ◄──── RSA public key ────────┘
    │
    ├─ Read voltage/current/power/energy
    ├─ Encrypt each value with RSA-2048 public key
    ├─ Base64-encode ciphertext
    │
    ├─ POST updateESP1data.php ──► MySQL: esp_1 (daily history)
    └─ POST updateESP1no.php   ──► MySQL: esp_1no (latest record)
                                      │
                                      ▼
                              gettegesp_1.php
                                      │
                         RSA private-key decryption
                                      │
                                      ▼
                           JSON with plaintext values
```

### RSA compatibility

The ESP32 firmware uses RSAES-PKCS1-v1_5 padding. PHP decrypts with `OPENSSL_PKCS1_PADDING`, so both sides use the same padding scheme. Each measurement is a short numeric string, which fits within the plaintext limit of RSA-2048 with PKCS#1 v1.5 padding.

RSA is not suitable for encrypting large files or arbitrary long messages. For larger payloads, use hybrid encryption (for example, AES-GCM for data and RSA-OAEP for key wrapping).

## Repository structure

```text
rsa-esp32-pzem/
├── database/
│   └── schema.sql
├── docs/
│   └── (project documentation)
├── firmware/
│   ├── ESP1Enkripsi.ino
│   └── secrets.example.h
├── server/
│   ├── config.php
│   ├── db.php
│   ├── helpers.php
│   ├── generate_keys.php
│   ├── public_key.php
│   ├── updateESP1data.php
│   ├── updateESP1no.php
│   └── gettegesp_1.php
├── .gitignore
├── DATABASE_SETUP.md
└── README.md
```

## Requirements

### Software

- Laragon (Apache, PHP, and MySQL/MariaDB).
- PHP extensions: `openssl`, `mysqli`.
- Arduino IDE with ESP32 board support.
- Arduino library: **PZEM004Tv30** (install through Library Manager).
- ESP32 Arduino core with the bundled Mbed TLS headers.
- HeidiSQL (included with many Laragon installations) or phpMyAdmin.

### Hardware

- ESP32 development board.
- PZEM-004T energy-monitoring module.
- Appropriate power supply, wiring, and electrical protection.

**Electrical safety:** PZEM-004T installations may involve hazardous mains voltage. Do not wire or probe live AC circuits unless qualified to do so. Use proper isolation and follow the module's datasheet.

## Installation (Laragon + MySQL)

### 1. Copy the project into Laragon

Extract/copy this repository to:

```text
C:\laragon\www\rsa-esp32-pzem\
```

The folder name can be changed, but the URL used in `firmware/secrets.h` must match it.

### 2. Start Laragon

1. Open Laragon.
2. Click **Start All**.
3. Confirm Apache and MySQL are running.

### 3. Create the MySQL database

Run the SQL file:

```text
database/schema.sql
```

Use HeidiSQL:

1. Open HeidiSQL from Laragon and connect to the local MySQL server.
2. Open a Query tab.
3. Paste the contents of `database/schema.sql`.
4. Execute the query (F9).
5. Refresh the database tree and confirm `rsa_pzem_db`, `esp_1`, and `esp_1no` exist.

Alternatively, import `database/schema.sql` through phpMyAdmin if installed. Detailed database settings and column explanations are in [`DATABASE_SETUP.md`](DATABASE_SETUP.md).

### 4. Check PHP database settings

The default `server/config.php` settings are for a typical Laragon installation:

| Setting | Default |
|---|---|
| Host | `127.0.0.1` |
| Port | `3306` |
| Database | `rsa_pzem_db` |
| Username | `root` |
| Password | empty |
| Charset | `utf8mb4` |

If your MySQL account uses a password, update the local configuration or set the `DB_PASS` environment variable. Do not commit production credentials to GitHub.

### 5. Generate the RSA key pair

The RSA private key is deliberately stored outside the web-accessible project folder. With the default Laragon location, the key directory is:

```text
C:\laragon\private\rsa-pzem\
```

Open **Laragon Terminal**, then run:

```bat
cd C:\laragon\www\rsa-esp32-pzem
php server\generate_keys.php
```

The command creates:

- `rsa_public_esp1.pem` — public key; served to ESP32.
- `rsa_private_esp1.pem` — private key; used only by PHP for decryption.

Do not upload either key to GitHub. Never place the private key inside `C:\laragon\www`.

If you intentionally need a new key pair, back up anything that must be decrypted first, then run:

```bat
php server\generate_keys.php --force
```

**Warning:** Replacing the key pair means ciphertext encrypted with the previous public key can no longer be decrypted by the new private key.

### 6. Test the public-key endpoint

On the computer running Laragon, open:

```text
http://localhost/rsa-esp32-pzem/server/public_key.php
```

The response should begin with:

```text
-----BEGIN PUBLIC KEY-----
```

If the endpoint reports that the public key is unavailable, run the key-generation command again and check the `RSA_KEY_DIR` path in `server/config.php`.

## Configure ESP32

### 1. Create the local secrets file

Copy:

```text
firmware/secrets.example.h
```

to:

```text
firmware/secrets.h
```

Edit `secrets.h`:

```cpp
#define WIFI_SSID "YOUR_WIFI_NAME"
#define WIFI_PASSWORD "YOUR_WIFI_PASSWORD"
#define SERVER_BASE_URL "http://192.168.1.10/rsa-esp32-pzem/server"
```

Replace `192.168.1.10` with the computer's actual local IPv4 address. On Windows, run `ipconfig` and find the IPv4 address of the Wi-Fi/Ethernet adapter connected to the same network as ESP32.

Do **not** use `localhost` or `127.0.0.1` in the ESP32 URL. Those addresses refer to the ESP32 itself, not the Laragon computer.

If the project folder has a different name, update the URL path accordingly.

### 2. Install the Arduino library

In Arduino IDE:

1. Open **Sketch → Include Library → Manage Libraries**.
2. Search for `PZEM004Tv30`.
3. Install the PZEM004Tv30 library.
4. Select the correct ESP32 board and COM port.

The Wi-Fi, HTTPClient, and Mbed TLS libraries are supplied by the ESP32 Arduino core.

### 3. Check the PZEM serial wiring

The firmware uses:

| ESP32 | PZEM-004T TTL |
|---|---|
| GPIO 16 (RX) | TX |
| GPIO 17 (TX) | RX |
| GND | GND (where required by the module interface) |

Confirm the exact PZEM-004T board version, voltage levels, and wiring diagram before connecting. Do not connect mains wiring based only on this table.

### 4. Upload firmware

Open `firmware/ESP1Enkripsi.ino` in Arduino IDE, confirm `secrets.h` is in the same sketch folder, select the ESP32 board and port, then upload.

Open Serial Monitor at **115200 baud**. Expected messages include Wi-Fi connection, public-key retrieval, PZEM readings, and HTTP responses from the two PHP endpoints.

The default send interval is 3 seconds, matching the actual interval value in the original sketch. Change `SEND_INTERVAL_MS` in the firmware if another interval is required.

## Database tables and API fields

| Endpoint | Method | Required form fields | Database destination |
|---|---|---|---|
| `public_key.php` | GET | — | Returns RSA public key PEM |
| `updateESP1data.php` | POST | `tegangan_1`, `arus_1`, `daya_1`, `energi_1` | `esp_1` |
| `updateESP1no.php` | POST | `tegangan_1no`, `arus_1no`, `daya_1no`, `energi_1no` | `esp_1no` |
| `gettegesp_1.php` | GET | — | Decrypts the latest row from `esp_1no` |

Ciphertext is Base64-encoded RSA-2048 output. Each encrypted value is stored as `VARCHAR(512)`, not as a numeric SQL type. The firmware URL-encodes Base64 values before sending them, so characters such as `+`, `/`, and `=` are transmitted safely.

## Verify that it works

1. Confirm Laragon's Apache and MySQL are running.
2. Confirm `public_key.php` returns a PEM public key.
3. Connect ESP32 to the same local network as the Laragon computer.
4. Check Serial Monitor for successful HTTP responses.
5. In HeidiSQL, refresh `rsa_pzem_db` and inspect `esp_1` and `esp_1no`.
6. Open:

```text
http://localhost/rsa-esp32-pzem/server/gettegesp_1.php
```

The endpoint should return a JSON array with the latest decrypted values. If no measurement has arrived yet, it returns `[]`.

## Security notes and limitations

- The private key is stored outside the web root by default. Keep it backed up and access-controlled.
- The public key is intentionally readable by ESP32. The private key must never be exposed.
- The local demo uses HTTP. HTTP does not protect the transmission against network interception or modification. Use HTTPS/TLS for deployment beyond a trusted local test network.
- RSA encryption protects confidentiality of the encrypted measurement values, but RSA alone does not prove who sent the data and does not provide message integrity. Add device authentication and message authentication/signatures for a stronger design.
- The current design encrypts each short numeric measurement separately. Use hybrid encryption for larger payloads.
- The latest-value table intentionally contains only one row. The history table, by default, retains only records from the current day, matching the original application behavior.
- Keep Wi-Fi credentials in `firmware/secrets.h` and database credentials out of public repositories.

## Original project notes

This repository is a cleaned-up and documented version of an earlier ESP32/PZEM-004T RSA experiment. The original source had mismatched POST parameter names between the firmware and PHP endpoints, placeholder database/key paths, and a retrieval query that did not return the energy field. Those integration issues are corrected in this package so the supplied firmware and PHP endpoints use matching field names and include all four measurements.

## License

No license has been assigned. Add a license file if you want to define reuse and distribution permissions.
