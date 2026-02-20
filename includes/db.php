<?php
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("اتصال به دیتابیس: " . $this->connection->connect_error);
            }
            
            $this->connection->set_charset("utf8mb4");
            $this->connection->query("SET NAMES utf8mb4");
            $this->connection->query("SET CHARACTER SET utf8mb4");
            
        } catch (Exception $e) {
            die("خطا در اتصال به دیتابیس: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function insertId() {
        return $this->connection->insert_id;
    }
    
    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }
    
    public function error() {
        return $this->connection->error;
    }
}

// تابع کمکی برای دسترسی آسان به دیتابیس
function db() {
    return Database::getInstance()->getConnection();
}
?>