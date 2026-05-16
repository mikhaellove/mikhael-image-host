<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    private static ?bool $hasNameColumn = null;

    /**
     * Check if the `name` column exists on the users table.
     * Cached for the lifetime of the request.
     */
    private static function hasNameColumn(): bool
    {
        if (self::$hasNameColumn !== null) {
            return self::$hasNameColumn;
        }
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'name'");
            self::$hasNameColumn = $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            self::$hasNameColumn = false;
        }
        return self::$hasNameColumn;
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    public static function create(string $username, string $password, string $role = 'user', bool $mustReset = true, ?string $name = null): int
    {
        $db = Database::getInstance()->getConnection();
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);

        if (self::hasNameColumn()) {
            $stmt = $db->prepare("
                INSERT INTO users (username, name, password_hash, role, must_reset, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $name !== null && $name !== '' ? $name : null, $passwordHash, $role, $mustReset ? 1 : 0]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO users (username, password_hash, role, must_reset, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$username, $passwordHash, $role, $mustReset ? 1 : 0]);
        }

        return (int)$db->lastInsertId();
    }

    /**
     * Update editable user fields. Accepts any subset of: name, username, role, password.
     * If password is non-empty it's re-hashed and must_reset is cleared.
     */
    public static function update(int $userId, array $data): bool
    {
        $db = Database::getInstance()->getConnection();

        $sets = [];
        $values = [];

        if (array_key_exists('name', $data) && self::hasNameColumn()) {
            $sets[] = 'name = ?';
            $values[] = $data['name'] !== '' ? $data['name'] : null;
        }
        if (array_key_exists('username', $data)) {
            $sets[] = 'username = ?';
            $values[] = $data['username'];
        }
        if (array_key_exists('role', $data)) {
            $sets[] = 'role = ?';
            $values[] = $data['role'];
        }
        if (!empty($data['password'])) {
            $sets[] = 'password_hash = ?';
            $values[] = password_hash($data['password'], PASSWORD_ARGON2ID);
            $sets[] = 'must_reset = 0';
        }

        if (empty($sets)) {
            return true;
        }

        $values[] = $userId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        return $stmt->execute($values);
    }

    public static function updatePassword(int $userId, string $newPassword, bool $clearMustReset = true): bool
    {
        $db = Database::getInstance()->getConnection();
        $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);

        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = ?, must_reset = ?
            WHERE id = ?
        ");

        return $stmt->execute([$passwordHash, $clearMustReset ? 0 : 1, $userId]);
    }

    public static function delete(int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }

    public static function getAllUsers(): array
    {
        $db = Database::getInstance()->getConnection();
        $nameCol = self::hasNameColumn() ? ', name' : '';
        $stmt = $db->query("SELECT id, username{$nameCol}, role, must_reset, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public static function getUserStorageStats(int $userId): array
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT
                COUNT(*) as image_count,
                SUM(LENGTH(image_data)) as total_size,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_count
            FROM images
            WHERE user_id = ?
        ");

        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    public static function getAllStorageStats(): array
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->query("
            SELECT
                u.id,
                u.username,
                u.role,
                COUNT(i.id) as image_count,
                SUM(LENGTH(i.image_data)) as total_size,
                SUM(CASE WHEN i.deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_count
            FROM users u
            LEFT JOIN images i ON u.id = i.user_id
            GROUP BY u.id, u.username, u.role
            ORDER BY total_size DESC
        ");

        return $stmt->fetchAll();
    }

    public static function createApiToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("UPDATE users SET api_token = ? WHERE id = ?");
        $stmt->execute([$token, $userId]);

        return $token;
    }

    public static function validateApiToken(string $token): ?int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT id FROM users WHERE api_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        return $user ? (int)$user['id'] : null;
    }

    public static function revokeApiToken(int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE users SET api_token = NULL WHERE id = ?");
        return $stmt->execute([$userId]);
    }
}
