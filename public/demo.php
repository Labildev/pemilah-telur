<?php
// Tentukan IP ESP32 secara statis sesuai permintaan user
$esp32_ip = "192.168.4.1"; // Default IP AP ESP32
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo & Test - Pemilah Telur</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
            --accent: #38bdf8;
            --danger: #ef4444;
            --success: #10b981;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            width: 100%;
            max-width: 600px;
        }
        h1 {
            text-align: center;
            color: var(--accent);
            margin-bottom: 20px;
        }
        .card {
            background-color: var(--card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .status {
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 20px;
            background: #334155;
        }
        .status.connected { background: var(--success); color: #fff; }
        .status.disconnected { background: var(--danger); color: #fff; }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .box {
            background: #0f172a;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #334155;
        }
        .box-title {
            font-size: 0.85rem;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .box-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent);
        }
        
        .control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #334155;
        }
        input[type="number"] {
            background: #0f172a;
            color: white;
            border: 1px solid #334155;
            padding: 8px;
            border-radius: 6px;
            width: 80px;
        }
        button {
            background: var(--accent);
            color: #0f172a;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            opacity: 0.9;
        }
        
        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #475569;
            transition: .4s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--accent); }
        input:checked + .slider:before { transform: translateX(26px); }
    </style>
</head>
<body>

<div class="container">
    <h1>Halaman Demo & Test</h1>
    
    <div style="display:flex; justify-content:center; gap:10px; margin-bottom:15px;">
        <input type="text" id="espIp" value="<?php echo $esp32_ip; ?>" placeholder="IP ESP32" style="text-align:center; padding:5px;">
        <button onclick="saveIpAndConnect()" style="padding:5px 15px;">Connect</button>
    </div>

    <div id="statusBadge" class="status disconnected">MENGHUBUNGKAN...</div>

    <!-- SENSOR INFO -->
    <div class="card">
        <h3 style="margin-top:0">Data Sensor</h3>
        <div class="grid">
            <div class="box">
                <div class="box-title">Jarak Ultrasonik</div>
                <div class="box-value" id="valDistance">0 cm</div>
            </div>
            <div class="box">
                <div class="box-title">Nilai Gas</div>
                <div class="box-value" id="valGas">0</div>
            </div>
        </div>
        
        <div class="control-row">
            <div>
                <strong>Injeksi Berat (Mock)</strong><br>
                <small style="color:#94a3b8">Bypass sensor loadcell sementara</small>
            </div>
            <div style="display:flex; gap:10px;">
                <input type="number" id="injectWeight" placeholder="gram">
                <button onclick="injectWeight()">Kirim</button>
            </div>
        </div>
    </div>

    <!-- SERVO TEST -->
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
            <h3 style="margin:0">Test Servo</h3>
            <div style="display:flex; align-items:center; gap:10px;">
                <span style="font-size:0.9rem">Manual Mode</span>
                <label class="switch">
                    <input type="checkbox" id="manualToggle" onchange="toggleManualMode()">
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        <p style="font-size:0.85rem; color:#94a3b8;">Aktifkan Manual Mode untuk menggerakkan servo. Servo akan bergerak ke sudut tes, lalu kembali ke 0 secara otomatis setelah 1 detik.</p>
        
        <div id="servoList"></div>
    </div>
    
    <div style="text-align:center; margin-top:20px;">
        <a href="index.php" style="color:var(--accent); text-decoration:none;">&larr; Kembali ke Dashboard Utama</a>
    </div>
</div>

<script>
    const ip = "<?php echo $esp32_ip; ?>";
    let ws = null;
    let isConnected = false;

    // Daftar Servo
    const servos = [
        { id: 'gate', name: 'Servo Gate' },
        { id: 'pendorong', name: 'Servo Pendorong' },
        { id: 'jalur1', name: 'Servo Jalur 1 (Ringan)' },
        { id: 'jalur2', name: 'Servo Jalur 2 (Sedang)' },
        { id: 'jalur3', name: 'Servo Jalur 3 (Berat/Busuk)' }
    ];

    function initUI() {
        const list = document.getElementById('servoList');
        servos.forEach(s => {
            list.innerHTML += `
                <div class="control-row">
                    <div><strong>${s.name}</strong></div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <input type="number" id="angle_${s.id}" value="90" min="0" max="180">
                        <button onclick="testServo('${s.id}')">TEST</button>
                    </div>
                </div>
            `;
        });
    }

    function connectWS() {
        if (ws) ws.close();
        const ip = document.getElementById('espIp').value.trim();
        if(!ip) return;
        
        const badge = document.getElementById('statusBadge');
        badge.textContent = "MENGHUBUNGKAN...";
        badge.className = "status disconnected";
        badge.style.background = "#f59e0b";
        
        ws = new WebSocket('ws://' + ip + ':81/');
        
        ws.onopen = () => {
            isConnected = true;
            badge.textContent = "TERHUBUNG KE ESP32";
            badge.className = "status connected";
            badge.style.background = "";
        };

        ws.onclose = () => {
            isConnected = false;
            badge.textContent = "KONEKSI TERPUTUS";
            badge.className = "status disconnected";
            badge.style.background = "";
            setTimeout(connectWS, 3000);
        };

        ws.onmessage = (e) => {
            try {
                const data = JSON.parse(e.data);
                if (data.distance !== undefined) document.getElementById('valDistance').textContent = data.distance + " cm";
                if (data.gas !== undefined) document.getElementById('valGas').textContent = data.gas;
                if (data.manualMode !== undefined) {
                    document.getElementById('manualToggle').checked = data.manualMode;
                }
            } catch (err) {}
        };
    }

    function saveIpAndConnect() {
        const ip = document.getElementById('espIp').value.trim();
        if(ip) {
            localStorage.setItem('esp_demo_ip', ip);
            connectWS();
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const savedIP = localStorage.getItem('esp_demo_ip');
        if(savedIP) {
            document.getElementById('espIp').value = savedIP;
        }
        initUI();
        connectWS();
    });

    function sendCmd(action, params = {}) {
        if (!isConnected) {
            alert('Belum terhubung ke ESP32');
            return;
        }
        ws.send(JSON.stringify({ action: action, ...params }));
    }

    function toggleManualMode() {
        const isManual = document.getElementById('manualToggle').checked;
        sendCmd('set_mode', { manual: isManual });
    }

    function injectWeight() {
        const val = parseFloat(document.getElementById('injectWeight').value);
        if (isNaN(val)) return;
        sendCmd('inject_weight', { value: val });
        alert(`Berat mock ${val}g dikirim!`);
    }

    function testServo(name) {
        const isManual = document.getElementById('manualToggle').checked;
        if (!isManual) {
            alert('Silakan aktifkan Manual Mode terlebih dahulu!');
            return;
        }
        
        const angle = parseInt(document.getElementById('angle_' + name).value);
        if (isNaN(angle)) return;
        
        // Kirim ke angle tes
        sendCmd('servo', { name: name, value: angle });
        
        // Kembali ke 0 setelah 1 detik
        setTimeout(() => {
            sendCmd('servo', { name: name, value: 0 });
        }, 1000);
    }
</script>
</body>
</html>
