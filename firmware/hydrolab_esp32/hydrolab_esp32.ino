#include <WiFi.h>
#include <HTTPClient.h>
#include <OneWire.h>
#include <DallasTemperature.h>

// =====================================================
// WIFI
// =====================================================

const char* ssid = "TU_WIFI";
const char* password = "TU_PASSWORD";

// =====================================================
// API HYDROLAB
// =====================================================
// El ESP32 NO puede usar 127.0.0.1 (eso es el PC).
// Usa la IP LAN de tu PC. Laragon (puerto 80) ya sirve Hydrolab:
//   http://TU_IP
// Si usas artisan, añade el puerto:
//   http://TU_IP:8000

const char* API_BASE = "http://TU_IP";
const char* DEVICE_TOKEN = "hydrolab-esp32-dev-token-change-me";

// =====================================================
// BOMBA / RELÉ
// =====================================================

const int rele = 23;

// HIGH = bomba encendida
// LOW  = bomba apagada

bool bombaEncendida = true;
unsigned long tiempoBomba = 0;

const unsigned long TIEMPO_BOMBA_ON  = 10000;
const unsigned long TIEMPO_BOMBA_OFF = 7000;

// =====================================================
// SENSOR DS18B20
// =====================================================

#define ONE_WIRE_BUS 15

OneWire oneWire(ONE_WIRE_BUS);
DallasTemperature sensors(&oneWire);

float lastTemperature = NAN;

const unsigned long SEND_INTERVAL_MS = 15000;
unsigned long lastSend = 0;

void testBridge();
void controlarBomba();
bool readTemperatureSensor();
String buildReadingsJson();
void sendReadings();

void setup() {
  Serial.begin(115200);

  pinMode(rele, OUTPUT);
  digitalWrite(rele, HIGH);
  bombaEncendida = true;
  tiempoBomba = millis();

  sensors.begin();

  WiFi.begin(ssid, password);
  Serial.print("Conectando al WiFi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("=================================");
  Serial.println("       HYDROLAB ESP32");
  Serial.println("=================================");
  Serial.println("¡WiFi conectado!");
  Serial.print("IP del ESP32: ");
  Serial.println(WiFi.localIP());
  Serial.print("API Hydrolab: ");
  Serial.println(API_BASE);
  Serial.printf("DS18B20 en GPIO %d | dispositivos: %d\n", ONE_WIRE_BUS, sensors.getDeviceCount());
  Serial.println("Bomba: ENCENDIDA");

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
  http.addHeader("Accept", "application/json");
  int pingCode = http.GET();
  Serial.printf("PING %d: %s\n", pingCode, http.getString().c_str());
  http.end();
}

void controlarBomba() {
  unsigned long ahora = millis();

  if (bombaEncendida) {
    if (ahora - tiempoBomba >= TIEMPO_BOMBA_ON) {
      digitalWrite(rele, LOW);
      bombaEncendida = false;
      tiempoBomba = ahora;
      Serial.println("Bomba: APAGADA");
    }
  } else {
    if (ahora - tiempoBomba >= TIEMPO_BOMBA_OFF) {
      digitalWrite(rele, HIGH);
      bombaEncendida = true;
      tiempoBomba = ahora;
      Serial.println("Bomba: ENCENDIDA");
    }
  }
}

bool readTemperatureSensor() {
  sensors.requestTemperatures();
  float temperature = sensors.getTempCByIndex(0);

  if (temperature == DEVICE_DISCONNECTED_C || temperature < -55 || temperature > 125) {
    Serial.println("Error leyendo DS18B20.");
    Serial.printf("Revisa GPIO %d y la resistencia 4.7k.\n", ONE_WIRE_BUS);
    return false;
  }

  lastTemperature = temperature;
  Serial.printf("Temperatura agua: %.2f °C\n", lastTemperature);
  return true;
}

String buildReadingsJson() {
  String body = "{";
  body += "\"pump_on\":";
  body += bombaEncendida ? "true" : "false";
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
    Serial.println("Sin respuesta.");
    Serial.println("En el PC ejecuta: php artisan serve --host=0.0.0.0 --port=8000");
    Serial.println("Revisa IP, puerto y firewall de Windows.");
  } else if (code == 401) {
    Serial.println("Token inválido.");
  } else if (code >= 200 && code < 300) {
    Serial.println("Lecturas guardadas en Hydrolab.");
  }

  http.end();
}

void loop() {
  unsigned long ahora = millis();
  controlarBomba();

  if (ahora - lastSend >= SEND_INTERVAL_MS) {
    lastSend = ahora;
    sendReadings();
  }
}
