<?php
// Start session first
session_start();

require_once '../../includes/i18n.php';

// Handle language switching
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Language Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .debug {
            background: #f0f0f0;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .test {
            background: #e8f5e8;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .error {
            background: #ffe8e8;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        a {
            display: inline-block;
            margin: 5px;
            padding: 10px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 3px;
        }

        a:hover {
            background: #005a87;
        }
    </style>
</head>

<body>
    <h1>Language Test Page</h1>

    <div class="debug">
        <h2>Debug Information</h2>
        <p><strong>Current Language:</strong> <?= getCurrentLanguage() ?></p>
        <p><strong>Session Language:</strong> <?= $_SESSION['user_language'] ?? 'NOT SET' ?></p>
        <p><strong>Available Languages:</strong> <?= implode(', ', getAvailableLanguages()) ?></p>
        <p><strong>GET Parameter:</strong> <?= $_GET['lang'] ?? 'NONE' ?></p>
    </div>

    <div class="test">
        <h2>Translation Test</h2>
        <p><strong>Welcome:</strong> <?= __('welcome') ?></p>
        <p><strong>Cart:</strong> <?= __('cart') ?></p>
        <p><strong>About:</strong> <?= __('about') ?></p>
        <p><strong>Courses:</strong> <?= __('courses') ?></p>
        <p><strong>Shop:</strong> <?= __('shop') ?></p>
        <p><strong>Contact:</strong> <?= __('contact') ?></p>
    </div>

    <div class="debug">
        <h2>Session Data</h2>
        <pre><?= print_r($_SESSION, true) ?></pre>
    </div>

    <div class="debug">
        <h2>GET Data</h2>
        <pre><?= print_r($_GET, true) ?></pre>
    </div>

    <h2>Language Switcher</h2>
    <a href="?lang=fr">Switch to French</a>
    <a href="?lang=en">Switch to English</a>
    <a href="?">Clear Parameters</a>

    <h2>Navigation</h2>
    <a href="basket.php">Go to Basket</a>
    <a href="about.php">Go to About</a>
    <a href="blog.php">Go to Blog</a>
</body>

</html>



