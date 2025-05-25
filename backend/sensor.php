<?php
// sensor.php

require_once 'db_config.php';

class Sensor {
    private $conn;
    private $table_name = "Sensors";

    public $id;
    public $id_project;
    public $name;
    public $active;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (IdProject, Name, Active) VALUES (:id_project, :name, :active)";
        $stmt = $this->conn->prepare($query);

        $this->id_project = htmlspecialchars(strip_tags($this->id_project));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->active = filter_var($this->active, FILTER_VALIDATE_BOOLEAN); // Stellt sicher, dass es ein boolescher Wert ist

        $stmt->bindParam(":id_project", $this->id_project);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":active", $this->active, PDO::PARAM_BOOL); // PDO::PARAM_BOOL für boolesche Werte

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function read_one() {
        $query = "SELECT Id, IdProject, Name, Active FROM " . $this->table_name . " WHERE Id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->id_project = $row['IdProject'];
            $this->name = $row['Name'];
            $this->active = (bool)$row['Active']; // Konvertiert in booleschen Wert
            return true;
        }
        return false;
    }

    public function read_all() {
        $query = "SELECT Id, IdProject, Name, Active FROM " . $this->table_name . " ORDER BY Id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Liest Sensoren für ein spezifisches Projekt
    public function read_by_project($id_project) {
        $query = "SELECT Id, IdProject, Name, Active FROM " . $this->table_name . " WHERE IdProject = ? ORDER BY Id DESC";
        $stmt = $this->conn->prepare($query);
        $id_project = htmlspecialchars(strip_tags($id_project));
        $stmt->bindParam(1, $id_project);
        $stmt->execute();
        return $stmt;
    }

    public function update() {
        $query = "UPDATE " . $this->table_name . " SET IdProject = :id_project, Name = :name, Active = :active WHERE Id = :id";
        $stmt = $this->conn->prepare($query);

        $this->id_project = htmlspecialchars(strip_tags($this->id_project));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->active = filter_var($this->active, FILTER_VALIDATE_BOOLEAN);
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':id_project', $this->id_project);
        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':active', $this->active, PDO::PARAM_BOOL);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE Id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>