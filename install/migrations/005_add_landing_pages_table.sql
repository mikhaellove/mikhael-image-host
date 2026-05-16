-- Migration: 005_add_landing_pages_table.sql
-- Description: Add landing_pages table for multiple customizable landing pages

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

-- Migrate existing landing page settings to new table
INSERT INTO `landing_pages` (`name`, `html_content`, `bg_color`, `text_color`, `logo_slug`, `tagline`, `is_active`)
SELECT
    'Default' as name,
    (SELECT setting_value FROM settings WHERE setting_key = 'landing_page_html'),
    COALESCE((SELECT setting_value FROM settings WHERE setting_key = 'landing_bg_color'), '#f5f5f5'),
    COALESCE((SELECT setting_value FROM settings WHERE setting_key = 'landing_text_color'), '#333333'),
    (SELECT setting_value FROM settings WHERE setting_key = 'landing_logo_slug'),
    (SELECT setting_value FROM settings WHERE setting_key = 'landing_tagline'),
    1
WHERE EXISTS (SELECT 1 FROM settings WHERE setting_key = 'landing_page_html');

-- Old settings will remain in settings table for backwards compatibility
-- but won't be used anymore
