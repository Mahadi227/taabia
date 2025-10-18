<?php

/**
 * All Courses - Course Discovery Page
 * Modern LMS interface for browsing available courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Search and filter
$search = $_GET['search'] ?? '';
$price_filter = $_GET['price'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';
$category_filter = $_GET['category'] ?? 'all';

// Initialize
$courses = [];
$total_available = 0;
$free_count = 0;
$paid_count = 0;
$my_enrolled_count = 0;

try {
    error_log("=== ALL COURSES DEBUG ===");
    error_log("Student ID: $student_id");

    // Step 1: Get all active courses
    $where_conditions = ["c.is_active = 1"];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($price_filter === 'free') {
        $where_conditions[] = "c.price = 0";
    } elseif ($price_filter === 'paid') {
        $where_conditions[] = "c.price > 0";
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get course IDs first
    $stmt_ids = $pdo->prepare("SELECT id FROM courses c WHERE $where_clause");
    $stmt_ids->execute($params);
    $course_ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);

    error_log("Found " . count($course_ids) . " course IDs");

    // Step 2: Build course data
    foreach ($course_ids as $course_id) {
        try {
            // Get course details
            $stmt_course = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
            $stmt_course->execute([$course_id]);
            $course = $stmt_course->fetch();

            if (!$course) continue;

            // Get instructor name
            try {
                $stmt_instructor = $pdo->prepare("
                    SELECT full_name FROM users WHERE id = ?
                ");
                $stmt_instructor->execute([$course['instructor_id']]);
                $course['instructor_name'] = $stmt_instructor->fetchColumn() ?: 'Unknown';
            } catch (PDOException $e) {
                $course['instructor_name'] = 'Unknown';
            }

            // Count lessons
            try {
                $stmt_lessons = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
                $stmt_lessons->execute([$course_id]);
                $course['lesson_count'] = $stmt_lessons->fetchColumn();
            } catch (PDOException $e) {
                $course['lesson_count'] = 0;
            }

            // Count enrollments
            try {
                $stmt_enroll = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE course_id = ?");
                $stmt_enroll->execute([$course_id]);
                $course['enrollment_count'] = $stmt_enroll->fetchColumn();
            } catch (PDOException $e) {
                $course['enrollment_count'] = 0;
            }

            // Check if current student is enrolled
            try {
                $stmt_my_enroll = $pdo->prepare("
                    SELECT COUNT(*) FROM student_courses 
                    WHERE course_id = ? AND student_id = ?
                ");
                $stmt_my_enroll->execute([$course_id, $student_id]);
                $course['is_enrolled'] = $stmt_my_enroll->fetchColumn() > 0;
            } catch (PDOException $e) {
                $course['is_enrolled'] = false;
            }

            // Calculate average rating (if reviews table exists)
            try {
                $stmt_rating = $pdo->prepare("
                    SELECT AVG(rating) FROM course_reviews WHERE course_id = ?
                ");
                $stmt_rating->execute([$course_id]);
                $course['avg_rating'] = round($stmt_rating->fetchColumn() ?? 0, 1);
            } catch (PDOException $e) {
                $course['avg_rating'] = 0;
            }

            $courses[] = $course;
        } catch (PDOException $e) {
            error_log("Error processing course $course_id: " . $e->getMessage());
        }
    }

    error_log("Built " . count($courses) . " courses with full data");

    // Sorting
    $sort_functions = [
        'recent' => fn($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']),
        'oldest' => fn($a, $b) => strtotime($a['created_at']) - strtotime($b['created_at']),
        'price_low' => fn($a, $b) => $a['price'] - $b['price'],
        'price_high' => fn($a, $b) => $b['price'] - $a['price'],
        'name' => fn($a, $b) => strcmp($a['title'], $b['title']),
        'popular' => fn($a, $b) => $b['enrollment_count'] - $a['enrollment_count']
    ];

    if (isset($sort_functions[$sort_by])) {
        usort($courses, $sort_functions[$sort_by]);
    }

    // Calculate statistics
    $total_available = count($courses);
    $free_count = count(array_filter($courses, fn($c) => $c['price'] == 0));
    $paid_count = count(array_filter($courses, fn($c) => $c['price'] > 0));
    $my_enrolled_count = count(array_filter($courses, fn($c) => $c['is_enrolled']));

    error_log("Stats - Total: $total_available, Free: $free_count, Paid: $paid_count, Enrolled: $my_enrolled_count");
} catch (PDOException $e) {
    error_log("Error in all_courses.php: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('discover_courses') ?? 'Découvrir les Cours' ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #00408075;
            --primary-dark: #004085;
            --secondary: #004082;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            border-bottom: 1px solid var(--gray-200);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            margin-right: 0.75rem;
        }

        .nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), transparent);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto auto;
            gap: 1rem;
        }

        .form-input,
        .form-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Course Grid */
        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .course-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            position: relative;
        }

        .course-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
        }

        .course-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
        }

        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .enrolled-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--success);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .course-body {
            padding: 1.5rem;
        }

        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .course-instructor {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
        }

        .course-description {
            font-size: 0.875rem;
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .course-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: var(--gray-600);
        }

        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }

        .price.free {
            color: var(--success);
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray-600);
            margin-bottom: 2rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
            </div>

            <nav class="nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item active">
                    <i class="fas fa-compass"></i>
                    <?= __('discover_courses') ?? 'Découvrir' ?>
                </a>
                <a href="course_lessons.php" class="nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?? 'Mes Leçons' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Page Header -->
            <div class="page-header">
                <h1><?= __('discover_courses') ?? 'Découvrir les Cours' ?></h1>
                <p><?= __('explore_learn_grow') ?? 'Explorez notre catalogue et développez vos compétences' ?></p>

                <?php if (isset($error_message)): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-radius: 8px; color: #991b1b;">
                        ❌ <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-label"><?= __('available_courses') ?? 'Cours Disponibles' ?></div>
                    <div class="stat-value"><?= $total_available ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="stat-label"><?= __('free_courses') ?? 'Cours Gratuits' ?></div>
                    <div class="stat-value"><?= $free_count ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-label"><?= __('premium_courses') ?? 'Cours Premium' ?></div>
                    <div class="stat-value"><?= $paid_count ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-label"><?= __('my_enrollments') ?? 'Mes Inscriptions' ?></div>
                    <div class="stat-value"><?= $my_enrolled_count ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filters-form">
                    <input type="text" name="search" class="form-input"
                        placeholder="<?= __('search_courses') ?? 'Rechercher un cours...' ?>"
                        value="<?= htmlspecialchars($search) ?>">

                    <select name="price" class="form-select">
                        <option value="all" <?= $price_filter === 'all' ? 'selected' : '' ?>>
                            <?= __('all_prices') ?? 'Tous les prix' ?>
                        </option>
                        <option value="free" <?= $price_filter === 'free' ? 'selected' : '' ?>>
                            <?= __('free') ?? 'Gratuit' ?>
                        </option>
                        <option value="paid" <?= $price_filter === 'paid' ? 'selected' : '' ?>>
                            <?= __('paid') ?? 'Payant' ?>
                        </option>
                    </select>

                    <select name="sort" class="form-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>
                            <?= __('newest') ?? 'Plus récents' ?>
                        </option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>
                            <?= __('oldest') ?? 'Plus anciens' ?>
                        </option>
                        <option value="price_low" <?= $sort_by === 'price_low' ? 'selected' : '' ?>>
                            <?= __('price_low_high') ?? 'Prix croissant' ?>
                        </option>
                        <option value="price_high" <?= $sort_by === 'price_high' ? 'selected' : '' ?>>
                            <?= __('price_high_low') ?? 'Prix décroissant' ?>
                        </option>
                        <option value="popular" <?= $sort_by === 'popular' ? 'selected' : '' ?>>
                            <?= __('most_popular') ?? 'Plus populaires' ?>
                        </option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>
                            <?= __('alphabetical') ?? 'A-Z' ?>
                        </option>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?= __('search') ?? 'Rechercher' ?>
                    </button>

                    <?php if (!empty($search) || $price_filter !== 'all' || $sort_by !== 'recent'): ?>
                        <a href="all_courses.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?= __('reset') ?? 'Réinitialiser' ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Courses Grid -->
            <?php if (!empty($courses)): ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-image">
                                <?php if (!empty($course['image_url'])): ?>
                                    <img src="../uploads/<?= htmlspecialchars($course['image_url']) ?>"
                                        alt="<?= htmlspecialchars($course['title']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-book"></i>
                                <?php endif; ?>

                                <?php if ($course['is_enrolled']): ?>
                                    <div class="enrolled-badge">
                                        <i class="fas fa-check"></i> <?= __('enrolled') ?? 'Inscrit' ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="course-body">
                                <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>

                                <div class="course-instructor">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor_name']) ?>
                                </div>

                                <p class="course-description">
                                    <?= htmlspecialchars($course['description'] ?? '') ?>
                                </p>

                                <div class="course-meta">
                                    <span>
                                        <i class="fas fa-users"></i>
                                        <?= $course['enrollment_count'] ?> <?= __('students') ?? 'étudiants' ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-play-circle"></i>
                                        <?= $course['lesson_count'] ?> <?= __('lessons') ?? 'leçons' ?>
                                    </span>
                                    <?php if ($course['avg_rating'] > 0): ?>
                                        <span>
                                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                                            <?= $course['avg_rating'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="course-footer">
                                    <div class="price <?= $course['price'] == 0 ? 'free' : '' ?>">
                                        <?php if ($course['price'] == 0): ?>
                                            <i class="fas fa-gift"></i> <?= __('free') ?? 'Gratuit' ?>
                                        <?php else: ?>
                                            <?= number_format($course['price'], 2) ?> GHS
                                        <?php endif; ?>
                                    </div>

                                    <div class="actions">
                                        <?php if ($course['is_enrolled']): ?>
                                            <a href="view_course.php?course_id=<?= $course['id'] ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-play"></i> <?= __('continue') ?? 'Continuer' ?>
                                            </a>
                                        <?php else: ?>
                                            <a href="view_course.php?course_id=<?= $course['id'] ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-eye"></i> <?= __('view') ?? 'Voir' ?>
                                            </a>
                                            <a href="enroll.php?course_id=<?= $course['id'] ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> <?= __('enroll') ?? 'S\'inscrire' ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>
                        <?= (!empty($search) || $price_filter !== 'all')
                            ? (__('no_courses_found') ?? 'Aucun cours trouvé')
                            : (__('no_courses_available') ?? 'Aucun cours disponible') ?>
                    </h3>
                    <p>
                        <?= (!empty($search) || $price_filter !== 'all')
                            ? (__('try_different_filters') ?? 'Essayez de modifier vos critères de recherche')
                            : (__('check_back_soon') ?? 'Revenez bientôt pour de nouveaux cours') ?>
                    </p>
                    <?php if (!empty($search) || $price_filter !== 'all'): ?>
                        <a href="all_courses.php" class="btn btn-primary">
                            <i class="fas fa-times"></i> <?= __('clear_filters') ?? 'Réinitialiser les filtres' ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on scroll
            const cards = document.querySelectorAll('.course-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>

</html>