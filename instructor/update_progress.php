<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('instructor');

// Vérifie que les paramètres existent
if (!isset($_GET['student_id']) || !isset($_GET['course_id'])) {
    redirect('students.php');
}

$student_id = (int) $_GET['student_id'];
$course_id = (int) $_GET['course_id'];

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progress = (int) $_POST['progress'];

    $stmt = $pdo->prepare("UPDATE student_courses SET progress_percent = ? WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$progress, $student_id, $course_id]);

    redirect('students.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mettre à jour la progression</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f5f5f5;
            padding: 2rem;
        }

        form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 500px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }

        h2 {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        input[type="number"] {
            width: 100%;
            padding: 0.7rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        button {
            background: #009688;
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #00796b;
        }
    </style>
</head>
<body>
    <form method="post">
        <h2>Mettre à jour la progression</h2>
        <label>Progression (%)</label>
        <input type="number" name="progress" min="0" max="100" required>
        <button type="submit">Enregistrer</button>
    </form>
</body>
</html>
