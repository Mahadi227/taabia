<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

if (!$student_id || !$course_id) {
    header('Location: students.php');
    exit;
}

try {
    // Verify course belongs to instructor
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    if ($stmt->rowCount() == 0) {
        header('Location: students.php');
        exit;
    }

    // Get student and course information
    $stmt = $pdo->prepare("
        SELECT 
            s.id as student_id,
            s.full_name,
            s.email,
            s.created_at as joined_date,
            sc.progress,
            sc.enrolled_at,
            sc.last_activity,
            c.id as course_id,
            c.title as course_title,
            c.description as course_description
        FROM student_courses sc
        JOIN students s ON sc.student_id = s.id
        JOIN courses c ON sc.course_id = c.id
        WHERE sc.student_id = ? AND sc.course_id = ?
    ");
    $stmt->execute([$student_id, $course_id]);
    $enrollment = $stmt->fetch();

    if (!$enrollment) {
        header('Location: students.php');
        exit;
    }

    // Get course lessons
    $stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.title,
            l.content,
            l.lesson_order,
            l.type,
            l.status,
            cl.completed_at,
            cl.progress as lesson_progress
        FROM lessons l
        LEFT JOIN completed_lessons cl ON l.id = cl.lesson_id AND cl.student_id = ?
        WHERE l.course_id = ?
        ORDER BY l.lesson_order ASC
    ");
    $stmt->execute([$student_id, $course_id]);
    $lessons = $stmt->fetchAll();

    // Get student submissions
    $stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.title,
            s.description,
            s.file_path,
            s.status,
            s.submitted_at,
            s.feedback,
            s.grade,
            l.title as lesson_title
        FROM submissions s
        LEFT JOIN lessons l ON s.lesson_id = l.id
        WHERE s.student_id = ? AND s.course_id = ?
        ORDER BY s.submitted_at DESC
    ");
    $stmt->execute([$student_id, $course_id]);
    $submissions = $stmt->fetchAll();

    // Calculate statistics
    $total_lessons = count($lessons);
    $completed_lessons = count(array_filter($lessons, fn($l) => $l['completed_at']));
    $completion_rate = $total_lessons > 0 ? ($completed_lessons / $total_lessons) * 100 : 0;
    $total_submissions = count($submissions);
    $approved_submissions = count(array_filter($submissions, fn($s) => $s['status'] == 'approved'));

} catch (PDOException $e) {
    error_log("Database error in view_student_progress: " . $e->getMessage());
    header('Location: students.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progression de <?= htmlspecialchars($enrollment['full_name']) ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p>Espace Formateur</p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    Nouveau cours
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="students.php" class="instructor-nav-item active">
                    <i class="fas fa-users"></i>
                    Mes étudiants
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    Devoirs à valider
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    Mes gains
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Transactions
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    Paiements
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <h1>Progression de l'étudiant</h1>
                <p>Suivez la progression de <?= htmlspecialchars($enrollment['full_name']) ?> dans <?= htmlspecialchars($enrollment['course_title']) ?></p>
            </div>

            <!-- Student Information -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-user"></i> Informations de l'étudiant
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-6);">
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2);">
                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($enrollment['full_name']) ?>
                            </div>
                            <div style="color: var(--gray-600); margin-bottom: var(--spacing-1);">
                                <?= htmlspecialchars($enrollment['email']) ?>
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Inscrit le <?= date('d/m/Y', strtotime($enrollment['enrolled_at'])) ?>
                            </div>
                        </div>
                        
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2);">
                                <i class="fas fa-book"></i> <?= htmlspecialchars($enrollment['course_title']) ?>
                            </div>
                            <div style="color: var(--gray-600); margin-bottom: var(--spacing-1);">
                                <?= $total_lessons ?> leçons au total
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Dernière activité: <?= $enrollment['last_activity'] ? date('d/m/Y à H:i', strtotime($enrollment['last_activity'])) : 'Aucune' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Statistics -->
            <div class="instructor-cards" style="margin-bottom: var(--spacing-6);">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Progression globale</div>
                    <div class="instructor-card-value"><?= round($enrollment['progress'], 1) ?>%</div>
                    <div class="instructor-card-description">Progression du cours</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Leçons complétées</div>
                    <div class="instructor-card-value"><?= $completed_lessons ?>/<?= $total_lessons ?></div>
                    <div class="instructor-card-description"><?= round($completion_rate, 1) ?>% de taux de completion</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Soumissions</div>
                    <div class="instructor-card-value"><?= $total_submissions ?></div>
                    <div class="instructor-card-description"><?= $approved_submissions ?> approuvées</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Temps d'étude</div>
                    <div class="instructor-card-value"><?= $enrollment['last_activity'] ? 'Actif' : 'Inactif' ?></div>
                    <div class="instructor-card-description">Dernière activité</div>
                </div>
            </div>

            <!-- Lessons Progress -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-play-circle"></i> Progression des leçons
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <?php if (count($lessons) > 0): ?>
                        <div class="instructor-lesson-grid">
                            <?php foreach ($lessons as $lesson): ?>
                                <div class="instructor-lesson-card">
                                    <div class="instructor-lesson-header">
                                        <div class="instructor-lesson-icon">
                                            <i class="fas fa-<?= $lesson['type'] == 'video' ? 'video' : ($lesson['type'] == 'pdf' ? 'file-pdf' : 'file-text') ?>"></i>
                                        </div>
                                        <div class="instructor-lesson-info">
                                            <h4 class="instructor-lesson-title">
                                                <?= htmlspecialchars($lesson['title']) ?>
                                            </h4>
                                            <div class="instructor-lesson-meta">
                                                Leçon #<?= $lesson['lesson_order'] ?> • <?= ucfirst($lesson['type']) ?>
                                            </div>
                                        </div>
                                        <div class="instructor-lesson-status">
                                            <?php if ($lesson['completed_at']): ?>
                                                <span class="instructor-badge success">Complétée</span>
                                            <?php elseif ($lesson['lesson_progress'] > 0): ?>
                                                <span class="instructor-badge info">En cours</span>
                                            <?php else: ?>
                                                <span class="instructor-badge warning">Non commencée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="instructor-lesson-content">
                                        <?php if ($lesson['content']): ?>
                                            <div class="instructor-lesson-description">
                                                <?= nl2br(htmlspecialchars(substr($lesson['content'], 0, 100))) ?>...
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="instructor-lesson-progress">
                                            <?php if ($lesson['completed_at']): ?>
                                                <div style="color: var(--success-color); font-weight: 600;">
                                                    <i class="fas fa-check"></i> Complétée le <?= date('d/m/Y', strtotime($lesson['completed_at'])) ?>
                                                </div>
                                            <?php elseif ($lesson['lesson_progress'] > 0): ?>
                                                <div style="margin-bottom: var(--spacing-2);">
                                                    <span style="font-weight: 600; color: var(--gray-700);">
                                                        Progression: <?= round($lesson['lesson_progress'], 1) ?>%
                                                    </span>
                                                </div>
                                                <div class="instructor-progress-bar">
                                                    <div class="instructor-progress-fill" style="width: <?= $lesson['lesson_progress'] ?>%"></div>
                                                </div>
                                            <?php else: ?>
                                                <div style="color: var(--gray-500); font-style: italic;">
                                                    <i class="fas fa-clock"></i> Non commencée
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-8); color: var(--gray-500);">
                            <i class="fas fa-play-circle" style="font-size: 3rem; margin-bottom: var(--spacing-4); opacity: 0.5;"></i>
                            <h3 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                                Aucune leçon trouvée
                            </h3>
                            <p style="margin: 0; color: var(--gray-500);">
                                Aucune leçon n'a été créée pour ce cours.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Submissions -->
            <?php if (count($submissions) > 0): ?>
                <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-file-alt"></i> Soumissions (<?= count($submissions) ?>)
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-submission-grid">
                            <?php foreach ($submissions as $submission): ?>
                                <div class="instructor-submission-card">
                                    <div class="instructor-submission-header">
                                        <div class="instructor-submission-avatar">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="instructor-submission-info">
                                            <h4 class="instructor-submission-title">
                                                <?= htmlspecialchars($submission['title']) ?>
                                            </h4>
                                            <div class="instructor-submission-meta">
                                                <?= $submission['lesson_title'] ? 'Leçon: ' . htmlspecialchars($submission['lesson_title']) : 'Devoir général' ?>
                                            </div>
                                        </div>
                                        <div class="instructor-submission-status">
                                            <?php if ($submission['status'] == 'approved'): ?>
                                                <span class="instructor-badge success">Approuvée</span>
                                            <?php elseif ($submission['status'] == 'rejected'): ?>
                                                <span class="instructor-badge danger">Rejetée</span>
                                            <?php else: ?>
                                                <span class="instructor-badge warning">En attente</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="instructor-submission-content">
                                        <?php if ($submission['description']): ?>
                                            <div class="instructor-submission-description">
                                                <?= nl2br(htmlspecialchars($submission['description'])) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="instructor-submission-details">
                                            <span>
                                                <i class="fas fa-calendar"></i>
                                                Soumis le <?= date('d/m/Y à H:i', strtotime($submission['submitted_at'])) ?>
                                            </span>
                                            <?php if ($submission['grade']): ?>
                                                <span>
                                                    <i class="fas fa-star"></i>
                                                    Note: <?= $submission['grade'] ?>/20
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($submission['feedback']): ?>
                                            <div class="instructor-submission-feedback">
                                                <strong>Feedback:</strong> <?= htmlspecialchars($submission['feedback']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="instructor-submission-actions">
                                        <?php if ($submission['file_path']): ?>
                                            <a href="../uploads/<?= $submission['file_path'] ?>" 
                                               target="_blank"
                                               class="instructor-btn instructor-btn-info"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-download"></i>
                                                Voir le fichier
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($submission['status'] == 'pending'): ?>
                                            <a href="validate_submission.php?id=<?= $submission['id'] ?>&action=approve" 
                                               class="instructor-btn instructor-btn-success"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-check"></i>
                                                Approuver
                                            </a>
                                            
                                            <a href="validate_submission.php?id=<?= $submission['id'] ?>&action=reject" 
                                               class="instructor-btn"
                                               style="background: var(--danger-color); color: var(--white); padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-times"></i>
                                                Rejeter
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="students.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux étudiants
                </a>
                
                <a href="edit_progress.php?student_id=<?= $student_id ?>&course_id=<?= $course_id ?>" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-edit"></i>
                    Modifier la progression
                </a>
                
                <a href="message_student.php?id=<?= $student_id ?>" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-envelope"></i>
                    Envoyer un message
                </a>
                
                <a href="remove_student.php?student_id=<?= $student_id ?>&course_id=<?= $course_id ?>" 
                   class="instructor-btn"
                   style="background: var(--danger-color); color: var(--white);"
                   onclick="return confirm('Êtes-vous sûr de vouloir retirer cet étudiant du cours ?')">
                    <i class="fas fa-user-times"></i>
                    Retirer du cours
                </a>
            </div>
        </div>
    </div>
</body>
</html>


<?php
// ✅ Fichier : edit_progress.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

if (!$student_id || !$course_id) redirect('students.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progress = min(100, max(0, (int)$_POST['progress']));
    $stmt = $pdo->prepare("UPDATE student_courses SET progress = ? WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$progress, $student_id, $course_id]);
    redirect("view_student_progress.php?student_id=$student_id&course_id=$course_id");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier la progression</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 2rem; background: #f4f4f4; }
        .card { background: #fff; padding: 2rem; max-width: 500px; margin: auto; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { color: #009688; }
        input[type='number'] { width: 100%; padding: 0.7rem; font-size: 1rem; margin: 1rem 0; }
        button { background: #009688; color: white; padding: 0.8rem 1.5rem; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
<div class="card">
    <h2>Modifier la progression</h2>
    <form method="post">
        <label>Progression (%) :</label>
        <input type="number" name="progress" min="0" max="100" required>
        <button type="submit">Enregistrer</button>
    </form>
</div>
</body>
</html>


<?php
// ✅ Fichier : remove_student.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_role('instructor');

$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

if ($student_id && $course_id) {
    $stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$student_id, $course_id]);
}
redirect('students.php');
