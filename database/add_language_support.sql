-- Add language support to users table
-- This migration adds a language preference column to the users table

-- Add language column to users table
ALTER TABLE users ADD COLUMN language_preference ENUM('fr', 'en') DEFAULT 'fr' AFTER phone;

-- Add comment to explain the column
ALTER TABLE users MODIFY COLUMN language_preference ENUM('fr', 'en') DEFAULT 'fr' COMMENT 'User language preference: fr (French) or en (English)';

-- Update existing users to have French as default language
UPDATE users SET language_preference = 'fr' WHERE language_preference IS NULL;

-- Add index for better performance when filtering by language
CREATE INDEX idx_users_language ON users(language_preference); 