<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$course_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$instructor_id = $_SESSION['user_id'];

// Vérifie que le cours appartient à l'instructeur
$course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
$course_stmt->execute([$course_id, $instructor_id]);
$course = $course_stmt->fetch();

if (!$course) {
    echo "⛔ Cours introuvable ou non autorisé.";
    exit;
}

// Récupérer les contenus du cours
$content_stmt = $pdo->prepare("SELECT * FROM course_contents WHERE course_id = ?");
$content_stmt->execute([$course_id]);
$contents = $content_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($course['title']) ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; margin: 0; background: #f5f5f5; }
        .sidebar {
            width: 220px;
            height: 100vh;
            background: #00796b;
            color: white;
            position: fixed;
            padding: 1.5rem;
        }
        .sidebar h2 { margin-bottom: 2rem; }
        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        .content {
            margin-left: 240px;
            padding: 2rem;
        }
        .card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 0 8px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 0 8px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 0.9rem;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        th { background-color: #f2f2f2; }
        tr:hover { background-color: #f9f9f9; }
        .btn {
            padding: 0.4rem 0.8rem;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-right: 5px;
        }
        .btn:hover { background: #388E3C; }
        .btn-danger {
            background: #e53935;
        }
        .btn-danger:hover {
            background: #c62828;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Formateur</h2>
    <a href="index.php">Dashboard</a>
    <a href="my_courses.php">Mes cours</a>
    <a href="student_courses.php">Gestion des cours</a>
    <a href="students.php">Étudiants</a>
    <a href="../auth/logout.php">Déconnexion</a>
</div>

<div class="content">
    <div class="card">
        <h1><?= htmlspecialchars($course['title']) ?></h1>
        <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
        <p><strong>Statut :</strong> <?= htmlspecialchars($course['status']) ?> | 
           <strong>Prix :</strong> <?= $course['price'] ?> FCFA</p>
        <a href="upload_content.php?course_id=<?= $course['id'] ?>" class="btn">➕ Ajouter du contenu</a>
    </div>

    <h2>Contenus du cours</h2>

    <?php if (count($contents) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($contents as $c): ?>
                    <tr>
                        <td><?= htmlspecialchars($c['title']) ?></td>
                        <td><?= htmlspecialchars($c['type']) ?></td>
                        <td><?= htmlspecialchars($c['description'] ?? '—') ?></td>
                        <td>
                            <a href="edit_content.php?id=<?= $c['id'] ?>" class="btn">Modifier</a>
                            <a href="delete_content.php?id=<?= $c['id'] ?>" class="btn btn-danger" onclick="return confirm('Confirmer la suppression ?')">Supprimer</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Aucun contenu n’a encore été ajouté.</p>
    <?php endif; ?>
</div>

</body>
</html>
