<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

// Vérifie si l'ID du cours est passé
if (!isset($_GET['course_id']) || empty($_GET['course_id'])) {
    header('Location: my_courses.php');
    exit;
}

$course_id = $_GET['course_id'];
$student_id = $_SESSION['user_id'];

try {
    // Vérifie si l'étudiant est bien inscrit à ce cours
    $stmtCheck = $pdo->prepare("SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmtCheck->execute([$student_id, $course_id]);
    $enrollment = $stmtCheck->fetch();

    if (!$enrollment) {
        // Si pas inscrit, rediriger vers la page d'inscription
        header("Location: enroll.php?course_id=$course_id");
        exit;
    }

    // Récupère les informations du cours
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS instructor_name, u.email AS instructor_email,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
               (SELECT COUNT(*) FROM student_lessons sl 
                JOIN lessons l ON sl.lesson_id = l.id 
                WHERE l.course_id = c.id AND sl.student_id = ?) as completed_lessons
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$student_id, $course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: my_courses.php');
        exit;
    }

    // Récupère les leçons associées à ce cours avec progression
    $stmtLessons = $pdo->prepare("
        SELECT l.*, 
               CASE WHEN sl.student_id IS NOT NULL THEN 1 ELSE 0 END as is_completed,
               sl.completed_at,
               (SELECT COUNT(*) FROM lessons WHERE course_id = l.course_id AND order_index <= l.order_index) as lesson_number
        FROM lessons l
        LEFT JOIN student_lessons sl ON l.id = sl.lesson_id AND sl.student_id = ?
        WHERE l.course_id = ?
        ORDER BY l.order_index ASC
    ");
    $stmtLessons->execute([$student_id, $course_id]);
    $lessons = $stmtLessons->fetchAll();

    // Calculer les statistiques
    $total_lessons = count($lessons);
    $completed_lessons = array_filter($lessons, fn($l) => $l['is_completed'] == 1);
    $progress_percent = $total_lessons > 0 ? round((count($completed_lessons) / $total_lessons) * 100) : 0;

    // Mettre à jour la progression si nécessaire
    if ($progress_percent != $enrollment['progress_percent']) {
        $stmtUpdate = $pdo->prepare("
            UPDATE student_courses 
            SET progress_percent = ?, last_accessed = NOW() 
            WHERE student_id = ? AND course_id = ?
        ");
        $stmtUpdate->execute([$progress_percent, $student_id, $course_id]);
    }

    // Trouver la prochaine leçon à compléter
    $next_lesson = null;
    foreach ($lessons as $lesson) {
        if (!$lesson['is_completed']) {
            $next_lesson = $lesson;
            break;
        }
    }

    // Récupérer les statistiques de temps d'étude
    $stmtTime = $pdo->prepare("
        SELECT 
            SUM(TIMESTAMPDIFF(MINUTE, sl.started_at, sl.completed_at)) as total_study_time,
            COUNT(DISTINCT DATE(sl.completed_at)) as study_days,
            MAX(sl.completed_at) as last_study_date
        FROM student_lessons sl
        JOIN lessons l ON sl.lesson_id = l.id
        WHERE l.course_id = ? AND sl.student_id = ? AND sl.started_at IS NOT NULL
    ");
    $stmtTime->execute([$course_id, $student_id]);
    $study_stats = $stmtTime->fetch();

} catch (PDOException $e) {
    error_log("Database error in view_course: " . $e->getMessage());
    header('Location: my_courses.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> | TaaBia</title>
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
                <a href="my_courses.php" class="student-nav-item active">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
                <a href="course_lessons.php" class="student-nav-item">
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
                        <h1><?= htmlspecialchars($course['title']) ?></h1>
                        <p>Par <?= htmlspecialchars($course['instructor_name']) ?></p>
                    </div>
                    <div>
                        <a href="my_courses.php" class="student-btn student-btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Retour aux cours
                        </a>
                    </div>
                </div>
            </div>

            <!-- Course Overview -->
            <div class="student-cards" style="margin-bottom: var(--spacing-6);">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Progression</div>
                    <div class="student-card-value"><?= $progress_percent ?>%</div>
                    <div class="student-card-description"><?= count($completed_lessons) ?> sur <?= $total_lessons ?> leçons complétées</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Temps d'étude</div>
                    <div class="student-card-value"><?= $study_stats['total_study_time'] ? round($study_stats['total_study_time'] / 60, 1) . 'h' : '0h' ?></div>
                    <div class="student-card-description"><?= $study_stats['study_days'] ?? 0 ?> jours d'étude</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Dernière activité</div>
                    <div class="student-card-value"><?= $study_stats['last_study_date'] ? date('d/m', strtotime($study_stats['last_study_date'])) : 'Aucune' ?></div>
                    <div class="student-card-description">Dernière leçon complétée</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Inscrit depuis</div>
                    <div class="student-card-value"><?= date('d/m/Y', strtotime($enrollment['enrolled_at'])) ?></div>
                    <div class="student-card-description"><?= timeAgo($enrollment['enrolled_at']) ?></div>
                </div>
            </div>

            <!-- Course Description -->
            <div class="student-card" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-info-circle"></i> Description du cours
                    </h3>
                </div>
                <div style="padding: var(--spacing-6);">
                    <p style="color: var(--gray-700); line-height: 1.6; margin-bottom: var(--spacing-4);">
                        <?= nl2br(htmlspecialchars($course['description'])) ?>
                    </p>
                    
                    <?php if ($next_lesson): ?>
                        <div style="background: var(--primary-color); color: white; padding: var(--spacing-4); border-radius: 8px; margin-top: var(--spacing-4);">
                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <h4 style="margin: 0 0 0.5rem 0;">Prochaine leçon</h4>
                                    <p style="margin: 0; opacity: 0.9;"><?= htmlspecialchars($next_lesson['title']) ?></p>
                                </div>
                                <a href="view_content.php?lesson_id=<?= $next_lesson['id'] ?>" class="student-btn" style="background: white; color: var(--primary-color); border: none;">
                                    <i class="fas fa-play"></i> Commencer
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lessons List -->
            <div class="student-card">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-list"></i> Leçons (<?= $total_lessons ?>)
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-4);">
                    <?php if (count($lessons) > 0): ?>
                        <?php foreach ($lessons as $lesson): ?>
                            <div style="display: flex; align-items: center; padding: var(--spacing-4); border: 1px solid var(--gray-200); border-radius: 8px; margin-bottom: var(--spacing-3); transition: all 0.3s ease; <?= $lesson['is_completed'] ? 'background: var(--success-color); color: white;' : '' ?>">
                                <div style="margin-right: var(--spacing-4);">
                                    <?php if ($lesson['is_completed']): ?>
                                        <div style="width: 40px; height: 40px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--success-color);">
                                            <i class="fas fa-check"></i>
                                        </div>
                                    <?php else: ?>
                                        <div style="width: 40px; height: 40px; background: var(--gray-200); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--gray-600);">
                                            <?= $lesson['lesson_number'] ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($lesson['title']) ?>
                                    </div>
                                    <div style="font-size: 0.875rem; opacity: 0.8;">
                                        <?= ucfirst($lesson['content_type']) ?>
                                        <?php if ($lesson['is_completed']): ?>
                                            • Complétée le <?= date('d/m/Y', strtotime($lesson['completed_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div style="margin-left: var(--spacing-4);">
                                    <?php if ($lesson['is_completed']): ?>
                                        <a href="view_content.php?lesson_id=<?= $lesson['id'] ?>" class="student-btn student-btn-secondary">
                                            <i class="fas fa-eye"></i> Revoir
                                        </a>
                                    <?php else: ?>
                                        <a href="view_content.php?lesson_id=<?= $lesson['id'] ?>" class="student-btn student-btn-primary">
                                            <i class="fas fa-play"></i> Commencer
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-8); color: var(--gray-500);">
                            <i class="fas fa-book" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Aucune leçon disponible pour le moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Contact Instructor -->
            <div class="student-card" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-user-tie"></i> Contactez l'instructeur
                    </h3>
                </div>
                <div style="padding: var(--spacing-6);">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <h4 style="margin: 0 0 0.5rem 0;"><?= htmlspecialchars($course['instructor_name']) ?></h4>
                            <p style="margin: 0; color: var(--gray-600);"><?= htmlspecialchars($course['instructor_email']) ?></p>
                        </div>
                        <a href="send_message.php?recipient_id=<?= $course['instructor_id'] ?>&course_id=<?= $course_id ?>" class="student-btn student-btn-primary">
                            <i class="fas fa-envelope"></i> Envoyer un message
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to lesson cards
            const lessonCards = document.querySelectorAll('.student-card > div > div > div');
            lessonCards.forEach(card => {
                if (!card.style.background.includes('var(--success-color)')) {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = 'none';
                    });
                }
            });

            // Animate progress on page load
            const progressCards = document.querySelectorAll('.student-card-value');
            progressCards.forEach(card => {
                const value = card.textContent;
                if (value.includes('%')) {
                    const percent = parseInt(value);
                    card.style.opacity = '0';
                    setTimeout(() => {
                        card.style.transition = 'opacity 0.5s ease';
                        card.style.opacity = '1';
                    }, 200);
                }
            });
        });
    </script>
</body>
</html>
