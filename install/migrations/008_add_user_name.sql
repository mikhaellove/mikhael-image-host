-- Migration: 008_add_user_name.sql
-- Description: Add display name column to users

ALTER TABLE `users`
ADD COLUMN `name` VARCHAR(100) NULL DEFAULT NULL AFTER `username`;
