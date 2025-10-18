-- ================================================
-- Add User Preferences Columns to Users Table
-- ================================================
-- This script adds new columns for advanced user preferences
-- Run this if you're upgrading from an older version
-- ================================================

USE taabia_skills;

-- Add timezone column
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `timezone` VARCHAR(50) DEFAULT 'Africa/Casablanca' AFTER `language_preference`;

-- Add date_format column
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `date_format` VARCHAR(20) DEFAULT 'd/m/Y' AFTER `timezone`;

-- Add time_format column
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `time_format` VARCHAR(10) DEFAULT '24h' AFTER `date_format`;

-- Add theme_preference column
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `theme_preference` VARCHAR(10) DEFAULT 'light' AFTER `time_format`;

-- Add font_size column
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `font_size` VARCHAR(10) DEFAULT 'medium' AFTER `theme_preference`;

-- ================================================
-- Verification Query
-- ================================================
-- Run this to verify the columns were added successfully:

SELECT
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_DEFAULT,
    IS_NULLABLE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE
    TABLE_SCHEMA = 'taabia_skills'
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME IN (
        'timezone',
        'date_format',
        'time_format',
        'theme_preference',
        'font_size'
    );

-- ================================================
-- Sample Update Query (Optional)
-- ================================================
-- If you want to update existing users with default values:

-- UPDATE users
-- SET
--     timezone = 'Africa/Casablanca',
--     date_format = 'd/m/Y',
--     time_format = '24h',
--     theme_preference = 'light',
--     font_size = 'medium'
-- WHERE timezone IS NULL OR date_format IS NULL;











