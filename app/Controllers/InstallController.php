<?php

namespace App\Controllers;

use App\Core\Database;
use App\Models\User;
use PDO;

class InstallController
{
    public function showInstaller(): void
    {
        // Only accessible if config doesn't exist
        if (file_exists(__DIR__ . '/../../config/config.php')) {
            http_response_code(403);
            echo "Installation already completed. Delete config/config.php to reinstall.";
            exit;
        }

        include __DIR__ . '/../../templates/install/wizard.php';
    }

    public function testConnection(): void
    {
        header('Content-Type: application/json');

        $host = $_POST['db_host'] ?? '';
        $database = $_POST['db_name'] ?? '';
        $username = $_POST['db_user'] ?? '';
        $password = $_POST['db_pass'] ?? '';

        if (empty($host) || empty($database) || empty($username)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        $config = [
            'host' => $host,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];

        if (Database::testConnection($config)) {
            echo json_encode(['success' => true, 'message' => 'Database connection successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        }
    }

    public function install(): void
    {
        header('Content-Type: application/json');

        // Validate input
        $host = $_POST['db_host'] ?? '';
        $database = $_POST['db_name'] ?? '';
        $username = $_POST['db_user'] ?? '';
        $password = $_POST['db_pass'] ?? '';
        $adminUsername = $_POST['admin_username'] ?? '';
        $adminPassword = $_POST['admin_password'] ?? '';

        if (empty($host) || empty($database) || empty($username) || empty($adminUsername) || empty($adminPassword)) {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            return;
        }

        try {
            // Initialize database
            $config = [
                'host' => $host,
                'database' => $database,
                'username' => $username,
                'password' => $password,
            ];

            Database::getInstance($config);
            $db = Database::getInstance()->getConnection();

            // Run schema creation
            $this->createSchema($db);

            // Mark all existing migrations as applied (since schema.sql includes them)
            $this->markMigrationsAsApplied($db);

            // Create admin user
            User::create($adminUsername, $adminPassword, 'admin', false);

            // Create default settings
            $this->createDefaultSettings($db);

            // Write config file
            $this->writeConfig($config);

            echo json_encode(['success' => true, 'message' => 'Installation completed successfully']);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Installation failed: ' . $e->getMessage()]);
        }
    }

    private function createSchema(PDO $db): void
    {
        $schema = file_get_contents(__DIR__ . '/../../install/schema.sql');
        $db->exec($schema);
    }

    private function markMigrationsAsApplied(PDO $db): void
    {
        // Get all migration files
        $migrationsDir = __DIR__ . '/../../install/migrations';
        $files = glob($migrationsDir . '/*.sql');

        if (empty($files)) {
            return;
        }

        // Mark each migration as already applied since schema.sql includes all changes
        $stmt = $db->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
        foreach ($files as $file) {
            $migrationName = basename($file);
            try {
                $stmt->execute([$migrationName]);
            } catch (\PDOException $e) {
                // Ignore duplicate entry errors (migration already recorded)
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
            }
        }
    }

    private function createDefaultSettings(PDO $db): void
    {
        $defaultLandingPage = '<h1>Welcome</h1><p>This is a private image vault.</p>';

        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['landing_page_html', $defaultLandingPage]);
    }

    private function writeConfig(array $config): void
    {
        $configContent = "<?php\n\nreturn [\n";
        $configContent .= "    'database' => [\n";
        $configContent .= "        'host' => " . var_export($config['host'], true) . ",\n";
        $configContent .= "        'database' => " . var_export($config['database'], true) . ",\n";
        $configContent .= "        'username' => " . var_export($config['username'], true) . ",\n";
        $configContent .= "        'password' => " . var_export($config['password'], true) . ",\n";
        $configContent .= "    ],\n";
        $configContent .= "];\n";

        $configDir = __DIR__ . '/../../config';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0700, true);
        }

        file_put_contents($configDir . '/config.php', $configContent);
        chmod($configDir . '/config.php', 0600);
    }

    public function preFlightCheck(): void
    {
        header('Content-Type: application/json');

        $checks = [
            'pdo' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
            'fileinfo' => extension_loaded('fileinfo'),
            'imagemagick' => \App\Services\ImageProcessor::isMagickAvailable(),
            'config_writable' => is_writable(__DIR__ . '/../../') || is_writable(__DIR__ . '/../../config'),
            'memory_limit' => $this->checkMemoryLimit(),
            'post_max_size' => $this->checkPostMaxSize(),
            'upload_max_filesize' => $this->checkUploadMaxFilesize(),
        ];

        $allPassed = !in_array(false, $checks, true);

        echo json_encode([
            'success' => $allPassed,
            'checks' => $checks,
        ]);
    }

    private function checkMemoryLimit(): bool
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') return true;

        $bytes = $this->convertToBytes($limit);
        return $bytes >= 256 * 1024 * 1024; // 256MB minimum
    }

    private function checkPostMaxSize(): bool
    {
        $limit = ini_get('post_max_size');
        $bytes = $this->convertToBytes($limit);
        return $bytes >= 50 * 1024 * 1024; // 50MB minimum
    }

    private function checkUploadMaxFilesize(): bool
    {
        $limit = ini_get('upload_max_filesize');
        $bytes = $this->convertToBytes($limit);
        return $bytes >= 50 * 1024 * 1024; // 50MB minimum
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
