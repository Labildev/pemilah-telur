# Sistem Pemilah Telur — Versi XAMPP

Dashboard monitoring hasil pemilahan telur otomatis berbasis ESP32 — **versi ini dirancang khusus untuk dijalankan via XAMPP** (tanpa Docker).

> 💡 Versi Docker tersedia di folder `../pemilah-telur/` jika Anda ingin menggunakan container.

---

## 📁 Struktur Folder

```
pemilah-telur-xampp/
├── config/
│   └── database.php        ← Konfigurasi koneksi MySQL XAMPP
├── database/
│   └── schema.sql          ← Script SQL untuk membuat database & tabel
├── public/                 ← Letakkan folder ini di htdocs XAMPP
│   ├── index.php           ← Halaman dashboard monitoring utama
│   ├── history.php         ← Riwayat data & export CSV
│   ├── test.php            ← Panel kalibrasi & uji aktuator (WebSocket)
│   ├── api/
│   │   ├── sort-result.php ← Endpoint POST untuk ESP32
│   │   └── stats.php       ← Endpoint GET untuk dashboard
│   └── assets/
│       ├── css/style.css
│       └── js/main.js
└── README.md               ← File ini
```

---

## ✅ Persyaratan Sistem

- **XAMPP** versi 7.4+ (dengan Apache + MySQL aktif)
- **PHP** 7.4 atau lebih baru (sudah termasuk dalam XAMPP)
- **Browser** modern (Chrome, Firefox, Edge)
- **ESP32** dengan firmware yang sudah dikonfigurasi (opsional, untuk pengiriman data sensor)

---

## 🚀 Langkah Instalasi (XAMPP)

### Langkah 1 — Salin Folder ke htdocs

Salin **seluruh folder `pemilah-telur-xampp`** ke dalam direktori `htdocs` XAMPP Anda:

| OS | Lokasi htdocs |
|---|---|
| **Windows** | `C:\xampp\htdocs\` |
| **Linux** | `/opt/lampp/htdocs/` |
| **macOS** | `/Applications/XAMPP/htdocs/` |

Setelah disalin, struktur menjadi:
```
C:\xampp\htdocs\pemilah-telur-xampp\   ← Windows
/opt/lampp/htdocs/pemilah-telur-xampp\ ← Linux
```

### Langkah 2 — Jalankan XAMPP

1. Buka **XAMPP Control Panel**
2. Klik **Start** pada modul **Apache**
3. Klik **Start** pada modul **MySQL**
4. Pastikan keduanya berstatus **Running** (hijau)

### Langkah 3 — Buat Database via phpMyAdmin

1. Buka browser, akses: **`http://localhost/phpmyadmin`**
2. Klik tab **SQL** di bagian atas
3. Copy-paste isi file `database/schema.sql` ke dalam kotak SQL:
   ```sql
   CREATE DATABASE IF NOT EXISTS egg_sorter;
   USE egg_sorter;

   CREATE TABLE IF NOT EXISTS egg_sort_results (
       id INT AUTO_INCREMENT PRIMARY KEY,
       weight DECIMAL(5, 2) NOT NULL,
       gas_value INT NOT NULL,
       category ENUM('ringan', 'sedang', 'berat', 'busuk') NOT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );

   CREATE INDEX idx_category ON egg_sort_results(category);
   CREATE INDEX idx_created_at ON egg_sort_results(created_at);
   ```
4. Klik **Go** / **Execute**
5. Database `egg_sorter` dan tabel `egg_sort_results` akan terbuat otomatis

### Langkah 4 — Sesuaikan Konfigurasi Database (jika perlu)

Buka file `config/database.php`. Secara default sudah dikonfigurasi untuk XAMPP standar:

```php
define('DB_HOST', 'localhost');  // Host MySQL (default XAMPP)
define('DB_USER', 'root');       // User MySQL (default XAMPP)
define('DB_PASS', '');           // Password (kosong = default XAMPP)
define('DB_NAME', 'egg_sorter'); // Nama database
define('ESP32_API_KEY', 'rahasia123'); // API Key untuk ESP32
```

> ⚠️ Jika XAMPP Anda memiliki password MySQL, ubah `DB_PASS` sesuai password Anda.

### Langkah 5 — Akses Dashboard

Karena project sudah dilengkapi file `.htaccess` di root folder, Anda dapat mengakses dashboard dan halaman lainnya secara langsung tanpa perlu menyertakan folder `public/`:

| Halaman | URL Utama (Direkomendasikan) | URL Alternatif (Manual) |
|---|---|---|
| **Dashboard Monitoring** | `http://localhost/pemilah-telur-xampp/` | `http://localhost/pemilah-telur-xampp/public/index.php` |
| **Riwayat Data** | `http://localhost/pemilah-telur-xampp/history.php` | `http://localhost/pemilah-telur-xampp/public/history.php` |
| **Uji & Kalibrasi** | `http://localhost/pemilah-telur-xampp/test.php` | `http://localhost/pemilah-telur-xampp/public/test.php` |

---

## 🔌 Konfigurasi ESP32

### Cari IP Komputer Anda

ESP32 memerlukan IP lokal komputer untuk mengirim data.

- **Windows**: Buka CMD → `ipconfig` → cari *IPv4 Address* (misal: `192.168.1.50`)
- **Linux/macOS**: Buka Terminal → `hostname -I` (misal: `192.168.1.50`)

### Setup Firmware ESP32

Buka file `egg_sorter_esp32/egg_sorter_esp32.ino` di Arduino IDE, sesuaikan:

```cpp
const char* WIFI_SSID     = "NAMA_WIFI_KAMU";
const char* WIFI_PASSWORD = "PASSWORD_WIFI_KAMU";

// URL untuk XAMPP (ganti IP dengan IP komputer Anda)
// Catatan: Karena adanya .htaccess, segmen /public/ bisa dihilangkan
const char* BACKEND_URL = "http://192.168.1.50/pemilah-telur-xampp/api/sort-result.php";

const char* API_KEY = "rahasia123";  // Harus sama dengan di config/database.php
```

Upload firmware ke ESP32, lalu buka **Serial Monitor** (baud 115200) untuk memantau koneksi.

---

## 🔌 API Endpoint

### `POST /api/sort-result.php` (Atau `/public/api/sort-result.php`)

Digunakan ESP32 untuk mengirim data hasil sortir.

**Headers:**
```
Content-Type: application/json
X-API-Key: rahasia123
```

**Request Body:**
```json
{
  "weight": 52.40,
  "gas_value": 350,
  "category": "ringan"
}
```

**Response (201 Created):**
```json
{
  "message": "Data berhasil disimpan.",
  "id": 1,
  "data": { "weight": 52.40, "gas_value": 350, "category": "ringan" }
}
```

**Kategori valid:** `ringan` | `sedang` | `berat` | `busuk`

### `GET /api/stats.php` (Atau `/public/api/stats.php`)

Digunakan dashboard untuk mengambil statistik real-time (polling tiap 3 detik).

---

## 🧪 Panel Uji & Kalibrasi (`test.php`)

Halaman ini berkomunikasi langsung dengan ESP32 via **WebSocket** (bukan via server PHP).

1. Pastikan ESP32 sudah diupload firmware utama dan terhubung ke WiFi
2. Buka `http://localhost/pemilah-telur-xampp/test.php` (atau `http://localhost/pemilah-telur-xampp/public/test.php`)
3. Masukkan **IP Address ESP32** (cek di Serial Monitor Arduino IDE)
4. Klik **Hubungkan**
5. Gunakan panel yang tersedia untuk:
   - Melihat data sensor real-time (berat, gas, jarak)
   - Kalibrasi timbangan HX711 (Tare + Kalibrasi faktor)
   - Mengatur sudut kalibrasi setiap servo
   - Mengatur threshold sensor (gas busuk, jarak deteksi, batas berat)
   - Override manual untuk menguji gerakan servo

---

## ❓ Troubleshooting

| Masalah | Solusi |
|---|---|
| Halaman tidak bisa dibuka | Pastikan Apache XAMPP sudah **Start** |
| Error koneksi database | Pastikan MySQL XAMPP sudah **Start** dan database `egg_sorter` sudah dibuat |
| ESP32 tidak bisa kirim data | Pastikan IP di firmware benar dan ESP32 terhubung ke WiFi yang sama |
| Password MySQL bukan kosong | Edit `config/database.php`, ubah `DB_PASS` sesuai password Anda |
| Port 80 sudah dipakai | Ganti port Apache di XAMPP Control Panel (misal ke 8081), lalu akses `http://localhost:8081/...` |

---

## 📝 Perbedaan dengan Versi Docker

| Aspek | Versi Docker (`pemilah-telur/`) | Versi XAMPP (`pemilah-telur-xampp/`) |
|---|---|---|
| **Web server** | Apache dalam container | Apache XAMPP lokal |
| **Database** | MySQL dalam container | MySQL XAMPP lokal |
| **Cara jalankan** | `docker compose up -d` | Klik Start di XAMPP Control Panel |
| **URL akses** | `http://localhost:8080/` | `http://localhost/pemilah-telur-xampp/` (Berkat `.htaccess`) |
| **Konfigurasi DB** | Via environment variable `.env` | Edit langsung `config/database.php` |
| **Setup database** | Otomatis dari `schema.sql` | Manual via phpMyAdmin |
