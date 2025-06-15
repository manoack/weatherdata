<?php
// data.php

require_once 'db_config.php';

class Data {
    public $id;

    private $conn;
    private $table_name = "Data";

    public $id_sensor;
    public $value_date;

    public $value;

    public $passphrase;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Nur das Anlegen neuer Daten ist erlaubt
    public function create() {
        $query =
            "INSERT INTO " . $this->table_name . " (IdSensor, ValueDate, Value) " .
            "(Select :id_sensor IdSensor , :value_date ValueDate, :value Value " .
            "FROM Projects p JOIN Sensors s ON s.IdProject = p.Id ".
            "WHERE s.Id = :id_sensor AND p.Passphrase = :passphrase) ".
            "RETURNING Id;";
        $stmt = $this->conn->prepare($query);

        // $this->id_sensor = $this->id_sensor;
        // $this->value_date = $this->value_date;
        $this->value = htmlspecialchars($this->value);
        $this->passphrase = htmlspecialchars($this->passphrase);

        // Validierung für numerische Werte könnte hier hinzugefügt werden

        $stmt->bindParam(":id_sensor", $this->id_sensor, PDO::PARAM_INT);
        $stmt->bindParam(":value_date", $this->value_date, PDO::PARAM_INT);
        $stmt->bindParam(":value", $this->value);
        $stmt->bindParam(":passphrase", $this->passphrase, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $row = $stmt->fetch();
            if ($row) {
                $this->id = $row["Id"];
                return true;
            }
        }
        return false;
    }

    // Keine read_one, read_all, update, delete Methoden, da nur das Anlegen erlaubt ist.
    // Wenn Sie Daten auslesen möchten, sollten Sie dies über separate interne Skripte tun
    // oder einen weiteren, separaten API-Endpunkt dafür erstellen.
}
?>