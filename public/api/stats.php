<?php
// public/api/stats.php

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';

$db = getDatabaseConnection();

try {
    // 1. Total keseluruhan per kategori
    $stmtTotal = $db->query("
        SELECT category, COUNT(*) as count 
        FROM egg_sort_results 
        GROUP BY category
    ");
    $rawTotal = $stmtTotal->fetchAll();
    $totals = ['ringan' => 0, 'sedang' => 0, 'berat' => 0, 'busuk' => 0];
    foreach ($rawTotal as $row) {
        $totals[$row['category']] = (int)$row['count'];
    }

    // 2. Total hari ini per kategori (Waktu server lokal)
    $stmtToday = $db->query("
        SELECT category, COUNT(*) as count 
        FROM egg_sort_results 
        WHERE DATE(created_at) = CURDATE()
        GROUP BY category
    ");
    $rawToday = $stmtToday->fetchAll();
    $today = ['ringan' => 0, 'sedang' => 0, 'berat' => 0, 'busuk' => 0];
    foreach ($rawToday as $row) {
        $today[$row['category']] = (int)$row['count'];
    }

    // 3. 10 Data terbaru
    $stmtLatest = $db->query("
        SELECT id, weight, gas_value, category, DATE_FORMAT(created_at, '%d-%m-%Y %H:%i:%s') as formatted_time
        FROM egg_sort_results 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $latest = $stmtLatest->fetchAll();

    // 4. Data 7 hari terakhir untuk grafik
    // Kita isi array kosong untuk 7 hari terakhir agar harinya lengkap meskipun kosong
    $chartData = [];
    for ($i = 6; $i >= 0; $i--) {
        $dateStr = date('Y-m-d', strtotime("-$i days"));
        $dateLabel = date('d M', strtotime("-$i days"));
        $chartData[$dateStr] = [
            'label' => $dateLabel,
            'ringan' => 0,
            'sedang' => 0,
            'berat' => 0,
            'busuk' => 0
        ];
    }

    // Query untuk mengambil hitungan per tanggal & kategori dalam 7 hari terakhir
    $stmtChart = $db->query("
        SELECT DATE(created_at) as date, category, COUNT(*) as count
        FROM egg_sort_results
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at), category
    ");
    $rawChart = $stmtChart->fetchAll();
    foreach ($rawChart as $row) {
        $date = $row['date'];
        if (isset($chartData[$date])) {
            $chartData[$date][$row['category']] = (int)$row['count'];
        }
    }

    // Format ulang agar mudah dibaca oleh Chart.js (arrays of labels & dataset data)
    $formattedChart = [
        'labels' => [],
        'ringan' => [],
        'sedang' => [],
        'berat' => [],
        'busuk' => []
    ];
    foreach ($chartData as $date => $data) {
        $formattedChart['labels'][] = $data['label'];
        $formattedChart['ringan'][] = $data['ringan'];
        $formattedChart['sedang'][] = $data['sedang'];
        $formattedChart['berat'][] = $data['berat'];
        $formattedChart['busuk'][] = $data['busuk'];
    }

    echo json_encode([
        'success' => true,
        'totals' => $totals,
        'today' => $today,
        'latest' => $latest,
        'chart' => $formattedChart
    ]);

} catch (PDOException $e) {
    error_log("Database Stats Error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Gagal memuat data statistik.']);
}
