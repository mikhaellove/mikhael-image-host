ALTER TABLE `images`
MODIFY COLUMN `media_type` ENUM('image', 'audio', 'video') NOT NULL DEFAULT 'image';
