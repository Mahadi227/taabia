<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get instructor's courses
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.status,
               COUNT(sc.student_id) as enrollment_count
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    // Get lessons for selected course
    $lessons = [];
    if (isset($_GET['course_id'])) {
        $course_id = $_GET['course_id'];
        $stmt = $pdo->prepare("
            SELECT id, title, lesson_order 
            FROM lessons 
            WHERE course_id = ? AND status = 'active'
            ORDER BY lesson_order ASC, title ASC
        ");
        $stmt->execute([$course_id]);
        $lessons = $stmt->fetchAll();
    }

    $success_message = '';
    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $course_id = $_POST['course_id'] ?? '';
        $lesson_id = $_POST['lesson_id'] ?? null;
        $session_title = trim($_POST['session_title'] ?? '');
        $session_date = $_POST['session_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $session_type = $_POST['session_type'] ?? 'lesson';

        // Validation
        if (empty($course_id)) {
            $error_message = "Veuillez sélectionner un cours.";
        } elseif (empty($session_title)) {
            $error_message = "Le titre de la session est obligatoire.";
        } elseif (strlen($session_title) < 3) {
            $error_message = "Le titre doit contenir au moins 3 caractères.";
        } elseif (empty($session_date)) {
            $error_message = "La date de la session est obligatoire.";
        } else {
            // Verify course belongs to instructor
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
            $stmt->execute([$course_id, $instructor_id]);
            if ($stmt->rowCount() == 0) {
                $error_message = "Cours invalide.";
            } else {
                // Insert attendance session
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_sessions (course_id, lesson_id, session_title, session_date, start_time, end_time, session_type, instructor_id, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$course_id, $lesson_id, $session_title, $session_date, $start_time, $end_time, $session_type, $instructor_id]);

                $session_id = $pdo->lastInsertId();

                // Auto-create attendance records for all enrolled students
                $stmt = $pdo->prepare("
                    INSERT INTO student_attendance (session_id, student_id, attendance_status, recorded_by, created_at)
                    SELECT ?, sc.student_id, 'absent', ?, NOW()
                    FROM student_courses sc
                    WHERE sc.course_id = ?
                ");
                $stmt->execute([$session_id, $instructor_id, $course_id]);

                $success_message = "Session de présence créée avec succès !";
                
                // Clear form data
                $session_title = $session_date = $start_time = $end_time = '';
                $lesson_id = null;
            }
        }
    }

} catch (PDOException $e) {
    error_log("Database error in create attendance session: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
    $courses = [];
    $lessons = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une session de présence | TaaBia</title>
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
                <a href="attendance_management.php" class="instructor-nav-item active">
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
                <h1>Créer une session de présence</h1>
                <p>Créez une nouvelle session de présence pour vos étudiants</p>
            </div>

            <!-- Success/Error Messages -->
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

            <!-- Session Form -->
            <div class="instructor-form">
                <form method="POST" id="sessionForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="course_id" class="instructor-form-label">
                                <i class="fas fa-book"></i> Cours associé *
                            </label>
                            <select name="course_id" id="course_id" required class="instructor-form-input instructor-form-select">
                                <option value="">-- Choisissez un cours --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" 
                                            <?= (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) || (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?> 
                                        (<?= $course['enrollment_count'] ?> inscrits)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="session_type" class="instructor-form-label">
                                <i class="fas fa-tag"></i> Type de session
                            </label>
                            <select name="session_type" id="session_type" class="instructor-form-input instructor-form-select">
                                <option value="lesson" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'lesson') ? 'selected' : '' ?>>Leçon</option>
                                <option value="quiz" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'quiz') ? 'selected' : '' ?>>Quiz</option>
                                <option value="assignment" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'assignment') ? 'selected' : '' ?>>Devoir</option>
                                <option value="meeting" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'meeting') ? 'selected' : '' ?>>Réunion</option>
                                <option value="other" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'other') ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="instructor-form-group">
                        <label for="session_title" class="instructor-form-label">
                            <i class="fas fa-heading"></i> Titre de la session *
                        </label>
                        <input type="text" name="session_title" id="session_title" required 
                               class="instructor-form-input" 
                               placeholder="Titre de votre session de présence"
                               value="<?= htmlspecialchars($_POST['session_title'] ?? '') ?>">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="session_date" class="instructor-form-label">
                                <i class="fas fa-calendar"></i> Date de la session *
                            </label>
                            <input type="date" name="session_date" id="session_date" required 
                                   class="instructor-form-input" 
                                   value="<?= $_POST['session_date'] ?? date('Y-m-d') ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="start_time" class="instructor-form-label">
                                <i class="fas fa-clock"></i> Heure de début
                            </label>
                            <input type="time" name="start_time" id="start_time" 
                                   class="instructor-form-input" 
                                   value="<?= $_POST['start_time'] ?? '' ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="end_time" class="instructor-form-label">
                                <i class="fas fa-clock"></i> Heure de fin
                            </label>
                            <input type="time" name="end_time" id="end_time" 
                                   class="instructor-form-input" 
                                   value="<?= $_POST['end_time'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <div class="instructor-form-group">
                        <label for="lesson_id" class="instructor-form-label">
                            <i class="fas fa-play-circle"></i> Leçon associée (optionnel)
                        </label>
                        <select name="lesson_id" id="lesson_id" class="instructor-form-input instructor-form-select">
                            <option value="">-- Aucune leçon spécifique --</option>
                            <?php foreach ($lessons as $lesson): ?>
                                <option value="<?= $lesson['id'] ?>" <?= (isset($_POST['lesson_id']) && $_POST['lesson_id'] == $lesson['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lesson['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            Créer la session
                        </button>
                        
                        <a href="attendance_management.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                        
                        <div style="margin-left: auto; font-size: var(--font-size-sm); color: var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            Les étudiants inscrits seront automatiquement marqués comme absents
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
                                            <span class="instructor-badge <?= $course['status'] == 'active' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($course['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="instructor-course-stats">
                                            <span>
                                                <i class="fas fa-users"></i>
                                                <?= $course['enrollment_count'] ?> inscrits
                                            </span>
                                        </div>
                                        
                                        <div class="instructor-course-footer">
                                            <a href="create_attendance_session.php?course_id=<?= $course['id'] ?>" 
                                               class="instructor-btn instructor-btn-primary"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-plus"></i>
                                                Créer une session
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
                <a href="attendance_management.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour à la gestion
                </a>
                
                <a href="my_courses.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const courseSelect = document.getElementById('course_id');
            const lessonSelect = document.getElementById('lesson_id');
            
            // Load lessons when course is selected
            courseSelect.addEventListener('change', function() {
                const courseId = this.value;
                if (courseId) {
                    // Clear current lessons
                    lessonSelect.innerHTML = '<option value="">-- Aucune leçon spécifique --</option>';
                    
                    // Fetch lessons for selected course
                    fetch(`get_lessons.php?course_id=${courseId}`)
                        .then(response => response.json())
                        .then(lessons => {
                            lessons.forEach(lesson => {
                                const option = document.createElement('option');
                                option.value = lesson.id;
                                option.textContent = lesson.title;
                                lessonSelect.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error loading lessons:', error);
                        });
                }
            });
            
            // Form validation
            const form = document.getElementById('sessionForm');
            form.addEventListener('submit', function(e) {
                const courseId = courseSelect.value;
                const sessionTitle = document.getElementById('session_title').value.trim();
                const sessionDate = document.getElementById('session_date').value;
                
                if (!courseId) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un cours.');
                    return;
                }
                
                if (!sessionTitle) {
                    e.preventDefault();
                    alert('Veuillez saisir un titre pour la session.');
                    document.getElementById('session_title').focus();
                    return;
                }
                
                if (!sessionDate) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une date pour la session.');
                    document.getElementById('session_date').focus();
                    return;
                }
            });
        });
    </script>
</body>
</html> 