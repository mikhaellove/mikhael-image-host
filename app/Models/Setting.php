<?php

namespace App\Models;

use App\Core\Database;

class Setting
{
    public static function get(string $key, mixed $default = null): mixed
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row !== false ? $row['setting_value'] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            INSERT INTO settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?
        ");
        $stmt->execute([$key, $value, $value]);
    }
}
