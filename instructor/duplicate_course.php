<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

// Vérifie que l'ID est présent
if (!isset($_GET['id'])) {
    redirect('my_courses.php');
}

$original_course_id = (int) $_GET['id'];
$instructor_id = $_SESSION['user_id'];

// 1. Vérifie que le cours appartient à l’instructeur
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$original_course_id, $instructor_id]);
$original_course = $stmt->fetch();

if (!$original_course) {
    echo "⛔ Cours introuvable ou accès non autorisé.";
    exit;
}

// 2. Duplique le cours
$new_title = $original_course['title'] . " (copie)";
$new_description = $original_course['description'];
$new_status = 'draft'; // toujours brouillon
$created_at = date('Y-m-d H:i:s');

$insert = $pdo->prepare("INSERT INTO courses (title, description, status, instructor_id, created_at) VALUES (?, ?, ?, ?, ?)");
$insert->execute([$new_title, $new_description, $new_status, $instructor_id, $created_at]);
$new_course_id = $pdo->lastInsertId();

// 3. Copier les contenus (si la table `course_contents` existe)
$stmt2 = $pdo->prepare("SELECT * FROM course_contents WHERE course_id = ?");
$stmt2->execute([$original_course_id]);
$contents = $stmt2->fetchAll();

if ($contents) {
    $copyContent = $pdo->prepare("INSERT INTO course_contents (course_id, title, content_type, content_data, created_at) VALUES (?, ?, ?, ?, ?)");
    foreach ($contents as $c) {
        $copyContent->execute([
            $new_course_id,
            $c['title'],
            $c['content_type'],
            $c['content_data'],
            date('Y-m-d H:i:s')
        ]);
    }
}

// 4. Redirection vers le cours dupliqué
redirect("edit_course.php?id=$new_course_id");
