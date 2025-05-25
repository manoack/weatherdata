<?php
// db_config.php

define('DB_HOST', 'localhost'); // Oder die IP-Adresse Ihres MySQL-Servers
define('DB_NAME', 'your_database_name'); // Ersetzen Sie dies durch den Namen Ihrer Datenbank
define('DB_USER', 'your_db_user');     // Ersetzen Sie dies durch Ihren MySQL-Benutzer
define('DB_PASS', 'your_db_password'); // Ersetzen Sie dies durch Ihr MySQL-Passwort

// Optional: Für Fehlerberichte im Entwicklungsmodus
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Eine einfache Funktion zur Verbindung mit der Datenbank
function get_db_connection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // Im Produktivsystem sollten Sie detaillierte Fehlermeldungen vermeiden
        // und stattdessen in Log-Dateien schreiben.
        http_response_code(500);
        echo json_encode(['message' => 'Database connection failed: ' . $e->getMessage()]);
        exit();
    }
}
?>