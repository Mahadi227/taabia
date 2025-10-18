<?php

/**
 * Modern Student LMS Dashboard
 * Complete learning management system interface for students
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Enhanced statistics with comprehensive data
try {
    // Core Statistics - check both possible table structures
    try {
        $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ?");
        $stmt1->execute([$student_id]);
        $total_courses = $stmt1->fetchColumn();
    } catch (PDOException $e) {
        // Try alternative table name
        try {
            $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM course_enrollments WHERE student_id = ?");
            $stmt1->execute([$student_id]);
            $total_courses = $stmt1->fetchColumn();
        } catch (PDOException $e2) {
            $total_courses = 0;
        }
    }

    // Calculate overall progress - compatible with your database
    $average_progress = 0;
    try {
        // Method 1: Try with lesson_progress table - check multiple column variations
        $progress_data = null;
        $methods_tried = [];

        // Simplified: Just count records in lesson_progress (presence = lesson viewed)
        try {
            $stmt_progress = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT l.id) as total_lessons,
                    COUNT(DISTINCT lp.lesson_id) as completed_lessons
                FROM student_courses sc
                JOIN lessons l ON l.course_id = sc.course_id
                LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = sc.student_id
                WHERE sc.student_id = ?
            ");
            $stmt_progress->execute([$student_id]);
            $progress_data = $stmt_progress->fetch();
            error_log("Progress calculation succeeded - simple count method");
        } catch (PDOException $e) {
            error_log("Progress calculation failed: " . $e->getMessage());
            $progress_data = ['total_lessons' => 0, 'completed_lessons' => 0];
        }

        if ($progress_data && $progress_data['total_lessons'] > 0) {
            $average_progress = round(($progress_data['completed_lessons'] / $progress_data['total_lessons']) * 100);
        } else {
            // Method 2: Try calculating from course-level progress if available
            try {
                $stmt_avg = $pdo->prepare("
                    SELECT AVG(progress) as avg_progress 
                    FROM student_courses 
                    WHERE student_id = ? AND progress IS NOT NULL
                ");
                $stmt_avg->execute([$student_id]);
                $avg_result = $stmt_avg->fetch();
                if ($avg_result && $avg_result['avg_progress'] !== null) {
                    $average_progress = round($avg_result['avg_progress']);
                }
            } catch (PDOException $e2) {
                // Keep average_progress at 0
            }
        }
    } catch (PDOException $e) {
        // Method 3: Calculate manually from each course
        if ($total_courses > 0 && !empty($recent_courses)) {
            $total_progress = 0;
            foreach ($recent_courses as $course) {
                if (isset($course['progress_percent'])) {
                    $total_progress += $course['progress_percent'];
                }
            }
            $average_progress = $total_courses > 0 ? round($total_progress / $total_courses) : 0;
        }
    }

    // Learning streak (consecutive days) - check multiple possible column names
    try {
        $stmt_streak = $pdo->prepare("
            SELECT COUNT(DISTINCT DATE(last_accessed)) as streak_days
            FROM student_courses 
            WHERE student_id = ? 
            AND last_accessed >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt_streak->execute([$student_id]);
        $learning_streak = $stmt_streak->fetchColumn();
    } catch (PDOException $e) {
        // Fallback: count distinct days from lesson_progress
        try {
            $stmt_streak_alt = $pdo->prepare("
                SELECT COUNT(DISTINCT DATE(lp.updated_at)) as streak_days
                FROM lesson_progress lp
                WHERE lp.student_id = ? 
                AND lp.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt_streak_alt->execute([$student_id]);
            $learning_streak = $stmt_streak_alt->fetchColumn();
        } catch (PDOException $e2) {
            $learning_streak = 0;
        }
    }

    // Certificates earned - check if table exists
    try {
        $stmt_certs = $pdo->prepare("SELECT COUNT(*) FROM course_certificates WHERE student_id = ?");
        $stmt_certs->execute([$student_id]);
        $certificates_earned = $stmt_certs->fetchColumn();
    } catch (PDOException $e) {
        $certificates_earned = 0;
    }

    // Total time spent (hours) - simplified
    $total_hours = 0;
    try {
        // Count number of lessons accessed (not time-based for now)
        $stmt_time = $pdo->prepare("
            SELECT COUNT(*) FROM lesson_progress WHERE student_id = ?
        ");
        $stmt_time->execute([$student_id]);
        $lessons_accessed = $stmt_time->fetchColumn() ?? 0;
        // Estimate: average 10 minutes per lesson
        $total_hours = round($lessons_accessed * 10 / 60, 1);
    } catch (PDOException $e) {
        $total_hours = 0;
    }

    // Recent courses with detailed progress - simplified query
    try {
        // First, try to get all enrolled courses
        $stmt_courses = $pdo->prepare("
            SELECT 
                c.id, c.title, c.image_url, c.description,
                u.full_name as instructor_name
            FROM student_courses sc
            JOIN courses c ON sc.course_id = c.id
            JOIN users u ON c.instructor_id = u.id
            WHERE sc.student_id = ?
            LIMIT 6
        ");
        $stmt_courses->execute([$student_id]);
        $recent_courses = $stmt_courses->fetchAll();

        // Calculate progress for each course
        foreach ($recent_courses as &$course) {
            // Count total lessons
            $stmt_lessons = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
            $stmt_lessons->execute([$course['id']]);
            $course['total_lessons'] = $stmt_lessons->fetchColumn();

            // Count lessons in progress (simplified - any record = lesson viewed)
            try {
                $stmt_completed = $pdo->prepare("
                    SELECT COUNT(DISTINCT lp.lesson_id) 
                    FROM lesson_progress lp
                    JOIN lessons l ON lp.lesson_id = l.id
                    WHERE l.course_id = ? AND lp.student_id = ?
                ");
                $stmt_completed->execute([$course['id'], $student_id]);
                $course['completed_lessons'] = $stmt_completed->fetchColumn();
            } catch (PDOException $e) {
                $course['completed_lessons'] = 0;
            }

            // Calculate percentage
            $course['progress_percent'] = $course['total_lessons'] > 0
                ? round(($course['completed_lessons'] / $course['total_lessons']) * 100)
                : 0;
        }
        unset($course);
    } catch (PDOException $e) {
        error_log("Error fetching courses: " . $e->getMessage());
        $recent_courses = [];
    }

    // Upcoming assignments/deadlines (if table exists)
    try {
        $stmt_assignments = $pdo->prepare("
            SELECT a.*, c.title as course_title
            FROM assignments a
            JOIN courses c ON a.course_id = c.id
            JOIN student_courses sc ON sc.course_id = c.id
            WHERE sc.student_id = ? 
            AND a.deadline >= CURDATE()
            ORDER BY a.deadline ASC
            LIMIT 5
        ");
        $stmt_assignments->execute([$student_id]);
        $upcoming_assignments = $stmt_assignments->fetchAll();
    } catch (PDOException $e) {
        $upcoming_assignments = [];
    }

    // Recent achievements/certificates - check if table exists
    try {
        $stmt_achievements = $pdo->prepare("
            SELECT cc.*, c.title as course_title
            FROM course_certificates cc
            JOIN courses c ON cc.course_id = c.id
            WHERE cc.student_id = ?
            ORDER BY cc.issued_at DESC
            LIMIT 3
        ");
        $stmt_achievements->execute([$student_id]);
        $recent_certificates = $stmt_achievements->fetchAll();
    } catch (PDOException $e) {
        $recent_certificates = [];
    }

    // Recommended courses
    $stmt_recommended = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.price, c.image_url,
               u.full_name as instructor_name,
               COUNT(DISTINCT sc2.student_id) as enrollment_count,
               COUNT(DISTINCT l.id) as lesson_count
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        LEFT JOIN student_courses sc2 ON sc2.course_id = c.id
        LEFT JOIN lessons l ON l.course_id = c.id
        WHERE c.is_active = 1
        AND c.id NOT IN (SELECT course_id FROM student_courses WHERE student_id = ?)
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 4
    ");
    $stmt_recommended->execute([$student_id]);
    $recommended_courses = $stmt_recommended->fetchAll();

    // Unread messages - check for different column names
    try {
        // Try is_read first
        $stmt_messages = $pdo->prepare("
            SELECT COUNT(*) FROM messages 
            WHERE receiver_id = ? AND is_read = 0
        ");
        $stmt_messages->execute([$student_id]);
        $unread_messages = $stmt_messages->fetchColumn();
    } catch (PDOException $e) {
        // Try read_at (if NULL = unread)
        try {
            $stmt_messages = $pdo->prepare("
                SELECT COUNT(*) FROM messages 
                WHERE receiver_id = ? AND read_at IS NULL
            ");
            $stmt_messages->execute([$student_id]);
            $unread_messages = $stmt_messages->fetchColumn();
        } catch (PDOException $e2) {
            // Try status column
            try {
                $stmt_messages = $pdo->prepare("
                    SELECT COUNT(*) FROM messages 
                    WHERE receiver_id = ? AND status = 'unread'
                ");
                $stmt_messages->execute([$student_id]);
                $unread_messages = $stmt_messages->fetchColumn();
            } catch (PDOException $e3) {
                // Just count all messages
                try {
                    $stmt_messages = $pdo->prepare("
                        SELECT COUNT(*) FROM messages WHERE receiver_id = ?
                    ");
                    $stmt_messages->execute([$student_id]);
                    $unread_messages = $stmt_messages->fetchColumn();
                } catch (PDOException $e4) {
                    $unread_messages = 0;
                }
            }
        }
    }

    // Weekly activity data for chart - with fallback
    try {
        $stmt_activity = $pdo->prepare("
            SELECT DATE(COALESCE(last_accessed, updated_at)) as date, COUNT(*) as count
            FROM student_courses
            WHERE student_id = ? 
            AND COALESCE(last_accessed, updated_at) >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(COALESCE(last_accessed, updated_at))
            ORDER BY date ASC
        ");
        $stmt_activity->execute([$student_id]);
        $weekly_activity = $stmt_activity->fetchAll();
    } catch (PDOException $e) {
        $weekly_activity = [];
    }
} catch (PDOException $e) {
    error_log("Database error in student dashboard: " . $e->getMessage());
    // Show error in development mode for debugging
    $error_message = "Erreur base de données: " . $e->getMessage();
    $total_courses = 0;
    $average_progress = 0;
    $learning_streak = 0;
    $certificates_earned = 0;
    $total_hours = 0;
    $recent_courses = [];
    $upcoming_assignments = [];
    $recent_certificates = [];
    $recommended_courses = [];
    $unread_messages = 0;
    $weekly_activity = [];
}

// Debug: Log what we found
error_log("Student Dashboard Debug - Student ID: $student_id");
error_log("Total Courses: $total_courses");
error_log("Average Progress: $average_progress");
error_log("Recent Courses Count: " . count($recent_courses));

// If we still have courses but no progress calculated, recalculate from courses
if ($average_progress == 0 && $total_courses > 0 && !empty($recent_courses)) {
    $total_progress = 0;
    $courses_with_progress = 0;
    foreach ($recent_courses as $course) {
        if (isset($course['progress_percent']) && $course['progress_percent'] > 0) {
            $total_progress += $course['progress_percent'];
            $courses_with_progress++;
        }
    }
    if ($courses_with_progress > 0) {
        $average_progress = round($total_progress / $courses_with_progress);
        error_log("Recalculated Average Progress: $average_progress (from $courses_with_progress courses)");
    }
}

// Get user info
$user_name = $_SESSION['full_name'] ?? 'Student';
$user_email = $_SESSION['email'] ?? '';
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?> | TaaBia Learning Platform</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary: #004075;
            --primary-dark: #004082;
            --secondary: #004080;
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
            --white: #ffffff;
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
            line-height: 1.6;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--white);
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
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .sidebar-nav {
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
            position: relative;
        }

        .nav-item i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
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

        .nav-item .badge {
            margin-left: auto;
            background: var(--danger);
            color: var(--white);
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Header */
        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.75rem;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 1.25rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--white);
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-icon.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-content h3 {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 600;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-content .value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: var(--white);
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            font-size: 1.125rem;
            color: var(--gray-900);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-header .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Course Card */
        .course-item {
            display: flex;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            transition: background 0.3s ease;
            margin-bottom: 1rem;
        }

        .course-item:hover {
            background: var(--gray-50);
        }

        .course-thumb {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
            flex-shrink: 0;
        }

        .course-info {
            flex: 1;
        }

        .course-info h4 {
            color: var(--gray-900);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .course-info .instructor {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .progress-bar-container {
            background: var(--gray-200);
            height: 6px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            transition: width 0.5s ease;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.75rem;
            color: var(--gray-600);
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: var(--white);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        /* Badge */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
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
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            color: var(--primary);
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .quick-action span {
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* ========================================
           HAMBURGER MENU - ALWAYS VISIBLE
        ======================================== */

        .hamburger-menu {
            display: flex;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1100;
            background: var(--white);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .hamburger-menu:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .hamburger-menu:active {
            transform: scale(0.95);
        }

        /* Tooltip for hamburger */
        .hamburger-menu::before {
            content: attr(data-tooltip);
            position: absolute;
            left: 60px;
            background: var(--gray-900);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.85rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
            font-weight: 500;
        }

        .hamburger-menu:hover::before {
            opacity: 1;
        }

        .hamburger-menu span {
            display: block;
            width: 24px;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
            transition: all 0.3s ease;
        }

        .hamburger-menu:hover span {
            background: var(--secondary);
        }

        /* Hamburger animation when active (transforms to X) */
        .hamburger-menu.active span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-menu.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-menu.active span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        .sidebar-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }

        /* Desktop: Sidebar toggle behavior */
        @media (min-width: 769px) {
            .sidebar {
                transition: transform 0.3s ease, width 0.3s ease;
            }

            .sidebar.collapsed {
                transform: translateX(-280px);
            }

            .main-content {
                transition: margin-left 0.3s ease;
            }

            .sidebar.collapsed~.dashboard-container .main-content {
                margin-left: 0;
            }

            .site-footer {
                transition: margin-left 0.3s ease;
            }

            .sidebar.collapsed~* .site-footer {
                margin-left: 0;
            }

            /* Hide overlay on desktop */
            .sidebar-overlay {
                display: none !important;
            }
        }

        /* ========================================
           BACK TO TOP BUTTON
        ======================================== */

        .back-to-top {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .back-to-top.show {
            display: flex;
            animation: fadeInUp 0.3s ease;
        }

        .back-to-top:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }

        .back-to-top:active {
            transform: translateY(-2px);
        }

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

        /* ========================================
           ENHANCED FOOTER STYLES
        ======================================== */

        .site-footer {
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            color: white;
            padding: 0;
            margin-top: 4rem;
            margin-left: 280px;
            position: relative;
        }

        .site-footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Footer CTA Section */
        .footer-cta {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 3rem 2rem;
            border-radius: 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .footer-cta-content h3 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
            color: white;
        }

        .footer-cta-content p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
        }

        .footer-newsletter {
            flex: 1;
            max-width: 500px;
        }

        .newsletter-form {
            display: flex;
            gap: 0.5rem;
        }

        .newsletter-form input {
            flex: 1;
            padding: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .newsletter-form input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .newsletter-form input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
        }

        .newsletter-form button {
            padding: 1rem 2rem;
            background: white;
            color: var(--primary);
            border: none;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .newsletter-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
        }

        /* Footer Grid */
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2.5rem;
            padding: 3rem 0;
        }

        .footer-column h3,
        .footer-column h4 {
            margin-bottom: 1.5rem;
        }

        .footer-title {
            font-size: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .footer-title i {
            color: var(--primary);
        }

        .footer-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .footer-heading {
            color: white;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-heading i {
            color: var(--primary);
            font-size: 1rem;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: white;
            padding-left: 0.5rem;
        }

        .footer-links i {
            font-size: 0.75rem;
            color: var(--primary);
        }

        /* Footer Stats */
        .footer-stats {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }

        .footer-stat {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
        }

        .footer-stat i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .footer-stat strong {
            color: white;
        }

        /* Contact Info */
        .footer-contact-info {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .contact-item i {
            color: var(--primary);
            font-size: 1rem;
        }

        .contact-item a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-item a:hover {
            color: white;
        }

        /* Social & Download Section */
        .footer-social-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 2rem 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            gap: 2rem;
            flex-wrap: wrap;
        }

        .footer-social-content h4,
        .footer-download h4 {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-social-large {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .social-link-large {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .social-link-large:hover {
            background: var(--primary);
            transform: translateY(-5px) rotate(5deg);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Download Buttons */
        .download-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .download-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .download-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: white;
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }

        .download-btn i {
            font-size: 2rem;
        }

        .download-btn small {
            display: block;
            font-size: 0.7rem;
            opacity: 0.8;
        }

        .download-btn strong {
            display: block;
            font-size: 0.95rem;
        }

        /* Footer Bottom */
        .footer-bottom {
            padding: 1.5rem 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-copyright {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .footer-copyright strong {
            color: white;
        }

        .footer-separator {
            margin: 0 0.5rem;
            color: rgba(255, 255, 255, 0.3);
        }

        .footer-links-bottom {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .footer-links-bottom a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }

        .footer-links-bottom a:hover {
            color: white;
        }

        .footer-links-bottom a i {
            font-size: 0.75rem;
        }

        .footer-links-bottom span {
            color: rgba(255, 255, 255, 0.3);
        }

        /* ========================================
           MOBILE RESPONSIVE
        ======================================== */

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 5rem;
            }

            .site-footer {
                margin-left: 0;
            }

            .footer-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .footer-cta {
                flex-direction: column;
                text-align: center;
                padding: 2rem 1rem;
            }

            .footer-newsletter {
                max-width: 100%;
            }

            .footer-social-section {
                flex-direction: column;
                gap: 2rem;
            }

            .footer-social-content,
            .footer-download {
                text-align: center;
            }

            .footer-social-large {
                justify-content: center;
            }

            .download-buttons {
                justify-content: center;
            }

            .footer-bottom {
                flex-direction: column;
                text-align: center;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .back-to-top {
                bottom: 1rem;
                right: 1rem;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .hamburger-menu {
                width: 45px;
                height: 45px;
            }

            .hamburger-menu span {
                width: 20px;
                height: 2px;
            }

            .footer-container {
                padding: 0 1rem;
            }

            .newsletter-form {
                flex-direction: column;
            }

            .newsletter-form button {
                width: 100%;
                justify-content: center;
            }

            .download-btn {
                width: 100%;
                justify-content: center;
            }

            .footer-stats {
                gap: 0.5rem;
            }

            .footer-contact-info {
                gap: 0.5rem;
            }

            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <!-- Hamburger Menu Button -->
    <button class="hamburger-menu" id="hamburgerBtn"
        aria-label="Toggle Menu"
        data-tooltip="<?= __('toggle_sidebar') ?? 'Masquer/Afficher le menu' ?>">
        <span></span>
        <span></span>
        <span></span>
    </button>

    <!-- Overlay for mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
                <button class="sidebar-close" id="sidebarClose" aria-label="Close Menu">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item active">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item">
                    <i class="fas fa-book-open"></i>
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
                <a href="assignments.php" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <?= __('assignments') ?? 'Devoirs' ?>
                </a>
                <a href="quizzes.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    <?= __('quizzes') ?? 'Quiz' ?>
                </a>
                <a href="attendance.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <?= __('attendance') ?? 'Présence' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                    <?php if ($unread_messages > 0): ?>
                        <span class="badge"><?= $unread_messages ?></span>
                    <?php endif; ?>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="language_settings.php" class="nav-item">
                    <i class="fas fa-globe"></i>
                    <?= __('language') ?? 'Langue' ?>
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
                <div>
                    <h1><?= __('welcome') ?? 'Bienvenue' ?>, <?= htmlspecialchars($user_name) ?>! 👋</h1>
                    <p><?= __('dashboard_subtitle') ?? 'Continuez votre apprentissage et suivez vos progrès' ?></p>
                    <?php if (isset($error_message)): ?>
                        <div style="color: red; margin-top: 0.5rem; font-size: 0.9rem;">
                            ⚠️ <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="header-actions">
                    <?php include '../includes/language_switcher.php'; ?>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= __('enrolled_courses') ?? 'Cours Inscrits' ?></h3>
                        <div class="value"><?= $total_courses ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= __('progress') ?? 'Progression' ?></h3>
                        <div class="value"><?= $average_progress ?>%</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= __('learning_streak') ?? 'Série d\'apprentissage' ?></h3>
                        <div class="value"><?= $learning_streak ?></div>
                    </div>
                </div>

                <a href="my_certificates.php" style="text-decoration: none;">
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= __('certificates') ?? 'Certificats' ?></h3>
                            <div class="value"><?= $certificates_earned ?></div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="all_courses.php" class="quick-action">
                    <i class="fas fa-compass"></i>
                    <span><?= __('explore_courses') ?? 'Explorer' ?></span>
                </a>
                <a href="my_courses.php" class="quick-action">
                    <i class="fas fa-play"></i>
                    <span><?= __('continue_learning') ?? 'Continuer' ?></span>
                </a>
                <a href="messages.php" class="quick-action">
                    <i class="fas fa-comments"></i>
                    <span><?= __('messages') ?? 'Messages' ?></span>
                </a>
                <a href="profile.php" class="quick-action">
                    <i class="fas fa-cog"></i>
                    <span><?= __('settings') ?? 'Paramètres' ?></span>
                </a>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- My Courses -->
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-book-open"></i>
                            <?= __('my_courses') ?? 'Mes Cours' ?>
                        </h3>
                        <a href="my_courses.php" class="view-all"><?= __('view_all') ?? 'Voir tout' ?> →</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_courses)): ?>
                            <div class="empty-state">
                                <i class="fas fa-book-open"></i>
                                <h3><?= __('no_courses_enrolled') ?? 'Aucun cours inscrit' ?></h3>
                                <p><?= __('start_learning_today') ?? 'Commencez à apprendre aujourd\'hui' ?>!</p>
                                <a href="all_courses.php" class="btn btn-primary" style="margin-top: 1rem;">
                                    <i class="fas fa-search"></i> <?= __('explore_courses') ?? 'Explorer les cours' ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_courses as $course): ?>
                                <div class="course-item">
                                    <?php if ($course['image_url']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($course['image_url']) ?>" alt="" class="course-thumb">
                                    <?php else: ?>
                                        <div class="course-thumb">
                                            <i class="fas fa-book"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="course-info">
                                        <h4><?= htmlspecialchars($course['title']) ?></h4>
                                        <div class="instructor">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor_name']) ?>
                                        </div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?= $course['progress_percent'] ?>%"></div>
                                        </div>
                                        <div class="progress-info">
                                            <span><?= $course['completed_lessons'] ?>/<?= $course['total_lessons'] ?> <?= __('lessons') ?? 'leçons' ?></span>
                                            <span><?= $course['progress_percent'] ?>%</span>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="view_course.php?course_id=<?= $course['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-play"></i> <?= __('continue') ?? 'Continuer' ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sidebar -->
                <div style="display: flex; flex-direction: column; gap: 2rem;">
                    <!-- Achievements -->
                    <?php if (!empty($recent_certificates)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-trophy"></i>
                                    <?= __('achievements') ?? 'Réalisations' ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($recent_certificates as $cert): ?>
                                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-200);">
                                        <div style="display: flex; align-items: center; gap: 1rem;">
                                            <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                                                <i class="fas fa-certificate"></i>
                                            </div>
                                            <div style="flex: 1;">
                                                <h4 style="color: var(--gray-900); font-size: 0.95rem; margin-bottom: 0.25rem;">
                                                    <?= htmlspecialchars($cert['course_title']) ?>
                                                </h4>
                                                <p style="color: var(--gray-600); font-size: 0.8rem;">
                                                    <?= date('M d, Y', strtotime($cert['issued_at'])) ?>
                                                </p>
                                            </div>
                                            <a href="../instructor/view_certificate.php?id=<?= $cert['id'] ?>" class="btn btn-secondary btn-sm" target="_blank">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recommended Courses -->
                    <?php if (!empty($recommended_courses)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-lightbulb"></i>
                                    <?= __('recommended') ?? 'Recommandés' ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php foreach (array_slice($recommended_courses, 0, 3) as $course): ?>
                                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-200);">
                                        <h4 style="color: var(--gray-900); font-size: 0.95rem; margin-bottom: 0.5rem;">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </h4>
                                        <p style="color: var(--gray-600); font-size: 0.8rem; margin-bottom: 0.5rem;">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor_name']) ?>
                                        </p>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span style="color: var(--gray-600); font-size: 0.8rem;">
                                                <i class="fas fa-users"></i> <?= $course['enrollment_count'] ?> <?= __('students') ?? 'étudiants' ?>
                                            </span>
                                            <a href="view_course.php?course_id=<?= $course['id'] ?>" class="btn btn-primary btn-sm">
                                                <?= __('view') ?? 'Voir' ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Upcoming Assignments -->
                    <?php if (!empty($upcoming_assignments)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3>
                                    <i class="fas fa-tasks"></i>
                                    <?= __('upcoming_assignments') ?? 'Devoirs à venir' ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php foreach ($upcoming_assignments as $assignment): ?>
                                    <div style="padding: 1rem 0; border-bottom: 1px solid var(--gray-200);">
                                        <h4 style="color: var(--gray-900); font-size: 0.95rem; margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($assignment['title']) ?>
                                        </h4>
                                        <p style="color: var(--gray-600); font-size: 0.8rem; margin-bottom: 0.5rem;">
                                            <?= htmlspecialchars($assignment['course_title']) ?>
                                        </p>
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> <?= date('M d, Y', strtotime($assignment['deadline'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Smooth animations on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .card, .quick-action');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });

            // Animate progress bars
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress-bar');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
            }, 500);
        });

        // Hamburger Menu Toggle - Works on Desktop & Mobile
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const sidebarClose = document.getElementById('sidebarClose');
        const mainContent = document.querySelector('.main-content');
        const siteFooter = document.querySelector('.site-footer');

        function toggleSidebar() {
            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                // Mobile behavior: slide with overlay
                if (sidebar.classList.contains('active')) {
                    closeSidebarMobile();
                } else {
                    openSidebarMobile();
                }
            } else {
                // Desktop behavior: collapse/expand
                sidebar.classList.toggle('collapsed');
                hamburgerBtn.classList.toggle('active');

                // Adjust main content and footer margin
                if (sidebar.classList.contains('collapsed')) {
                    mainContent.style.marginLeft = '0';
                    siteFooter.style.marginLeft = '0';
                } else {
                    mainContent.style.marginLeft = '280px';
                    siteFooter.style.marginLeft = '280px';
                }
            }
        }

        function openSidebarMobile() {
            sidebar.classList.add('active');
            sidebarOverlay.classList.add('active');
            hamburgerBtn.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeSidebarMobile() {
            sidebar.classList.remove('active');
            sidebarOverlay.classList.remove('active');
            hamburgerBtn.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Event listeners
        hamburgerBtn.addEventListener('click', toggleSidebar);
        sidebarClose.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                closeSidebarMobile();
            } else {
                toggleSidebar();
            }
        });
        sidebarOverlay.addEventListener('click', closeSidebarMobile);

        // Close sidebar when clicking on a link (mobile only)
        const navLinks = document.querySelectorAll('.nav-item');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    closeSidebarMobile();
                }
            });
        });

        // Close sidebar on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (window.innerWidth <= 768 && sidebar.classList.contains('active')) {
                    closeSidebarMobile();
                } else if (window.innerWidth > 768 && !sidebar.classList.contains('collapsed')) {
                    toggleSidebar();
                }
            }
        });

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                const isMobile = window.innerWidth <= 768;

                if (!isMobile) {
                    // Desktop mode: remove mobile classes
                    sidebar.classList.remove('active');
                    sidebarOverlay.classList.remove('active');
                    hamburgerBtn.classList.remove('active');
                    document.body.style.overflow = '';

                    // Restore margin if sidebar is not collapsed
                    if (!sidebar.classList.contains('collapsed')) {
                        mainContent.style.marginLeft = '280px';
                        siteFooter.style.marginLeft = '280px';
                    }
                } else {
                    // Mobile mode: remove desktop classes
                    sidebar.classList.remove('collapsed');
                    hamburgerBtn.classList.remove('active');
                    mainContent.style.marginLeft = '0';
                    siteFooter.style.marginLeft = '0';
                }
            }, 250);
        });

        // Update tooltip text based on sidebar state
        function updateTooltip() {
            const isMobile = window.innerWidth <= 768;
            if (isMobile) {
                hamburgerBtn.setAttribute('data-tooltip', '<?= __('menu') ?? 'Menu' ?>');
            } else {
                const isCollapsed = sidebar.classList.contains('collapsed');
                hamburgerBtn.setAttribute('data-tooltip', isCollapsed ?
                    '<?= __('show_sidebar') ?? 'Afficher le menu' ?>' :
                    '<?= __('hide_sidebar') ?? 'Masquer le menu' ?>');
            }
        }

        // Update tooltip on toggle
        hamburgerBtn.addEventListener('click', () => {
            setTimeout(updateTooltip, 100);
        });

        // Initialize tooltip
        updateTooltip();

        // ========================================
        // BACK TO TOP BUTTON
        // ========================================

        const backToTopBtn = document.getElementById('backToTop');

        // Show/hide button based on scroll position
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopBtn.classList.add('show');
            } else {
                backToTopBtn.classList.remove('show');
            }
        });

        // Scroll to top smoothly
        backToTopBtn.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // ========================================
        // NEWSLETTER FORM
        // ========================================

        const newsletterForm = document.querySelector('.newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const email = newsletterForm.querySelector('input[type="email"]').value;

                // Show success message (you can customize this)
                alert('<?= __('newsletter_success') ?? 'Merci pour votre abonnement!' ?> (' + email + ')');
                newsletterForm.reset();
            });
        }
    </script>

    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top" aria-label="Back to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Footer with Enhanced Sitemap -->
    <footer class="site-footer">
        <div class="footer-container">
            <!-- Footer Top - CTA Section -->
            <div class="footer-cta">
                <div class="footer-cta-content">
                    <h3><?= __('stay_connected') ?? 'Restez Connecté' ?></h3>
                    <p><?= __('get_latest_updates') ?? 'Recevez les dernières mises à jour et nouveaux cours directement dans votre boîte mail' ?></p>
                </div>
                <div class="footer-newsletter">
                    <form action="#" method="POST" class="newsletter-form">
                        <input type="email" placeholder="<?= __('your_email') ?? 'Votre email' ?>" required>
                        <button type="submit">
                            <i class="fas fa-paper-plane"></i>
                            <?= __('subscribe') ?? 'S\'abonner' ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Footer Main - Sitemap Grid -->
            <div class="footer-grid">
                <!-- About Section -->
                <div class="footer-column">
                    <h3 class="footer-title">
                        <i class="fas fa-graduation-cap"></i>
                        TaaBia LMS
                    </h3>
                    <p class="footer-description">
                        <?= __('footer_description') ?? 'Plateforme d\'apprentissage moderne pour développer vos compétences et atteindre vos objectifs éducatifs.' ?>
                    </p>
                    <div class="footer-stats">
                        <div class="footer-stat">
                            <i class="fas fa-users"></i>
                            <span><strong>1,000+</strong> <?= __('students') ?? 'Étudiants' ?></span>
                        </div>
                        <div class="footer-stat">
                            <i class="fas fa-book"></i>
                            <span><strong>100+</strong> <?= __('courses') ?? 'Cours' ?></span>
                        </div>
                        <div class="footer-stat">
                            <i class="fas fa-certificate"></i>
                            <span><strong>500+</strong> <?= __('certificates') ?? 'Certificats' ?></span>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="footer-column">
                    <h4 class="footer-heading">
                        <i class="fas fa-compass"></i>
                        <?= __('navigation') ?? 'Navigation' ?>
                    </h4>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-angle-right"></i> <?= __('dashboard') ?? 'Tableau de Bord' ?></a></li>
                        <li><a href="my_courses.php"><i class="fas fa-angle-right"></i> <?= __('my_courses') ?? 'Mes Cours' ?></a></li>
                        <li><a href="all_courses.php"><i class="fas fa-angle-right"></i> <?= __('discover_courses') ?? 'Découvrir' ?></a></li>
                        <li><a href="course_lessons.php"><i class="fas fa-angle-right"></i> <?= __('my_lessons') ?? 'Mes Leçons' ?></a></li>
                        <li><a href="my_certificates.php"><i class="fas fa-angle-right"></i> <?= __('my_certificates') ?? 'Mes Certificats' ?></a></li>
                    </ul>
                </div>

                <!-- Learning Tools -->
                <div class="footer-column">
                    <h4 class="footer-heading">
                        <i class="fas fa-tools"></i>
                        <?= __('learning_tools') ?? 'Outils d\'Apprentissage' ?>
                    </h4>
                    <ul class="footer-links">
                        <li><a href="assignments.php"><i class="fas fa-angle-right"></i> <?= __('assignments') ?? 'Devoirs' ?></a></li>
                        <li><a href="quizzes.php"><i class="fas fa-angle-right"></i> <?= __('quizzes') ?? 'Quiz' ?></a></li>
                        <li><a href="attendance.php"><i class="fas fa-angle-right"></i> <?= __('attendance') ?? 'Présence' ?></a></li>
                        <li><a href="messages.php"><i class="fas fa-angle-right"></i> <?= __('messages') ?? 'Messages' ?></a></li>
                        <li><a href="orders.php"><i class="fas fa-angle-right"></i> <?= __('my_purchases') ?? 'Mes Achats' ?></a></li>
                    </ul>
                </div>

                <!-- Account & Settings -->
                <div class="footer-column">
                    <h4 class="footer-heading">
                        <i class="fas fa-user-cog"></i>
                        <?= __('account_settings') ?? 'Compte & Paramètres' ?>
                    </h4>
                    <ul class="footer-links">
                        <li><a href="profile.php"><i class="fas fa-angle-right"></i> <?= __('my_profile') ?? 'Mon Profil' ?></a></li>
                        <li><a href="edit_profile.php"><i class="fas fa-angle-right"></i> <?= __('edit_profile') ?? 'Modifier le Profil' ?></a></li>
                        <li><a href="language_settings.php"><i class="fas fa-angle-right"></i> <?= __('preferences') ?? 'Préférences' ?></a></li>
                        <li><a href="orders.php"><i class="fas fa-angle-right"></i> <?= __('order_history') ?? 'Historique Achats' ?></a></li>
                        <li><a href="../auth/logout.php"><i class="fas fa-angle-right"></i> <?= __('logout') ?? 'Déconnexion' ?></a></li>
                    </ul>
                </div>

                <!-- Support & Resources -->
                <div class="footer-column">
                    <h4 class="footer-heading">
                        <i class="fas fa-headset"></i>
                        <?= __('support') ?? 'Support & Aide' ?>
                    </h4>
                    <ul class="footer-links">
                        <li><a href="../public/main_site/contact.php"><i class="fas fa-angle-right"></i> <?= __('contact_us') ?? 'Nous Contacter' ?></a></li>
                        <li><a href="../public/main_site/faq.php"><i class="fas fa-angle-right"></i> <?= __('faq') ?? 'FAQ' ?></a></li>
                        <li><a href="../public/main_site/help.php"><i class="fas fa-angle-right"></i> <?= __('help_center') ?? 'Centre d\'Aide' ?></a></li>
                        <li><a href="../public/main_site/about.php"><i class="fas fa-angle-right"></i> <?= __('about_us') ?? 'À Propos' ?></a></li>
                        <li><a href="../public/main_site/blog.php"><i class="fas fa-angle-right"></i> <?= __('blog') ?? 'Blog' ?></a></li>
                    </ul>

                    <!-- Contact Info -->
                    <div class="footer-contact-info">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:support@taabia.com">support@taabia.com</a>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <a href="tel:+212XXXXXXXXX">+212 XX XX XX XX</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Social & Download -->
            <div class="footer-social-section">
                <div class="footer-social-content">
                    <h4><?= __('follow_us') ?? 'Suivez-nous' ?></h4>
                    <div class="footer-social-large">
                        <a href="#" class="social-link-large" aria-label="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link-large" aria-label="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link-large" aria-label="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-link-large" aria-label="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="social-link-large" aria-label="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="#" class="social-link-large" aria-label="TikTok">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>
                </div>

                <div class="footer-download">
                    <h4><?= __('download_app') ?? 'Télécharger l\'App' ?></h4>
                    <div class="download-buttons">
                        <a href="#" class="download-btn">
                            <i class="fab fa-apple"></i>
                            <div>
                                <small><?= __('download_on') ?? 'Télécharger sur' ?></small>
                                <strong>App Store</strong>
                            </div>
                        </a>
                        <a href="#" class="download-btn">
                            <i class="fab fa-google-play"></i>
                            <div>
                                <small><?= __('get_it_on') ?? 'Disponible sur' ?></small>
                                <strong>Google Play</strong>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="footer-copyright">
                    <p>
                        &copy; <?= date('Y') ?> <strong>TaaBia LMS</strong>.
                        <?= __('all_rights_reserved') ?? 'Tous droits réservés.' ?>
                        <span class="footer-separator">|</span>
                        <?= __('made_with') ?? 'Fait avec' ?> <i class="fas fa-heart" style="color: #f56565;"></i> <?= __('in_niger') ?? 'au Niger' ?>
                    </p>
                </div>
                <div class="footer-links-bottom">
                    <a href="../public/main_site/privacy.php">
                        <i class="fas fa-shield-alt"></i>
                        <?= __('privacy_policy') ?? 'Politique de confidentialité' ?>
                    </a>
                    <span>•</span>
                    <a href="../public/main_site/terms.php">
                        <i class="fas fa-file-contract"></i>
                        <?= __('terms_of_service') ?? 'Conditions d\'utilisation' ?>
                    </a>
                    <span>•</span>
                    <a href="../public/main_site/cookies.php">
                        <i class="fas fa-cookie-bite"></i>
                        <?= __('cookies_policy') ?? 'Politique Cookies' ?>
                    </a>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>