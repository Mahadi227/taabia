<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Handle language change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['language'])) {
    $new_language = $_POST['language'];
    
    if (in_array($new_language, ['fr', 'en'])) {
        try {
            // Update user's language preference in database
            $stmt = $pdo->prepare("UPDATE users SET language_preference = ? WHERE id = ?");
            $stmt->execute([$new_language, $student_id]);
            
            // Update session language
            setLanguage($new_language);
            
            $message = __('saved_successfully');
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = __('error_occurred');
            $message_type = 'error';
            error_log("Failed to update language preference: " . $e->getMessage());
        }
    }
}

// Get current user language
try {
    $stmt = $pdo->prepare("SELECT language_preference FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $current_language = $stmt->fetchColumn() ?: 'fr';
} catch (PDOException $e) {
    $current_language = 'fr';
    error_log("Failed to get user language preference: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('language') ?> - <?= __('settings') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?></p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    <?= __('discover_courses') ?>
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="course_lessons.php" class="student-nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?>
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?>
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?>
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('my_profile') ?>
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('language') ?> - <?= __('settings') ?></h1>
                        <p><?= __('switch_language') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="student-alert student-alert-<?= $message_type ?>" style="margin-bottom: var(--spacing-4);">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Language Settings -->
            <div class="student-table-container">
                <div style="padding: var(--spacing-6);">
                    <form method="POST" action="">
                        <div style="margin-bottom: var(--spacing-6);">
                            <h3 style="margin: 0 0 var(--spacing-4) 0; color: var(--gray-900);">
                                <i class="fas fa-globe"></i> <?= __('language') ?>
                            </h3>
                            <p style="margin: 0; color: var(--gray-600);">
                                <?= __('switch_language') ?>
                            </p>
                        </div>

                        <div style="display: grid; gap: var(--spacing-4);">
                            <!-- French Option -->
                            <label class="language-option">
                                <input type="radio" name="language" value="fr" <?= $current_language === 'fr' ? 'checked' : '' ?>>
                                <div class="language-option-content">
                                    <div class="language-option-flag">🇫🇷</div>
                                    <div class="language-option-text">
                                        <div class="language-option-name"><?= __('french') ?></div>
                                        <div class="language-option-desc">Français</div>
                                    </div>
                                    <?php if ($current_language === 'fr'): ?>
                                        <div class="language-option-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>

                            <!-- English Option -->
                            <label class="language-option">
                                <input type="radio" name="language" value="en" <?= $current_language === 'en' ? 'checked' : '' ?>>
                                <div class="language-option-content">
                                    <div class="language-option-flag">🇬🇧</div>
                                    <div class="language-option-text">
                                        <div class="language-option-name"><?= __('english') ?></div>
                                        <div class="language-option-desc">English</div>
                                    </div>
                                    <?php if ($current_language === 'en'): ?>
                                        <div class="language-option-check">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>

                        <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-3);">
                            <button type="submit" class="student-btn student-btn-primary">
                                <i class="fas fa-save"></i>
                                <?= __('save') ?>
                            </button>
                            <a href="profile.php" class="student-btn student-btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <?= __('back') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
    .language-option {
        display: block;
        cursor: pointer;
        border: 2px solid var(--gray-200);
        border-radius: 8px;
        padding: var(--spacing-4);
        transition: all 0.2s ease;
        background: var(--white);
    }

    .language-option:hover {
        border-color: var(--primary-color);
        background: var(--gray-50);
    }

    .language-option input[type="radio"] {
        display: none;
    }

    .language-option input[type="radio"]:checked + .language-option-content {
        border-color: var(--primary-color);
        background: var(--primary-color);
        color: var(--white);
    }

    .language-option-content {
        display: flex;
        align-items: center;
        gap: var(--spacing-4);
        border: 2px solid transparent;
        border-radius: 6px;
        padding: var(--spacing-3);
        transition: all 0.2s ease;
    }

    .language-option-flag {
        font-size: 24px;
        width: 40px;
        text-align: center;
    }

    .language-option-text {
        flex: 1;
    }

    .language-option-name {
        font-weight: 600;
        font-size: var(--font-size-lg);
        margin-bottom: var(--spacing-1);
    }

    .language-option-desc {
        font-size: var(--font-size-sm);
        opacity: 0.8;
    }

    .language-option-check {
        color: var(--primary-color);
        font-size: var(--font-size-lg);
    }

    .language-option input[type="radio"]:checked + .language-option-content .language-option-check {
        color: var(--white);
    }

    .student-alert {
        padding: var(--spacing-4);
        border-radius: 6px;
        margin-bottom: var(--spacing-4);
    }

    .student-alert-success {
        background: var(--success-color);
        color: var(--white);
    }

    .student-alert-error {
        background: var(--danger-color);
        color: var(--white);
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-submit form when language is selected
        const languageRadios = document.querySelectorAll('input[name="language"]');
        languageRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    this.closest('form').submit();
                }
            });
        });
    });
    </script>
</body>
</html> 