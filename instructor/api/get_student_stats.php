<?php

/**
 * API Endpoint for Dynamic Student Statistics
 * 
 * This endpoint provides real-time student statistics for the instructor dashboard
 */

// Start output buffering
ob_start();

// Handle CORS and headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/function.php';

// Security check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'instructor') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$instructor_id = $_SESSION['user_id'];

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['instructor_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit();
}

// Verify instructor ID matches session
if ($input['instructor_id'] != $instructor_id) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit();
}

/**
 * Fetch comprehensive statistics with multiple fallback queries
 */
function fetchRealTimeStatistics($pdo, $instructor_id)
{
    try {
        // First, try to get basic course and student counts
        $basic_stats = [
            'total_courses' => 0,
            'total_students' => 0,
            'avg_progress' => 0,
            'completed_courses' => 0,
            'active_students' => 0,
            'inactive_students' => 0,
            'recent_activity' => 0
        ];

        // Get total courses
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
            $stmt->execute([$instructor_id]);
            $basic_stats['total_courses'] = $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error fetching courses: " . $e->getMessage());
        }

        // Try multiple queries to get student count
        $student_queries = [
            // Try student_courses table first
            "SELECT COUNT(DISTINCT s.id) as total_students FROM student_courses sc JOIN students s ON sc.student_id = s.id JOIN courses c ON sc.course_id = c.id WHERE c.instructor_id = ?",

            // Try course_enrollments table
            "SELECT COUNT(DISTINCT s.id) as total_students FROM course_enrollments ce JOIN students s ON ce.student_id = s.id JOIN courses c ON ce.course_id = c.id WHERE c.instructor_id = ?",

            // Try enrollments table
            "SELECT COUNT(DISTINCT s.id) as total_students FROM enrollments e JOIN students s ON e.student_id = s.id JOIN courses c ON e.course_id = c.id WHERE c.instructor_id = ?",

            // Try user_courses table
            "SELECT COUNT(DISTINCT u.id) as total_students FROM user_courses uc JOIN users u ON uc.user_id = u.id JOIN courses c ON uc.course_id = c.id WHERE c.instructor_id = ? AND u.role = 'student'",

            // Try transactions table as fallback
            "SELECT COUNT(DISTINCT t.buyer_id) as total_students FROM transactions t JOIN courses c ON t.course_id = c.id WHERE c.instructor_id = ? AND t.status = 'completed'"
        ];

        $total_students = 0;
        foreach ($student_queries as $query) {
            try {
                $stmt = $pdo->prepare($query);
                $stmt->execute([$instructor_id]);
                $count = $stmt->fetchColumn();
                if ($count > 0) {
                    $total_students = $count;
                    break;
                }
            } catch (PDOException $e) {
                // Continue to next query if this one fails
                continue;
            }
        }

        $basic_stats['total_students'] = $total_students;

        // Try to get progress statistics if we have students
        if ($total_students > 0) {
            $progress_queries = [
                // Try student_courses with progress
                "SELECT AVG(COALESCE(sc.progress, 0)) as avg_progress, COUNT(CASE WHEN COALESCE(sc.progress, 0) >= 100 THEN 1 END) as completed_courses, COUNT(CASE WHEN COALESCE(sc.progress, 0) > 0 AND COALESCE(sc.progress, 0) < 100 THEN 1 END) as active_students, COUNT(CASE WHEN COALESCE(sc.progress, 0) = 0 THEN 1 END) as inactive_students FROM student_courses sc JOIN courses c ON sc.course_id = c.id WHERE c.instructor_id = ?",

                // Try course_enrollments with progress
                "SELECT AVG(COALESCE(ce.progress, 0)) as avg_progress, COUNT(CASE WHEN COALESCE(ce.progress, 0) >= 100 THEN 1 END) as completed_courses, COUNT(CASE WHEN COALESCE(ce.progress, 0) > 0 AND COALESCE(ce.progress, 0) < 100 THEN 1 END) as active_students, COUNT(CASE WHEN COALESCE(ce.progress, 0) = 0 THEN 1 END) as inactive_students FROM course_enrollments ce JOIN courses c ON ce.course_id = c.id WHERE c.instructor_id = ?",

                // Try enrollments with progress
                "SELECT AVG(COALESCE(e.progress, 0)) as avg_progress, COUNT(CASE WHEN COALESCE(e.progress, 0) >= 100 THEN 1 END) as completed_courses, COUNT(CASE WHEN COALESCE(e.progress, 0) > 0 AND COALESCE(e.progress, 0) < 100 THEN 1 END) as active_students, COUNT(CASE WHEN COALESCE(e.progress, 0) = 0 THEN 1 END) as inactive_students FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE c.instructor_id = ?"
            ];

            foreach ($progress_queries as $query) {
                try {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$instructor_id]);
                    $progress_data = $stmt->fetch();
                    if ($progress_data && $progress_data['avg_progress'] !== null) {
                        $basic_stats['avg_progress'] = round($progress_data['avg_progress'], 1);
                        $basic_stats['completed_courses'] = $progress_data['completed_courses'];
                        $basic_stats['active_students'] = $progress_data['active_students'];
                        $basic_stats['inactive_students'] = $progress_data['inactive_students'];
                        break;
                    }
                } catch (PDOException $e) {
                    // Continue to next query if this one fails
                    continue;
                }
            }
        }

        // Get recent activity (last 7 days)
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as recent_activity 
                FROM student_courses sc 
                JOIN courses c ON sc.course_id = c.id 
                WHERE c.instructor_id = ? 
                AND sc.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute([$instructor_id]);
            $basic_stats['recent_activity'] = $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            // Fallback: try other tables
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as recent_activity 
                    FROM course_enrollments ce 
                    JOIN courses c ON ce.course_id = c.id 
                    WHERE c.instructor_id = ? 
                    AND ce.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $stmt->execute([$instructor_id]);
                $basic_stats['recent_activity'] = $stmt->fetchColumn() ?: 0;
            } catch (PDOException $e2) {
                $basic_stats['recent_activity'] = 0;
            }
        }

        return $basic_stats;
    } catch (PDOException $e) {
        error_log("Error fetching statistics: " . $e->getMessage());
        return [
            'total_students' => 0,
            'total_courses' => 0,
            'avg_progress' => 0,
            'completed_courses' => 0,
            'active_students' => 0,
            'inactive_students' => 0,
            'recent_activity' => 0
        ];
    }
}

// Fetch real-time statistics
$stats = fetchRealTimeStatistics($pdo, $instructor_id);

// Add calculated fields
$stats['completion_rate'] = $stats['total_students'] > 0 ? round(($stats['completed_courses'] / $stats['total_students']) * 100, 1) : 0;
$stats['active_rate'] = $stats['total_students'] > 0 ? round(($stats['active_students'] / $stats['total_students']) * 100, 1) : 0;

// Add timestamp
$stats['last_updated'] = date('Y-m-d H:i:s');

// Return JSON response
echo json_encode($stats);
