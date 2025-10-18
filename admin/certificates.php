<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

// Handle bulk actions
if ($_POST['action'] ?? false) {
    $action = $_POST['action'];
    $selected_certificates = $_POST['selected_certificates'] ?? [];

    if (!empty($selected_certificates)) {
        switch ($action) {
            case 'delete':
                $placeholders = str_repeat('?,', count($selected_certificates) - 1) . '?';
                $delete_query = "DELETE FROM course_certificates WHERE id IN ($placeholders)";
                $delete_stmt = $pdo->prepare($delete_query);
                $delete_stmt->execute($selected_certificates);
                $_SESSION['success_message'] = count($selected_certificates) . ' certificates deleted successfully.';
                break;

            case 'regenerate':
                // Regenerate verification codes for selected certificates
                $placeholders = str_repeat('?,', count($selected_certificates) - 1) . '?';
                $regenerate_query = "UPDATE course_certificates SET verification_code = CONCAT('VER-', UNIX_TIMESTAMP(), '-', SUBSTRING(MD5(RAND()), 1, 8)) WHERE id IN ($placeholders)";
                $regenerate_stmt = $pdo->prepare($regenerate_query);
                $regenerate_stmt->execute($selected_certificates);
                $_SESSION['success_message'] = count($selected_certificates) . ' certificates regenerated successfully.';
                break;
        }
    }

    header('Location: certificates.php');
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$student_filter = $_GET['student'] ?? '';
$course_filter = $_GET['course'] ?? '';
$instructor_filter = $_GET['instructor'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$grade_min = $_GET['grade_min'] ?? '';
$grade_max = $_GET['grade_max'] ?? '';
$sort_by = $_GET['sort'] ?? 'issue_date';
$sort_order = $_GET['order'] ?? 'DESC';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;

// Validate sort parameters
$allowed_sorts = ['issue_date', 'student_name', 'course_title', 'instructor_name', 'final_grade', 'certificate_number'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'issue_date';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'DESC';
}

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(cc.student_name LIKE ? OR cc.course_title LIKE ? OR cc.instructor_name LIKE ? OR cc.certificate_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($student_filter)) {
    $where_conditions[] = "cc.student_name LIKE ?";
    $params[] = "%$student_filter%";
}

if (!empty($course_filter)) {
    $where_conditions[] = "cc.course_title LIKE ?";
    $params[] = "%$course_filter%";
}

if (!empty($instructor_filter)) {
    $where_conditions[] = "cc.instructor_name LIKE ?";
    $params[] = "%$instructor_filter%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(cc.issue_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(cc.issue_date) <= ?";
    $params[] = $date_to;
}

if (!empty($grade_min)) {
    $where_conditions[] = "cc.final_grade >= ?";
    $params[] = $grade_min;
}

if (!empty($grade_max)) {
    $where_conditions[] = "cc.final_grade <= ?";
    $params[] = $grade_max;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM course_certificates cc
    $where_clause
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_certificates = $count_stmt->fetch()['total'];
$total_pages = ceil($total_certificates / $per_page);

// Get certificates with pagination
$offset = ($page - 1) * $per_page;
$certificates_query = "
    SELECT cc.*, 
           u.email as student_email,
           c.title as current_course_title,
           i.full_name as current_instructor_name,
           i.email as instructor_email,
           COUNT(DISTINCT cv.id) as verification_count,
           COUNT(DISTINCT cs.id) as share_count
    FROM course_certificates cc
    LEFT JOIN users u ON cc.student_id = u.id
    LEFT JOIN courses c ON cc.course_id = c.id
    LEFT JOIN users i ON c.instructor_id = i.id
    LEFT JOIN certificate_verifications cv ON cc.id = cv.certificate_id
    LEFT JOIN certificate_shares cs ON cc.id = cs.certificate_id
    $where_clause
    GROUP BY cc.id
    ORDER BY cc.$sort_by $sort_order
    LIMIT $per_page OFFSET $offset
";

$certificates_stmt = $pdo->prepare($certificates_query);
$certificates_stmt->execute($params);
$certificates = $certificates_stmt->fetchAll();

// Get filter options for dropdowns
$students_query = "SELECT DISTINCT student_name FROM course_certificates ORDER BY student_name";
$students_stmt = $pdo->query($students_query);
$students = $students_stmt->fetchAll();

$courses_query = "SELECT DISTINCT course_title FROM course_certificates ORDER BY course_title";
$courses_stmt = $pdo->query($courses_query);
$courses = $courses_stmt->fetchAll();

$instructors_query = "SELECT DISTINCT instructor_name FROM course_certificates ORDER BY instructor_name";
$instructors_stmt = $pdo->query($instructors_query);
$instructors = $instructors_stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_certificates,
        COUNT(DISTINCT student_id) as unique_students,
        COUNT(DISTINCT course_id) as unique_courses,
        COUNT(DISTINCT instructor_name) as unique_instructors,
        AVG(final_grade) as average_grade,
        COUNT(CASE WHEN DATE(issue_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
        COUNT(CASE WHEN DATE(issue_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
        COUNT(CASE WHEN DATE(issue_date) = CURDATE() THEN 1 END) as today
    FROM course_certificates
";
$stats_stmt = $pdo->query($stats_query);
$stats = $stats_stmt->fetch();

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('certificates') ?> - <?= __('admin_panel') ?></title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .certificates-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-number.primary {
            color: #667eea;
        }

        .stat-number.success {
            color: #38a169;
        }

        .stat-number.warning {
            color: #ed8936;
        }

        .stat-number.info {
            color: #3182ce;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .bulk-actions {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .bulk-actions.show {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .certificates-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .table-actions {
            display: flex;
            gap: 0.5rem;
        }

        .table-content {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
            position: sticky;
            top: 0;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .certificate-number {
            font-family: monospace;
            font-size: 0.9rem;
            color: #667eea;
        }

        .grade-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .grade-excellent {
            background: #d4edda;
            color: #155724;
        }

        .grade-good {
            background: #cce5ff;
            color: #004085;
        }

        .grade-satisfactory {
            background: #fff3cd;
            color: #856404;
        }

        .grade-pass {
            background: #f8d7da;
            color: #721c24;
        }

        .grade-fail {
            background: #f5c6cb;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        .sortable {
            cursor: pointer;
            user-select: none;
        }

        .sortable:hover {
            background: #e9ecef;
        }

        .sort-indicator {
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }

        .export-actions {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1><?= __('certificates') ?></h1>
                <p><?= __('manage_and_view_all_certificates') ?></p>
            </div>

            <div class="certificates-container">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number primary"><?= number_format($stats['total_certificates']) ?></div>
                        <div class="stat-label"><?= __('total_certificates') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number success"><?= number_format($stats['unique_students']) ?></div>
                        <div class="stat-label"><?= __('unique_students') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number info"><?= number_format($stats['unique_courses']) ?></div>
                        <div class="stat-label"><?= __('courses_with_certificates') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number warning"><?= round($stats['average_grade'], 1) ?>%</div>
                        <div class="stat-label"><?= __('average_grade') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number primary"><?= number_format($stats['last_30_days']) ?></div>
                        <div class="stat-label"><?= __('last_30_days') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number success"><?= number_format($stats['last_7_days']) ?></div>
                        <div class="stat-label"><?= __('last_7_days') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number info"><?= number_format($stats['today']) ?></div>
                        <div class="stat-label"><?= __('today') ?></div>
                    </div>
                </div>

                <!-- Export Actions -->
                <div class="export-actions">
                    <a href="certificate_analytics.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i> <?= __('analytics') ?>
                    </a>
                    <a href="export_certificates.php" class="btn btn-success">
                        <i class="fas fa-download"></i> <?= __('export_certificates') ?>
                    </a>
                </div>

                <!-- Filters -->
                <div class="filters-section">
                    <h3><?= __('filters') ?></h3>
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label for="search"><?= __('search') ?></label>
                                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                                    placeholder="<?= __('search_certificates') ?>">
                            </div>

                            <div class="filter-group">
                                <label for="student"><?= __('student') ?></label>
                                <select id="student" name="student">
                                    <option value=""><?= __('all_students') ?></option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?= htmlspecialchars($student['student_name']) ?>"
                                            <?= $student_filter === $student['student_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($student['student_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="course"><?= __('course') ?></label>
                                <select id="course" name="course">
                                    <option value=""><?= __('all_courses') ?></option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= htmlspecialchars($course['course_title']) ?>"
                                            <?= $course_filter === $course['course_title'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['course_title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="instructor"><?= __('instructor') ?></label>
                                <select id="instructor" name="instructor">
                                    <option value=""><?= __('all_instructors') ?></option>
                                    <?php foreach ($instructors as $instructor): ?>
                                        <option value="<?= htmlspecialchars($instructor['instructor_name']) ?>"
                                            <?= $instructor_filter === $instructor['instructor_name'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instructor['instructor_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="date_from"><?= __('from_date') ?></label>
                                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                            </div>

                            <div class="filter-group">
                                <label for="date_to"><?= __('to_date') ?></label>
                                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                            </div>

                            <div class="filter-group">
                                <label for="grade_min"><?= __('min_grade') ?></label>
                                <input type="number" id="grade_min" name="grade_min" value="<?= htmlspecialchars($grade_min) ?>"
                                    min="0" max="100" placeholder="0">
                            </div>

                            <div class="filter-group">
                                <label for="grade_max"><?= __('max_grade') ?></label>
                                <input type="number" id="grade_max" name="grade_max" value="<?= htmlspecialchars($grade_max) ?>"
                                    min="0" max="100" placeholder="100">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> <?= __('apply_filters') ?>
                            </button>
                            <a href="certificates.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <span id="selectedCount">0 <?= __('selected') ?></span>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="selected_certificates" id="selectedCertificates">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('<?= __('confirm_delete_selected') ?>')">
                            <i class="fas fa-trash"></i> <?= __('delete_selected') ?>
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="regenerate">
                        <input type="hidden" name="selected_certificates" id="selectedCertificatesRegen">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-sync"></i> <?= __('regenerate_selected') ?>
                        </button>
                    </form>
                </div>

                <!-- Certificates Table -->
                <div class="certificates-table">
                    <div class="table-header">
                        <h3 class="table-title"><?= __('certificates_list') ?> (<?= number_format($total_certificates) ?>)</h3>
                        <div class="table-actions">
                            <label>
                                <input type="checkbox" id="selectAll"> <?= __('select_all') ?>
                            </label>
                        </div>
                    </div>

                    <div class="table-content">
                        <?php if (empty($certificates)): ?>
                            <div class="empty-state">
                                <i class="fas fa-certificate"></i>
                                <p><?= __('no_certificates_found') ?></p>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="certificate_number">
                                            <?= __('certificate_number') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'certificate_number' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="student_name">
                                            <?= __('student') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'student_name' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="course_title">
                                            <?= __('course') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'course_title' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="instructor_name">
                                            <?= __('instructor') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'instructor_name' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="final_grade">
                                            <?= __('grade') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'final_grade' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="issue_date">
                                            <?= __('issue_date') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'issue_date' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th><?= __('verifications') ?></th>
                                        <th><?= __('shares') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($certificates as $cert): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="certificate-checkbox" value="<?= $cert['id'] ?>">
                                                <div class="certificate-number"><?= htmlspecialchars($cert['certificate_number']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($cert['student_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($cert['student_email']) ?></small>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($cert['course_title']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($cert['current_course_title']) ?></small>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($cert['instructor_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($cert['instructor_email']) ?></small>
                                            </td>
                                            <td>
                                                <?php if ($cert['final_grade']): ?>
                                                    <?php
                                                    $grade = $cert['final_grade'];
                                                    $grade_class = '';
                                                    if ($grade >= 90) $grade_class = 'grade-excellent';
                                                    elseif ($grade >= 80) $grade_class = 'grade-good';
                                                    elseif ($grade >= 70) $grade_class = 'grade-satisfactory';
                                                    elseif ($grade >= 60) $grade_class = 'grade-pass';
                                                    else $grade_class = 'grade-fail';
                                                    ?>
                                                    <span class="grade-badge <?= $grade_class ?>">
                                                        <?= round($grade, 1) ?>%
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div><?= date('M j, Y', strtotime($cert['issue_date'])) ?></div>
                                                <small class="text-muted"><?= date('g:i A', strtotime($cert['issue_date'])) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?= $cert['verification_count'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success"><?= $cert['share_count'] ?></span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="certificate_details.php?id=<?= $cert['id'] ?>"
                                                        class="btn btn-primary btn-sm" title="<?= __('view_details') ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="../instructor/generate_certificate.php?id=<?= $cert['id'] ?>"
                                                        class="btn btn-success btn-sm" target="_blank" title="<?= __('view_certificate') ?>">
                                                        <i class="fas fa-certificate"></i>
                                                    </a>
                                                    <a href="../public/verify_certificate.php?code=<?= urlencode($cert['verification_code']) ?>"
                                                        class="btn btn-secondary btn-sm" target="_blank" title="<?= __('verify') ?>">
                                                        <i class="fas fa-check-circle"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        ?>

                        <?php if ($start_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Select All functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkActions();
        });

        // Individual checkbox functionality
        document.querySelectorAll('.certificate-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.certificate-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const selectedCertificates = document.getElementById('selectedCertificates');
            const selectedCertificatesRegen = document.getElementById('selectedCertificatesRegen');

            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                selectedCount.textContent = checkboxes.length + ' <?= __('selected') ?>';

                const selectedIds = Array.from(checkboxes).map(cb => cb.value);
                selectedCertificates.value = JSON.stringify(selectedIds);
                selectedCertificatesRegen.value = JSON.stringify(selectedIds);
            } else {
                bulkActions.classList.remove('show');
            }
        }

        // Sorting functionality
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const currentSort = '<?= $sort_by ?>';
                const currentOrder = '<?= $sort_order ?>';

                let newOrder = 'ASC';
                if (sortBy === currentSort && currentOrder === 'ASC') {
                    newOrder = 'DESC';
                }

                const url = new URL(window.location);
                url.searchParams.set('sort', sortBy);
                url.searchParams.set('order', newOrder);
                url.searchParams.delete('page'); // Reset to first page

                window.location.href = url.toString();
            });
        });
    </script>
</body>

</html>






