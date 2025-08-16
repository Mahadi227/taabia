<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Initialisation
$courses = [];
$total_courses = 0;
$completed_courses = [];
$in_progress_courses = [];
$not_started_courses = [];
$avg_progress = 0;

// Search and filter functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Build query
$where_conditions = ["sc.student_id = ?"];
$params = [$student_id];

if (!empty($search)) {
    $where_conditions[] = "c.title LIKE ?";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Sorting
$order_clause = match($sort_by) {
    'recent' => 'sc.enrolled_at DESC',
    'oldest' => 'sc.enrolled_at ASC',
    'progress' => 'sc.progress_percent DESC',
    'name' => 'c.title ASC',
    default => 'sc.enrolled_at DESC'
};

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.status, c.price, 
               sc.enrolled_at, sc.progress_percent, sc.last_accessed,
               u.full_name AS instructor_name,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
        FROM student_courses sc
        JOIN courses c ON sc.course_id = c.id
        JOIN users u ON c.instructor_id = u.id
        WHERE $where_clause
        ORDER BY $order_clause
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll();

    // Stats
    $total_courses = count($courses);
    $completed_courses = array_filter($courses, fn($c) => isset($c['progress_percent']) && $c['progress_percent'] >= 100);
    $in_progress_courses = array_filter($courses, fn($c) => isset($c['progress_percent']) && $c['progress_percent'] > 0 && $c['progress_percent'] < 100);
    $not_started_courses = array_filter($courses, fn($c) => isset($c['progress_percent']) && $c['progress_percent'] == 0);

    $avg_progress = $total_courses > 0
        ? round(array_sum(array_column($courses, 'progress_percent')) / $total_courses)
        : 0;

} catch (PDOException $e) {
    error_log("Database error in my_courses.php: " . $e->getMessage());
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
            <a href="index.php" class="student-nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
            <a href="all_courses.php" class="student-nav-item"><i class="fas fa-book-open"></i> Découvrir les cours</a>
            <a href="my_courses.php" class="student-nav-item active"><i class="fas fa-graduation-cap"></i> Mes cours</a>
            <a href="course_lessons.php" class="student-nav-item"><i class="fas fa-play-circle"></i> Mes leçons</a>
            <a href="orders.php" class="student-nav-item"><i class="fas fa-shopping-cart"></i> Mes achats</a>
            <a href="messages.php" class="student-nav-item"><i class="fas fa-envelope"></i> Messages</a>
            <a href="profile.php" class="student-nav-item"><i class="fas fa-user"></i> Mon profil</a>
            <a href="../auth/logout.php" class="student-nav-item"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="student-main">
        <div class="student-header">
            <h1>Mes Cours</h1>
            <p>Gérez et suivez votre progression dans tous vos cours</p>
        </div>

        <!-- Statistiques -->
        <div class="student-cards">
            <div class="student-card">
                <div class="student-card-header"><div class="student-card-icon primary"><i class="fas fa-book"></i></div></div>
                <div class="student-card-title">Total des cours</div>
                <div class="student-card-value"><?= $total_courses ?></div>
                <div class="student-card-description">Cours auxquels vous êtes inscrit</div>
            </div>

            <div class="student-card">
                <div class="student-card-header"><div class="student-card-icon success"><i class="fas fa-check-circle"></i></div></div>
                <div class="student-card-title">Cours terminés</div>
                <div class="student-card-value"><?= count($completed_courses) ?></div>
                <div class="student-card-description">Cours avec 100% de progression</div>
            </div>

            <div class="student-card">
                <div class="student-card-header"><div class="student-card-icon warning"><i class="fas fa-play-circle"></i></div></div>
                <div class="student-card-title">En cours</div>
                <div class="student-card-value"><?= count($in_progress_courses) ?></div>
                <div class="student-card-description">Cours en cours de progression</div>
            </div>

            <div class="student-card">
                <div class="student-card-header"><div class="student-card-icon danger"><i class="fas fa-hourglass-start"></i></div></div>
                <div class="student-card-title">Non commencés</div>
                <div class="student-card-value"><?= count($not_started_courses) ?></div>
                <div class="student-card-description">Cours non démarrés</div>
            </div>

            <div class="student-card">
                <div class="student-card-header"><div class="student-card-icon info"><i class="fas fa-chart-line"></i></div></div>
                <div class="student-card-title">Progression moyenne</div>
                <div class="student-card-value"><?= $avg_progress ?>%</div>
                <div class="student-card-description">Progression globale</div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="student-filters">
            <form method="GET" class="student-search">
                <input type="text" name="search" placeholder="Rechercher un cours..." value="<?= htmlspecialchars($search) ?>" class="student-search-input">
                <select name="status" class="student-filter-select">
                    <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                    <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>>Publié</option>
                    <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                    <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                </select>
                <select name="sort" class="student-filter-select">
                    <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                    <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Plus anciens</option>
                    <option value="progress" <?= $sort_by === 'progress' ? 'selected' : '' ?>>Progression</option>
                    <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Nom A-Z</option>
                </select>
                <button type="submit" class="student-btn student-btn-primary"><i class="fas fa-search"></i> Rechercher</button>
                <?php if (!empty($search) || $status_filter !== 'all' || $sort_by !== 'recent'): ?>
                    <a href="my_courses.php" class="student-btn student-btn-secondary"><i class="fas fa-times"></i> Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Affichage des cours -->
        <?php if (count($courses) > 0): ?>
            <div class="student-course-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="student-course-card">
                        <div class="student-course-image"><i class="fas fa-graduation-cap"></i></div>
                        <div class="student-course-content">
                            <div class="student-course-title"><?= htmlspecialchars($course['title']) ?></div>
                            <div class="student-course-instructor"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($course['instructor_name']) ?></div>
                            <div class="student-course-description"><?= htmlspecialchars(substr($course['description'], 0, 120)) ?>...</div>

                            <div style="margin: var(--spacing-4) 0;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-2);">
                                    <span style="font-size: var(--font-size-sm); color: var(--gray-600);">Progression</span>
                                    <span style="font-weight: 600; color: var(--primary-color);"><?= (int)$course['progress_percent'] ?>%</span>
                                </div>
                                <div class="student-progress">
                                    <div class="student-progress-bar" style="width: <?= (int)$course['progress_percent'] ?>%;"></div>
                                </div>
                                <div style="font-size: var(--font-size-xs); color: var(--gray-500); margin-top: var(--spacing-1);">
                                    <?= $course['lesson_count'] ?> leçons • Inscrit le <?= date('d/m/Y', strtotime($course['enrolled_at'])) ?>
                                </div>
                            </div>

                            <div class="student-course-footer">
                                <span class="student-badge <?= $course['status'] ?>"><?= ucfirst($course['status']) ?></span>
                                <div style="display: flex; gap: var(--spacing-2);">
                                    <a href="view_course.php?course_id=<?= $course['id'] ?>" class="student-btn student-btn-primary" style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-xs);"><i class="fas fa-play"></i> Continuer</a>
                                    <a href="course_lessons.php?course_id=<?= $course['id'] ?>" class="student-btn student-btn-secondary" style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-xs);"><i class="fas fa-list"></i> Leçons</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="student-empty">
                <div class="student-empty-icon"><i class="fas fa-book-open"></i></div>
                <div class="student-empty-title">
                    <?= (!empty($search) || $status_filter !== 'all') ? "Aucun cours trouvé" : "Aucun cours inscrit" ?>
                </div>
                <div class="student-empty-description">
                    <?= (!empty($search) || $status_filter !== 'all') ? "Essayez de modifier vos critères de recherche" : "Commencez par explorer nos cours disponibles" ?>
                </div>
                <a href="all_courses.php" class="student-btn student-btn-primary"><i class="fas fa-search"></i> Découvrir les cours</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Animation des progress bars
    const bars = document.querySelectorAll('.student-progress-bar');
    bars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => { bar.style.width = width; }, 300);
    });
});
</script>
</body>
</html>
