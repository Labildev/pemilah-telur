/*
  ============================================================
  ALAT PEMILAH TELUR OTOMATIS - ESP32S 38pin
  Desain: gravitasi-based, 5 servo (sesuai skema prototipe)
  ============================================================
  Sensor  : HC-SR04 (ultrasonik, deteksi telur di gate awal)
            Loadcell 5Kg + HX711
            MQ-135 (gas, dibaca bersamaan dengan loadcell)
  Aktuator:
    Servo Gate      -> tahan/lepas telur dari titik awal
    Servo Pendorong -> dorong telur dari timbangan ke jalur sortir
    Servo Jalur 1   -> flap buka/tutup untuk kategori RINGAN
    Servo Jalur 2   -> flap buka/tutup untuk kategori SEDANG
    Servo Jalur 3   -> flap untuk kategori BERAT saja
                       (BUSUK: lurus ke penampungan, tidak pakai servo)
  Output  : Web server (AP mode) + WebSocket, live monitoring

  ASUMSI PENTING (WAJIB DIKONFIRMASI):
  Servo Jalur 3 diasumsikan punya 2 sudut berbeda untuk mengarahkan
  telur ke bin BERAT atau bin BUSUK (bukan sekadar buka/tutup).
  Kalau mekanisme fisik Anda berbeda, sudut SORT3_BERAT_ANGLE dan
  SORT3_BUSUK_ANGLE di bawah perlu disesuaikan atau logikanya diubah.

  LIBRARY YANG HARUS DIINSTALL (Library Manager Arduino IDE):
  1. HX711 by bogde
  2. ESP32Servo by Kevin Harrington / madhephaestus
  3. ESPAsyncWebServer by me-no-dev
  4. AsyncTCP by me-no-dev
  5. ArduinoJson by Benoit Blanchon (versi 6.x)
  ============================================================
*/

#include "soc/rtc_cntl_reg.h"
#include "soc/soc.h"

#include <AsyncTCP.h>
#include <WiFi.h>

// Perbesar antrian WebSocket dari default 32 -> 64
// Harus didefinisikan SEBELUM include ESPAsyncWebServer
#define WS_MAX_QUEUED_MESSAGES 64
#include <ArduinoJson.h>
#include <ESP32Servo.h>
#include <ESPAsyncWebServer.h>
#include <HTTPClient.h>
#include <HX711.h>
#include <Preferences.h>

// ---------------- KONFIGURASI PIN (SKEMA BARU - AMAN WIFI) ----------------
#define PIN_HX711_DT 21   // HX711 DOUT  (GPIO 21)
#define PIN_HX711_SCK 22  // HX711 SCK   (GPIO 22)
#define PIN_MQ135_AOUT 34 // ADC1 - aman untuk WiFi

#define PIN_ULTRASONIC_TRIG 23
#define PIN_ULTRASONIC_ECHO 19

#define PIN_SERVO_GATE 13      // gate awal, tahan/lepas telur
#define PIN_SERVO_PENDORONG 14 // dorong telur ke jalur sortir
#define PIN_SERVO_JALUR1 4     // flap RINGAN
#define PIN_SERVO_JALUR2 16    // flap SEDANG
#define PIN_SERVO_JALUR3 17    // flap BERAT / BUSUK (2 posisi) (2 posisi)

// ---------------- KALIBRASI - dimuat dari NVS saat boot ----------------
float CALIBRATION_FACTOR = -420.0;
// GAS_THRESHOLD dihapus - deteksi gas kini menggunakan GAS_BASELINE + GAS_DELTA (relatif terhadap ambient)

int   EGG_AT_GATE_DISTANCE_CM = 5;

// Ambang batas berat (gram)
float WEIGHT_RINGAN_MAX = 55.0;
float WEIGHT_SEDANG_MAX = 64.0;
const float WEIGHT_MIN_DETECT = 10.0; // Berat minimum telur valid (gram) - di bawah ini = timbangan kosong

// ---------------- SUDUT SERVO - dapat dikalibrasi via web ----------------
// Semua variabel (bukan const) agar bisa disimpan ke NVS
int GATE_CLOSED_ANGLE = 0;
int GATE_OPEN_ANGLE = 60;
int PENDORONG_HOME_ANGLE = 0;
int PENDORONG_PUSH_ANGLE = 90;
int JALUR1_CLOSED_ANGLE = 0;
int JALUR1_OPEN_ANGLE = 90;
int JALUR2_CLOSED_ANGLE = 0;
int JALUR2_OPEN_ANGLE = 90;
int JALUR3_CLOSED_ANGLE = 0;
int JALUR3_BERAT_ANGLE = 70; // Jalur3 HANYA untuk BERAT
// BUSUK: tidak pakai servo, telur lurus ke penampungan

// ---------------- WAKTU TUNDA ANTAR TAHAP (ms) - WAJIB DIUJI & DISESUAIKAN ----------------
const unsigned long T_GATE_OPEN_HOLD    = 500;   // gate terbuka, telur menggelinding lewat
const unsigned long T_GATE_CLOSE_WAIT   = 300;   // tunggu gate benar2 tertutup lagi
const unsigned long T_WEIGHING_SETTLE   = 1000;  // sesuai catatan: stabil +/- 1 detik
const unsigned long T_PENDORONG_HOLD    = 500;
const unsigned long T_PENDORONG_RETURN  = 400;
const unsigned long T_SORT_GATE_OPEN    = 700;   // waktu flap terbuka sampai telur jatuh
const unsigned long T_SORT_GATE_CLOSE   = 400;
const unsigned long ULTRASONIC_POLL_MS      = 200;
const unsigned long T_ABORT_GATE_REOPEN     = 500;   // Gate buka sebentar saat abort timbangan kosong (ms)
const unsigned long T_ABORT_RETRY_WAIT      = 15000; // Jeda total siklus ulang timbangan kosong (ms) - adjustable

// ---------------- WIFI ACCESS POINT ----------------
const char *AP_SSID = "EggSorter_ESP32";
const char *AP_PASSWORD = "sortir123";

// ---------------- WIFI STA & BACKEND CONFIGURATION ----------------
const char *WIFI_SSID = "Ham";
const char *WIFI_PASSWORD = "cipacantik";

// URL backend - disimpan di NVS agar bisa diubah dari dashboard tanpa reflash
// PERHATIAN: ESP32 tidak bisa menggunakan "localhost". Gunakan IP WiFi komputer
// Anda! Format: http://<IP_KOMPUTER_ANDA>/pemilah-telur/api/sort-result.php
char BACKEND_URL[128] = "http://192.168.x.x/pemilah-telur/api/sort-result.php";
const char *API_KEY = "rahasia123";

// Baseline gas ambient (dikalibrasi saat boot) & delta sensitivitas
int GAS_BASELINE = 300;   // Default, di-update oleh calibrateGasBaseline() saat setup
int GAS_DELTA    = 150;   // Selisih dari baseline = "ada gas" (adjustable via NVS/dashboard)

// Baseline gas ambient (dikalibrasi saat boot) & delta sensitivitas
int GAS_BASELINE = 300;   // Default, di-update oleh calibrateGasBaseline() saat setup
int GAS_DELTA    = 150;   // Selisih dari baseline = "ada gas" (adjustable via NVS/dashboard)

// ---------------- OBJEK GLOBAL ----------------
HX711 scale;
bool scaleEnabled = false;
Preferences preferences;

Servo servoGate;
Servo servoPendorong;
Servo servoJalur1;
Servo servoJalur2;
Servo servoJalur3;

AsyncWebServer server(80);
AsyncWebSocket ws("/ws");

// ---------------- STATE MACHINE ----------------
enum SortState {
  WAIT_EGG_AT_GATE,
  OPEN_GATE,
  CLOSE_GATE,
  WEIGHING_SETTLE,
  DECIDE_CATEGORY,
  PUSH_EGG,
  RETURN_PENDORONG,
  OPEN_SORT_GATE,
  CLOSE_SORT_GATE,
  ABORT_RETRY         // Timbangan kosong: buka gate lagi, tunggu, lalu ulang siklus
};

SortState currentState = WAIT_EGG_AT_GATE;
unsigned long stateTimer = 0;
unsigned long lastUltrasonicPoll = 0;
unsigned long lastBroadcast = 0; // throttle broadcast
const unsigned long BROADCAST_INTERVAL_MS =
    2000; // 1x per 2 detik - cegah queue penuh

float currentWeight = 0;
int currentGas = 0;
long currentDistance = -1;
String lastCategory = "-";

unsigned int countRingan = 0;
unsigned int countSedang = 0;
unsigned int countBerat = 0;
unsigned int countBusuk = 0;

// Mode override manual (diatur dari dashboard)
bool manualOverrideMode = false;

// Antrian pengiriman HTTP POST (non-blocking)
bool pendingBackendPost = false;
float pendingWeight = 0;
int pendingGas = 0;
String pendingCategory = "-";

// ---------------- HALAMAN WEB ----------------
const char INDEX_HTML[] PROGMEM = R"HTML(
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Egg Sorter Monitor</title>
<style>
  body{font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:20px;color:#222}
  h1{font-size:20px;text-align:center;margin-bottom:20px}
  .card{background:#fff;border-radius:10px;padding:16px;margin-bottom:14px;box-shadow:0 1px 4px rgba(0,0,0,0.1)}
  .row{display:flex;justify-content:space-between;margin:6px 0;font-size:15px}
  .label{color:#666}
  .value{font-weight:bold}
  .step{font-size:14px;text-align:center;padding:10px;border-radius:8px;margin-top:8px;background:#eef;color:#334}
  .cat{font-size:22px;text-align:center;padding:14px;border-radius:8px;margin-top:8px}
  .ringan{background:#e0ffe4;color:#1a7a2e}
  .sedang{background:#fff3d6;color:#a67c00}
  .berat{background:#ffe6d6;color:#a3521e}
  .busuk{background:#333;color:#fff}
  .idle{background:#eee;color:#666}
  .counters{display:flex;gap:8px;text-align:center}
  .counters div{flex:1;background:#fafafa;padding:10px;border-radius:8px}
  .counters span{display:block;font-size:20px;font-weight:bold}
</style>
</head>
<body>
<h1>Egg Sorter - Monitoring</h1>

<div class="card">
  <div class="row"><span class="label">Jarak (gate)</span><span class="value" id="distance">-- cm</span></div>
  <div class="row"><span class="label">Berat</span><span class="value" id="weight">-- g</span></div>
  <div class="row"><span class="label">Nilai gas (MQ-135)</span><span class="value" id="gas">--</span></div>
  <div class="step" id="step">Menunggu telur</div>
  <div id="category" class="cat idle">-</div>
</div>

<div class="card">
  <div class="counters">
    <div>Ringan<span id="cRingan">0</span></div>
    <div>Sedang<span id="cSedang">0</span></div>
    <div>Berat<span id="cBerat">0</span></div>
    <div>Busuk<span id="cBusuk">0</span></div>
  </div>
</div>

<script>
let ws = new WebSocket("ws://" + location.host + "/ws");
ws.onmessage = function(evt){
  let d = JSON.parse(evt.data);
  document.getElementById("distance").innerText = d.distance + " cm";
  document.getElementById("weight").innerText = d.weight.toFixed(1) + " g";
  document.getElementById("gas").innerText = d.gas;
  document.getElementById("step").innerText = d.step;
  document.getElementById("cRingan").innerText = d.cRingan;
  document.getElementById("cSedang").innerText = d.cSedang;
  document.getElementById("cBerat").innerText = d.cBerat;
  document.getElementById("cBusuk").innerText = d.cBusuk;

  let cat = document.getElementById("category");
  cat.innerText = d.category;
  cat.className = "cat " + d.categoryClass;
};
ws.onclose = function(){ setTimeout(()=>location.reload(), 2000); };
</script>
</body>
</html>
)HTML";

// ---------------- FUNGSI BANTUAN ----------------

void broadcastState(String step, String rawStep, String category,
                    String categoryClass) {
  unsigned long now = millis();
  if (now - lastBroadcast < BROADCAST_INTERVAL_MS)
    return;
  lastBroadcast = now;
  ws.cleanupClients();
  if (ws.count() == 0)
    return;

  StaticJsonDocument<600> doc;
  doc["distance"] = currentDistance;
  doc["weight"] = currentWeight;
  doc["gas"] = currentGas;
  doc["step"] = step;
  doc["rawStep"] = rawStep;
  doc["category"] = category;
  doc["categoryClass"] = categoryClass;
  doc["cRingan"] = countRingan;
  doc["cSedang"] = countSedang;
  doc["cBerat"] = countBerat;
  doc["cBusuk"] = countBusuk;
  doc["cal_factor"] = CALIBRATION_FACTOR;
  doc["scaleStatus"] = scaleEnabled ? "online" : "offline";
  doc["manualMode"] = manualOverrideMode;
  // Config servo & threshold (untuk dashboard kalibrasi)
  doc["cfg_gate_open"]    = GATE_OPEN_ANGLE;
  doc["cfg_gate_cls"]     = GATE_CLOSED_ANGLE;
  doc["cfg_push"]         = PENDORONG_PUSH_ANGLE;
  doc["cfg_j1_open"]      = JALUR1_OPEN_ANGLE;
  doc["cfg_j2_open"]      = JALUR2_OPEN_ANGLE;
  doc["cfg_j3_berat"]     = JALUR3_BERAT_ANGLE;
  doc["cfg_gas_delta"]    = GAS_DELTA;
  doc["gas_baseline"]     = GAS_BASELINE;
  doc["cfg_us_dist"]      = EGG_AT_GATE_DISTANCE_CM;
  doc["cfg_w_ringan"]     = WEIGHT_RINGAN_MAX;
  doc["cfg_w_sedang"]     = WEIGHT_SEDANG_MAX;
  doc["backend_url"]      = BACKEND_URL;

  String json;
  serializeJson(doc, json);
  if (ws.count() > 0)
    ws.textAll(json);
}

// Helper: simpan semua config servo & threshold ke NVS
void saveConfigToNVS() {
  preferences.begin("egg-sorter", false);
  preferences.putInt("gate_open",   GATE_OPEN_ANGLE);
  preferences.putInt("gate_cls",    GATE_CLOSED_ANGLE);
  preferences.putInt("push_angle",  PENDORONG_PUSH_ANGLE);
  preferences.putInt("j1_open",     JALUR1_OPEN_ANGLE);
  preferences.putInt("j2_open",     JALUR2_OPEN_ANGLE);
  preferences.putInt("j3_berat",    JALUR3_BERAT_ANGLE);
  preferences.putInt("gas_delta",   GAS_DELTA);
  preferences.putInt("us_dist",     EGG_AT_GATE_DISTANCE_CM);
  preferences.putFloat("w_ringan",  WEIGHT_RINGAN_MAX);
  preferences.putFloat("w_sedang",  WEIGHT_SEDANG_MAX);
  preferences.putString("backend_url", BACKEND_URL);
  preferences.end();
  Serial.println("[NVS] Konfigurasi tersimpan.");
}

// Forward declaration - diperlukan karena Arduino IDE gagal auto-generate
// prototype untuk fungsi yang dipanggil dari dalam handleWsMessage
// (parser Arduino terkacaukan oleh tipe uint8_t* dari ESPAsyncWebServer)
void safeTare();

// Handler perintah WebSocket dari dashboard
void handleWsMessage(uint8_t *data, size_t len) {
  StaticJsonDocument<300> cmd;
  DeserializationError err = deserializeJson(cmd, data, len);
  if (err) {
    Serial.print("[WS] JSON err: ");
    Serial.println(err.c_str());
    return;
  }

  String action = cmd["action"] | "";
  Serial.print("[WS] action: ");
  Serial.println(action);

  if (action == "set_mode") {
    manualOverrideMode = cmd["manual"] | false;

  } else if (action == "servo") {
    if (!manualOverrideMode)
      return;
    String name = cmd["name"] | "";
    int angle = constrain((int)(cmd["value"] | 0), 0, 180);
    if (name == "gate")
      servoGate.write(angle);
    else if (name == "pendorong")
      servoPendorong.write(angle);
    else if (name == "jalur1")
      servoJalur1.write(angle);
    else if (name == "jalur2")
      servoJalur2.write(angle);
    else if (name == "jalur3")
      servoJalur3.write(angle);

  } else if (action == "save_servo") {
    // Simpan sudut kalibrasi servo ke NVS
    // payload: {action:"save_servo", key:"gate_open", value:60}
    String key = cmd["key"] | "";
    int val = constrain((int)(cmd["value"] | 0), 0, 180);
    if (key == "gate_open") {
      GATE_OPEN_ANGLE = val;
      servoGate.write(val);
    } else if (key == "gate_cls") {
      GATE_CLOSED_ANGLE = val;
    } else if (key == "push_angle") {
      PENDORONG_PUSH_ANGLE = val;
    } else if (key == "j1_open") {
      JALUR1_OPEN_ANGLE = val;
      servoJalur1.write(val);
    } else if (key == "j2_open") {
      JALUR2_OPEN_ANGLE = val;
      servoJalur2.write(val);
    } else if (key == "j3_berat") {
      JALUR3_BERAT_ANGLE = val;
      servoJalur3.write(val);
    }
    saveConfigToNVS();
    Serial.printf("[WS] save_servo %s=%d\n", key.c_str(), val);

  } else if (action == "save_threshold") {
    String key = cmd["key"] | "";
    if      (key == "gas_delta")  { GAS_DELTA = (int)(cmd["value"] | GAS_DELTA); }
    else if (key == "us_dist")    { EGG_AT_GATE_DISTANCE_CM = (int)(cmd["value"] | EGG_AT_GATE_DISTANCE_CM); }
    else if (key == "w_ringan")   { WEIGHT_RINGAN_MAX = (float)(cmd["value"] | WEIGHT_RINGAN_MAX); }
    else if (key == "w_sedang")   { WEIGHT_SEDANG_MAX = (float)(cmd["value"] | WEIGHT_SEDANG_MAX); }
    saveConfigToNVS();
    Serial.printf("[WS] save_threshold %s\n", key.c_str());

  } else if (action == "tare") {
    safeTare();

  } else if (action == "calibrate") {
    float knownWeight = cmd["known_weight"] | 0.0;
    if (knownWeight > 0 && scaleEnabled) {
      long rawVal = scale.read_average(10);
      float newFactor = (float)(rawVal - scale.get_offset()) / knownWeight;
      if (newFactor != 0) {
        CALIBRATION_FACTOR = newFactor;
        scale.set_scale(CALIBRATION_FACTOR);
        preferences.begin("egg-sorter", false);
        preferences.putFloat("cal_factor", CALIBRATION_FACTOR);
        preferences.end();
        Serial.print("[WS] Cal.factor baru: ");
        Serial.println(CALIBRATION_FACTOR);
      }
    }
  } else if (action == "save_backend_url") {
    // Update URL backend tanpa reflash
    // payload: {action:"save_backend_url",
    // url:"http://192.168.x.x:8080/api/sort-result.php"}
    String url = cmd["url"] | "";
    if (url.length() > 0 && url.length() < 128) {
      url.toCharArray(BACKEND_URL, sizeof(BACKEND_URL));
      preferences.begin("egg-sorter", false);
      preferences.putString("backend_url", BACKEND_URL);
      preferences.end();
      Serial.print("[WS] Backend URL baru: ");
      Serial.println(BACKEND_URL);
    }
  }
}

long readDistanceCM() {
  digitalWrite(PIN_ULTRASONIC_TRIG, LOW);
  delayMicroseconds(2);
  digitalWrite(PIN_ULTRASONIC_TRIG, HIGH);
  delayMicroseconds(10);
  digitalWrite(PIN_ULTRASONIC_TRIG, LOW);

  long duration = pulseIn(PIN_ULTRASONIC_ECHO, HIGH, 30000);
  if (duration == 0)
    return -1;
  return duration * 0.0343 / 2;
}

float readWeightStable() {
  if (!scaleEnabled)
    return currentWeight;
  long sum = 0;
  int count = 0;
  for (int i = 0; i < 5; i++) {
    unsigned long start = millis();
    bool ready = false;
    while (millis() - start < 100) { // Tunggu maks 100ms per pembacaan
      if (scale.is_ready()) {
        ready = true;
        break;
      }
      delay(5);
    }
    if (ready) {
      sum += scale.read();
      count++;
    }
  }
  if (count > 0) {
    return (float)(sum / count - scale.get_offset()) / scale.get_scale();
  }
  return currentWeight; // Kembalikan berat terakhir jika gagal
}

// Kategorisasi berdasarkan berat saja
// Gas dicek SEBELUM fungsi ini dipanggil di state DECIDE_CATEGORY
String decideCategory(float weight, String &categoryClass) {
  if (weight < WEIGHT_RINGAN_MAX) {
    categoryClass = "ringan";
    return "RINGAN";
  } else if (weight <= WEIGHT_SEDANG_MAX) {
    categoryClass = "sedang";
    return "SEDANG";
  } else {
    categoryClass = "berat";
    return "BERAT";
  }
}

// Menjadwalkan HTTP POST tanpa langsung blocking loop
void queueSortResultToBackend(float weight, int gas, String category) {
  pendingWeight = weight;
  pendingGas = gas;
  pendingCategory = category;
  pendingBackendPost = true;
}

// Panggil dari loop() saat ada waktu luang
void flushBackendPost() {
  if (!pendingBackendPost)
    return;
  pendingBackendPost = false;

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("[HTTP] Wi-Fi tidak tersambung. Data tidak dikirim.");
    return;
  }

  HTTPClient http;
  http.begin(BACKEND_URL);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", API_KEY);
  http.setTimeout(3000); // max 3 detik, jangan terlalu lama

  StaticJsonDocument<200> doc;
  doc["weight"] = pendingWeight;
  doc["gas_value"] = pendingGas;
  doc["category"] = pendingCategory;

  String body;
  serializeJson(doc, body);
  Serial.print("[HTTP] POST ke backend: ");
  Serial.println(body);

  int code = http.POST(body);
  if (code > 0) {
    Serial.print("[HTTP] Respons ");
    Serial.println(code);
  } else {
    Serial.print("[HTTP] Gagal, kode error: ");
    Serial.println(code);
  }
  http.end();
}

bool isHX711Connected() {
  pinMode(PIN_HX711_DT, INPUT_PULLUP);
  pinMode(PIN_HX711_SCK, OUTPUT);
  digitalWrite(PIN_HX711_SCK, LOW);
  delay(50);

  unsigned long start = millis();
  while (millis() - start < 300) {
    if (digitalRead(PIN_HX711_DT) == LOW) return true;
    delay(10);
  }
  return false;
}

// Kalibrasi baseline gas ambient — dipanggil sekali di setup()
// Baca 20 sampel udara bersih sebelum alat dioperasikan
void calibrateGasBaseline() {
  Serial.println("[GAS] Mengkalibrasi baseline udara bersih (20 sampel x 50ms)...");
  long sum = 0;
  for (int i = 0; i < 20; i++) {
    sum += analogRead(PIN_MQ135_AOUT);
    delay(50);
  }
  GAS_BASELINE = (int)(sum / 20);
  Serial.printf("[GAS] Baseline = %d ADC | Deteksi gas jika > %d ADC (baseline + delta %d)\n",
                GAS_BASELINE, GAS_BASELINE + GAS_DELTA, GAS_DELTA);
}

void safeTare() {
  if (!scaleEnabled)
    return;
  Serial.println("[TIMBANGAN] Mencoba melakukan Tare (Nolkan Timbangan)...");
  unsigned long start = millis();
  bool ready = false;
  while (millis() - start < 1500) { // Tunggu maksimal 1.5 detik
    if (scale.is_ready()) {
      ready = true;
      break;
    }
    delay(20);
  }
  if (ready) {
    scale.tare();
    Serial.println("[TIMBANGAN] Tare berhasil dilakukan.");
  } else {
    Serial.println(
        "[PERINGATAN] Sensor HX711 tidak merespon! Proses Tare dilewati.");
  }
}

// ---------------- SETUP ----------------
void setup() {
  WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0); // Matikan brownout detector

  Serial.begin(115200);

  // 1. Mencoba tersambung ke Wi-Fi (STA Mode) di awal
  WiFi.disconnect(true);
  delay(100);
  WiFi.mode(WIFI_AP_STA);
  Serial.print("Menghubungkan ke Wi-Fi ");
  Serial.println(WIFI_SSID);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 15) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nTerhubung ke Wi-Fi lokal!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nGagal terhubung ke Wi-Fi lokal. Mengaktifkan Mode AP...");
    WiFi.softAP(AP_SSID, AP_PASSWORD);
    Serial.print("AP aktif, IP: ");
    Serial.println(WiFi.softAPIP());
  }

  // 2. Muat SEMUA konfigurasi dari NVS
  preferences.begin("egg-sorter", true);
  CALIBRATION_FACTOR      = preferences.getFloat("cal_factor",  CALIBRATION_FACTOR);
  GATE_OPEN_ANGLE         = preferences.getInt("gate_open",    GATE_OPEN_ANGLE);
  GATE_CLOSED_ANGLE       = preferences.getInt("gate_cls",     GATE_CLOSED_ANGLE);
  PENDORONG_PUSH_ANGLE    = preferences.getInt("push_angle",   PENDORONG_PUSH_ANGLE);
  JALUR1_OPEN_ANGLE       = preferences.getInt("j1_open",      JALUR1_OPEN_ANGLE);
  JALUR2_OPEN_ANGLE       = preferences.getInt("j2_open",      JALUR2_OPEN_ANGLE);
  JALUR3_BERAT_ANGLE      = preferences.getInt("j3_berat",     JALUR3_BERAT_ANGLE);
  GAS_DELTA               = preferences.getInt("gas_delta",    GAS_DELTA);
  EGG_AT_GATE_DISTANCE_CM = preferences.getInt("us_dist",      EGG_AT_GATE_DISTANCE_CM);
  WEIGHT_RINGAN_MAX       = preferences.getFloat("w_ringan",   WEIGHT_RINGAN_MAX);
  WEIGHT_SEDANG_MAX       = preferences.getFloat("w_sedang",   WEIGHT_SEDANG_MAX);
  // Load backend URL - fallback ke nilai default jika belum pernah disimpan
  String savedUrl = preferences.getString("backend_url", String(BACKEND_URL));
  savedUrl.toCharArray(BACKEND_URL, sizeof(BACKEND_URL));
  preferences.end();
  Serial.printf("[NVS] Cal.factor=%.2f | Gas.delta=%d | US.dist=%d\n",
                CALIBRATION_FACTOR, GAS_DELTA, EGG_AT_GATE_DISTANCE_CM);
  Serial.print("[NVS] Backend URL: "); Serial.println(BACKEND_URL);

  // 3. Inisialisasi sensor timbangan HX711
  Serial.println("[BOOT] Memeriksa koneksi fisik sensor timbangan HX711...");
  if (isHX711Connected()) {
    Serial.println("[BOOT] Sensor HX711 terdeteksi! Mengaktifkan timbangan...");
    scaleEnabled = true;
    scale.begin(PIN_HX711_DT, PIN_HX711_SCK);
    scale.set_scale(CALIBRATION_FACTOR);
    safeTare();
  } else {
    Serial.println("[PERINGATAN] Sensor HX711 tidak terhubung! Fitur timbangan "
                   "dinonaktifkan.");
    scaleEnabled = false;
  }

  pinMode(PIN_ULTRASONIC_TRIG, OUTPUT);
  pinMode(PIN_ULTRASONIC_ECHO, INPUT);

  ESP32PWM::allocateTimer(0);
  ESP32PWM::allocateTimer(1);
  ESP32PWM::allocateTimer(2);
  ESP32PWM::allocateTimer(3);

  servoGate.setPeriodHertz(50);
  servoPendorong.setPeriodHertz(50);
  servoJalur1.setPeriodHertz(50);
  servoJalur2.setPeriodHertz(50);
  servoJalur3.setPeriodHertz(50);

  servoGate.attach(PIN_SERVO_GATE, 500, 2400);
  servoPendorong.attach(PIN_SERVO_PENDORONG, 500, 2400);
  servoJalur1.attach(PIN_SERVO_JALUR1, 500, 2400);
  servoJalur2.attach(PIN_SERVO_JALUR2, 500, 2400);
  servoJalur3.attach(PIN_SERVO_JALUR3, 500, 2400);

  servoGate.write(GATE_CLOSED_ANGLE);
  servoPendorong.write(PENDORONG_HOME_ANGLE);
  servoJalur1.write(JALUR1_CLOSED_ANGLE);
  servoJalur2.write(JALUR2_CLOSED_ANGLE);
  servoJalur3.write(JALUR3_CLOSED_ANGLE);

  pinMode(PIN_MQ135_AOUT, INPUT);
  analogReadResolution(12);
  calibrateGasBaseline(); // Kalibrasi baseline udara bersih untuk deteksi gas relatif

  ws.onEvent([](AsyncWebSocket *serverWs, AsyncWebSocketClient *client,
                AwsEventType type, void *arg, uint8_t *data, size_t len) {
    if (type == WS_EVT_CONNECT) {
      Serial.printf("[WS] Client #%u terhubung dari %s\n", client->id(),
                    client->remoteIP().toString().c_str());
    } else if (type == WS_EVT_DISCONNECT) {
      Serial.printf("[WS] Client #%u terputus\n", client->id());
    } else if (type == WS_EVT_DATA) {
      AwsFrameInfo *info = (AwsFrameInfo *)arg;
      // Hanya proses frame teks yang sudah lengkap
      if (info->final && info->index == 0 && info->len == len &&
          info->opcode == WS_TEXT) {
        handleWsMessage(data, len);
      }
    } else if (type == WS_EVT_ERROR) {
      Serial.printf("[WS] Error client #%u\n", client->id());
    }
  });
  server.addHandler(&ws);

  server.on("/", HTTP_GET, [](AsyncWebServerRequest *request) {
    request->send_P(200, "text/html", INDEX_HTML);
  });

  server.begin();
  Serial.println("Web server dimulai.");

  currentState = WAIT_EGG_AT_GATE;
  stateTimer = millis();
}

// ---------------- LOOP UTAMA (STATE MACHINE NON-BLOCKING) ----------------
void loop() {
  ws.cleanupClients();

  // --- Cetak status koneksi SEKALI saat kondisi berubah ---
  static bool lastWifiState = false;
  bool currentWifiState = (WiFi.status() == WL_CONNECTED);
  if (currentWifiState != lastWifiState) {
    lastWifiState = currentWifiState;
    if (currentWifiState) {
      Serial.print("[INFO KONEKSI - TEST] Terhubung ke Wi-Fi | IP ESP32: ");
      Serial.println(WiFi.localIP());
    } else {
      Serial.print("[INFO KONEKSI - TEST] Mode AP Aktif (SSID: ");
      Serial.print(AP_SSID);
      Serial.print(") | IP ESP32: ");
      Serial.println(WiFi.softAPIP());
    }
  }

  // --- Print nilai sensor setiap 2 detik ---
  static unsigned long lastSensorPrint = 0;
  if (millis() - lastSensorPrint >= 2000) {
    lastSensorPrint = millis();
    Serial.printf("[SENSOR] Jarak: %ld cm | Berat: %.1f g | Gas: %d | State: "
                  "%d | WS Client: %u\n",
                  currentDistance, currentWeight, currentGas, (int)currentState,
                  ws.count());
  }

  switch (currentState) {

  case WAIT_EGG_AT_GATE: {
    if (millis() - lastUltrasonicPoll >= ULTRASONIC_POLL_MS) {
      currentDistance = readDistanceCM();
      lastUltrasonicPoll = millis();
    }
    broadcastState("Menunggu telur di gate", "WAIT_EGG_AT_GATE", "-", "idle");

    case OPEN_GATE: {
      broadcastState("Gate terbuka, telur menggelinding", "OPEN_GATE", "-", "idle");
      if (millis() - stateTimer >= T_GATE_OPEN_HOLD) {
        servoGate.write(GATE_CLOSED_ANGLE);
        currentState = CLOSE_GATE;
        stateTimer = millis();
      }
      break;
    }

    case CLOSE_GATE: {
      broadcastState("Gate menutup kembali", "CLOSE_GATE", "-", "idle");
      if (millis() - stateTimer >= T_GATE_CLOSE_WAIT) {
        currentState = WEIGHING_SETTLE;
        stateTimer = millis();
      }
      break;
    }

    case WEIGHING_SETTLE: {
      // loadcell & gas dibaca bersamaan selama tahap ini
      currentWeight = readWeightStable();
      currentGas = analogRead(PIN_MQ135_AOUT);
      broadcastState("Menimbang & mengecek gas...", "WEIGHING_SETTLE", "-", "idle");

      if (millis() - stateTimer >= T_WEIGHING_SETTLE) {
        currentState = DECIDE_CATEGORY;
        stateTimer = millis();
      }
      break;
    }

    case DECIDE_CATEGORY: {
      String categoryClass;

      // PRIORITAS 1: Gas override — jika gas terdeteksi, BUSUK meski berat < 10g
      if (currentGas > (GAS_BASELINE + GAS_DELTA)) {
        lastCategory  = "BUSUK";
        categoryClass = "busuk";
        Serial.printf("[DECIDE] Gas terdeteksi! (%d > %d). Lanjut ke BUSUK meski berat %.1fg.\n",
                      currentGas, GAS_BASELINE + GAS_DELTA, currentWeight);
        broadcastState("Gas terdeteksi - BUSUK!", "DECIDE_CATEGORY", lastCategory, categoryClass);
        servoPendorong.write(PENDORONG_PUSH_ANGLE);
        currentState = PUSH_EGG;
        stateTimer   = millis();
        break;
      }

      // PRIORITAS 2: Cek berat minimum — timbangan harus ada beban valid
      if (currentWeight < WEIGHT_MIN_DETECT) {
        Serial.printf("[DECIDE] Berat terlalu ringan (%.1fg < %.1fg). Timbangan kosong. Abort & retry.\n",
                      currentWeight, WEIGHT_MIN_DETECT);
        broadcastState("Timbangan kosong - mengulang...", "ABORT_RETRY", "-", "idle");
        servoGate.write(GATE_OPEN_ANGLE); // Buka gate lagi, bantu telur yang mungkin nyangkut
        currentState = ABORT_RETRY;
        stateTimer   = millis();
        break;
      }

      // PRIORITAS 3: Kategorisasi normal berdasarkan berat
      lastCategory = decideCategory(currentWeight, categoryClass);
      broadcastState("Kategori ditentukan", "DECIDE_CATEGORY", lastCategory, categoryClass);
      servoPendorong.write(PENDORONG_PUSH_ANGLE);
      currentState = PUSH_EGG;
      stateTimer   = millis();
      break;
<<<<<<< HEAD
    }
    break;
  }

  case OPEN_GATE: {
    broadcastState("Gate terbuka, telur menggelinding", "OPEN_GATE", "-",
                   "idle");
    if (millis() - stateTimer >= T_GATE_OPEN_HOLD) {
      servoGate.write(GATE_CLOSED_ANGLE);
      currentState = CLOSE_GATE;
      stateTimer = millis();
=======
>>>>>>> 4f30178cca7a31db7bede5cd73e507a6c816dfb1
    }
    break;
  }

  case CLOSE_GATE: {
    broadcastState("Gate menutup kembali", "CLOSE_GATE", "-", "idle");
    if (millis() - stateTimer >= T_GATE_CLOSE_WAIT) {
      currentState = WEIGHING_SETTLE;
      stateTimer = millis();
    }
    break;
  }

  case WEIGHING_SETTLE: {
    // loadcell & gas dibaca bersamaan selama tahap ini
    currentWeight = readWeightStable();
    currentGas = analogRead(PIN_MQ135_AOUT);
    broadcastState("Menimbang & mengecek gas...", "WEIGHING_SETTLE", "-",
                   "idle");

    if (millis() - stateTimer >= T_WEIGHING_SETTLE) {
      currentState = DECIDE_CATEGORY;
      stateTimer = millis();
    }
    break;
  }

  case DECIDE_CATEGORY: {
    String categoryClass;
    lastCategory = decideCategory(currentWeight, currentGas, categoryClass);
    broadcastState("Kategori ditentukan", "DECIDE_CATEGORY", lastCategory,
                   categoryClass);

    servoPendorong.write(PENDORONG_PUSH_ANGLE);
    currentState = PUSH_EGG;
    stateTimer = millis();
    break;
  }

  case PUSH_EGG: {
    broadcastState("Mendorong telur ke jalur sortir", "PUSH_EGG", lastCategory,
                   "idle");
    if (millis() - stateTimer >= T_PENDORONG_HOLD) {
      servoPendorong.write(PENDORONG_HOME_ANGLE);
      currentState = RETURN_PENDORONG;
      stateTimer = millis();
    }
    break;
  }

  case RETURN_PENDORONG: {
    broadcastState("Pendorong kembali ke posisi awal", "RETURN_PENDORONG",
                   lastCategory, "idle");
    if (millis() - stateTimer >= T_PENDORONG_RETURN) {
      // Buka flap sesuai kategori
      // BUSUK: tidak pakai servo, telur langsung lurus ke penampungan
      if (lastCategory == "RINGAN") {
        servoJalur1.write(JALUR1_OPEN_ANGLE);
      } else if (lastCategory == "SEDANG") {
        servoJalur2.write(JALUR2_OPEN_ANGLE);
      } else if (lastCategory == "BERAT") {
        servoJalur3.write(JALUR3_BERAT_ANGLE);
      }
      // BUSUK: tidak ada aksi servo
      currentState = OPEN_SORT_GATE;
      stateTimer = millis();
    }
    break;
  }

  case OPEN_SORT_GATE: {
    String categoryClass;
    if (lastCategory == "RINGAN")
      categoryClass = "ringan";
    else if (lastCategory == "SEDANG")
      categoryClass = "sedang";
    else if (lastCategory == "BERAT")
      categoryClass = "berat";
    else
      categoryClass = "busuk";

    broadcastState("Telur masuk penampungan", "OPEN_SORT_GATE", lastCategory,
                   categoryClass);

    if (millis() - stateTimer >= T_SORT_GATE_OPEN) {
      // Tutup flap yang tadi dibuka (BUSUK tidak buka flap)
      if (lastCategory == "RINGAN")
        servoJalur1.write(JALUR1_CLOSED_ANGLE);
      else if (lastCategory == "SEDANG")
        servoJalur2.write(JALUR2_CLOSED_ANGLE);
      else if (lastCategory == "BERAT")
        servoJalur3.write(JALUR3_CLOSED_ANGLE);
      // BUSUK: tidak ada servo yang perlu ditutup

      // update counter dan kirim data ke backend
      if (lastCategory == "RINGAN") {
        countRingan++;
        queueSortResultToBackend(currentWeight, currentGas, "ringan");
      } else if (lastCategory == "SEDANG") {
        countSedang++;
        queueSortResultToBackend(currentWeight, currentGas, "sedang");
      } else if (lastCategory == "BERAT") {
        countBerat++;
        queueSortResultToBackend(currentWeight, currentGas, "berat");
      } else {
        countBusuk++;
        queueSortResultToBackend(currentWeight, currentGas, "busuk");
      }

      currentState = CLOSE_SORT_GATE;
      stateTimer = millis();
    }
    break;
  }

  case CLOSE_SORT_GATE: {
    broadcastState("Jalur sortir kembali netral", "CLOSE_SORT_GATE", "-",
                   "idle");
    if (millis() - stateTimer >= T_SORT_GATE_CLOSE) {
      currentState = WAIT_EGG_AT_GATE;
      stateTimer = millis();
      currentDistance = -1;
    }

    case ABORT_RETRY: {
      // Timbangan kosong - buka gate sebentar lalu tunggu T_ABORT_RETRY_WAIT
      unsigned long elapsed = millis() - stateTimer;
      broadcastState("Timbangan kosong - mengulang...", "ABORT_RETRY", "-", "idle");

      // Tutup gate setelah T_ABORT_GATE_REOPEN (500ms)
      if (elapsed >= T_ABORT_GATE_REOPEN) {
        servoGate.write(GATE_CLOSED_ANGLE); // Idempotent, aman dipanggil berulang
      }

      // Setelah T_ABORT_RETRY_WAIT (15 detik), kembali ke siklus awal
      if (elapsed >= T_ABORT_RETRY_WAIT) {
        Serial.println("[ABORT] Jeda 15 detik selesai. Siap menerima telur baru.");
        currentDistance = -1;
        currentState    = WAIT_EGG_AT_GATE;
        stateTimer      = millis();
      }
      break;
    }

    case ABORT_RETRY: {
      // Timbangan kosong - buka gate sebentar lalu tunggu T_ABORT_RETRY_WAIT
      unsigned long elapsed = millis() - stateTimer;
      broadcastState("Timbangan kosong - mengulang...", "ABORT_RETRY", "-", "idle");

      // Tutup gate setelah T_ABORT_GATE_REOPEN (500ms)
      if (elapsed >= T_ABORT_GATE_REOPEN) {
        servoGate.write(GATE_CLOSED_ANGLE); // Idempotent, aman dipanggil berulang
      }

      // Setelah T_ABORT_RETRY_WAIT (15 detik), kembali ke siklus awal
      if (elapsed >= T_ABORT_RETRY_WAIT) {
        Serial.println("[ABORT] Jeda 15 detik selesai. Siap menerima telur baru.");
        currentDistance = -1;
        currentState    = WAIT_EGG_AT_GATE;
        stateTimer      = millis();
      }
      break;
    }
  }

  // Kirim HTTP POST jika ada antrian (lakukan di luar state machine)
  flushBackendPost();

  delay(20);
}
