<?php
require_once 'includes/i18n.php';

// Handle language switching via URL
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    setLanguage($_GET['lang']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('language') ?> Test | TaaBia</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .lang-btn { padding: 10px 20px; margin: 5px; text-decoration: none; border: 1px solid #007bff; color: #007bff; border-radius: 5px; }
        .lang-btn.active { background: #007bff; color: white; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .key { font-weight: bold; color: #007bff; }
        .value { color: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?= __('language') ?> Test</h1>
        
        <div>
            <a href="?lang=fr" class="lang-btn <?= getCurrentLanguage() === 'fr' ? 'active' : '' ?>">🇫🇷 <?= __('french') ?></a>
            <a href="?lang=en" class="lang-btn <?= getCurrentLanguage() === 'en' ? 'active' : '' ?>">🇬🇧 <?= __('english') ?></a>
        </div>

        <div class="section">
            <h3><?= __('common') ?></h3>
            <p><span class="key">dashboard:</span> <span class="value"><?= __('dashboard') ?></span></p>
            <p><span class="key">profile:</span> <span class="value"><?= __('profile') ?></span></p>
            <p><span class="key">settings:</span> <span class="value"><?= __('settings') ?></span></p>
            <p><span class="key">logout:</span> <span class="value"><?= __('logout') ?></span></p>
        </div>

        <div class="section">
            <h3><?= __('courses') ?></h3>
            <p><span class="key">courses:</span> <span class="value"><?= __('courses') ?></span></p>
            <p><span class="key">my_courses:</span> <span class="value"><?= __('my_courses') ?></span></p>
            <p><span class="key">discover_courses:</span> <span class="value"><?= __('discover_courses') ?></span></p>
        </div>

        <div class="section">
            <h3><?= __('profile') ?></h3>
            <p><span class="key">my_profile_title:</span> <span class="value"><?= __('my_profile_title') ?></span></p>
            <p><span class="key">edit_profile:</span> <span class="value"><?= __('edit_profile') ?></span></p>
            <p><span class="key">change_password:</span> <span class="value"><?= __('change_password') ?></span></p>
        </div>

        <p><strong><?= __('language') ?>:</strong> <?= getCurrentLanguage() === 'fr' ? __('french') : __('english') ?></p>
    </div>
</body>
</html> 