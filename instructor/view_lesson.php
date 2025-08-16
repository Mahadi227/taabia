<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Vérifie que l'ID de la leçon est passé
if (!isset($_GET['id']) || empty($_GET['id'])) {
    flash_message("ID de leçon non spécifié.", 'error');
    redirect('lessons.php');
}

$lesson_id = (int)$_GET['id'];

// Récupération de la leçon depuis la base de données avec vérification d'appartenance
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.title as course_title 
        FROM lessons l 
        JOIN courses c ON l.course_id = c.id 
        WHERE l.id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$lesson_id, $instructor_id]);
    $lesson = $stmt->fetch();

    if (!$lesson) {
        flash_message("Leçon non trouvée ou vous n'avez pas les permissions.", 'error');
        redirect('lessons.php');
    }
} catch (PDOException $e) {
    error_log("Database error in view_lesson: " . $e->getMessage());
    flash_message("Erreur de base de données.", 'error');
    redirect('lessons.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Voir Leçon | TaaBia</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }

        .lesson-container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 0 15px rgba(0,0,0,0.08);
        }

        h2 {
            color: #343a40;
            margin-bottom: 15px;
        }

        .lesson-detail {
            margin-bottom: 12px;
        }

        .lesson-detail strong {
            color: #007bff;
        }

        .btn-download {
            display: inline-block;
            margin-top: 15px;
            padding: 10px 18px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .btn-download:hover {
            background: #0056b3;
        }

        video, iframe, embed {
            width: 100%;
            max-height: 400px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .back-link {
            display: block;
            margin-top: 30px;
            text-align: center;
            text-decoration: none;
            color: #555;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="lesson-container">
    <h2><?= htmlspecialchars($lesson['title']) ?></h2>

    <div class="lesson-detail">
        <strong>Cours :</strong> <?= htmlspecialchars($lesson['course_title']) ?>
    </div>

    <div class="lesson-detail">
        <strong>Type de contenu :</strong> <?= ucfirst($lesson['content_type']) ?>
    </div>

    <div class="lesson-detail">
        <strong>Ordre :</strong> <?= $lesson['order_index'] ?>
    </div>

    <div class="lesson-detail">
        <strong>Contenu :</strong><br>
        <?= nl2br(htmlspecialchars($lesson['content'])) ?>
    </div>

    <?php if (!empty($lesson['file_url'])): ?>
    <div class="lesson-detail">
        <strong>Fichier/URL :</strong><br>
        <?php
        $file_url = $lesson['file_url'];
        $file_ext = pathinfo($file_url, PATHINFO_EXTENSION);

        if (in_array($file_ext, ['mp4', 'webm', 'avi', 'mov'])) {
            echo "<video controls src=\"$file_url\"></video>";
        } elseif (in_array($file_ext, ['pdf'])) {
            echo "<embed src=\"$file_url\" type=\"application/pdf\" />";
        } elseif (strpos($file_url, 'youtube.com') !== false || strpos($file_url, 'youtu.be') !== false) {
            // Convert YouTube URL to embed
            $video_id = '';
            if (strpos($file_url, 'youtube.com/watch?v=') !== false) {
                $video_id = substr($file_url, strpos($file_url, 'v=') + 2);
            } elseif (strpos($file_url, 'youtu.be/') !== false) {
                $video_id = substr($file_url, strpos($file_url, 'youtu.be/') + 9);
            }
            if ($video_id) {
                echo "<iframe width=\"100%\" height=\"400\" src=\"https://www.youtube.com/embed/$video_id\" frameborder=\"0\" allowfullscreen></iframe>";
            } else {
                echo "<a class='btn-download' href=\"$file_url\" target=\"_blank\">Voir la vidéo</a>";
            }
        } else {
            echo "<a class='btn-download' href=\"$file_url\" target=\"_blank\">Voir le fichier</a>";
        }
        ?>
    </div>
    <?php endif; ?>

    <a class="back-link" href="lessons.php">← Retour à la liste des leçons</a>
</div>

</body>
</html>
