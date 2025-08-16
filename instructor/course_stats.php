<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

// Vérification de l'ID du cours
if (!isset($_GET['id'])) {
    redirect('my_courses.php');
}

$course_id = (int) $_GET['id'];
$instructor_id = $_SESSION['user_id'];

// Vérifie que le cours appartient bien à l'instructeur
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
$course = $stmt->fetch();

if (!$course) {
    echo "⛔ Cours introuvable ou non autorisé.";
    exit;
}

// Compter les contenus du cours
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM course_contents WHERE course_id = ?");
$stmt2->execute([$course_id]);
$total_contents = $stmt2->fetchColumn();

// Ajouter d'autres stats si tu veux
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Statistiques du cours | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            background: #f4f4f4;
            padding: 2rem;
        }
        .container {
            max-width: 800px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        h1 {
            color: #009688;
            margin-bottom: 1rem;
        }
        .stats {
            margin-top: 1rem;
        }
        .stat-item {
            margin-bottom: 1rem;
        }
        .btn-back {
            display: inline-block;
            margin-top: 2rem;
            background: #009688;
            color: white;
            padding: 0.7rem 1.2rem;
            border-radius: 5px;
            text-decoration: none;
        }
        .btn-back:hover {
            background: #00796b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Statistiques du cours</h1>

        <div class="stats">
            <div class="stat-item"><strong>Titre :</strong> <?= sanitize($course['title']) ?></div>
            <div class="stat-item"><strong>Description :</strong> <?= sanitize($course['description']) ?></div>
            <div class="stat-item"><strong>Status :</strong> <?= ucfirst($course['status']) ?></div>
            <div class="stat-item"><strong>Date de création :</strong> <?= format_date($course['created_at']) ?></div>
            <div class="stat-item"><strong>Contenus publiés :</strong> <?= $total_contents ?> élément(s)</div>
        </div>

        <a href="my_courses.php" class="btn-back">← Retour à mes cours</a>
    </div>
</body>
</html>
