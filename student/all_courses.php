<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Search and filter functionality
$search = $_GET['search'] ?? '';
$price_filter = $_GET['price'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Build the query with filters
$where_conditions = ["c.status = 'published'", "c.is_active = 1"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($price_filter !== 'all') {
    switch ($price_filter) {
        case 'free':
            $where_conditions[] = "c.price = 0";
            break;
        case 'paid':
            $where_conditions[] = "c.price > 0";
            break;
        case 'low':
            $where_conditions[] = "c.price <= 50";
            break;
        case 'medium':
            $where_conditions[] = "c.price > 50 AND c.price <= 200";
            break;
        case 'high':
            $where_conditions[] = "c.price > 200";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Order by clause
$order_clause = match($sort_by) {
    'recent' => 'c.created_at DESC',
    'oldest' => 'c.created_at ASC',
    'price_low' => 'c.price ASC',
    'price_high' => 'c.price DESC',
    'name' => 'c.title ASC',
    'popular' => 'enrollment_count DESC',
    default => 'c.created_at DESC'
};

try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.price, c.created_at, 
               u.full_name AS instructor_name,
               (SELECT COUNT(*) FROM student_courses WHERE course_id = c.id) as enrollment_count,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count,
               CASE WHEN sc.student_id IS NOT NULL THEN 1 ELSE 0 END as is_enrolled
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        LEFT JOIN student_courses sc ON c.id = sc.course_id AND sc.student_id = ?
        WHERE $where_clause
        ORDER BY $order_clause
    ");
    $stmt->execute(array_merge([$student_id], $params));
    $courses = $stmt->fetchAll();

    // Get statistics
    $total_courses = count($courses);
    $free_courses = array_filter($courses, fn($c) => $c['price'] == 0);
    $paid_courses = array_filter($courses, fn($c) => $c['price'] > 0);
    $enrolled_courses = array_filter($courses, fn($c) => $c['is_enrolled'] == 1);

} catch (PDOException $e) {
    error_log("Database error in all_courses: " . $e->getMessage());
    $courses = [];
    $total_courses = 0;
    $free_courses = [];
    $paid_courses = [];
    $enrolled_courses = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Découvrir les Cours | TaaBia</title>
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
                <h1>Découvrir les Cours</h1>
                <p>Explorez notre collection de cours et développez vos compétences</p>
            </div>

            <!-- Statistics Cards -->
            <div class="student-cards">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Total des cours</div>
                    <div class="student-card-value"><?= $total_courses ?></div>
                    <div class="student-card-description">Cours disponibles</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-gift"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Cours gratuits</div>
                    <div class="student-card-value"><?= count($free_courses) ?></div>
                    <div class="student-card-description">Cours sans frais</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Cours payants</div>
                    <div class="student-card-value"><?= count($paid_courses) ?></div>
                    <div class="student-card-description">Cours premium</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Mes inscriptions</div>
                    <div class="student-card-value"><?= count($enrolled_courses) ?></div>
                    <div class="student-card-description">Cours suivis</div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="student-filters">
                <form method="GET" class="student-search">
                    <input 
                        type="text" 
                        name="search" 
                        placeholder="Rechercher un cours..." 
                        value="<?= htmlspecialchars($search) ?>"
                        class="student-search-input"
                    >
                    
                    <select name="price" class="student-filter-select">
                        <option value="all" <?= $price_filter === 'all' ? 'selected' : '' ?>>Tous les prix</option>
                        <option value="free" <?= $price_filter === 'free' ? 'selected' : '' ?>>Gratuits</option>
                        <option value="paid" <?= $price_filter === 'paid' ? 'selected' : '' ?>>Payants</option>
                        <option value="low" <?= $price_filter === 'low' ? 'selected' : '' ?>>≤ 50 GHS</option>
                        <option value="medium" <?= $price_filter === 'medium' ? 'selected' : '' ?>>51-200 GHS</option>
                        <option value="high" <?= $price_filter === 'high' ? 'selected' : '' ?>>> 200 GHS</option>
                    </select>
                    
                    <select name="sort" class="student-filter-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Plus anciens</option>
                        <option value="price_low" <?= $sort_by === 'price_low' ? 'selected' : '' ?>>Prix croissant</option>
                        <option value="price_high" <?= $sort_by === 'price_high' ? 'selected' : '' ?>>Prix décroissant</option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Nom A-Z</option>
                        <option value="popular" <?= $sort_by === 'popular' ? 'selected' : '' ?>>Plus populaires</option>
                    </select>
                    
                    <button type="submit" class="student-btn student-btn-primary">
                        <i class="fas fa-search"></i>
                        Rechercher
                    </button>
                    
                    <?php if (!empty($search) || $price_filter !== 'all' || $sort_by !== 'recent'): ?>
                        <a href="all_courses.php" class="student-btn student-btn-secondary">
                            <i class="fas fa-times"></i>
                            Réinitialiser
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Courses Grid -->
            <?php if (count($courses) > 0): ?>
                <div class="student-course-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="student-course-card">
                            <div class="student-course-image">
                                <i class="fas fa-graduation-cap"></i>
                                <?php if ($course['is_enrolled']): ?>
                                    <div style="position: absolute; top: var(--spacing-2); right: var(--spacing-2);">
                                        <span class="student-badge success">
                                            <i class="fas fa-check"></i> Inscrit
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="student-course-content">
                                <div class="student-course-title">
                                    <?= htmlspecialchars($course['title']) ?>
                                </div>
                                
                                <div class="student-course-instructor">
                                    <i class="fas fa-user-tie"></i>
                                    <?= htmlspecialchars($course['instructor_name']) ?>
                                </div>
                                
                                <div class="student-course-description">
                                    <?= htmlspecialchars(substr($course['description'], 0, 120)) ?>...
                                </div>
                                
                                <!-- Course Stats -->
                                <div style="display: flex; justify-content: space-between; align-items: center; margin: var(--spacing-4) 0; font-size: var(--font-size-sm); color: var(--gray-600);">
                                    <span>
                                        <i class="fas fa-users"></i>
                                        <?= $course['enrollment_count'] ?> inscrits
                                    </span>
                                    <span>
                                        <i class="fas fa-play-circle"></i>
                                        <?= $course['lesson_count'] ?> leçons
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= date('M Y', strtotime($course['created_at'])) ?>
                                    </span>
                                </div>
                                
                                <div class="student-course-footer">
                                    <div class="student-course-price">
                                        <?php if ($course['price'] == 0): ?>
                                            <span style="color: var(--success-color); font-weight: 600;">
                                                <i class="fas fa-gift"></i> Gratuit
                                            </span>
                                        <?php else: ?>
                                            <?= number_format($course['price'], 2) ?> GHS
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div style="display: flex; gap: var(--spacing-2);">
                                        <?php if ($course['is_enrolled']): ?>
                                            <a href="view_course.php?course_id=<?= $course['id'] ?>" 
                                               class="student-btn student-btn-success" 
                                               style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-xs);">
                                                <i class="fas fa-play"></i>
                                                Continuer
                                            </a>
                                        <?php else: ?>
                                            <a href="enroll.php?course_id=<?= $course['id'] ?>" 
                                               class="student-btn student-btn-primary" 
                                               style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-xs);">
                                                <i class="fas fa-plus"></i>
                                                S'inscrire
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="view_course.php?course_id=<?= $course['id'] ?>" 
                                           class="student-btn student-btn-secondary" 
                                           style="padding: var(--spacing-2) var(--spacing-3); font-size: var(--font-size-xs);">
                                            <i class="fas fa-eye"></i>
                                            Voir
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="student-empty">
                    <div class="student-empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="student-empty-title">
                        <?php if (!empty($search) || $price_filter !== 'all'): ?>
                            Aucun cours trouvé
                        <?php else: ?>
                            Aucun cours disponible
                        <?php endif; ?>
                    </div>
                    <div class="student-empty-description">
                        <?php if (!empty($search) || $price_filter !== 'all'): ?>
                            Essayez de modifier vos critères de recherche
                        <?php else: ?>
                            Revenez plus tard pour découvrir de nouveaux cours
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($search) || $price_filter !== 'all'): ?>
                        <a href="all_courses.php" class="student-btn student-btn-primary">
                            <i class="fas fa-times"></i>
                            Réinitialiser les filtres
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to course cards
            const courseCards = document.querySelectorAll('.student-course-card');
            courseCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Add smooth scrolling for better UX
            const links = document.querySelectorAll('a[href^="#"]');
            links.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth' });
                    }
                });
            });
        });
    </script>
</body>
</html>
