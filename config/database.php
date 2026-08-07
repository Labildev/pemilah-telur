<?php
// config/database.php
// =====================================================================
// KONFIGURASI DATABASE UNTUK XAMPP
// Sesuaikan nilai di bawah jika kredensial MySQL XAMPP Anda berbeda.
// =====================================================================

define('DB_HOST', 'localhost');       // Host MySQL XAMPP (biasanya localhost)
define('DB_USER', 'root');            // User default XAMPP
define('DB_PASS', '');                // Password default XAMPP (kosong)
define('DB_NAME', 'egg_sorter');      // Nama database yang sudah dibuat di phpMyAdmin

// API Key untuk autentikasi ESP32
// Pastikan nilai ini sama dengan yang ada di firmware ESP32 Anda
define('ESP32_API_KEY', 'rahasia123');

// Fungsi untuk mendapatkan koneksi database PDO
function getDatabaseConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Koneksi ke database gagal. Pastikan MySQL XAMPP sudah berjalan dan database "egg_sorter" sudah dibuat.']);
        exit;
    }
}
