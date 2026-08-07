## Tabel lengkap pemetaan pin ESP32S 38pin

| No | Komponen | Pin ESP32 | Keterangan |
|---|---|---|---|
| 1 | HX711 — DT | GPIO16 | Data output ke ESP32 |
| 2 | HX711 — SCK | GPIO4 | Clock |
| 3 | HX711 — VCC | 5V | Perlu 5V untuk stabilitas amplifier |
| 4 | HX711 — GND | GND | — |
| 5 | Load cell YZC-131 → HX711 | E+, E-, S+, S- | Terhubung ke terminal HX711, bukan langsung ke ESP32 |
| 6 | MQ-135 — AOUT | GPIO34 | Analog input |
| 7 | MQ-135 — DOUT | Tidak dipakai |
| 8 | MQ-135 — VCC | 5V | — |
| 9 | MQ-135 — GND | GND | — |
| 10 | HC-SR04 — TRIG | GPIO23 | Output dari ESP32 ke sensor |
| 11 | HC-SR04 — ECHO | GPIO19 | Input ke ESP32 — voltage divider wajib  |
| 12 | HC-SR04 — VCC | 5V | — |
| 13 | HC-SR04 — GND | GND | — |
| 14 | Servo Gate — signal | GPIO13 | PWM |
| 15 | Servo Pendorong — signal | GPIO27 | PWM |
| 16 | Servo Jalur 1 (Ringan) — signal | GPIO25 | PWM |
| 17 | Servo Jalur 2 (Sedang) — signal | GPIO26 | PWM |
| 18 | Servo Jalur 3 (Berat/Busuk) — signal | GPIO33 | PWM |
| 19 | Semua servo — VCC | 5V (dari adaptor eksternal, bukan dari pin ESP32) |
| 20 | Semua servo — GND | GND (gabung dengan GND ESP32) | Common ground wajib |