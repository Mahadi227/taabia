<?php

/**
 * My Courses Page - Modern LMS
 * Display all enrolled courses with progress tracking
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Initialize variables
$all_courses = [];
$courses = [];
$total_enrolled = 0;
$completed_count = 0;
$in_progress_count = 0;
$not_started_count = 0;
$avg_progress = 0;

try {
    // Ultra simple query first - just get course IDs
    $search_param = "%{$search}%";

    error_log("=== MY COURSES DEBUG START ===");
    error_log("Student ID: " . $student_id);

    // Step 1: Get course IDs from student_courses
    $stmt_ids = $pdo->prepare("SELECT course_id FROM student_courses WHERE student_id = ?");
    $stmt_ids->execute([$student_id]);
    $course_ids = $stmt_ids->fetchAll(PDO::FETCH_COLUMN);

    error_log("Step 1: Found " . count($course_ids) . " course IDs: " . implode(', ', $course_ids));

    if (empty($course_ids)) {
        $all_courses = [];
        error_log("No courses found in student_courses");
    } else {
        // Step 2: Get course details for each ID
        $all_courses = [];
        foreach ($course_ids as $course_id) {
            try {
                $stmt_course = $pdo->prepare("
                    SELECT 
                        c.id, 
                        c.title, 
                        COALESCE(c.description, '') as description, 
                        COALESCE(c.image_url, '') as image_url, 
                        COALESCE(c.price, 0) as price
                    FROM courses c
                    WHERE c.id = ?
                ");
                $stmt_course->execute([$course_id]);
                $course = $stmt_course->fetch();

                if ($course) {
                    // Get instructor name
                    try {
                        $stmt_instructor = $pdo->prepare("
                            SELECT u.full_name 
                            FROM users u 
                            JOIN courses c ON u.id = c.instructor_id 
                            WHERE c.id = ?
                        ");
                        $stmt_instructor->execute([$course_id]);
                        $instructor = $stmt_instructor->fetchColumn();
                        $course['instructor_name'] = $instructor ?: 'Unknown';
                    } catch (PDOException $e) {
                        $course['instructor_name'] = 'Unknown';
                    }

                    $course['enrolled_at'] = date('Y-m-d H:i:s');

                    // Apply search filter
                    if (
                        empty($search) ||
                        stripos($course['title'], $search) !== false ||
                        stripos($course['description'], $search) !== false
                    ) {
                        $all_courses[] = $course;
                    }
                }
            } catch (PDOException $e) {
                error_log("Error fetching course $course_id: " . $e->getMessage());
            }
        }

        error_log("Step 2: Built " . count($all_courses) . " courses with details");
    }

    // Calculate progress for each course
    foreach ($all_courses as &$course) {
        // Count total lessons
        $stmt_lessons = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
        $stmt_lessons->execute([$course['id']]);
        $course['total_lessons'] = $stmt_lessons->fetchColumn();

        // Count viewed lessons (any record in lesson_progress = viewed)
        try {
            $stmt_progress = $pdo->prepare("
                SELECT COUNT(DISTINCT lp.lesson_id)
                FROM lesson_progress lp
                JOIN lessons l ON lp.lesson_id = l.id
                WHERE l.course_id = ? AND lp.student_id = ?
            ");
            $stmt_progress->execute([$course['id'], $student_id]);
            $course['completed_lessons'] = $stmt_progress->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error counting completed lessons: " . $e->getMessage());
            $course['completed_lessons'] = 0;
        }

        // Calculate percentage
        $course['progress_percent'] = $course['total_lessons'] > 0
            ? round(($course['completed_lessons'] / $course['total_lessons']) * 100)
            : 0;

        // Determine status
        if ($course['progress_percent'] >= 100) {
            $course['progress_status'] = 'completed';
        } elseif ($course['progress_percent'] > 0) {
            $course['progress_status'] = 'in_progress';
        } else {
            $course['progress_status'] = 'not_started';
        }
    }
    unset($course);

    // Count TOTAL stats from ALL courses (before filtering)
    $total_enrolled = count($all_courses);
    $completed_count = count(array_filter($all_courses, fn($c) => $c['progress_status'] === 'completed'));
    $in_progress_count = count(array_filter($all_courses, fn($c) => $c['progress_status'] === 'in_progress'));
    $not_started_count = count(array_filter($all_courses, fn($c) => $c['progress_status'] === 'not_started'));

    // Apply status filter for DISPLAY
    if ($status_filter !== 'all') {
        $courses = array_filter($all_courses, function ($course) use ($status_filter) {
            return $course['progress_status'] === $status_filter;
        });
    } else {
        $courses = $all_courses;
    }

    // Sorting
    $sort_functions = [
        'recent' => fn($a, $b) => strtotime($b['enrolled_at']) - strtotime($a['enrolled_at']),
        'oldest' => fn($a, $b) => strtotime($a['enrolled_at']) - strtotime($b['enrolled_at']),
        'progress' => fn($a, $b) => $b['progress_percent'] - $a['progress_percent'],
        'name' => fn($a, $b) => strcmp($a['title'], $b['title'])
    ];

    if (isset($sort_functions[$sort_by])) {
        usort($courses, $sort_functions[$sort_by]);
    }

    // Calculate average progress from ALL courses (not filtered)
    $avg_progress = !empty($all_courses)
        ? round(array_sum(array_column($all_courses, 'progress_percent')) / count($all_courses))
        : 0;

    // Displayed courses count
    $total_courses = count($courses);

    // Log for debugging
    error_log("My Courses - Student: $student_id, Total Enrolled: $total_enrolled, Completed: $completed_count, In Progress: $in_progress_count, Not Started: $not_started_count, Avg: $avg_progress%");
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    error_log("Database error in my_courses.php: " . $e->getMessage());
    // Initialize with defaults if error
    $all_courses = [];
    $courses = [];
    $total_enrolled = 0;
    $completed_count = 0;
    $in_progress_count = 0;
    $not_started_count = 0;
    $avg_progress = 0;
    $total_courses = 0;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_courses') ?? 'Mes Cours' ?> | TaaBia</title>

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004082;
            --primary-dark: #004085;
            --secondary: #004075;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
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
            color: var(--gray-800);
        }

        .page-container {
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
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
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
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        /* Main Content */
        .main-content {
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            color: white;
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

        .stat-icon.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
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
        .filters-section {
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
            align-items: center;
        }

        .form-input,
        .form-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
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

        .btn-secondary:hover {
            background: var(--gray-300);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
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
        }

        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
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
        }

        .course-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 1rem;
        }

        .progress-section {
            margin: 1.5rem 0;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .progress-value {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--primary);
        }

        .progress-bar-container {
            height: 8px;
            background: var(--gray-200);
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 10px;
            transition: width 0.8s ease;
        }

        .course-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-completed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-in_progress {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-not_started {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray-400);
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

            .courses-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="page-container">
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
                <a href="my_courses.php" class="nav-item active">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item">
                    <i class="fas fa-search"></i>
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
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1><?= __('my_courses') ?? 'Mes Cours' ?></h1>
                <p><?= __('my_courses_subtitle') ?? 'Gérez et suivez votre progression dans tous vos cours' ?></p>

                <!-- Debug Info (temporary) -->
                <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 8px; font-size: 0.875rem;">
                    <strong>🔍 Debug:</strong>
                    All Courses: <?= count($all_courses) ?> |
                    Displayed: <?= count($courses) ?> |
                    Enrolled: <?= $total_enrolled ?> |
                    Completed: <?= $completed_count ?> |
                    In Progress: <?= $in_progress_count ?> |
                    Not Started: <?= $not_started_count ?> |
                    Avg: <?= $avg_progress ?>%
                    <?php if (isset($error_message)): ?>
                        <br><span style="color: red;">❌ <?= htmlspecialchars($error_message) ?></span>
                    <?php endif; ?>
                    <br>
                    <a href="debug_my_courses.php" style="color: #6366f1; text-decoration: underline;">View Detailed Debug →</a>
                </div>
            </div>

            <!-- Statistics - Always show TOTAL stats, not filtered -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-label"><?= __('total_courses') ?? 'Total des Cours' ?></div>
                    <div class="stat-value"><?= $total_enrolled ?></div>
                    <small style="color: var(--gray-600); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        <?= __('courses_enrolled') ?? 'Cours inscrits' ?>
                    </small>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-label"><?= __('completed') ?? 'Terminés' ?></div>
                    <div class="stat-value"><?= $completed_count ?></div>
                    <small style="color: var(--gray-600); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        <?= $total_enrolled > 0 ? round(($completed_count / $total_enrolled) * 100) : 0 ?>% <?= __('of_total') ?? 'du total' ?>
                    </small>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-label"><?= __('in_progress') ?? 'En Cours' ?></div>
                    <div class="stat-value"><?= $in_progress_count ?></div>
                    <small style="color: var(--gray-600); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        <?= $total_enrolled > 0 ? round(($in_progress_count / $total_enrolled) * 100) : 0 ?>% <?= __('of_total') ?? 'du total' ?>
                    </small>
                </div>

                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-hourglass-start"></i>
                    </div>
                    <div class="stat-label"><?= __('not_started') ?? 'Non Commencés' ?></div>
                    <div class="stat-value"><?= $not_started_count ?></div>
                    <small style="color: var(--gray-600); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        <?= $total_enrolled > 0 ? round(($not_started_count / $total_enrolled) * 100) : 0 ?>% <?= __('of_total') ?? 'du total' ?>
                    </small>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-label"><?= __('average_progress') ?? 'Progression Moyenne' ?></div>
                    <div class="stat-value"><?= $avg_progress ?>%</div>
                    <small style="color: var(--gray-600); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                        <?= __('across_all_courses') ?? 'Sur tous les cours' ?>
                    </small>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" class="filters-form">
                    <input type="text" name="search" class="form-input"
                        placeholder="<?= __('search_courses') ?? 'Rechercher un cours...' ?>"
                        value="<?= htmlspecialchars($search) ?>">

                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>
                            <?= __('all_status') ?? 'Tous les statuts' ?>
                        </option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>
                            <?= __('completed') ?? 'Terminés' ?>
                        </option>
                        <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>
                            <?= __('in_progress') ?? 'En cours' ?>
                        </option>
                        <option value="not_started" <?= $status_filter === 'not_started' ? 'selected' : '' ?>>
                            <?= __('not_started') ?? 'Non commencés' ?>
                        </option>
                    </select>

                    <select name="sort" class="form-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>
                            <?= __('most_recent') ?? 'Plus récents' ?>
                        </option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>
                            <?= __('oldest') ?? 'Plus anciens' ?>
                        </option>
                        <option value="progress" <?= $sort_by === 'progress' ? 'selected' : '' ?>>
                            <?= __('by_progress') ?? 'Par progression' ?>
                        </option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>
                            <?= __('by_name') ?? 'Par nom' ?>
                        </option>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?= __('search') ?? 'Rechercher' ?>
                    </button>

                    <?php if (!empty($search) || $status_filter !== 'all' || $sort_by !== 'recent'): ?>
                        <a href="my_courses.php" class="btn btn-secondary">
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
                                <?php if ($course['image_url']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($course['image_url']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-book"></i>
                                <?php endif; ?>
                            </div>

                            <div class="course-body">
                                <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>

                                <div class="course-instructor">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor_name']) ?>
                                </div>

                                <p class="course-description">
                                    <?= htmlspecialchars(substr($course['description'] ?? '', 0, 120)) ?>...
                                </p>

                                <div class="course-meta">
                                    <span><i class="fas fa-play-circle"></i> <?= $course['total_lessons'] ?> <?= __('lessons') ?? 'leçons' ?></span>
                                    <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($course['enrolled_at'])) ?></span>
                                </div>

                                <div class="progress-section">
                                    <div class="progress-header">
                                        <span class="progress-label"><?= __('progress') ?? 'Progression' ?></span>
                                        <span class="progress-value"><?= $course['progress_percent'] ?>%</span>
                                    </div>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" data-progress="<?= $course['progress_percent'] ?>"></div>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--gray-600); margin-top: 0.5rem;">
                                        <?= $course['completed_lessons'] ?> / <?= $course['total_lessons'] ?> <?= __('lessons_completed') ?? 'leçons complétées' ?>
                                    </div>
                                </div>

                                <div class="course-footer">
                                    <span class="badge badge-<?= $course['progress_status'] ?>">
                                        <?php
                                        echo match ($course['progress_status']) {
                                            'completed' => __('completed') ?? 'Terminé',
                                            'in_progress' => __('in_progress') ?? 'En cours',
                                            'not_started' => __('not_started') ?? 'Non commencé',
                                            default => ''
                                        };
                                        ?>
                                    </span>

                                    <div class="action-buttons">
                                        <a href="view_course.php?course_id=<?= $course['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-play"></i> <?= __('continue') ?? 'Continuer' ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3><?= (!empty($search) || $status_filter !== 'all') ? (__('no_courses_found') ?? 'Aucun cours trouvé') : (__('no_courses_enrolled') ?? 'Aucun cours inscrit') ?></h3>
                    <p><?= (!empty($search) || $status_filter !== 'all') ? (__('try_different_filters') ?? 'Essayez de modifier vos critères de recherche') : (__('start_learning') ?? 'Commencez votre parcours d\'apprentissage') ?></p>
                    <a href="all_courses.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?= __('discover_courses') ?? 'Découvrir les cours' ?>
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars
            const progressBars = document.querySelectorAll('.progress-bar');
            progressBars.forEach(bar => {
                const progress = bar.getAttribute('data-progress');
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = progress + '%';
                }, 200);
            });

            // Animate cards
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