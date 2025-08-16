<?php
require_once '../includes/db.php';

if (!isset($_GET['id'])) {
    echo "Lesson ID not provided.";
    exit;
}

$lesson_id = (int) $_GET['id'];

// Récupérer les détails de la leçon
$stmt = $pdo->prepare("SELECT l.*, c.title AS course_title, c.id AS course_id
                       FROM lessons l
                       JOIN courses c ON l.course_id = c.id
                       WHERE l.id = ?");
$stmt->execute([$lesson_id]);
$lesson = $stmt->fetch();

if (!$lesson) {
    echo "Leçon introuvable.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($lesson['title']) ?> - Leçon</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f1f3f5;
            padding: 30px;
        }
        .lesson-container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
            padding: 25px;
            max-width: 900px;
            margin: 0 auto;
        }
        h2 {
            margin-top: 0;
        }
        .video-container {
            margin: 20px 0;
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .content {
            margin-top: 20px;
            line-height: 1.6;
        }
        .back-button {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            background-color: #007bff;
            color: #fff;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .back-button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

<div class="lesson-container">
    <h2><?= htmlspecialchars($lesson['title']) ?></h2>
    <p><strong>Cours :</strong> <?= htmlspecialchars($lesson['course_title']) ?></p>

    <?php if (!empty($lesson['video_url'])): ?>
        <div class="video-container">
            <iframe src="<?= htmlspecialchars($lesson['video_url']) ?>" frameborder="0" allowfullscreen></iframe>
        </div>
    <?php else: ?>
        <p><em>Aucune vidéo n’est disponible pour cette leçon.</em></p>
    <?php endif; ?>

    <div class="content">
        <?= nl2br(htmlspecialchars($lesson['content'])) ?>
    </div>

    <a href="view_course.php?id=<?= $lesson['course_id'] ?>" class="back-button">⬅ Retour au cours</a>
</div>

</body>
</html>