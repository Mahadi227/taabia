<?php

/**
 * Validate Submissions Management Page - Professional LMS Version
 * 
 * Advanced submission validation with modern UI, analytics, and comprehensive features
 */

// ============================================================================
// INITIALIZATION & SECURITY
// ============================================================================

ob_start();
require_once '../includes/language_handler.php';
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

require_role('instructor');
$instructor_id = $_SESSION['user_id'];

// ============================================================================
// INPUT VALIDATION & PROCESSING
// ============================================================================

/**
 * Validate and sanitize input parameters
 */
function validateInputs()
{
    return [
        'search' => trim($_GET['search'] ?? ''),
        'status' => in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected', 'needs_revision']) ? $_GET['status'] : '',
        'course' => is_numeric($_GET['course'] ?? '') ? (int)$_GET['course'] : '',
        'priority' => in_array($_GET['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_GET['priority'] : '',
        'sort' => in_array($_GET['sort'] ?? '', ['recent', 'oldest', 'student', 'course', 'status', 'grade', 'priority']) ? $_GET['sort'] : 'recent',
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => min(100, max(10, (int)($_GET['per_page'] ?? 20))),
        'bulk_action' => $_POST['bulk_action'] ?? '',
        'selected_submissions' => $_POST['selected_submissions'] ?? []
    ];
}

/**
 * Handle bulk actions
 */
function handleBulkActions($pdo, $inputs)
{
    if (empty($inputs['bulk_action']) || empty($inputs['selected_submissions'])) {
        return ['success' => false, 'message' => __('no_submissions_selected')];
    }

    $submission_ids = array_map('intval', $inputs['selected_submissions']);
    $placeholders = str_repeat('?,', count($submission_ids) - 1) . '?';

    try {
        switch ($inputs['bulk_action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE submissions SET status = 'approved', graded_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($submission_ids);
                return ['success' => true, 'message' => __('submissions_approved_successfully')];

            case 'reject':
                $stmt = $pdo->prepare("UPDATE submissions SET status = 'rejected', graded_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($submission_ids);
                return ['success' => true, 'message' => __('submissions_rejected_successfully')];

            case 'needs_revision':
                $stmt = $pdo->prepare("UPDATE submissions SET status = 'needs_revision', graded_at = NOW() WHERE id IN ($placeholders)");
                $stmt->execute($submission_ids);
                return ['success' => true, 'message' => __('submissions_marked_for_revision')];

            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM submissions WHERE id IN ($placeholders)");
                $stmt->execute($submission_ids);
                return ['success' => true, 'message' => __('submissions_deleted_successfully')];

            default:
                return ['success' => false, 'message' => __('invalid_bulk_action')];
        }
    } catch (PDOException $e) {
        error_log("Bulk action error: " . $e->getMessage());
        return ['success' => false, 'message' => __('error_processing_bulk_action')];
    }
}

// Process bulk actions
$bulk_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($inputs['bulk_action'])) {
    $bulk_result = handleBulkActions($pdo, $inputs);
}

// ============================================================================
// DATA FETCHING FUNCTIONS
// ============================================================================

/**
 * Fetch instructor's courses for filtering
 */
function fetchInstructorCourses($pdo, $instructor_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.title,
                COUNT(s.id) as submission_count
            FROM courses c
            LEFT JOIN submissions s ON c.id = s.course_id
            WHERE c.instructor_id = ?
            GROUP BY c.id, c.title
            ORDER BY c.title
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Build dynamic query for submissions
 */
function buildSubmissionsQuery($inputs, $instructor_id)
{
    $where_conditions = ["c.instructor_id = ?"];
    $params = [$instructor_id];

    // Search functionality
    if (!empty($inputs['search'])) {
        $where_conditions[] = "(s.title LIKE ? OR s.description LIKE ? OR u.fullname LIKE ? OR u.email LIKE ?)";
        $search_term = "%{$inputs['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Status filter
    if (!empty($inputs['status'])) {
        $where_conditions[] = "s.status = ?";
        $params[] = $inputs['status'];
    }

    // Course filter
    if (!empty($inputs['course'])) {
        $where_conditions[] = "s.course_id = ?";
        $params[] = $inputs['course'];
    }

    // Priority filter
    if (!empty($inputs['priority'])) {
        $where_conditions[] = "s.priority = ?";
        $params[] = $inputs['priority'];
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Sort order
    $order_by = match ($inputs['sort']) {
        'recent' => 's.submitted_at DESC',
        'oldest' => 's.submitted_at ASC',
        'student' => 'u.fullname ASC',
        'course' => 'c.title ASC',
        'status' => 's.status ASC',
        'grade' => 's.grade DESC',
        'priority' => 'FIELD(s.priority, "urgent", "high", "medium", "low")',
        default => 's.submitted_at DESC'
    };

    return [
        'where' => $where_clause,
        'params' => $params,
        'order' => $order_by
    ];
}

/**
 * Fetch submissions with comprehensive data
 */
function fetchSubmissions($pdo, $query_data, $page, $per_page)
{
    try {
        $offset = ($page - 1) * $per_page;

        $query = "
            SELECT 
                s.id,
                s.title,
                s.description,
                s.file_path,
                s.file_name,
                s.file_size,
                s.file_type,
                s.status,
                s.priority,
                s.grade,
                s.feedback,
                s.submitted_at,
                s.graded_at,
                s.created_at,
                s.updated_at,
                c.id as course_id,
                c.title as course_title,
                c.thumbnail as course_thumbnail,
                u.id as student_id,
                u.fullname as student_name,
                u.email as student_email,
                u.profile_image as student_avatar,
                COUNT(DISTINCT s2.id) as total_submissions_by_student,
                AVG(s2.grade) as student_avg_grade
            FROM submissions s
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON s.student_id = u.id
            LEFT JOIN submissions s2 ON u.id = s2.student_id AND s2.grade IS NOT NULL
            WHERE {$query_data['where']}
            GROUP BY s.id
            ORDER BY {$query_data['order']}
            LIMIT ? OFFSET ?
        ";

        $params = array_merge($query_data['params'], [$per_page, $offset]);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching submissions: " . $e->getMessage());
        return [];
    }
}

/**
 * Get total count for pagination
 */
function getTotalCount($pdo, $query_data)
{
    try {
        $query = "
            SELECT COUNT(DISTINCT s.id) as total
            FROM submissions s
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON s.student_id = u.id
            WHERE {$query_data['where']}
        ";

        $stmt = $pdo->prepare($query);
        $stmt->execute($query_data['params']);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error getting total count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fetch comprehensive statistics
 */
function fetchStatistics($pdo, $instructor_id)
{
    try {
        $stats = [];

        // Basic counts
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_submissions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_submissions,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_submissions,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_submissions,
                COUNT(CASE WHEN status = 'needs_revision' THEN 1 END) as revision_submissions,
                AVG(grade) as avg_grade,
                COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_submissions,
                COUNT(CASE WHEN priority = 'high' THEN 1 END) as high_priority_submissions
            FROM submissions s
            JOIN courses c ON s.course_id = c.id
            WHERE c.instructor_id = ?
        ");
        $stmt->execute([$instructor_id]);
        $basic_stats = $stmt->fetch();

        $stats = array_merge($stats, $basic_stats);

        // Recent activity (last 7 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as recent_submissions
            FROM submissions s
            JOIN courses c ON s.course_id = c.id
            WHERE c.instructor_id = ? AND s.submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$instructor_id]);
        $stats['recent_submissions'] = $stmt->fetchColumn();

        // Average grading time
        $stmt = $pdo->prepare("
            SELECT AVG(TIMESTAMPDIFF(HOUR, submitted_at, graded_at)) as avg_grading_time
            FROM submissions s
            JOIN courses c ON s.course_id = c.id
            WHERE c.instructor_id = ? AND graded_at IS NOT NULL
        ");
        $stmt->execute([$instructor_id]);
        $stats['avg_grading_time'] = round($stmt->fetchColumn() ?? 0, 1);

        // Top performing students
        $stmt = $pdo->prepare("
            SELECT 
                u.fullname as student_name,
                u.email as student_email,
                COUNT(s.id) as submission_count,
                AVG(s.grade) as avg_grade
            FROM submissions s
            JOIN courses c ON s.course_id = c.id
            JOIN users u ON s.student_id = u.id
            WHERE c.instructor_id = ? AND s.grade IS NOT NULL
            GROUP BY u.id
            ORDER BY avg_grade DESC
            LIMIT 5
        ");
        $stmt->execute([$instructor_id]);
        $stats['top_students'] = $stmt->fetchAll();

        return $stats;
    } catch (PDOException $e) {
        error_log("Error fetching statistics: " . $e->getMessage());
        return [
            'total_submissions' => 0,
            'pending_submissions' => 0,
            'approved_submissions' => 0,
            'rejected_submissions' => 0,
            'revision_submissions' => 0,
            'avg_grade' => 0,
            'urgent_submissions' => 0,
            'high_priority_submissions' => 0,
            'recent_submissions' => 0,
            'avg_grading_time' => 0,
            'top_students' => []
        ];
    }
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$inputs = validateInputs();
$courses = fetchInstructorCourses($pdo, $instructor_id);
$query_data = buildSubmissionsQuery($inputs, $instructor_id);
$submissions = fetchSubmissions($pdo, $query_data, $inputs['page'], $inputs['per_page']);
$total_count = getTotalCount($pdo, $query_data);
$stats = fetchStatistics($pdo, $instructor_id);

// Calculate pagination
$total_pages = ceil($total_count / $inputs['per_page']);

// Enhanced statistics
$enhanced_stats = [
    'total_submissions' => $stats['total_submissions'],
    'pending_submissions' => $stats['pending_submissions'],
    'approved_submissions' => $stats['approved_submissions'],
    'rejected_submissions' => $stats['rejected_submissions'],
    'revision_submissions' => $stats['revision_submissions'],
    'avg_grade' => round($stats['avg_grade'] ?? 0, 1),
    'urgent_submissions' => $stats['urgent_submissions'],
    'high_priority_submissions' => $stats['high_priority_submissions'],
    'recent_submissions' => $stats['recent_submissions'],
    'avg_grading_time' => $stats['avg_grading_time'],
    'approval_rate' => $stats['total_submissions'] > 0 ? round(($stats['approved_submissions'] / $stats['total_submissions']) * 100, 1) : 0,
    'pending_rate' => $stats['total_submissions'] > 0 ? round(($stats['pending_submissions'] / $stats['total_submissions']) * 100, 1) : 0
];

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('validate_submissions') ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">

    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <style>
        /* Professional Modern Design */
        .instructor-main {
            margin-left: 280px;
            padding: var(--spacing-8);
            background-color: var(--gray-50);
            min-height: 100vh;
        }

        @media (max-width: 1024px) {
            .instructor-main {
                margin-left: 0;
                padding: var(--spacing-4);
            }
        }

        /* Header Section */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-info h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-info p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: white;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
            box-shadow: var(--shadow);
        }

        .alert-error {
            background: white;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
            box-shadow: var(--shadow);
        }

        /* Statistics Dashboard */
        .stats-section {
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-top: 3px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.pending {
            border-top-color: var(--warning-color);
        }

        .stat-card.approved {
            border-top-color: var(--success-color);
        }

        .stat-card.rejected {
            border-top-color: var(--danger-color);
        }

        .stat-card.urgent {
            border-top-color: #dc2626;
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.pending {
            background: linear-gradient(135deg, var(--warning-color), var(--warning-dark));
        }

        .stat-icon.approved {
            background: linear-gradient(135deg, var(--success-color), var(--success-dark));
        }

        .stat-icon.rejected {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
        }

        .stat-icon.urgent {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin: 0;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0.5rem 0 0 0;
        }

        .stat-change {
            font-size: 0.85rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.negative {
            color: var(--danger-color);
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filters-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .filter-input {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.2s ease;
            background: white;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--gray-100);
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .bulk-actions.hidden {
            display: none;
        }

        .bulk-select-all {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .bulk-actions-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--radius-sm);
            background: white;
            font-size: 0.9rem;
        }

        /* Submissions Table */
        .submissions-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .submissions-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .submissions-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        .submissions-count {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .submissions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .submissions-table th {
            background: var(--gray-50);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.9rem;
        }

        .submissions-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: top;
        }

        .submissions-table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Submission Row */
        .submission-row {
            transition: all 0.2s ease;
        }

        .submission-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .submission-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .submission-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.9rem;
        }

        .submission-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }

        .submission-details p {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin: 0;
        }

        .submission-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .submission-course {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .submission-date {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-badge.approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-badge.rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .status-badge.needs_revision {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        /* Priority Badges */
        .priority-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .priority-badge.urgent {
            background: rgba(220, 38, 38, 0.1);
            color: #dc2626;
        }

        .priority-badge.high {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .priority-badge.medium {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        .priority-badge.low {
            background: rgba(107, 114, 128, 0.1);
            color: var(--gray-500);
        }

        /* Grade Display */
        .grade-display {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .grade-display.excellent {
            color: var(--success-color);
        }

        .grade-display.good {
            color: var(--primary-color);
        }

        .grade-display.average {
            color: var(--warning-color);
        }

        .grade-display.poor {
            color: var(--danger-color);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem;
            border: none;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .btn-action.approve {
            background: var(--success-color);
            color: white;
        }

        .btn-action.approve:hover {
            background: var(--success-dark);
            transform: scale(1.05);
        }

        .btn-action.reject {
            background: var(--danger-color);
            color: white;
        }

        .btn-action.reject:hover {
            background: #dc2626;
            transform: scale(1.05);
        }

        .btn-action.view {
            background: var(--primary-color);
            color: white;
        }

        .btn-action.view:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .btn-action.download {
            background: var(--gray-600);
            color: white;
        }

        .btn-action.download:hover {
            background: var(--gray-700);
            transform: scale(1.05);
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 2rem;
        }

        .pagination-btn {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-200);
            background: white;
            color: var(--gray-700);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .pagination-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .pagination-btn.active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 0.5rem 0;
        }

        .empty-state p {
            font-size: 1rem;
            margin: 0;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .submissions-table {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .submissions-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <?php include '../includes/instructor_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="instructor-main">
            <!-- Alert Messages -->
            <?php if ($bulk_result): ?>
                <div class="alert <?= $bulk_result['success'] ? 'alert-success' : 'alert-error' ?>">
                    <i class="fas fa-<?= $bulk_result['success'] ? 'check-circle' : 'exclamation-circle' ?>" style="font-size: 1.5rem;"></i>
                    <strong><?= $bulk_result['message'] ?></strong>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <header class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <h1>
                            <i class="fas fa-clipboard-check"></i>
                            <?= __('validate_submissions') ?>
                        </h1>
                        <p><?= __('manage_and_validate_student_submissions') ?></p>
                    </div>
                    <div class="header-actions">
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                        <button class="btn btn-primary" onclick="exportSubmissions()">
                            <i class="fas fa-download"></i> <?= __('export_submissions') ?>
                        </button>
                        <button class="btn btn-secondary" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> <?= __('refresh') ?>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Statistics Dashboard -->
            <section class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card pending">
                        <div class="stat-header">
                            <div class="stat-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['pending_submissions']) ?></div>
                        <div class="stat-label"><?= __('pending_submissions') ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?= $enhanced_stats['pending_rate'] ?>% <?= __('of_total') ?>
                        </div>
                    </div>

                    <div class="stat-card approved">
                        <div class="stat-header">
                            <div class="stat-icon approved">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['approved_submissions']) ?></div>
                        <div class="stat-label"><?= __('approved_submissions') ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?= $enhanced_stats['approval_rate'] ?>% <?= __('approval_rate') ?>
                        </div>
                    </div>

                    <div class="stat-card rejected">
                        <div class="stat-header">
                            <div class="stat-icon rejected">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['rejected_submissions']) ?></div>
                        <div class="stat-label"><?= __('rejected_submissions') ?></div>
                        <div class="stat-change">
                            <i class="fas fa-chart-line"></i> <?= $enhanced_stats['avg_grade'] ?> <?= __('avg_grade') ?>
                        </div>
                    </div>

                    <div class="stat-card urgent">
                        <div class="stat-header">
                            <div class="stat-icon urgent">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['urgent_submissions']) ?></div>
                        <div class="stat-label"><?= __('urgent_submissions') ?></div>
                        <div class="stat-change">
                            <i class="fas fa-clock"></i> <?= $enhanced_stats['avg_grading_time'] ?>h <?= __('avg_grading_time') ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Filters Section -->
            <section class="filters-section">
                <div class="filters-header">
                    <h3 class="filters-title"><?= __('filters_and_search') ?></h3>
                    <button class="btn btn-outline" onclick="clearFilters()">
                        <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                    </button>
                </div>

                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label class="filter-label"><?= __('search') ?></label>
                            <input type="text" name="search" class="filter-input"
                                value="<?= htmlspecialchars($inputs['search']) ?>"
                                placeholder="<?= __('search_submissions') ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('status') ?></label>
                            <select name="status" class="filter-input">
                                <option value=""><?= __('all_statuses') ?></option>
                                <option value="pending" <?= $inputs['status'] === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                                <option value="approved" <?= $inputs['status'] === 'approved' ? 'selected' : '' ?>><?= __('approved') ?></option>
                                <option value="rejected" <?= $inputs['status'] === 'rejected' ? 'selected' : '' ?>><?= __('rejected') ?></option>
                                <option value="needs_revision" <?= $inputs['status'] === 'needs_revision' ? 'selected' : '' ?>><?= __('needs_revision') ?></option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('course') ?></label>
                            <select name="course" class="filter-input">
                                <option value=""><?= __('all_courses') ?></option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= $inputs['course'] == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?> (<?= $course['submission_count'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('priority') ?></label>
                            <select name="priority" class="filter-input">
                                <option value=""><?= __('all_priorities') ?></option>
                                <option value="urgent" <?= $inputs['priority'] === 'urgent' ? 'selected' : '' ?>><?= __('urgent') ?></option>
                                <option value="high" <?= $inputs['priority'] === 'high' ? 'selected' : '' ?>><?= __('high') ?></option>
                                <option value="medium" <?= $inputs['priority'] === 'medium' ? 'selected' : '' ?>><?= __('medium') ?></option>
                                <option value="low" <?= $inputs['priority'] === 'low' ? 'selected' : '' ?>><?= __('low') ?></option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('sort_by') ?></label>
                            <select name="sort" class="filter-input">
                                <option value="recent" <?= $inputs['sort'] === 'recent' ? 'selected' : '' ?>><?= __('most_recent') ?></option>
                                <option value="oldest" <?= $inputs['sort'] === 'oldest' ? 'selected' : '' ?>><?= __('oldest_first') ?></option>
                                <option value="student" <?= $inputs['sort'] === 'student' ? 'selected' : '' ?>><?= __('student_name') ?></option>
                                <option value="course" <?= $inputs['sort'] === 'course' ? 'selected' : '' ?>><?= __('course_name') ?></option>
                                <option value="status" <?= $inputs['sort'] === 'status' ? 'selected' : '' ?>><?= __('status') ?></option>
                                <option value="grade" <?= $inputs['sort'] === 'grade' ? 'selected' : '' ?>><?= __('grade') ?></option>
                                <option value="priority" <?= $inputs['sort'] === 'priority' ? 'selected' : '' ?>><?= __('priority') ?></option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('per_page') ?></label>
                            <select name="per_page" class="filter-input">
                                <option value="10" <?= $inputs['per_page'] == 10 ? 'selected' : '' ?>>10</option>
                                <option value="20" <?= $inputs['per_page'] == 20 ? 'selected' : '' ?>>20</option>
                                <option value="50" <?= $inputs['per_page'] == 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $inputs['per_page'] == 100 ? 'selected' : '' ?>>100</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" class="btn btn-outline" onclick="clearFilters()">
                            <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> <?= __('apply_filters') ?>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Bulk Actions -->
            <div class="bulk-actions hidden" id="bulkActions">
                <div class="bulk-select-all">
                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                    <label for="selectAll"><?= __('select_all') ?></label>
                </div>
                <span id="selectedCount">0 <?= __('selected') ?></span>
                <select class="bulk-actions-select" id="bulkActionSelect">
                    <option value=""><?= __('choose_action') ?></option>
                    <option value="approve"><?= __('approve_selected') ?></option>
                    <option value="reject"><?= __('reject_selected') ?></option>
                    <option value="needs_revision"><?= __('mark_for_revision') ?></option>
                    <option value="delete"><?= __('delete_selected') ?></option>
                </select>
                <button class="btn btn-primary" onclick="executeBulkAction()">
                    <i class="fas fa-check"></i> <?= __('execute') ?>
                </button>
            </div>

            <!-- Submissions Table -->
            <section class="submissions-section">
                <div class="submissions-header">
                    <h3 class="submissions-title"><?= __('submissions') ?></h3>
                    <span class="submissions-count">
                        <?= number_format($total_count) ?> <?= __('submissions_found') ?>
                    </span>
                </div>

                <?php if (empty($submissions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3><?= __('no_submissions_found') ?></h3>
                        <p><?= __('try_adjusting_your_filters') ?></p>
                    </div>
                <?php else: ?>
                    <form method="POST" id="bulkForm">
                        <table class="submissions-table">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="selectAllHeader" onchange="toggleSelectAll()">
                                    </th>
                                    <th><?= __('submission') ?></th>
                                    <th><?= __('student') ?></th>
                                    <th><?= __('course') ?></th>
                                    <th><?= __('status') ?></th>
                                    <th><?= __('priority') ?></th>
                                    <th><?= __('grade') ?></th>
                                    <th><?= __('submitted') ?></th>
                                    <th width="120"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <tr class="submission-row">
                                        <td>
                                            <input type="checkbox" name="selected_submissions[]"
                                                value="<?= $submission['id'] ?>"
                                                class="submission-checkbox"
                                                onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div class="submission-info">
                                                <div class="submission-details">
                                                    <h4><?= htmlspecialchars($submission['title']) ?></h4>
                                                    <p><?= htmlspecialchars(substr($submission['description'], 0, 100)) ?>...</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="submission-info">
                                                <div class="submission-avatar">
                                                    <?= strtoupper(substr($submission['student_name'], 0, 1)) ?>
                                                </div>
                                                <div class="submission-details">
                                                    <h4><?= htmlspecialchars($submission['student_name']) ?></h4>
                                                    <p><?= htmlspecialchars($submission['student_email']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="submission-meta">
                                                <div class="submission-course"><?= htmlspecialchars($submission['course_title']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge <?= $submission['status'] ?>">
                                                <?= __($submission['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($submission['priority']): ?>
                                                <span class="priority-badge <?= $submission['priority'] ?>">
                                                    <?= __($submission['priority']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($submission['grade']): ?>
                                                <span class="grade-display <?= $submission['grade'] >= 90 ? 'excellent' : ($submission['grade'] >= 80 ? 'good' : ($submission['grade'] >= 70 ? 'average' : 'poor')) ?>">
                                                    <?= $submission['grade'] ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="submission-meta">
                                                <div class="submission-date">
                                                    <?= date('M j, Y', strtotime($submission['submitted_at'])) ?>
                                                </div>
                                                <div class="submission-date">
                                                    <?= date('g:i A', strtotime($submission['submitted_at'])) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action view"
                                                    onclick="viewSubmission(<?= $submission['id'] ?>)"
                                                    title="<?= __('view_submission') ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($submission['file_path']): ?>
                                                    <button type="button" class="btn-action download"
                                                        onclick="downloadFile('<?= htmlspecialchars($submission['file_path']) ?>')"
                                                        title="<?= __('download_file') ?>">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($submission['status'] === 'pending'): ?>
                                                    <button type="button" class="btn-action approve"
                                                        onclick="approveSubmission(<?= $submission['id'] ?>)"
                                                        title="<?= __('approve') ?>">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn-action reject"
                                                        onclick="rejectSubmission(<?= $submission['id'] ?>)"
                                                        title="<?= __('reject') ?>">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <button class="pagination-btn"
                                onclick="changePage(<?= max(1, $inputs['page'] - 1) ?>)"
                                <?= $inputs['page'] <= 1 ? 'disabled' : '' ?>>
                                <i class="fas fa-chevron-left"></i>
                            </button>

                            <?php for ($i = max(1, $inputs['page'] - 2); $i <= min($total_pages, $inputs['page'] + 2); $i++): ?>
                                <button class="pagination-btn <?= $i == $inputs['page'] ? 'active' : '' ?>"
                                    onclick="changePage(<?= $i ?>)">
                                    <?= $i ?>
                                </button>
                            <?php endfor; ?>

                            <button class="pagination-btn"
                                onclick="changePage(<?= min($total_pages, $inputs['page'] + 1) ?>)"
                                <?= $inputs['page'] >= $total_pages ? 'disabled' : '' ?>>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script>
        // Configuration
        window.submissionsConfig = {
            totalCount: <?= $total_count ?>,
            currentPage: <?= $inputs['page'] ?>,
            totalPages: <?= $total_pages ?>,
            perPage: <?= $inputs['per_page'] ?>,
            language: '<?= $_SESSION['user_language'] ?? 'fr' ?>',
            instructorId: <?= $instructor_id ?>
        };

        // Bulk Actions Management
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.submission-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');

            if (checkboxes.length > 0) {
                bulkActions.classList.remove('hidden');
                selectedCount.textContent = `${checkboxes.length} <?= __('selected') ?>`;
            } else {
                bulkActions.classList.add('hidden');
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllHeader');
            const checkboxes = document.querySelectorAll('.submission-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });

            updateBulkActions();
        }

        function executeBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const form = document.getElementById('bulkForm');

            if (!action) {
                alert('<?= __('please_select_an_action') ?>');
                return;
            }

            const checkboxes = document.querySelectorAll('.submission-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('<?= __('please_select_submissions') ?>');
                return;
            }

            if (confirm(`<?= __('confirm_bulk_action') ?> ${action}?`)) {
                const bulkActionInput = document.createElement('input');
                bulkActionInput.type = 'hidden';
                bulkActionInput.name = 'bulk_action';
                bulkActionInput.value = action;
                form.appendChild(bulkActionInput);
                form.submit();
            }
        }

        // Individual Actions
        function viewSubmission(id) {
            // Open submission details modal or redirect
            window.open(`view_submission.php?id=${id}`, '_blank');
        }

        function downloadFile(filePath) {
            window.open(`download_submission.php?file=${encodeURIComponent(filePath)}`, '_blank');
        }

        function approveSubmission(id) {
            if (confirm('<?= __('confirm_approve_submission') ?>')) {
                // Submit approval
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="submission_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectSubmission(id) {
            if (confirm('<?= __('confirm_reject_submission') ?>')) {
                // Submit rejection
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="submission_id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Utility Functions
        function clearFilters() {
            const form = document.querySelector('.filters-form');
            form.reset();
            form.submit();
        }

        function changePage(page) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        function exportSubmissions() {
            const url = new URL(window.location);
            url.searchParams.set('export', 'csv');
            window.open(url.toString(), '_blank');
        }

        function refreshData() {
            window.location.reload();
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide success messages
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>

</html>