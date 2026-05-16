-- Migration: 004_allow_null_image_data.sql
-- Description: Allow image_data and thumb_data to be NULL for soft-deleted images

ALTER TABLE `images`
MODIFY COLUMN `image_data` LONGBLOB NULL DEFAULT NULL,
MODIFY COLUMN `thumb_data` MEDIUMBLOB NULL DEFAULT NULL;
