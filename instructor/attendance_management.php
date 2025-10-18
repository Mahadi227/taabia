<?php

/**
 * Attendance Management Page - Professional LMS Version
 * 
 * Advanced attendance tracking with modern UI, analytics, and comprehensive features
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
        'course' => is_numeric($_GET['course'] ?? '') ? (int)$_GET['course'] : '',
        'status' => in_array($_GET['status'] ?? '', ['present', 'absent', 'late', 'excused']) ? $_GET['status'] : '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'sort' => in_array($_GET['sort'] ?? '', ['recent', 'oldest', 'course', 'student', 'attendance_rate']) ? $_GET['sort'] : 'recent',
        'page' => max(1, (int)($_GET['page'] ?? 1)),
        'per_page' => min(100, max(10, (int)($_GET['per_page'] ?? 20))),
        'action' => $_POST['action'] ?? '',
        'session_id' => (int)($_POST['session_id'] ?? 0),
        'student_id' => (int)($_POST['student_id'] ?? 0),
        'attendance_status' => $_POST['attendance_status'] ?? '',
        'notes' => trim($_POST['notes'] ?? '')
    ];
}

/**
 * Handle attendance actions
 */
function handleAttendanceAction($pdo, $inputs)
{
    if (empty($inputs['action']) || empty($inputs['session_id']) || empty($inputs['student_id'])) {
        return ['success' => false, 'message' => __('missing_required_fields')];
    }

    try {
        switch ($inputs['action']) {
            case 'mark_attendance':
                if (empty($inputs['attendance_status'])) {
                    return ['success' => false, 'message' => __('attendance_status_required')];
                }

                // Check if attendance already exists
                $stmt = $pdo->prepare("SELECT id FROM student_attendance WHERE session_id = ? AND student_id = ?");
                $stmt->execute([$inputs['session_id'], $inputs['student_id']]);
                $existing = $stmt->fetch();

                if ($existing) {
                    // Update existing attendance
                    $stmt = $pdo->prepare("
                        UPDATE student_attendance 
                        SET attendance_status = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$inputs['attendance_status'], $inputs['notes'], $existing['id']]);
                } else {
                    // Insert new attendance
                    $stmt = $pdo->prepare("
                        INSERT INTO student_attendance (session_id, student_id, attendance_status, notes, created_at, updated_at)
                        VALUES (?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$inputs['session_id'], $inputs['student_id'], $inputs['attendance_status'], $inputs['notes']]);
                }

                return ['success' => true, 'message' => __('attendance_updated_successfully')];

            case 'create_session':
                $session_title = trim($_POST['session_title'] ?? '');
                $session_date = $_POST['session_date'] ?? '';
                $start_time = $_POST['start_time'] ?? '';
                $end_time = $_POST['end_time'] ?? '';
                $course_id = (int)($_POST['course_id'] ?? 0);

                if (empty($session_title) || empty($session_date) || empty($start_time) || empty($course_id)) {
                    return ['success' => false, 'message' => __('missing_session_details')];
                }

                global $instructor_id;
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_sessions (course_id, instructor_id, session_title, session_date, start_time, end_time, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
                ");
                $stmt->execute([$course_id, $instructor_id, $session_title, $session_date, $start_time, $end_time]);

                return ['success' => true, 'message' => __('attendance_session_created_successfully')];

            default:
                return ['success' => false, 'message' => __('invalid_action')];
        }
    } catch (PDOException $e) {
        error_log("Attendance action error: " . $e->getMessage());
        return ['success' => false, 'message' => __('error_processing_attendance')];
    }
}

// Process attendance actions
$action_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($inputs['action'])) {
    $action_result = handleAttendanceAction($pdo, $inputs);
}

// ============================================================================
// DATA FETCHING FUNCTIONS
// ============================================================================

/**
 * Fetch instructor's courses for attendance
 */
function fetchInstructorCourses($pdo, $instructor_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.title,
                c.image_url,
                COUNT(DISTINCT as2.id) as session_count,
                COUNT(DISTINCT sc.student_id) as enrolled_students
            FROM courses c
            LEFT JOIN attendance_sessions as2 ON c.id = as2.course_id AND as2.is_active = 1
            LEFT JOIN student_courses sc ON c.id = sc.course_id
            WHERE c.instructor_id = ? AND c.status = 'published'
            GROUP BY c.id, c.title, c.image_url
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
 * Build dynamic query for attendance records
 */
function buildAttendanceQuery($inputs, $instructor_id)
{
    $where_conditions = ["c.instructor_id = ?"];
    $params = [$instructor_id];

    // Search functionality
    if (!empty($inputs['search'])) {
        $where_conditions[] = "(u.fullname LIKE ? OR u.email LIKE ? OR c.title LIKE ? OR as2.session_title LIKE ?)";
        $search_term = "%{$inputs['search']}%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    // Course filter
    if (!empty($inputs['course'])) {
        $where_conditions[] = "c.id = ?";
        $params[] = $inputs['course'];
    }

    // Status filter
    if (!empty($inputs['status'])) {
        $where_conditions[] = "sa.attendance_status = ?";
        $params[] = $inputs['status'];
    }

    // Date range filter
    if (!empty($inputs['date_from'])) {
        $where_conditions[] = "as2.session_date >= ?";
        $params[] = $inputs['date_from'];
    }

    if (!empty($inputs['date_to'])) {
        $where_conditions[] = "as2.session_date <= ?";
        $params[] = $inputs['date_to'];
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Sort order
    $order_by = match ($inputs['sort']) {
        'recent' => 'as2.session_date DESC, as2.start_time DESC',
        'oldest' => 'as2.session_date ASC, as2.start_time ASC',
        'course' => 'c.title ASC',
        'student' => 'u.fullname ASC',
        'attendance_rate' => 'attendance_rate DESC',
        default => 'as2.session_date DESC, as2.start_time DESC'
    };

    return [
        'where' => $where_clause,
        'params' => $params,
        'order' => $order_by
    ];
}

/**
 * Fetch attendance records with comprehensive data
 */
function fetchAttendanceRecords($pdo, $query_data, $page, $per_page)
{
    try {
        $offset = ($page - 1) * $per_page;

        $query = "
            SELECT 
                sa.id,
                sa.attendance_status,
                sa.notes,
                sa.created_at,
                sa.updated_at,
                as2.id as session_id,
                as2.session_title,
                as2.session_date,
                as2.start_time,
                as2.end_time,
                as2.is_active,
                c.id as course_id,
                c.title as course_title,
                c.image_url as course_thumbnail,
                u.id as student_id,
                u.fullname as student_name,
                u.email as student_email,
                u.profile_image as student_avatar,
                COUNT(DISTINCT sa2.id) as total_sessions,
                COUNT(DISTINCT CASE WHEN sa2.attendance_status IN ('present', 'late') THEN sa2.id END) as attended_sessions,
                ROUND((COUNT(DISTINCT CASE WHEN sa2.attendance_status IN ('present', 'late') THEN sa2.id END) / COUNT(DISTINCT sa2.id)) * 100, 1) as attendance_rate
            FROM student_attendance sa
            JOIN attendance_sessions as2 ON sa.session_id = as2.id
            JOIN courses c ON as2.course_id = c.id
            JOIN users u ON sa.student_id = u.id
            LEFT JOIN student_attendance sa2 ON u.id = sa2.student_id
            LEFT JOIN attendance_sessions as3 ON sa2.session_id = as3.id AND as3.course_id = c.id
            WHERE {$query_data['where']}
            GROUP BY sa.id, as2.id, c.id, u.id
            ORDER BY {$query_data['order']}
            LIMIT ? OFFSET ?
        ";

        $params = array_merge($query_data['params'], [$per_page, $offset]);
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching attendance records: " . $e->getMessage());
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
            SELECT COUNT(DISTINCT sa.id) as total
            FROM student_attendance sa
            JOIN attendance_sessions as2 ON sa.session_id = as2.id
            JOIN courses c ON as2.course_id = c.id
            JOIN users u ON sa.student_id = u.id
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
 * Fetch comprehensive attendance statistics
 */
function fetchAttendanceStatistics($pdo, $instructor_id)
{
    try {
        $stats = [];

        // Basic attendance counts
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT sa.id) as total_records,
                COUNT(DISTINCT CASE WHEN sa.attendance_status = 'present' THEN sa.id END) as present_count,
                COUNT(DISTINCT CASE WHEN sa.attendance_status = 'absent' THEN sa.id END) as absent_count,
                COUNT(DISTINCT CASE WHEN sa.attendance_status = 'late' THEN sa.id END) as late_count,
                COUNT(DISTINCT CASE WHEN sa.attendance_status = 'excused' THEN sa.id END) as excused_count,
                COUNT(DISTINCT as2.id) as total_sessions,
                COUNT(DISTINCT c.id) as total_courses,
                COUNT(DISTINCT u.id) as total_students
            FROM student_attendance sa
            JOIN attendance_sessions as2 ON sa.session_id = as2.id
            JOIN courses c ON as2.course_id = c.id
            JOIN users u ON sa.student_id = u.id
            WHERE c.instructor_id = ?
        ");
        $stmt->execute([$instructor_id]);
        $basic_stats = $stmt->fetch();

        $stats = array_merge($stats, $basic_stats);

        // Calculate attendance rate
        $stats['attendance_rate'] = $stats['total_records'] > 0 ?
            round((($stats['present_count'] + $stats['late_count']) / $stats['total_records']) * 100, 1) : 0;

        // Recent activity (last 7 days)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT sa.id) as recent_attendance
            FROM student_attendance sa
            JOIN attendance_sessions as2 ON sa.session_id = as2.id
            JOIN courses c ON as2.course_id = c.id
            WHERE c.instructor_id = ? AND sa.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute([$instructor_id]);
        $stats['recent_attendance'] = $stmt->fetchColumn();

        // Top performing students
        $stmt = $pdo->prepare("
            SELECT 
                u.fullname as student_name,
                u.email as student_email,
                COUNT(DISTINCT sa.id) as total_attendance,
                COUNT(DISTINCT CASE WHEN sa.attendance_status IN ('present', 'late') THEN sa.id END) as attended_count,
                ROUND((COUNT(DISTINCT CASE WHEN sa.attendance_status IN ('present', 'late') THEN sa.id END) / COUNT(DISTINCT sa.id)) * 100, 1) as attendance_rate
            FROM student_attendance sa
            JOIN attendance_sessions as2 ON sa.session_id = as2.id
            JOIN courses c ON as2.course_id = c.id
            JOIN users u ON sa.student_id = u.id
            WHERE c.instructor_id = ?
            GROUP BY u.id
            HAVING total_attendance > 0
            ORDER BY attendance_rate DESC
            LIMIT 5
        ");
        $stmt->execute([$instructor_id]);
        $stats['top_students'] = $stmt->fetchAll();

        // Recent sessions
        $stmt = $pdo->prepare("
            SELECT 
                as2.id,
                as2.session_title,
                as2.session_date,
                as2.start_time,
                c.title as course_title,
                COUNT(sa.id) as attendance_count,
                COUNT(CASE WHEN sa.attendance_status IN ('present', 'late') THEN 1 END) as present_count
            FROM attendance_sessions as2
            JOIN courses c ON as2.course_id = c.id
            LEFT JOIN student_attendance sa ON as2.id = sa.session_id
            WHERE as2.instructor_id = ?
            GROUP BY as2.id
            ORDER BY as2.session_date DESC, as2.start_time DESC
            LIMIT 5
        ");
        $stmt->execute([$instructor_id]);
        $stats['recent_sessions'] = $stmt->fetchAll();

        return $stats;
    } catch (PDOException $e) {
        error_log("Error fetching attendance statistics: " . $e->getMessage());
        return [
            'total_records' => 0,
            'present_count' => 0,
            'absent_count' => 0,
            'late_count' => 0,
            'excused_count' => 0,
            'total_sessions' => 0,
            'total_courses' => 0,
            'total_students' => 0,
            'attendance_rate' => 0,
            'recent_attendance' => 0,
            'top_students' => [],
            'recent_sessions' => []
        ];
    }
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$inputs = validateInputs();
$courses = fetchInstructorCourses($pdo, $instructor_id);
$query_data = buildAttendanceQuery($inputs, $instructor_id);
$attendance_records = fetchAttendanceRecords($pdo, $query_data, $inputs['page'], $inputs['per_page']);
$total_count = getTotalCount($pdo, $query_data);
$stats = fetchAttendanceStatistics($pdo, $instructor_id);

// Calculate pagination
$total_pages = ceil($total_count / $inputs['per_page']);

// Enhanced statistics
$enhanced_stats = [
    'total_records' => $stats['total_records'],
    'present_count' => $stats['present_count'],
    'absent_count' => $stats['absent_count'],
    'late_count' => $stats['late_count'],
    'excused_count' => $stats['excused_count'],
    'total_sessions' => $stats['total_sessions'],
    'total_courses' => $stats['total_courses'],
    'total_students' => $stats['total_students'],
    'attendance_rate' => $stats['attendance_rate'],
    'recent_attendance' => $stats['recent_attendance'],
    'present_rate' => $stats['total_records'] > 0 ? round(($stats['present_count'] / $stats['total_records']) * 100, 1) : 0,
    'absent_rate' => $stats['total_records'] > 0 ? round(($stats['absent_count'] / $stats['total_records']) * 100, 1) : 0
];

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('attendance_management') ?> | TaaBia</title>

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

        .stat-card.present {
            border-top-color: var(--success-color);
        }

        .stat-card.absent {
            border-top-color: var(--danger-color);
        }

        .stat-card.late {
            border-top-color: var(--warning-color);
        }

        .stat-card.overall {
            border-top-color: var(--primary-color);
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

        .stat-icon.present {
            background: linear-gradient(135deg, var(--success-color), var(--success-dark));
        }

        .stat-icon.absent {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
        }

        .stat-icon.late {
            background: linear-gradient(135deg, var(--warning-color), var(--warning-dark));
        }

        .stat-icon.overall {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
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

        /* Quick Actions - Enhanced */
        .quick-actions {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .quick-actions::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--success-color), var(--warning-color), var(--info-color));
        }

        .quick-actions-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--gray-900);
            margin: 0 0 2rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .quick-actions-title::before {
            content: '⚡';
            font-size: 1.8rem;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .action-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 2rem;
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid var(--gray-200);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.5s;
        }

        .action-card:hover::before {
            left: 100%;
        }

        .action-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }

        .action-card.create-session {
            border-left: 4px solid var(--success-color);
        }

        .action-card.create-session:hover {
            background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
            color: white;
        }

        .action-card.bulk-attendance {
            border-left: 4px solid var(--primary-color);
        }

        .action-card.bulk-attendance:hover {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .action-card.reports {
            border-left: 4px solid var(--warning-color);
        }

        .action-card.reports:hover {
            background: linear-gradient(135deg, var(--warning-color) 0%, var(--warning-dark) 100%);
            color: white;
        }

        .action-card.export {
            border-left: 4px solid var(--info-color);
        }

        .action-card.export:hover {
            background: linear-gradient(135deg, var(--info-color) 0%, #0891b2 100%);
            color: white;
        }

        .action-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .action-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .action-icon::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transition: all 0.3s ease;
            transform: translate(-50%, -50%);
        }

        .action-card:hover .action-icon::before {
            width: 100%;
            height: 100%;
        }

        .action-card.create-session .action-icon {
            background: linear-gradient(135deg, var(--success-color), #059669);
        }

        .action-card.bulk-attendance .action-icon {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .action-card.reports .action-icon {
            background: linear-gradient(135deg, var(--warning-color), var(--warning-dark));
        }

        .action-card.export .action-icon {
            background: linear-gradient(135deg, var(--info-color), #0891b2);
        }

        .action-card:hover .action-icon {
            transform: rotate(5deg) scale(1.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .action-content {
            flex: 1;
        }

        .action-title {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 0.5rem 0;
            transition: all 0.3s ease;
        }

        .action-description {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0 0 1rem 0;
            line-height: 1.5;
            transition: all 0.3s ease;
        }

        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0.75rem;
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .action-card:hover .action-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .action-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }

        .action-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .action-card:hover .action-stat {
            opacity: 1;
        }

        .action-arrow {
            opacity: 0;
            transform: translateX(-10px);
            transition: all 0.3s ease;
        }

        .action-card:hover .action-arrow {
            opacity: 1;
            transform: translateX(0);
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

        /* Attendance Table */
        .attendance-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .attendance-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .attendance-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        .attendance-count {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table th {
            background: var(--gray-50);
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 1px solid var(--gray-200);
            font-size: 0.9rem;
        }

        .attendance-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            vertical-align: top;
        }

        .attendance-table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Attendance Row */
        .attendance-row {
            transition: all 0.2s ease;
        }

        .attendance-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .attendance-avatar {
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

        .attendance-details h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }

        .attendance-details p {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin: 0;
        }

        .attendance-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .attendance-course {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .attendance-date {
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

        .status-badge.present {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-badge.absent {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .status-badge.late {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-badge.excused {
            background: rgba(59, 130, 246, 0.1);
            color: var(--primary-color);
        }

        /* Attendance Rate */
        .attendance-rate {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .attendance-rate.excellent {
            color: var(--success-color);
        }

        .attendance-rate.good {
            color: var(--primary-color);
        }

        .attendance-rate.average {
            color: var(--warning-color);
        }

        .attendance-rate.poor {
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

        .btn-action.edit {
            background: var(--primary-color);
            color: white;
        }

        .btn-action.edit:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .btn-action.view {
            background: var(--gray-600);
            color: white;
        }

        .btn-action.view:hover {
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-xl);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
        }

        .close {
            color: var(--gray-400);
            font-size: 2rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .close:hover {
            color: var(--gray-600);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .attendance-table {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .attendance-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }

            .actions-grid {
                grid-template-columns: 1fr;
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
            <?php if ($action_result): ?>
                <div class="alert <?= $action_result['success'] ? 'alert-success' : 'alert-error' ?>">
                    <i class="fas fa-<?= $action_result['success'] ? 'check-circle' : 'exclamation-circle' ?>" style="font-size: 1.5rem;"></i>
                    <strong><?= $action_result['message'] ?></strong>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <header class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <h1>
                            <i class="fas fa-clipboard-list"></i>
                            <?= __('attendance_management') ?>
                        </h1>
                        <p><?= __('track_and_manage_student_attendance') ?></p>
                    </div>
                    <div class="header-actions">
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                        <button class="btn btn-primary" onclick="exportAttendance()">
                            <i class="fas fa-download"></i> <?= __('export_attendance') ?>
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
                    <div class="stat-card present">
                        <div class="stat-header">
                            <div class="stat-icon present">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['present_count']) ?></div>
                        <div class="stat-label"><?= __('present') ?></div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> <?= $enhanced_stats['present_rate'] ?>% <?= __('of_total') ?>
                        </div>
                    </div>

                    <div class="stat-card absent">
                        <div class="stat-header">
                            <div class="stat-icon absent">
                                <i class="fas fa-times-circle"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['absent_count']) ?></div>
                        <div class="stat-label"><?= __('absent') ?></div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i> <?= $enhanced_stats['absent_rate'] ?>% <?= __('of_total') ?>
                        </div>
                    </div>

                    <div class="stat-card late">
                        <div class="stat-header">
                            <div class="stat-icon late">
                                <i class="fas fa-clock"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= number_format($enhanced_stats['late_count']) ?></div>
                        <div class="stat-label"><?= __('late') ?></div>
                        <div class="stat-change">
                            <i class="fas fa-chart-line"></i> <?= $enhanced_stats['total_sessions'] ?> <?= __('sessions') ?>
                        </div>
                    </div>

                    <div class="stat-card overall">
                        <div class="stat-header">
                            <div class="stat-icon overall">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                        </div>
                        <div class="stat-value"><?= $enhanced_stats['attendance_rate'] ?>%</div>
                        <div class="stat-label"><?= __('overall_attendance_rate') ?></div>
                        <div class="stat-change">
                            <i class="fas fa-users"></i> <?= $enhanced_stats['total_students'] ?> <?= __('students') ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Quick Actions -->
            <section class="quick-actions">
                <h3 class="quick-actions-title"><?= __('quick_actions') ?></h3>
                <div class="actions-grid">
                    <div class="action-card create-session" onclick="openCreateSessionModal()">
                        <div class="action-header">
                            <div class="action-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="action-content">
                                <h4 class="action-title"><?= __('create_session') ?></h4>
                                <p class="action-description"><?= __('create_new_attendance_session') ?></p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                        <div class="action-badge">
                            <i class="fas fa-clock"></i>
                            <?= __('quick_setup') ?>
                        </div>
                        <div class="action-stats">
                            <div class="action-stat">
                                <i class="fas fa-calendar"></i>
                                <?= $enhanced_stats['total_sessions'] ?> <?= __('sessions') ?>
                            </div>
                            <div class="action-stat">
                                <i class="fas fa-users"></i>
                                <?= $enhanced_stats['total_students'] ?> <?= __('students') ?>
                            </div>
                        </div>
                    </div>

                    <div class="action-card bulk-attendance" onclick="openBulkAttendanceModal()">
                        <div class="action-header">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="action-content">
                                <h4 class="action-title"><?= __('bulk_attendance') ?></h4>
                                <p class="action-description"><?= __('mark_attendance_for_multiple_students') ?></p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                        <div class="action-badge">
                            <i class="fas fa-bolt"></i>
                            <?= __('efficient') ?>
                        </div>
                        <div class="action-stats">
                            <div class="action-stat">
                                <i class="fas fa-check-circle"></i>
                                <?= $enhanced_stats['present_count'] ?> <?= __('present') ?>
                            </div>
                            <div class="action-stat">
                                <i class="fas fa-times-circle"></i>
                                <?= $enhanced_stats['absent_count'] ?> <?= __('absent') ?>
                            </div>
                        </div>
                    </div>

                    <div class="action-card reports" onclick="viewAttendanceReports()">
                        <div class="action-header">
                            <div class="action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="action-content">
                                <h4 class="action-title"><?= __('attendance_reports') ?></h4>
                                <p class="action-description"><?= __('view_detailed_attendance_analytics') ?></p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                        <div class="action-badge">
                            <i class="fas fa-chart-line"></i>
                            <?= __('analytics') ?>
                        </div>
                        <div class="action-stats">
                            <div class="action-stat">
                                <i class="fas fa-percentage"></i>
                                <?= $enhanced_stats['attendance_rate'] ?>% <?= __('rate') ?>
                            </div>
                            <div class="action-stat">
                                <i class="fas fa-clock"></i>
                                <?= $enhanced_stats['late_count'] ?> <?= __('late') ?>
                            </div>
                        </div>
                    </div>

                    <div class="action-card export" onclick="exportAttendance()">
                        <div class="action-header">
                            <div class="action-icon">
                                <i class="fas fa-file-export"></i>
                            </div>
                            <div class="action-content">
                                <h4 class="action-title"><?= __('export_data') ?></h4>
                                <p class="action-description"><?= __('export_attendance_data_to_csv') ?></p>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-arrow-right"></i>
                            </div>
                        </div>
                        <div class="action-badge">
                            <i class="fas fa-download"></i>
                            <?= __('download') ?>
                        </div>
                        <div class="action-stats">
                            <div class="action-stat">
                                <i class="fas fa-file-csv"></i>
                                CSV <?= __('format') ?>
                            </div>
                            <div class="action-stat">
                                <i class="fas fa-database"></i>
                                <?= $enhanced_stats['total_records'] ?> <?= __('records') ?>
                            </div>
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
                                placeholder="<?= __('search_attendance_records') ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('course') ?></label>
                            <select name="course" class="filter-input">
                                <option value=""><?= __('all_courses') ?></option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= $inputs['course'] == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?> (<?= $course['session_count'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('status') ?></label>
                            <select name="status" class="filter-input">
                                <option value=""><?= __('all_statuses') ?></option>
                                <option value="present" <?= $inputs['status'] === 'present' ? 'selected' : '' ?>><?= __('present') ?></option>
                                <option value="absent" <?= $inputs['status'] === 'absent' ? 'selected' : '' ?>><?= __('absent') ?></option>
                                <option value="late" <?= $inputs['status'] === 'late' ? 'selected' : '' ?>><?= __('late') ?></option>
                                <option value="excused" <?= $inputs['status'] === 'excused' ? 'selected' : '' ?>><?= __('excused') ?></option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('date_from') ?></label>
                            <input type="date" name="date_from" class="filter-input"
                                value="<?= htmlspecialchars($inputs['date_from']) ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('date_to') ?></label>
                            <input type="date" name="date_to" class="filter-input"
                                value="<?= htmlspecialchars($inputs['date_to']) ?>">
                        </div>

                        <div class="filter-group">
                            <label class="filter-label"><?= __('sort_by') ?></label>
                            <select name="sort" class="filter-input">
                                <option value="recent" <?= $inputs['sort'] === 'recent' ? 'selected' : '' ?>><?= __('most_recent') ?></option>
                                <option value="oldest" <?= $inputs['sort'] === 'oldest' ? 'selected' : '' ?>><?= __('oldest_first') ?></option>
                                <option value="course" <?= $inputs['sort'] === 'course' ? 'selected' : '' ?>><?= __('course_name') ?></option>
                                <option value="student" <?= $inputs['sort'] === 'student' ? 'selected' : '' ?>><?= __('student_name') ?></option>
                                <option value="attendance_rate" <?= $inputs['sort'] === 'attendance_rate' ? 'selected' : '' ?>><?= __('attendance_rate') ?></option>
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

            <!-- Attendance Records Table -->
            <section class="attendance-section">
                <div class="attendance-header">
                    <h3 class="attendance-title"><?= __('attendance_records') ?></h3>
                    <span class="attendance-count">
                        <?= number_format($total_count) ?> <?= __('records_found') ?>
                    </span>
                </div>

                <?php if (empty($attendance_records)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3><?= __('no_attendance_records_found') ?></h3>
                        <p><?= __('try_adjusting_your_filters') ?></p>
                    </div>
                <?php else: ?>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th><?= __('student') ?></th>
                                <th><?= __('course') ?></th>
                                <th><?= __('session') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('attendance_rate') ?></th>
                                <th><?= __('date') ?></th>
                                <th><?= __('notes') ?></th>
                                <th width="100"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                                <tr class="attendance-row">
                                    <td>
                                        <div class="attendance-info">
                                            <div class="attendance-avatar">
                                                <?= strtoupper(substr($record['student_name'], 0, 1)) ?>
                                            </div>
                                            <div class="attendance-details">
                                                <h4><?= htmlspecialchars($record['student_name']) ?></h4>
                                                <p><?= htmlspecialchars($record['student_email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="attendance-meta">
                                            <div class="attendance-course"><?= htmlspecialchars($record['course_title']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="attendance-meta">
                                            <div class="attendance-course"><?= htmlspecialchars($record['session_title']) ?></div>
                                            <div class="attendance-date">
                                                <?= date('g:i A', strtotime($record['start_time'])) ?> -
                                                <?= date('g:i A', strtotime($record['end_time'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?= $record['attendance_status'] ?>">
                                            <?= __($record['attendance_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="attendance-rate <?= $record['attendance_rate'] >= 90 ? 'excellent' : ($record['attendance_rate'] >= 80 ? 'good' : ($record['attendance_rate'] >= 70 ? 'average' : 'poor')) ?>">
                                            <?= $record['attendance_rate'] ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div class="attendance-meta">
                                            <div class="attendance-date">
                                                <?= date('M j, Y', strtotime($record['session_date'])) ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($record['notes']): ?>
                                            <span title="<?= htmlspecialchars($record['notes']) ?>">
                                                <?= htmlspecialchars(substr($record['notes'], 0, 30)) ?>...
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-action edit"
                                                onclick="editAttendance(<?= $record['id'] ?>)"
                                                title="<?= __('edit_attendance') ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn-action view"
                                                onclick="viewAttendanceDetails(<?= $record['id'] ?>)"
                                                title="<?= __('view_details') ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

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

    <!-- Create Session Modal -->
    <div id="createSessionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= __('create_attendance_session') ?></h3>
                <span class="close" onclick="closeModal('createSessionModal')">&times;</span>
            </div>
            <form method="POST" id="createSessionForm">
                <input type="hidden" name="action" value="create_session">

                <div class="form-group">
                    <label class="form-label"><?= __('course') ?> <span style="color: red;">*</span></label>
                    <select name="course_id" class="form-input" required>
                        <option value=""><?= __('select_course') ?></option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('session_title') ?> <span style="color: red;">*</span></label>
                    <input type="text" name="session_title" class="form-input" required
                        placeholder="<?= __('enter_session_title') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('session_date') ?> <span style="color: red;">*</span></label>
                    <input type="date" name="session_date" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('start_time') ?> <span style="color: red;">*</span></label>
                    <input type="time" name="start_time" class="form-input" required>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('end_time') ?></label>
                    <input type="time" name="end_time" class="form-input">
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('createSessionModal')">
                        <?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?= __('create_session') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Attendance Modal -->
    <div id="editAttendanceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?= __('edit_attendance') ?></h3>
                <span class="close" onclick="closeModal('editAttendanceModal')">&times;</span>
            </div>
            <form method="POST" id="editAttendanceForm">
                <input type="hidden" name="action" value="mark_attendance">
                <input type="hidden" name="session_id" id="editSessionId">
                <input type="hidden" name="student_id" id="editStudentId">

                <div class="form-group">
                    <label class="form-label"><?= __('attendance_status') ?> <span style="color: red;">*</span></label>
                    <select name="attendance_status" class="form-input" required>
                        <option value="present"><?= __('present') ?></option>
                        <option value="absent"><?= __('absent') ?></option>
                        <option value="late"><?= __('late') ?></option>
                        <option value="excused"><?= __('excused') ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label"><?= __('notes') ?></label>
                    <textarea name="notes" class="form-input form-textarea"
                        placeholder="<?= __('add_notes_optional') ?>"></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-outline" onclick="closeModal('editAttendanceModal')">
                        <?= __('cancel') ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?= __('save_changes') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Configuration
        window.attendanceConfig = {
            totalCount: <?= $total_count ?>,
            currentPage: <?= $inputs['page'] ?>,
            totalPages: <?= $total_pages ?>,
            perPage: <?= $inputs['per_page'] ?>,
            language: '<?= $_SESSION['user_language'] ?? 'fr' ?>',
            instructorId: <?= $instructor_id ?>
        };

        // Modal Functions
        function openCreateSessionModal() {
            document.getElementById('createSessionModal').style.display = 'block';
        }

        function openBulkAttendanceModal() {
            alert('<?= __('bulk_attendance_feature_coming_soon') ?>');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function editAttendance(recordId) {
            // Set the record ID for editing
            document.getElementById('editSessionId').value = recordId;
            document.getElementById('editStudentId').value = recordId;
            document.getElementById('editAttendanceModal').style.display = 'block';
        }

        function viewAttendanceDetails(recordId) {
            // Open attendance details in new window
            window.open(`attendance_details.php?id=${recordId}`, '_blank');
        }

        function viewAttendanceReports() {
            window.open('attendance_reports.php', '_blank');
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

        function exportAttendance() {
            const url = new URL(window.location);
            url.searchParams.set('export', 'csv');
            window.open(url.toString(), '_blank');
        }

        function refreshData() {
            window.location.reload();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
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

            // Set default date to today for new sessions
            const today = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="session_date"]').value = today;
        });
    </script>
</body>

</html>