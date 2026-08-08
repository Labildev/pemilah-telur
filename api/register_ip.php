<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/plain");

// Ambil IP dari parameter GET (dikirim oleh ESP32), atau gunakan REMOTE_ADDR sebagai fallback
$ip = isset($_GET['ip']) ? trim($_GET['ip']) : $_SERVER['REMOTE_ADDR'];

// Validasi sederhana agar tidak menyimpan string sembarangan
if(filter_var($ip, FILTER_VALIDATE_IP)) {
    // Simpan ke file esp32_ip.txt di root folder
    file_put_contents(__DIR__ . '/../esp32_ip.txt', $ip);
    echo "OK: IP $ip berhasil didaftarkan ke server Laptop.";
} else {
    echo "ERROR: IP tidak valid.";
}
?>
