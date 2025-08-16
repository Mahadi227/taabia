# Multi-Language Implementation Guide

## Overview

The TaaBia platform now supports multiple languages with a comprehensive internationalization (i18n) system. The system supports French (FR) and English (EN) languages with the ability to easily add more languages in the future.

## Features

- **Automatic Language Detection**: Detects user's preferred language from browser settings
- **User Language Preference**: Users can set and save their language preference
- **Session-based Language**: Language preference persists across sessions
- **Database Storage**: User language preferences are stored in the database
- **Fallback System**: Falls back to English if a translation is missing
- **Language Switcher**: Easy-to-use language switcher component
- **URL-based Language Switching**: Can switch languages via URL parameters

## File Structure

```
taabia/
├── includes/
│   ├── i18n.php              # Main internationalization system
│   └── language_switcher.php # Reusable language switcher component
├── lang/
│   ├── fr.php                # French translations
│   └── en.php                # English translations
├── student/
│   └── language_settings.php # Language settings page
├── database/
│   └── add_language_support.sql # Database migration
└── run_language_migration.php   # Migration script
```

## Installation

### 1. Run Database Migration

Execute the migration script to add language support to the database:

```bash
php run_language_migration.php
```

This will:
- Add a `language_preference` column to the `users` table
- Set default language to French for existing users
- Create an index for better performance

### 2. Include i18n System

Add the i18n system to your pages by including it in your session file or at the top of your pages:

```php
require_once 'includes/i18n.php';
```

## Usage

### Basic Translation

Use the `__()` function to translate text:

```php
echo __('dashboard'); // Outputs: "Tableau de bord" (FR) or "Dashboard" (EN)
```

### Translation with Parameters

You can include parameters in translations:

```php
echo __('welcome_user', ['name' => 'John']); // "Bienvenue John" or "Welcome John"
```

### Language Detection and Setting

```php
// Get current language
$current_lang = getCurrentLanguage(); // Returns 'fr' or 'en'

// Set language
setLanguage('en'); // Switches to English

// Get available languages
$languages = getAvailableLanguages(); // Returns ['fr', 'en']
```

### Language Switcher Component

Include the language switcher in any page:

```php
include 'includes/language_switcher.php';
```

## Translation Files

### Adding New Translations

To add a new translation key, edit the language files:

**French (`lang/fr.php`):**
```php
return [
    'new_key' => 'Nouvelle traduction',
    // ... other translations
];
```

**English (`lang/en.php`):**
```php
return [
    'new_key' => 'New translation',
    // ... other translations
];
```

### Translation Categories

Translations are organized into categories:

- **Common**: Basic UI elements (dashboard, profile, save, cancel, etc.)
- **Navigation**: Menu items and navigation elements
- **Profile**: User profile related text
- **Statistics**: Dashboard statistics and metrics
- **Activity**: Recent activity and notifications
- **Courses**: Course-related terminology
- **Products**: E-commerce product terms
- **Orders**: Order management terms
- **Messages**: Messaging system terms
- **Events**: Event management terms
- **Blog**: Blog and content terms
- **Contact**: Contact form and communication terms
- **About**: About page and company information
- **Language**: Language-related terms
- **Time and dates**: Date and time formatting
- **Status**: Status indicators (active, pending, etc.)
- **Actions**: Action buttons and commands
- **Notifications**: Notification system terms
- **Errors**: Error messages
- **Success messages**: Success notifications
- **Form validation**: Form validation messages
- **Currency**: Currency and payment terms
- **Empty states**: Empty state messages
- **Welcome messages**: Greeting messages

## Language Settings Page

Users can change their language preference through the dedicated language settings page:

- **URL**: `/student/language_settings.php`
- **Features**: 
  - Visual language selection with flags
  - Auto-save on selection
  - Success/error feedback
  - Responsive design

## Browser Language Detection

The system automatically detects the user's preferred language from:

1. **User Session**: Previously saved language preference
2. **URL Parameter**: `?lang=en` or `?lang=fr`
3. **Browser Language**: HTTP Accept-Language header
4. **Default**: French (fr)

## Database Schema

### Users Table Update

The `users` table now includes a `language_preference` column:

```sql
ALTER TABLE users ADD COLUMN language_preference ENUM('fr', 'en') DEFAULT 'fr';
```

### Index for Performance

```sql
CREATE INDEX idx_users_language ON users(language_preference);
```

## Adding New Languages

To add a new language (e.g., Spanish):

1. **Create translation file**: `lang/es.php`
2. **Update i18n system**: Add 'es' to `$available_languages` array
3. **Update database**: Modify the ENUM to include 'es'
4. **Add language name**: Update `getLanguageName()` method

### Example for Spanish

**`lang/es.php`:**
```php
<?php
return [
    'dashboard' => 'Panel de control',
    'profile' => 'Perfil',
    // ... add all translations
];
```

**Update `includes/i18n.php`:**
```php
private $available_languages = ['fr', 'en', 'es'];

public function getLanguageName($code = null) {
    $code = $code ?: $this->current_language;
    $names = [
        'fr' => 'Français',
        'en' => 'English',
        'es' => 'Español'
    ];
    return $names[$code] ?? $code;
}
```

## Best Practices

### 1. Use Translation Keys Consistently

Always use descriptive, lowercase keys with underscores:

```php
// Good
echo __('user_profile_updated');

// Avoid
echo __('userProfileUpdated');
```

### 2. Group Related Translations

Keep related translations together in the language files:

```php
// Profile section
'profile' => 'Profil',
'edit_profile' => 'Modifier le profil',
'change_password' => 'Changer le mot de passe',
```

### 3. Use Parameters for Dynamic Content

For content that includes variables:

```php
// In translation file
'welcome_user' => 'Bienvenue {name}',

// In code
echo __('welcome_user', ['name' => $user_name]);
```

### 4. Test Both Languages

Always test your pages in both languages to ensure:
- All text is translated
- Layout works with different text lengths
- No hardcoded text remains

## Troubleshooting

### Common Issues

1. **Translation not showing**: Check if the key exists in both language files
2. **Language not switching**: Verify the session is working and database is updated
3. **Missing translations**: Add the key to both `fr.php` and `en.php`

### Debug Mode

To debug translation issues, you can temporarily modify the `t()` method in `i18n.php`:

```php
public function t($key, $params = []) {
    $translation = $this->translations[$key] ?? $key;
    
    // Debug: Show missing translations
    if (!isset($this->translations[$key])) {
        error_log("Missing translation for key: $key");
    }
    
    // ... rest of the method
}
```

## Performance Considerations

- Translation files are loaded once per session
- Database queries for language preference are cached
- Language switcher uses efficient DOM manipulation
- Index on `language_preference` column improves query performance

## Security

- All user input is sanitized before database storage
- Language preferences are validated against allowed values
- SQL injection protection through prepared statements
- XSS protection through proper output escaping

## Future Enhancements

Potential improvements for the multi-language system:

1. **Content Translation**: Translate course content, blog posts, etc.
2. **Date/Time Localization**: Format dates according to locale
3. **Number Formatting**: Format numbers according to locale
4. **RTL Support**: Add support for right-to-left languages
5. **Translation Management**: Admin interface for managing translations
6. **Auto-translation**: Integration with translation APIs
7. **Language-specific SEO**: Meta tags and URLs in different languages

## Support

For issues or questions about the multi-language implementation:

1. Check this documentation
2. Review the translation files for missing keys
3. Verify database migration was successful
4. Test with different browsers and language settings 