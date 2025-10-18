<?php

/**
 * View Course Page - Modern LMS
 * Detailed course view with lessons and progress tracking
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$course_id = $_GET['course_id'] ?? 0;
$student_id = $_SESSION['user_id'];

if (!$course_id) {
    header('Location: my_courses.php');
    exit;
}

try {
    // Check if student is enrolled
    $stmt_check = $pdo->prepare("SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmt_check->execute([$student_id, $course_id]);
    $enrollment = $stmt_check->fetch();

    if (!$enrollment) {
        // Not enrolled - redirect to enrollment page
        header("Location: enroll.php?course_id=$course_id");
        exit;
    }

    // Get course details
    $stmt_course = $pdo->prepare("
        SELECT c.*, u.full_name AS instructor_name, u.email AS instructor_email, u.id as instructor_id
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.id = ?
    ");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch();

    if (!$course) {
        header('Location: my_courses.php');
        exit;
    }

    // Get lessons with progress
    $stmt_lessons = $pdo->prepare("
        SELECT l.*
        FROM lessons l
        WHERE l.course_id = ?
        ORDER BY l.order_index ASC, l.id ASC
    ");
    $stmt_lessons->execute([$course_id]);
    $lessons = $stmt_lessons->fetchAll();

    // Check which lessons are viewed
    foreach ($lessons as &$lesson) {
        try {
            $stmt_progress = $pdo->prepare("
                SELECT * FROM lesson_progress 
                WHERE lesson_id = ? AND student_id = ?
            ");
            $stmt_progress->execute([$lesson['id'], $student_id]);
            $progress = $stmt_progress->fetch();

            $lesson['is_viewed'] = $progress ? true : false;
            $lesson['viewed_at'] = $progress ? ($progress['updated_at'] ?? $progress['created_at']) : null;
        } catch (PDOException $e) {
            $lesson['is_viewed'] = false;
            $lesson['viewed_at'] = null;
        }
    }
    unset($lesson);

    // Calculate statistics
    $total_lessons = count($lessons);
    $viewed_lessons = count(array_filter($lessons, fn($l) => $l['is_viewed']));
    $progress_percent = $total_lessons > 0 ? round(($viewed_lessons / $total_lessons) * 100) : 0;

    // Find next lesson
    $next_lesson = null;
    foreach ($lessons as $lesson) {
        if (!$lesson['is_viewed']) {
            $next_lesson = $lesson;
            break;
        }
    }

    // Get course certificate if completed
    $certificate = null;
    if ($progress_percent >= 100) {
        try {
            $stmt_cert = $pdo->prepare("
                SELECT * FROM course_certificates 
                WHERE student_id = ? AND course_id = ?
            ");
            $stmt_cert->execute([$student_id, $course_id]);
            $certificate = $stmt_cert->fetch();
        } catch (PDOException $e) {
            // Certificate table might not exist
        }
    }

    // Update last accessed
    try {
        $stmt_update = $pdo->prepare("
            UPDATE student_courses 
            SET last_accessed = NOW() 
            WHERE student_id = ? AND course_id = ?
        ");
        $stmt_update->execute([$student_id, $course_id]);
    } catch (PDOException $e) {
        // Column might not exist
    }
} catch (PDOException $e) {
    error_log("Error in view_course: " . $e->getMessage());
    header('Location: my_courses.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
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
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            margin-right: 0.75rem;
        }

        .nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), transparent);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Course Hero */
        .course-hero {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .hero-image {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 5rem;
            position: relative;
        }

        .hero-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .progress-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.8);
            padding: 1.5rem;
            color: white;
        }

        .progress-bar-container {
            background: rgba(255, 255, 255, 0.2);
            height: 8px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--success), #059669);
            border-radius: 10px;
            transition: width 1s ease;
        }

        .hero-content {
            padding: 2rem;
        }

        .hero-content h1 {
            color: var(--gray-900);
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .instructor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .instructor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        /* Lesson List */
        .lessons-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }

        .section-header h3 {
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 700;
        }

        .lesson-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            transition: background 0.3s ease;
        }

        .lesson-item:hover {
            background: var(--gray-50);
        }

        .lesson-item.completed {
            background: linear-gradient(90deg, rgba(16, 185, 129, 0.05), transparent);
        }

        .lesson-number {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.125rem;
            flex-shrink: 0;
        }

        .lesson-number.pending {
            background: var(--gray-200);
            color: var(--gray-600);
        }

        .lesson-number.completed {
            background: var(--success);
            color: white;
        }

        .lesson-info {
            flex: 1;
        }

        .lesson-title {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            font-size: 1.05rem;
        }

        .lesson-meta {
            font-size: 0.875rem;
            color: var(--gray-600);
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-body {
            padding: 2rem;
        }

        .next-lesson-cta {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .certificate-banner {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .hero-image {
                height: 200px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
            </div>

            <nav class="nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item active">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item">
                    <i class="fas fa-compass"></i>
                    <?= __('discover_courses') ?? 'Découvrir' ?>
                </a>
                <a href="course_lessons.php" class="nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?? 'Mes Leçons' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 2rem; display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <!-- Certificate Banner -->
            <?php if ($certificate): ?>
                <div class="certificate-banner">
                    <i class="fas fa-trophy" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h2 style="margin-bottom: 0.5rem;"><?= __('congratulations') ?? 'Félicitations !' ?></h2>
                    <p style="margin-bottom: 1.5rem;"><?= __('course_completed') ?? 'Vous avez terminé ce cours avec succès' ?></p>
                    <a href="../instructor/view_certificate.php?id=<?= $certificate['id'] ?>"
                        class="btn btn-primary"
                        style="background: white; color: #f59e0b;"
                        target="_blank">
                        <i class="fas fa-certificate"></i> <?= __('view_certificate') ?? 'Voir mon certificat' ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Next Lesson CTA -->
            <?php if ($next_lesson && !$certificate): ?>
                <div class="next-lesson-cta">
                    <div>
                        <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;"><?= __('continue_learning') ?? 'Continuer l\'apprentissage' ?></h3>
                        <p style="opacity: 0.9;"><?= __('next') ?? 'Suivant' ?>: <?= htmlspecialchars($next_lesson['title']) ?></p>
                    </div>
                    <a href="view_lesson.php?lesson_id=<?= $next_lesson['id'] ?>" class="btn btn-success" style="font-size: 1.125rem; padding: 1rem 2rem;">
                        <i class="fas fa-play-circle"></i> <?= __('start_lesson') ?? 'Commencer la leçon' ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Course Hero -->
            <div class="course-hero">
                <div class="hero-image">
                    <?php if (!empty($course['image_url'])): ?>
                        <img src="../uploads/<?= htmlspecialchars($course['image_url']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                    <?php else: ?>
                        <i class="fas fa-book-open"></i>
                    <?php endif; ?>

                    <div class="progress-overlay">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span><?= __('your_progress') ?? 'Votre progression' ?></span>
                            <span><strong><?= $progress_percent ?>%</strong></span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" data-progress="<?= $progress_percent ?>"></div>
                        </div>
                        <div style="font-size: 0.875rem; margin-top: 0.5rem; opacity: 0.9;">
                            <?= $viewed_lessons ?> / <?= $total_lessons ?> <?= __('lessons_completed') ?? 'leçons complétées' ?>
                        </div>
                    </div>
                </div>

                <div class="hero-content">
                    <h1><?= htmlspecialchars($course['title']) ?></h1>

                    <div class="instructor-info">
                        <div class="instructor-avatar">
                            <?= strtoupper(substr($course['instructor_name'], 0, 1)) ?>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: var(--gray-900);">
                                <?= htmlspecialchars($course['instructor_name']) ?>
                            </div>
                            <div style="font-size: 0.875rem; color: var(--gray-600);">
                                <?= __('course_instructor') ?? 'Instructeur du cours' ?>
                            </div>
                        </div>
                        <a href="send_message.php?instructor_id=<?= $course['instructor_id'] ?>&course_id=<?= $course_id ?>"
                            class="btn btn-secondary" style="margin-left: auto;">
                            <i class="fas fa-envelope"></i> <?= __('contact') ?? 'Contacter' ?>
                        </a>
                    </div>

                    <p style="color: var(--gray-700); line-height: 1.8;">
                        <?= nl2br(htmlspecialchars($course['description'] ?? '')) ?>
                    </p>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-label"><?= __('progress') ?? 'Progression' ?></div>
                    <div class="stat-value"><?= $progress_percent ?>%</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-label"><?= __('completed_lessons') ?? 'Leçons Vues' ?></div>
                    <div class="stat-value"><?= $viewed_lessons ?>/<?= $total_lessons ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-label"><?= __('remaining') ?? 'Restantes' ?></div>
                    <div class="stat-value"><?= $total_lessons - $viewed_lessons ?></div>
                </div>

                <?php if ($progress_percent >= 100): ?>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-label"><?= __('status') ?? 'Statut' ?></div>
                        <div class="stat-value" style="font-size: 1.25rem;">
                            <i class="fas fa-check-circle" style="color: var(--success);"></i> <?= __('completed') ?? 'Terminé' ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Lessons List -->
            <div class="lessons-container">
                <div class="section-header">
                    <h3>
                        <i class="fas fa-list"></i> <?= __('course_content') ?? 'Contenu du Cours' ?>
                        <span style="font-weight: 400; color: var(--gray-600); font-size: 1rem;">
                            (<?= $total_lessons ?> <?= __('lessons') ?? 'leçons' ?>)
                        </span>
                    </h3>
                </div>

                <?php if (!empty($lessons)): ?>
                    <?php foreach ($lessons as $index => $lesson): ?>
                        <div class="lesson-item <?= $lesson['is_viewed'] ? 'completed' : '' ?>">
                            <div class="lesson-number <?= $lesson['is_viewed'] ? 'completed' : 'pending' ?>">
                                <?php if ($lesson['is_viewed']): ?>
                                    <i class="fas fa-check"></i>
                                <?php else: ?>
                                    <?= $index + 1 ?>
                                <?php endif; ?>
                            </div>

                            <div class="lesson-info">
                                <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                                <div class="lesson-meta">
                                    <i class="fas fa-<?= match ($lesson['content_type'] ?? 'video') {
                                                            'video' => 'video',
                                                            'text' => 'file-alt',
                                                            'pdf' => 'file-pdf',
                                                            'quiz' => 'question-circle',
                                                            default => 'play-circle'
                                                        } ?>"></i>
                                    <?= ucfirst($lesson['content_type'] ?? 'Vidéo') ?>
                                    <?php if ($lesson['is_viewed'] && $lesson['viewed_at']): ?>
                                        • <?= __('viewed_on') ?? 'Vue le' ?> <?= date('d/m/Y', strtotime($lesson['viewed_at'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($lesson['is_viewed']): ?>
                                <a href="view_lesson.php?lesson_id=<?= $lesson['id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> <?= __('review') ?? 'Revoir' ?>
                                </a>
                            <?php else: ?>
                                <a href="view_lesson.php?lesson_id=<?= $lesson['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-play"></i> <?= __('start') ?? 'Commencer' ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3><?= __('no_lessons_yet') ?? 'Aucune leçon disponible' ?></h3>
                        <p><?= __('check_back_soon') ?? 'Revenez bientôt pour du nouveau contenu' ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div style="display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap;">
                <a href="my_courses.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> <?= __('back_to_courses') ?? 'Retour aux cours' ?>
                </a>
                <?php if ($next_lesson): ?>
                    <a href="view_lesson.php?lesson_id=<?= $next_lesson['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-play"></i> <?= __('continue_learning') ?? 'Continuer l\'apprentissage' ?>
                    </a>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bar
            setTimeout(() => {
                const bar = document.querySelector('.progress-bar');
                if (bar) {
                    const progress = bar.getAttribute('data-progress');
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = progress + '%';
                    }, 100);
                }
            }, 300);

            // Animate lesson cards
            const lessons = document.querySelectorAll('.lesson-item');
            lessons.forEach((lesson, index) => {
                lesson.style.opacity = '0';
                lesson.style.transform = 'translateX(-20px)';
                setTimeout(() => {
                    lesson.style.transition = 'all 0.5s ease';
                    lesson.style.opacity = '1';
                    lesson.style.transform = 'translateX(0)';
                }, index * 50);
            });
        });
    </script>
</body>

</html>