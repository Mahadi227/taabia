<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$message = '';
$error = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    redirect('users.php');
}

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('users.php');
    }
} catch (PDOException $e) {
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $role = sanitize($_POST['role']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($full_name)) {
        $error = __("full_name_required");
    } elseif (empty($email)) {
        $error = __("email_required");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __("email_invalid_format");
    } elseif (!in_array($role, ['admin', 'instructor', 'student', 'vendor'])) {
        $error = __("invalid_role");
    } else {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = __("email_already_used");
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, address = ?, role = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $address, $role, $is_active, $user_id]);

                $message = __("user_updated_successfully");

                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $error = __("error_updating_user") . ': ' . $e->getMessage();
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
    <title><?= __('edit_user_title') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
        /* Enhanced User Edit Page Styles */
        :root {
            --primary-color: #009688;
            --primary-hover: #00796b;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #dee2e6;
            --border-radius: 8px;
            --border-radius-sm: 4px;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 4px 8px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--dark-color);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
            min-height: 100vh;
        }

        .form-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
            max-width: 900px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
        }

        .form-header {
            text-align: center;
            margin-bottom: 2.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid var(--light-color);
        }

        .form-header h1 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-size: 2rem;
            font-weight: 600;
        }

        .form-header p {
            color: var(--secondary-color);
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-family: inherit;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--secondary-color);
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2.5rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--light-color);
        }

        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }

        .user-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            border: 1px solid var(--border-color);
        }

        .user-info p {
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        .user-info span {
            color: var(--secondary-color);
            font-size: 0.95rem;
        }

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
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: white;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
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
            color: var(--dark-color);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--light-color);
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

        /* Header Actions */
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .form-container {
                padding: 1.5rem;
                margin: 0;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .header-actions {
                flex-direction: column;
                gap: 10px;
            }
        }

        /* Enhanced Form Styling */
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: normal;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="form-container">
            <div class="form-header">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h1><i class="fas fa-edit"></i> <?= __('edit_user_title') ?></h1>

                    <div class="header-actions">
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
                </div>
                <p><?= __('edit_user_description') ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="user-info">
                <p><strong><?= __('user_id') ?>:</strong> <span><?= $user['id'] ?></span></p>
                <p><strong><?= __('registration_date') ?>:</strong> <span><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></span></p>
                <p><strong><?= __('last_login') ?>:</strong> <span><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : __('never') ?></span></p>
            </div>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name"><?= __('full_name') ?> *</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email"><?= __('email') ?> *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone"><?= __('phone') ?></label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="address"><?= __('address') ?></label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="role"><?= __('role') ?> *</label>
                        <select id="role" name="role" required>
                            <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>><?= __('student') ?></option>
                            <option value="instructor" <?= $user['role'] === 'instructor' ? 'selected' : '' ?>><?= __('instructor') ?></option>
                            <option value="vendor" <?= $user['role'] === 'vendor' ? 'selected' : '' ?>><?= __('vendor') ?></option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>><?= __('admin') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="is_active"><?= __('status') ?> *</label>
                        <select id="is_active" name="is_active" required>
                            <option value="1" <?= $user['is_active'] == 1 ? 'selected' : '' ?>><?= __('active') ?></option>
                            <option value="0" <?= $user['is_active'] == 0 ? 'selected' : '' ?>><?= __('inactive') ?></option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                    </a>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> <?= __('update_user') ?>
                    </button>
                </div>
            </form>
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

        // Enhanced form interactions
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

            // Enhanced form validation feedback
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input[required], select[required]');

            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = 'var(--danger-color)';
                    } else {
                        this.style.borderColor = 'var(--success-color)';
                    }
                });

                input.addEventListener('input', function() {
                    if (this.style.borderColor === 'var(--danger-color)' && this.value.trim() !== '') {
                        this.style.borderColor = 'var(--success-color)';
                    }
                });
            });

            // Form submission enhancement
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('update_user') ?>...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>

</html>