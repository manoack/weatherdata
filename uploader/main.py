import machine
import onewire
import ds18x20
import time
import uos
import sys
import network
import urequests # Import für HTTP-Anfragen
import json      # Import für JSON-Serialisierung

# --- Funktion zum Laden der Geheimnisse aus secrets.json ---
def load_secrets(filename="secrets.json"):
    try:
        with open(filename, 'r') as f:
            secrets = json.load(f)
        print(f"Geheimnisse aus '{filename}' geladen.")
        return secrets
    except OSError:
        print(f"Fehler: Datei '{filename}' nicht gefunden. Bitte erstellen Sie diese mit Ihren Zugangsdaten.")
        sys.exit()
    except ValueError:
        print(f"Fehler: '{filename}' ist keine gültige JSON-Datei.")
        sys.exit()
    except Exception as e:
        print(f"Ein unerwarteter Fehler beim Laden von '{filename}' ist aufgetreten: {e}")
        sys.exit()

# Lade die Geheimnisse
secrets = load_secrets()

# --- WLAN Konfiguration (aus secrets.json) ---
# Stellen Sie sicher, dass diese Schlüssel in Ihrer secrets.json vorhanden sind.
WLAN_SSID = secrets.get("WLAN_SSID")
WLAN_PASSWORD = secrets.get("WLAN_PASSWORD")

# --- API Konfiguration (aus secrets.json) ---
# Stellen Sie sicher, dass diese Schlüssel in Ihrer secrets.json vorhanden sind.
API_BASE_URL = secrets.get("API_BASE_URL")
PROJECT_PASSPHRASE = secrets.get("PROJECT_PASSPHRASE", "") # Standardmäßig leer, falls nicht in secrets.json
# Das SENSOR_ID_MAPPING wird ebenfalls aus secrets.json geladen.
# Beachten Sie, dass JSON-Keys Strings sind. Wir müssen die Keys ggf. zu int konvertieren.
SENSOR_ID_MAPPING = {int(k): v for k, v in secrets.get("SENSOR_ID_MAPPING", {}).items()}


# --- Konfiguration der Sensoren ---
SENSOR_PINS = [3, 4, 7]           # GPIO-Pins, an die die Sensoren angeschlossen sind (z.B. GP0, GP1, GP2)
MESSINTERVALL_SEKUNDEN = 5        # X Sekunden: Intervall zum Nehmen der einzelnen Messwerte
SPEICHERINTERVALL_SEKUNDEN = 60   # Y Sekunden: Intervall zum Speichern des Mittelwerts und zur Ausgabe
DATEINAME = "temperatur_log.csv"

# --- Funktion zur WLAN-Verbindung ---
def connect_to_wlan(ssid, password):
    if not ssid or not password:
        print("Fehler: WLAN-SSID oder -Passwort in secrets.json fehlen.")
        return None

    wlan = network.WLAN(network.STA_IF)
    wlan.active(True)
    
    if wlan.isconnected():
        print("Bereits mit WLAN verbunden. IP-Adresse:", wlan.ifconfig()[0])
        return wlan

    print(f"Versuche Verbindung mit WLAN-Netzwerk '{ssid}'...")
    wlan.connect(ssid, password)
    max_attempts = 30
    attempts = 0
    while not wlan.isconnected() and attempts < max_attempts:
        status = wlan.status()
        if status == network.STAT_NO_AP_FOUND:
            print("Kein AP gefunden. Überprüfe SSID.")
        elif status == network.STAT_WRONG_PASSWORD:
            print("Falsches Passwort.")
        elif status == network.STAT_CONNECTING:
            print("Verbinde...", end="")
        else:
            print(f"Status: {status}...", end="")
        time.sleep(2)
        attempts += 1
    
    if wlan.isconnected():
        print("\nErfolgreich mit WLAN verbunden!")
        print("IP-Adresse:", wlan.ifconfig()[0])
        try:
            import ntptime
            ntptime.host = 'pool.ntp.org'
            ntptime.settime()
            print("Uhrzeit mit NTP synchronisiert.")
        except Exception as e:
            print(f"Fehler bei NTP-Synchronisierung: {e}")
    else:
        print("\nVerbindung zum WLAN fehlgeschlagen nach", attempts, "Versuchen.")
        final_status = wlan.status()
        if final_status == network.STAT_NO_AP_FOUND:
            print("Fehler: Kein WLAN-Netzwerk mit dieser SSID gefunden.")
        elif final_status == network.STAT_WRONG_PASSWORD:
            print("Fehler: Falsches WLAN-Passwort.")
        elif final_status == network.STAT_TIMEOUT:
            print("Fehler: Verbindungstimeout.")
        elif final_status == network.STAT_BAD_AUTH:
            print("Fehler: Schlechte Authentifizierung.")
        else:
            print(f"Unbekannter Fehlerstatus: {final_status}")
    return wlan

# --- WLAN verbinden ---
wlan_connection = connect_to_wlan(WLAN_SSID, WLAN_PASSWORD)

# --- Initialisierung der Sensoren ---
sensoren = []
for pin_num in SENSOR_PINS:
    try:
        ow = onewire.OneWire(machine.Pin(pin_num))
        ds = ds18x20.DS18X20(ow)
        roms = ds.scan()
        if roms:
            sensoren.append({"ds_obj": ds, "rom": roms[0], "pin": pin_num, "messwerte": []})
            print(f"Sensor an GP{pin_num} gefunden. ROM: {roms[0].hex()}")
        else:
            print(f"Kein DS18B20-Sensor an GP{pin_num} gefunden.")
    except Exception as e:
        print(f"Fehler bei Initialisierung von GP{pin_num}: {e}")

if not sensoren:
    print("Keine Sensoren gefunden. Bitte überprüfe die Verkabelung und die Pins.")
    sys.exit()

# --- CSV-Header schreiben (falls Datei neu oder leer ist) ---
try:
    with open(DATEINAME, 'r') as f:
        if not f.readline().strip().startswith("Timestamp"):
            raise Exception("Header missing")
except (OSError, Exception):
    with open(DATEINAME, 'w') as f:
        header = "Timestamp"
        for i, sensor_info in enumerate(sensoren):
            header += f",Sensor_{i + 1}_GP{sensor_info['pin']}_AvgTemp_C"
        f.write(header + "\n")
    print(f"Neue CSV-Datei '{DATEINAME}' erstellt und Header geschrieben.")

# --- Hauptschleife ---
letzter_messzeitpunkt = time.time()
letzter_speicherzeitpunkt = time.time()

print(f"Starte Temperaturmessung. Messintervall: {MESSINTERVALL_SEKUNDEN}s, Speicherintervall: {SPEICHERINTERVALL_SEKUNDEN}s.")

while True:
    aktueller_zeitpunkt = time.time()

    # Messwerte nehmen (alle X Sekunden)
    if aktueller_zeitpunkt - letzter_messzeitpunkt >= MESSINTERVALL_SEKUNDEN:
        for sensor_info in sensoren:
            ds = sensor_info["ds_obj"]
            rom = sensor_info["rom"]
            ds.convert_temp()
            time.sleep_ms(750)
            try:
                temp = ds.read_temp(rom)
                if temp is not None and -55 <= temp <= 125:
                    sensor_info["messwerte"].append(temp)
                    print(f"Sensor an GP{sensor_info['pin']}: {temp:.2f} °C")
                else:
                    print(f"Fehlerhafter Messwert von Sensor an GP{sensor_info['pin']}: {temp}")
            except Exception as e:
                print(f"Fehler beim Lesen von Sensor an GP{sensor_info['pin']}: {e}")
        letzter_messzeitpunkt = aktueller_zeitpunkt

    # Mittelwerte berechnen, speichern und ausgeben (alle Y Sekunden)
    if aktueller_zeitpunkt - letzter_speicherzeitpunkt >= SPEICHERINTERVALL_SEKUNDEN:
        try:
            with open(DATEINAME, 'a') as f:
                timestamp = time.localtime()
                # Timestamp für CSV-Datei
                timestamp_str = f"{timestamp[0]:04d}-{timestamp[1]:02d}-{timestamp[2]:02d} {timestamp[3]:02d}:{timestamp[4]:02d}:{timestamp[5]:02d}"
                csv_line = timestamp_str

                # Timestamp für API-Request (Millisekunden seit Unix-Epoche)
                # Nur synchronisierte Zeit verwenden, wenn WLAN verbunden ist
                api_value_date = int(time.time() * 1000) if wlan_connection and wlan_connection.isconnected() else 0 # 0 oder Fehlerwert, wenn keine synchrone Zeit

                for sensor_info in sensoren:
                    sensor_pin = sensor_info['pin']
                    # SENSOR_ID_MAPPING hat jetzt Integer-Schlüssel, nachdem sie aus JSON konvertiert wurden
                    sensor_api_id = SENSOR_ID_MAPPING.get(sensor_pin)

                    if sensor_info["messwerte"]:
                        mittelwert = sum(sensor_info["messwerte"]) / len(sensor_info["messwerte"])
                        print(f"Sensor an GP{sensor_pin} - Mittelwert ({len(sensor_info['messwerte'])} Messungen): {mittelwert:.2f} °C")
                        csv_line += f",{mittelwert:.2f}"
                        
                        # --- Daten an API senden ---
                        if wlan_connection and wlan_connection.isconnected() and sensor_api_id is not None:
                            if not API_BASE_URL:
                                print("API-Fehler: API_BASE_URL in secrets.json fehlt.")
                                continue # Überspringe diesen Sensor und gehe zum nächsten

                            try:
                                payload = {
                                    "id_sensor": sensor_api_id,
                                    "value_date": api_value_date,
                                    "value": float(f"{mittelwert:.2f}"),
                                    "passphrase": PROJECT_PASSPHRASE
                                }
                                headers = {'Content-Type': 'application/json'}
                                response = urequests.post(API_BASE_URL, headers=headers, data=json.dumps(payload))
                                
                                if response.status_code == 201:
                                    print(f"API: Daten für Sensor GP{sensor_pin} erfolgreich gesendet. Antwort: {response.json()}")
                                else:
                                    print(f"API-Fehler für Sensor GP{sensor_pin}: Status {response.status_code}, Antwort: {response.text}")
                                response.close()
                            except Exception as api_e:
                                print(f"Fehler beim Senden der API-Daten für Sensor GP{sensor_pin}: {api_e}")
                        elif sensor_api_id is None:
                            print(f"API: Keine Sensor-ID für GP{sensor_pin} im Mapping gefunden. Daten nicht gesendet.")
                        else:
                            print(f"API: Keine WLAN-Verbindung aktiv. Daten für Sensor GP{sensor_pin} nicht gesendet.")
                        # --- Ende API-Daten senden ---

                        sensor_info["messwerte"] = [] # Messwerte zurücksetzen
                    else:
                        print(f"Sensor an GP{sensor_pin}: Keine neuen Messwerte seit letzter Speicherung.")
                        csv_line += "," # Leerer Eintrag in CSV, wenn keine Messwerte
                f.write(csv_line + "\n")
            print(f"Daten in '{DATEINAME}' gespeichert.")
        except Exception as e:
            print(f"Fehler beim Schreiben der CSV-Datei: {e}")
        letzter_speicherzeitpunkt = aktueller_zeitpunkt

    time.sleep(1) # Kurze Pause, um CPU-Auslastung zu reduzieren und andere Aufgaben zu ermöglichen
