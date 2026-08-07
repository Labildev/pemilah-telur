import re

with open('egg_sorter_esp32/egg_sorter_esp32.ino', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Includes
content = content.replace('#include <AsyncTCP.h>', '')
content = content.replace('#define WS_MAX_QUEUED_MESSAGES 64', '')
content = content.replace('#include <ESPAsyncWebServer.h>', '#include <WebSocketsServer.h>')

# 2. Global variables
content = content.replace('AsyncWebServer server(80);', '')
content = content.replace('AsyncWebSocket ws("/ws");', 'WebSocketsServer webSocket(81);')

# 3. broadcastState function
old_broadcast = '''void broadcastState(const char *message) {
  if (ws.count() > 0) {
    ws.textAll(message);
  }
}'''
new_broadcast = '''void broadcastState(const char *message) {
  webSocket.broadcastTXT(message);
}'''
content = content.replace(old_broadcast, new_broadcast)

# 4. handleWsMessage signature -> webSocketEvent
old_ws_handle = 'void handleWsMessage(uint8_t *data, size_t len) {'
new_ws_handle = '''void webSocketEvent(uint8_t num, WStype_t type, uint8_t * payload, size_t length) {
  if (type == WStype_TEXT) {
    uint8_t *data = payload;
    size_t len = length;
'''
content = content.replace(old_ws_handle, new_ws_handle)

# Find the end of handleWsMessage which ends before "void setup() {"
# We'll just replace "void setup() {" with "  }\n}\n\nvoid setup() {"
content = content.replace('void setup() {', '  }\n}\n\nvoid setup() {')

# 5. setup() modifications
old_setup_ws = '''  // WebSocket
  ws.onEvent([](AsyncWebSocket *serverWs, AsyncWebSocketClient *client,
                AwsEventType type, void *arg, uint8_t *data, size_t len) {
    if (type == WS_EVT_DATA) {
      AwsFrameInfo *info = (AwsFrameInfo *)arg;
      if (info->final && info->index == 0 && info->len == len &&
          info->opcode == WS_TEXT) {
        handleWsMessage(data, len);
      }
    }
  });
  server.addHandler(&ws);
  server.begin();
  Serial.println("Web/WebSocket Server dimulai!");'''

new_setup_ws = '''  // WebSocket
  webSocket.begin();
  webSocket.onEvent(webSocketEvent);
  Serial.println("WebSocket Server dimulai pada port 81!");'''
content = content.replace(old_setup_ws, new_setup_ws)

# 6. loop() modifications
old_loop_start = '''void loop() {
  unsigned long currentMillis = millis();'''

new_loop_start = '''void loop() {
  webSocket.loop();
  unsigned long currentMillis = millis();'''
content = content.replace(old_loop_start, new_loop_start)

# Clean up empty ws.cleanupClients() if it exists
content = content.replace('ws.cleanupClients();', '')

with open('egg_sorter_esp32/egg_sorter_esp32.ino', 'w', encoding='utf-8') as f:
    f.write(content)

print("Migration script executed successfully.")
