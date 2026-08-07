<!DOCTYPE html>
<!-- public/test.php -->
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Uji & Kalibrasi - Egg Sorter</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .test-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        @media (max-width: 900px) {
            .test-grid {
                grid-template-columns: 1fr;
            }
        }
        .sensor-card {
            background: #1e293b;
            color: #f8fafc;
            border-color: #334155;
        }
        .sensor-card .section-title {
            color: #f8fafc;
            border-bottom-color: #334155;
        }
        .gauge-container {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 15px;
        }
        .gauge-box {
            background: #0f172a;
            border: 1px solid #1e293b;
            border-radius: 6px;
            padding: 16px;
            width: 140px;
            text-align: center;
        }
        .gauge-val {
            font-family: var(--font-data);
            font-size: 1.8rem;
            font-weight: 700;
            color: #38bdf8;
            margin: 8px 0;
        }
        .gauge-lbl {
            font-size: 0.75rem;
            color: #94a3b8;
            text-transform: uppercase;
            font-weight: 600;
        }
        .gauge-sub {
            font-size: 0.7rem;
            color: #64748b;
            font-family: var(--font-data);
        }
        .override-banner {
            background: #fffbeb;
            border: 1px solid #fef08a;
            color: #854d0e;
            border-radius: 4px;
            padding: 12px;
            font-size: 0.85rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .override-active {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        .control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--color-border-light);
        }
        .control-row:last-child {
            border-bottom: none;
        }
        .control-lbl {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .control-actions {
            display: flex;
            gap: 6px;
            align-items: center;
        }
        .control-actions input[type="range"] {
            width: 100px;
        }
        .angle-disp {
            font-family: var(--font-data);
            font-size: 0.8rem;
            font-weight: 600;
            min-width: 32px;
            text-align: right;
        }
        .switch-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 48px;
            height: 24px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider-switch {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: .4s;
            border-radius: 24px;
        }
        .slider-switch:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider-switch {
            background-color: #ea580c;
        }
        input:checked + .slider-switch:before {
            transform: translateX(24px);
        }
        .flow-steps {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 10px;
        }
        .flow-step {
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }
        .flow-step.active {
            background: #dcfce7;
            border-color: #86efac;
            color: #15803d;
            font-weight: 600;
        }
        .flow-step.active::after {
            content: 'AKTIF';
            font-size: 0.7rem;
            background: #16a34a;
            color: white;
            padding: 1px 6px;
            border-radius: 3px;
            font-weight: 700;
        }
        .ip-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: #ffffff;
            border: 1px solid var(--color-border);
            border-radius: 4px;
            padding: 10px 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .ip-bar label {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .ip-input {
            font-family: var(--font-data);
            padding: 4px 8px;
            font-size: 0.875rem;
            width: 140px;
            border: 1px solid var(--color-border);
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <header>
        <div>
            <h1>Panel Kalibrasi & Pengujian</h1>
            <p class="subtitle">Pengujian sensor-aktuator secara manual dan kalibrasi loadcell</p>
        </div>
        <nav>
            <a href="index.php">Monitoring</a>
            <a href="history.php">Riwayat Data</a>
            <a href="test.php" class="active">Uji & Kalibrasi</a>
        </nav>
    </header>

    <main class="test-grid">
        <!-- Kolom Kiri: Live Sensors & Calibration -->
        <section>
            <!-- Konfigurasi Koneksi ESP32 -->
            <div class="ip-bar">
                <label for="esp-ip">IP Address ESP32:</label>
                <input type="text" id="esp-ip" class="ip-input" value="192.168.4.1" placeholder="Contoh: 192.168.1.15">
                <button id="connect-btn" class="btn" onclick="toggleConnection()" style="padding: 4px 12px; font-size: 0.8rem;">Hubungkan</button>
                <span class="connection-status" id="ws-status">Terputus</span>
            </div>

            <!-- Live Sensor Telemetry -->
            <div class="card sensor-card">
                <h2 class="section-title">Telemetri Sensor Real-Time</h2>
                <div class="gauge-container">
                    <div class="gauge-box">
                        <div class="gauge-lbl">Timbangan</div>
                        <div class="gauge-val" id="val-weight">-- g</div>
                        <div class="gauge-sub" id="val-weight-raw">Raw: --</div>
                    </div>
                    <div class="gauge-box">
                        <div class="gauge-lbl">MQ-135 Gas</div>
                        <div class="gauge-val" id="val-gas">--</div>
                        <div class="gauge-sub" id="val-gas-status">Status: --</div>
                    </div>
                    <div class="gauge-box">
                        <div class="gauge-lbl">Ultrasonik</div>
                        <div class="gauge-val" id="val-distance">-- cm</div>
                        <div class="gauge-sub" id="val-distance-status">Deteksi: --</div>
                    </div>
                </div>
            </div>

            <!-- Calibration Panel -->
            <div class="card">
                <h2 class="section-title">Kalibrasi Timbangan (HX711)</h2>
                <div style="font-size: 0.85rem; color: var(--color-text-muted); margin-bottom: 15px;">
                    Kalibrasi dilakukan dengan meniadakan beban awal (Tare) lalu meletakkan beban dengan berat referensi yang sudah diketahui.
                </div>
                
                <div class="control-row">
                    <div>
                        <div class="control-lbl">Langkah 1: Tare (Nolkan Timbangan)</div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">Pastikan wadah timbangan kosong</div>
                    </div>
                    <button class="btn btn-secondary" onclick="sendAction('tare')" style="border-color: #475569; color: #475569;">Nolkan (Tare)</button>
                </div>
                
                <div class="control-row" style="align-items: flex-end;">
                    <div>
                        <div class="control-lbl">Langkah 2: Kalibrasi Berat Referensi</div>
                        <div style="font-size: 0.75rem; color: var(--color-text-muted);">Letakkan beban referensi dan masukkan beratnya di samping</div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; max-width: 200px;">
                        <input type="number" id="ref-weight" class="form-control" value="50.0" step="0.1" style="width: 80px; text-align: right;">
                        <span style="font-size: 0.85rem; font-weight: 600;">g</span>
                        <button class="btn" onclick="runCalibration()">Kalibrasi</button>
                    </div>
                </div>

                <div class="control-row" style="background: #f8fafc; padding: 10px; margin-top: 10px; border-radius: 4px;">
                    <div class="control-lbl">Faktor Kalibrasi Saat Ini (NVS):</div>
                    <div class="mono" id="val-cal-factor" style="font-weight: 700; color: #ea580c;">--</div>
                </div>
            </div><!-- end calibration panel -->

            <!-- Panel Kalibrasi Servo -->
            <div class="card">
                <h2 class="section-title">Kalibrasi Sudut Servo</h2>
                <div style="font-size:0.8rem;color:var(--color-text-muted);margin-bottom:12px;">
                    Geser slider → servo bergerak langsung. Klik <b>Simpan</b> untuk menyimpan ke NVS ESP32.
                    <br>Nilai tersimpan otomatis dipakai saat program berjalan.
                </div>

                <!-- Servo Gate -->
                <div class="control-row">
                    <div><div class="control-lbl">Gate (Buka)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">Sudut saat telur dilepas</div></div>
                    <div class="control-actions">
                        <input type="range" min="0" max="180" value="60" id="cal-gate-open" oninput="previewServo('gate', this.value); document.getElementById('dsp-gate-open').innerText=this.value+'°'">
                        <span class="angle-disp" id="dsp-gate-open">60°</span>
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveServo('gate_open','cal-gate-open')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Pendorong (Dorong)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">Sudut saat mendorong telur</div></div>
                    <div class="control-actions">
                        <input type="range" min="0" max="180" value="90" id="cal-push" oninput="previewServo('pendorong', this.value); document.getElementById('dsp-push').innerText=this.value+'°'">
                        <span class="angle-disp" id="dsp-push">90°</span>
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveServo('push_angle','cal-push')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Jalur 1 - RINGAN (Buka)</div></div>
                    <div class="control-actions">
                        <input type="range" min="0" max="180" value="90" id="cal-j1" oninput="previewServo('jalur1', this.value); document.getElementById('dsp-j1').innerText=this.value+'°'">
                        <span class="angle-disp" id="dsp-j1">90°</span>
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveServo('j1_open','cal-j1')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Jalur 2 - SEDANG (Buka)</div></div>
                    <div class="control-actions">
                        <input type="range" min="0" max="180" value="90" id="cal-j2" oninput="previewServo('jalur2', this.value); document.getElementById('dsp-j2').innerText=this.value+'°'">
                        <span class="angle-disp" id="dsp-j2">90°</span>
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveServo('j2_open','cal-j2')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Jalur 3 - BERAT (Buka)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">BUSUK lurus, tidak pakai servo</div></div>
                    <div class="control-actions">
                        <input type="range" min="0" max="180" value="70" id="cal-j3" oninput="previewServo('jalur3', this.value); document.getElementById('dsp-j3').innerText=this.value+'°'">
                        <span class="angle-disp" id="dsp-j3">70°</span>
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveServo('j3_berat','cal-j3')">Simpan</button>
                    </div>
                </div>
            </div>

            <!-- Panel Kalibrasi Threshold Sensor -->
            <div class="card">
                <h2 class="section-title">Kalibrasi Threshold Sensor</h2>
                <div class="control-row">
                    <div><div class="control-lbl">Threshold Gas Busuk (MQ-135)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">Nilai ADC raw (0-4095). Saat ini: <span id="live-gas-thr">1800</span></div></div>
                    <div class="control-actions">
                        <input type="number" id="inp-gas-thr" value="1800" min="100" max="4095" style="width:70px;padding:4px;border:1px solid #cbd5e1;border-radius:3px;font-size:0.85rem">
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveThreshold('gas_thr','inp-gas-thr')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Jarak Deteksi Telur di Gate (cm)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">Saat ini: <span id="live-us-dist">5</span> cm</div></div>
                    <div class="control-actions">
                        <input type="number" id="inp-us-dist" value="5" min="1" max="50" style="width:60px;padding:4px;border:1px solid #cbd5e1;border-radius:3px;font-size:0.85rem">
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveThreshold('us_dist','inp-us-dist')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Batas Berat RINGAN (gram)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">Di bawah nilai ini = Ringan. Saat ini: <span id="live-w-ringan">55</span>g</div></div>
                    <div class="control-actions">
                        <input type="number" id="inp-w-ringan" value="55" min="1" max="200" style="width:70px;padding:4px;border:1px solid #cbd5e1;border-radius:3px;font-size:0.85rem">
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveThreshold('w_ringan','inp-w-ringan')">Simpan</button>
                    </div>
                </div>
                <div class="control-row">
                    <div><div class="control-lbl">Batas Berat SEDANG (gram)</div><div style="font-size:0.7rem;color:var(--color-text-muted)">Di bawah nilai ini = Sedang. Di atas = Berat. Saat ini: <span id="live-w-sedang">64</span>g</div></div>
                    <div class="control-actions">
                        <input type="number" id="inp-w-sedang" value="64" min="1" max="200" style="width:70px;padding:4px;border:1px solid #cbd5e1;border-radius:3px;font-size:0.85rem">
                        <button class="btn" style="padding:3px 8px;font-size:0.75rem" onclick="saveThreshold('w_sedang','inp-w-sedang')">Simpan</button>
                    </div>
                </div>
            </div>

            <!-- Backend URL Config -->
            <div class="card">
                <h2 class="section-title">🌐 URL Backend Server</h2>
                <div style="font-size:0.8rem;color:var(--color-text-muted);margin-bottom:12px">
                    Ubah jika IP server berubah — tersimpan di NVS ESP32, tidak perlu reflash.
                </div>
                <div style="display:flex;gap:8px;width:100%;align-items:center;margin-bottom:8px">
                    <input type="text" id="inp-backend-url" value=""
                           placeholder="http://192.168.x.x/pemilah-telur/api/sort-result.php"
                           style="flex:1;padding:7px 10px;border:1px solid #cbd5e1;border-radius:6px;font-size:0.82rem;font-family:monospace">
                    <button class="btn" style="white-space:nowrap" onclick="saveBackendUrl()">Simpan URL</button>
                </div>
                <div style="font-size:0.72rem;color:#64748b">
                    URL aktif di ESP32: <span id="live-backend-url" style="font-family:monospace;color:#0f766e">--</span>
                </div>
            </div>
        </section><!-- end kolom kiri -->

        <!-- Kolom Kanan: Manual Override Actuators & State Visualizer -->
        <section>
            <!-- Override Switch -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="font-family: var(--font-title); font-size: 1.1rem; font-weight: 700;">Override Manual (Uji Aktuator)</h2>
                        <p style="font-size: 0.75rem; color: var(--color-text-muted);">Mengontrol servo secara terpisah untuk pengujian mekanis</p>
                    </div>
                    <div class="switch-container">
                        <span style="font-size: 0.85rem; font-weight: 600;" id="override-lbl">OFF</span>
                        <label class="switch">
                            <input type="checkbox" id="override-switch" onchange="toggleOverride(this.checked)">
                            <span class="slider-switch"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Override Warning Banner -->
            <div class="override-banner" id="banner-warning">
                ⚠️ **Perhatian**: Mode otomatis sedang aktif. Aktifkan **Override Manual** di atas untuk menguji motor servo secara manual.
            </div>

            <!-- Servo Actuators Control Box -->
            <div class="card" id="actuators-card" style="opacity: 0.5; pointer-events: none; transition: opacity 0.3s ease;">
                <h2 class="section-title">Kontrol Aktuator Servo</h2>
                
                <!-- Servo Gate -->
                <div class="control-row">
                    <span class="control-lbl">1. Servo Gate (Tahan/Lepas)</span>
                    <div class="control-actions">
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('gate', 0)">Tutup (0°)</button>
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('gate', 60)">Buka (60°)</button>
                        <input type="range" min="0" max="180" value="0" id="slide-gate" oninput="slideServo('gate', this.value)">
                        <span class="angle-disp" id="disp-gate">0°</span>
                    </div>
                </div>

                <!-- Servo Pendorong -->
                <div class="control-row">
                    <span class="control-lbl">2. Servo Pendorong</span>
                    <div class="control-actions">
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('pendorong', 0)">Awal (0°)</button>
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('pendorong', 90)">Dorong (90°)</button>
                        <input type="range" min="0" max="180" value="0" id="slide-pendorong" oninput="slideServo('pendorong', this.value)">
                        <span class="angle-disp" id="disp-pendorong">0°</span>
                    </div>
                </div>

                <!-- Servo Jalur 1 -->
                <div class="control-row">
                    <span class="control-lbl">3. Servo Jalur 1 (Flap Ringan)</span>
                    <div class="control-actions">
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('jalur1', 0)">Tutup (0°)</button>
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('jalur1', 90)">Buka (90°)</button>
                        <input type="range" min="0" max="180" value="0" id="slide-jalur1" oninput="slideServo('jalur1', this.value)">
                        <span class="angle-disp" id="disp-jalur1">0°</span>
                    </div>
                </div>

                <!-- Servo Jalur 2 -->
                <div class="control-row">
                    <span class="control-lbl">4. Servo Jalur 2 (Flap Sedang)</span>
                    <div class="control-actions">
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('jalur2', 0)">Tutup (0°)</button>
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('jalur2', 90)">Buka (90°)</button>
                        <input type="range" min="0" max="180" value="0" id="slide-jalur2" oninput="slideServo('jalur2', this.value)">
                        <span class="angle-disp" id="disp-jalur2">0°</span>
                    </div>
                </div>

                <!-- Servo Jalur 3 -->
                <div class="control-row">
                    <span class="control-lbl">5. Servo Jalur 3 (Flap BERAT)</span>
                    <div class="control-actions">
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('jalur3', 0)">Tutup (0°)</button>
                        <button class="btn btn-secondary" style="padding: 4px 8px; font-size: 0.75rem;" onclick="moveServo('jalur3', 70)">Berat (70°)</button>
                        <input type="range" min="0" max="180" value="0" id="slide-jalur3" oninput="slideServo('jalur3', this.value)">
                        <span class="angle-disp" id="disp-jalur3">0°</span>
                    </div>
                </div>
            </div>

            <!-- State Machine Monitor -->
            <div class="card">
                <h2 class="section-title">Visualisasi Alur State Machine</h2>
                <div class="flow-steps">
                    <div class="flow-step" id="step-WAIT_EGG_AT_GATE">1. WAIT_EGG_AT_GATE (Menunggu telur terdeteksi di gate awal)</div>
                    <div class="flow-step" id="step-OPEN_GATE">2. OPEN_GATE (Gate dibuka, telur turun ke timbangan)</div>
                    <div class="flow-step" id="step-CLOSE_GATE">3. CLOSE_GATE (Gate menutup kembali untuk menahan telur berikutnya)</div>
                    <div class="flow-step" id="step-WEIGHING_SETTLE">4. WEIGHING_SETTLE (Proses stabilisasi pembacaan berat & gas)</div>
                    <div class="flow-step" id="step-DECIDE_CATEGORY">5. DECIDE_CATEGORY (Penentuan kategori berdasarkan data sensor)</div>
                    <div class="flow-step" id="step-PUSH_EGG">6. PUSH_EGG (Servo pendorong mendorong telur ke jalur sortir)</div>
                    <div class="flow-step" id="step-RETURN_PENDORONG">7. RETURN_PENDORONG (Pendorong ditarik kembali ke posisi awal)</div>
                    <div class="flow-step" id="step-OPEN_SORT_GATE">8. OPEN_SORT_GATE (Flap penampung kategori terbuka)</div>
                    <div class="flow-step" id="step-CLOSE_SORT_GATE">9. CLOSE_SORT_GATE (Flap menutup kembali, siklus selesai)</div>
                </div>
            </div>
        </section>
    </main>

    <script>
        let ws = null;
        let isConnected = false;
        
        // Load IP dari LocalStorage saat buka halaman
        document.addEventListener('DOMContentLoaded', () => {
            const savedIP = localStorage.getItem('esp_ip');
            if (savedIP) {
                document.getElementById('esp-ip').value = savedIP;
            }
        });

        function toggleConnection() {
            const ipInput = document.getElementById('esp-ip');
            const connectBtn = document.getElementById('connect-btn');
            const statusBadge = document.getElementById('ws-status');
            
            if (isConnected) {
                // Putuskan koneksi
                if (ws) ws.close();
                return;
            }

            const ip = ipInput.value.trim();
            if (!ip) {
                alert('Silakan isi IP address ESP32 terlebih dahulu.');
                return;
            }

            localStorage.setItem('esp_ip', ip);
            statusBadge.innerText = 'Menghubungkan...';
            statusBadge.className = 'connection-status';
            statusBadge.style.backgroundColor = '#fef3c7';
            statusBadge.style.color = '#d97706';
            statusBadge.style.borderColor = '#fde047';

            ws = new WebSocket('ws://' + ip + '/ws');

            ws.onopen = function() {
                isConnected = true;
                connectBtn.innerText = 'Putuskan';
                connectBtn.style.backgroundColor = '#dc2626';
                connectBtn.style.borderColor = '#dc2626';
                statusBadge.innerText = 'Terhubung';
                statusBadge.className = 'connection-status active';
                statusBadge.style = ''; // Reset inline style
            };

            ws.onmessage = function(evt) {
                const data = JSON.parse(evt.data);
                
                // Update live sensor readouts
                document.getElementById('val-weight').innerText = data.weight.toFixed(1) + ' g';
                document.getElementById('val-gas').innerText = data.gas;
                document.getElementById('val-distance').innerText = data.distance + ' cm';
                
                // Raw weight estimate based on calibration factor
                const rawWeight = data.weight;
                document.getElementById('val-weight-raw').innerText = 'Nilai: ' + Math.round(rawWeight);
                
                // Status Gas MQ-135
                const gasVal = data.gas;
                const gasStatus = document.getElementById('val-gas-status');
                if (gasVal > 1800) {
                    gasStatus.innerText = 'Status: BUSUK 🚨';
                    gasStatus.style.color = '#ef4444';
                } else {
                    gasStatus.innerText = 'Status: SEGAR 🟢';
                    gasStatus.style.color = '#22c55e';
                }

                // Status Deteksi Jarak
                const distance = data.distance;
                const distanceStatus = document.getElementById('val-distance-status');
                if (distance > 0 && distance <= 5) {
                    distanceStatus.innerText = 'Deteksi: ADA TELUR';
                    distanceStatus.style.color = '#3b82f6';
                } else {
                    distanceStatus.innerText = 'Deteksi: KOSONG';
                    distanceStatus.style.color = '#64748b';
                }

                // Update config sliders dari data ESP32
                if (data.cfg_gate_open !== undefined) {
                    setSlider('cal-gate-open', 'dsp-gate-open', data.cfg_gate_open);
                    setSlider('cal-push',      'dsp-push',      data.cfg_push);
                    setSlider('cal-j1',        'dsp-j1',        data.cfg_j1_open);
                    setSlider('cal-j2',        'dsp-j2',        data.cfg_j2_open);
                    setSlider('cal-j3',        'dsp-j3',        data.cfg_j3_berat);
                    document.getElementById('inp-gas-thr').value  = data.cfg_gas_thr;
                    document.getElementById('inp-us-dist').value  = data.cfg_us_dist;
                    document.getElementById('inp-w-ringan').value = data.cfg_w_ringan;
                    document.getElementById('inp-w-sedang').value = data.cfg_w_sedang;
                    document.getElementById('live-gas-thr').innerText  = data.cfg_gas_thr;
                    document.getElementById('live-us-dist').innerText  = data.cfg_us_dist;
                    document.getElementById('live-w-ringan').innerText = data.cfg_w_ringan;
                    document.getElementById('live-w-sedang').innerText = data.cfg_w_sedang;
                }

                if (data.backend_url !== undefined) {
                    document.getElementById('live-backend-url').innerText = data.backend_url;
                    const inp = document.getElementById('inp-backend-url');
                    if (!inp.value) inp.value = data.backend_url; // isi sekali saat connect
                }

                // Update Calibration Factor display
                if (data.cal_factor !== undefined) {
                    document.getElementById('val-cal-factor').innerText = data.cal_factor.toFixed(2);
                }

                // State Machine highlight
                const rawStep = data.rawStep || data.step;
                highlightState(rawStep);
            };

            ws.onclose = function() {
                isConnected = false;
                connectBtn.innerText = 'Hubungkan';
                connectBtn.style = ''; // Reset inline style
                statusBadge.innerText = 'Terputus';
                statusBadge.className = 'connection-status';
                statusBadge.style = '';
                
                // Reset visual data
                document.getElementById('val-weight').innerText = '-- g';
                document.getElementById('val-gas').innerText = '--';
                document.getElementById('val-distance').innerText = '-- cm';
                document.getElementById('val-cal-factor').innerText = '--';
                
                // Reset State visual
                document.querySelectorAll('.flow-step').forEach(el => el.classList.remove('active'));
            };

            ws.onerror = function(err) {
                console.error('WebSocket Error: ', err);
            };
        }

        function highlightState(activeState) {
            // Bersihkan active state sebelumnya
            document.querySelectorAll('.flow-step').forEach(el => el.classList.remove('active'));
            
            // Map state deskripsi atau raw state dari ESP32
            let stepId = '';
            if (activeState.includes("Menunggu telur") || activeState === "WAIT_EGG_AT_GATE") stepId = "step-WAIT_EGG_AT_GATE";
            else if (activeState.includes("Gate terbuka") || activeState === "OPEN_GATE") stepId = "step-OPEN_GATE";
            else if (activeState.includes("Gate menutup") || activeState === "CLOSE_GATE") stepId = "step-CLOSE_GATE";
            else if (activeState.includes("Menimbang") || activeState === "WEIGHING_SETTLE") stepId = "step-WEIGHING_SETTLE";
            else if (activeState.includes("Kategori ditentukan") || activeState === "DECIDE_CATEGORY") stepId = "step-DECIDE_CATEGORY";
            else if (activeState.includes("Mendorong") || activeState === "PUSH_EGG") stepId = "step-PUSH_EGG";
            else if (activeState.includes("Pendorong kembali") || activeState === "RETURN_PENDORONG") stepId = "step-RETURN_PENDORONG";
            else if (activeState.includes("masuk penampungan") || activeState === "OPEN_SORT_GATE") stepId = "step-OPEN_SORT_GATE";
            else if (activeState.includes("tutup kembali") || activeState === "CLOSE_SORT_GATE") stepId = "step-CLOSE_SORT_GATE";
            
            const activeEl = document.getElementById(stepId);
            if (activeEl) {
                activeEl.classList.add('active');
            }
        }

        function toggleOverride(checked) {
            const label = document.getElementById('override-lbl');
            const warningBanner = document.getElementById('banner-warning');
            const actuatorsCard = document.getElementById('actuators-card');
            
            if (checked) {
                label.innerText = 'ON';
                label.style.color = '#ef4444';
                warningBanner.className = 'override-banner override-active';
                warningBanner.innerText = '🛑 Mode Override Manual AKTIF. Jalur otomatisasi dinonaktifkan sementara.';
                actuatorsCard.style.opacity = '1';
                actuatorsCard.style.pointerEvents = 'auto';
                
                sendAction('set_mode', { manual: true });
            } else {
                label.innerText = 'OFF';
                label.style.color = '';
                warningBanner.className = 'override-banner';
                warningBanner.innerText = '⚠️ Perhatian: Mode otomatis sedang aktif. Aktifkan Override Manual di atas untuk menguji motor servo secara manual.';
                actuatorsCard.style.opacity = '0.5';
                actuatorsCard.style.pointerEvents = 'none';
                
                sendAction('set_mode', { manual: false });
            }
        }

        function moveServo(servoName, angle) {
            // Update slider & text visual
            document.getElementById('slide-' + servoName).value = angle;
            document.getElementById('disp-' + servoName).innerText = angle + '°';
            
            sendAction('servo', { name: servoName, value: angle });
        }

        function slideServo(servoName, angle) {
            document.getElementById('disp-' + servoName).innerText = angle + '°';
            sendAction('servo', { name: servoName, value: parseInt(angle) });
        }

        function saveBackendUrl() {
            const url = document.getElementById('inp-backend-url').value.trim();
            if (!url.startsWith('http')) {
                alert('URL harus diawali dengan http:// atau https://');
                return;
            }
            sendAction('save_backend_url', { url: url });
            document.getElementById('live-backend-url').innerText = url;
            const btn = event.target;
            const orig = btn.innerText;
            btn.innerText = '✓ Tersimpan';
            btn.style.background = '#16a34a';
            setTimeout(() => { btn.innerText = orig; btn.style.background = ''; }, 1500);
        }

        function setSlider(sliderId, dispId, val) {
            const el = document.getElementById(sliderId);
            if (el) el.value = val;
            const disp = document.getElementById(dispId);
            if (disp) disp.innerText = val + '°';
        }

        function previewServo(servoName, angle) {
            // Gerak servo real-time saat slider digeser (override harus aktif)
            sendAction('servo', { name: servoName, value: parseInt(angle) });
        }

        function saveServo(key, sliderId) {
            const val = parseInt(document.getElementById(sliderId).value);
            sendAction('save_servo', { key: key, value: val });
            // Flash visual feedback
            const btn = event.target;
            const orig = btn.innerText;
            btn.innerText = '✓ Tersimpan';
            btn.style.background = '#16a34a';
            setTimeout(() => { btn.innerText = orig; btn.style.background = ''; }, 1500);
        }

        function saveThreshold(key, inputId) {
            const val = parseFloat(document.getElementById(inputId).value);
            sendAction('save_threshold', { key: key, value: val });
            const btn = event.target;
            const orig = btn.innerText;
            btn.innerText = '✓ Tersimpan';
            btn.style.background = '#16a34a';
            setTimeout(() => { btn.innerText = orig; btn.style.background = ''; }, 1500);
        }

        function runCalibration() {
            const refWeight = parseFloat(document.getElementById('ref-weight').value);
            if (isNaN(refWeight) || refWeight <= 0) {
                alert('Masukkan berat referensi yang valid.');
                return;
            }
            if (confirm(`Sudah menaruh beban ${refWeight}g di timbangan?`)) {
                sendAction('calibrate', { known_weight: refWeight });
            }
        }

        function sendAction(actionName, extraParams = {}) {
            if (!isConnected || !ws) {
                alert('Tolong hubungkan ke WebSocket ESP32 terlebih dahulu.');
                return;
            }
            ws.send(JSON.stringify({ action: actionName, ...extraParams }));
        }
    </script>
</body>
</html>
