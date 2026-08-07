import re

with open('public/test.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace <title>
content = content.replace('<title>Uji & Kalibrasi - Egg Sorter</title>', '<title>Demo Mock Loadcell - Egg Sorter</title>')

# Replace active nav
content = content.replace('<a href="test.php" class="active">Uji & Kalibrasi</a>', '<a href="test.php">Uji & Kalibrasi</a>\n            <a href="demo.php" class="active">Demo Mock</a>')

# Header text
content = content.replace('<h1>Panel Kalibrasi & Pengujian</h1>', '<h1>Panel Demo (Mock Loadcell)</h1>')
content = content.replace('Pengujian sensor-aktuator secara manual dan kalibrasi loadcell', 'Simulasi berat telur jika sensor load cell rusak (Demonstrasi)')

# Replace Calibration panel with Mock panel
calib_start = content.find('<!-- Calibration Panel -->')
calib_end = content.find('<!-- Panel Kalibrasi Servo -->')

mock_panel = '''<!-- Injeksi Berat Panel -->
            <div class="card" style="border: 2px solid #38bdf8; background-color: #f0f9ff;">
                <h2 class="section-title" style="color: #0369a1; border-bottom-color: #bae6fd;">💉 Injeksi Berat Telur (Mock Loadcell)</h2>
                <div style="font-size: 0.85rem; color: #0f172a; margin-bottom: 15px;">
                    Gunakan fitur ini jika sensor timbangan fisik rusak. Masukkan estimasi berat lalu klik <b>Injeksi Berat</b>.<br>
                    Setelah diinjeksi, silakan taruh telur di depan sensor jarak agar telur mulai disortir. ESP32 akan menggunakan nilai yang Anda masukkan.
                </div>
                
                <div class="control-row" style="align-items: flex-end; border:none;">
                    <div>
                        <div class="control-lbl" style="color:#0f172a;">Berat Telur Simulasi:</div>
                        <div style="font-size: 0.75rem; color: #475569;">Nilai ini hanya dipakai 1 kali siklus.</div>
                    </div>
                    <div style="display: flex; gap: 8px; align-items: center; max-width: 200px;">
                        <input type="number" id="mock-weight" class="form-control" value="60.0" step="0.1" style="width: 80px; text-align: right; border-color: #7dd3fc; background: #fff;">
                        <span style="font-size: 0.85rem; font-weight: 600; color: #0f172a;">g</span>
                        <button class="btn" style="background-color: #0284c7; border-color: #0284c7;" onclick="injectMockWeight()">Injeksi</button>
                    </div>
                </div>
            </div><!-- end mock panel -->

            '''
            
content = content[:calib_start] + mock_panel + content[calib_end:]

# Add injectMockWeight function in JS
js_func = '''
        function runCalibration() {'''
        
mock_js = '''
        function injectMockWeight() {
            const mockWeight = parseFloat(document.getElementById('mock-weight').value);
            if (isNaN(mockWeight) || mockWeight <= 0) {
                alert('Masukkan berat yang valid.');
                return;
            }
            sendAction('inject_weight', { value: mockWeight });
            
            // Visual feedback
            const btn = event.target;
            const orig = btn.innerText;
            btn.innerText = '✓ Terinjeksi';
            btn.style.background = '#16a34a';
            setTimeout(() => { btn.innerText = orig; btn.style.background = '#0284c7'; }, 1500);
        }

        function runCalibration() {'''

content = content.replace(js_func, mock_js)

with open('public/demo.php', 'w', encoding='utf-8') as f:
    f.write(content)
print("demo.php created successfully.")
