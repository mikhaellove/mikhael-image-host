<?php

namespace App\Models;

use App\Core\Cache;
use App\Core\Database;
use PDO;

class Image
{
    private static $metadataColumnExists = null;

    /**
     * Check if metadata column exists in images table
     * Cached to avoid repeated checks
     */
    private static function hasMetadataColumn(): bool
    {
        if (self::$metadataColumnExists !== null) {
            return self::$metadataColumnExists;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'metadata'");
            self::$metadataColumnExists = $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            self::$metadataColumnExists = false;
            error_log("Error checking metadata column: " . $e->getMessage());
        }

        return self::$metadataColumnExists;
    }

    public static function findBySlug(string $slug, bool $includeDeleted = false): ?array
    {
        $db = Database::getInstance()->getConnection();

        $sql = "SELECT * FROM images WHERE slug = ?";
        if (!$includeDeleted) {
            $sql .= " AND deleted_at IS NULL";
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$slug]);
        $image = $stmt->fetch();

        return $image ?: null;
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM images WHERE id = ?");
        $stmt->execute([$id]);
        $image = $stmt->fetch();

        return $image ?: null;
    }

    public static function create(
        int $userId,
        string $slug,
        string $imageData,
        string $thumbData,
        ?string $caption = null,
        ?array $metadata = null,
        string $mediaType = 'image',
        ?int $duration = null,
        ?int $fileSize = null,
        string $mimeType = 'image/jpeg',
        int $slotCount = 1
    ): int {
        $db = Database::getInstance()->getConnection();

        // Check which columns exist for backwards compatibility
        $hasOriginalColumn = false;
        $hasMediaColumns = false;
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'original_image_data'");
            $hasOriginalColumn = $checkStmt->rowCount() > 0;

            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'media_type'");
            $hasMediaColumns = $checkStmt->rowCount() > 0;
        } catch (\Exception $e) {}

        // Build SQL based on available columns
        $columns = ['user_id', 'slug', 'mime_type', 'image_data'];
        $values = [$userId, $slug, $mimeType, $imageData];
        $placeholders = ['?', '?', '?', '?'];

        if ($hasMediaColumns) {
            $columns[] = 'media_type';
            $columns[] = 'duration';
            $columns[] = 'file_size';
            $values[] = $mediaType;
            $values[] = $duration;
            $values[] = $fileSize;
            $placeholders[] = '?';
            $placeholders[] = '?';
            $placeholders[] = '?';
        }

        // Add slot_count if the column exists (migration 011)
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'slot_count'");
            if ($checkStmt->rowCount() > 0) {
                $columns[] = 'slot_count';
                $values[] = $slotCount;
                $placeholders[] = '?';
            }
        } catch (\Exception $e) {}

        // Only store original_image_data for legacy binary uploads, not gallery JSON
        if ($hasOriginalColumn && strlen($imageData) > 0 && ord($imageData[0]) !== 0x5B) {
            $columns[] = 'original_image_data';
            $values[] = $imageData;
            $placeholders[] = '?';
        }

        $columns[] = 'thumb_data';
        $columns[] = 'caption';
        $values[] = $thumbData;
        $values[] = $caption;
        $placeholders[] = '?';
        $placeholders[] = '?';

        if (self::hasMetadataColumn() && $metadata !== null) {
            $columns[] = 'metadata';
            $values[] = json_encode($metadata);
            $placeholders[] = '?';
        } elseif ($metadata !== null) {
            error_log("Metadata column does not exist. Run migrations. Metadata not saved for slug: {$slug}");
        }

        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            "INSERT INTO images (%s) VALUES (%s)",
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $db->prepare($sql);
        $stmt->execute($values);

        return (int)$db->lastInsertId();
    }

    public static function updateCaption(int $imageId, ?string $caption): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE images SET caption = ? WHERE id = ?");
        return $stmt->execute([$caption, $imageId]);
    }

    public static function updateRotatedImage(int $imageId, string $masterData, string $thumbData, array $metadata): bool
    {
        $db = Database::getInstance()->getConnection();

        // Check if metadata column exists
        if (self::hasMetadataColumn()) {
            $stmt = $db->prepare("
                UPDATE images
                SET image_data = ?, thumb_data = ?, metadata = ?
                WHERE id = ?
            ");
            return $stmt->execute([$masterData, $thumbData, json_encode($metadata), $imageId]);
        } else {
            // Fallback without metadata column
            $stmt = $db->prepare("
                UPDATE images
                SET image_data = ?, thumb_data = ?
                WHERE id = ?
            ");
            return $stmt->execute([$masterData, $thumbData, $imageId]);
        }
    }

    public static function softDelete(int $imageId): bool
    {
        $db = Database::getInstance()->getConnection();

        // Set deleted_at and null out BLOBs to reclaim space
        $stmt = $db->prepare("
            UPDATE images
            SET deleted_at = NOW(),
                image_data = NULL,
                thumb_data = NULL,
                caption = NULL
            WHERE id = ?
        ");

        return $stmt->execute([$imageId]);
    }

    public static function hardDelete(int $imageId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM images WHERE id = ?");
        return $stmt->execute([$imageId]);
    }

    public static function getGalleryForUser(int $userId, int $page = 1, int $perPage = 50): array
    {
        $db = Database::getInstance()->getConnection();
        $offset = ($page - 1) * $perPage;

        // Check if view_count column exists
        $viewCountColumn = '';
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'view_count'");
            if ($checkStmt->rowCount() > 0) {
                $viewCountColumn = ', view_count';
            }
        } catch (\Exception $e) {
            // Column doesn't exist, continue without it
        }

        // Check if media_type column exists
        $mediaColumns = '';
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'media_type'");
            if ($checkStmt->rowCount() > 0) {
                $mediaColumns = ', media_type, duration';
            }
        } catch (\Exception $e) {
            // Column doesn't exist, continue without it
        }

        // Check if slot_count column exists
        $slotCountColumn = '';
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'slot_count'");
            if ($checkStmt->rowCount() > 0) {
                $slotCountColumn = ', slot_count';
            }
        } catch (\Exception $e) {}

        // Only load thumbnail data, not full image data
        $stmt = $db->prepare("
            SELECT id, slug, thumb_data, caption, created_at{$viewCountColumn}{$mediaColumns}{$slotCountColumn}
            FROM images
            WHERE user_id = ? AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$userId, $perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function getGalleryCountForUser(int $userId): int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM images WHERE user_id = ? AND deleted_at IS NULL");
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    public static function getAllGallery(int $page = 1, int $perPage = 50): array
    {
        $db = Database::getInstance()->getConnection();
        $offset = ($page - 1) * $perPage;

        // Check if view_count column exists
        $viewCountColumn = '';
        try {
            $checkStmt = $db->query("SHOW COLUMNS FROM images LIKE 'view_count'");
            if ($checkStmt->rowCount() > 0) {
                $viewCountColumn = ', i.view_count';
            }
        } catch (\Exception $e) {
            // Column doesn't exist, continue without it
        }

        $stmt = $db->prepare("
            SELECT i.id, i.slug, i.thumb_data, i.caption, i.created_at, u.username{$viewCountColumn}
            FROM images i
            JOIN users u ON i.user_id = u.id
            WHERE i.deleted_at IS NULL
            ORDER BY i.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$perPage, $offset]);
        return $stmt->fetchAll();
    }

    public static function getAllGalleryCount(): int
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT COUNT(*) FROM images WHERE deleted_at IS NULL");
        return (int)$stmt->fetchColumn();
    }

    public static function isOwnedByUser(int $imageId, int $userId): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT user_id FROM images WHERE id = ?");
        $stmt->execute([$imageId]);
        $result = $stmt->fetch();

        return $result && (int)$result['user_id'] === $userId;
    }

    public static function slugExists(string $slug): bool
    {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM images WHERE slug = ?");
        $stmt->execute([$slug]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Increment view counter for an image.
     * If $ip is provided, the increment is deduplicated per-IP for 1 hour via Redis.
     * If Redis is unavailable, the increment proceeds without dedup (better to over-count than lose views).
     */
    public static function incrementViewCount(int $imageId, ?string $ip = null): bool
    {
        if ($ip !== null) {
            $dedupResult = Cache::getInstance()->checkAndSet("view:{$imageId}:{$ip}", 3600);
            if ($dedupResult === false) {
                return true; // Duplicate within window — successfully not counted
            }
            // true (first hit) or null (Redis unavailable) → fall through and increment
        }

        $db = Database::getInstance()->getConnection();

        try {
            // Check if view_count column exists
            $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'view_count'");
            if ($stmt->rowCount() === 0) {
                return false; // Column doesn't exist yet
            }

            $stmt = $db->prepare("UPDATE images SET view_count = view_count + 1 WHERE id = ?");
            return $stmt->execute([$imageId]);
        } catch (\Exception $e) {
            error_log("Failed to increment view count: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if image has expired
     */
    public static function isExpired(array $image): bool
    {
        if (empty($image['expires_at'])) {
            return false;
        }

        return strtotime($image['expires_at']) < time();
    }

    /**
     * Verify password for password-protected image
     */
    public static function verifyPassword(array $image, string $password): bool
    {
        if (empty($image['link_password'])) {
            return true; // No password set
        }

        return password_verify($password, $image['link_password']);
    }

    /**
     * Update share settings for an image
     */
    public static function updateShareSettings(int $imageId, ?string $expiresAt, ?string $passwordHash, ?array $displayMetadata = null, ?string $caption = null): bool
    {
        $db = Database::getInstance()->getConnection();

        try {
            // Check if columns exist
            $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'expires_at'");
            if ($stmt->rowCount() === 0) {
                error_log("Share settings columns don't exist. Run migration 002.");
                return false;
            }

            // Check if display_metadata column exists
            $hasDisplayMetadata = false;
            $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'display_metadata'");
            if ($stmt->rowCount() > 0) {
                $hasDisplayMetadata = true;
            }

            if ($hasDisplayMetadata && $displayMetadata !== null) {
                $stmt = $db->prepare("
                    UPDATE images
                    SET expires_at = ?, link_password = ?, display_metadata = ?, caption = ?
                    WHERE id = ?
                ");
                return $stmt->execute([$expiresAt, $passwordHash, json_encode($displayMetadata), $caption, $imageId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE images
                    SET expires_at = ?, link_password = ?, caption = ?
                    WHERE id = ?
                ");
                return $stmt->execute([$expiresAt, $passwordHash, $caption, $imageId]);
            }
        } catch (\Exception $e) {
            error_log("Failed to update share settings: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Revert image to original (non-edited) version
     */
    public static function revertToOriginal(int $imageId): bool
    {
        $db = Database::getInstance()->getConnection();

        try {
            // Check if original_image_data column exists
            $stmt = $db->query("SHOW COLUMNS FROM images LIKE 'original_image_data'");
            if ($stmt->rowCount() === 0) {
                error_log("original_image_data column doesn't exist. Run migration 003.");
                return false;
            }

            // Get current image
            $stmt = $db->prepare("SELECT original_image_data FROM images WHERE id = ?");
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();

            if (!$image || empty($image['original_image_data'])) {
                error_log("No original image data found for image ID: {$imageId}");
                return false;
            }

            // Restore original to image_data and regenerate thumbnail
            $imagick = new \Imagick();
            $imagick->readImageBlob($image['original_image_data']);

            // Regenerate thumbnail
            $thumb = clone $imagick;
            $thumb->thumbnailImage(400, 400, true);
            $thumb->setImageFormat('jpeg');
            $thumb->setImageCompressionQuality(85);
            $thumbData = $thumb->getImageBlob();

            // Update database - restore original to image_data
            $stmt = $db->prepare("
                UPDATE images
                SET image_data = original_image_data, thumb_data = ?
                WHERE id = ?
            ");

            return $stmt->execute([$thumbData, $imageId]);
        } catch (\Exception $e) {
            error_log("Failed to revert to original: " . $e->getMessage());
            return false;
        }
    }

    public static function isImage(array $item): bool
    {
        return ($item['media_type'] ?? 'image') === 'image';
    }

    public static function isAudio(array $item): bool
    {
        return ($item['media_type'] ?? 'image') === 'audio';
    }

    public static function isVideo(array $item): bool
    {
        return ($item['media_type'] ?? 'image') === 'video';
    }

    // --- Gallery slot helpers ---

    public static function decodeSlots(string $blob): ?array
    {
        if ($blob === '' || ord($blob[0]) !== 0x5B) {
            return null; // Legacy binary format (JPEG starts with 0xFF, not '[')
        }
        $slots = json_decode($blob, true);
        return is_array($slots) ? $slots : null;
    }

    public static function encodeSlots(array $slots): string
    {
        return json_encode($slots, JSON_UNESCAPED_SLASHES);
    }

    public static function getThumbFromSlots(array $slots): string
    {
        return base64_decode($slots[0]['thumb'] ?? '');
    }

    public static function updateSlots(int $imageId, array $slots, ?string $newThumbData = null): bool
    {
        $db = Database::getInstance()->getConnection();
        $imageData = self::encodeSlots($slots);
        $slotCount = count($slots);

        $hasSlotCount = false;
        try {
            $chk = $db->query("SHOW COLUMNS FROM images LIKE 'slot_count'");
            $hasSlotCount = $chk->rowCount() > 0;
        } catch (\Exception $e) {}

        if ($newThumbData !== null) {
            if ($hasSlotCount) {
                $stmt = $db->prepare("UPDATE images SET image_data = ?, thumb_data = ?, slot_count = ? WHERE id = ?");
                return $stmt->execute([$imageData, $newThumbData, $slotCount, $imageId]);
            }
            $stmt = $db->prepare("UPDATE images SET image_data = ?, thumb_data = ? WHERE id = ?");
            return $stmt->execute([$imageData, $newThumbData, $imageId]);
        }

        if ($hasSlotCount) {
            $stmt = $db->prepare("UPDATE images SET image_data = ?, slot_count = ? WHERE id = ?");
            return $stmt->execute([$imageData, $slotCount, $imageId]);
        }
        $stmt = $db->prepare("UPDATE images SET image_data = ? WHERE id = ?");
        return $stmt->execute([$imageData, $imageId]);
    }
}
