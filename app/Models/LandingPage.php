<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class LandingPage
{
    /**
     * Get the active landing page
     */
    public static function getActive(): ?array
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->query("SELECT * FROM landing_pages WHERE is_active = 1 LIMIT 1");
        $page = $stmt->fetch();

        return $page ?: null;
    }

    /**
     * Get all landing pages
     */
    public static function getAll(): array
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->query("SELECT * FROM landing_pages ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    /**
     * Get landing page by ID
     */
    public static function findById(int $id): ?array
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("SELECT * FROM landing_pages WHERE id = ?");
        $stmt->execute([$id]);
        $page = $stmt->fetch();

        return $page ?: null;
    }

    /**
     * Create new landing page
     */
    public static function create(string $name, array $settings): int
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            INSERT INTO landing_pages (name, html_content, bg_color, text_color, logo_slug, tagline, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            $name,
            $settings['html_content'] ?? '',
            $settings['bg_color'] ?? '#f5f5f5',
            $settings['text_color'] ?? '#333333',
            $settings['logo_slug'] ?? null,
            $settings['tagline'] ?? null
        ]);

        return (int)$db->lastInsertId();
    }

    /**
     * Update landing page
     */
    public static function update(int $id, string $name, array $settings): bool
    {
        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            UPDATE landing_pages
            SET name = ?, html_content = ?, bg_color = ?, text_color = ?, logo_slug = ?, tagline = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $name,
            $settings['html_content'] ?? '',
            $settings['bg_color'] ?? '#f5f5f5',
            $settings['text_color'] ?? '#333333',
            $settings['logo_slug'] ?? null,
            $settings['tagline'] ?? null,
            $id
        ]);
    }

    /**
     * Delete landing page
     */
    public static function delete(int $id): bool
    {
        $db = Database::getInstance()->getConnection();

        // Don't allow deleting the active page
        $page = self::findById($id);
        if ($page && $page['is_active']) {
            return false;
        }

        $stmt = $db->prepare("DELETE FROM landing_pages WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Set a landing page as active (deactivates all others)
     */
    public static function setActive(int $id): bool
    {
        $db = Database::getInstance()->getConnection();

        try {
            $db->beginTransaction();

            // Deactivate all pages
            $db->exec("UPDATE landing_pages SET is_active = 0");

            // Activate the specified page
            $stmt = $db->prepare("UPDATE landing_pages SET is_active = 1 WHERE id = ?");
            $stmt->execute([$id]);

            $db->commit();
            return true;

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Failed to set active landing page: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if landing_pages table exists
     */
    public static function tableExists(): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SHOW TABLES LIKE 'landing_pages'");
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
