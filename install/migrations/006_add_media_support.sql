-- Migration: 006_add_media_support.sql
-- Description: Add support for audio files alongside images (unified media vault)

ALTER TABLE `images`
ADD COLUMN `media_type` ENUM('image', 'audio') NOT NULL DEFAULT 'image' AFTER `mime_type`,
ADD COLUMN `duration` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Duration in seconds for audio files' AFTER `media_type`,
ADD COLUMN `file_size` INT UNSIGNED NULL DEFAULT NULL COMMENT 'File size in bytes' AFTER `duration`;

-- Mark all existing records as images
UPDATE `images` SET media_type = 'image';
