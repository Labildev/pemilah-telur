<?php
// public/api/sort-result.php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

// Hanya izinkan HTTP POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['error' => 'Method not allowed. Gunakan POST.']);
    exit;
}

// Ambil API Key dari Header (X-API-Key) atau Query Parameter (api_key)
$headers = getallheaders();
$apiKey = '';

if (isset($headers['X-API-Key'])) {
    $apiKey = $headers['X-API-Key'];
} elseif (isset($headers['x-api-key'])) {
    $apiKey = $headers['x-api-key'];
} elseif (isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
}

// Validasi API Key
if (empty($apiKey) || $apiKey !== ESP32_API_KEY) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized. API Key tidak valid atau kosong.']);
    exit;
}

// Ambil raw JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if ($data === null) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Payload bukan format JSON yang valid.']);
    exit;
}

// Validasi keberadaan field data
$weight = isset($data['weight']) ? filter_var($data['weight'], FILTER_VALIDATE_FLOAT) : false;
$gasValue = isset($data['gas_value']) ? filter_var($data['gas_value'], FILTER_VALIDATE_INT) : false;
$category = isset($data['category']) ? trim(strtolower($data['category'])) : '';

if ($weight === false || $gasValue === false || empty($category)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Data tidak lengkap atau format salah (weight, gas_value, category wajib diisi).']);
    exit;
}

// Validasi kategori telur
$validCategories = ['ringan', 'sedang', 'berat', 'busuk'];
if (!in_array($category, $validCategories)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Kategori tidak valid. Pilihan: ringan, sedang, berat, busuk.']);
    exit;
}

// Simpan ke database menggunakan PDO Prepared Statement
$db = getDatabaseConnection();
try {
    $query = "INSERT INTO egg_sort_results (weight, gas_value, category) VALUES (:weight, :gas_value, :category)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':weight' => $weight,
        ':gas_value' => $gasValue,
        ':category' => $category
    ]);

    header('HTTP/1.1 201 Created');
    echo json_encode([
        'message' => 'Data berhasil disimpan.',
        'id' => $db->lastInsertId(),
        'data' => [
            'weight' => $weight,
            'gas_value' => $gasValue,
            'category' => $category
        ]
    ]);
} catch (PDOException $e) {
    error_log("Database Insert Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Gagal menyimpan data ke database.']);
}
