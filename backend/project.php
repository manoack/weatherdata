<?php
// project.php

require_once 'db_config.php';

class Project {
    private $conn;
    private string $table_name = "Projects";

    public $id;
    public $name;
    public $description;
    public $passphrase;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create(): bool
    {
        $query = "INSERT INTO " . $this->table_name . " (Name, Description, Passphrase) VALUES (:name, :description, :passphrase)";
        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->passphrase = htmlspecialchars(strip_tags($this->passphrase));

        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":passphrase", $this->passphrase);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function read_one(): bool
    {
        $query = "SELECT Id, Name, Description FROM " . $this->table_name . " WHERE Id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->name = $row['Name'];
            $this->description = $row['Description'];
            return true;
        }
        return false;
    }

    public function read_all() {
        $query = "SELECT Id, Name, Description FROM " . $this->table_name . " ORDER BY Id DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    public function update(): bool
    {
        $query = "UPDATE " . $this->table_name . " SET Name = :name, Description = :description, Passphrase = :passphrase WHERE Id = :id";
        $stmt = $this->conn->prepare($query);

        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->passphrase = htmlspecialchars(strip_tags($this->passphrase));
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(':name', $this->name);
        $stmt->bindParam(':description', $this->description);
        $stmt->bindParam(':passphrase', $this->passphrase);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete(): bool
    {
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
