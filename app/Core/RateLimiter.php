<?php

namespace App\Core;

use PDO;
use PDOException;

class RateLimiter
{
    const MAX_FAILURES = 10;
    const WINDOW_SECONDS = 300;       // 5-minute sliding window
    const BLOCK_DURATION_SECONDS = 86400; // 24-hour auto-block

    public static function getClientIp(): ?string
    {
        // Try HTTP_CLIENT_IP first
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // Try HTTP_X_FORWARDED_FOR (take first entry)
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        // Fall back to REMOTE_ADDR
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        return null;
    }

    public static function isBlocked(string $ip): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->getConnection()->prepare(
                'SELECT is_blocked, block_expires_at FROM ip_blocks
                 WHERE ip_address = ? LIMIT 1'
            );
            $stmt->execute([$ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return false;
            }

            // Not currently blocked
            if (!$row['is_blocked']) {
                return false;
            }

            // Has an expiration time and it's in the past
            if ($row['block_expires_at'] && strtotime($row['block_expires_at']) <= time()) {
                // Lazy cleanup: reset the block
                $updateStmt = $db->getConnection()->prepare(
                    'UPDATE ip_blocks SET is_blocked = 0, blocked_at = NULL, block_expires_at = NULL
                     WHERE ip_address = ?'
                );
                $updateStmt->execute([$ip]);
                return false;
            }

            // Blocked and either permanent or not expired yet
            return true;
        } catch (PDOException $e) {
            // Table doesn't exist yet, fail silently and allow request
            return false;
        }
    }

    public static function recordFailedAttempt(string $ip): void
    {
        try {
            $db = Database::getInstance();
            $now = date('Y-m-d H:i:s');
            $windowStart = date('Y-m-d H:i:s', time() - self::WINDOW_SECONDS);

            $stmt = $db->getConnection()->prepare(
                'SELECT failed_attempts, window_start FROM ip_blocks WHERE ip_address = ? LIMIT 1'
            );
            $stmt->execute([$ip]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                // New record
                $insertStmt = $db->getConnection()->prepare(
                    'INSERT INTO ip_blocks (ip_address, failed_attempts, window_start, last_attempt_at)
                     VALUES (?, 1, ?, ?)'
                );
                $insertStmt->execute([$ip, $now, $now]);
            } else {
                // Check if window has expired
                if (strtotime($row['window_start']) < time() - self::WINDOW_SECONDS) {
                    // Window expired, reset counter
                    $resetStmt = $db->getConnection()->prepare(
                        'UPDATE ip_blocks
                         SET failed_attempts = 1, window_start = ?, is_blocked = 0,
                             blocked_at = NULL, block_expires_at = NULL, last_attempt_at = ?
                         WHERE ip_address = ?'
                    );
                    $resetStmt->execute([$now, $now, $ip]);
                } else {
                    // Window still active, increment counter
                    $newAttempts = $row['failed_attempts'] + 1;
                    $blockUntil = null;
                    $isBlocked = 0;

                    if ($newAttempts >= self::MAX_FAILURES) {
                        $isBlocked = 1;
                        $blockUntil = date('Y-m-d H:i:s', time() + self::BLOCK_DURATION_SECONDS);
                    }

                    $updateStmt = $db->getConnection()->prepare(
                        'UPDATE ip_blocks
                         SET failed_attempts = ?, is_blocked = ?, blocked_at = ?, block_expires_at = ?, last_attempt_at = ?
                         WHERE ip_address = ?'
                    );
                    $updateStmt->execute([
                        $newAttempts,
                        $isBlocked,
                        $isBlocked ? $now : null,
                        $blockUntil,
                        $now,
                        $ip
                    ]);
                }
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet, silently fail
        }
    }

    public static function unblockIp(string $ip): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->getConnection()->prepare(
                'UPDATE ip_blocks
                 SET is_blocked = 0, blocked_at = NULL, block_expires_at = NULL, failed_attempts = 0
                 WHERE ip_address = ?'
            );
            $stmt->execute([$ip]);
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }
    }

    public static function manualBlockIp(string $ip): void
    {
        try {
            $db = Database::getInstance();
            $now = date('Y-m-d H:i:s');

            $stmt = $db->getConnection()->prepare(
                'SELECT id FROM ip_blocks WHERE ip_address = ? LIMIT 1'
            );
            $stmt->execute([$ip]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($exists) {
                $updateStmt = $db->getConnection()->prepare(
                    'UPDATE ip_blocks SET is_blocked = 1, blocked_at = ?, block_expires_at = NULL
                     WHERE ip_address = ?'
                );
                $updateStmt->execute([$now, $ip]);
            } else {
                $insertStmt = $db->getConnection()->prepare(
                    'INSERT INTO ip_blocks (ip_address, is_blocked, blocked_at, last_attempt_at)
                     VALUES (?, 1, ?, ?)'
                );
                $insertStmt->execute([$ip, $now, $now]);
            }
        } catch (PDOException $e) {
            // Table doesn't exist yet
        }
    }

    public static function getAll(): array
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->getConnection()->prepare(
                'SELECT * FROM ip_blocks ORDER BY last_attempt_at DESC'
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Table doesn't exist yet
            return [];
        }
    }
}
