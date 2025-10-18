<?php

/**
 * Advanced Student Progress Update System
 * Modern, feature-rich progress management for instructors
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';

require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

// Enhanced validation
if (!$student_id || !$course_id) {
    $_SESSION['error_message'] = __('invalid_parameters') ?? 'Paramètres invalides';
    header('Location: students.php');
    exit;
}

$student_id = (int) $student_id;
$course_id = (int) $course_id;

// Verify course belongs to instructor
try {
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch();

    if (!$course) {
        $_SESSION['error_message'] = __('course_not_found') ?? 'Cours non trouvé';
        header('Location: students.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in update_progress: " . $e->getMessage());
    $_SESSION['error_message'] = __('database_error') ?? 'Erreur de base de données';
    header('Location: students.php');
    exit;
}

// Get student and current progress information
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.id as student_id,
            COALESCE(u.fullname, '') as full_name,
            u.email,
            u.profile_image as avatar,
            sc.progress_percent as current_progress,
            sc.enrolled_at,
            sc.completed_at,
            c.title as course_title,
            c.description as course_description
        FROM student_courses sc
        JOIN users u ON sc.student_id = u.id
        JOIN courses c ON sc.course_id = c.id
        WHERE sc.student_id = ? AND sc.course_id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id, $course_id]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        $_SESSION['error_message'] = __('enrollment_not_found') ?? 'Inscription non trouvée';
        header('Location: students.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in update_progress: " . $e->getMessage());
    $_SESSION['error_message'] = __('database_error') ?? 'Erreur de base de données';
    header('Location: students.php');
    exit;
}

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_progress = (int) $_POST['progress'];
    $progress_note = trim($_POST['progress_note'] ?? '');
    $completion_status = $_POST['completion_status'] ?? 'active';

    // Enhanced validation
    if ($new_progress < 0 || $new_progress > 100) {
        $error_message = __('invalid_progress_range') ?? 'La progression doit être entre 0 et 100%';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Update progress - simple approach that should work with any student_courses structure
            $stmt = $pdo->prepare("
                UPDATE student_courses 
                SET progress_percent = ?
                WHERE student_id = ? AND course_id = ?
            ");

            $stmt->execute([
                $new_progress,
                $student_id,
                $course_id
            ]);

            // Try to update completed_at if the column exists
            if ($new_progress >= 100 && $completion_status === 'completed') {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE student_courses 
                        SET completed_at = ?
                        WHERE student_id = ? AND course_id = ?
                    ");
                    $stmt->execute([date('Y-m-d H:i:s'), $student_id, $course_id]);
                } catch (PDOException $e) {
                    // completed_at column doesn't exist, that's okay
                    error_log("completed_at column not available: " . $e->getMessage());
                }
            }

            // Update lesson progress for all lessons in the course (optional)
            try {
                $stmt = $pdo->prepare("
                    SELECT cc.id as lesson_id 
                    FROM course_contents cc 
                    WHERE cc.course_id = ?
                ");
                $stmt->execute([$course_id]);
                $lessons = $stmt->fetchAll();

                // Calculate lesson progress based on overall course progress
                $lesson_progress_percent = $new_progress;

                foreach ($lessons as $lesson) {
                    // Update or insert lesson progress
                    $stmt = $pdo->prepare("
                        INSERT INTO lesson_progress (lesson_id, student_id, progress_percent, last_accessed, is_completed, completion_date)
                        VALUES (?, ?, ?, NOW(), ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        progress_percent = ?,
                        last_accessed = NOW(),
                        is_completed = ?,
                        completion_date = ?
                    ");

                    $is_completed = ($new_progress >= 100) ? 1 : 0;
                    $completion_date = ($new_progress >= 100) ? date('Y-m-d H:i:s') : null;

                    $stmt->execute([
                        $lesson['lesson_id'],
                        $student_id,
                        $lesson_progress_percent,
                        $is_completed,
                        $completion_date,
                        $lesson_progress_percent,
                        $is_completed,
                        $completion_date
                    ]);
                }
            } catch (PDOException $e) {
                // If lesson_progress table doesn't exist or has issues, continue without it
                error_log("Warning: Could not update lesson progress: " . $e->getMessage());
            }

            // Send notification to student if significant progress change
            if (abs($new_progress - $enrollment['current_progress']) >= 10) {
                // Add notification logic here
                $notification_message = "Votre progression dans le cours '{$course['title']}' a été mise à jour à {$new_progress}%";
                // You can implement email notification or in-app notification here
            }

            $pdo->commit();

            $success_message = __('progress_updated_successfully') ?? 'Progression mise à jour avec succès';

            // Refresh enrollment data
            $stmt = $pdo->prepare("
                SELECT 
                    u.id as student_id,
                    COALESCE(u.fullname, '') as full_name,
                    u.email,
                    u.profile_image as avatar,
                    sc.progress_percent as current_progress,
                    sc.enrolled_at,
                    sc.completed_at,
                    c.title as course_title,
                    c.description as course_description
                FROM student_courses sc
                JOIN users u ON sc.student_id = u.id
                JOIN courses c ON sc.course_id = c.id
                WHERE sc.student_id = ? AND sc.course_id = ? AND u.role = 'student'
            ");
            $stmt->execute([$student_id, $course_id]);
            $enrollment = $stmt->fetch();
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Database error updating progress: " . $e->getMessage());
            $error_message = __('update_failed') ?? 'Échec de la mise à jour';

            // Debug: Show the actual error message
            $error_message .= " - " . $e->getMessage();
        }
    }
}

// Get progress history from lesson_progress table
try {
    $stmt = $pdo->prepare("
        SELECT 
            lp.*,
            cc.title as lesson_title,
            cc.content_type,
            u.fullname as instructor_name
        FROM lesson_progress lp
        LEFT JOIN course_contents cc ON lp.lesson_id = cc.id
        LEFT JOIN users u ON ? = u.id
        WHERE lp.student_id = ? AND cc.course_id = ?
        ORDER BY lp.last_accessed DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id, $student_id, $course_id]);
    $progress_history = $stmt->fetchAll();
} catch (PDOException $e) {
    $progress_history = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('update_progress') ?> | TaaBia LMS</title>

    <!-- External Dependencies -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="instructor-styles.css">

    <style>
        :root {
            --primary-color: #2563eb;
            --primary-dark: #1d4ed8;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: var(--dark-color);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        .instructor-layout {
            display: flex;
            min-height: 100vh;
        }

        .instructor-sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .instructor-sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: white;
        }

        .instructor-sidebar-header h2 {
            margin: 0 0 0.5rem 0;
            font-size: 1.25rem;
            font-weight: 700;
        }

        .instructor-sidebar-header p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .instructor-nav {
            padding: 1rem 0;
        }

        .instructor-nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .instructor-nav-item:hover {
            background: #f1f5f9;
            color: #2563eb;
        }

        .instructor-nav-item.active {
            background: #eff6ff;
            color: #2563eb;
            border-left-color: #2563eb;
        }

        .instructor-nav-item i {
            width: 20px;
            text-align: center;
        }

        .instructor-main {
            flex: 1;
            margin-left: 280px;
            background: #f8fafc;
            min-height: 100vh;
        }

        .progress-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .progress-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: var(--radius-xl);
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .progress-header h1 {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .progress-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .progress-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
            gap: 2rem;
        }

        .progress-form-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .progress-form-header {
            background: var(--light-color);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .progress-form-header h2 {
            font-size: 1.25rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }

        .progress-form-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }

        .progress-slider {
            margin: 1rem 0;
        }

        .progress-slider input[type="range"] {
            width: 100%;
            height: 8px;
            border-radius: 4px;
            background: var(--light-color);
            outline: none;
            -webkit-appearance: none;
            appearance: none;
        }

        .progress-slider input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .progress-slider input[type="range"]::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--primary-color);
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .progress-display {
            text-align: center;
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light-color);
            border-radius: var(--radius-md);
        }

        .progress-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 0;
        }

        .progress-label {
            font-size: 0.9rem;
            color: var(--dark-color);
            margin: 0.5rem 0 0 0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: white;
            color: var(--dark-color);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .student-info-card {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }

        .student-info-header {
            background: var(--light-color);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .student-info-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }

        .student-info-body {
            padding: 1.5rem;
        }

        .student-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .student-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .student-name {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0 0 0.25rem 0;
        }

        .student-email {
            color: var(--dark-color);
            font-size: 0.9rem;
            margin: 0 0 1rem 0;
        }

        .current-progress {
            background: var(--light-color);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }

        .current-progress-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary-color);
            margin: 0;
        }

        .current-progress-label {
            font-size: 0.9rem;
            color: var(--dark-color);
            margin: 0.25rem 0 0 0;
        }

        .progress-history {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            margin-top: 2rem;
        }

        .progress-history-header {
            background: var(--light-color);
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .progress-history-header h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark-color);
        }

        .progress-history-body {
            padding: 1.5rem;
        }

        .history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .history-item:last-child {
            border-bottom: none;
        }

        .history-info {
            flex: 1;
        }

        .history-progress {
            font-weight: 700;
            color: var(--primary-color);
        }

        .history-date {
            font-size: 0.8rem;
            color: var(--dark-color);
        }

        .history-note {
            font-size: 0.9rem;
            color: var(--dark-color);
            margin-top: 0.25rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .instructor-layout {
                flex-direction: column;
            }

            .instructor-sidebar {
                position: relative;
                width: 100%;
                height: auto;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .instructor-main {
                margin-left: 0;
            }

            .progress-content {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .progress-container {
                padding: 1rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .progress-header {
                padding: 1.5rem;
            }

            .progress-header h1 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .progress-container {
                padding: 0.5rem;
            }

            .progress-form-body,
            .student-info-body {
                padding: 1rem;
            }

            .progress-header {
                padding: 1rem;
            }

            .progress-header h1 {
                font-size: 1.25rem;
            }
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p><?= __('instructor_space') ?? 'Espace Formateur' ?></p>
            </div>

            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?? 'Dashboard' ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes cours' ?>
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <?= __('new_course') ?? 'Nouveau cours' ?>
                </a>
                <a href="students.php" class="instructor-nav-item active">
                    <i class="fas fa-users"></i>
                    <?= __('my_students') ?? 'Mes étudiants' ?>
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    <?= __('assignments_to_validate') ?? 'Devoirs à valider' ?>
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    <?= __('my_earnings') ?? 'Mes gains' ?>
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('transactions') ?? 'Transactions' ?>
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= __('payments') ?? 'Paiements' ?>
                </a>
                <a href="profile.php" class="instructor-nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="progress-container">
                <!-- Header -->
                <div class="progress-header">
                    <h1>
                        <i class="fas fa-chart-line"></i>
                        <?= __('update_progress') ?>
                    </h1>
                    <p><?= __('update_student_progress_description') ?? 'Mettre à jour la progression de l\'étudiant' ?></p>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <div class="progress-content">
                    <!-- Progress Update Form -->
                    <div class="progress-form-card">
                        <div class="progress-form-header">
                            <h2>
                                <i class="fas fa-edit"></i>
                                <?= __('update_progress') ?>
                            </h2>
                        </div>
                        <div class="progress-form-body">
                            <form method="POST" id="progressForm">
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-percentage"></i>
                                        <?= __('progress_percentage') ?>
                                    </label>

                                    <div class="progress-slider">
                                        <input type="range"
                                            id="progressSlider"
                                            name="progress"
                                            min="0"
                                            max="100"
                                            value="<?= $enrollment['current_progress'] ?>"
                                            oninput="updateProgressDisplay(this.value)">
                                    </div>

                                    <div class="progress-display">
                                        <div class="progress-value" id="progressValue"><?= $enrollment['current_progress'] ?>%</div>
                                        <div class="progress-label"><?= __('current_progress') ?></div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-flag"></i>
                                        <?= __('completion_status') ?>
                                    </label>
                                    <select name="completion_status" class="form-select">
                                        <option value="active" <?= $enrollment['completed_at'] ? '' : 'selected' ?>>
                                            <?= __('active') ?>
                                        </option>
                                        <option value="completed" <?= $enrollment['completed_at'] ? 'selected' : '' ?>>
                                            <?= __('completed') ?>
                                        </option>
                                        <option value="dropped">
                                            <?= __('dropped') ?>
                                        </option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-sticky-note"></i>
                                        <?= __('progress_note') ?>
                                    </label>
                                    <textarea name="progress_note"
                                        class="form-textarea"
                                        placeholder="<?= __('add_progress_note') ?? 'Ajouter une note sur la progression...' ?>"></textarea>
                                </div>

                                <div class="form-actions">
                                    <a href="students.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i>
                                        <?= __('back_to_students') ?>
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        <?= __('update_progress') ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Student Information -->
                    <div class="student-info-card">
                        <div class="student-info-header">
                            <h3>
                                <i class="fas fa-user"></i>
                                <?= __('student_information') ?>
                            </h3>
                        </div>
                        <div class="student-info-body">
                            <div class="student-avatar">
                                <?php if ($enrollment['avatar']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($enrollment['avatar']) ?>"
                                        alt="<?= htmlspecialchars($enrollment['full_name']) ?>">
                                <?php else: ?>
                                    <?= strtoupper(substr($enrollment['full_name'], 0, 2)) ?>
                                <?php endif; ?>
                            </div>

                            <h4 class="student-name"><?= htmlspecialchars($enrollment['full_name']) ?></h4>
                            <p class="student-email"><?= htmlspecialchars($enrollment['email']) ?></p>

                            <div class="current-progress">
                                <div class="current-progress-value"><?= $enrollment['current_progress'] ?>%</div>
                                <div class="current-progress-label"><?= __('current_progress') ?></div>
                            </div>

                            <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--dark-color);">
                                <p><strong><?= __('course') ?>:</strong> <?= htmlspecialchars($enrollment['course_title']) ?></p>
                                <p><strong><?= __('enrolled_on') ?>:</strong> <?= date('d/m/Y', strtotime($enrollment['enrolled_at'])) ?></p>
                                <?php if ($enrollment['completed_at']): ?>
                                    <p><strong><?= __('completed_on') ?>:</strong> <?= date('d/m/Y', strtotime($enrollment['completed_at'])) ?></p>
                                <?php endif; ?>

                                <?php
                                // Get lesson progress summary
                                try {
                                    $stmt = $pdo->prepare("
                                    SELECT 
                                        COUNT(*) as total_lessons,
                                        COUNT(CASE WHEN lp.is_completed = 1 THEN 1 END) as completed_lessons,
                                        AVG(lp.progress_percent) as avg_lesson_progress,
                                        SUM(lp.time_spent) as total_time_spent
                                    FROM course_contents cc
                                    LEFT JOIN lesson_progress lp ON cc.id = lp.lesson_id AND lp.student_id = ?
                                    WHERE cc.course_id = ?
                                ");
                                    $stmt->execute([$student_id, $course_id]);
                                    $lesson_stats = $stmt->fetch();
                                } catch (PDOException $e) {
                                    $lesson_stats = ['total_lessons' => 0, 'completed_lessons' => 0, 'avg_lesson_progress' => 0, 'total_time_spent' => 0];
                                }
                                ?>

                                <div style="margin-top: 1rem; padding: 1rem; background: var(--light-color); border-radius: var(--radius-md);">
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: var(--dark-color);">
                                        <i class="fas fa-chart-bar"></i> Progression des leçons
                                    </h4>
                                    <p style="margin: 0.25rem 0; font-size: 0.8rem;">
                                        <strong>Leçons complétées:</strong> <?= $lesson_stats['completed_lessons'] ?>/<?= $lesson_stats['total_lessons'] ?>
                                    </p>
                                    <p style="margin: 0.25rem 0; font-size: 0.8rem;">
                                        <strong>Progression moyenne:</strong> <?= round($lesson_stats['avg_lesson_progress'], 1) ?>%
                                    </p>
                                    <?php if ($lesson_stats['total_time_spent'] > 0): ?>
                                        <p style="margin: 0.25rem 0; font-size: 0.8rem;">
                                            <strong>Temps total:</strong> <?= gmdate('H:i:s', $lesson_stats['total_time_spent']) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Progress History -->
                <?php if (!empty($progress_history)): ?>
                    <div class="progress-history">
                        <div class="progress-history-header">
                            <h3>
                                <i class="fas fa-history"></i>
                                <?= __('progress_history') ?>
                            </h3>
                        </div>
                        <div class="progress-history-body">
                            <?php foreach ($progress_history as $history): ?>
                                <div class="history-item">
                                    <div class="history-info">
                                        <div class="history-progress">
                                            <?= $history['lesson_title'] ?> - <?= round($history['progress_percent'], 1) ?>%
                                            <?php if ($history['is_completed']): ?>
                                                <span style="color: var(--success-color); font-weight: 600;">
                                                    <i class="fas fa-check-circle"></i> Complété
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="history-date">
                                            <?= date('d/m/Y à H:i', strtotime($history['last_accessed'])) ?>
                                            <?php if ($history['instructor_name']): ?>
                                                par <?= htmlspecialchars($history['instructor_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="history-note">
                                            <i class="fas fa-<?= $history['content_type'] == 'video' ? 'video' : ($history['content_type'] == 'pdf' ? 'file-pdf' : 'file-text') ?>"></i>
                                            <?= ucfirst($history['content_type']) ?>
                                            <?php if ($history['time_spent'] > 0): ?>
                                                - <?= gmdate('H:i:s', $history['time_spent']) ?> de temps passé
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script>
            // Update progress display in real-time
            function updateProgressDisplay(value) {
                document.getElementById('progressValue').textContent = value + '%';

                // Update completion status based on progress
                const completionSelect = document.querySelector('select[name="completion_status"]');
                if (value >= 100) {
                    completionSelect.value = 'completed';
                } else if (value < 100) {
                    completionSelect.value = 'active';
                }
            }

            // Form validation
            document.getElementById('progressForm').addEventListener('submit', function(e) {
                const progress = parseInt(document.getElementById('progressSlider').value);
                const completionStatus = document.querySelector('select[name="completion_status"]').value;

                if (completionStatus === 'completed' && progress < 100) {
                    e.preventDefault();
                    alert('<?= __('completion_requires_100_percent') ?? 'La completion nécessite 100% de progression' ?>');
                    return false;
                }

                if (completionStatus === 'dropped' && progress > 0) {
                    if (!confirm('<?= __('confirm_drop_with_progress') ?? 'Confirmer l\'abandon avec progression?' ?>')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });

            // Auto-save draft (optional feature)
            let autoSaveTimeout;
            document.querySelectorAll('input, select, textarea').forEach(element => {
                element.addEventListener('input', function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(() => {
                        // Auto-save logic could be implemented here
                        console.log('Auto-saving draft...');
                    }, 2000);
                });
            });

            // Progress slider enhancement
            const slider = document.getElementById('progressSlider');
            slider.addEventListener('input', function() {
                const value = this.value;
                const percentage = (value / 100) * 100;

                // Visual feedback
                this.style.background = `linear-gradient(to right, var(--primary-color) 0%, var(--primary-color) ${percentage}%, var(--light-color) ${percentage}%, var(--light-color) 100%)`;
            });

            // Initialize slider background
            slider.dispatchEvent(new Event('input'));
        </script>
    </div>
    </div>
    </div>
</body>

</html>