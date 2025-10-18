<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

if (!$student_id || !$course_id) {
    header('Location: students.php');
    exit;
}

// Verify course belongs to instructor
$stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
if ($stmt->rowCount() == 0) {
    header('Location: students.php');
    exit;
}

// Remove student from course
$stmt = $pdo->prepare("DELETE FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);

// Redirect back to students page
header('Location: students.php?removed=1');
exit;
