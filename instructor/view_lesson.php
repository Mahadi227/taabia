<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Check if lesson ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash_message(__('lesson_id_not_specified'), 'error');
    redirect('my_courses.php');
}

$lesson_id = (int)$_GET['id'];
error_log("View lesson: Attempting to view lesson ID: $lesson_id for instructor ID: $instructor_id");

// Fetch lesson from database with ownership verification
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.title as course_title, c.id as course_id,
               c.description as course_description, c.enrolled_students,
               c.rating, c.created_at as course_created
        FROM lessons l 
        JOIN courses c ON l.course_id = c.id 
        WHERE l.id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$lesson_id, $instructor_id]);
    $lesson = $stmt->fetch();

    error_log("View lesson: Lesson found: " . ($lesson ? 'YES' : 'NO'));
    if ($lesson) {
        error_log("View lesson: Lesson title: " . $lesson['title'] . ", Course ID: " . $lesson['course_id']);
    }

    if (!$lesson) {
        error_log("View lesson: No lesson found or no permission for lesson ID: $lesson_id, instructor ID: $instructor_id");
        flash_message(__('lesson_not_found_or_no_permission'), 'error');
        redirect('my_courses.php');
    }

    // Get course ID for navigation
    $course_id = $lesson['course_id'];

    // Get all lessons for this course for navigation
    $lessons_stmt = $pdo->prepare("
        SELECT id, title, content_type, display_order 
        FROM lessons 
        WHERE course_id = ? 
        ORDER BY display_order ASC
    ");
    $lessons_stmt->execute([$course_id]);
    $all_lessons = $lessons_stmt->fetchAll();

    // Find current lesson index for navigation
    $current_index = -1;
    $prev_lesson = null;
    $next_lesson = null;

    foreach ($all_lessons as $index => $l) {
        if ($l['id'] == $lesson_id) {
            $current_index = $index;
            if ($index > 0) {
                $prev_lesson = $all_lessons[$index - 1];
            }
            if ($index < count($all_lessons) - 1) {
                $next_lesson = $all_lessons[$index + 1];
            }
            break;
        }
    }
} catch (PDOException $e) {
    error_log("Database error in view_lesson: " . $e->getMessage());
    flash_message(__('database_error'), 'error');
    redirect('course_lessons.php');
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('view_lesson') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
    <style>
        /* CSS Variables for Consistent Theming */
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary-color: #1e40af;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;

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

            --spacing-1: 0.25rem;
            --spacing-2: 0.5rem;
            --spacing-3: 0.75rem;
            --spacing-4: 1rem;
            --spacing-5: 1.25rem;
            --spacing-6: 1.5rem;
            --spacing-8: 2rem;
            --spacing-10: 2.5rem;
            --spacing-12: 3rem;

            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;

            --border-radius-sm: 0.375rem;
            --border-radius: 0.5rem;
            --border-radius-lg: 0.75rem;
            --border-radius-xl: 1rem;
            --border-radius-2xl: 1.5rem;

            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0f2f5 0%, #e2e8f0 100%);
            color: var(--gray-800);
            line-height: 1.6;
            font-size: var(--font-size-base);
            min-height: 100vh;
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

        /* Hamburger Menu Button */
        .hamburger-menu-btn {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary-color);
            border: none;
            border-radius: 8px;
            width: 50px;
            height: 50px;
            cursor: pointer;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 4px;
            box-shadow: var(--shadow-lg);
            transition: all 0.3s ease;
        }

        .hamburger-line {
            width: 24px;
            height: 3px;
            background: white;
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        .hamburger-menu-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
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

        /* Enhanced Sidebar */
        .sidebar {
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            padding: var(--spacing-8) var(--spacing-6);
            box-shadow: var(--shadow-xl);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .sidebar h2 {
            margin-bottom: var(--spacing-8);
            text-align: center;
            font-size: var(--font-size-2xl);
            font-weight: 700;
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-3);
        }

        .sidebar h2::before {
            content: '👨‍🏫';
            font-size: var(--font-size-3xl);
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }

        .sidebar a {
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
            color: white;
            text-decoration: none;
            padding: var(--spacing-4) var(--spacing-5);
            margin-bottom: var(--spacing-2);
            border-radius: var(--border-radius-lg);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            font-weight: 500;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .sidebar a.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: var(--shadow-lg);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .sidebar a i {
            width: 20px;
            text-align: center;
            font-size: var(--font-size-lg);
        }

        /* Enhanced Main Content */
        .main-content {
            margin-left: 280px;
            padding: var(--spacing-8);
            min-height: 100vh;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Professional Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-8);
            background: white;
            padding: var(--spacing-6) var(--spacing-8);
            border-radius: var(--border-radius-2xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-200);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-dark));
        }

        .header h1 {
            color: var(--gray-800);
            font-size: var(--font-size-3xl);
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
        }

        .header h1::before {
            content: '📚';
            font-size: var(--font-size-2xl);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: var(--spacing-3) var(--spacing-5);
            text-decoration: none;
            border-radius: var(--border-radius-xl);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            box-shadow: var(--shadow-md);
            border: none;
            cursor: pointer;
            font-size: var(--font-size-sm);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--secondary-color) 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--gray-200) 100%);
            color: var(--gray-700);
            border: 1px solid var(--gray-300);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--gray-200) 0%, var(--gray-300) 100%);
            color: var(--gray-800);
        }

        /* Lesson Content Container */
        .lesson-content-container {
            background: white;
            border-radius: var(--border-radius-2xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        /* Lesson Header */
        .lesson-header {
            padding: var(--spacing-8);
            background: linear-gradient(135deg, var(--gray-50) 0%, white 100%);
            border-bottom: 1px solid var(--gray-200);
            position: relative;
        }

        .lesson-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--success-color), var(--info-color));
        }

        .lesson-title {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--spacing-4);
            display: flex;
            align-items: center;
            gap: var(--spacing-3);
        }

        .lesson-title::before {
            content: '📖';
            font-size: var(--font-size-2xl);
        }

        .lesson-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-6);
            margin-bottom: var(--spacing-4);
        }

        .lesson-meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            color: var(--gray-600);
            font-size: var(--font-size-sm);
            background: rgba(255, 255, 255, 0.7);
            padding: var(--spacing-2) var(--spacing-3);
            border-radius: var(--border-radius-lg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--gray-200);
        }

        .lesson-meta-item i {
            color: var(--gray-400);
            font-size: var(--font-size-base);
        }

        .lesson-type-badge {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-2) var(--spacing-4);
            border-radius: var(--border-radius-xl);
            font-size: var(--font-size-xs);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border: 2px solid;
        }

        .type-text {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border-color: #93c5fd;
        }

        .type-video {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border-color: #fbbf24;
        }

        .type-pdf {
            background: linear-gradient(135deg, #fecaca 0%, #fca5a5 100%);
            color: #dc2626;
            border-color: #f87171;
        }

        /* Lesson Body */
        .lesson-body {
            padding: var(--spacing-8);
        }

        .lesson-description {
            color: var(--gray-600);
            font-size: var(--font-size-lg);
            line-height: 1.7;
            margin-bottom: var(--spacing-8);
            background: var(--gray-50);
            padding: var(--spacing-6);
            border-radius: var(--border-radius-xl);
            border-left: 4px solid var(--primary-color);
        }

        .lesson-media {
            margin: var(--spacing-8) 0;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .lesson-media video,
        .lesson-media iframe,
        .lesson-media embed {
            width: 100%;
            min-height: 400px;
            border: none;
        }

        .lesson-file-download {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-4) var(--spacing-6);
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius-xl);
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-md);
        }

        .lesson-file-download:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, #059669 0%, var(--success-color) 100%);
        }

        /* Navigation Controls */
        .lesson-navigation {
            padding: var(--spacing-6);
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-2);
            padding: var(--spacing-3) var(--spacing-5);
            background: white;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--border-radius-xl);
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
        }

        .nav-btn:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .lesson-progress {
            display: flex;
            align-items: center;
            gap: var(--spacing-2);
            color: var(--gray-600);
            font-size: var(--font-size-sm);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .hamburger-menu-btn {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: var(--spacing-6);
            }

            .header {
                flex-direction: column;
                gap: var(--spacing-4);
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
                justify-content: space-between;
            }

            .lesson-meta {
                flex-direction: column;
                gap: var(--spacing-3);
            }

            .lesson-navigation {
                flex-direction: column;
                gap: var(--spacing-4);
            }
        }

        @media (max-width: 480px) {
            .hamburger-menu-btn {
                top: 15px;
                left: 15px;
                width: 45px;
                height: 45px;
            }

            .main-content {
                padding: var(--spacing-4);
            }

            .lesson-header,
            .lesson-body {
                padding: var(--spacing-6);
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Hamburger Menu Button -->
    <button class="hamburger-menu-btn" id="hamburgerMenuBtn" aria-label="Toggle navigation">
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
        <span class="hamburger-line"></span>
    </button>

    <!-- Sidebar Navigation -->
    <div class="sidebar" id="sidebar">
        <h2><?= __('instructor_space') ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="my_courses.php"><i class="fas fa-book"></i> <?= __('my_courses') ?></a>
        <a href="add_course.php"><i class="fas fa-plus"></i> <?= __('new_course') ?></a>
        <a href="students.php"><i class="fas fa-users"></i> <?= __('my_students') ?></a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> <?= __('payouts') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <h1><?= __('view_lesson') ?></h1>
            <div class="header-actions">
                <?php include '../includes/instructor_language_switcher.php'; ?>
                <a href="course_lessons.php?course_id=<?= $course_id ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <?= __('back_to_lessons') ?>
                </a>
                <a href="lesson_edit.php?id=<?= $lesson_id ?>" class="btn">
                    <i class="fas fa-edit"></i> <?= __('edit_lesson') ?>
                </a>
            </div>
        </div>

        <!-- Lesson Content -->
        <div class="lesson-content-container">
            <!-- Lesson Header -->
            <div class="lesson-header">
                <h2 class="lesson-title"><?= htmlspecialchars($lesson['title'] ?? 'Untitled Lesson') ?></h2>

                <div class="lesson-meta">
                    <div class="lesson-meta-item">
                        <i class="fas fa-book"></i>
                        <span><?= htmlspecialchars($lesson['course_title'] ?? 'Unknown Course') ?></span>
                    </div>

                    <div class="lesson-type-badge type-<?= $lesson['content_type'] ?? 'text' ?>">
                        <i class="fas fa-<?= ($lesson['content_type'] ?? 'text') === 'video' ? 'play' : (($lesson['content_type'] ?? 'text') === 'pdf' ? 'file-pdf' : 'file-text') ?>"></i>
                        <?= ucfirst($lesson['content_type'] ?? 'text') ?>
                    </div>

                    <div class="lesson-meta-item">
                        <i class="fas fa-sort-numeric-up"></i>
                        <span><?= __('order') ?>: <?= $lesson['display_order'] ?? $lesson['order_index'] ?? 'N/A' ?></span>
                    </div>

                    <?php if (!empty($lesson['duration'])): ?>
                        <div class="lesson-meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?= $lesson['duration'] ?> <?= __('minutes') ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lesson Body -->
            <div class="lesson-body">
                <?php if (!empty($lesson['content'])): ?>
                    <div class="lesson-description">
                        <?= nl2br(htmlspecialchars($lesson['content'])) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($lesson['file_url'])): ?>
                    <div class="lesson-media">
                        <?php
                        $file_url = $lesson['file_url'];
                        $file_ext = pathinfo($file_url, PATHINFO_EXTENSION);

                        if (in_array($file_ext, ['mp4', 'webm', 'avi', 'mov'])) {
                            echo "<video controls src=\"$file_url\" preload=\"metadata\"></video>";
                        } elseif (in_array($file_ext, ['pdf'])) {
                            echo "<embed src=\"$file_url\" type=\"application/pdf\" />";
                        } elseif (strpos($file_url, 'youtube.com') !== false || strpos($file_url, 'youtu.be') !== false) {
                            // Convert YouTube URL to embed
                            $video_id = '';
                            if (strpos($file_url, 'youtube.com/watch?v=') !== false) {
                                $video_id = substr($file_url, strpos($file_url, 'v=') + 2);
                            } elseif (strpos($file_url, 'youtu.be/') !== false) {
                                $video_id = substr($file_url, strpos($file_url, 'youtu.be/') + 9);
                            }
                            if ($video_id) {
                                echo "<iframe width=\"100%\" height=\"400\" src=\"https://www.youtube.com/embed/$video_id\" frameborder=\"0\" allowfullscreen></iframe>";
                            } else {
                                echo "<a class='lesson-file-download' href=\"$file_url\" target=\"_blank\"><i class=\"fas fa-external-link-alt\"></i> " . __('view_video') . "</a>";
                            }
                        } else {
                            echo "<a class='lesson-file-download' href=\"$file_url\" target=\"_blank\"><i class=\"fas fa-download\"></i> " . __('view_file') . "</a>";
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lesson Navigation -->
            <div class="lesson-navigation">
                <div class="lesson-progress">
                    <span><?= __('lesson') ?> <?= $current_index + 1 ?> <?= __('of') ?> <?= count($all_lessons) ?></span>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <?php if ($prev_lesson): ?>
                        <a href="view_lesson.php?id=<?= $prev_lesson['id'] ?>" class="nav-btn">
                            <i class="fas fa-chevron-left"></i>
                            <span><?= __('previous_lesson') ?></span>
                        </a>
                    <?php else: ?>
                        <span class="nav-btn" disabled>
                            <i class="fas fa-chevron-left"></i>
                            <span><?= __('previous_lesson') ?></span>
                        </span>
                    <?php endif; ?>

                    <?php if ($next_lesson): ?>
                        <a href="view_lesson.php?id=<?= $next_lesson['id'] ?>" class="nav-btn">
                            <span><?= __('next_lesson') ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="nav-btn" disabled>
                            <span><?= __('next_lesson') ?></span>
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hamburger Menu Functionality
        function initializeHamburgerMenu() {
            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const body = document.body;

            // Toggle menu function
            function toggleMenu() {
                const isOpen = sidebar.classList.contains('open');

                if (isOpen) {
                    closeMenu();
                } else {
                    openMenu();
                }
            }

            // Open menu function
            function openMenu() {
                sidebar.classList.add('open');
                mobileOverlay.classList.add('active');
                hamburgerBtn.classList.add('active');
                body.style.overflow = 'hidden';
            }

            // Close menu function
            function closeMenu() {
                sidebar.classList.remove('open');
                mobileOverlay.classList.remove('active');
                hamburgerBtn.classList.remove('active');
                body.style.overflow = '';
            }

            // Event listeners
            hamburgerBtn.addEventListener('click', toggleMenu);
            mobileOverlay.addEventListener('click', closeMenu);

            // Close menu when clicking on sidebar links
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', closeMenu);
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    closeMenu();
                }
            });

            // Handle escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    closeMenu();
                }
            });

            // Handle touch gestures for mobile
            let startX = 0;
            let startY = 0;

            document.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientY;
            });

            document.addEventListener('touchmove', (e) => {
                if (!startX || !startY) return;

                const diffX = startX - e.touches[0].clientX;
                const diffY = startY - e.touches[0].clientY;

                if (Math.abs(diffX) > Math.abs(diffY)) {
                    if (diffX > 50) {
                        if (sidebar.classList.contains('open')) {
                            closeMenu();
                        }
                    } else if (diffX < -50) {
                        if (!sidebar.classList.contains('open') && window.innerWidth <= 768) {
                            openMenu();
                        }
                    }
                }

                startX = 0;
                startY = 0;
            });
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', initializeHamburgerMenu);

        // Re-initialize if needed
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeHamburgerMenu);
        } else {
            initializeHamburgerMenu();
        }

        // Enhanced video controls
        document.addEventListener('DOMContentLoaded', function() {
            const videos = document.querySelectorAll('video');
            videos.forEach(video => {
                // Add custom controls enhancement
                video.addEventListener('loadedmetadata', function() {
                    console.log('Video loaded:', this.duration, 'seconds');
                });

                // Add keyboard shortcuts
                video.addEventListener('keydown', function(e) {
                    if (e.code === 'Space') {
                        e.preventDefault();
                        this.paused ? this.play() : this.pause();
                    }
                });
            });
        });
    </script>
</body>

</html>