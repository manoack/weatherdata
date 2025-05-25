<?php
// data.php

require_once 'db_config.php';

class Data {
    private $conn;
    private $table_name = "Data";

    public $id;
    public $id_sensor;
    public $value_date;
    public $value;
    public $created;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Nur das Anlegen neuer Daten ist erlaubt
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (IdSensor, ValueDate, Value) VALUES (:id_sensor, :value_date, :value)";
        $stmt = $this->conn->prepare($query);

        $this->id_sensor = htmlspecialchars(strip_tags($this->id_sensor));
        // ValueDate und Value brauchen keine htmlspecialchars/strip_tags, da sie numerisch sein sollten
        $this->value_date = $this->value_date;
        $this->value = $this->value;

        // Validierung für numerische Werte könnte hier hinzugefügt werden

        $stmt->bindParam(":id_sensor", $this->id_sensor);
        $stmt->bindParam(":value_date", $this->value_date);
        $stmt->bindParam(":value", $this->value);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Keine read_one, read_all, update, delete Methoden, da nur das Anlegen erlaubt ist.
    // Wenn Sie Daten auslesen möchten, sollten Sie dies über separate interne Skripte tun
    // oder einen weiteren, separaten API-Endpunkt dafür erstellen.
}
?>