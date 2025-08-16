<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$content_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Vérifie que ce contenu appartient à un cours de l’instructeur
$stmt = $pdo->prepare("
    SELECT cc.*, c.instructor_id
    FROM course_contents cc
    JOIN courses c ON cc.course_id = c.id
    WHERE cc.id = ? AND c.instructor_id = ?
");
$stmt->execute([$content_id, $_SESSION['user_id']]);
$content = $stmt->fetch();

if (!$content) {
    echo "⛔ Contenu introuvable ou non autorisé.";
    exit;
}

// Traitement de la mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    $content_data = '';

    if ($type === 'text') {
        $content_data = $_POST['text_content'] ?? '';
    } elseif ($type === 'video') {
        $content_data = $_POST['video_url'] ?? '';
    } elseif ($type === 'file') {
        $content_data = $_POST['file_url'] ?? '';
    }

    $update = $pdo->prepare("
        UPDATE course_contents 
        SET title = ?, type = ?, content_data = ?, description = ? 
        WHERE id = ?
    ");
    $update->execute([$title, $type, $content_data, $description, $content_id]);

    redirect('view_course.php?id=' . $content['course_id']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier le contenu | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f7f7f7; padding: 2rem; }
        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h1 { margin-top: 0; }
        label { display: block; font-weight: bold; margin-top: 1rem; }
        input, textarea, select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ccc;
            border-radius: 5px;
            margin-top: 0.3rem;
        }
        textarea { resize: vertical; }
        button {
            margin-top: 1.5rem;
            padding: 0.7rem 1.2rem;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        button:hover {
            background-color: #388E3C;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Modifier le contenu</h1>

    <form method="POST">
        <label for="title">Titre :</label>
        <input type="text" name="title" id="title" value="<?= htmlspecialchars($content['title']) ?>" required>

        <label for="description">Description :</label>
        <textarea name="description" rows="4"><?= htmlspecialchars($content['description'] ?? '') ?></textarea>

        <label for="type">Type :</label>
        <select name="type" id="type" onchange="toggleFields()" required>
            <option value="text" <?= $content['type'] === 'text' ? 'selected' : '' ?>>Texte</option>
            <option value="video" <?= $content['type'] === 'video' ? 'selected' : '' ?>>Vidéo</option>
            <option value="file" <?= $content['type'] === 'file' ? 'selected' : '' ?>>Fichier</option>
        </select>

        <div id="text-field" style="display: <?= $content['type'] === 'text' ? 'block' : 'none' ?>;">
            <label for="text_content">Contenu texte :</label>
            <textarea name="text_content" rows="5"><?= $content['type'] === 'text' ? htmlspecialchars($content['content_data']) : '' ?></textarea>
        </div>

        <div id="video-field" style="display: <?= $content['type'] === 'video' ? 'block' : 'none' ?>;">
            <label for="video_url">Lien vidéo :</label>
            <input type="text" name="video_url" value="<?= $content['type'] === 'video' ? htmlspecialchars($content['content_data']) : '' ?>">
        </div>

        <div id="file-field" style="display: <?= $content['type'] === 'file' ? 'block' : 'none' ?>;">
            <label for="file_url">Lien fichier :</label>
            <input type="text" name="file_url" value="<?= $content['type'] === 'file' ? htmlspecialchars($content['content_data']) : '' ?>">
        </div>

        <button type="submit">Enregistrer les modifications</button>
    </form>
</div>

<script>
    function toggleFields() {
        const type = document.getElementById('type').value;
        document.getElementById('text-field').style.display = (type === 'text') ? 'block' : 'none';
        document.getElementById('video-field').style.display = (type === 'video') ? 'block' : 'none';
        document.getElementById('file-field').style.display = (type === 'file') ? 'block' : 'none';
    }
</script>

</body>
</html>
