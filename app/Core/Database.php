<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct(array $config)
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(array $config = []): Database
    {
        if (self::$instance === null) {
            if (empty($config)) {
                throw new \RuntimeException("Database configuration required for first initialization");
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    public static function testConnection(array $config): bool
    {
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password']);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
