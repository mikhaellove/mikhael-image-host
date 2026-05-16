-- Add sharing features and view counter to images table
-- Enables expiring links, password protection, and view analytics

ALTER TABLE `images`
ADD COLUMN `expires_at` TIMESTAMP NULL DEFAULT NULL AFTER `metadata`,
ADD COLUMN `link_password` VARCHAR(255) NULL DEFAULT NULL AFTER `expires_at`,
ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `link_password`,
ADD INDEX `idx_expires_at` (`expires_at`);
