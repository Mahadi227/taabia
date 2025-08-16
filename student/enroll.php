<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

try {
    // Récupérer le course_id depuis l'URL
    $course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

    // Vérifier que le course_id est valide
    if (!$course_id) {
        header('Location: all_courses.php');
        exit;
    }

    // Vérifier que le cours existe et récupérer les détails
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as instructor_name,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id AND status = 'active') as lesson_count,
               (SELECT COUNT(*) FROM student_courses WHERE course_id = c.id) as enrollment_count
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.id = ? AND c.status = 'active'
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: all_courses.php');
        exit;
    }

    // Vérifier si l'étudiant est déjà inscrit
    $check = $pdo->prepare("SELECT * FROM student_courses WHERE student_id = ? AND course_id = ?");
    $check->execute([$student_id, $course_id]);
    if ($check->rowCount() > 0) {
        header('Location: view_course.php?course_id=' . $course_id);
        exit;
    }

    // Process enrollment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $insert = $pdo->prepare("INSERT INTO student_courses (student_id, course_id, enrolled_at, progress_percent) VALUES (?, ?, NOW(), 0)");
        $insert->execute([$student_id, $course_id]);
        
        $_SESSION['success_message'] = 'Inscription réussie au cours "' . $course['title'] . '"';
        header('Location: view_course.php?course_id=' . $course_id);
        exit;
    }

} catch (PDOException $e) {
    error_log("Database error in enroll: " . $e->getMessage());
    header('Location: all_courses.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>S'inscrire au cours | TaaBia</title>
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
                <a href="all_courses.php" class="student-nav-item active">
                    <i class="fas fa-book-open"></i>
                    Découvrir les cours
                </a>
                <a href="my_courses.php" class="student-nav-item">
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
                <h1>Inscription au cours</h1>
                <p>Confirmez votre inscription au cours</p>
            </div>

            <!-- Course Information -->
            <div class="student-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-info-circle"></i> Informations du cours
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-6);">
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-book"></i> Détails du cours
                            </h4>
                            <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                                <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2); font-size: var(--font-size-lg);">
                                    <?= htmlspecialchars($course['title']) ?>
                                </div>
                                <div style="color: var(--gray-600); line-height: 1.6; margin-bottom: var(--spacing-3);">
                                    <?= nl2br(htmlspecialchars($course['description'])) ?>
                                </div>
                                <div style="display: flex; gap: var(--spacing-4); font-size: var(--font-size-sm); color: var(--gray-500);">
                                    <span>
                                        <i class="fas fa-user-tie"></i>
                                        <?= htmlspecialchars($course['instructor_name']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-play-circle"></i>
                                        <?= $course['lesson_count'] ?> leçons
                                    </span>
                                    <span>
                                        <i class="fas fa-users"></i>
                                        <?= $course['enrollment_count'] ?> inscrits
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-check-circle"></i> Ce que vous obtiendrez
                            </h4>
                            <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                                <ul style="margin: 0; padding-left: var(--spacing-4); color: var(--gray-700); line-height: 1.8;">
                                    <li>Accès à toutes les leçons du cours</li>
                                    <li>Suivi de votre progression</li>
                                    <li>Support du formateur</li>
                                    <li>Certificat de completion</li>
                                    <li>Accès permanent au contenu</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment Confirmation -->
            <div class="student-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-user-plus"></i> Confirmation d'inscription
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div style="
                        background: var(--success-color); 
                        color: var(--white); 
                        padding: var(--spacing-4); 
                        border-radius: var(--radius-lg);
                        margin-bottom: var(--spacing-6);
                        display: flex;
                        align-items: center;
                        gap: var(--spacing-3);
                    ">
                        <i class="fas fa-check-circle" style="font-size: var(--font-size-xl);"></i>
                        <div>
                            <div style="font-weight: 600; margin-bottom: var(--spacing-1);">
                                Inscription gratuite
                            </div>
                            <div style="font-size: var(--font-size-sm); opacity: 0.9;">
                                Ce cours est gratuit. Vous pouvez commencer immédiatement après l'inscription.
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" style="text-align: center;">
                        <div style="margin-bottom: var(--spacing-6);">
                            <p style="color: var(--gray-600); margin: 0;">
                                En cliquant sur "S'inscrire", vous confirmez que vous souhaitez rejoindre ce cours.
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-4); justify-content: center; flex-wrap: wrap;">
                            <button type="submit" class="student-btn student-btn-success" style="padding: var(--spacing-3) var(--spacing-6);">
                                <i class="fas fa-user-plus"></i>
                                S'inscrire au cours
                            </button>
                            
                            <a href="all_courses.php" class="student-btn student-btn-secondary" style="padding: var(--spacing-3) var(--spacing-6);">
                                <i class="fas fa-times"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="all_courses.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>
                
                <a href="my_courses.php" class="student-btn student-btn-primary">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation dialog
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                if (!confirm('Êtes-vous sûr de vouloir vous inscrire à ce cours ?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
