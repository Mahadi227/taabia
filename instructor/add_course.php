<?php
// Start session first to ensure proper language handling
require_once '../includes/session.php';

// Handle language switching
require_once '../includes/language_handler.php';
require_once '../includes/function.php';
require_once '../includes/db.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = $_POST['category'] ?? 'general';
        $level = $_POST['level'] ?? 'beginner';
        $duration = $_POST['duration'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $uploaded_image_filename = null;

        // Validation
        if (empty($title)) {
            $error_message = __('course_title_required');
        } elseif (strlen($title) < 5) {
            $error_message = __('course_title_min_length');
        } elseif (empty($description)) {
            $error_message = __('course_description_required');
        } elseif (strlen($description) < 20) {
            $error_message = __('course_description_min_length');
        } elseif ($price < 0) {
            $error_message = __('course_price_negative');
        } else {
            // Optional image upload handling
            if (isset($_FILES['course_image']) && is_array($_FILES['course_image']) && ($_FILES['course_image']['error'] !== UPLOAD_ERR_NO_FILE)) {
                $file = $_FILES['course_image'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_message = __('image_upload_error') . " (code: " . (int)$file['error'] . ").";
                } else {
                    $allowedMime = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    if (!isset($allowedMime[$mime])) {
                        $error_message = __('unsupported_image_format');
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $error_message = __('image_too_large');
                    } else {
                        $uploadsDir = realpath(__DIR__ . '/../uploads');
                        if ($uploadsDir === false) {
                            $error_message = __('upload_directory_not_found');
                        } else {
                            $ext = $allowedMime[$mime];
                            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
                            $unique = time() . '_' . bin2hex(random_bytes(4));
                            $filename = "course_{$unique}_{$safeBase}.{$ext}";
                            $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
                            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $error_message = "Impossible d'enregistrer l'image.";
                            } else {
                                @chmod($targetPath, 0644);
                                $uploaded_image_filename = $filename;
                            }
                        }
                    }
                }
            }

            if (empty($error_message)) {
                // Insert course
                $stmt = $pdo->prepare("
                INSERT INTO courses (title, description, instructor_id, price, category, level, duration, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
                $stmt->execute([$title, $description, $instructor_id, $price, $category, $level, $duration, $status]);

                $course_id = (int)$pdo->lastInsertId();

                // Set image column if uploaded
                if ($uploaded_image_filename) {
                    $columns = $pdo->query("SHOW COLUMNS FROM courses")->fetchAll(PDO::FETCH_COLUMN);
                    $imgCol = null;
                    if (in_array('image_url', $columns, true)) {
                        $imgCol = 'image_url';
                    } elseif (in_array('thumbnail_url', $columns, true)) {
                        $imgCol = 'thumbnail_url';
                    }
                    if ($imgCol) {
                        $upd = $pdo->prepare("UPDATE courses SET {$imgCol} = ? WHERE id = ?");
                        $upd->execute([$uploaded_image_filename, $course_id]);
                    }
                }

                $success_message = __('course_created_successfully') . " ID: " . $course_id;

                // Clear form data
                $title = $description = '';
                $price = 0;
                $category = 'general';
                $level = 'beginner';
                $duration = '';
                $status = 'draft';
            }
        }
    }

    // Get instructor statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_courses = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'published'");
    $stmt->execute([$instructor_id]);
    $active_courses = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error in add_course: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
    $total_courses = 0;
    $active_courses = 0;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('create_new_course') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
    <style>
        /* Hamburger Menu Styles */
        .hamburger-menu-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: all 0.3s ease;
        }

        .hamburger-line {
            width: 100%;
            height: 3px;
            background: linear-gradient(135deg,rgb(235, 37, 37) 0%,rgb(216, 29, 29) 100%) !important;
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        .hamburger-menu-btn:hover .hamburger-line {
            background: linear-gradient(135deg,rgb(235, 37, 37) 0%,rgb(216, 29, 29) 100%) !important;
        }

        /* Responsive Sidebar */
        .instructor-sidebar {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .instructor-sidebar.mobile-hidden {
            transform: translateX(-100%);
        }

        .instructor-sidebar.mobile-visible {
            transform: translateX(0);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Enhanced Form Styles */
        .instructor-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .instructor-form-group {
            margin-bottom: 25px;
        }

        .instructor-form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .instructor-form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .instructor-form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .instructor-form-input.error {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }

        .instructor-form-input.success {
            border-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
        }

        .instructor-form-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .instructor-form-select {
            cursor: pointer;
        }

        .form-validation-message {
            font-size: 12px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .form-validation-message.error {
            color: var(--danger-color);
        }

        .form-validation-message.success {
            color: var(--success-color);
        }

        .character-counter {
            font-size: 12px;
            color: var(--gray-500);
            text-align: right;
            margin-top: 4px;
        }

        .character-counter.warning {
            color: var(--warning-color);
        }

        .character-counter.error {
            color: var(--danger-color);
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .hamburger-menu-btn {
                display: flex;
            }

            .instructor-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                transform: translateX(-100%);
                box-shadow: none;
            }

            .instructor-sidebar.mobile-visible {
                transform: translateX(0);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }

            .instructor-main {
                margin-left: 0;
                width: 100%;
            }

            .instructor-header {
                padding-left: 60px;
            }

            .instructor-form {
                padding: 20px;
                margin: 0 15px 20px 15px;
            }

            /* Stack form columns on mobile */
            .instructor-form>div[style*="grid-template-columns"] {
                display: block !important;
            }

            .instructor-form>div[style*="grid-template-columns"]>div {
                margin-bottom: 20px;
            }

            /* Mobile-friendly buttons */
            .instructor-btn {
                padding: 12px 16px;
                font-size: 14px;
                width: 100%;
                margin-bottom: 10px;
            }

            /* Adjust language switcher for mobile */
            .instructor-language-switcher {
                margin-top: 10px;
            }
        }

        @media (max-width: 480px) {
            .hamburger-menu-btn {
                top: 15px;
                left: 15px;
                width: 25px;
                height: 25px;
            }

            .hamburger-line {
                height: 2px;
            }

            .instructor-header {
                padding: 20px 15px 20px 50px;
            }

            .instructor-header h1 {
                font-size: 1.5rem;
            }

            .instructor-header p {
                font-size: 0.9rem;
            }

            .instructor-form {
                padding: 15px;
                margin: 0 10px 15px 10px;
            }

            /* Stack header content vertically on very small screens */
            .instructor-header>div {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Tablet Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .instructor-sidebar {
                width: 250px;
            }

            .instructor-main {
                margin-left: 250px;
            }

            .instructor-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Animation for smooth transitions */
        .instructor-layout {
            transition: all 0.3s ease;
        }

        /* Focus styles for accessibility */
        .hamburger-menu-btn:focus {
            outline: 2px solid var(--primary-color, #2563eb);
            outline-offset: 2px;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .hamburger-line {
                background: #ffffff;
            }

            .hamburger-menu-btn:hover .hamburger-line {
                background: #e5e7eb;
            }

            .mobile-overlay {
                background: rgba(0, 0, 0, 0.7);
            }
        }

        /* Form Preview Styles */
        .course-preview {
            background: #f8fafc;
            border: 2px dashed var(--gray-300);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .course-preview.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .preview-image {
            width: 100%;
            height: 200px;
            background: var(--gray-100);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            overflow: hidden;
        }

        .preview-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .preview-content h3 {
            margin: 0 0 10px 0;
            color: var(--gray-900);
        }

        .preview-content p {
            margin: 0 0 15px 0;
            color: var(--gray-600);
            line-height: 1.5;
        }

        .preview-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            font-size: 12px;
            color: var(--gray-500);
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Sidebar -->
        <div class="instructor-sidebar" id="sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p><?= __('instructor_space') ?></p>
            </div>

            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="add_course.php" class="instructor-nav-item active">
                    <i class="fas fa-plus-circle"></i>
                    <?= __('new_course') ?>
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('add_lesson') ?>
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    <?= __('my_students') ?>
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    <?= __('assignments_to_validate') ?>
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    <?= __('my_earnings') ?>
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('transactions') ?>
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= __('payments') ?>
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Mobile Hamburger Menu Button -->
        <button class="hamburger-menu-btn" id="hamburgerMenuBtn" aria-label="<?= __('toggle_navigation') ?>">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('create_new_course') ?></h1>
                        <p><?= __('create_complete_course') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div style="
                    background: var(--success-color); 
                    color: var(--white); 
                    padding: var(--spacing-4); 
                    border-radius: var(--radius-lg); 
                    margin-bottom: var(--spacing-6);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                ">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="
                    background: var(--danger-color); 
                    color: var(--white); 
                    padding: var(--spacing-4); 
                    border-radius: var(--radius-lg); 
                    margin-bottom: var(--spacing-6);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                ">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Course Creation Form -->
            <div class="instructor-form">
                <form method="POST" id="courseForm" enctype="multipart/form-data">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="title" class="instructor-form-label">
                                <i class="fas fa-heading"></i> <?= __('course_title') ?> *
                            </label>
                            <input type="text" name="title" id="title" required
                                class="instructor-form-input"
                                placeholder="<?= __('course_title_placeholder') ?>"
                                value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                            <div class="character-counter" id="titleCounter">0/100</div>
                        </div>

                        <div class="instructor-form-group">
                            <label for="category" class="instructor-form-label">
                                <i class="fas fa-tag"></i> <?= __('category') ?>
                            </label>
                            <select name="category" id="category" class="instructor-form-input instructor-form-select">
                                <option value="general" <?= (isset($_POST['category']) && $_POST['category'] == 'general') ? 'selected' : '' ?>><?= __('general') ?></option>
                                <option value="technology" <?= (isset($_POST['category']) && $_POST['category'] == 'technology') ? 'selected' : '' ?>><?= __('technology') ?></option>
                                <option value="business" <?= (isset($_POST['category']) && $_POST['category'] == 'business') ? 'selected' : '' ?>><?= __('business_management') ?></option>
                                <option value="development" <?= (isset($_POST['category']) && $_POST['category'] == 'development') ? 'selected' : '' ?>><?= __('personal_development') ?></option>
                                <option value="design" <?= (isset($_POST['category']) && $_POST['category'] == 'design') ? 'selected' : '' ?>><?= __('arts_lifestyle') ?></option>
                                <option value="marketing" <?= (isset($_POST['category']) && $_POST['category'] == 'marketing') ? 'selected' : '' ?>><?= __('marketing_communication') ?></option>
                                <option value="languages" <?= (isset($_POST['category']) && $_POST['category'] == 'languages') ? 'selected' : '' ?>><?= __('languages_culture') ?></option>
                                <option value="health" <?= (isset($_POST['category']) && $_POST['category'] == 'health') ? 'selected' : '' ?>><?= __('health_fitness') ?></option>
                                <option value="other" <?= (isset($_POST['category']) && $_POST['category'] == 'other') ? 'selected' : '' ?>><?= __('other') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="instructor-form-group">
                        <label for="description" class="instructor-form-label">
                            <i class="fas fa-align-left"></i> <?= __('description') ?> *
                        </label>
                        <textarea name="description" id="description" rows="6" required
                            class="instructor-form-input instructor-form-textarea"
                            placeholder="<?= __('course_description_placeholder') ?>"
                            style="resize: vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        <div class="character-counter" id="descriptionCounter">0/500</div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="price" class="instructor-form-label">
                                <i class="fas fa-coins"></i> <?= __('price') ?> (<?= __('currency') ?>)
                            </label>
                            <input type="number" name="price" id="price"
                                min="0" step="0.01"
                                class="instructor-form-input"
                                placeholder="0.00"
                                value="<?= $_POST['price'] ?? 0 ?>">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                <?= __('free_course_note') ?>
                            </div>
                        </div>

                        <div class="instructor-form-group">
                            <label for="level" class="instructor-form-label">
                                <i class="fas fa-signal"></i> <?= __('level') ?>
                            </label>
                            <select name="level" id="level" class="instructor-form-input instructor-form-select">
                                <option value="beginner" <?= (isset($_POST['level']) && $_POST['level'] == 'beginner') ? 'selected' : '' ?>><?= __('beginner') ?></option>
                                <option value="intermediate" <?= (isset($_POST['level']) && $_POST['level'] == 'intermediate') ? 'selected' : '' ?>><?= __('intermediate') ?></option>
                                <option value="advanced" <?= (isset($_POST['level']) && $_POST['level'] == 'advanced') ? 'selected' : '' ?>><?= __('advanced') ?></option>
                                <option value="expert" <?= (isset($_POST['level']) && $_POST['level'] == 'expert') ? 'selected' : '' ?>><?= __('expert') ?></option>
                            </select>
                        </div>

                        <div class="instructor-form-group">
                            <label for="duration" class="instructor-form-label">
                                <i class="fas fa-clock"></i> <?= __('estimated_duration') ?>
                            </label>
                            <input type="text" name="duration" id="duration"
                                class="instructor-form-input"
                                placeholder="<?= __('duration_placeholder') ?>"
                                value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>">
                        </div>

                        <div class="instructor-form-group">
                            <label for="status" class="instructor-form-label">
                                <i class="fas fa-toggle-on"></i> <?= __('status') ?>
                            </label>
                            <select name="status" id="status" class="instructor-form-input instructor-form-select">
                                <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>><?= __('draft') ?></option>
                                <option value="published" <?= (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : '' ?>><?= __('published') ?></option>
                                <option value="archived" <?= (isset($_POST['status']) && $_POST['status'] == 'archived') ? 'selected' : '' ?>><?= __('archived') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="instructor-form-group" style="margin-bottom: var(--spacing-6);">
                        <label for="course_image" class="instructor-form-label">
                            <i class="fas fa-image"></i> <?= __('course_cover_image') ?> (JPG/PNG/WEBP, 5MB <?= __('max') ?>)
                        </label>
                        <input type="file" name="course_image" id="course_image" class="instructor-form-input" accept="image/jpeg,image/png,image/webp">
                        <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                            <?= __('course_image_optional_note') ?>
                        </div>
                    </div>

                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            <?= __('create_course') ?>
                        </button>

                        <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            <?= __('cancel') ?>
                        </a>

                        <div style="margin-left: auto; font-size: var(--font-size-sm); color: var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            <?= __('lessons_after_creation_note') ?>
                        </div>
                    </div>

                    <!-- Course Preview -->
                    <div class="course-preview" id="coursePreview">
                        <div class="preview-image" id="previewImage">
                            <i class="fas fa-image" style="font-size: 2rem; color: var(--gray-400);"></i>
                        </div>
                        <div class="preview-content">
                            <h3 id="previewTitle"><?= __('course_title') ?></h3>
                            <p id="previewDescription"><?= __('description') ?></p>
                            <div class="preview-meta">
                                <span id="previewCategory"><i class="fas fa-tag"></i> <?= __('category') ?></span>
                                <span id="previewLevel"><i class="fas fa-signal"></i> <?= __('level') ?></span>
                                <span id="previewPrice"><i class="fas fa-coins"></i> <?= __('price') ?></span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Instructor Statistics -->
            <div class="instructor-table-container" style="margin-top: var(--spacing-8);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-chart-bar"></i> <?= __('your_statistics') ?>
                    </h3>
                </div>

                <div style="padding: var(--spacing-6);">
                    <div class="instructor-cards" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="instructor-card">
                            <div class="instructor-card-header">
                                <div class="instructor-card-icon primary">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                            <div class="instructor-card-title"><?= __('total_courses') ?></div>
                            <div class="instructor-card-value"><?= $total_courses ?></div>
                            <div class="instructor-card-description"><?= __('courses_created') ?></div>
                        </div>

                        <div class="instructor-card">
                            <div class="instructor-card-header">
                                <div class="instructor-card-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="instructor-card-title"><?= __('active_courses') ?></div>
                            <div class="instructor-card-value"><?= $active_courses ?></div>
                            <div class="instructor-card-description"><?= __('published_courses') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_courses') ?>
                </a>

                <a href="add_lesson.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-play-circle"></i>
                    <?= __('add_lesson') ?>
                </a>

                <a href="students.php" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-users"></i>
                    <?= __('manage_students') ?>
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeFormFeatures();
            initializeHamburgerMenu();
            initializeCoursePreview();
        });

        // Form Features
        function initializeFormFeatures() {
            const form = document.getElementById('courseForm');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const priceInput = document.getElementById('price');
            const imageInput = document.getElementById('course_image');

            // Real-time form validation
            setupRealTimeValidation();

            // Character counters
            setupCharacterCounters();

            // Auto-resize textarea
            descriptionInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });

            // Form submission validation
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                showLoadingState();
            });

            // Image preview
            imageInput.addEventListener('change', function() {
                previewImage(this);
            });
        }

        // Real-time validation
        function setupRealTimeValidation() {
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const priceInput = document.getElementById('price');

            titleInput.addEventListener('input', function() {
                validateTitle(this);
            });

            descriptionInput.addEventListener('input', function() {
                validateDescription(this);
            });

            priceInput.addEventListener('input', function() {
                validatePrice(this);
            });
        }

        // Character counters
        function setupCharacterCounters() {
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const titleCounter = document.getElementById('titleCounter');
            const descriptionCounter = document.getElementById('descriptionCounter');

            titleInput.addEventListener('input', function() {
                const length = this.value.length;
                titleCounter.textContent = `${length}/100`;

                if (length > 100) {
                    titleCounter.className = 'character-counter error';
                } else if (length > 80) {
                    titleCounter.className = 'character-counter warning';
                } else {
                    titleCounter.className = 'character-counter';
                }
            });

            descriptionInput.addEventListener('input', function() {
                const length = this.value.length;
                descriptionCounter.textContent = `${length}/500`;

                if (length > 500) {
                    descriptionCounter.className = 'character-counter error';
                } else if (length > 400) {
                    descriptionCounter.className = 'character-counter warning';
                } else {
                    descriptionCounter.className = 'character-counter';
                }
            });
        }

        // Validation functions
        function validateTitle(input) {
            const value = input.value.trim();
            const group = input.closest('.instructor-form-group');
            let message = group.querySelector('.form-validation-message');

            if (!message) {
                message = document.createElement('div');
                message.className = 'form-validation-message';
                group.appendChild(message);
            }

            if (value.length === 0) {
                input.className = 'instructor-form-input error';
                message.className = 'form-validation-message error';
                message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?= __('title_required') ?>';
                return false;
            } else if (value.length < 5) {
                input.className = 'instructor-form-input error';
                message.className = 'form-validation-message error';
                message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?= __('title_min_length') ?>';
                return false;
            } else {
                input.className = 'instructor-form-input success';
                message.className = 'form-validation-message success';
                message.innerHTML = '<i class="fas fa-check"></i> <?= __('title_valid') ?>';
                return true;
            }
        }

        function validateDescription(input) {
            const value = input.value.trim();
            const group = input.closest('.instructor-form-group');
            let message = group.querySelector('.form-validation-message');

            if (!message) {
                message = document.createElement('div');
                message.className = 'form-validation-message';
                group.appendChild(message);
            }

            if (value.length === 0) {
                input.className = 'instructor-form-input error';
                message.className = 'form-validation-message error';
                message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?= __('description_required') ?>';
                return false;
            } else if (value.length < 20) {
                input.className = 'instructor-form-input error';
                message.className = 'form-validation-message error';
                message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?= __('description_min_length') ?>';
                return false;
            } else {
                input.className = 'instructor-form-input success';
                message.className = 'form-validation-message success';
                message.innerHTML = '<i class="fas fa-check"></i> <?= __('description_valid') ?>';
                return true;
            }
        }

        function validatePrice(input) {
            const value = parseFloat(input.value);
            const group = input.closest('.instructor-form-group');
            let message = group.querySelector('.form-validation-message');

            if (!message) {
                message = document.createElement('div');
                message.className = 'form-validation-message';
                group.appendChild(message);
            }

            if (isNaN(value) || value < 0) {
                input.className = 'instructor-form-input error';
                message.className = 'form-validation-message error';
                message.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <?= __('price_invalid') ?>';
                return false;
            } else {
                input.className = 'instructor-form-input success';
                message.className = 'form-validation-message success';
                message.innerHTML = '<i class="fas fa-check"></i> <?= __('price_valid') ?>';
                return true;
            }
        }

        function validateForm() {
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const priceInput = document.getElementById('price');

            const titleValid = validateTitle(titleInput);
            const descriptionValid = validateDescription(descriptionInput);
            const priceValid = validatePrice(priceInput);

            return titleValid && descriptionValid && priceValid;
        }

        // Image preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewImage = document.getElementById('previewImage');
                    previewImage.innerHTML = `<img src="${e.target.result}" alt="Course preview">`;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Loading state
        function showLoadingState() {
            const submitBtn = document.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('creating_course') ?>...';
            submitBtn.disabled = true;
        }

        // Course Preview
        function initializeCoursePreview() {
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const categorySelect = document.getElementById('category');
            const levelSelect = document.getElementById('level');
            const priceInput = document.getElementById('price');
            const preview = document.getElementById('coursePreview');

            const inputs = [titleInput, descriptionInput, categorySelect, levelSelect, priceInput];

            inputs.forEach(input => {
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });
        }

        function updatePreview() {
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const category = document.getElementById('category').selectedOptions[0]?.text || '';
            const level = document.getElementById('level').selectedOptions[0]?.text || '';
            const price = document.getElementById('price').value;
            const preview = document.getElementById('coursePreview');

            if (title || description) {
                document.getElementById('previewTitle').textContent = title || '<?= __('course_title') ?>';
                document.getElementById('previewDescription').textContent = description || '<?= __('description') ?>';
                document.getElementById('previewCategory').innerHTML = `<i class="fas fa-tag"></i> ${category}`;
                document.getElementById('previewLevel').innerHTML = `<i class="fas fa-signal"></i> ${level}`;
                document.getElementById('previewPrice').innerHTML = `<i class="fas fa-coins"></i> ${price || '0'} <?= __('currency') ?>`;

                preview.classList.add('show');
            } else {
                preview.classList.remove('show');
            }
        }

        // Hamburger Menu Functionality
        function initializeHamburgerMenu() {
            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            let isMenuOpen = false;

            function toggleMenu() {
                isMenuOpen = !isMenuOpen;

                if (isMenuOpen) {
                    hamburgerBtn.classList.add('active');
                    sidebar.classList.remove('mobile-hidden');
                    sidebar.classList.add('mobile-visible');
                    mobileOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            function closeMenu() {
                if (isMenuOpen) {
                    isMenuOpen = false;
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleMenu);
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMenu);
            }

            const sidebarLinks = document.querySelectorAll('.instructor-nav-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', closeMenu);
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeMenu();
                    document.body.style.overflow = '';
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isMenuOpen) {
                    closeMenu();
                }
            });

            if (window.innerWidth <= 768) {
                sidebar.classList.add('mobile-hidden');
            }
        }

        // Enhanced mobile interactions
        function setupMobileInteractions() {
            let startX = 0;
            let startY = 0;
            const sidebar = document.getElementById('sidebar');

            if (sidebar) {
                sidebar.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                });

                sidebar.addEventListener('touchmove', function(e) {
                    if (!startX || !startY) return;

                    const diffX = startX - e.touches[0].clientX;
                    const diffY = startY - e.touches[0].clientY;

                    if (Math.abs(diffX) > Math.abs(diffY)) {
                        if (diffX > 50) {
                            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
                            if (hamburgerBtn && hamburgerBtn.classList.contains('active')) {
                                hamburgerBtn.click();
                            }
                        }
                    }

                    startX = 0;
                    startY = 0;
                });
            }
        }

        setupMobileInteractions();
    </script>
</body>

</html>