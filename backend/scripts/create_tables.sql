-- Datenbank auswählen oder erstellen, falls noch nicht geschehen
-- USE your_database_name; 
-- Wenn Sie eine neue Datenbank erstellen möchten, entkommentieren Sie die folgende Zeile:
-- CREATE DATABASE IF NOT EXISTS your_database_name;
-- USE your_database_name;

-- Tabelle 'Projects' erstellen
CREATE TABLE IF NOT EXISTS Projects (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,       -- Geändert auf 100 Zeichen
    Description VARCHAR(255),         -- Geändert auf 255 Zeichen
    Passphrase VARCHAR(255)
);

-- Tabelle 'Sensors' erstellen
CREATE TABLE IF NOT EXISTS Sensors (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    IdProject INT NOT NULL,
    Name VARCHAR(100) NOT NULL,       -- Geändert auf 100 Zeichen
    Active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (IdProject) REFERENCES Projects(Id) ON DELETE CASCADE
);

-- Tabelle 'Data' erstellen
CREATE TABLE IF NOT EXISTS Data (
    Id INT AUTO_INCREMENT PRIMARY KEY,
    IdSensor INT NOT NULL,
    ValueDate BIGINT NOT NULL, -- Für Tickcount wird BIGINT verwendet
    Value FLOAT NOT NULL,
    Created BIGINT NOT NULL, -- Automatisch gesetzt mit aktuellem Tickcount
    FOREIGN KEY (IdSensor) REFERENCES Sensors(Id) ON DELETE CASCADE
);

-- Trigger für die Tabelle 'Data', um 'Created' automatisch zu setzen
DELIMITER $$
CREATE TRIGGER `set_data_created_on_insert`
BEFORE INSERT ON `Data`
FOR EACH ROW
BEGIN
    SET NEW.Created = UNIX_TIMESTAMP() * 1000; -- Aktuellen Tickcount (Millisekunden seit Epoch) setzen
END$$
DELIMITER ;