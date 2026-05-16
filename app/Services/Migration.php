<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class Migration
{
    private const MIGRATIONS_DIR = __DIR__ . '/../../install/migrations';

    /**
     * Ensure the migrations tracking table exists
     */
    private static function ensureMigrationsTable(): void
    {
        $db = Database::getInstance()->getConnection();

        $db->exec("
            CREATE TABLE IF NOT EXISTS `migrations` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `migration_name` VARCHAR(255) NOT NULL UNIQUE,
                `applied_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_migration_name` (`migration_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Get list of pending migrations
     */
    public static function getPending(): array
    {
        self::ensureMigrationsTable();

        $db = Database::getInstance()->getConnection();

        // Get all applied migrations
        $stmt = $db->query("SELECT migration_name FROM migrations");
        $applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Get all migration files
        $files = glob(self::MIGRATIONS_DIR . '/*.sql');
        $pending = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $applied)) {
                $pending[] = $name;
            }
        }

        sort($pending); // Ensure they run in order
        return $pending;
    }

    /**
     * Get list of applied migrations
     */
    public static function getApplied(): array
    {
        self::ensureMigrationsTable();

        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT migration_name, applied_at FROM migrations ORDER BY applied_at ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Run all pending migrations
     */
    public static function runPending(): array
    {
        $pending = self::getPending();
        $results = [];

        foreach ($pending as $migration) {
            try {
                self::runMigration($migration);
                $results[] = [
                    'migration' => $migration,
                    'success' => true,
                    'error' => null
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'migration' => $migration,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                // Stop on first error
                break;
            }
        }

        return $results;
    }

    /**
     * Run a specific migration
     */
    private static function runMigration(string $migrationName): void
    {
        $db = Database::getInstance()->getConnection();
        $filePath = self::MIGRATIONS_DIR . '/' . $migrationName;

        if (!file_exists($filePath)) {
            throw new \RuntimeException("Migration file not found: {$migrationName}");
        }

        $sql = file_get_contents($filePath);

        if (empty($sql)) {
            throw new \RuntimeException("Migration file is empty: {$migrationName}");
        }

        try {
            // Execute the migration SQL
            // Note: DDL statements (ALTER TABLE, CREATE INDEX, etc.) auto-commit in MySQL/MariaDB
            // so we don't use transactions here
            $db->exec($sql);

            // Record the migration
            $stmt = $db->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $stmt->execute([$migrationName]);

        } catch (\Exception $e) {
            throw new \RuntimeException("Migration failed: {$e->getMessage()}");
        }
    }

    /**
     * Check if there are pending migrations
     */
    public static function hasPending(): bool
    {
        return count(self::getPending()) > 0;
    }
}
