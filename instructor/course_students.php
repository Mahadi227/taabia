<?php

/**
 * Course Students Management - Enhanced Version
 * 
 * Modern, feature-rich student management interface with advanced analytics,
 * real-time updates, and comprehensive student tracking capabilities.
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Validate course_id parameter
if (!isset($_GET['course_id']) || !is_numeric($_GET['course_id'])) {
    flash_message(__('invalid_course') ?? 'Cours invalide', 'error');
    redirect('my_courses.php');
}

$course_id = (int)$_GET['course_id'];

// Get course information with enhanced data
try {
    $course_stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(sc.student_id) as total_enrollments,
               AVG(sc.progress_percent) as avg_progress,
               COUNT(CASE WHEN sc.progress_percent >= 100 THEN 1 END) as completed_count,
               COUNT(CASE WHEN sc.progress_percent > 0 AND sc.progress_percent < 100 THEN 1 END) as active_count
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.id = ? AND c.instructor_id = ?
        GROUP BY c.id
    ");
    $course_stmt->execute([$course_id, $instructor_id]);
    $course = $course_stmt->fetch();

    if (!$course) {
        flash_message(__('course_not_found') ?? 'Cours non trouvé', 'error');
        redirect('my_courses.php');
    }
} catch (PDOException $e) {
    error_log("Database error in course_students.php: " . $e->getMessage());
    flash_message(__('database_error') ?? 'Erreur de base de données', 'error');
    redirect('my_courses.php');
}

// Get students with enhanced information
$students = [];
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'enrolled_at';
$sort_order = $_GET['order'] ?? 'desc';

try {
    $where_conditions = ["sc.course_id = ?"];
    $params = [$course_id];

    // Add search filter
    if (!empty($search_term)) {
        $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search_term%";
        $params[] = "%$search_term%";
    }

    // Add status filter
    if ($status_filter !== 'all') {
        switch ($status_filter) {
            case 'completed':
                $where_conditions[] = "sc.progress_percent >= 100";
                break;
            case 'active':
                $where_conditions[] = "sc.progress_percent > 0 AND sc.progress_percent < 100";
                break;
            case 'inactive':
                $where_conditions[] = "sc.progress_percent = 0";
                break;
        }
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Determine sort column
    $sort_columns = [
        'name' => 'u.full_name',
        'email' => 'u.email',
        'progress' => 'sc.progress_percent',
        'enrolled_at' => 'sc.enrolled_at',
        'last_accessed' => 'sc.last_accessed'
    ];
    $sort_column = $sort_columns[$sort_by] ?? 'sc.enrolled_at';

    $students_stmt = $pdo->prepare("
        SELECT sc.*, 
               u.full_name, 
               u.email, 
               u.phone, 
               u.created_at as user_created_at,
               sc.enrolled_at,
               sc.progress_percent,
               sc.last_accessed,
               sc.completed_at,
               CASE 
                   WHEN sc.progress_percent >= 100 THEN 'completed'
                   WHEN sc.progress_percent > 0 THEN 'active'
                   ELSE 'inactive'
               END as status
        FROM student_courses sc
        JOIN users u ON sc.student_id = u.id
        WHERE $where_clause
        ORDER BY $sort_column $sort_order
    ");

    $students_stmt->execute($params);
    $students = $students_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error fetching students: " . $e->getMessage());
    $students = [];
}

// Calculate enhanced statistics from real data
$total_students = count($students);
$completed_students = array_filter($students, fn($s) => ($s['progress_percent'] ?? 0) >= 100);
$active_students = array_filter($students, fn($s) => ($s['progress_percent'] ?? 0) > 0 && ($s['progress_percent'] ?? 0) < 100);
$inactive_students = array_filter($students, fn($s) => ($s['progress_percent'] ?? 0) == 0);

$completion_rate = $total_students > 0 ? round((count($completed_students) / $total_students) * 100, 1) : 0;
$avg_progress = $total_students > 0 ? round(array_sum(array_column($students, 'progress_percent')) / $total_students, 1) : 0;

// Get recent activity (last 7 days)
$recent_activity = 0;
try {
    $activity_stmt = $pdo->prepare("
        SELECT COUNT(*) as activity_count
        FROM student_courses sc
        WHERE sc.course_id = ? AND sc.last_accessed >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $activity_stmt->execute([$course_id]);
    $recent_activity = $activity_stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error fetching recent activity: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('course_students') ?? 'Étudiants du Cours' ?> - <?= htmlspecialchars($course['title']) ?></title>

    <!-- External Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="../assets/css/instructor-styles.css">
    <link rel="stylesheet" href="assets/css/students.css">

    <style>
        :root {
            --primary-color: #1976d2;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 8px;
            --box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .instructor-layout {
            display: flex;
            min-height: 100vh;
        }

        .instructor-sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1976d2 0%, #1565c0 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .instructor-main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            background: transparent;
        }

        .course-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
            position: relative;
            overflow: hidden;
        }

        .course-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color));
        }

        .course-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .course-description {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card.success::before {
            background: var(--success-color);
        }

        .stat-card.warning::before {
            background: var(--warning-color);
        }

        .stat-card.danger::before {
            background: var(--danger-color);
        }

        .stat-card.info::before {
            background: var(--info-color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2rem;
            opacity: 0.1;
        }

        .filters-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .filter-input,
        .filter-select {
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-1px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-warning {
            background: var(--warning-color);
            color: var(--dark-color);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .students-table {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }

        .table-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .table-content {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .student-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #42a5f5);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .student-details h4 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .student-details p {
            margin: 0;
            font-size: 0.8rem;
            color: #666;
        }

        .progress-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .progress-bar {
            width: 120px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-fill.success {
            background: var(--success-color);
        }

        .progress-fill.warning {
            background: var(--warning-color);
        }

        .progress-fill.danger {
            background: var(--danger-color);
        }

        .progress-text {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-color);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-active {
            background: #fff3cd;
            color: #856404;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .empty-state h3 {
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 2rem;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .instructor-sidebar {
                width: 250px;
            }

            .instructor-main {
                margin-left: 250px;
            }
        }

        @media (max-width: 768px) {
            .instructor-sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }

            .instructor-main {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .table-content {
                font-size: 0.9rem;
            }
        }

        /* Animation for loading states */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div style="padding: 2rem 1.5rem;">
                <h2 style="margin-bottom: 2rem; text-align: center; font-size: 1.5rem;">
                    <i class="fas fa-chalkboard-teacher"></i> <?= __('instructor') ?? 'Instructeur' ?>
                </h2>

                <nav style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <a href="index.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s;">
                        <i class="fas fa-home"></i> <?= __('dashboard') ?? 'Dashboard' ?>
                    </a>
                    <a href="my_courses.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s; background: rgba(255,255,255,0.1);">
                        <i class="fas fa-book"></i> <?= __('my_courses') ?? 'Mes Cours' ?>
                    </a>
                    <a href="add_course.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s;">
                        <i class="fas fa-plus"></i> <?= __('create_course') ?? 'Créer Cours' ?>
                    </a>
                    <a href="students.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s;">
                        <i class="fas fa-users"></i> <?= __('students') ?? 'Étudiants' ?>
                    </a>
                    <a href="earnings.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s;">
                        <i class="fas fa-money-bill-wave"></i> <?= __('earnings') ?? 'Gains' ?>
                    </a>
                    <a href="payouts.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s;">
                        <i class="fas fa-hand-holding-usd"></i> <?= __('payouts') ?? 'Paiements' ?>
                    </a>
                    <a href="../auth/logout.php" style="display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; color: white; text-decoration: none; border-radius: 6px; transition: background-color 0.3s; margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1);">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?? 'Déconnexion' ?>
                    </a>
                </nav>
            </div>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <!-- Course Header -->
            <div class="course-header fade-in-up">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                    <div>
                        <h1 class="course-title"><?= htmlspecialchars($course['title']) ?></h1>
                        <p class="course-description"><?= htmlspecialchars($course['description']) ?></p>
                    </div>
                    <div style="display: flex; gap: 1rem;">
                        <a href="course_lessons.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                            <i class="fas fa-play"></i> <?= __('lessons') ?? 'Leçons' ?>
                        </a>
                        <a href="my_courses.php" class="btn" style="background: #6c757d; color: white;">
                            <i class="fas fa-arrow-left"></i> <?= __('back_to_courses') ?? 'Retour' ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid fade-in-up">
                <div class="stat-card success">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-number"><?= $total_students ?></div>
                    <div class="stat-label"><?= __('total_students') ?? 'Total Étudiants' ?></div>
                </div>
                <div class="stat-card warning">
                    <i class="fas fa-user-clock stat-icon"></i>
                    <div class="stat-number"><?= count($active_students) ?></div>
                    <div class="stat-label"><?= __('active_students') ?? 'Étudiants Actifs' ?></div>
                </div>
                <div class="stat-card success">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <div class="stat-number"><?= count($completed_students) ?></div>
                    <div class="stat-label"><?= __('completed_students') ?? 'Terminés' ?></div>
                </div>
                <div class="stat-card info">
                    <i class="fas fa-chart-line stat-icon"></i>
                    <div class="stat-number"><?= $completion_rate ?>%</div>
                    <div class="stat-label"><?= __('completion_rate') ?? 'Taux de Réussite' ?></div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-percentage stat-icon"></i>
                    <div class="stat-number"><?= $avg_progress ?>%</div>
                    <div class="stat-label"><?= __('average_progress') ?? 'Progression Moyenne' ?></div>
                </div>
                <div class="stat-card danger">
                    <i class="fas fa-clock stat-icon"></i>
                    <div class="stat-number"><?= $recent_activity ?></div>
                    <div class="stat-label"><?= __('recent_activity') ?? 'Activité Récente' ?></div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section fade-in-up">
                <form method="GET" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="course_id" value="<?= $course_id ?>">

                    <div class="filter-group">
                        <label class="filter-label"><?= __('search') ?? 'Rechercher' ?></label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search_term) ?>"
                            placeholder="<?= __('search_students') ?? 'Rechercher des étudiants...' ?>"
                            class="filter-input" style="width: 250px;">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('status') ?? 'Statut' ?></label>
                        <select name="status" class="filter-select">
                            <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>><?= __('all') ?? 'Tous' ?></option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>><?= __('completed') ?? 'Terminés' ?></option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>><?= __('active') ?? 'Actifs' ?></option>
                            <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?? 'Inactifs' ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('sort_by') ?? 'Trier par' ?></label>
                        <select name="sort" class="filter-select">
                            <option value="enrolled_at" <?= $sort_by === 'enrolled_at' ? 'selected' : '' ?>><?= __('enrollment_date') ?? 'Date d\'inscription' ?></option>
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>><?= __('name') ?? 'Nom' ?></option>
                            <option value="progress" <?= $sort_by === 'progress' ? 'selected' : '' ?>><?= __('progress') ?? 'Progression' ?></option>
                            <option value="last_accessed" <?= $sort_by === 'last_accessed' ? 'selected' : '' ?>><?= __('last_activity') ?? 'Dernière activité' ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('order') ?? 'Ordre' ?></label>
                        <select name="order" class="filter-select">
                            <option value="desc" <?= $sort_order === 'desc' ? 'selected' : '' ?>><?= __('descending') ?? 'Décroissant' ?></option>
                            <option value="asc" <?= $sort_order === 'asc' ? 'selected' : '' ?>><?= __('ascending') ?? 'Croissant' ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?= __('filter') ?? 'Filtrer' ?>
                    </button>

                    <a href="course_students.php?course_id=<?= $course_id ?>" class="btn" style="background: #6c757d; color: white;">
                        <i class="fas fa-times"></i> <?= __('clear') ?? 'Effacer' ?>
                    </a>
                </form>
            </div>

            <!-- Students Table -->
            <?php if (empty($students)): ?>
                <div class="empty-state fade-in-up">
                    <i class="fas fa-users"></i>
                    <h3><?= __('no_students_found') ?? 'Aucun étudiant trouvé' ?></h3>
                    <p><?= __('no_students_message') ?? 'Aucun étudiant ne correspond à vos critères de recherche.' ?></p>
                    <a href="course_students.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                        <i class="fas fa-refresh"></i> <?= __('show_all') ?? 'Afficher tous' ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="students-table fade-in-up">
                    <div class="table-header">
                        <h3 class="table-title">
                            <?= __('students_list') ?? 'Liste des Étudiants' ?> (<?= $total_students ?>)
                        </h3>
                    </div>
                    <div class="table-content">
                        <table>
                            <thead>
                                <tr>
                                    <th><?= __('student') ?? 'Étudiant' ?></th>
                                    <th><?= __('email') ?? 'Email' ?></th>
                                    <th><?= __('progress') ?? 'Progression' ?></th>
                                    <th><?= __('status') ?? 'Statut' ?></th>
                                    <th><?= __('enrolled_date') ?? 'Date d\'inscription' ?></th>
                                    <th><?= __('last_activity') ?? 'Dernière activité' ?></th>
                                    <th><?= __('actions') ?? 'Actions' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="student-info">
                                                <div class="student-avatar">
                                                    <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                                                </div>
                                                <div class="student-details">
                                                    <h4><?= htmlspecialchars($student['full_name']) ?></h4>
                                                    <p><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($student['email']) ?></td>
                                        <td>
                                            <div class="progress-container">
                                                <div class="progress-bar">
                                                    <?php
                                                    $progress = $student['progress_percent'] ?? 0;
                                                    $progressClass = $progress >= 80 ? 'success' : ($progress >= 40 ? 'warning' : 'danger');
                                                    ?>
                                                    <div class="progress-fill <?= $progressClass ?>" style="width: <?= $progress ?>%"></div>
                                                </div>
                                                <span class="progress-text"><?= $progress ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $progress = $student['progress_percent'] ?? 0;
                                            $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'active' : 'inactive');
                                            ?>
                                            <span class="status-badge status-<?= $status ?>">
                                                <i class="fas fa-<?= $status === 'completed' ? 'check-circle' : ($status === 'active' ? 'play-circle' : 'pause-circle') ?>"></i>
                                                <?= ucfirst($status) ?>
                                            </span>
                                        </td>
                                        <td><?= format_date($student['enrolled_at']) ?></td>
                                        <td>
                                            <?php if ($student['last_accessed']): ?>
                                                <?= format_date($student['last_accessed']) ?>
                                            <?php else: ?>
                                                <span style="color: #999;"><?= __('never') ?? 'Jamais' ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_student_progress.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $course_id ?>"
                                                    class="btn btn-success btn-sm" title="<?= __('view_progress') ?? 'Voir la progression' ?>">
                                                    <i class="fas fa-chart-line"></i>
                                                </a>
                                                <a href="update_progress.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $course_id ?>"
                                                    class="btn btn-warning btn-sm" title="<?= __('update_progress') ?? 'Mettre à jour' ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="message_student.php?student_id=<?= $student['student_id'] ?>"
                                                    class="btn btn-primary btn-sm" title="<?= __('send_message') ?? 'Envoyer un message' ?>">
                                                    <i class="fas fa-envelope"></i>
                                                </a>
                                                <a href="remove_student.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $course_id ?>"
                                                    class="btn btn-danger btn-sm"
                                                    title="<?= __('remove_student') ?? 'Retirer l\'étudiant' ?>"
                                                    onclick="return confirm('<?= __('confirm_remove_student') ?? 'Êtes-vous sûr de vouloir retirer cet étudiant du cours ?' ?>')">
                                                    <i class="fas fa-user-times"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Add interactive features
        document.addEventListener('DOMContentLoaded', function() {
            // Animate statistics on load
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = stat.textContent;
                const numericValue = parseInt(finalValue.replace(/[^\d]/g, ''));

                if (!isNaN(numericValue) && numericValue > 0) {
                    animateCounter(stat, 0, numericValue, 1500);
                }
            });

            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.transition = 'transform 0.2s ease';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                });
            });

            // Add loading states for buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function() {
                    if (this.href && !this.href.includes('#')) {
                        this.style.opacity = '0.7';
                        this.style.pointerEvents = 'none';
                    }
                });
            });
        });

        // Counter animation function
        function animateCounter(element, start, end, duration) {
            const startTime = performance.now();

            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const current = Math.floor(progress * (end - start) + start);

                element.textContent = current;

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    element.textContent = end;
                }
            }

            requestAnimationFrame(updateCounter);
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + F to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) {
                    searchInput.focus();
                }
            }
        });
    </script>
</body>

</html>