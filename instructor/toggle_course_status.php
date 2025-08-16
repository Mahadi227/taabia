<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id'])) {
    $course_id = $_POST['course_id'];

    // Vérifie que le cours appartient bien à l'instructeur connecté
    $stmt = $pdo->prepare("SELECT status FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if ($course) {
        $new_status = $course['status'] === 'published' ? 'draft' : 'published';
        $update = $pdo->prepare("UPDATE courses SET status = ? WHERE id = ?");
        $update->execute([$new_status, $course_id]);
    }
}
header('Location: student_courses.php');
exit;