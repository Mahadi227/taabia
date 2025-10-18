<?php

/**
 * Student Assignments Page
 * View and submit assignments for enrolled courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all'; // all, pending, submitted, graded
$course_filter = $_GET['course'] ?? 'all';

// Fetch student's enrolled courses for filter
$courses_query = "
    SELECT DISTINCT c.id, c.title 
    FROM courses c
    INNER JOIN student_courses sc ON c.id = sc.course_id
    WHERE sc.student_id = ?
    ORDER BY c.title
";
$courses_stmt = $pdo->prepare($courses_query);
$courses_stmt->execute([$student_id]);
$enrolled_courses = $courses_stmt->fetchAll();

// Build assignments query
try {
    $where_conditions = ["sc.student_id = ?"];
    $params = [$student_id];

    if ($course_filter !== 'all') {
        $where_conditions[] = "a.course_id = ?";
        $params[] = $course_filter;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Fetch assignments with submission status
    $assignments_query = "
        SELECT 
            a.*,
            c.title as course_title,
            c.id as course_id,
            u.full_name as instructor_name,
            asub.id as submission_id,
            asub.submitted_at,
            asub.grade,
            asub.feedback,
            asub.file_path as submission_file,
            CASE 
                WHEN asub.id IS NULL THEN 'pending'
                WHEN asub.grade IS NOT NULL THEN 'graded'
                ELSE 'submitted'
            END as status
        FROM assignments a
        INNER JOIN courses c ON a.course_id = c.id
        INNER JOIN users u ON c.instructor_id = u.id
        INNER JOIN student_courses sc ON sc.course_id = c.id
        LEFT JOIN assignment_submissions asub ON asub.assignment_id = a.id AND asub.student_id = ?
        WHERE {$where_clause}
        ORDER BY a.deadline ASC, a.created_at DESC
    ";

    $params[] = $student_id; // Add student_id for the submission join
    $assignments_stmt = $pdo->prepare($assignments_query);
    $assignments_stmt->execute($params);
    $all_assignments = $assignments_stmt->fetchAll();

    // Filter by status if needed
    if ($status_filter !== 'all') {
        $assignments = array_filter($all_assignments, function ($a) use ($status_filter) {
            return $a['status'] === $status_filter;
        });
    } else {
        $assignments = $all_assignments;
    }

    // Calculate statistics
    $total_assignments = count($all_assignments);
    $pending_count = count(array_filter($all_assignments, fn($a) => $a['status'] === 'pending'));
    $submitted_count = count(array_filter($all_assignments, fn($a) => $a['status'] === 'submitted'));
    $graded_count = count(array_filter($all_assignments, fn($a) => $a['status'] === 'graded'));

    $graded_assignments = array_filter($all_assignments, fn($a) => $a['grade'] !== null);
    $avg_grade = !empty($graded_assignments) ? array_sum(array_column($graded_assignments, 'grade')) / count($graded_assignments) : 0;
} catch (PDOException $e) {
    $assignments = [];
    $total_assignments = 0;
    $pending_count = 0;
    $submitted_count = 0;
    $graded_count = 0;
    $avg_grade = 0;
    $error_message = "Erreur: La table 'assignments' n'existe pas encore dans votre base de données.";
}

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('assignments') ?? 'Devoirs' ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
        }

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);

            border-bottom: 1px solid #e2e8f0;
        }

        .sidebar-header h2 {
            color: rgb(250, 250, 250);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-header p {
            color: rgb(255, 255, 255);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-item:hover {
            background: #f7fafc;
            color: #004075;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1) 0%, transparent 100%);
            color: #004082;
            font-weight: 600;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: #004082;
        }

        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            color: #1a202c;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header h1 i {
            color: #004082;
        }

        .page-header p {
            color: #718096;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .stat-card h3 {
            color: #718096;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            color: #1a202c;
            font-size: 2rem;
            font-weight: 700;
        }

        .stat-card .label {
            color: #004082;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            color: #4a5568;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .form-group select {
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .btn-filter {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .assignments-grid {
            display: grid;
            gap: 1.5rem;
        }

        .assignment-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .assignment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .assignment-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .assignment-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .course-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e6f0ff;
            color: #004075;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-badge.pending {
            background: #feebc8;
            color: #7c2d12;
        }

        .status-badge.submitted {
            background: #bee3f8;
            color: #2c5282;
        }

        .status-badge.graded {
            background: #c6f6d5;
            color: #22543d;
        }

        .assignment-description {
            color: #4a5568;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .assignment-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #718096;
            font-size: 0.9rem;
        }

        .meta-item i {
            color: #667eea;
        }

        .deadline-warning {
            color: #e53e3e;
            font-weight: 600;
        }

        .assignment-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .grade-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .grade-score {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .feedback-box {
            margin-top: 1rem;
            padding: 1rem;
            background: #f7fafc;
            border-left: 4px solid #667eea;
            border-radius: 4px;
        }

        .feedback-box h4 {
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .feedback-box p {
            color: #4a5568;
            line-height: 1.6;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: 12px;
            color: #a0aec0;
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .error-message {
            background: #fed7d7;
            color: #742a2a;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
            </div>

            <nav class="sidebar-nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item">
                    <i class="fas fa-book-open"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item">
                    <i class="fas fa-search"></i>
                    <?= __('discover_courses') ?? 'Découvrir' ?>
                </a>
                <a href="course_lessons.php" class="nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?? 'Mes Leçons' ?>
                </a>
                <a href="assignments.php" class="nav-item active">
                    <i class="fas fa-tasks"></i>
                    <?= __('assignments') ?? 'Devoirs' ?>
                </a>
                <a href="quizzes.php" class="nav-item">
                    <i class="fas fa-question-circle"></i>
                    <?= __('quizzes') ?? 'Quiz' ?>
                </a>
                <a href="attendance.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <?= __('attendance') ?? 'Présence' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-tasks"></i>
                    <?= __('my_assignments') ?? 'Mes Devoirs' ?>
                </h1>
                <p><?= __('assignments_subtitle') ?? 'Gérez et soumettez vos devoirs pour tous vos cours' ?></p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                        <?= __('assignments_table_missing') ?? 'Veuillez contacter votre administrateur pour créer la table "assignments" dans la base de données.' ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= __('total_assignments') ?? 'Total Devoirs' ?></h3>
                    <div class="value"><?= $total_assignments ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('pending') ?? 'En Attente' ?></h3>
                    <div class="value"><?= $pending_count ?></div>
                    <div class="label"><?= __('to_submit') ?? 'À soumettre' ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('submitted') ?? 'Soumis' ?></h3>
                    <div class="value"><?= $submitted_count ?></div>
                    <div class="label"><?= __('awaiting_grade') ?? 'En correction' ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('average_grade') ?? 'Moyenne' ?></h3>
                    <div class="value"><?= round($avg_grade, 1) ?>%</div>
                    <div class="label"><?= $graded_count ?> <?= __('graded') ?? 'notés' ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters-section">
                <form method="GET" action="" class="filters-form">
                    <div class="form-group">
                        <label for="course"><?= __('course') ?? 'Cours' ?></label>
                        <select name="course" id="course" onchange="this.form.submit()">
                            <option value="all"><?= __('all_courses') ?? 'Tous les cours' ?></option>
                            <?php foreach ($enrolled_courses as $course): ?>
                                <option value="<?= $course['id'] ?>" <?= $course_filter == $course['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status"><?= __('status') ?? 'Statut' ?></label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="all"><?= __('all_status') ?? 'Tous les statuts' ?></option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>><?= __('pending') ?? 'En Attente' ?></option>
                            <option value="submitted" <?= $status_filter === 'submitted' ? 'selected' : '' ?>><?= __('submitted') ?? 'Soumis' ?></option>
                            <option value="graded" <?= $status_filter === 'graded' ? 'selected' : '' ?>><?= __('graded') ?? 'Noté' ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> <?= __('filter') ?? 'Filtrer' ?>
                    </button>
                </form>
            </div>

            <!-- Assignments List -->
            <div class="assignments-grid">
                <?php if (empty($assignments)): ?>
                    <div class="no-data">
                        <i class="fas fa-clipboard-list"></i>
                        <h3><?= __('no_assignments') ?? 'Aucun devoir trouvé' ?></h3>
                        <p><?= __('no_assignments_message') ?? 'Vous n\'avez aucun devoir pour le moment ou aucun devoir ne correspond aux filtres sélectionnés.' ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($assignments as $assignment): ?>
                        <?php
                        $deadline = strtotime($assignment['deadline']);
                        $now = time();
                        $is_overdue = $deadline < $now && $assignment['status'] === 'pending';
                        $days_left = ceil(($deadline - $now) / (60 * 60 * 24));
                        ?>
                        <div class="assignment-card">
                            <div class="assignment-header">
                                <div>
                                    <div class="assignment-title"><?= htmlspecialchars($assignment['title']) ?></div>
                                    <span class="course-badge"><?= htmlspecialchars($assignment['course_title']) ?></span>
                                </div>
                                <span class="status-badge <?= $assignment['status'] ?>">
                                    <?= match ($assignment['status']) {
                                        'pending' => __('pending') ?? 'En Attente',
                                        'submitted' => __('submitted') ?? 'Soumis',
                                        'graded' => __('graded') ?? 'Noté',
                                        default => $assignment['status']
                                    } ?>
                                </span>
                            </div>

                            <div class="assignment-description">
                                <?= nl2br(htmlspecialchars($assignment['description'] ?? '')) ?>
                            </div>

                            <div class="assignment-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($assignment['instructor_name']) ?>
                                </div>
                                <div class="meta-item <?= $is_overdue ? 'deadline-warning' : '' ?>">
                                    <i class="fas fa-calendar"></i>
                                    <?= __('deadline') ?? 'Date limite' ?>: <?= date('d/m/Y H:i', $deadline) ?>
                                    <?php if ($assignment['status'] === 'pending'): ?>
                                        (<?= $is_overdue ? __('overdue') ?? 'En retard' : $days_left . ' ' . (__('days_left') ?? 'jours restants') ?>)
                                    <?php endif; ?>
                                </div>
                                <?php if ($assignment['max_grade']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-star"></i>
                                        <?= __('max_grade') ?? 'Note max' ?>: <?= $assignment['max_grade'] ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($assignment['status'] === 'graded'): ?>
                                <div class="grade-display">
                                    <div class="grade-score"><?= $assignment['grade'] ?>%</div>
                                    <div>
                                        <strong><?= __('your_grade') ?? 'Votre note' ?></strong>
                                        <p style="color: #718096; font-size: 0.875rem;">
                                            <?= __('submitted_on') ?? 'Soumis le' ?> <?= date('d/m/Y H:i', strtotime($assignment['submitted_at'])) ?>
                                        </p>
                                    </div>
                                </div>

                                <?php if ($assignment['feedback']): ?>
                                    <div class="feedback-box">
                                        <h4><?= __('instructor_feedback') ?? 'Commentaire de l\'instructeur' ?></h4>
                                        <p><?= nl2br(htmlspecialchars($assignment['feedback'])) ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($assignment['status'] === 'submitted'): ?>
                                <div class="meta-item" style="background: #f7fafc; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
                                    <i class="fas fa-check-circle" style="color: #48bb78;"></i>
                                    <?= __('submitted_on') ?? 'Soumis le' ?> <?= date('d/m/Y H:i', strtotime($assignment['submitted_at'])) ?>
                                </div>
                            <?php endif; ?>

                            <div class="assignment-actions">
                                <?php if ($assignment['status'] === 'pending'): ?>
                                    <a href="submit_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-upload"></i>
                                        <?= __('submit_assignment') ?? 'Soumettre le devoir' ?>
                                    </a>
                                <?php else: ?>
                                    <a href="view_assignment.php?id=<?= $assignment['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-eye"></i>
                                        <?= __('view_details') ?? 'Voir les détails' ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ($assignment['file_path']): ?>
                                    <a href="<?= htmlspecialchars($assignment['file_path']) ?>" class="btn btn-secondary" download>
                                        <i class="fas fa-download"></i>
                                        <?= __('download_instructions') ?? 'Télécharger' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>