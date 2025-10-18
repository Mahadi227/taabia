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

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $organizer_id = (int)($_POST['organizer_id'] ?? 0);
    $event_type = trim($_POST['event_type'] ?? 'webinar');
    $duration = (int)($_POST['duration'] ?? 60);
    $max_participants = (int)($_POST['max_participants'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $meeting_url = trim($_POST['meeting_url'] ?? '');

    if ($title && $description && $event_date && $organizer_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, organizer_id, event_type, duration, max_participants, price, meeting_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')");
            if ($stmt->execute([$title, $description, $event_date, $organizer_id, $event_type, $duration, $max_participants, $price, $meeting_url])) {
                $success = __("event_created_successfully");
                $_POST = array();
            } else {
                $error = __("error_creating_event");
            }
        } catch (PDOException $e) {
            error_log("Database error in admin/add_event.php: " . $e->getMessage());
            $error = __("error_creating_event");
        }
    } else {
        $error = __("all_required_fields");
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_event') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-plus-circle"></i> <?= __('add_event') ?></h1>
                        <p><?= __('create_new_event') ?></p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 20px;">
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

                        <a href="events.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?= __('back_to_events') ?>
                        </a>

                        <div class="user-menu">
                            <?php
                            $current_user = null;
                            try {
                                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                $stmt->execute([current_user_id()]);
                                $current_user = $stmt->fetch();
                            } catch (PDOException $e) {
                                error_log("Error fetching current user: " . $e->getMessage());
                            }
                            ?>
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Flash Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Event Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-calendar-plus"></i>
                        <?= __('event_information') ?>
                    </h3>
                </div>

                <div style="padding: var(--spacing-xl);">
                    <form action="add_event.php" method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-heading"></i>
                                    <?= __('event_title') ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="title" name="title" class="form-control"
                                    value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                    placeholder="<?= __('event_title_placeholder') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tie"></i>
                                    <?= __('organizer') ?> <span class="required">*</span>
                                </label>
                                <select id="organizer_id" name="organizer_id" class="form-control" required>
                                    <option value="">-- <?= __('choose_organizer') ?> --</option>
                                    <?php
                                    try {
                                        $organizers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'instructor') AND is_active = 1 ORDER BY full_name")->fetchAll();
                                        foreach ($organizers as $organizer) {
                                            $selected = ($_POST['organizer_id'] ?? '') == $organizer['id'] ? 'selected' : '';
                                            echo "<option value='{$organizer['id']}' {$selected}>{$organizer['full_name']}</option>";
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error fetching organizers: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i>
                                    <?= __('event_date') ?> <span class="required">*</span>
                                </label>
                                <input type="date" id="event_date" name="event_date" class="form-control"
                                    value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>"
                                    min="<?= date('Y-m-d') ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i>
                                    <?= __('event_type') ?>
                                </label>
                                <select id="event_type" name="event_type" class="form-control">
                                    <option value="webinar"
                                        <?= ($_POST['event_type'] ?? '') === 'webinar' ? 'selected' : '' ?>><?= __('webinar') ?>
                                    </option>
                                    <option value="workshop"
                                        <?= ($_POST['event_type'] ?? '') === 'workshop' ? 'selected' : '' ?>><?= __('workshop') ?>
                                    </option>
                                    <option value="meetup"
                                        <?= ($_POST['event_type'] ?? '') === 'meetup' ? 'selected' : '' ?>><?= __('meetup') ?>
                                    </option>
                                    <option value="conference"
                                        <?= ($_POST['event_type'] ?? '') === 'conference' ? 'selected' : '' ?>><?= __('conference') ?>
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-clock"></i>
                                    <?= __('duration_minutes') ?>
                                </label>
                                <input type="number" id="duration" name="duration" class="form-control"
                                    value="<?= htmlspecialchars($_POST['duration'] ?? '60') ?>" min="15" max="480"
                                    placeholder="60">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-link"></i>
                                    <?= __('meeting_url') ?>
                                </label>
                                <input type="url" id="meeting_url" name="meeting_url" class="form-control"
                                    value="<?= htmlspecialchars($_POST['meeting_url'] ?? '') ?>"
                                    placeholder="https://meet.google.com/...">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                <?= __('description') ?> <span class="required">*</span>
                            </label>
                            <textarea id="description" name="description" class="form-control" rows="6"
                                placeholder="<?= __('event_description_placeholder') ?>"
                                required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="event_image"><?= __('event_image') ?></label>
                            <input type="file" name="event_image" id="event_image" accept="image/*"
                                class="form-control">
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?= __('create_event') ?>
                            </button>
                            <a href="events.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                <?= __('cancel') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </div>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-label i {
            color: var(--primary-color);
            width: 16px;
        }

        .required {
            color: var(--danger-color);
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-base);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 121, 96, 0.1);
        }

        .form-control::placeholder {
            color: var(--text-light);
        }

        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }
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
    </style>

    <script>
        // Add smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus effects to form controls
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });

                control.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

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
    </script>
</body>

</html>