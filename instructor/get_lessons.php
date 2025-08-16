<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

header('Content-Type: application/json');

$instructor_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? 0;

try {
    // Verify course belongs to instructor
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    
    if ($stmt->rowCount() == 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized access to course']);
        exit;
    }
    
    // Get lessons for the course
    $stmt = $pdo->prepare("
        SELECT id, title, lesson_order 
        FROM lessons 
        WHERE course_id = ? AND status = 'active'
        ORDER BY lesson_order ASC, title ASC
    ");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($lessons);
    
} catch (PDOException $e) {
    error_log("Database error in get_lessons: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?> 