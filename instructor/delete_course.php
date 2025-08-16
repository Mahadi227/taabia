<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('instructor');

if (!isset($_GET['id'])) redirect('my_courses.php');
$id = (int) $_GET['id'];

// Vérifie que le cours appartient à l’instructeur
$stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);
$course = $stmt->fetch();

if ($course) {
    // Supprimer les contenus liés si nécessaire (à adapter selon ta structure)
    $pdo->prepare("DELETE FROM courses WHERE id = ?")->execute([$id]);
}

redirect('my_courses.php');
