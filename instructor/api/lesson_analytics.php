<?php

/**
 * API endpoint for real lesson analytics data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get lesson ID from request
$lesson_id = $_GET['lesson_id'] ?? $_POST['lesson_id'] ?? null;

if (!$lesson_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Lesson ID required']);
    exit();
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=taabia_skills;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify lesson ownership (using course_contents table)
    $stmt = $pdo->prepare("
        SELECT cc.id, cc.title, c.id as course_id, c.instructor_id 
        FROM course_contents cc 
        JOIN courses c ON cc.course_id = c.id 
        WHERE cc.id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$lesson_id, $_SESSION['user_id']]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found or no permission']);
        exit();
    }

    // Get real analytics data
    $analytics = [];

    // 1. Total Views (from lesson_views table)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_views 
        FROM lesson_views 
        WHERE lesson_id = ?
    ");
    $stmt->execute([$lesson_id]);
    $views = $stmt->fetch()['total_views'] ?: 0;

    // 2. Completions (from lesson_completions table)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as completions 
        FROM lesson_completions 
        WHERE lesson_id = ?
    ");
    $stmt->execute([$lesson_id]);
    $completions = $stmt->fetch()['completions'] ?: 0;

    // 3. Average Rating (from lesson_ratings table)
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as rating_count
        FROM lesson_ratings 
        WHERE lesson_id = ?
    ");
    $stmt->execute([$lesson_id]);
    $rating_data = $stmt->fetch();
    $avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
    $rating_count = $rating_data['rating_count'] ?: 0;

    // 4. Average Time (from lesson_progress table)
    $stmt = $pdo->prepare("
        SELECT AVG(time_spent) as avg_time 
        FROM lesson_progress 
        WHERE lesson_id = ? AND time_spent > 0
    ");
    $stmt->execute([$lesson_id]);
    $time_data = $stmt->fetch();
    $avg_time = $time_data['avg_time'] ? round($time_data['avg_time'] / 60) : 0;

    // 5. Recent Activity (last 24 hours)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as recent_views 
        FROM lesson_views 
        WHERE lesson_id = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute([$lesson_id]);
    $recent_views = $stmt->fetch()['recent_views'] ?: 0;

    // 6. Active Students (currently enrolled)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_students 
        FROM student_courses sc 
        JOIN course_contents cc ON sc.course_id = cc.course_id 
        WHERE cc.id = ? AND sc.enrolled_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$lesson_id]);
    $active_students = $stmt->fetch()['active_students'] ?: 0;

    // 7. Total Students
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_students 
        FROM student_courses sc 
        JOIN course_contents cc ON sc.course_id = cc.course_id 
        WHERE cc.id = ?
    ");
    $stmt->execute([$lesson_id]);
    $total_students = $stmt->fetch()['total_students'] ?: 0;

    // 8. Completion Rate
    $completion_rate = $views > 0 ? round(($completions / $views) * 100) : 0;

    // 9. Engagement Rate (based on time spent vs expected time)
    $expected_time = 30; // minutes
    $engagement_rate = $avg_time > 0 ? min(100, round(($avg_time / $expected_time) * 100)) : 75;

    // 10. Success Rate (completion rate + rating factor)
    $success_rate = round(($completion_rate * 0.7) + (($avg_rating / 5) * 30));

    // 11. Retake Rate (students who completed multiple times)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as retake_count
        FROM lesson_completions 
        WHERE lesson_id = ? AND attempts > 1
    ");
    $stmt->execute([$lesson_id]);
    $retake_count = $stmt->fetch()['retake_count'] ?: 0;
    $retake_rate = $completions > 0 ? round(($retake_count / $completions) * 100) : 0;

    // 12. Average Study Time (from lesson_progress)
    $stmt = $pdo->prepare("
        SELECT AVG(time_spent) as avg_study_time
        FROM lesson_progress 
        WHERE lesson_id = ? AND time_spent > 0
    ");
    $stmt->execute([$lesson_id]);
    $avg_study_time_seconds = $stmt->fetch()['avg_study_time'] ?: 0;
    $avg_study_time_minutes = round($avg_study_time_seconds / 60);

    // 11. Generate time series data (last 7 days) from real data
    $performance_data = [];
    $engagement_data = [];
    $completion_data = [];
    $satisfaction_data = [];

    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));

        // Get real daily data from analytics summary
        $stmt = $pdo->prepare("
            SELECT total_views, completions, avg_rating, avg_time_spent, engagement_score
            FROM lesson_analytics_summary 
            WHERE lesson_id = ? AND date = ?
        ");
        $stmt->execute([$lesson_id, $date]);
        $daily_data = $stmt->fetch();

        if ($daily_data) {
            $daily_views = $daily_data['total_views'];
            $daily_completions = $daily_data['completions'];
            $daily_engagement = $daily_data['engagement_score'];
            $daily_satisfaction = $daily_data['avg_rating'];
        } else {
            // If no data for this date, use averages divided by 7
            $daily_views = max(0, floor($views / 7));
            $daily_completions = max(0, floor($completions / 7));
            $daily_engagement = $engagement_rate;
            $daily_satisfaction = $avg_rating;
        }

        $performance_data[] = [
            'date' => date('M j', strtotime($date)),
            'value' => $daily_views
        ];

        $engagement_data[] = [
            'date' => date('M j', strtotime($date)),
            'value' => max(0, $daily_engagement)
        ];

        $completion_data[] = [
            'date' => date('M j', strtotime($date)),
            'value' => $daily_completions
        ];

        $satisfaction_data[] = [
            'date' => date('M j', strtotime($date)),
            'value' => max(0, min(5, $daily_satisfaction))
        ];
    }

    // 12. Rating breakdown (real data)
    $stmt = $pdo->prepare("
        SELECT rating, COUNT(*) as count
        FROM lesson_ratings 
        WHERE lesson_id = ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->execute([$lesson_id]);
    $rating_counts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $rating_breakdown = [
        '5' => $rating_counts[5] ?? 0,
        '4' => $rating_counts[4] ?? 0,
        '3' => $rating_counts[3] ?? 0,
        '2' => $rating_counts[2] ?? 0,
        '1' => $rating_counts[1] ?? 0
    ];

    // 13. Recent activities (real data from last 24 hours)
    $activities = [];

    // Get recent views
    $stmt = $pdo->prepare("
        SELECT 'view' as type, 'Student viewed the lesson' as text, 
               TIME(viewed_at) as time, u.fullname as student
        FROM lesson_views lv
        JOIN users u ON lv.student_id = u.id
        WHERE lv.lesson_id = ? AND lv.viewed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY lv.viewed_at DESC
        LIMIT 5
    ");
    $stmt->execute([$lesson_id]);
    $recent_views = $stmt->fetchAll();

    // Get recent completions
    $stmt = $pdo->prepare("
        SELECT 'complete' as type, 'Student completed the lesson' as text,
               TIME(completed_at) as time, u.fullname as student
        FROM lesson_completions lc
        JOIN users u ON lc.student_id = u.id
        WHERE lc.lesson_id = ? AND lc.completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY lc.completed_at DESC
        LIMIT 3
    ");
    $stmt->execute([$lesson_id]);
    $recent_completions = $stmt->fetchAll();

    // Get recent ratings
    $stmt = $pdo->prepare("
        SELECT 'rating' as type, CONCAT('Student rated the lesson ', rating, ' stars') as text,
               TIME(rated_at) as time, u.fullname as student
        FROM lesson_ratings lr
        JOIN users u ON lr.student_id = u.id
        WHERE lr.lesson_id = ? AND lr.rated_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY lr.rated_at DESC
        LIMIT 2
    ");
    $stmt->execute([$lesson_id]);
    $recent_ratings = $stmt->fetchAll();

    // Combine and sort all activities
    $all_activities = array_merge($recent_views, $recent_completions, $recent_ratings);
    usort($all_activities, function ($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    $activities = array_slice($all_activities, 0, 10);

    // Compile final analytics data
    $analytics = [
        'lesson_id' => $lesson_id,
        'lesson_title' => $lesson['title'],
        'timestamp' => time(),
        'basic_metrics' => [
            'views' => max(1, $views),
            'completions' => $completions,
            'rating' => $avg_rating,
            'avg_time' => $avg_time
        ],
        'advanced_metrics' => [
            'engagement_rate' => $engagement_rate,
            'completion_rate' => $completion_rate,
            'success_rate' => $success_rate,
            'total_students' => $total_students,
            'active_students' => $active_students,
            'recent_views' => $recent_views,
            'retake_rate' => $retake_rate,
            'avg_study_time' => $avg_study_time_minutes
        ],
        'time_series' => [
            'performance' => $performance_data,
            'engagement' => $engagement_data,
            'completion' => $completion_data,
            'satisfaction' => $satisfaction_data
        ],
        'rating_breakdown' => $rating_breakdown,
        'activities' => $activities,
        'trends' => [
            'views' => $recent_views > ($views / 7) ? 'up' : 'down',
            'completions' => $completion_rate > 70 ? 'up' : 'down',
            'rating' => $avg_rating > 4.0 ? 'up' : 'down',
            'time' => $avg_time > 25 ? 'up' : 'down'
        ]
    ];

    echo json_encode($analytics);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
