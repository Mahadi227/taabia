<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// Vérifie si le cours appartient à l'instructeur
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
$course = $stmt->fetch();

if (!$course) {
    echo "⛔ Cours introuvable ou non autorisé";
    exit;
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $type = $_POST['type'];
    $description = trim($_POST['description']);
    $content_data = '';

    if ($type === 'video') {
        $content_data = $_POST['video_url'] ?? '';
    } elseif ($type === 'file') {
        $content_data = $_POST['file_url'] ?? '';
    } elseif ($type === 'text') {
        $content_data = $_POST['text_content'] ?? '';
    }

    $stmt = $pdo->prepare("INSERT INTO course_contents (course_id, title, type, content_data, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$course_id, $title, $type, $content_data, $description]);

    redirect("view_course.php?id=$course_id");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un contenu | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; background: #f4f4f4; }
        .container {
            max-width: 700px;
            margin: 50px auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        h1 { margin-top: 0; }
        label { font-weight: bold; display: block; margin-top: 1rem; }
        input[type="text"],
        textarea,
        select {
            width: 100%;
            padding: 0.8rem;
            margin-top: 0.3rem;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        textarea { resize: vertical; }
        button {
            margin-top: 1.5rem;
            background: #009688;
            color: white;
            border: none;
            padding: 0.8rem 1.2rem;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover { background: #00796b; }
        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            text-decoration: none;
            color: #009688;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Ajouter un contenu au cours : <?= htmlspecialchars($course['title']) ?></h1>

    <form method="POST">
        <label for="title">Titre du contenu :</label>
        <input type="text" name="title" id="title" required>

        <label for="description">Description :</label>
        <textarea name="description" id="description" rows="4" placeholder="Ex : Ce module couvre les bases..."></textarea>

        <label for="type">Type de contenu :</label>
        <select name="type" id="type" required onchange="toggleContentFields()">
            <option value="text">Texte</option>
            <option value="video">Vidéo</option>
            <option value="file">Fichier</option>
        </select>

        <div id="text-field">
            <label for="text_content">Contenu texte :</label>
            <textarea name="text_content" id="text_content" rows="5" placeholder="Tapez le contenu ici..."></textarea>
        </div>

        <div id="video-field" style="display:none;">
            <label for="video_url">Lien vidéo :</label>
            <input type="text" name="video_url" id="video_url" placeholder="https://youtube.com/...">
        </div>

        <div id="file-field" style="display:none;">
            <label for="file_url">Lien du fichier :</label>
            <input type="text" name="file_url" id="file_url" placeholder="https://... ou /uploads/fichier.pdf">
        </div>

        <button type="submit">Ajouter le contenu</button>
    </form>

    <a href="view_course.php?id=<?= $course_id ?>" class="back-link">← Retour au cours</a>
</div>

<script>
    function toggleContentFields() {
        const type = document.getElementById('type').value;
        document.getElementById('text-field').style.display = (type === 'text') ? 'block' : 'none';
        document.getElementById('video-field').style.display = (type === 'video') ? 'block' : 'none';
        document.getElementById('file-field').style.display = (type === 'file') ? 'block' : 'none';
    }
</script>

</body>
</html>
