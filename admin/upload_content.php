<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

// Récupérer tous les cours
$courses = $pdo->query("SELECT id, title FROM courses ORDER BY created_at DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = (int) $_POST['course_id'];
    $title = sanitize($_POST['title']);
    $type = $_POST['content_type'];
    $url = sanitize($_POST['content_url']);
    $position = (int) $_POST['position'];
    $preview = isset($_POST['is_free_preview']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO course_contents (course_id, title, content_type, content_url, is_free_preview, position) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$course_id, $title, $type, $url, $preview, $position]);

    redirect("upload_content.php");
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un contenu de cours | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f4f4;
            padding: 2rem;
        }
        .container {
            max-width: 700px;
            margin: auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        label {
            font-weight: bold;
            display: block;
            margin-top: 1rem;
        }
        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 0.8rem;
            margin-top: 0.3rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        input[type="checkbox"] {
            margin-right: 5px;
        }
        button {
            padding: 0.8rem 1.5rem;
            background-color: #009688;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background-color: #00796b;
        }
        .back-button {
    display: inline-block;
    margin-top: 1rem;
    color: #009688;
    text-decoration: none;
    font-weight: 600;
}
.back-button:hover {
    text-decoration: underline;
}

    </style>
</head>
<body>

<div class="container">

    <h2>Ajouter un contenu à un cours</h2>
    <form method="POST">
        <label for="course_id">Cours :</label>
        <select name="course_id" id="course_id" required>
            <option value="">-- Choisir un cours --</option>
            <?php foreach ($courses as $c): ?>
                <option value="<?= $c['id'] ?>"><?= sanitize($c['title']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="title">Titre du contenu :</label>
        <input type="text" name="title" id="title" required>

        <label for="content_type">Type de contenu :</label>
        <select name="content_type" id="content_type" required>
            <option value="video">Vidéo</option>
            <option value="pdf">PDF</option>
            <option value="texte">Texte</option>
            <option value="lien">Lien externe</option>
        </select>

        <label for="content_url">URL / Lien :</label>
        <input type="text" name="content_url" id="content_url" required>

        <label for="position">Position (ordre dans le cours) :</label>
        <input type="number" name="position" id="position" min="1" value="1">

        <label>
            <input type="checkbox" name="is_free_preview"> Contenu gratuit en prévisualisation ?
        </label>

        <br><br>
        <button type="submit">Ajouter le contenu</button>
        
    </form>
                    <a href="courses.php" class="back-button">← Retour à la liste des cours</a>

</div>

</body>
</html>
