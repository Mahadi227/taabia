<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once'../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';

// Recherche de cours par mot-clé
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE instructor_id = ? AND title LIKE ? ORDER BY created_at DESC");
    $stmt->execute([$instructor_id, "%$search%"]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE instructor_id = ? ORDER BY created_at DESC");
    $stmt->execute([$instructor_id]);
}

$courses = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Cours | Instructeur</title>
    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 30px;
        }
        h1 {
            color: #1e3a8a;
            margin-bottom: 20px;
        }
        form {
            margin-bottom: 20px;
        }
        input[type="text"] {
            padding: 10px;
            width: 300px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            font-size: 16px;
        }
        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s ease;
            margin-left: 6px;
        }
        .btn-blue {
            background-color: #3b82f6;
            color: white;
        }
        .btn-blue:hover {
            background-color: #2563eb;
        }
        .btn-green {
            background-color: #22c55e;
            color: white;
        }
        .btn-green:hover {
            background-color: #16a34a;
        }
        .btn-yellow {
            background-color: #facc15;
            color: #1e293b;
        }
        .btn-yellow:hover {
            background-color: #eab308;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            font-size: 15px;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
background-color: #009688;
            color: white;
        }

     

         .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.85rem;
            color: white;
        }
        .published { background-color: green; }
        .draft { background-color: orange; }
        .pending { background-color: gray; }

        .actions a {
            margin-right: 0.5rem;
            font-size: 0.9rem;
            text-decoration: none;
        }
        .actions a.edit { color: #007bff; }
        .actions a.delete { color: #f44336; }
        .actions a.stats { color: #673ab7; }
        .actions a.copy { color: #009688; }
        .actions a.view { color: #555; }
    </style>
</head>
<body>

    <h1>📚 Mes Cours</h1>

    <form method="get">
        <input type="text" name="search" placeholder="🔍 Rechercher un cours..." value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-blue">Rechercher</button>
    </form>

    <?php if (count($courses) === 0): ?>
        <p>Aucun cours trouvé.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Titre</th>
                    <th>Statut</th>
                    <th>Leçons</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $course): ?>
                    <?php
                        $lesson_stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
                        $lesson_stmt->execute([$course['id']]);
                        $lesson_count = $lesson_stmt->fetchColumn();

                        $status = $course['status'];
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($course['title']) ?></td>
                        <td>
                            <span class="badge <?= $status ?>">
                                <?= $status === 'published' ? '✅ Publié' : '🕒 Brouillon' ?>
                            </span>
                        </td>
                        <td><?= $lesson_count ?> </td>
                        <td>
                            <form method="post" action="toggle_course_status.php" style="display:inline;">
                                <input type="hidden" name="course_id" value="<?= $course['id'] ?>">
                                <button type="submit" class="btn <?= $status === 'published' ? 'btn-yellow' : 'btn-green' ?>">
                                    <?= $status === 'published' ? '🕒 Brouillon' : '✅ Publier' ?>
                                </button>
                            </form>
                            <a href="course_lessons.php?course_id=<?= $course['id'] ?>" class="btn btn-blue">📘 Voir Leçons</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>