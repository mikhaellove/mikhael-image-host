-- Project Vault Database Schema

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `must_reset` TINYINT(1) NOT NULL DEFAULT 1,
    `api_token` VARCHAR(64) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_api_token` (`api_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Images table
CREATE TABLE IF NOT EXISTS `images` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `slug` VARCHAR(12) NOT NULL UNIQUE COLLATE utf8mb4_bin,
    `mime_type` VARCHAR(20) NOT NULL DEFAULT 'image/jpeg',
    `media_type` ENUM('image', 'audio', 'video') NOT NULL DEFAULT 'image',
    `duration` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Duration in seconds for audio files',
    `file_size` INT UNSIGNED NULL DEFAULT NULL COMMENT 'File size in bytes',
    `image_data` LONGBLOB NULL DEFAULT NULL,
    `original_image_data` MEDIUMBLOB NULL DEFAULT NULL,
    `thumb_data` MEDIUMBLOB NULL DEFAULT NULL,
    `caption` TEXT NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `link_password` VARCHAR(255) NULL DEFAULT NULL,
    `display_metadata` JSON NULL DEFAULT NULL COMMENT 'Controls which metadata to display on viewer page',
    `view_count` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_slug` (`slug`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_deleted_at` (`deleted_at`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE IF NOT EXISTS `settings` (
    `setting_key` VARCHAR(50) PRIMARY KEY,
    `setting_value` LONGTEXT NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Landing pages table
CREATE TABLE IF NOT EXISTS `landing_pages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `html_content` LONGTEXT NULL DEFAULT NULL,
    `bg_color` VARCHAR(7) NOT NULL DEFAULT '#f5f5f5',
    `text_color` VARCHAR(7) NOT NULL DEFAULT '#333333',
    `logo_slug` VARCHAR(12) NULL DEFAULT NULL,
    `tagline` VARCHAR(255) NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IP blocks table for rate limiting
CREATE TABLE IF NOT EXISTS `ip_blocks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `failed_attempts` INT NOT NULL DEFAULT 0,
    `is_blocked` TINYINT(1) NOT NULL DEFAULT 0,
    `window_start` TIMESTAMP NULL,
    `blocked_at` TIMESTAMP NULL,
    `block_expires_at` TIMESTAMP NULL,
    `last_attempt_at` TIMESTAMP NULL,
    UNIQUE KEY `uq_ip` (`ip_address`),
    KEY `idx_blocked` (`is_blocked`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
