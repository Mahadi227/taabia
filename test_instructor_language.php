<?php

/**
 * Test file for instructor language switching functionality
 * This file can be used to test the language switcher without logging in
 */

// Start session
session_start();

// Include the language handler
require_once 'includes/language_handler.php';

// Include i18n system
require_once 'includes/i18n.php';

// Set a test user language in session if not set
if (!isset($_SESSION['user_language'])) {
    $_SESSION['user_language'] = 'fr';
}

?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Language Switcher Test - Instructor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .test-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .test-header h1 {
            color: #2d3748;
            margin-bottom: 10px;
        }

        .test-header p {
            color: #718096;
        }

        .current-language {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .language-switcher-container {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .test-results {
            margin-top: 30px;
            padding: 20px;
            background: #e6fffa;
            border-radius: 8px;
            border-left: 4px solid #38b2ac;
        }

        .test-results h3 {
            color: #234e52;
            margin-top: 0;
        }

        .test-results ul {
            color: #285e61;
        }

        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="test-container">
        <div class="test-header">
            <h1>🌐 Language Switcher Test</h1>
            <p>Testing the professional language switcher for instructor dashboard</p>
        </div>

        <div class="current-language">
            <strong>Current Language:</strong> <?= getCurrentLanguage() === 'fr' ? '🇫🇷 Français' : '🇬🇧 English' ?>
        </div>

        <div class="language-switcher-container">
            <h3>Professional Language Switcher:</h3>
            <?php include 'includes/instructor_language_switcher.php'; ?>
        </div>

        <div class="test-results">
            <h3>✅ Test Results:</h3>
            <ul>
                <li><strong>Session Language:</strong> <?= $_SESSION['user_language'] ?? 'Not set' ?></li>
                <li><strong>Detected Language:</strong> <?= getCurrentLanguage() ?></li>
                <li><strong>Available Languages:</strong> <?= implode(', ', getAvailableLanguages()) ?></li>
                <li><strong>Translation Test:</strong> <?= __('dashboard') ?> - <?= __('instructor_space') ?></li>
                <li><strong>Current URL:</strong> <?= $_SERVER['REQUEST_URI'] ?></li>
            </ul>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #fff5f5; border-radius: 8px; border-left: 4px solid #f56565;">
            <h3 style="color: #742a2a; margin-top: 0;">🧪 Testing Instructions:</h3>
            <ol style="color: #742a2a;">
                <li>Click on the language switcher above</li>
                <li>Select a different language</li>
                <li>Verify that the page reloads with the new language</li>
                <li>Check that the "Current Language" and "Translation Test" sections update</li>
                <li>Test switching back and forth between languages</li>
            </ol>
        </div>

        <a href="instructor/index.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Instructor Dashboard
        </a>
    </div>
</body>

</html>