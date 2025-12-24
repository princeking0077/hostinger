<?php
// Database Helper Class for Installer
class Database {
    private $conn;
    private $error;

    public function connect($host, $user, $pass, $name) {
        try {
            // Accept host:port or plain host
            $port = null;
            if (strpos($host, ':') !== false) {
                list($hostOnly, $port) = explode(':', $host, 2);
                $host = $hostOnly;
            }
            $dsn = "mysql:host=$host;";
            if ($port) { $dsn .= "port=$port;"; }
            $dsn .= "charset=utf8mb4";

            // First connect without DB to check credentials
            $this->conn = new PDO($dsn, $user, $pass);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Check if DB exists, if not try to create (optional, but good for shared hosting if user has permisison)
            // Ideally user gives an existing empty DB.
            // We'll try to select it.
            $this->conn->exec("USE `$name`");
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function getError() {
        return $this->error;
    }

    public function getConnection() {
        return $this->conn;
    }
}
?>
