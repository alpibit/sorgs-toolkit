<?php
class Database
{
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct($config = null)
    {
        if ($config) {
            $this->host = $config['DB_HOST'];
            $this->db_name = $config['DB_NAME'];
            $this->username = $config['DB_USER'];
            $this->password = $config['DB_PASS'];
        } else {
            $this->host = DB_HOST;
            $this->db_name = DB_NAME;
            $this->username = DB_USER;
            $this->password = DB_PASS;
        }
    }

    public function connect()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Connection Error: " . $e->getMessage());
        }

        return $this->conn;
    }
}
