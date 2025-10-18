<?php
// Test file to verify language switching functionality
session_start();

// Include the language system
require_once 'includes/i18n.php';
require_once 'includes/language_handler.php';

// Get current language
$current_lang = getCurrentLanguage();

echo "<h1>Language Switching Test</h1>";
echo "<p>Current Language: " . $current_lang . "</p>";
echo "<p>Session Language: " . ($_SESSION['user_language'] ?? 'Not set') . "</p>";

// Test translation
echo "<h2>Test Translations</h2>";
echo "<p>Dashboard (EN): " . __('dashboard') . "</p>";
echo "<p>Dashboard (FR): " . __('dashboard') . "</p>";

// Language switcher links
echo "<h2>Language Switcher</h2>";
echo "<a href='?lang=en'>Switch to English</a> | ";
echo "<a href='?lang=fr'>Switch to French</a>";
echo "<br><br>";
echo "<a href='test_language_switching.php'>Refresh Page</a>";

// Debug information
echo "<h2>Debug Information</h2>";
echo "<pre>";
echo "Session Data:\n";
print_r($_SESSION);
echo "\nGET Data:\n";
print_r($_GET);
echo "</pre>";

