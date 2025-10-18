-- Update site settings table with additional settings
-- This script adds new settings to the existing system_settings table

-- Insert additional system settings if they don't exist
INSERT IGNORE INTO system_settings (setting_key, setting_value, description) VALUES
('contact_email', '', 'Contact email address'),
('contact_phone', '', 'Contact phone number'),
('address', '', 'Platform physical address'),
('maintenance_mode', '0', 'Maintenance mode status (0=disabled, 1=enabled)'),
('registration_enabled', '1', 'User registration enabled (0=disabled, 1=enabled)'),
('email_notifications', '1', 'Email notifications enabled (0=disabled, 1=enabled)'),
('backup_frequency', 'daily', 'Backup frequency (daily, weekly, monthly, disabled)');

-- Update existing settings with better descriptions
UPDATE system_settings SET description = 'Platform name displayed throughout the site' WHERE setting_key = 'platform_name';
UPDATE system_settings SET description = 'Platform description for SEO and about pages' WHERE setting_key = 'platform_description';
UPDATE system_settings SET description = 'Default currency for the platform' WHERE setting_key = 'currency';
UPDATE system_settings SET description = 'Platform commission rate in percentage' WHERE setting_key = 'commission_rate';
UPDATE system_settings SET description = 'Minimum payout amount in platform currency' WHERE setting_key = 'min_payout_amount';
UPDATE system_settings SET description = 'Maximum file upload size in bytes' WHERE setting_key = 'max_file_size';

-- Ensure commission_settings table exists with proper structure
CREATE TABLE IF NOT EXISTS commission_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default commission settings if they don't exist
INSERT IGNORE INTO commission_settings (setting_key, setting_value, description) VALUES
('instructor_commission_rate', '20.00', 'Commission rate for instructors in percentage'),
('vendor_commission_rate', '15.00', 'Commission rate for vendors in percentage'),
('default_commission_rate', '20.00', 'Default commission rate for the platform');

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_system_settings_key ON system_settings(setting_key);
CREATE INDEX IF NOT EXISTS idx_commission_settings_key ON commission_settings(setting_key);
