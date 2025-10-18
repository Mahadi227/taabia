<?php
// Handle language switching first
require_once '../../includes/language_handler.php';
require_once '../../includes/i18n.php';
session_start();
?>
<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Language Test - TaaBia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
        }

        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
        }

        .current-lang {
            background: #e7f3ff;
            padding: 10px;
            margin: 10px 0;
        }

        .session-info {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h1>Language Switching Test</h1>

    <div class="current-lang">
        <strong>Current Language:</strong> <?= getCurrentLanguage() ?>
    </div>

    <div class="session-info">
        <strong>Session Language:</strong> <?= $_SESSION['user_language'] ?? 'Not set' ?>
    </div>

    <div class="test-section">
        <h2>Test Translations</h2>
        <p><strong>Welcome:</strong> <?= __('welcome') ?></p>
        <p><strong>Courses:</strong> <?= __('courses') ?></p>
        <p><strong>Shop:</strong> <?= __('shop') ?></p>
        <p><strong>About:</strong> <?= __('about') ?></p>
        <p><strong>Contact:</strong> <?= __('contact') ?></p>
        <p><strong>Search Courses:</strong> <?= __('search_courses') ?></p>
        <p><strong>Category:</strong> <?= __('category') ?></p>
        <p><strong>Level:</strong> <?= __('level') ?></p>
        <p><strong>Free:</strong> <?= __('free') ?></p>
        <p><strong>Shop:</strong> <?= __('shop') ?></p>
        <p><strong>In Stock:</strong> <?= __('in_stock') ?></p>
        <p><strong>Out of Stock:</strong> <?= __('out_of_stock') ?></p>
        <p><strong>Add to Cart:</strong> <?= __('add_to_cart') ?></p>
    </div>

    <div class="test-section">
        <h2>Language Switcher</h2>
        <?php include '../../includes/public_language_switcher.php'; ?>
    </div>

    <div class="test-section">
        <h2>Manual Links</h2>
        <p><a href="?lang=fr">Switch to French</a></p>
        <p><a href="?lang=en">Switch to English</a></p>
        <p><a href="test_language.php">Clear Language (Reload Page)</a></p>
        <p><a href="courses.php">Test Courses Page</a></p>
        <p><a href="shop.php">Test Shop Page</a></p>
    </div>

    <div class="test-section">
        <h2>Debug Information</h2>
        <p><strong>Available Languages:</strong> <?= implode(', ', getAvailableLanguages()) ?></p>
        <p><strong>Current URL:</strong> <?= $_SERVER['REQUEST_URI'] ?></p>
        <p><strong>GET Parameters:</strong> <?= print_r($_GET, true) ?></p>
        <p><strong>Session Data:</strong> <?= print_r($_SESSION, true) ?></p>
    </div>
</body>

</html>