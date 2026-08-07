<?php
// public/history.php

require_once __DIR__ . '/../config/database.php';

$db = getDatabaseConnection();

// Ambil input filter
$categoryFilter = isset($_GET['category']) ? trim(strtolower($_GET['category'])) : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Susun kondisi WHERE
$where = [];
$params = [];

if (!empty($categoryFilter) && in_array($categoryFilter, ['ringan', 'sedang', 'berat', 'busuk'])) {
    $where[] = "category = :category";
    $params[':category'] = $categoryFilter;
}
if (!empty($startDate)) {
    $where[] = "DATE(created_at) >= :start_date";
    $params[':start_date'] = $startDate;
}
if (!empty($endDate)) {
    $where[] = "DATE(created_at) <= :end_date";
    $params[':end_date'] = $endDate;
}

$whereClause = "";
if (count($where) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $where);
}

// ---------------- EXPORT CSV ----------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=riwayat_sortir_telur_' . date('Y-m-d_H-i-s') . '.csv');
    
    $output = fopen('php://output', 'w');
    // Header CSV
    fputcsv($output, ['ID', 'Berat (gram)', 'Nilai Gas (MQ-135)', 'Kategori', 'Waktu']);
    
    $query = "SELECT id, weight, gas_value, category, created_at FROM egg_sort_results $whereClause ORDER BY id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            $row['weight'],
            $row['gas_value'],
            strtoupper($row['category']),
            $row['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// ---------------- PAGINATION SETUP ----------------
$limit = 25; // Jumlah data per halaman
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Hitung total baris data yang cocok
$countQuery = "SELECT COUNT(*) FROM egg_sort_results $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = ceil($totalRows / $limit);

// Ambil data untuk halaman aktif
$query = "SELECT id, weight, gas_value, category, created_at 
          FROM egg_sort_results 
          $whereClause 
          ORDER BY id DESC 
          LIMIT :limit OFFSET :offset";

$stmt = $db->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->execute();
$records = $stmt->fetchAll();

// Membantu membuat query string url untuk pagination & export
function buildQueryUri($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pemilahan Telur - Egg Sorter</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <div>
            <h1>Riwayat Pemilahan</h1>
            <p class="subtitle">Log historis penimbangan berat dan pengecekan gas MQ-135</p>
        </div>
        <nav>
            <a href="index.php">Monitoring</a>
            <a href="history.php" class="active">Riwayat Data</a>
            <a href="test.php">Uji &amp; Kalibrasi</a>
        </nav>
    </header>

    <main style="display: block;">
        <div class="card">
            <!-- Form Filter Pencarian -->
            <form method="GET" action="history.php" class="filter-form">
                <div class="form-group">
                    <label for="category">Kategori</label>
                    <select name="category" id="category" class="form-control">
                        <option value="">Semua Kategori</option>
                        <option value="ringan" <?php echo $categoryFilter === 'ringan' ? 'selected' : ''; ?>>Ringan</option>
                        <option value="sedang" <?php echo $categoryFilter === 'sedang' ? 'selected' : ''; ?>>Sedang</option>
                        <option value="berat" <?php echo $categoryFilter === 'berat' ? 'selected' : ''; ?>>Berat</option>
                        <option value="busuk" <?php echo $categoryFilter === 'busuk' ? 'selected' : ''; ?>>Busuk</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="start_date">Tanggal Mulai</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>

                <div class="form-group">
                    <label for="end_date">Tanggal Akhir</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>

                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn">Filter</button>
                    <a href="history.php" class="btn btn-secondary">Reset</a>
                    <a href="<?php echo buildQueryUri(['export' => 'csv']); ?>" class="btn btn-secondary" style="border: 1px solid #059669; color: #059669;">
                        Export CSV
                    </a>
                </div>
            </form>

            <!-- Tabel Data -->
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Kategori</th>
                            <th style="text-align: right;">Berat (gram)</th>
                            <th style="text-align: right;">Gas MQ-135 (Raw ADC)</th>
                            <th style="text-align: right;">Waktu Penyimpanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($records) === 0): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--color-text-muted); padding: 30px 10px;">
                                    Tidak ada data riwayat yang ditemukan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $row): ?>
                                <tr>
                                    <td class="mono" style="color: var(--color-text-muted);">#<?php echo $row['id']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $row['category']; ?>">
                                            <?php echo strtoupper($row['category']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;" class="mono"><?php echo number_format($row['weight'], 2); ?>g</td>
                                    <td style="text-align: right;" class="mono"><?php echo $row['gas_value']; ?></td>
                                    <td style="text-align: right;" class="mono"><?php echo date('d-m-Y H:i:s', strtotime($row['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bagian Navigasi Halaman (Pagination) -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Menampilkan <?php echo $offset + 1; ?> - <?php echo min($offset + $limit, $totalRows); ?> dari <?php echo $totalRows; ?> baris data
                    </div>
                    <div class="pagination-buttons">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo buildQueryUri(['page' => $page - 1]); ?>">&laquo; Prev</a>
                        <?php endif; ?>

                        <?php 
                        // Tampilkan maksimal 5 link halaman di sekitar halaman aktif
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="<?php echo buildQueryUri(['page' => $i]); ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo buildQueryUri(['page' => $page + 1]); ?>">Next &raquo;</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
