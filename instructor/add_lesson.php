<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get instructor's courses
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.status,
               COUNT(l.id) as lesson_count,
               COUNT(sc.student_id) as enrollment_count
        FROM courses c
        LEFT JOIN lessons l ON c.id = l.course_id
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    $success_message = '';
    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $course_id = $_POST['course_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $lesson_order = (int)($_POST['lesson_order'] ?? 0);
        $lesson_type = $_POST['lesson_type'] ?? 'text';

        // Validation
        if (empty($course_id)) {
            $error_message = "Veuillez sélectionner un cours.";
        } elseif (empty($title)) {
            $error_message = "Le titre de la leçon est obligatoire.";
        } elseif (strlen($title) < 3) {
            $error_message = "Le titre doit contenir au moins 3 caractères.";
        } else {
            // Verify course belongs to instructor
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
            $stmt->execute([$course_id, $instructor_id]);
            if ($stmt->rowCount() == 0) {
                $error_message = "Cours invalide.";
            } else {
                // Insert lesson - Updated to match the actual table structure
                $stmt = $pdo->prepare("
                    INSERT INTO lessons (course_id, title, content, file_url, order_index, content_type, is_active, created_at, description) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([$course_id, $title, $content, $video_url, $lesson_order, $lesson_type, $content]);

                $success_message = "Leçon ajoutée avec succès !";
                
                // Clear form data
                $title = $content = $video_url = '';
                $lesson_order = 0;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Database error in add_lesson: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une leçon | TaaBia</title>
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
                <a href="add_lesson.php" class="instructor-nav-item active">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="attendance_management.php" class="instructor-nav-item">
                    <i class="fas fa-calendar-check"></i>
                    Gestion de la présence
                </a>
                <a href="students.php" class="instructor-nav-item">
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
                <h1>Ajouter une leçon</h1>
                <p>Créez une nouvelle leçon pour vos cours</p>
            </div>

            <!-- Success/Error Messages -->
             <?php if (!isset($success_message)) $success_message = ''; ?>
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

            <!-- Lesson Form -->
            <div class="instructor-form">
                <form method="POST" id="lessonForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="course_id" class="instructor-form-label">
                                <i class="fas fa-book"></i> Cours associé *
                            </label>
                            <select name="course_id" id="course_id" required class="instructor-form-input instructor-form-select">
                                <option value="">-- Choisissez un cours --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= isset($_POST['course_id']) && $_POST['course_id'] == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?> 
                                        (<?= $course['lesson_count'] ?> leçons, <?= $course['enrollment_count'] ?> inscrits)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="lesson_type" class="instructor-form-label">
                                <i class="fas fa-tag"></i> Type de leçon
                            </label>
                            <select name="lesson_type" id="lesson_type" class="instructor-form-input instructor-form-select">
                                <option value="text" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'text') ? 'selected' : '' ?>>Texte</option>
                                <option value="video" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'video') ? 'selected' : '' ?>>Vidéo</option>
                                <option value="pdf" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'pdf') ? 'selected' : '' ?>>PDF</option>
                                <option value="quiz" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'quiz') ? 'selected' : '' ?>>Quiz</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="instructor-form-group">
                        <label for="title" class="instructor-form-label">
                            <i class="fas fa-heading"></i> Titre de la leçon *
                        </label>
                        <input type="text" name="title" id="title" required 
                               class="instructor-form-input" 
                               placeholder="Titre de votre leçon"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>
                    
                    <div class="instructor-form-group">
                        <label for="content" class="instructor-form-label">
                            <i class="fas fa-align-left"></i> Contenu / Description
                        </label>
                        <textarea name="content" id="content" rows="6" 
                                  class="instructor-form-input instructor-form-textarea" 
                                  placeholder="Décrivez le contenu de cette leçon..."
                                  style="resize: vertical;"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="video_url" class="instructor-form-label">
                                <i class="fas fa-video"></i> Lien vidéo (optionnel)
                            </label>
                            <input type="url" name="video_url" id="video_url" 
                                   class="instructor-form-input" 
                                   placeholder="https://youtube.com/watch?v=..."
                                   value="<?= htmlspecialchars($_POST['video_url'] ?? '') ?>">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                Supporte YouTube, Vimeo, et autres plateformes
                            </div>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="lesson_order" class="instructor-form-label">
                                <i class="fas fa-sort-numeric-up"></i> Ordre d'affichage
                            </label>
                            <input type="number" name="lesson_order" id="lesson_order" 
                                   min="0" value="<?= $_POST['lesson_order'] ?? 0 ?>"
                                   class="instructor-form-input">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                Ordre dans la liste des leçons (0 = premier)
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            Ajouter la leçon
                        </button>
                        
                        <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                        
                        <div style="margin-left: auto; font-size: var(--font-size-sm); color: var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            La leçon sera ajoutée au cours sélectionné
                        </div>
                    </div>
                </form>
            </div>

            <!-- Course Information -->
            <?php if (count($courses) > 0): ?>
                <div class="instructor-table-container" style="margin-top: var(--spacing-8);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-info-circle"></i> Vos cours disponibles
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-course-grid">
                            <?php foreach ($courses as $course): ?>
                                <div class="instructor-course-card">
                                    <div class="instructor-course-image">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    
                                    <div class="instructor-course-content">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-3);">
                                            <h3 class="instructor-course-title">
                                                <?= htmlspecialchars($course['title']) ?>
                                            </h3>
                                            <span class="instructor-badge <?= $course['status'] == 'published' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($course['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="instructor-course-stats">
                                            <span>
                                                <i class="fas fa-play-circle"></i>
                                                <?= $course['lesson_count'] ?> leçons
                                            </span>
                                            <span>
                                                <i class="fas fa-users"></i>
                                                <?= $course['enrollment_count'] ?> inscrits
                                            </span>
                                        </div>
                                        
                                        <div class="instructor-course-footer">
                                            <a href="course_lessons.php?course_id=<?= $course['id'] ?>" 
                                               class="instructor-btn instructor-btn-primary"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-play"></i>
                                                Gérer les leçons
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>
                
                <a href="add_course.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-plus"></i>
                    Créer un nouveau cours
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('lessonForm');
            const titleInput = document.getElementById('title');
            const contentInput = document.getElementById('content');
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const courseId = document.getElementById('course_id').value;
                
                if (!courseId) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un cours.');
                    return;
                }
                
                if (!title) {
                    e.preventDefault();
                    alert('Veuillez saisir un titre pour la leçon.');
                    titleInput.focus();
                    return;
                }
                
                if (title.length < 3) {
                    e.preventDefault();
                    alert('Le titre doit contenir au moins 3 caractères.');
                    titleInput.focus();
                    return;
                }
            });
            
            // Auto-resize textarea
            contentInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Character counter for title
            titleInput.addEventListener('input', function() {
                const length = this.value.length;
                const maxLength = 100;
                
                if (length > maxLength) {
                    this.style.borderColor = 'var(--danger-color)';
                } else {
                    this.style.borderColor = 'var(--gray-200)';
                }
            });
        });
    </script>
</body>
</html>
