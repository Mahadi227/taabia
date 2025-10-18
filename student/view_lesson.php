<?php

/**
 * View Lesson Page - Modern LMS
 * Display lesson content with progress tracking
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$lesson_id = $_GET['lesson_id'] ?? 0;
$student_id = $_SESSION['user_id'];

if (!$lesson_id) {
    header('Location: my_courses.php');
    exit;
}

try {
    // Get lesson details
    $stmt_lesson = $pdo->prepare("
        SELECT l.*, c.title as course_title, c.id as course_id,
               u.full_name as instructor_name
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        JOIN users u ON c.instructor_id = u.id
        WHERE l.id = ?
    ");
    $stmt_lesson->execute([$lesson_id]);
    $lesson = $stmt_lesson->fetch();

    if (!$lesson) {
        $_SESSION['error_message'] = __('lesson_not_found') ?? 'Leçon introuvable';
        header('Location: my_courses.php');
        exit;
    }

    $course_id = $lesson['course_id'];

    // Verify enrollment
    $stmt_enrollment = $pdo->prepare("
        SELECT * FROM student_courses 
        WHERE student_id = ? AND course_id = ?
    ");
    $stmt_enrollment->execute([$student_id, $course_id]);
    $enrollment = $stmt_enrollment->fetch();

    if (!$enrollment) {
        $_SESSION['error_message'] = __('not_enrolled') ?? 'Vous n\'êtes pas inscrit à ce cours';
        header('Location: enroll.php?course_id=' . $course_id);
        exit;
    }

    // Track lesson progress
    try {
        $stmt_check = $pdo->prepare("
            SELECT * FROM lesson_progress 
            WHERE lesson_id = ? AND student_id = ?
        ");
        $stmt_check->execute([$lesson_id, $student_id]);
        $existing_progress = $stmt_check->fetch();

        if (!$existing_progress) {
            // Create progress record
            $stmt_insert = $pdo->prepare("
                INSERT INTO lesson_progress (lesson_id, student_id, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
            ");
            $stmt_insert->execute([$lesson_id, $student_id]);
            error_log("Created lesson_progress record for student $student_id, lesson $lesson_id");
        } else {
            // Update progress
            $stmt_update = $pdo->prepare("
                UPDATE lesson_progress 
                SET updated_at = NOW() 
                WHERE lesson_id = ? AND student_id = ?
            ");
            $stmt_update->execute([$lesson_id, $student_id]);
        }
    } catch (PDOException $e) {
        error_log("Error tracking lesson progress: " . $e->getMessage());
    }

    // Get all lessons in this course
    $stmt_all_lessons = $pdo->prepare("
        SELECT l.id, l.title, l.order_index
        FROM lessons l
        WHERE l.course_id = ?
        ORDER BY l.order_index ASC, l.id ASC
    ");
    $stmt_all_lessons->execute([$course_id]);
    $all_lessons = $stmt_all_lessons->fetchAll();

    // Find previous and next lessons
    $current_index = null;
    foreach ($all_lessons as $index => $l) {
        if ($l['id'] == $lesson_id) {
            $current_index = $index;
            break;
        }
    }

    $prev_lesson = $current_index > 0 ? $all_lessons[$current_index - 1] : null;
    $next_lesson = $current_index < count($all_lessons) - 1 ? $all_lessons[$current_index + 1] : null;
} catch (PDOException $e) {
    error_log("Error in view_lesson: " . $e->getMessage());
    header('Location: my_courses.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lesson['title']) ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #000;
            color: white;
        }

        .lesson-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar */
        .top-bar {
            background: rgba(0, 0, 0, 0.95);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .course-info h2 {
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
        }

        .course-info p {
            font-size: 0.875rem;
            opacity: 0.7;
        }

        .top-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Video Player / Content Area */
        .content-area {
            flex: 1;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .video-player {
            width: 100%;
            max-width: 1400px;
            aspect-ratio: 16/9;
        }

        .video-player video {
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }

        .text-content {
            max-width: 900px;
            padding: 3rem;
            color: white;
            line-height: 1.8;
        }

        .text-content h1 {
            font-size: 2.5rem;
            margin-bottom: 2rem;
        }

        .text-content p {
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
        }

        /* Bottom Navigation */
        .bottom-nav {
            background: rgba(0, 0, 0, 0.95);
            padding: 1.5rem 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-button {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1.5rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .nav-button.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .nav-button.primary:hover {
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .nav-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .completion-notice {
            background: linear-gradient(135deg, var(--success), #059669);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        /* PDF Viewer */
        .pdf-viewer {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .bottom-nav {
                flex-direction: column;
                gap: 1rem;
            }

            .text-content {
                padding: 2rem 1.5rem;
            }

            .text-content h1 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>

<body>
    <div class="lesson-container">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="course-info">
                <h2><?= htmlspecialchars($lesson['course_title']) ?></h2>
                <p><?= htmlspecialchars($lesson['title']) ?></p>
            </div>

            <div class="top-actions">
                <a href="view_course.php?course_id=<?= $course_id ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_course') ?? 'Retour au cours' ?>
                </a>
                <a href="my_courses.php" class="btn btn-secondary">
                    <i class="fas fa-th-large"></i>
                    <?= __('my_courses') ?? 'Mes cours' ?>
                </a>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <?php
            $content_type = strtolower($lesson['content_type'] ?? 'video');

            if ($content_type === 'video' && !empty($lesson['video_url'])):
            ?>
                <!-- Video Player -->
                <div class="video-player">
                    <video controls autoplay>
                        <source src="<?= htmlspecialchars($lesson['video_url']) ?>" type="video/mp4">
                        <?= __('browser_no_support') ?? 'Votre navigateur ne supporte pas la vidéo' ?>
                    </video>
                </div>

            <?php elseif ($content_type === 'pdf' && !empty($lesson['file_url'])): ?>
                <!-- PDF Viewer -->
                <iframe src="<?= htmlspecialchars($lesson['file_url']) ?>" class="pdf-viewer"></iframe>

            <?php elseif ($content_type === 'text' || !empty($lesson['content'])): ?>
                <!-- Text Content -->
                <div class="text-content">
                    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
                    <div>
                        <?= nl2br(htmlspecialchars($lesson['content'] ?? '')) ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- No Content Available -->
                <div class="text-content">
                    <h1><?= htmlspecialchars($lesson['title']) ?></h1>
                    <p style="opacity: 0.7;">
                        <i class="fas fa-info-circle"></i>
                        <?= __('content_not_available') ?? 'Contenu de la leçon non disponible pour le moment' ?>
                    </p>
                    <p>
                        <?= __('contact_instructor') ?? 'Contactez l\'instructeur pour plus d\'informations' ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Navigation -->
        <div class="bottom-nav">
            <?php if ($prev_lesson): ?>
                <a href="view_lesson.php?lesson_id=<?= $prev_lesson['id'] ?>" class="nav-button">
                    <i class="fas fa-chevron-left"></i>
                    <div>
                        <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('previous') ?? 'Précédent' ?></div>
                        <div><?= htmlspecialchars($prev_lesson['title']) ?></div>
                    </div>
                </a>
            <?php else: ?>
                <div class="nav-button disabled">
                    <i class="fas fa-chevron-left"></i>
                    <div><?= __('first_lesson') ?? 'Première leçon' ?></div>
                </div>
            <?php endif; ?>

            <div class="completion-notice">
                <i class="fas fa-check-circle"></i>
                <?= __('lesson_viewed') ?? 'Leçon consultée' ?> •
                <?= __('progress_saved') ?? 'Progression enregistrée' ?>
            </div>

            <?php if ($next_lesson): ?>
                <a href="view_lesson.php?lesson_id=<?= $next_lesson['id'] ?>" class="nav-button primary">
                    <div>
                        <div style="font-size: 0.75rem; opacity: 0.9;"><?= __('next') ?? 'Suivant' ?></div>
                        <div><?= htmlspecialchars($next_lesson['title']) ?></div>
                    </div>
                    <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <a href="view_course.php?course_id=<?= $course_id ?>" class="nav-button primary">
                    <div><?= __('back_to_course') ?? 'Retour au cours' ?></div>
                    <i class="fas fa-check-circle"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Track video progress
        const video = document.querySelector('video');
        if (video) {
            let hasStarted = false;
            let watchedPercentage = 0;

            video.addEventListener('play', function() {
                if (!hasStarted) {
                    hasStarted = true;
                    console.log('Video started');
                }
            });

            video.addEventListener('timeupdate', function() {
                if (video.duration > 0) {
                    watchedPercentage = (video.currentTime / video.duration) * 100;

                    // Mark as viewed when 80% watched
                    if (watchedPercentage >= 80 && !video.hasAttribute('data-completed')) {
                        video.setAttribute('data-completed', 'true');
                        console.log('Lesson 80% completed');

                        // Optional: Send AJAX to mark as completed
                        // fetch('mark_lesson_complete.php?lesson_id=<?= $lesson_id ?>')
                    }
                }
            });

            video.addEventListener('ended', function() {
                console.log('Video ended - lesson completed');
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Left arrow - previous lesson
            if (e.key === 'ArrowLeft' && <?= $prev_lesson ? 'true' : 'false' ?>) {
                window.location.href = 'view_lesson.php?lesson_id=<?= $prev_lesson['id'] ?? '' ?>';
            }

            // Right arrow - next lesson
            if (e.key === 'ArrowRight' && <?= $next_lesson ? 'true' : 'false' ?>) {
                window.location.href = 'view_lesson.php?lesson_id=<?= $next_lesson['id'] ?? '' ?>';
            }

            // Escape - back to course
            if (e.key === 'Escape') {
                window.location.href = 'view_course.php?course_id=<?= $course_id ?>';
            }
        });

        // Show keyboard shortcuts hint
        console.log('Keyboard shortcuts: ← Previous | → Next | ESC Back to course');
    </script>
</body>

</html>