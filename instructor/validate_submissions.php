<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_course = $_GET['course'] ?? '';
$sort_by = $_GET['sort'] ?? 'recent';

try {
    // Get instructor's courses for filter
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    // Build query with filters
    $where_conditions = ["c.instructor_id = ?"];
    $params = [$instructor_id];

    if ($search) {
        $where_conditions[] = "(s.title LIKE ? OR s.description LIKE ? OR st.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_status) {
        $where_conditions[] = "s.status = ?";
        $params[] = $filter_status;
    }

    if ($filter_course) {
        $where_conditions[] = "c.id = ?";
        $params[] = $filter_course;
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Determine sort order
    $order_by = match($sort_by) {
        'recent' => 's.submitted_at DESC',
        'oldest' => 's.submitted_at ASC',
        'student' => 'st.full_name ASC',
        'course' => 'c.title ASC',
        'status' => 's.status ASC',
        default => 's.submitted_at DESC'
    };

    // Get submissions with comprehensive data
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
            st.id as student_id,
            st.full_name as student_name,
            st.email as student_email,
            c.id as course_id,
            c.title as course_title,
            l.title as lesson_title
        FROM submissions s
        JOIN students st ON s.student_id = st.id
        JOIN courses c ON s.course_id = c.id
        LEFT JOIN lessons l ON s.lesson_id = l.id
        WHERE $where_clause
        ORDER BY $order_by
    ");
    $stmt->execute($params);
    $submissions = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_submissions,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_submissions,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_submissions,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_submissions,
            AVG(grade) as avg_grade
        FROM submissions s
        JOIN courses c ON s.course_id = c.id
        WHERE c.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Database error in validate_submissions: " . $e->getMessage());
    $submissions = [];
    $courses = [];
    $stats = ['total_submissions' => 0, 'pending_submissions' => 0, 'approved_submissions' => 0, 'rejected_submissions' => 0, 'avg_grade' => 0];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Validation des devoirs | TaaBia</title>
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
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    Mes étudiants
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item active">
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
                <h1>Validation des devoirs</h1>
                <p>Gérez et évaluez les soumissions de vos étudiants</p>
            </div>

            <!-- Statistics Cards -->
            <div class="instructor-cards" style="margin-bottom: var(--spacing-6);">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Total soumissions</div>
                    <div class="instructor-card-value"><?= $stats['total_submissions'] ?></div>
                    <div class="instructor-card-description">Toutes les soumissions</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">En attente</div>
                    <div class="instructor-card-value"><?= $stats['pending_submissions'] ?></div>
                    <div class="instructor-card-description">À valider</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Approuvées</div>
                    <div class="instructor-card-value"><?= $stats['approved_submissions'] ?></div>
                    <div class="instructor-card-description">Soumissions validées</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-star"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Note moyenne</div>
                    <div class="instructor-card-value"><?= round($stats['avg_grade'], 1) ?>/20</div>
                    <div class="instructor-card-description">Toutes soumissions</div>
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
                                   placeholder="Titre, description ou étudiant"
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
                                <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approuvées</option>
                                <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>Rejetées</option>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-sort"></i> Trier par
                            </label>
                            <select name="sort" class="instructor-form-input instructor-form-select">
                                <option value="recent" <?= $sort_by == 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                                <option value="oldest" <?= $sort_by == 'oldest' ? 'selected' : '' ?>>Plus anciennes</option>
                                <option value="student" <?= $sort_by == 'student' ? 'selected' : '' ?>>Étudiant</option>
                                <option value="course" <?= $sort_by == 'course' ? 'selected' : '' ?>>Cours</option>
                                <option value="status" <?= $sort_by == 'status' ? 'selected' : '' ?>>Statut</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-2); align-items: end;">
                            <button type="submit" class="instructor-btn instructor-btn-primary">
                                <i class="fas fa-search"></i>
                                Filtrer
                            </button>
                            
                            <a href="validate_submissions.php" class="instructor-btn instructor-btn-secondary">
                                <i class="fas fa-times"></i>
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Submissions List -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-file-alt"></i> Soumissions (<?= count($submissions) ?>)
                    </h3>
                </div>
                
                <?php if (count($submissions) === 0): ?>
                    <div style="padding: var(--spacing-8); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-file-alt" style="font-size: 3rem; margin-bottom: var(--spacing-4); opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                            Aucune soumission trouvée
                        </h3>
                        <p style="margin: 0; color: var(--gray-500);">
                            <?= $search || $filter_course || $filter_status ? 'Aucune soumission ne correspond à vos critères de recherche.' : 'Aucune soumission reçue pour l\'instant.' ?>
                        </p>
                        <?php if ($search || $filter_course || $filter_status): ?>
                            <a href="validate_submissions.php" class="instructor-btn instructor-btn-primary" style="margin-top: var(--spacing-4);">
                                <i class="fas fa-times"></i>
                                Réinitialiser les filtres
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-submission-grid">
                            <?php foreach ($submissions as $submission): ?>
                                <div class="instructor-submission-card">
                                    <div class="instructor-submission-header">
                                        <div class="instructor-submission-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="instructor-submission-info">
                                            <h4 class="instructor-submission-title">
                                                <?= htmlspecialchars($submission['title']) ?>
                                            </h4>
                                            <div class="instructor-submission-student">
                                                <?= htmlspecialchars($submission['student_name']) ?>
                                            </div>
                                        </div>
                                        <div class="instructor-submission-status">
                                            <?php if ($submission['status'] == 'pending'): ?>
                                                <span class="instructor-badge warning">En attente</span>
                                            <?php elseif ($submission['status'] == 'approved'): ?>
                                                <span class="instructor-badge success">Approuvée</span>
                                            <?php else: ?>
                                                <span class="instructor-badge danger">Rejetée</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="instructor-submission-content">
                                        <div class="instructor-submission-course">
                                            <i class="fas fa-book"></i>
                                            <?= htmlspecialchars($submission['course_title']) ?>
                                            <?php if ($submission['lesson_title']): ?>
                                                <span style="color: var(--gray-500);">
                                                    - <?= htmlspecialchars($submission['lesson_title']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
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
                                        <?php else: ?>
                                            <span style="
                                                color: var(--gray-500); 
                                                font-size: var(--font-size-sm);
                                                font-style: italic;
                                            ">
                                                <?= ucfirst($submission['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <a href="message_student.php?id=<?= $submission['student_id'] ?>" 
                                           class="instructor-btn instructor-btn-primary"
                                           style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-envelope"></i>
                                            Message
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
                <a href="students.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux étudiants
                </a>
                
                <a href="my_courses.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-book"></i>
                    Mes cours
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
