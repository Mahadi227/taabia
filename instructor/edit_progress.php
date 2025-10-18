<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;

if (!$student_id || !$course_id) {
    header('Location: students.php');
    exit;
}

// Verify course belongs to instructor
$stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
$stmt->execute([$course_id, $instructor_id]);
if ($stmt->rowCount() == 0) {
    header('Location: students.php');
    exit;
}

// Get current progress
$stmt = $pdo->prepare("SELECT progress_percent FROM student_courses WHERE student_id = ? AND course_id = ?");
$stmt->execute([$student_id, $course_id]);
$current_progress = $stmt->fetchColumn() ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progress = min(100, max(0, (int)$_POST['progress']));
    $stmt = $pdo->prepare("UPDATE student_courses SET progress_percent = ? WHERE student_id = ? AND course_id = ?");
    $stmt->execute([$progress, $student_id, $course_id]);
    header("Location: view_student_progress.php?student_id=$student_id&course_id=$course_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier la progression | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
        }

        .card h2 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
        }

        .card p {
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 2rem;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        .progress-display {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-weight: 600;
            color: #2d3748;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2><i class="fas fa-edit"></i> Modifier la progression</h2>
        <p>Mettez à jour le pourcentage de progression de l'étudiant dans ce cours.</p>

        <div class="progress-display">
            <i class="fas fa-chart-line"></i> Progression actuelle: <?= $current_progress ?>%
        </div>

        <form method="post">
            <div class="form-group">
                <label for="progress">Nouvelle progression (%) :</label>
                <input type="number"
                    name="progress"
                    id="progress"
                    min="0"
                    max="100"
                    value="<?= $current_progress ?>"
                    required>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Enregistrer
            </button>

            <a href="view_student_progress.php?student_id=<?= $student_id ?>&course_id=<?= $course_id ?>"
                class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Annuler
            </a>
        </form>
    </div>
</body>

</html>