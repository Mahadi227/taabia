<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_role('instructor');

$id = $_GET['id'] ?? null;
if (!$id) redirect('students.php');

$stmt = $pdo->prepare("SELECT sc.*, u.full_name, c.title 
                       FROM student_courses sc 
                       JOIN users u ON sc.student_id = u.id 
                       JOIN courses c ON sc.course_id = c.id 
                       WHERE sc.id = ?");
$stmt->execute([$id]);
$enrollment = $stmt->fetch();

if (!$enrollment) {
    echo "⛔ Inscription introuvable."; exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progress = max(0, min(100, (int)$_POST['progress']));
    $pdo->prepare("UPDATE student_courses SET progress = ? WHERE id = ?")
        ->execute([$progress, $id]);
    redirect("students.php?course_id=" . $enrollment['course_id']);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mettre à jour la progression</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 2rem; }
        form { background: #fff; padding: 2rem; max-width: 400px; margin: auto; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        label, input { display: block; width: 100%; margin-bottom: 1rem; }
        input[type=number] { padding: 0.7rem; font-size: 1rem; }
        button { background: #009688; color: white; padding: 0.8rem; border: none; cursor: pointer; border-radius: 6px; }
    </style>
</head>
<body>
<h2>Mettre à jour la progression de <?= sanitize($enrollment['full_name']) ?> dans <?= sanitize($enrollment['title']) ?></h2>
<form method="POST">
    <label>Progression (%)</label>
    <input type="number" name="progress" min="0" max="100" value="<?= $enrollment['progress'] ?>">
    <button type="submit">Enregistrer</button>
</form>
</body>
</html>
