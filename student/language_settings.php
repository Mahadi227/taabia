<?php

/**
 * Advanced Language & Regional Settings
 * Enhanced version with timezone, date format, theme, and accessibility options
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $updates = [];
        $params = [];

        // Language preference
        if (isset($_POST['language']) && in_array($_POST['language'], ['fr', 'en', 'ar', 'es'])) {
            $updates[] = "language_preference = ?";
            $params[] = $_POST['language'];
            setLanguage($_POST['language']);
        }

        // Timezone
        if (isset($_POST['timezone'])) {
            $updates[] = "timezone = ?";
            $params[] = $_POST['timezone'];
        }

        // Date format
        if (isset($_POST['date_format'])) {
            $updates[] = "date_format = ?";
            $params[] = $_POST['date_format'];
        }

        // Time format
        if (isset($_POST['time_format'])) {
            $updates[] = "time_format = ?";
            $params[] = $_POST['time_format'];
        }

        // Theme preference
        if (isset($_POST['theme'])) {
            $updates[] = "theme_preference = ?";
            $params[] = $_POST['theme'];
        }

        // Font size
        if (isset($_POST['font_size'])) {
            $updates[] = "font_size = ?";
            $params[] = $_POST['font_size'];
        }

        if (!empty($updates)) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
            $params[] = $student_id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $message = __('settings_saved_successfully') ?? 'Paramètres enregistrés avec succès';
            $message_type = 'success';

            // Refresh page to apply changes
            header("Refresh: 1");
        }
    } catch (PDOException $e) {
        $message = __('error_occurred') ?? 'Une erreur est survenue';
        $message_type = 'error';
        error_log("Failed to update user settings: " . $e->getMessage());
    }
}

// Get current user settings with defaults
try {
    $stmt = $pdo->prepare("
        SELECT 
            language_preference, 
            timezone,
            date_format,
            time_format,
            theme_preference,
            font_size
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set defaults if not set
    $current_language = $settings['language_preference'] ?? 'fr';
    $current_timezone = $settings['timezone'] ?? 'Africa/Casablanca';
    $current_date_format = $settings['date_format'] ?? 'd/m/Y';
    $current_time_format = $settings['time_format'] ?? '24h';
    $current_theme = $settings['theme_preference'] ?? 'light';
    $current_font_size = $settings['font_size'] ?? 'medium';
} catch (PDOException $e) {
    // If columns don't exist, use defaults
    $current_language = 'fr';
    $current_timezone = 'Africa/Casablanca';
    $current_date_format = 'd/m/Y';
    $current_time_format = '24h';
    $current_theme = 'light';
    $current_font_size = 'medium';
}

// Available languages
$languages = [
    'fr' => ['name' => 'Français', 'native' => 'Français', 'flag' => '🇫🇷'],
    'en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇬🇧'],
    'ar' => ['name' => 'Arabic', 'native' => 'العربية', 'flag' => '🇸🇦'],
    'es' => ['name' => 'Spanish', 'native' => 'Español', 'flag' => '🇪🇸'],
];

// Popular timezones
$timezones = [
    'Africa/Casablanca' => 'Casablanca (GMT+1)',
    'Africa/Cairo' => 'Cairo (GMT+2)',
    'Europe/Paris' => 'Paris (GMT+1)',
    'Europe/London' => 'London (GMT+0)',
    'America/New_York' => 'New York (GMT-5)',
    'America/Los_Angeles' => 'Los Angeles (GMT-8)',
    'Asia/Dubai' => 'Dubai (GMT+4)',
    'Asia/Tokyo' => 'Tokyo (GMT+9)',
];

// Date formats
$date_formats = [
    'd/m/Y' => '31/12/2025',
    'm/d/Y' => '12/31/2025',
    'Y-m-d' => '2025-12-31',
    'd-m-Y' => '31-12-2025',
    'F d, Y' => 'December 31, 2025',
    'd F Y' => '31 December 2025',
];

?>

<!DOCTYPE html>
<html lang="<?= $current_language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('language_and_regional_settings') ?? 'Paramètres de Langue et Région' ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #004075;
            --primary-dark: #004085;
            --secondary: #004082;
            --success: #48bb78;
            --danger: #f56565;
            --warning: #ed8936;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --white: #ffffff;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --radius: 12px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
            font-size: <?= $current_font_size === 'small' ? '14px' : ($current_font_size === 'large' ? '18px' : '16px') ?>;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }

        .header h1 {
            color: var(--gray-900);
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header h1 i {
            color: var(--primary);
        }

        .header p {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 600;
            transition: var(--transition);
        }

        .back-link:hover {
            color: var(--primary-dark);
            transform: translateX(-5px);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: var(--success);
            color: white;
        }

        .alert-error {
            background: var(--danger);
            color: white;
        }

        .settings-grid {
            display: grid;
            gap: 2rem;
        }

        .settings-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .settings-card h2 {
            color: var(--gray-900);
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .settings-card h2 i {
            color: var(--primary);
        }

        .settings-card-description {
            color: var(--gray-600);
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            display: block;
            color: var(--gray-700);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
        }

        .form-description {
            color: var(--gray-500);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        /* Language Grid */
        .language-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .language-option {
            position: relative;
            cursor: pointer;
        }

        .language-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .language-option-card {
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: var(--transition);
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }

        .language-option:hover .language-option-card {
            border-color: var(--primary);
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .language-option input[type="radio"]:checked+.language-option-card {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
        }

        .language-flag {
            font-size: 3rem;
        }

        .language-name {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 1.1rem;
        }

        .language-native {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .check-icon {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
        }

        .language-option input[type="radio"]:checked+.language-option-card .check-icon {
            display: flex;
        }

        /* Select Inputs */
        select,
        .custom-select {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius);
            font-size: 1rem;
            color: var(--gray-700);
            background: white;
            transition: var(--transition);
            cursor: pointer;
        }

        select:hover,
        .custom-select:hover {
            border-color: var(--gray-300);
        }

        select:focus,
        .custom-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        /* Toggle Switch */
        .toggle-group {
            display: flex;
            gap: 0.5rem;
            background: var(--gray-100);
            padding: 0.25rem;
            border-radius: 50px;
        }

        .toggle-option {
            position: relative;
            flex: 1;
        }

        .toggle-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .toggle-option label {
            display: block;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            background: transparent;
            color: var(--gray-600);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 500;
            text-align: center;
        }

        .toggle-option input[type="radio"]:checked+label {
            background: white;
            color: var(--primary);
            box-shadow: var(--shadow-sm);
        }

        /* Preview Card */
        .preview-card {
            background: var(--gray-50);
            border: 2px dashed var(--gray-300);
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .preview-title {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .preview-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem;
            background: white;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .preview-label {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .preview-value {
            color: var(--gray-900);
            font-weight: 600;
        }

        /* Button */
        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid var(--gray-100);
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid var(--primary);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .info-box-title {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-box-text {
            color: var(--gray-700);
            font-size: 0.9rem;
            line-height: 1.6;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .language-grid {
                grid-template-columns: 1fr;
            }

            .button-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Loading Animation */
        .saving {
            pointer-events: none;
            opacity: 0.6;
        }

        .saving::after {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="index.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            <?= __('back_to_dashboard') ?? 'Retour au tableau de bord' ?>
        </a>

        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-cog"></i>
                <?= __('language_and_regional_settings') ?? 'Paramètres de Langue et Région' ?>
            </h1>
            <p><?= __('customize_language_regional_settings') ?? 'Personnalisez vos préférences de langue, fuseau horaire et formats d\'affichage' ?></p>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?>">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <!-- Settings Form -->
        <form method="POST" id="settingsForm">
            <div class="settings-grid">

                <!-- Language Settings -->
                <div class="settings-card">
                    <h2>
                        <i class="fas fa-language"></i>
                        <?= __('language_preference') ?? 'Préférence de Langue' ?>
                    </h2>
                    <p class="settings-card-description">
                        <?= __('select_preferred_language') ?? 'Sélectionnez votre langue préférée pour l\'interface' ?>
                    </p>

                    <div class="language-grid">
                        <?php foreach ($languages as $code => $lang): ?>
                            <label class="language-option">
                                <input type="radio" name="language" value="<?= $code ?>"
                                    <?= $current_language === $code ? 'checked' : '' ?>
                                    onchange="document.getElementById('settingsForm').submit()">
                                <div class="language-option-card">
                                    <span class="check-icon"><i class="fas fa-check"></i></span>
                                    <div class="language-flag"><?= $lang['flag'] ?></div>
                                    <div class="language-name"><?= $lang['name'] ?></div>
                                    <div class="language-native"><?= $lang['native'] ?></div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="info-box">
                        <div class="info-box-title">
                            <i class="fas fa-lightbulb"></i>
                            <?= __('tip') ?? 'Astuce' ?>
                        </div>
                        <div class="info-box-text">
                            <?= __('language_auto_apply') ?? 'Le changement de langue s\'applique automatiquement à toute l\'interface' ?>
                        </div>
                    </div>
                </div>

                <!-- Regional Settings -->
                <div class="settings-card">
                    <h2>
                        <i class="fas fa-globe-americas"></i>
                        <?= __('regional_settings') ?? 'Paramètres Régionaux' ?>
                    </h2>
                    <p class="settings-card-description">
                        <?= __('configure_timezone_date_formats') ?? 'Configurez votre fuseau horaire et formats de date/heure' ?>
                    </p>

                    <!-- Timezone -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-clock"></i>
                            <?= __('timezone') ?? 'Fuseau Horaire' ?>
                        </label>
                        <select name="timezone" class="custom-select">
                            <?php foreach ($timezones as $tz => $label): ?>
                                <option value="<?= $tz ?>" <?= $current_timezone === $tz ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-description">
                            <?= __('current_time') ?? 'Heure actuelle' ?>:
                            <strong id="currentTime"></strong>
                        </div>
                    </div>

                    <!-- Date Format -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i>
                            <?= __('date_format') ?? 'Format de Date' ?>
                        </label>
                        <select name="date_format" class="custom-select">
                            <?php foreach ($date_formats as $format => $example): ?>
                                <option value="<?= $format ?>" <?= $current_date_format === $format ? 'selected' : '' ?>>
                                    <?= $example ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Time Format -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-stopwatch"></i>
                            <?= __('time_format') ?? 'Format d\'Heure' ?>
                        </label>
                        <div class="toggle-group">
                            <div class="toggle-option">
                                <input type="radio" name="time_format" value="12h" id="time12"
                                    <?= $current_time_format === '12h' ? 'checked' : '' ?>>
                                <label for="time12">12 <?= __('hours') ?? 'heures' ?> (2:30 PM)</label>
                            </div>
                            <div class="toggle-option">
                                <input type="radio" name="time_format" value="24h" id="time24"
                                    <?= $current_time_format === '24h' ? 'checked' : '' ?>>
                                <label for="time24">24 <?= __('hours') ?? 'heures' ?> (14:30)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appearance Settings -->
                <div class="settings-card">
                    <h2>
                        <i class="fas fa-palette"></i>
                        <?= __('appearance') ?? 'Apparence' ?>
                    </h2>
                    <p class="settings-card-description">
                        <?= __('customize_interface_appearance') ?? 'Personnalisez l\'apparence de l\'interface' ?>
                    </p>

                    <!-- Theme -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-moon"></i>
                            <?= __('theme') ?? 'Thème' ?>
                        </label>
                        <div class="toggle-group">
                            <div class="toggle-option">
                                <input type="radio" name="theme" value="light" id="themeLight"
                                    <?= $current_theme === 'light' ? 'checked' : '' ?>>
                                <label for="themeLight">
                                    <i class="fas fa-sun"></i> <?= __('light') ?? 'Clair' ?>
                                </label>
                            </div>
                            <div class="toggle-option">
                                <input type="radio" name="theme" value="dark" id="themeDark"
                                    <?= $current_theme === 'dark' ? 'checked' : '' ?>>
                                <label for="themeDark">
                                    <i class="fas fa-moon"></i> <?= __('dark') ?? 'Sombre' ?>
                                </label>
                            </div>
                            <div class="toggle-option">
                                <input type="radio" name="theme" value="auto" id="themeAuto"
                                    <?= $current_theme === 'auto' ? 'checked' : '' ?>>
                                <label for="themeAuto">
                                    <i class="fas fa-adjust"></i> <?= __('auto') ?? 'Auto' ?>
                                </label>
                            </div>
                        </div>
                        <div class="form-description">
                            <?= __('theme_description') ?? 'Le mode automatique s\'adapte aux préférences de votre système' ?>
                        </div>
                    </div>

                    <!-- Font Size -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-text-height"></i>
                            <?= __('font_size') ?? 'Taille de Police' ?>
                        </label>
                        <div class="toggle-group">
                            <div class="toggle-option">
                                <input type="radio" name="font_size" value="small" id="fontSmall"
                                    <?= $current_font_size === 'small' ? 'checked' : '' ?>>
                                <label for="fontSmall"><?= __('small') ?? 'Petit' ?></label>
                            </div>
                            <div class="toggle-option">
                                <input type="radio" name="font_size" value="medium" id="fontMedium"
                                    <?= $current_font_size === 'medium' ? 'checked' : '' ?>>
                                <label for="fontMedium"><?= __('medium') ?? 'Moyen' ?></label>
                            </div>
                            <div class="toggle-option">
                                <input type="radio" name="font_size" value="large" id="fontLarge"
                                    <?= $current_font_size === 'large' ? 'checked' : '' ?>>
                                <label for="fontLarge"><?= __('large') ?? 'Grand' ?></label>
                            </div>
                        </div>
                        <div class="form-description">
                            <?= __('font_size_accessibility') ?? 'Ajustez la taille du texte pour une meilleure lisibilité' ?>
                        </div>
                    </div>

                    <div class="info-box">
                        <div class="info-box-title">
                            <i class="fas fa-universal-access"></i>
                            <?= __('accessibility') ?? 'Accessibilité' ?>
                        </div>
                        <div class="info-box-text">
                            <?= __('accessibility_description') ?? 'Ces options améliorent l\'accessibilité de l\'interface pour tous les utilisateurs' ?>
                        </div>
                    </div>
                </div>

                <!-- Preview -->
                <div class="settings-card">
                    <h2>
                        <i class="fas fa-eye"></i>
                        <?= __('preview') ?? 'Aperçu' ?>
                    </h2>
                    <p class="settings-card-description">
                        <?= __('see_how_settings_applied') ?? 'Voyez comment vos paramètres seront appliqués' ?>
                    </p>

                    <div class="preview-card">
                        <div class="preview-title">
                            <i class="fas fa-desktop"></i>
                            <?= __('current_settings') ?? 'Paramètres Actuels' ?>
                        </div>

                        <div class="preview-item">
                            <span class="preview-label"><?= __('language') ?? 'Langue' ?></span>
                            <span class="preview-value" id="previewLanguage">
                                <?= $languages[$current_language]['native'] ?? 'Français' ?>
                            </span>
                        </div>

                        <div class="preview-item">
                            <span class="preview-label"><?= __('current_date') ?? 'Date actuelle' ?></span>
                            <span class="preview-value" id="previewDate">
                                <?= date($current_date_format) ?>
                            </span>
                        </div>

                        <div class="preview-item">
                            <span class="preview-label"><?= __('current_time') ?? 'Heure actuelle' ?></span>
                            <span class="preview-value" id="previewTime">
                                <?= $current_time_format === '12h' ? date('h:i A') : date('H:i') ?>
                            </span>
                        </div>

                        <div class="preview-item">
                            <span class="preview-label"><?= __('timezone') ?? 'Fuseau horaire' ?></span>
                            <span class="preview-value">
                                <?= $timezones[$current_timezone] ?? 'Casablanca (GMT+1)' ?>
                            </span>
                        </div>

                        <div class="preview-item">
                            <span class="preview-label"><?= __('theme') ?? 'Thème' ?></span>
                            <span class="preview-value" id="previewTheme">
                                <?= ucfirst($current_theme) ?>
                            </span>
                        </div>

                        <div class="preview-item">
                            <span class="preview-label"><?= __('font_size') ?? 'Taille de police' ?></span>
                            <span class="preview-value" id="previewFontSize">
                                <?= ucfirst($current_font_size) ?>
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Action Buttons -->
            <div class="button-group">
                <button type="submit" class="btn btn-primary" id="saveBtn">
                    <i class="fas fa-save"></i>
                    <?= __('save_changes') ?? 'Enregistrer les modifications' ?>
                </button>
                <a href="profile.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    <?= __('cancel') ?? 'Annuler' ?>
                </a>
            </div>
        </form>
    </div>

    <script>
        // Update current time display
        function updateCurrentTime() {
            const now = new Date();
            const timeFormat = document.querySelector('input[name="time_format"]:checked').value;
            const timeString = timeFormat === '12h' ?
                now.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }) :
                now.toLocaleTimeString('fr-FR', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                });

            document.getElementById('currentTime').textContent = timeString;

            // Update preview time
            document.getElementById('previewTime').textContent = timeString;
        }

        // Update preview when selections change
        document.querySelectorAll('input[name="language"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const languages = <?= json_encode($languages) ?>;
                document.getElementById('previewLanguage').textContent = languages[this.value].native;
            });
        });

        document.querySelectorAll('input[name="theme"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('previewTheme').textContent = this.value.charAt(0).toUpperCase() + this.value.slice(1);
            });
        });

        document.querySelectorAll('input[name="font_size"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.getElementById('previewFontSize').textContent = this.value.charAt(0).toUpperCase() + this.value.slice(1);
            });
        });

        document.querySelectorAll('input[name="time_format"]').forEach(radio => {
            radio.addEventListener('change', updateCurrentTime);
        });

        // Show loading state on form submit
        document.getElementById('settingsForm').addEventListener('submit', function() {
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.classList.add('saving');
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('saving') ?? 'Enregistrement...' ?>';
        });

        // Initialize
        updateCurrentTime();
        setInterval(updateCurrentTime, 1000);

        // Smooth scroll to top on language change
        document.querySelectorAll('input[name="language"]').forEach(radio => {
            radio.addEventListener('change', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>

</html>