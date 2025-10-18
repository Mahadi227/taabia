<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

$message = null;
$error = null;

// Get current settings
$settings = get_all_settings();
$commission_settings = get_commission_settings();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_general':
            $platform_name = sanitize($_POST['platform_name']);
            $platform_description = sanitize($_POST['platform_description']);
            $currency = sanitize($_POST['currency']);
            $contact_email = sanitize($_POST['contact_email']);
            $contact_phone = sanitize($_POST['contact_phone']);
            $address = sanitize($_POST['address']);

            if (
                update_setting('platform_name', $platform_name, 'Platform name') &&
                update_setting('platform_description', $platform_description, 'Platform description') &&
                update_setting('currency', $currency, 'Default currency') &&
                update_setting('contact_email', $contact_email, 'Contact email') &&
                update_setting('contact_phone', $contact_phone, 'Contact phone') &&
                update_setting('address', $address, 'Platform address')
            ) {
                $message = __('general_settings_updated_successfully');
            } else {
                $error = __('error_updating_general_settings');
            }
            break;

        case 'update_business':
            $instructor_rate = (float) $_POST['instructor_commission_rate'];
            $vendor_rate = (float) $_POST['vendor_commission_rate'];
            $min_payout = (float) $_POST['min_payout_amount'];
            $max_file_size = (int) $_POST['max_file_size'];

            if (
                $instructor_rate >= 0 && $instructor_rate <= 100 &&
                $vendor_rate >= 0 && $vendor_rate <= 100 &&
                $min_payout >= 0 && $max_file_size > 0
            ) {

                if (
                    update_commission_settings($instructor_rate, $vendor_rate) &&
                    update_setting('min_payout_amount', $min_payout, 'Minimum payout amount') &&
                    update_setting('max_file_size', $max_file_size, 'Maximum file upload size in bytes')
                ) {
                    $message = __('business_settings_updated_successfully');
                } else {
                    $error = __('error_updating_business_settings');
                }
            } else {
                $error = __('invalid_business_settings_values');
            }
            break;

        case 'upload_logo':
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $result = upload_site_image($_FILES['logo'], 'site_logo', 2097152); // 2MB max
                if ($result['success']) {
                    $message = __('logo_updated_successfully');
                } else {
                    $error = $result['error'];
                }
            } else {
                $error = __('error_uploading_logo');
            }
            break;

        case 'upload_banner':
            if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
                $result = upload_site_image($_FILES['banner'], 'featured_courses_banner', 5242880); // 5MB max
                if ($result['success']) {
                    $message = __('banner_updated_successfully');
                } else {
                    $error = $result['error'];
                }
            } else {
                $error = __('error_uploading_banner');
            }
            break;

        case 'update_technical':
            $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
            $registration_enabled = isset($_POST['registration_enabled']) ? 1 : 0;
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $backup_frequency = sanitize($_POST['backup_frequency']);

            if (
                update_setting('maintenance_mode', $maintenance_mode, 'Maintenance mode status') &&
                update_setting('registration_enabled', $registration_enabled, 'User registration enabled') &&
                update_setting('email_notifications', $email_notifications, 'Email notifications enabled') &&
                update_setting('backup_frequency', $backup_frequency, 'Backup frequency')
            ) {
                $message = __('technical_settings_updated_successfully');
            } else {
                $error = __('error_updating_technical_settings');
            }
            break;
    }

    // Refresh settings after update
    $settings = get_all_settings();
    $commission_settings = get_commission_settings();
}

// Get current images
$current_logo = get_site_image('site_logo');
$current_banner = get_site_image('featured_courses_banner');
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <title><?= __('site_settings') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .settings-container {
            max-width: 1200px;
            margin: 2rem auto;
            background: #fff;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .settings-tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 2rem;
        }

        .tab-button {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: #009688;
            border-bottom-color: #009688;
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .settings-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .settings-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #009688;
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .image-preview {
            max-width: 200px;
            max-height: 100px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
        }

        .btn {
            background: #009688;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            background: #00796b;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1rem;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #009688;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        /* Admin Language Switcher Styles */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .admin-language-btn:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: #f8f9fa;
        }

        .admin-language-item.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .language-flag {
            font-size: 1rem;
        }

        .language-name {
            flex: 1;
        }

        .admin-language-item i {
            color: var(--success-color);
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <div class="settings-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <div style="margin-left: 260px; padding: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h1><i class="fas fa-cog"></i> <?= __('site_settings') ?></h1>

                <!-- Language Switcher -->
                <div class="admin-language-switcher">
                    <div class="admin-language-dropdown">
                        <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                            <i class="fas fa-globe"></i>
                            <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>

                        <div class="admin-language-menu" id="adminLanguageDropdown">
                            <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                <span class="language-flag">🇫🇷</span>
                                <span class="language-name">Français</span>
                                <?php if (getCurrentLanguage() == 'fr'): ?>
                                    <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </a>
                            <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                <span class="language-flag">🇬🇧</span>
                                <span class="language-name">English</span>
                                <?php if (getCurrentLanguage() == 'en'): ?>
                                    <i class="fas fa-check"></i>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="tab-button active" onclick="showTab('general')">
                    <i class="fas fa-info-circle"></i> <?= __('general') ?>
                </button>
                <button class="tab-button" onclick="showTab('visual')">
                    <i class="fas fa-palette"></i> <?= __('visual') ?>
                </button>
                <button class="tab-button" onclick="showTab('business')">
                    <i class="fas fa-chart-line"></i> <?= __('business') ?>
                </button>
                <button class="tab-button" onclick="showTab('technical')">
                    <i class="fas fa-cogs"></i> <?= __('technical') ?>
                </button>
            </div>

            <!-- General Settings Tab -->
            <div id="general" class="tab-content active">
                <div class="settings-section">
                    <h3><i class="fas fa-info-circle"></i> <?= __('general_information') ?></h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_general">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="platform_name"><?= __('platform_name') ?></label>
                                <input type="text" id="platform_name" name="platform_name"
                                    value="<?= htmlspecialchars($settings['platform_name']['value'] ?? 'TaaBia Skills & Market') ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="currency"><?= __('currency') ?></label>
                                <select id="currency" name="currency">
                                    <option value="GHS" <?= ($settings['currency']['value'] ?? 'GHS') === 'GHS' ? 'selected' : '' ?>>GHS (Cedi)</option>
                                    <option value="USD" <?= ($settings['currency']['value'] ?? '') === 'USD' ? 'selected' : '' ?>>USD (Dollar)</option>
                                    <option value="EUR" <?= ($settings['currency']['value'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                                    <option value="XOF" <?= ($settings['currency']['value'] ?? '') === 'XOF' ? 'selected' : '' ?>>XOF (Franc CFA)</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="platform_description"><?= __('platform_description') ?></label>
                            <textarea id="platform_description" name="platform_description" rows="3"><?= htmlspecialchars($settings['platform_description']['value'] ?? '') ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="contact_email"><?= __('contact_email') ?></label>
                                <input type="email" id="contact_email" name="contact_email"
                                    value="<?= htmlspecialchars($settings['contact_email']['value'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="contact_phone"><?= __('contact_phone') ?></label>
                                <input type="tel" id="contact_phone" name="contact_phone"
                                    value="<?= htmlspecialchars($settings['contact_phone']['value'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address"><?= __('address') ?></label>
                            <textarea id="address" name="address" rows="2"><?= htmlspecialchars($settings['address']['value'] ?? '') ?></textarea>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> <?= __('save_general_settings') ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Visual Settings Tab -->
            <div id="visual" class="tab-content">
                <div class="settings-section">
                    <h3><i class="fas fa-image"></i> <?= __('platform_logo') ?></h3>
                    <?php if ($current_logo): ?>
                        <div>
                            <div style="color:#555; margin-bottom:.5rem;"><?= __('current_logo') ?>:</div>
                            <img src="../<?= htmlspecialchars($current_logo) ?>" alt="<?= __('current_logo') ?>" class="image-preview">
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom:1rem; color:#777;"><?= __('no_logo_defined') ?></div>
                    <?php endif; ?>

                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_logo">
                        <div class="form-group">
                            <label for="logo"><?= __('choose_logo') ?> (JPG, PNG, WEBP, max 2MB)</label>
                            <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <button type="submit" class="btn">
                            <i class="fas fa-upload"></i> <?= __('update_logo') ?>
                        </button>
                    </form>
                </div>

                <div class="settings-section">
                    <h3><i class="fas fa-image"></i> <?= __('featured_courses_banner') ?></h3>
                    <?php if ($current_banner): ?>
                        <div>
                            <div style="color:#555; margin-bottom:.5rem;"><?= __('current_banner') ?>:</div>
                            <img src="../<?= htmlspecialchars($current_banner) ?>" alt="<?= __('current_banner') ?>" class="image-preview">
                        </div>
                    <?php else: ?>
                        <div style="margin-bottom:1rem; color:#777;"><?= __('no_banner_defined') ?></div>
                    <?php endif; ?>

                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_banner">
                        <div class="form-group">
                            <label for="banner"><?= __('choose_banner') ?> (JPG, PNG, WEBP, max 5MB)</label>
                            <input type="file" id="banner" name="banner" accept="image/jpeg,image/png,image/webp" required>
                        </div>
                        <button type="submit" class="btn">
                            <i class="fas fa-upload"></i> <?= __('update_banner') ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Business Settings Tab -->
            <div id="business" class="tab-content">
                <div class="settings-section">
                    <h3><i class="fas fa-percentage"></i> <?= __('commission_settings') ?></h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_business">

                        <div class="form-row">
                            <div class="form-group">
                                <label for="instructor_commission_rate"><?= __('instructor_commission') ?> (%)</label>
                                <input type="number" id="instructor_commission_rate" name="instructor_commission_rate"
                                    value="<?= htmlspecialchars($commission_settings['instructor_commission_rate'] ?? '20') ?>"
                                    min="0" max="100" step="0.01" required>
                                <small style="color:#666;"><?= __('instructor_commission_description') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="vendor_commission_rate"><?= __('vendor_commission') ?> (%)</label>
                                <input type="number" id="vendor_commission_rate" name="vendor_commission_rate"
                                    value="<?= htmlspecialchars($commission_settings['vendor_commission_rate'] ?? '15') ?>"
                                    min="0" max="100" step="0.01" required>
                                <small style="color:#666;"><?= __('vendor_commission_description') ?></small>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="min_payout_amount"><?= __('minimum_payout_amount') ?></label>
                                <input type="number" id="min_payout_amount" name="min_payout_amount"
                                    value="<?= htmlspecialchars($settings['min_payout_amount']['value'] ?? '50') ?>"
                                    min="0" step="0.01" required>
                                <small style="color:#666;"><?= __('minimum_payout_description') ?></small>
                            </div>
                            <div class="form-group">
                                <label for="max_file_size"><?= __('max_file_size') ?> (MB)</label>
                                <input type="number" id="max_file_size" name="max_file_size"
                                    value="<?= htmlspecialchars(($settings['max_file_size']['value'] ?? 10485760) / 1024 / 1024) ?>"
                                    min="1" step="1" required>
                                <small style="color:#666;"><?= __('max_file_size_description') ?></small>
                            </div>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> <?= __('save_business_settings') ?>
                        </button>
                    </form>
                </div>

                <div class="settings-section">
                    <h3><i class="fas fa-chart-bar"></i> <?= __('platform_statistics') ?></h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format(100 - ($commission_settings['instructor_commission_rate'] ?? 20), 1) ?>%</div>
                            <div class="stat-label"><?= __('instructor_earnings') ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format(100 - ($commission_settings['vendor_commission_rate'] ?? 15), 1) ?>%</div>
                            <div class="stat-label"><?= __('vendor_earnings') ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($commission_settings['instructor_commission_rate'] ?? 20, 1) ?>%</div>
                            <div class="stat-label"><?= __('platform_commission_instructors') ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= number_format($commission_settings['vendor_commission_rate'] ?? 15, 1) ?>%</div>
                            <div class="stat-label"><?= __('platform_commission_vendors') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Technical Settings Tab -->
            <div id="technical" class="tab-content">
                <div class="settings-section">
                    <h3><i class="fas fa-cogs"></i> <?= __('technical_settings') ?></h3>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="update_technical">

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="maintenance_mode" name="maintenance_mode"
                                    <?= ($settings['maintenance_mode']['value'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label for="maintenance_mode"><?= __('maintenance_mode') ?></label>
                            </div>
                            <small style="color:#666;"><?= __('maintenance_mode_description') ?></small>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="registration_enabled" name="registration_enabled"
                                    <?= ($settings['registration_enabled']['value'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label for="registration_enabled"><?= __('user_registration_enabled') ?></label>
                            </div>
                            <small style="color:#666;"><?= __('user_registration_description') ?></small>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="email_notifications" name="email_notifications"
                                    <?= ($settings['email_notifications']['value'] ?? '1') === '1' ? 'checked' : '' ?>>
                                <label for="email_notifications"><?= __('email_notifications') ?></label>
                            </div>
                            <small style="color:#666;"><?= __('email_notifications_description') ?></small>
                        </div>

                        <div class="form-group">
                            <label for="backup_frequency"><?= __('backup_frequency') ?></label>
                            <select id="backup_frequency" name="backup_frequency">
                                <option value="daily" <?= ($settings['backup_frequency']['value'] ?? 'daily') === 'daily' ? 'selected' : '' ?>><?= __('daily') ?></option>
                                <option value="weekly" <?= ($settings['backup_frequency']['value'] ?? '') === 'weekly' ? 'selected' : '' ?>><?= __('weekly') ?></option>
                                <option value="monthly" <?= ($settings['backup_frequency']['value'] ?? '') === 'monthly' ? 'selected' : '' ?>><?= __('monthly') ?></option>
                                <option value="disabled" <?= ($settings['backup_frequency']['value'] ?? '') === 'disabled' ? 'selected' : '' ?>><?= __('disabled') ?></option>
                            </select>
                        </div>

                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> <?= __('save_technical_settings') ?>
                        </button>
                    </form>
                </div>

                <div class="settings-section">
                    <h3><i class="fas fa-info-circle"></i> Informations système</h3>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= phpversion() ?></div>
                            <div class="stat-label">Version PHP</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= ini_get('upload_max_filesize') ?></div>
                            <div class="stat-label">Taille max upload (PHP)</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= ini_get('max_execution_time') ?>s</div>
                            <div class="stat-label">Temps d'exécution max</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= ini_get('memory_limit') ?></div>
                            <div class="stat-label">Limite mémoire</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => content.classList.remove('active'));

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => button.classList.remove('active'));

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>

</html>