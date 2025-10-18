<?php

/**
 * Student Progress Tracking
 * Allows instructors to view detailed progress of all students across all courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';

// Check if user is logged in and is an instructor
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Get filter parameters
$course_filter = $_GET['course'] ?? 'all';
$search = $_GET['search'] ?? '';

// Fetch instructor's courses for filter dropdown
$courses_query = "SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title";
$courses_stmt = $pdo->prepare($courses_query);
$courses_stmt->execute([$instructor_id]);
$courses = $courses_stmt->fetchAll();

// Build student progress query
$where_conditions = ["c.instructor_id = ?"];
$params = [$instructor_id];

if ($course_filter !== 'all') {
    $where_conditions[] = "c.id = ?";
    $params[] = $course_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Fetch student progress data
$progress_query = "
    SELECT 
        u.id as student_id,
        u.full_name as student_name,
        u.email as student_email,
        c.id as course_id,
        c.title as course_title,
        COALESCE(sc.progress, 0) as progress,
        sc.enrolled_at,
        sc.last_accessed,
        COUNT(DISTINCT l.id) as total_lessons,
        COUNT(DISTINCT lp.lesson_id) as completed_lessons,
        NULL as avg_quiz_score
    FROM student_courses sc
    INNER JOIN users u ON sc.student_id = u.id
    INNER JOIN courses c ON sc.course_id = c.id
    LEFT JOIN lessons l ON l.course_id = c.id
    LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = u.id
    WHERE {$where_clause}
    GROUP BY u.id, c.id, sc.progress, sc.enrolled_at, sc.last_accessed
    ORDER BY u.full_name ASC, c.title ASC
";

try {
    $progress_stmt = $pdo->prepare($progress_query);
    $progress_stmt->execute($params);
    $student_progress = $progress_stmt->fetchAll();
} catch (PDOException $e) {
    // If student_courses doesn't have progress column, try alternative query
    $progress_query = "
        SELECT 
            u.id as student_id,
            u.full_name as student_name,
            u.email as student_email,
            c.id as course_id,
            c.title as course_title,
            0 as progress,
            COALESCE(sc.enrolled_at, u.created_at) as enrolled_at,
            NOW() as last_accessed,
            COUNT(DISTINCT l.id) as total_lessons,
            COUNT(DISTINCT lp.lesson_id) as completed_lessons,
            NULL as avg_quiz_score
        FROM student_courses sc
        INNER JOIN users u ON sc.student_id = u.id
        INNER JOIN courses c ON sc.course_id = c.id
        LEFT JOIN lessons l ON l.course_id = c.id
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = u.id
        WHERE {$where_clause}
        GROUP BY u.id, c.id
        ORDER BY u.full_name ASC, c.title ASC
    ";
    $progress_stmt = $pdo->prepare($progress_query);
    $progress_stmt->execute($params);
    $student_progress = $progress_stmt->fetchAll();

    // Calculate progress based on completed lessons
    foreach ($student_progress as &$item) {
        if ($item['total_lessons'] > 0) {
            $item['progress'] = ($item['completed_lessons'] / $item['total_lessons']) * 100;
        }
    }
    unset($item);
}

// Calculate overall statistics
$total_students = count(array_unique(array_column($student_progress, 'student_id')));
$avg_progress = !empty($student_progress) ? array_sum(array_column($student_progress, 'progress')) / count($student_progress) : 0;
$active_enrollments = count($student_progress);
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('student_progress') ?> | TaaBia</title>

    <!-- External Dependencies -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-left: 260px;
        }

        .main-content {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #1a202c;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header h1 i {
            color: #667eea;
        }

        .page-header p {
            color: #718096;
            font-size: 1rem;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.students {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.progress {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.enrollments {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-content h3 {
            color: #718096;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .stat-content .value {
            color: #1a202c;
            font-size: 1.75rem;
            font-weight: 700;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            color: #4a5568;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .form-group select,
        .form-group input {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s ease;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .btn-filter {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Progress Table */
        .progress-table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .progress-table {
            width: 100%;
            border-collapse: collapse;
        }

        .progress-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .progress-table th {
            padding: 1rem;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .progress-table tbody tr {
            border-bottom: 1px solid #e2e8f0;
            transition: background-color 0.3s ease;
        }

        .progress-table tbody tr:hover {
            background-color: #f7fafc;
        }

        .progress-table td {
            padding: 1rem;
            color: #4a5568;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .student-details h4 {
            color: #1a202c;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.15rem;
        }

        .student-details p {
            color: #a0aec0;
            font-size: 0.8rem;
        }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 0.875rem;
            font-weight: 600;
            color: #667eea;
        }

        .badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-warning {
            background: #feebc8;
            color: #7c2d12;
        }

        .badge-info {
            background: #bee3f8;
            color: #2c5282;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.85rem;
            transition: background 0.3s ease;
            display: inline-block;
        }

        .action-btn:hover {
            background: #5568d3;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #a0aec0;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #cbd5e0;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }

            .main-content {
                padding: 1rem;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .progress-table-container {
                overflow-x: auto;
            }

            .progress-table {
                min-width: 800px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/instructor_sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-chart-line"></i>
                <?= __('student_progress') ?>
            </h1>
            <p><?= __('track_student_progress_description') ?? 'Suivez les progrès de tous vos étudiants en temps réel' ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon students">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <h3><?= __('total_students') ?? 'Total Étudiants' ?></h3>
                    <div class="value"><?= $total_students ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon progress">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <h3><?= __('average_progress') ?? 'Progrès Moyen' ?></h3>
                    <div class="value"><?= round($avg_progress, 1) ?>%</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon enrollments">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-content">
                    <h3><?= __('active_enrollments') ?? 'Inscriptions Actives' ?></h3>
                    <div class="value"><?= $active_enrollments ?></div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="" class="filters-form">
                <div class="form-group">
                    <label for="course"><?= __('filter_by_course') ?? 'Filtrer par cours' ?></label>
                    <select name="course" id="course">
                        <option value="all" <?= $course_filter === 'all' ? 'selected' : '' ?>><?= __('all_courses') ?? 'Tous les cours' ?></option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="search"><?= __('search_student') ?? 'Rechercher un étudiant' ?></label>
                    <input type="text" name="search" id="search" placeholder="<?= __('name_or_email') ?? 'Nom ou email' ?>" value="<?= htmlspecialchars($search) ?>">
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> <?= __('filter') ?? 'Filtrer' ?>
                </button>
            </form>
        </div>

        <!-- Progress Table -->
        <div class="progress-table-container">
            <?php if (empty($student_progress)): ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <h3><?= __('no_students_found') ?? 'Aucun étudiant trouvé' ?></h3>
                    <p><?= __('try_adjusting_filters') ?? 'Essayez d\'ajuster vos filtres' ?></p>
                </div>
            <?php else: ?>
                <table class="progress-table">
                    <thead>
                        <tr>
                            <th><?= __('student') ?? 'Étudiant' ?></th>
                            <th><?= __('course') ?? 'Cours' ?></th>
                            <th><?= __('progress') ?? 'Progrès' ?></th>
                            <th><?= __('lessons') ?? 'Leçons' ?></th>
                            <th><?= __('quiz_score') ?? 'Score Quiz' ?></th>
                            <th><?= __('enrolled_date') ?? 'Date d\'inscription' ?></th>
                            <th><?= __('actions') ?? 'Actions' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_progress as $progress): ?>
                            <tr>
                                <td>
                                    <div class="student-info">
                                        <div class="student-avatar">
                                            <?= strtoupper(substr($progress['student_name'], 0, 1)) ?>
                                        </div>
                                        <div class="student-details">
                                            <h4><?= htmlspecialchars($progress['student_name']) ?></h4>
                                            <p><?= htmlspecialchars($progress['student_email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($progress['course_title']) ?></strong>
                                </td>
                                <td>
                                    <div class="progress-bar-container">
                                        <div class="progress-bar" style="width: <?= $progress['progress'] ?>%"></div>
                                    </div>
                                    <div class="progress-text"><?= round($progress['progress'], 1) ?>%</div>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?= $progress['completed_lessons'] ?> / <?= $progress['total_lessons'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($progress['avg_quiz_score'] !== null): ?>
                                        <span class="badge <?= $progress['avg_quiz_score'] >= 70 ? 'badge-success' : 'badge-warning' ?>">
                                            <?= round($progress['avg_quiz_score'], 1) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #a0aec0;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($progress['enrolled_at'])) ?>
                                </td>
                                <td>
                                    <a href="view_student_progress.php?student_id=<?= $progress['student_id'] ?>&course_id=<?= $progress['course_id'] ?>" class="action-btn">
                                        <i class="fas fa-eye"></i> <?= __('view_details') ?? 'Détails' ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form on select change for better UX
        document.getElementById('course').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>

</html>