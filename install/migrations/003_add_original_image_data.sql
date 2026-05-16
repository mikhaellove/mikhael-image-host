-- Migration: 003_add_original_image_data.sql
-- Description: Add column to store original unedited image data for non-destructive editing

ALTER TABLE `images`
ADD COLUMN `original_image_data` MEDIUMBLOB NULL DEFAULT NULL AFTER `image_data`;
