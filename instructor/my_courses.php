<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Search and filter functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Build the query with filters
$where_conditions = ["c.instructor_id = ?"];
$params = [$instructor_id];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Order by clause
$order_clause = match($sort_by) {
    'recent' => 'c.created_at DESC',
    'oldest' => 'c.created_at ASC',
    'name' => 'c.title ASC',
    'enrollments' => 'enrollment_count DESC',
    'progress' => 'avg_progress DESC',
    default => 'c.created_at DESC'
};

try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(sc.student_id) as enrollment_count,
               AVG(sc.progress_percent) as avg_progress,
               COUNT(l.id) as lesson_count,
               CASE 
                               WHEN c.status = 'published' THEN 'success'
            WHEN c.status = 'draft' THEN 'warning'
            WHEN c.status = 'archived' THEN 'danger'
                   ELSE 'info'
               END as status_class
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        LEFT JOIN lessons l ON c.id = l.course_id
        WHERE $where_clause
        GROUP BY c.id
        ORDER BY $order_clause
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll();

    // Get statistics
    $total_courses = count($courses);
    $published_courses = array_filter($courses, fn($c) => $c['status'] == 'published');
    $draft_courses = array_filter($courses, fn($c) => $c['status'] == 'draft');
    $archived_courses = array_filter($courses, fn($c) => $c['status'] == 'archived');
    
    $total_enrollments = array_sum(array_column($courses, 'enrollment_count'));
    $avg_progress = $total_courses > 0 ? array_sum(array_column($courses, 'avg_progress')) / $total_courses : 0;

} catch (PDOException $e) {
    error_log("Database error in my_courses: " . $e->getMessage());
    $courses = [];
    $total_courses = 0;
    $active_courses = [];
    $draft_courses = [];
    $inactive_courses = [];
    $total_enrollments = 0;
    $avg_progress = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Cours | TaaBia</title>
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
                <a href="my_courses.php" class="instructor-nav-item active">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    Nouveau cours
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
                <h1>Mes Cours</h1>
                <p>Gérez vos formations et suivez les performances</p>
            </div>

            <!-- Statistics Cards -->
            <div class="instructor-cards">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Total des cours</div>
                    <div class="instructor-card-value"><?= $total_courses ?></div>
                    <div class="instructor-card-description">Cours créés</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Cours publiés</div>
                    <div class="instructor-card-value"><?= count($published_courses) ?></div>
                    <div class="instructor-card-description">Cours publiés</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-edit"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Brouillons</div>
                    <div class="instructor-card-value"><?= count($draft_courses) ?></div>
                    <div class="instructor-card-description">En cours de création</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Total inscrits</div>
                    <div class="instructor-card-value"><?= $total_enrollments ?></div>
                    <div class="instructor-card-description">Étudiants inscrits</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="instructor-filters">
                <form method="GET" class="instructor-search">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Rechercher un cours..." 
                        value="<?= htmlspecialchars($search) ?>"
                        class="instructor-search-input"
                    >
                    
                    <select name="status" class="instructor-filter-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                                                        <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>>Publiés</option>
                                <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Brouillons</option>
                                <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>>Archivés</option>
                    </select>
                    
                    <select name="sort" class="instructor-filter-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Plus anciens</option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Nom A-Z</option>
                        <option value="enrollments" <?= $sort_by === 'enrollments' ? 'selected' : '' ?>>Plus d'inscriptions</option>
                        <option value="progress" <?= $sort_by === 'progress' ? 'selected' : '' ?>>Meilleure progression</option>
                    </select>
                    
                    <button type="submit" class="instructor-btn instructor-btn-primary">
                        <i class="fas fa-search"></i>
                        Rechercher
                    </button>
                    
                    <?php if (!empty($search) || $status_filter !== 'all' || $sort_by !== 'recent'): ?>
                        <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            Réinitialiser
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Quick Actions -->
            <div style="margin-bottom: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-plus"></i>
                    Créer un nouveau cours
                </a>
                
                <a href="course_stats.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-chart-bar"></i>
                    Statistiques détaillées
                </a>
            </div>

            <!-- Courses Grid -->
            <?php if (count($courses) > 0): ?>
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
                                    <span class="instructor-badge <?= $course['status_class'] ?>">
                                        <?= ucfirst($course['status']) ?>
                                    </span>
                                </div>
                                
                                <p class="instructor-course-description">
                                    <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>...
                                </p>
                                
                                <div class="instructor-course-stats">
                                    <span>
                                        <i class="fas fa-users"></i>
                                        <?= $course['enrollment_count'] ?> inscrits
                                    </span>
                                    <span>
                                        <i class="fas fa-play-circle"></i>
                                        <?= $course['lesson_count'] ?> leçons
                                    </span>
                                    <span>
                                        <i class="fas fa-chart-line"></i>
                                        <?= round($course['avg_progress'] ?? 0) ?>% progression
                                    </span>
                                </div>
                                
                                <div class="instructor-course-footer">
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                        Créé le <?= date('d/m/Y', strtotime($course['created_at'])) ?>
                                    </div>
                                    
                                    <div style="display: flex; gap: var(--spacing-2);">
                                        <a href="edit_course.php?id=<?= $course['id'] ?>" 
                                           class="instructor-btn instructor-btn-secondary"
                                           style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-edit"></i>
                                            Modifier
                                        </a>
                                        
                                        <a href="course_lessons.php?course_id=<?= $course['id'] ?>" 
                                           class="instructor-btn instructor-btn-primary"
                                           style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-play"></i>
                                            Leçons
                                        </a>
                                        
                                        <a href="course_students.php?course_id=<?= $course['id'] ?>" 
                                           class="instructor-btn instructor-btn-success"
                                           style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-users"></i>
                                            Étudiants
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="instructor-empty">
                    <div class="instructor-empty-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="instructor-empty-title">
                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                            Aucun cours trouvé
                        <?php else: ?>
                            Aucun cours créé
                        <?php endif; ?>
                    </div>
                    <div class="instructor-empty-description">
                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                            Essayez de modifier vos critères de recherche
                        <?php else: ?>
                            Commencez par créer votre premier cours
                        <?php endif; ?>
                    </div>
                    <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                        <i class="fas fa-plus"></i>
                        Créer un cours
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to course cards
            const courseCards = document.querySelectorAll('.instructor-course-card');
            courseCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                    this.style.boxShadow = 'var(--shadow-lg)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'var(--shadow-md)';
                });
            });
        });
    </script>
</body>
</html>
