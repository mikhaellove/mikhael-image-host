-- Add metadata JSON column to images table
-- This stores all EXIF, IPTC, XMP, and PNG chunk data extracted before image processing

ALTER TABLE `images`
ADD COLUMN `metadata` JSON NULL DEFAULT NULL AFTER `caption`;
