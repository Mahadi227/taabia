# Site Settings Implementation

## Overview
The comprehensive site settings system has been implemented to provide administrators with full control over platform configuration, visual elements, business parameters, and technical settings.

## Structure

### 1. Database Functions (`includes/function.php`)

#### Core Settings Functions:
- `get_setting($key, $default)` - Retrieve a system setting value
- `update_setting($key, $value, $description)` - Update or create a system setting
- `get_all_settings()` - Get all system settings as an array
- `get_commission_settings()` - Get commission-specific settings
- `update_commission_settings($instructor_rate, $vendor_rate)` - Update commission rates
- `upload_site_image($file, $type, $max_size)` - Handle image uploads for site assets
- `get_site_image($type)` - Get current site image path

### 2. Settings Interface (`admin/site_settings.php`)

#### Tabbed Interface with 4 Main Sections:

##### 🏢 General Settings
- Platform name and description
- Currency selection (GHS, USD, EUR, XOF)
- Contact information (email, phone, address)

##### 🎨 Visual Settings
- Logo upload and management
- Featured courses banner management
- Image preview functionality
- Support for JPG, PNG, WEBP formats

##### 💼 Business Settings
- Commission rates for instructors and vendors
- Minimum payout amounts
- File upload size limits
- Real-time commission statistics display

##### ⚙️ Technical Settings
- Maintenance mode toggle
- User registration control
- Email notifications settings
- Backup frequency configuration
- System information display

### 3. Database Schema

#### `system_settings` Table:
```sql
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### `commission_settings` Table:
```sql
CREATE TABLE commission_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value VARCHAR(255) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 4. Default Settings

#### System Settings:
- `platform_name`: 'TaaBia Skills & Market'
- `platform_description`: 'Integrated learning and e-commerce platform'
- `currency`: 'GHS'
- `commission_rate`: '10'
- `min_payout_amount`: '50'
- `max_file_size`: '10485760'
- `contact_email`: ''
- `contact_phone`: ''
- `address`: ''
- `maintenance_mode`: '0'
- `registration_enabled`: '1'
- `email_notifications`: '1'
- `backup_frequency`: 'daily'

#### Commission Settings:
- `instructor_commission_rate`: '20.00'
- `vendor_commission_rate`: '15.00'
- `default_commission_rate`: '20.00'

## Features

### ✅ Implemented Features:
1. **Comprehensive Settings Management** - All platform settings in one interface
2. **Image Management** - Logo and banner upload with preview
3. **Commission Control** - Real-time commission rate management
4. **Technical Controls** - Maintenance mode, registration control
5. **Responsive Design** - Modern tabbed interface
6. **Data Validation** - Input validation and error handling
7. **Database Integration** - Proper transaction handling
8. **Security** - File upload validation and sanitization

### 🎯 Key Benefits:
- **Centralized Control** - All settings in one place
- **Real-time Updates** - Changes take effect immediately
- **User-friendly Interface** - Intuitive tabbed design
- **Data Integrity** - Proper validation and error handling
- **Extensible** - Easy to add new settings
- **Secure** - Proper file upload and input validation

## Usage

### Accessing Settings:
1. Navigate to `admin/site_settings.php`
2. Use the tabbed interface to access different setting categories
3. Make changes and click "Enregistrer" (Save) buttons
4. Changes are applied immediately

### Running Migration:
```bash
php setup_site_settings.php
```

### File Structure:
```
admin/
├── site_settings.php          # Main settings interface
includes/
├── function.php               # Settings management functions
database/
├── update_site_settings.sql   # Database migration
setup_site_settings.php        # Migration runner
docs/
├── site-settings-implementation.md  # This documentation
```

## Security Considerations

1. **File Upload Security**:
   - MIME type validation
   - File size limits
   - Secure file naming
   - Proper permissions

2. **Input Validation**:
   - All inputs are sanitized
   - Type checking for numeric values
   - Range validation for percentages

3. **Database Security**:
   - Prepared statements
   - Transaction handling
   - Error handling

## Future Enhancements

Potential future additions:
- Theme/color scheme management
- Email template customization
- Advanced backup settings
- API configuration
- Social media integration
- Multi-language settings
- Advanced security settings
