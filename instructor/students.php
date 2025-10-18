<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$filter_course = $_GET['course'] ?? '';
$filter_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'name';

try {
    // Get instructor's courses for filter
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    // Build query with filters
    $where_conditions = ["c.instructor_id = ?"];
    $params = [$instructor_id];

    if ($search) {
        $where_conditions[] = "(s.full_name LIKE ? OR s.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_course) {
        $where_conditions[] = "c.id = ?";
        $params[] = $filter_course;
    }

    if ($filter_status) {
        switch ($filter_status) {
            case 'active':
                $where_conditions[] = "sc.progress > 0";
                break;
            case 'completed':
                $where_conditions[] = "sc.progress >= 100";
                break;
            case 'inactive':
                $where_conditions[] = "sc.progress = 0";
                break;
        }
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Determine sort order
    $order_by = match ($sort_by) {
        'name' => 's.full_name ASC',
        'email' => 's.email ASC',
        'course' => 'c.title ASC',
        'progress' => 'sc.progress DESC',
        'enrolled' => 'sc.enrolled_at DESC',
        'recent' => 'sc.last_activity DESC',
        default => 's.full_name ASC'
    };

    // Get students with comprehensive data
    $stmt = $pdo->prepare("
        SELECT 
            s.id as student_id,
            s.full_name,
            s.email,
            s.created_at as joined_date,
            c.id as course_id,
            c.title as course_title,
            sc.progress,
            sc.enrolled_at,
            sc.last_activity,
            COUNT(l.id) as total_lessons,
            COUNT(cl.id) as completed_lessons
        FROM student_courses sc
        JOIN students s ON sc.student_id = s.id
        JOIN courses c ON sc.course_id = c.id
        LEFT JOIN lessons l ON c.id = l.course_id
        LEFT JOIN completed_lessons cl ON l.id = cl.lesson_id AND cl.student_id = s.id
        WHERE $where_clause
        GROUP BY s.id, c.id
        ORDER BY $order_by
    ");
    $stmt->execute($params);
    $students = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT s.id) as total_students,
            COUNT(DISTINCT c.id) as total_courses,
            AVG(sc.progress) as avg_progress,
            COUNT(CASE WHEN sc.progress >= 100 THEN 1 END) as completed_courses
        FROM student_courses sc
        JOIN students s ON sc.student_id = s.id
        JOIN courses c ON sc.course_id = c.id
        WHERE c.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Database error in students: " . $e->getMessage());
    $students = [];
    $courses = [];
    $stats = ['total_students' => 0, 'total_courses' => 0, 'avg_progress' => 0, 'completed_courses' => 0];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes étudiants | TaaBia</title>
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
                <a href="profile.php" class="instructor-nav-item">
                    <i class="fas fa-user"></i>
                    Profil
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
                <h1>Mes étudiants</h1>
                <p>Gérez vos étudiants et suivez leur progression</p>
            </div>

            <!-- Statistics Cards -->
            <div class="instructor-cards" style="margin-bottom: var(--spacing-6);">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Total étudiants</div>
                    <div class="instructor-card-value"><?= $stats['total_students'] ?></div>
                    <div class="instructor-card-description">Étudiants inscrits</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Cours actifs</div>
                    <div class="instructor-card-value"><?= $stats['total_courses'] ?></div>
                    <div class="instructor-card-description">Cours avec étudiants</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Progression moyenne</div>
                    <div class="instructor-card-value"><?= round($stats['avg_progress'], 1) ?>%</div>
                    <div class="instructor-card-description">Tous cours confondus</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-trophy"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Cours terminés</div>
                    <div class="instructor-card-value"><?= $stats['completed_courses'] ?></div>
                    <div class="instructor-card-description">100% de progression</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-search"></i> Recherche et filtres
                    </h3>
                </div>

                <div style="padding: var(--spacing-6);">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-4);">
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-search"></i> Rechercher
                            </label>
                            <input type="text" name="search"
                                class="instructor-form-input"
                                placeholder="Nom ou email de l'étudiant"
                                value="<?= htmlspecialchars($search) ?>">
                        </div>

                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-book"></i> Cours
                            </label>
                            <select name="course" class="instructor-form-input instructor-form-select">
                                <option value="">Tous les cours</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= $filter_course == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-filter"></i> Statut
                            </label>
                            <select name="status" class="instructor-form-input instructor-form-select">
                                <option value="">Tous les statuts</option>
                                <option value="active" <?= $filter_status == 'active' ? 'selected' : '' ?>>Actifs</option>
                                <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Terminés</option>
                                <option value="inactive" <?= $filter_status == 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                            </select>
                        </div>

                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-sort"></i> Trier par
                            </label>
                            <select name="sort" class="instructor-form-input instructor-form-select">
                                <option value="name" <?= $sort_by == 'name' ? 'selected' : '' ?>>Nom</option>
                                <option value="email" <?= $sort_by == 'email' ? 'selected' : '' ?>>Email</option>
                                <option value="course" <?= $sort_by == 'course' ? 'selected' : '' ?>>Cours</option>
                                <option value="progress" <?= $sort_by == 'progress' ? 'selected' : '' ?>>Progression</option>
                                <option value="enrolled" <?= $sort_by == 'enrolled' ? 'selected' : '' ?>>Date d'inscription</option>
                                <option value="recent" <?= $sort_by == 'recent' ? 'selected' : '' ?>>Activité récente</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: var(--spacing-2); align-items: end;">
                            <button type="submit" class="instructor-btn instructor-btn-primary">
                                <i class="fas fa-search"></i>
                                Filtrer
                            </button>

                            <a href="students.php" class="instructor-btn instructor-btn-secondary">
                                <i class="fas fa-times"></i>
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Students List -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-users"></i> Liste des étudiants (<?= count($students) ?>)
                    </h3>
                </div>

                <?php if (count($students) === 0): ?>
                    <div style="padding: var(--spacing-8); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-users" style="font-size: 3rem; margin-bottom: var(--spacing-4); opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                            Aucun étudiant trouvé
                        </h3>
                        <p style="margin: 0; color: var(--gray-500);">
                            <?= $search || $filter_course || $filter_status ? 'Aucun étudiant ne correspond à vos critères de recherche.' : 'Aucun étudiant inscrit pour l\'instant.' ?>
                        </p>
                        <?php if ($search || $filter_course || $filter_status): ?>
                            <a href="students.php" class="instructor-btn instructor-btn-primary" style="margin-top: var(--spacing-4);">
                                <i class="fas fa-times"></i>
                                Réinitialiser les filtres
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-student-grid">
                            <?php foreach ($students as $student): ?>
                                <div class="instructor-student-card">
                                    <div class="instructor-student-header">
                                        <div class="instructor-student-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="instructor-student-info">
                                            <h4 class="instructor-student-name">
                                                <?= htmlspecialchars($student['full_name']) ?>
                                            </h4>
                                            <div class="instructor-student-email">
                                                <?= htmlspecialchars($student['email']) ?>
                                            </div>
                                        </div>
                                        <div class="instructor-student-status">
                                            <?php if ($student['progress'] >= 100): ?>
                                                <span class="instructor-badge success">Terminé</span>
                                            <?php elseif ($student['progress'] > 0): ?>
                                                <span class="instructor-badge info">En cours</span>
                                            <?php else: ?>
                                                <span class="instructor-badge warning">Inactif</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="instructor-student-content">
                                        <div class="instructor-student-course">
                                            <i class="fas fa-book"></i>
                                            <?= htmlspecialchars($student['course_title']) ?>
                                        </div>

                                        <div class="instructor-student-progress">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-1);">
                                                <span style="font-weight: 600; color: var(--gray-700);">
                                                    Progression
                                                </span>
                                                <span style="font-weight: 600; color: var(--primary-color);">
                                                    <?= round($student['progress'], 1) ?>%
                                                </span>
                                            </div>
                                            <div class="instructor-progress-bar">
                                                <div class="instructor-progress-fill" style="width: <?= $student['progress'] ?>%"></div>
                                            </div>
                                        </div>

                                        <div class="instructor-student-stats">
                                            <span>
                                                <i class="fas fa-play-circle"></i>
                                                <?= $student['completed_lessons'] ?>/<?= $student['total_lessons'] ?> leçons
                                            </span>
                                            <span>
                                                <i class="fas fa-calendar"></i>
                                                Inscrit le <?= date('d/m/Y', strtotime($student['enrolled_at'])) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="instructor-student-actions">
                                        <a href="view_student_progress.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $student['course_id'] ?>"
                                            class="instructor-btn instructor-btn-primary"
                                            style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-chart-line"></i>
                                            Progression
                                        </a>

                                        <a href="message_student.php?id=<?= $student['student_id'] ?>"
                                            class="instructor-btn instructor-btn-info"
                                            style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-envelope"></i>
                                            Message
                                        </a>

                                        <a href="update_progress.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $student['course_id'] ?>"
                                            class="instructor-btn instructor-btn-success"
                                            style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-edit"></i>
                                            Modifier
                                        </a>

                                        <a href="remove_student.php?id=<?= $student['student_id'] ?>"
                                            class="instructor-btn"
                                            style="background: var(--danger-color); color: var(--white); padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);"
                                            onclick="return confirm('Êtes-vous sûr de vouloir retirer cet étudiant du cours ?')">
                                            <i class="fas fa-trash"></i>
                                            Retirer
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>

                <a href="validate_submissions.php" class="instructor-btn instructor-btn-warning">
                    <i class="fas fa-check-circle"></i>
                    Devoirs à valider
                </a>

                <a href="earnings.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-chart-line"></i>
                    Mes gains
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form when filters change
            const filterSelects = document.querySelectorAll('select[name="course"], select[name="status"], select[name="sort"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });

            // Search with debounce
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.closest('form').submit();
                }, 500);
            });
        });
    </script>
</body>

</html>