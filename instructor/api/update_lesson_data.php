<?php

/**
 * API endpoint for updating lesson data in real-time
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'instructor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);
$lesson_id = $input['lesson_id'] ?? null;
$action = $input['action'] ?? null;

if (!$lesson_id || !$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lesson_id or action']);
    exit();
}

try {
    // Database connection
    $pdo = new PDO("mysql:host=localhost;dbname=taabia_skills;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Verify lesson ownership
    $stmt = $pdo->prepare("
        SELECT l.id, l.title, c.id as course_id, c.instructor_id 
        FROM lessons l 
        JOIN courses c ON l.course_id = c.id 
        WHERE l.id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$lesson_id, $_SESSION['user_id']]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        http_response_code(404);
        echo json_encode(['error' => 'Lesson not found or no permission']);
        exit();
    }

    $response = ['success' => true, 'action' => $action, 'lesson_id' => $lesson_id];

    switch ($action) {
        case 'view':
            // Record a lesson view
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_views (lesson_id, viewed_at, ip_address) 
                    VALUES (?, NOW(), ?)
                    ON DUPLICATE KEY UPDATE view_count = view_count + 1, viewed_at = NOW()
                ");
                $stmt->execute([$lesson_id, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                $response['message'] = 'View recorded';
            } catch (PDOException $e) {
                // Table might not exist, create it
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS lesson_views (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lesson_id INT,
                        view_count INT DEFAULT 1,
                        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        ip_address VARCHAR(45),
                        INDEX(lesson_id),
                        UNIQUE KEY unique_lesson_ip (lesson_id, ip_address)
                    )
                ");
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_views (lesson_id, viewed_at, ip_address) 
                    VALUES (?, NOW(), ?)
                ");
                $stmt->execute([$lesson_id, $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
                $response['message'] = 'View recorded (table created)';
            }
            break;

        case 'complete':
            // Record a lesson completion
            $student_id = $input['student_id'] ?? $_SESSION['user_id'];
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_completions (lesson_id, student_id, completed_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE completed_at = NOW()
                ");
                $stmt->execute([$lesson_id, $student_id]);
                $response['message'] = 'Completion recorded';
            } catch (PDOException $e) {
                // Create table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS lesson_completions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lesson_id INT,
                        student_id INT,
                        completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX(lesson_id),
                        UNIQUE KEY unique_lesson_student (lesson_id, student_id)
                    )
                ");
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_completions (lesson_id, student_id, completed_at) 
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$lesson_id, $student_id]);
                $response['message'] = 'Completion recorded (table created)';
            }
            break;

        case 'rate':
            // Record a lesson rating
            $rating = $input['rating'] ?? null;
            $student_id = $input['student_id'] ?? $_SESSION['user_id'];

            if (!$rating || $rating < 1 || $rating > 5) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid rating']);
                exit();
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_ratings (lesson_id, student_id, rating, rated_at) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE rating = ?, rated_at = NOW()
                ");
                $stmt->execute([$lesson_id, $student_id, $rating, $rating]);
                $response['message'] = 'Rating recorded';
            } catch (PDOException $e) {
                // Create table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS lesson_ratings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lesson_id INT,
                        student_id INT,
                        rating TINYINT CHECK (rating >= 1 AND rating <= 5),
                        rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX(lesson_id),
                        UNIQUE KEY unique_lesson_student (lesson_id, student_id)
                    )
                ");
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_ratings (lesson_id, student_id, rating, rated_at) 
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$lesson_id, $student_id, $rating]);
                $response['message'] = 'Rating recorded (table created)';
            }
            break;

        case 'progress':
            // Record lesson progress
            $student_id = $input['student_id'] ?? $_SESSION['user_id'];
            $time_spent = $input['time_spent'] ?? 0;
            $progress_percent = $input['progress_percent'] ?? 0;

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_progress (lesson_id, student_id, time_spent, progress_percent, updated_at) 
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    time_spent = time_spent + VALUES(time_spent),
                    progress_percent = GREATEST(progress_percent, VALUES(progress_percent)),
                    updated_at = NOW()
                ");
                $stmt->execute([$lesson_id, $student_id, $time_spent, $progress_percent]);
                $response['message'] = 'Progress recorded';
            } catch (PDOException $e) {
                // Create table if it doesn't exist
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS lesson_progress (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        lesson_id INT,
                        student_id INT,
                        time_spent INT DEFAULT 0,
                        progress_percent DECIMAL(5,2) DEFAULT 0,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX(lesson_id),
                        UNIQUE KEY unique_lesson_student (lesson_id, student_id)
                    )
                ");
                $stmt = $pdo->prepare("
                    INSERT INTO lesson_progress (lesson_id, student_id, time_spent, progress_percent, updated_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$lesson_id, $student_id, $time_spent, $progress_percent]);
                $response['message'] = 'Progress recorded (table created)';
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit();
    }

    echo json_encode($response);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
