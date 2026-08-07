import re
with open('egg_sorter_esp32/egg_sorter_esp32.ino', 'r', encoding='utf-8') as f:
    content = f.read()

loop_index = content.find('void loop() {')
if loop_index != -1:
    new_loop = '''void loop() {
  if (WiFi.status() != WL_CONNECTED) {
    // Reconnect logic
  }

  ws.cleanupClients();

  if (manualOverrideMode) {
    // Mode manual: abaikan sensor, hanya layani WebSocket
    delay(50);
    return;
  }

  // --- STATE MACHINE OTOMATIS ---
  static unsigned long lastSensorPrint = 0;
  if (millis() - lastSensorPrint >= 2000) {
    lastSensorPrint = millis();
    Serial.printf("[SENSOR] Jarak: %ld cm | Berat: %.1f g | Gas: %d | State: %d | WS Client: %u\\n",
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

    if (currentDistance != -1 && currentDistance <= EGG_AT_GATE_DISTANCE_CM) {
      servoGate.write(GATE_OPEN_ANGLE);
      currentState = OPEN_GATE;
      stateTimer = millis();
    }
    break;
  }

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
    if (mockWeight >= 0.0) {
      currentWeight = mockWeight;
      mockWeight = -1.0; // Reset setelah digunakan untuk 1 siklus
      Serial.printf("[DEMO] Menggunakan berat injeksi: %.1f g\\n", currentWeight);
    } else {
      currentWeight = readWeightStable();
    }
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

    // PRIORITAS 1: Gas override
    if (currentGas > (GAS_BASELINE + GAS_DELTA)) {
      lastCategory = "BUSUK";
      categoryClass = "busuk";
      Serial.printf("[DECIDE] Gas terdeteksi! (%d > %d). Lanjut ke BUSUK meski berat %.1fg.\\n",
                    currentGas, GAS_BASELINE + GAS_DELTA, currentWeight);
      broadcastState("Gas terdeteksi - BUSUK!", "DECIDE_CATEGORY", lastCategory, categoryClass);
      servoPendorong.write(PENDORONG_PUSH_ANGLE);
      currentState = PUSH_EGG;
      stateTimer = millis();
      break;
    }

    // PRIORITAS 2: Cek berat minimum
    if (currentWeight < WEIGHT_MIN_DETECT) {
      Serial.printf("[DECIDE] Berat terlalu ringan (%.1fg < %.1fg). Timbangan kosong. Abort & retry.\\n",
                    currentWeight, WEIGHT_MIN_DETECT);
      broadcastState("Timbangan kosong - mengulang...", "ABORT_RETRY", "-", "idle");
      servoGate.write(GATE_OPEN_ANGLE);
      currentState = ABORT_RETRY;
      stateTimer = millis();
      break;
    }

    // PRIORITAS 3: Kategorisasi normal berdasarkan berat
    lastCategory = decideCategory(currentWeight, categoryClass);
    broadcastState("Kategori ditentukan", "DECIDE_CATEGORY", lastCategory, categoryClass);
    servoPendorong.write(PENDORONG_PUSH_ANGLE);
    currentState = PUSH_EGG;
    stateTimer = millis();
    break;
  }

  case PUSH_EGG: {
    broadcastState("Mendorong telur ke jalur sortir", "PUSH_EGG", lastCategory, "idle");
    if (millis() - stateTimer >= T_PENDORONG_HOLD) {
      servoPendorong.write(PENDORONG_HOME_ANGLE);
      currentState = RETURN_PENDORONG;
      stateTimer = millis();
    }
    break;
  }

  case RETURN_PENDORONG: {
    broadcastState("Pendorong kembali ke posisi awal", "RETURN_PENDORONG", lastCategory, "idle");
    if (millis() - stateTimer >= T_PENDORONG_RETURN) {
      if (lastCategory == "RINGAN") {
        servoJalur1.write(JALUR1_OPEN_ANGLE);
      } else if (lastCategory == "SEDANG") {
        servoJalur2.write(JALUR2_OPEN_ANGLE);
      } else if (lastCategory == "BERAT") {
        servoJalur3.write(JALUR3_BERAT_ANGLE);
      }
      currentState = OPEN_SORT_GATE;
      stateTimer = millis();
    }
    break;
  }

  case OPEN_SORT_GATE: {
    String categoryClass;
    if (lastCategory == "RINGAN") categoryClass = "ringan";
    else if (lastCategory == "SEDANG") categoryClass = "sedang";
    else if (lastCategory == "BERAT") categoryClass = "berat";
    else categoryClass = "busuk";

    broadcastState("Telur masuk penampungan", "OPEN_SORT_GATE", lastCategory, categoryClass);

    if (millis() - stateTimer >= T_SORT_GATE_OPEN) {
      if (lastCategory == "RINGAN") servoJalur1.write(JALUR1_CLOSED_ANGLE);
      else if (lastCategory == "SEDANG") servoJalur2.write(JALUR2_CLOSED_ANGLE);
      else if (lastCategory == "BERAT") servoJalur3.write(JALUR3_CLOSED_ANGLE);

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
    broadcastState("Jalur sortir kembali netral", "CLOSE_SORT_GATE", "-", "idle");
    if (millis() - stateTimer >= T_SORT_GATE_CLOSE) {
      currentState = WAIT_EGG_AT_GATE;
      stateTimer = millis();
      currentDistance = -1;
    }
    break;
  }

  case ABORT_RETRY: {
    unsigned long elapsed = millis() - stateTimer;
    broadcastState("Timbangan kosong - mengulang...", "ABORT_RETRY", "-", "idle");

    if (elapsed >= T_ABORT_GATE_REOPEN) {
      servoGate.write(GATE_CLOSED_ANGLE);
    }

    if (elapsed >= T_ABORT_RETRY_WAIT) {
      Serial.println("[ABORT] Jeda selesai. Siap menerima telur baru.");
      currentDistance = -1;
      currentState = WAIT_EGG_AT_GATE;
      stateTimer = millis();
    }
    break;
  }
  } // end switch

  flushBackendPost();
  delay(20);
}
'''
    new_content = content[:loop_index] + new_loop
    with open('egg_sorter_esp32/egg_sorter_esp32.ino', 'w', encoding='utf-8') as f:
        f.write(new_content)
    print('loop() rewritten successfully.')
