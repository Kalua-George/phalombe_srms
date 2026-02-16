<?php
// config/database.php

class Database {
    private $host = "localhost";       // Database host
    private $db_name = "school_data";  // Database name
    private $username = "root";        // DB username
    private $password = "";            // DB password
    public $conn;

    // Get the PDO database connection
    public function getConnection() {
        $this->conn = null;

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on errors
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Fetch associative arrays
                PDO::ATTR_EMULATE_PREPARES => false, // Use native prepared statements
            ];
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
        } catch(PDOException $exception) {
            die("Database connection error: " . $exception->getMessage());
        }

        return $this->conn;
    }
}
