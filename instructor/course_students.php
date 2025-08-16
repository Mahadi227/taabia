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

// Verify course belongs to this instructor
try {
    $course_stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    if ($course_stmt->execute([$course_id, $instructor_id])) {
        $course = $course_stmt->fetch();
        if (!$course) {
            flash_message("Cours non trouvé ou vous n'avez pas les permissions.", 'error');
            redirect('my_courses.php');
        }
    }
} catch (PDOException $e) {
    error_log("Database error in instructor/course_students.php: " . $e->getMessage());
    flash_message("Erreur de base de données.", 'error');
    redirect('my_courses.php');
}

// Get students enrolled in this course
$students = [];
try {
    $students_stmt = $pdo->prepare("
        SELECT sc.*, u.full_name, u.email, u.phone, u.created_at as joined_date
        FROM student_courses sc
        JOIN users u ON sc.student_id = u.id
        WHERE sc.course_id = ?
        ORDER BY sc.enrolled_at DESC
    ");
    if ($students_stmt->execute([$course_id])) {
        $students = $students_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in instructor/course_students.php: " . $e->getMessage());
    $students = [];
}

// Calculate course statistics
$total_students = count($students);
$active_students = 0;
$completed_students = 0;

foreach ($students as $student) {
    if ($student['progress'] >= 100) {
        $completed_students++;
    } elseif ($student['progress'] > 0) {
        $active_students++;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Étudiants du Cours | TaaBia</title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .students-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
        }

        .table-header h3 {
            margin: 0;
            color: #333;
        }

        .table-content {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1rem 1.5rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .progress-bar {
            width: 100px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s ease;
        }

        .progress-fill.warning {
            background: #ffc107;
        }

        .progress-fill.danger {
            background: #dc3545;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1976d2;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9rem;
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
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .table-content {
                font-size: 0.9rem;
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
            <h1>Étudiants du Cours</h1>
            <a href="message_student.php?course_id=<?= $course_id ?>" class="btn">
                <i class="fas fa-envelope"></i> Envoyer un message
            </a>
        </div>

        <div class="course-info">
            <h2 class="course-title"><?= htmlspecialchars($course['title']) ?></h2>
            <p class="course-description"><?= htmlspecialchars($course['description']) ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_students ?></div>
                <div class="stat-label">Total Étudiants</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $active_students ?></div>
                <div class="stat-label">Étudiants Actifs</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $completed_students ?></div>
                <div class="stat-label">Cours Terminés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $total_students > 0 ? round(($completed_students / $total_students) * 100, 1) : 0 ?>%</div>
                <div class="stat-label">Taux de Réussite</div>
            </div>
        </div>

        <?php if (empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>Aucun étudiant inscrit</h3>
            <p>Vous n'avez pas encore d'étudiants inscrits à ce cours.</p>
        </div>
        <?php else: ?>
        <div class="students-table">
            <div class="table-header">
                <h3>Liste des Étudiants (<?= $total_students ?>)</h3>
            </div>
            <div class="table-content">
                <table>
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Email</th>
                            <th>Progression</th>
                            <th>Date d'inscription</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="student-avatar">
                                        <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($student['full_name']) ?></div>
                                        <div style="font-size: 0.8rem; color: #666;"><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($student['email']) ?></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div class="progress-bar">
                                        <?php 
                                        $progress = $student['progress'] ?? 0;
                                        $progressClass = $progress >= 80 ? '' : ($progress >= 40 ? 'warning' : 'danger');
                                        ?>
                                        <div class="progress-fill <?= $progressClass ?>" style="width: <?= $progress ?>%"></div>
                                    </div>
                                    <span style="font-size: 0.9rem; color: #666;"><?= $progress ?>%</span>
                                </div>
                            </td>
                            <td><?= format_date($student['enrolled_at']) ?></td>
                            <td>
                                <div style="display: flex; gap: 0.5rem;">
                                    <a href="view_student_progress.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $course_id ?>" 
                                       class="btn btn-success" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                        <i class="fas fa-eye"></i> Progression
                                    </a>
                                    <a href="message_student.php?student_id=<?= $student['student_id'] ?>" 
                                       class="btn" style="padding: 0.5rem 1rem; font-size: 0.8rem;">
                                        <i class="fas fa-envelope"></i> Message
                                    </a>
                                    <a href="remove_student.php?student_id=<?= $student['student_id'] ?>&course_id=<?= $course_id ?>" 
                                       class="btn btn-danger" style="padding: 0.5rem 1rem; font-size: 0.8rem;"
                                       onclick="return confirm('Êtes-vous sûr de vouloir retirer cet étudiant du cours ?')">
                                        <i class="fas fa-user-times"></i> Retirer
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
