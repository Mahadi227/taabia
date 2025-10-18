a<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($full_name)) {
        $error_message = __("full_name_required");
    } elseif (empty($email)) {
        $error_message = __("email_required");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = __("email_invalid");
    } elseif (empty($password)) {
        $error_message = __("password_required");
    } elseif (strlen($password) < 6) {
        $error_message = __("password_min_length");
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = __("email_already_exists");
        } else {
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, role, phone, address, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            if ($stmt->execute([$full_name, $email, $hashed_password, $role, $phone, $address, $is_active])) {
                $success_message = __("user_created_successfully");
                // Clear form data
                $full_name = $email = $phone = $address = '';
                $role = 'student';
                $is_active = 1;
            } else {
                $error_message = __("error_creating_user");
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_user_title') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
        /* Admin Language Switcher */
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
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-medium);
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--bg-secondary);
        }

        .admin-language-item.active {
            background: var(--primary-color);
            color: white;
        }

        .language-flag {
            font-size: 16px;
        }

        .language-name {
            flex: 1;
            font-size: 14px;
        }

        .admin-language-item i {
            font-size: 12px;
            margin-left: auto;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1><?= __('add_user_title') ?></h1>
                    <p><?= __('add_user_description') ?></p>
                </div>

                <div class="header-right" style="display: flex; align-items: center; gap: 20px;">
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

                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        <?= __('back_to_list') ?>
                    </a>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('user_information') ?></h3>
                </div>

                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="form-label"><?= __('full_name') ?> *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control"
                                    value="<?= htmlspecialchars($full_name ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="email" class="form-label"><?= __('email') ?> *</label>
                                <input type="email" id="email" name="email" class="form-control"
                                    value="<?= htmlspecialchars($email ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password" class="form-label"><?= __('password') ?> *</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                                <small class="form-text"><?= __('minimum_characters') ?></small>
                            </div>

                            <div class="form-group">
                                <label for="role" class="form-label"><?= __('role') ?> *</label>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="student" <?= ($role ?? '') === 'student' ? 'selected' : '' ?>><?= __('student') ?></option>
                                    <option value="instructor" <?= ($role ?? '') === 'instructor' ? 'selected' : '' ?>><?= __('instructor') ?></option>
                                    <option value="vendor" <?= ($role ?? '') === 'vendor' ? 'selected' : '' ?>><?= __('vendor') ?></option>
                                    <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>><?= __('admin') ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="form-label"><?= __('phone') ?></label>
                                <input type="tel" id="phone" name="phone" class="form-control"
                                    value="<?= htmlspecialchars($phone ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="is_active" class="form-label"><?= __('status') ?></label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="is_active" name="is_active"
                                            <?= ($is_active ?? 1) == 1 ? 'checked' : '' ?>>
                                        <span class="checkmark"></span>
                                        <?= __('user_active') ?>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address" class="form-label"><?= __('address') ?></label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($address ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?= __('create_user') ?>
                            </button>

                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                <?= __('cancel') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close admin language dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const switcher = document.querySelector('.admin-language-switcher');

            if (switcher && !switcher.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Add smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>

</html>