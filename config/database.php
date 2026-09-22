<?php
class Database {
    // Live Production Database Credentials for InfinityFree
    private $host = "ftpupload.net"; 
    private $db_name = "if0_42980405_db_nazareth"; 
    private $username = "if0_42980405"; 
    private $password = "purpleprada4"; 
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Database Connection Error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
