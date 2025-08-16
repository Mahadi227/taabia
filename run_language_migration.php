<?php
/**
 * Database Migration Script for Language Support
 * This script adds language preference support to the users table
 */

require_once 'includes/db.php';

echo "Starting language support migration...\n";

try {
    // Check if language_preference column already exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'language_preference'");
    $stmt->execute();
    $column_exists = $stmt->fetch();
    
    if ($column_exists) {
        echo "Language preference column already exists. Skipping migration.\n";
    } else {
        // Add language column to users table
        echo "Adding language_preference column to users table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN language_preference ENUM('fr', 'en') DEFAULT 'fr' AFTER phone");
        
        // Add comment to explain the column
        $pdo->exec("ALTER TABLE users MODIFY COLUMN language_preference ENUM('fr', 'en') DEFAULT 'fr' COMMENT 'User language preference: fr (French) or en (English)'");
        
        // Update existing users to have French as default language
        echo "Setting default language for existing users...\n";
        $pdo->exec("UPDATE users SET language_preference = 'fr' WHERE language_preference IS NULL");
        
        // Add index for better performance when filtering by language
        echo "Creating index for language preference...\n";
        $pdo->exec("CREATE INDEX idx_users_language ON users(language_preference)");
        
        echo "Migration completed successfully!\n";
    }
    
    // Verify the migration
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $user_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE language_preference IS NOT NULL");
    $stmt->execute();
    $users_with_lang = $stmt->fetchColumn();
    
    echo "Verification:\n";
    echo "- Total users: $user_count\n";
    echo "- Users with language preference: $users_with_lang\n";
    
    if ($user_count == $users_with_lang) {
        echo "✅ All users have language preferences set.\n";
    } else {
        echo "⚠️  Some users may not have language preferences set.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nLanguage support migration completed!\n";
echo "The platform now supports French (FR) and English (EN) languages.\n";
echo "Users can change their language preference through the language settings page.\n";
?> 