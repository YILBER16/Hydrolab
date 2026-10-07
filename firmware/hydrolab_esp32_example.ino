#include <WiFi.h>
#include <HTTPClient.h>
#include <OneWire.h>
#include <DallasTemperature.h>

const char* ssid = "TU_WIFI";
const char* password = "TU_PASSWORD";

// IP de tu PC en la misma WiFi + puerto de artisan serve
const char* API_BASE = "http://TU_IP";
const char* DEVICE_TOKEN = "hydrolab-esp32-dev-token-change-me";

// Sensor DS18B20 (sonda)
// Cableado típico:
//   Rojo    (VCC)  -> 3.3V
//   Negro   (GND)  -> GND
//   Amarillo(DATA) -> GPIO 15
// Imprescindible: resistencia 4.7kΩ entre VCC y DATA
#define ONE_WIRE_BUS 15
OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

const unsigned long SEND_INTERVAL_MS = 15000;
unsigned long lastSend = 0;

float lastTemperature = NAN;

void setup() {
  Serial.begin(115200);
  sensors.begin();

  WiFi.begin(ssid, password);

  Serial.print("Conectando al WiFi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("¡Conectado!");
  Serial.print("IP del ESP32: ");
  Serial.println(WiFi.localIP());
  Serial.print("API Hydrolab: ");
  Serial.println(API_BASE);
  Serial.printf("DS18B20 en GPIO %d | dispositivos: %d\n", ONE_WIRE_BUS, sensors.getDeviceCount());

  testBridge();
}

void testBridge() {
  HTTPClient http;

  http.begin(String(API_BASE) + "/api/v1/health");
  int healthCode = http.GET();
  Serial.printf("HEALTH %d: %s\n", healthCode, http.getString().c_str());
  http.end();

  http.begin(String(API_BASE) + "/api/v1/ping");
  http.addHeader("X-Device-Token", DEVICE_TOKEN);
  int pingCode = http.GET();
  Serial.printf("PING %d: %s\n", pingCode, http.getString().c_str());
  http.end();
}

bool readTemperatureSensor() {
  sensors.requestTemperatures();
  float temperature = sensors.getTempCByIndex(0);

  // -127 = sin sensor / cable malo; 85 = a veces lectura inválida al iniciar
  if (temperature == DEVICE_DISCONNECTED_C || temperature < -55 || temperature > 125) {
    Serial.println("Error leyendo DS18B20. Revisa GPIO 15 y la resistencia 4.7k.");
    return false;
  }

  lastTemperature = temperature;
  Serial.printf("Temperatura agua: %.2f °C\n", lastTemperature);
  return true;
}

String buildReadingsJson() {
  String body = "{";
  body += "\"pump_on\":true";
  body += ",\"readings\":[";
  body += "{\"type\":\"temperature\",\"value\":" + String(lastTemperature, 2) + "}";
  body += "]}";
  return body;
}

void sendReadings() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi desconectado, reintentando...");
    WiFi.reconnect();
    return;
  }

  if (!readTemperatureSensor()) {
    return;
  }

  HTTPClient http;
  http.begin(String(API_BASE) + "/api/v1/readings");
  http.addHeader("Content-Type", "application/json");
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Device-Token", DEVICE_TOKEN);
  http.setTimeout(10000);

  String body = buildReadingsJson();
  Serial.print("Enviando: ");
  Serial.println(body);

  int code = http.POST(body);
  Serial.printf("POST %d: %s\n", code, http.getString().c_str());

  if (code <= 0) {
    Serial.println("Sin respuesta. Revisa: php artisan serve --host=0.0.0.0 --port=8000 y firewall.");
  } else if (code == 401) {
    Serial.println("Token inválido.");
  } else if (code >= 200 && code < 300) {
    Serial.println("Lecturas guardadas en Hydrolab.");
  }

  http.end();
}

void loop() {
  if (millis() - lastSend >= SEND_INTERVAL_MS) {
    lastSend = millis();
    sendReadings();
  }
}
