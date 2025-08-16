<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

try {
    // Récupération du contenu avec vérification d'accès
    $lesson_id = isset($_GET['lesson_id']) ? (int) $_GET['lesson_id'] : 0;

    if (!$lesson_id) {
        header('Location: my_courses.php');
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT l.*, c.title as course_title, c.id as course_id, c.instructor_id,
               u.full_name as instructor_name,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
               (SELECT COUNT(*) FROM student_lessons sl2 
                JOIN lessons l2 ON sl2.lesson_id = l2.id 
                WHERE l2.course_id = c.id AND sl2.student_id = ?) as completed_lessons
        FROM lessons l
        JOIN courses c ON l.course_id = c.id
        JOIN users u ON c.instructor_id = u.id
        JOIN student_courses sc ON c.id = sc.course_id
        WHERE l.id = ? AND sc.student_id = ?
    ");
    $stmt->execute([$student_id, $lesson_id, $student_id]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        header('Location: my_courses.php');
        exit;
    }

    // Mark lesson as started
    $stmtStart = $pdo->prepare("
        INSERT INTO student_lessons (student_id, lesson_id, started_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE started_at = COALESCE(started_at, NOW())
    ");
    $stmtStart->execute([$student_id, $lesson_id]);

    // Get all lessons in this course for navigation
    $stmtNavigation = $pdo->prepare("
        SELECT l.id, l.title, l.order_index,
               CASE WHEN sl.student_id IS NOT NULL THEN 1 ELSE 0 END as is_completed
        FROM lessons l
        JOIN student_courses sc ON l.course_id = sc.course_id
        LEFT JOIN student_lessons sl ON l.id = sl.lesson_id AND sl.student_id = ?
        WHERE sc.student_id = ? AND l.course_id = ?
        ORDER BY l.order_index ASC
    ");
    $stmtNavigation->execute([$student_id, $student_id, $lesson['course_id']]);
    $all_lessons = $stmtNavigation->fetchAll();

    // Find current lesson position
    $current_index = array_search($lesson_id, array_column($all_lessons, 'id'));
    
    $prev_lesson = $current_index > 0 ? $all_lessons[$current_index - 1] : null;
    $next_lesson = $current_index < count($all_lessons) - 1 ? $all_lessons[$current_index + 1] : null;

    // Calculate course progress
    $course_progress = $lesson['total_lessons'] > 0 ? round(($lesson['completed_lessons'] / $lesson['total_lessons']) * 100) : 0;

    // Update course progress if needed
    $stmtUpdateProgress = $pdo->prepare("
        UPDATE student_courses 
        SET progress_percent = ?, last_accessed = NOW() 
        WHERE student_id = ? AND course_id = ?
    ");
    $stmtUpdateProgress->execute([$course_progress, $student_id, $lesson['course_id']]);

} catch (PDOException $e) {
    error_log("Database error in view_content: " . $e->getMessage());
    header('Location: my_courses.php');
    exit;
}

// Handle lesson completion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_complete'])) {
    try {
        $stmtComplete = $pdo->prepare("
            INSERT INTO student_lessons (student_id, lesson_id, completed_at) 
            VALUES (?, ?, NOW()) 
            ON DUPLICATE KEY UPDATE completed_at = NOW()
        ");
        $stmtComplete->execute([$student_id, $lesson_id]);
        
        // Redirect to refresh the page
        header("Location: view_content.php?lesson_id=$lesson_id");
        exit;
    } catch (PDOException $e) {
        error_log("Database error marking lesson complete: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lesson['title']) ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
    <style>
        .content-viewer {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: var(--spacing-6);
        }
        
        .content-header {
            padding: var(--spacing-6);
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        
        .content-body {
            padding: var(--spacing-6);
        }
        
        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            margin-bottom: var(--spacing-4);
        }
        
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            border-radius: 8px;
        }
        
        .pdf-container {
            width: 100%;
            height: 600px;
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: var(--spacing-4);
        }
        
        .text-content {
            line-height: 1.8;
            color: var(--gray-700);
            font-size: 1rem;
        }
        
        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-4);
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
        }
        
        .lesson-progress {
            display: flex;
            align-items: center;
            gap: var(--spacing-4);
            margin-bottom: var(--spacing-4);
        }
        
        .progress-bar {
            flex: 1;
            height: 8px;
            background: var(--gray-200);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s ease;
        }
        
        .lesson-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .lesson-item {
            padding: var(--spacing-3);
            border-bottom: 1px solid var(--gray-100);
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .lesson-item:hover {
            background: var(--gray-50);
        }
        
        .lesson-item.current {
            background: var(--primary-color);
            color: white;
        }
        
        .lesson-item.completed {
            background: var(--success-color);
            color: white;
        }
    </style>
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p>Espace Apprenant</p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    Découvrir les cours
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
                <a href="course_lessons.php" class="student-nav-item active">
                    <i class="fas fa-play-circle"></i>
                    Mes leçons
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Mes achats
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    Mon profil
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= htmlspecialchars($lesson['title']) ?></h1>
                        <p>
                            <a href="view_course.php?course_id=<?= $lesson['course_id'] ?>" style="color: var(--primary-color); text-decoration: none;">
                                <?= htmlspecialchars($lesson['course_title']) ?>
                            </a>
                            • Par <?= htmlspecialchars($lesson['instructor_name']) ?>
                        </p>
                    </div>
                    <div>
                        <a href="view_course.php?course_id=<?= $lesson['course_id'] ?>" class="student-btn student-btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Retour au cours
                        </a>
                    </div>
                </div>
            </div>

            <!-- Course Progress -->
            <div class="student-card" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-4);">
                    <div class="lesson-progress">
                        <span style="font-weight: 600; color: var(--gray-700);">
                            Progression du cours: <?= $course_progress ?>%
                        </span>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $course_progress ?>%;"></div>
                        </div>
                        <span style="font-size: 0.875rem; color: var(--gray-600);">
                            <?= $lesson['completed_lessons'] ?>/<?= $lesson['total_lessons'] ?> leçons
                        </span>
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="student-grid" style="grid-template-columns: 2fr 1fr; gap: var(--spacing-6);">
                <!-- Main Content -->
                <div class="content-viewer">
                    <div class="content-header">
                        <h3 style="margin: 0 0 0.5rem 0; color: var(--gray-900);">
                            <?= htmlspecialchars($lesson['title']) ?>
                        </h3>
                        <div style="display: flex; align-items: center; gap: var(--spacing-4);">
                            <span class="student-badge student-badge-info">
                                <?= ucfirst($lesson['content_type']) ?>
                            </span>
                            <span style="font-size: 0.875rem; color: var(--gray-600);">
                                Leçon <?= $lesson['order_index'] ?> sur <?= $lesson['total_lessons'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="content-body">
                        <?php if ($lesson['content_type'] === 'video'): ?>
                            <?php if ($lesson['file_url']): ?>
                                <div class="video-container">
                                    <?php if (strpos($lesson['file_url'], 'youtube.com') !== false || strpos($lesson['file_url'], 'youtu.be') !== false): ?>
                                        <?php
                                        // Extract YouTube video ID
                                        $video_id = '';
                                        if (preg_match('/youtube\.com\/watch\?v=([^&]+)/', $lesson['file_url'], $matches)) {
                                            $video_id = $matches[1];
                                        } elseif (preg_match('/youtu\.be\/([^?]+)/', $lesson['file_url'], $matches)) {
                                            $video_id = $matches[1];
                                        }
                                        ?>
                                        <?php if ($video_id): ?>
                                            <iframe src="https://www.youtube.com/embed/<?= $video_id ?>?rel=0" allowfullscreen></iframe>
                                        <?php else: ?>
                                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; background: var(--gray-100); border-radius: 8px;">
                                                <a href="<?= htmlspecialchars($lesson['file_url']) ?>" target="_blank" class="student-btn student-btn-primary">
                                                    <i class="fas fa-external-link-alt"></i> Ouvrir la vidéo
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <video controls style="width: 100%; height: 100%; border-radius: 8px;">
                                            <source src="<?= htmlspecialchars($lesson['file_url']) ?>" type="video/mp4">
                                            Votre navigateur ne supporte pas la lecture de vidéos.
                                        </video>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: var(--spacing-8); color: var(--gray-500);">
                                    <i class="fas fa-video" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>Aucune vidéo disponible pour cette leçon.</p>
                                </div>
                            <?php endif; ?>

                        <?php elseif ($lesson['content_type'] === 'pdf'): ?>
                            <?php if ($lesson['file_url']): ?>
                                <div class="pdf-container">
                                    <iframe src="<?= htmlspecialchars($lesson['file_url']) ?>" width="100%" height="100%"></iframe>
                                </div>
                                <div style="text-align: center; margin-bottom: var(--spacing-4);">
                                    <a href="<?= htmlspecialchars($lesson['file_url']) ?>" target="_blank" class="student-btn student-btn-secondary">
                                        <i class="fas fa-download"></i> Télécharger le PDF
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; padding: var(--spacing-8); color: var(--gray-500);">
                                    <i class="fas fa-file-pdf" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>Aucun PDF disponible pour cette leçon.</p>
                                </div>
                            <?php endif; ?>

                        <?php else: ?>
                            <!-- Text content -->
                            <div class="text-content">
                                <?= nl2br(htmlspecialchars($lesson['content'])) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Lesson completion -->
                        <div style="margin-top: var(--spacing-6); padding-top: var(--spacing-4); border-top: 1px solid var(--gray-200);">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="mark_complete" class="student-btn student-btn-success">
                                    <i class="fas fa-check"></i> Marquer comme terminée
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div class="navigation-buttons">
                        <?php if ($prev_lesson): ?>
                            <a href="view_content.php?lesson_id=<?= $prev_lesson['id'] ?>" class="student-btn student-btn-secondary">
                                <i class="fas fa-chevron-left"></i> Leçon précédente
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>

                        <?php if ($next_lesson): ?>
                            <a href="view_content.php?lesson_id=<?= $next_lesson['id'] ?>" class="student-btn student-btn-primary">
                                Leçon suivante <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <div></div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lesson Navigation Sidebar -->
                <div class="student-card">
                    <div style="padding: var(--spacing-4); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-list"></i> Leçons du cours
                        </h3>
                    </div>
                    <div class="lesson-list">
                        <?php foreach ($all_lessons as $nav_lesson): ?>
                            <div class="lesson-item <?= $nav_lesson['id'] == $lesson_id ? 'current' : ($nav_lesson['is_completed'] ? 'completed' : '') ?>" 
                                 onclick="window.location.href='view_content.php?lesson_id=<?= $nav_lesson['id'] ?>'">
                                <div style="display: flex; align-items: center; gap: var(--spacing-3);">
                                    <?php if ($nav_lesson['is_completed']): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success-color);"></i>
                                    <?php elseif ($nav_lesson['id'] == $lesson_id): ?>
                                        <i class="fas fa-play-circle" style="color: var(--primary-color);"></i>
                                    <?php else: ?>
                                        <i class="fas fa-circle" style="color: var(--gray-400);"></i>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 500; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($nav_lesson['title']) ?>
                                        </div>
                                        <div style="font-size: 0.75rem; opacity: 0.7;">
                                            Leçon <?= $nav_lesson['order_index'] ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-scroll to current lesson in sidebar
            const currentLesson = document.querySelector('.lesson-item.current');
            if (currentLesson) {
                currentLesson.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // Add click tracking for video/PDF content
            const videoContainer = document.querySelector('.video-container');
            const pdfContainer = document.querySelector('.pdf-container');
            
            if (videoContainer || pdfContainer) {
                // Mark lesson as completed when user interacts with content
                const markComplete = () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="mark_complete" value="1">';
                    document.body.appendChild(form);
                    form.submit();
                };

                // Add event listeners for content interaction
                if (videoContainer) {
                    videoContainer.addEventListener('click', markComplete);
                }
                if (pdfContainer) {
                    pdfContainer.addEventListener('click', markComplete);
                }
            }

            // Progress bar animation
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const width = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = width;
                }, 500);
            }
        });
    </script>
</body>
</html>
