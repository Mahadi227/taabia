<?php

/**
 * API endpoint for fetching course analytics data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$course_id = $_GET['course_id'] ?? null;
$instructor_id = $_SESSION['user_id'];

if (!$course_id || !is_numeric($course_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID']);
    exit();
}

try {
    require_once '../../includes/db.php';

    // Verify course belongs to instructor
    $course_stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $course_stmt->execute([$course_id, $instructor_id]);
    if (!$course_stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Course not found or access denied']);
        exit();
    }

    // Get course overview analytics
    $overview_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT lc.student_id) as total_students,
            COALESCE(SUM(lv.view_count), 0) as total_views,
            COUNT(lc.id) as total_completions,
            COALESCE(AVG(lr.rating), 0) as average_rating,
            COALESCE(AVG(lp.time_spent), 0) as average_time_spent,
            COALESCE(COUNT(lc.id) * 100.0 / NULLIF(COUNT(DISTINCT lc.student_id), 0), 0) as completion_rate,
            COALESCE(COUNT(lc.id) * 100.0 / NULLIF(COUNT(DISTINCT lc.student_id), 0), 0) as engagement_rate
        FROM lessons l
        LEFT JOIN lesson_views lv ON l.id = lv.lesson_id
        LEFT JOIN lesson_completions lc ON l.id = lc.lesson_id
        LEFT JOIN lesson_ratings lr ON l.id = lr.lesson_id
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id
        WHERE l.course_id = ?
    ");
    $overview_stmt->execute([$course_id]);
    $overview = $overview_stmt->fetch();

    // Get individual lesson analytics
    $lessons_stmt = $pdo->prepare("
        SELECT 
            l.id,
            l.title,
            l.content_type,
            l.order_index,
            COALESCE(SUM(lv.view_count), 0) as total_views,
            COUNT(lc.id) as completions,
            COALESCE(AVG(lr.rating), 0) as avg_rating,
            COALESCE(AVG(lp.time_spent), 0) as avg_time_spent,
            COALESCE(COUNT(lc.id) * 100.0 / NULLIF(COUNT(DISTINCT lc.student_id), 0), 0) as completion_rate
        FROM lessons l
        LEFT JOIN lesson_views lv ON l.id = lv.lesson_id
        LEFT JOIN lesson_completions lc ON l.id = lc.lesson_id
        LEFT JOIN lesson_ratings lr ON l.id = lr.lesson_id
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id
        WHERE l.course_id = ?
        GROUP BY l.id, l.title, l.content_type, l.order_index
        ORDER BY l.order_index ASC, l.id ASC
    ");
    $lessons_stmt->execute([$course_id]);
    $lessons_analytics = $lessons_stmt->fetchAll();

    // Get monthly trends (last 6 months) - simplified for existing structure
    $trends_stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(lv.viewed_at, '%Y-%m') as month,
            SUM(lv.view_count) as views
        FROM lessons l
        LEFT JOIN lesson_views lv ON l.id = lv.lesson_id
        WHERE l.course_id = ? 
        AND lv.viewed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(lv.viewed_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $trends_stmt->execute([$course_id]);
    $views_trends = $trends_stmt->fetchAll();

    // Get completion trends
    $completion_trends_stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(lc.completed_at, '%Y-%m') as month,
            COUNT(lc.id) as completions
        FROM lessons l
        LEFT JOIN lesson_completions lc ON l.id = lc.lesson_id
        WHERE l.course_id = ? 
        AND lc.completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(lc.completed_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $completion_trends_stmt->execute([$course_id]);
    $completion_trends = $completion_trends_stmt->fetchAll();

    // Get engagement breakdown - simplified for existing structure
    $engagement_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT lc.student_id) as completed_students,
            COUNT(DISTINCT lp.student_id) - COUNT(DISTINCT lc.student_id) as in_progress_students,
            0 as not_started_students
        FROM lessons l
        LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id
        LEFT JOIN lesson_completions lc ON l.id = lc.lesson_id
        WHERE l.course_id = ?
    ");
    $engagement_stmt->execute([$course_id]);
    $engagement = $engagement_stmt->fetch();

    // Calculate success rate (students who completed at least 80% of lessons)
    $success_rate_stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT lc.student_id) as total_enrolled,
            COUNT(DISTINCT CASE 
                WHEN completed_lessons.total_completed >= (total_lessons.total * 0.8) 
                THEN lc.student_id 
            END) as successful_students
        FROM lesson_completions lc
        JOIN lessons l ON lc.lesson_id = l.id
        CROSS JOIN (
            SELECT COUNT(*) as total FROM lessons WHERE course_id = ?
        ) total_lessons
        LEFT JOIN (
            SELECT 
                lc2.student_id,
                COUNT(DISTINCT lc2.lesson_id) as total_completed
            FROM lesson_completions lc2
            JOIN lessons l2 ON lc2.lesson_id = l2.id
            WHERE l2.course_id = ?
            GROUP BY lc2.student_id
        ) completed_lessons ON lc.student_id = completed_lessons.student_id
        WHERE l.course_id = ?
    ");
    $success_rate_stmt->execute([$course_id, $course_id, $course_id]);
    $success_data = $success_rate_stmt->fetch();
    $success_rate = $success_data['total_enrolled'] > 0 ?
        ($success_data['successful_students'] * 100.0 / $success_data['total_enrolled']) : 0;

    // Prepare response data
    $response = [
        'status' => 'success',
        'data' => [
            'overview' => [
                'total_views' => (int)($overview['total_views'] ?? 0),
                'total_completions' => (int)($overview['total_completions'] ?? 0),
                'average_rating' => round((float)($overview['average_rating'] ?? 0), 1),
                'average_time' => round((float)($overview['average_time_spent'] ?? 0) / 60, 0), // Convert to minutes
                'completion_rate' => round((float)($overview['completion_rate'] ?? 0), 1),
                'engagement_rate' => round((float)($overview['engagement_rate'] ?? 0), 1),
                'success_rate' => round($success_rate, 1),
                'total_students' => (int)($overview['total_students'] ?? 0)
            ],
            'lessons' => array_map(function ($lesson) {
                return [
                    'id' => (int)$lesson['id'],
                    'title' => $lesson['title'],
                    'content_type' => $lesson['content_type'],
                    'duration' => 30, // Default duration since it's not in the table
                    'views' => (int)$lesson['total_views'],
                    'completions' => (int)$lesson['completions'],
                    'completion_rate' => round((float)$lesson['completion_rate'], 1),
                    'avg_rating' => round((float)$lesson['avg_rating'], 1),
                    'avg_time' => round((float)$lesson['avg_time_spent'] / 60, 0) // Convert to minutes
                ];
            }, $lessons_analytics),
            'trends' => [
                'views' => $views_trends,
                'completions' => $completion_trends
            ],
            'engagement' => [
                'completed' => (int)($engagement['completed_students'] ?? 0),
                'in_progress' => (int)($engagement['in_progress_students'] ?? 0),
                'not_started' => (int)($engagement['not_started_students'] ?? 0)
            ]
        ]
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
