<?php

/**
 * API endpoint for tracking lesson analytics
 * Handles student interactions with lessons
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized - Student access required']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit();
}

$lesson_id = $input['lesson_id'] ?? null;
$student_id = $input['student_id'] ?? null;
$action = $input['action'] ?? null;
$data = $input['data'] ?? [];

if (!$lesson_id || !$student_id || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit();
}

// Verify student ID matches session
if ($student_id != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Student ID mismatch']);
    exit();
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=taabia_skills;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify lesson exists and student is enrolled
    $stmt = $pdo->prepare("
        SELECT cc.id, cc.title, c.id as course_id
        FROM course_contents cc 
        JOIN courses c ON cc.course_id = c.id
        JOIN student_courses sc ON c.id = sc.course_id
        WHERE cc.id = ? AND sc.student_id = ?
    ");
    $stmt->execute([$lesson_id, $student_id]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found or student not enrolled']);
        exit();
    }

    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    $result = ['success' => true];

    switch ($action) {
        case 'view':
            // Track lesson view
            $stmt = $pdo->prepare("
                INSERT INTO lesson_views (lesson_id, student_id, viewed_at, session_duration, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $lesson_id,
                $student_id,
                $timestamp,
                $data['session_duration'] ?? 0,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            $result['message'] = 'View tracked';
            break;

        case 'completion':
            // Track lesson completion
            $stmt = $pdo->prepare("
                INSERT INTO lesson_completions (lesson_id, student_id, completed_at, completion_time)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    completed_at = VALUES(completed_at),
                    completion_time = VALUES(completion_time),
                    attempts = attempts + 1
            ");
            $stmt->execute([
                $lesson_id,
                $student_id,
                $timestamp,
                $data['completion_time'] ?? 0
            ]);
            $result['message'] = 'Completion tracked';
            break;

        case 'rating':
            // Track lesson rating
            $rating = $data['rating'] ?? null;
            $review = $data['review'] ?? null;

            if (!$rating || $rating < 1 || $rating > 5) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid rating']);
                exit();
            }

            $stmt = $pdo->prepare("
                INSERT INTO lesson_ratings (lesson_id, student_id, rating, review, rated_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    rating = VALUES(rating),
                    review = VALUES(review),
                    rated_at = VALUES(rated_at)
            ");
            $stmt->execute([$lesson_id, $student_id, $rating, $review, $timestamp]);
            $result['message'] = 'Rating tracked';
            break;

        case 'progress':
            // Update lesson progress
            $progress_percent = $data['progress_percent'] ?? 0;
            $time_spent = $data['time_spent'] ?? 0;
            $is_completed = $progress_percent >= 100;

            $stmt = $pdo->prepare("
                INSERT INTO lesson_progress (lesson_id, student_id, progress_percent, time_spent, last_accessed, is_completed, completion_date)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    progress_percent = VALUES(progress_percent),
                    time_spent = time_spent + VALUES(time_spent),
                    last_accessed = VALUES(last_accessed),
                    is_completed = VALUES(is_completed),
                    completion_date = CASE WHEN VALUES(is_completed) = 1 AND completion_date IS NULL THEN VALUES(completion_date) ELSE completion_date END
            ");
            $stmt->execute([
                $lesson_id,
                $student_id,
                $progress_percent,
                $time_spent,
                $timestamp,
                $is_completed,
                $is_completed ? $timestamp : null
            ]);
            $result['message'] = 'Progress updated';
            break;

        case 'interaction':
            // Track user interactions
            $interaction_type = $data['interaction_type'] ?? 'unknown';
            $interaction_data = json_encode($data['interaction_data'] ?? []);

            $stmt = $pdo->prepare("
                INSERT INTO lesson_interactions (lesson_id, student_id, interaction_type, interaction_data, created_at)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$lesson_id, $student_id, $interaction_type, $interaction_data, $timestamp]);
            $result['message'] = 'Interaction tracked';
            break;

        case 'video_progress':
            // Track video progress
            $progress_percent = $data['progress_percent'] ?? 0;

            $stmt = $pdo->prepare("
                INSERT INTO lesson_progress (lesson_id, student_id, progress_percent, last_accessed)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    progress_percent = GREATEST(progress_percent, VALUES(progress_percent)),
                    last_accessed = VALUES(last_accessed)
            ");
            $stmt->execute([$lesson_id, $student_id, $progress_percent, $timestamp]);
            $result['message'] = 'Video progress tracked';
            break;

        case 'session_end':
            // Track session end
            $session_duration = $data['session_duration'] ?? 0;

            $stmt = $pdo->prepare("
                UPDATE lesson_progress 
                SET time_spent = time_spent + ?, last_accessed = ?
                WHERE lesson_id = ? AND student_id = ?
            ");
            $stmt->execute([$session_duration, $timestamp, $lesson_id, $student_id]);
            $result['message'] = 'Session end tracked';
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
            exit();
    }

    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
