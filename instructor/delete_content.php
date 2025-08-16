<?php
// ====== delete_content.php ======
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

if (!isset($_GET['id'])) redirect('my_courses.php');
$id = (int) $_GET['id'];

// Récupère le contenu pour connaître le course_id
$stmt = $pdo->prepare("SELECT course_id FROM course_contents WHERE id = ?");
$stmt->execute([$id]);
$content = $stmt->fetch();

if ($content) {
    $pdo->prepare("DELETE FROM course_contents WHERE id = ?")->execute([$id]);
    redirect("view_course.php?id=" . $content['course_id']);
}  else {
    echo "<p style='color: red; font-weight: bold; text-align: center;'>⛔ Contenu introuvable.</p>";
}