<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

if (!isset($_GET['course_id'])) {
    redirect('my_courses.php');
}

$course_id = (int)$_GET['course_id'];

// Verify course belongs to this instructor and get enrollment count
try {
    $course_stmt = $pdo->prepare("
        SELECT c.*, COUNT(sc.student_id) as enrolled_students 
        FROM courses c 
        LEFT JOIN student_courses sc ON c.id = sc.course_id 
        WHERE c.id = ? AND c.instructor_id = ? 
        GROUP BY c.id
    ");
    if ($course_stmt->execute([$course_id, $instructor_id])) {
        $course = $course_stmt->fetch();
        if (!$course) {
            flash_message("Cours non trouvé ou vous n'avez pas les permissions.", 'error');
            redirect('my_courses.php');
        }
    }
} catch (PDOException $e) {
    error_log("Database error in instructor/course_lessons.php: " . $e->getMessage());
    flash_message("Erreur de base de données.", 'error');
    redirect('my_courses.php');
}

// Get lessons for this course
$lessons = [];
try {
    $lessons_stmt = $pdo->prepare("
        SELECT * FROM lessons 
        WHERE course_id = ? 
        ORDER BY order_index ASC
    ");
    if ($lessons_stmt->execute([$course_id])) {
        $lessons = $lessons_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in instructor/course_lessons.php: " . $e->getMessage());
    $lessons = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Leçons du Cours | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: #1976d2;
            color: white;
            position: fixed;
            padding: 2rem 1rem;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.4rem;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #1976d2;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #1565c0;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .course-info {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .course-title {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .course-description {
            color: #666;
            margin-bottom: 1rem;
        }

        .lessons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .lesson-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .lesson-card:hover {
            transform: translateY(-3px);
        }

        .lesson-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .lesson-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .lesson-type {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .type-video {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-pdf {
            background: #fff3e0;
            color: #f57c00;
        }

        .type-text {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .lesson-duration {
            color: #666;
            font-size: 0.9rem;
        }

        .lesson-content {
            padding: 1.5rem;
        }

        .lesson-description {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .lesson-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .lessons-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>👨‍🏫 Instructeur</h2>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="my_courses.php"><i class="fas fa-book"></i> Mes Cours</a>
        <a href="add_course.php"><i class="fas fa-plus"></i> Créer Cours</a>
        <a href="students.php"><i class="fas fa-users"></i> Mes Étudiants</a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> Mes Gains</a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> Paiements</a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Leçons du Cours</h1>
            <a href="add_lesson.php?course_id=<?= $course_id ?>" class="btn">
                <i class="fas fa-plus"></i> Ajouter une leçon
            </a>
        </div>

        <div class="course-info">
            <h2 class="course-title"><?= htmlspecialchars($course['title']) ?></h2>
            <p class="course-description"><?= htmlspecialchars($course['description']) ?></p>
            <div style="display: flex; gap: 2rem; color: #666;">
                <span><i class="fas fa-book"></i> <?= count($lessons) ?> leçons</span>
                <span><i class="fas fa-users"></i> <?= $course['enrolled_students'] ?? 0 ?> étudiants</span>
                <span><i class="fas fa-star"></i> <?= $course['rating'] ?? 'N/A' ?> étoiles</span>
            </div>
        </div>

        <?php if (empty($lessons)): ?>
        <div class="empty-state">
            <i class="fas fa-book-open"></i>
            <h3>Aucune leçon trouvée</h3>
            <p>Vous n'avez pas encore ajouté de leçons à ce cours.</p>
            <a href="add_lesson.php?course_id=<?= $course_id ?>" class="btn">Ajouter votre première leçon</a>
        </div>
        <?php else: ?>
        <div class="lessons-grid">
            <?php foreach ($lessons as $lesson): ?>
            <div class="lesson-card">
                <div class="lesson-header">
                    <h3 class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></h3>
                    <div class="lesson-type type-<?= $lesson['content_type'] ?>">
                        <?= ucfirst($lesson['content_type']) ?>
                    </div>
                    <div class="lesson-duration">
                        <i class="fas fa-clock"></i> <?= $lesson['duration'] ?? 'N/A' ?> minutes
                    </div>
                </div>
                
                <div class="lesson-content">
                    <p class="lesson-description">
                        <?= htmlspecialchars(substr($lesson['content'], 0, 100)) ?>
                        <?= strlen($lesson['content']) > 100 ? '...' : '' ?>
                    </p>
                    
                    <div class="lesson-actions">
                        <a href="view_lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-success">
                            <i class="fas fa-eye"></i> Voir
                        </a>
                        <a href="lesson_edit.php?id=<?= $lesson['id'] ?>" class="btn">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="lesson_delete.php?id=<?= $lesson['id'] ?>" class="btn btn-danger" 
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette leçon ?')">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
