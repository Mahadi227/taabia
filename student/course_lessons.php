<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Get course ID from URL
if (!isset($_GET['course_id'])) {
    header('Location: my_courses.php');
    exit;
}
$course_id = (int) $_GET['course_id'];

try {
    // Check if student is enrolled in this course
    $stmt = $pdo->prepare("SELECT sc.*, c.title as course_title, c.description as course_description, u.full_name as instructor_name 
                           FROM student_courses sc 
                           JOIN courses c ON sc.course_id = c.id 
                           JOIN users u ON c.instructor_id = u.id 
                           WHERE sc.student_id = ? AND sc.course_id = ?");
    $stmt->execute([$student_id, $course_id]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        header('Location: my_courses.php');
        exit;
    }

    // Get lessons with completion status
    $stmtLessons = $pdo->prepare("
        SELECT l.*, 
               CASE WHEN sl.student_id IS NOT NULL THEN 1 ELSE 0 END as is_completed,
               sl.completed_at
        FROM lessons l
        LEFT JOIN student_lessons sl ON l.id = sl.lesson_id AND sl.student_id = ?
        WHERE l.course_id = ? AND l.status = 'active'
        ORDER BY l.position ASC, l.created_at ASC
    ");
    $stmtLessons->execute([$student_id, $course_id]);
    $lessons = $stmtLessons->fetchAll();

    // Calculate progress
    $total_lessons = count($lessons);
    $completed_lessons = array_filter($lessons, fn($l) => $l['is_completed'] == 1);
    $progress_percent = $total_lessons > 0 ? round((count($completed_lessons) / $total_lessons) * 100) : 0;

    // Update progress in database
    if ($progress_percent != $enrollment['progress_percent']) {
        $stmtUpdate = $pdo->prepare("UPDATE student_courses SET progress_percent = ? WHERE student_id = ? AND course_id = ?");
        $stmtUpdate->execute([$progress_percent, $student_id, $course_id]);
    }

} catch (PDOException $e) {
    error_log("Database error in course_lessons: " . $e->getMessage());
    header('Location: my_courses.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leçons - <?= htmlspecialchars($enrollment['course_title']) ?> | TaaBia</title>
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
            <!-- Course Header -->
            <div class="student-header">
                <h1><?= htmlspecialchars($enrollment['course_title']) ?></h1>
                <p>Leçons et contenu du cours</p>
            </div>

            <!-- Course Progress -->
            <div class="student-cards" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Total des leçons</div>
                    <div class="student-card-value"><?= $total_lessons ?></div>
                    <div class="student-card-description">Leçons disponibles</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Leçons terminées</div>
                    <div class="student-card-value"><?= count($completed_lessons) ?></div>
                    <div class="student-card-description">Leçons complétées</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="student-card-title">En cours</div>
                    <div class="student-card-value"><?= $total_lessons - count($completed_lessons) ?></div>
                    <div class="student-card-description">Leçons restantes</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Progression</div>
                    <div class="student-card-value"><?= $progress_percent ?>%</div>
                    <div class="student-card-description">Progression globale</div>
                </div>
            </div>

            <!-- Course Info -->
            <div class="student-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-4);">
                        <div>
                            <h3 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-900);">
                                <i class="fas fa-info-circle"></i> Informations du cours
                            </h3>
                            <p style="margin: 0; color: var(--gray-600);">
                                <i class="fas fa-user-tie"></i> Formateur: <?= htmlspecialchars($enrollment['instructor_name']) ?>
                            </p>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--primary-color);">
                                <?= $progress_percent ?>% terminé
                            </div>
                            <div class="student-progress" style="width: 200px; margin-top: var(--spacing-2);">
                                <div class="student-progress-bar" style="width: <?= $progress_percent ?>%;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                        <p style="margin: 0; color: var(--gray-700); line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($enrollment['course_description'])) ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Lessons List -->
            <div class="student-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h2 style="margin: 0; color: var(--gray-900);">
                        <i class="fas fa-list"></i> Leçons du cours
                    </h2>
                </div>
                
                <?php if (count($lessons) > 0): ?>
                    <div style="padding: var(--spacing-4);">
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <div style="
                                display: flex; 
                                align-items: center; 
                                justify-content: space-between; 
                                padding: var(--spacing-4); 
                                border: 1px solid var(--gray-200); 
                                border-radius: var(--radius-lg); 
                                margin-bottom: var(--spacing-3);
                                background: var(--white);
                                transition: var(--transition-normal);
                                border-left: 4px solid <?= $lesson['is_completed'] ? 'var(--success-color)' : 'var(--primary-color)' ?>;
                            " class="lesson-item" data-lesson-id="<?= $lesson['id'] ?>">
                                
                                <div style="display: flex; align-items: center; gap: var(--spacing-4);">
                                    <div style="
                                        width: 48px; 
                                        height: 48px; 
                                        border-radius: 50%; 
                                        display: flex; 
                                        align-items: center; 
                                        justify-content: center;
                                        background: <?= $lesson['is_completed'] ? 'var(--success-color)' : 'var(--primary-color)' ?>;
                                        color: var(--white);
                                        font-weight: 600;
                                        font-size: var(--font-size-lg);
                                    ">
                                        <?php if ($lesson['is_completed']): ?>
                                            <i class="fas fa-check"></i>
                                        <?php else: ?>
                                            <?= $index + 1 ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                            <?= htmlspecialchars($lesson['title']) ?>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--gray-600); margin-bottom: var(--spacing-2);">
                                            <?= htmlspecialchars(substr($lesson['content'], 0, 150)) ?>...
                                        </div>
                                        <div style="display: flex; gap: var(--spacing-4); font-size: var(--font-size-xs); color: var(--gray-500);">
                                            <span>
                                                <i class="fas fa-clock"></i>
                                                <?= $lesson['duration'] ?? 'N/A' ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-file-alt"></i>
                                                <?= ucfirst($lesson['type'] ?? 'text') ?>
                                            </span>
                                            <?php if ($lesson['is_completed']): ?>
                                                <span style="color: var(--success-color);">
                                                    <i class="fas fa-check"></i>
                                                    Terminé le <?= date('d/m/Y', strtotime($lesson['completed_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div style="display: flex; align-items: center; gap: var(--spacing-3);">
                                    <?php if ($lesson['is_completed']): ?>
                                        <span class="student-badge success">
                                            <i class="fas fa-check"></i> Terminé
                                        </span>
                                    <?php endif; ?>
                                    
                                    <a href="view_content.php?lesson_id=<?= $lesson['id'] ?>" 
                                       class="student-btn <?= $lesson['is_completed'] ? 'student-btn-success' : 'student-btn-primary' ?>"
                                       style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-xs);">
                                        <i class="fas fa-<?= $lesson['is_completed'] ? 'check' : 'play' ?>"></i>
                                        <?= $lesson['is_completed'] ? 'Revoir' : 'Commencer' ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="student-empty">
                        <div class="student-empty-icon">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <div class="student-empty-title">Aucune leçon disponible</div>
                        <div class="student-empty-description">
                            Le formateur n'a pas encore ajouté de leçons à ce cours.
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Course Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour à mes cours
                </a>
                
                <a href="view_course.php?course_id=<?= $course_id ?>" class="student-btn student-btn-primary">
                    <i class="fas fa-eye"></i>
                    Voir le cours
                </a>
                
                <a href="messages.php?instructor_id=<?= $enrollment['instructor_id'] ?? '' ?>" class="student-btn student-btn-success">
                    <i class="fas fa-envelope"></i>
                    Contacter le formateur
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to lesson items
            const lessonItems = document.querySelectorAll('.lesson-item');
            lessonItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = 'var(--shadow-md)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });

            // Animate progress bar on load
            const progressBar = document.querySelector('.student-progress-bar');
            if (progressBar) {
                const width = progressBar.style.width;
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = width;
                }, 500);
            }
        });
    </script>
</body>
</html>