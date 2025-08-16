<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$id = $_GET['id'] ?? null;
if (!$id) redirect('students.php');

$stmt = $pdo->prepare("SELECT course_id FROM student_courses WHERE id = ?");
$stmt->execute([$id]);
$row = $stmt->fetch();

if ($row) {
    $pdo->prepare("DELETE FROM student_courses WHERE id = ?")->execute([$id]);
    redirect("students.php?course_id=" . $row['course_id']);
} else {
    echo "❌ Étudiant non trouvé.";
}
