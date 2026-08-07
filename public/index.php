<!DOCTYPE html>
<!-- public/index.php -->
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Egg Sorter Dashboard - Monitoring Pemilah Telur</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Chart.js via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header>
        <div>
            <h1>Dashboard Pemilah Telur</h1>
            <p class="subtitle">Sistem Monitoring Berat &amp; Deteksi Kualitas Telur Lokal (HX711 &amp; MQ-135)</p>
        </div>
        <nav>
            <a href="index.php" class="active">Monitoring</a>
            <a href="history.php">Riwayat Data</a>
            <a href="test.php">Uji &amp; Kalibrasi</a>
        </nav>
    </header>

    <main>
        <!-- Kolom Utama: Statistik & Grafik -->
        <section>
            <div class="card">
                <div class="section-title">
                    <span>Statistik Pemilahan</span>
                    <span class="connection-status active" id="poll-indicator">
                        <span class="blink-dot"></span>Real-time Polling
                    </span>
                </div>
                
                <!-- Grid Statistik per Kategori -->
                <div class="stats-grid">
                    <div class="stat-box ringan-box">
                        <span class="stat-label">RINGAN (&lt; 55g)</span>
                        <div class="stat-numbers">
                            <span class="stat-val-today" id="today-ringan">0</span>
                            <span class="stat-val-total" id="total-ringan">Total: 0</span>
                        </div>
                    </div>
                    <div class="stat-box sedang-box">
                        <span class="stat-label">SEDANG (55 - 64g)</span>
                        <div class="stat-numbers">
                            <span class="stat-val-today" id="today-sedang">0</span>
                            <span class="stat-val-total" id="total-sedang">Total: 0</span>
                        </div>
                    </div>
                    <div class="stat-box berat-box">
                        <span class="stat-label">BERAT (&gt; 64g)</span>
                        <div class="stat-numbers">
                            <span class="stat-val-today" id="today-berat">0</span>
                            <span class="stat-val-total" id="total-berat">Total: 0</span>
                        </div>
                    </div>
                    <div class="stat-box busuk-box">
                        <span class="stat-label">BUSUK (Gas)</span>
                        <div class="stat-numbers">
                            <span class="stat-val-today" id="today-busuk">0</span>
                            <span class="stat-val-total" id="total-busuk">Total: 0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafik Tren 7 Hari Terakhir -->
            <div class="card">
                <h2 class="section-title">Tren Pemilahan 7 Hari Terakhir</h2>
                <div style="position: relative; height: 320px; width: 100%;">
                    <canvas id="eggTrendChart"></canvas>
                </div>
            </div>
        </section>

        <!-- Kolom Samping: Log Data Terbaru -->
        <aside>
            <div class="card" style="height: 100%;">
                <h2 class="section-title">Log Terakhir</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th style="text-align: right;">Berat</th>
                                <th style="text-align: right;">Gas MQ135</th>
                                <th style="text-align: right;">Waktu</th>
                            </tr>
                        </thead>
                        <tbody id="latest-logs">
                            <tr>
                                <td colspan="4" style="text-align: center; color: var(--color-text-muted); padding: 20px;">
                                    Menunggu data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </aside>
    </main>

    <script src="assets/js/main.js"></script>
</body>
</html>
