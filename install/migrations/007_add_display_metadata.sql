-- Migration: 007_add_display_metadata.sql
-- Description: Add metadata display options for shared media viewer pages

ALTER TABLE `images`
ADD COLUMN `display_metadata` JSON NULL DEFAULT NULL COMMENT 'Controls which metadata to display on viewer page' AFTER `link_password`;

-- Set default for existing records (all metadata hidden by default)
UPDATE `images`
SET display_metadata = JSON_OBJECT(
    'show_caption', false,
    'show_date', false,
    'show_views', false,
    'show_size', false,
    'show_dimensions', false,
    'show_format', false,
    'show_duration', false
)
WHERE display_metadata IS NULL;
