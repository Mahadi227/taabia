<?php

/**
 * Student Quizzes Page
 * View and take quizzes for enrolled courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all'; // all, not_started, completed
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

// Build quizzes query
try {
    $where_conditions = ["sc.student_id = ?"];
    $params = [$student_id];

    if ($course_filter !== 'all') {
        $where_conditions[] = "q.course_id = ?";
        $params[] = $course_filter;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Fetch quizzes with attempt status
    $quizzes_query = "
        SELECT 
            q.*,
            c.title as course_title,
            c.id as course_id,
            u.full_name as instructor_name,
            qa.id as attempt_id,
            qa.score,
            qa.completed_at,
            qa.time_taken,
            CASE 
                WHEN qa.id IS NULL THEN 'not_started'
                ELSE 'completed'
            END as status
        FROM quizzes q
        INNER JOIN courses c ON q.course_id = c.id
        INNER JOIN users u ON c.instructor_id = u.id
        INNER JOIN student_courses sc ON sc.course_id = c.id
        LEFT JOIN quiz_attempts qa ON qa.quiz_id = q.id AND qa.student_id = ?
        WHERE {$where_clause}
        ORDER BY q.created_at DESC
    ";

    $params[] = $student_id; // Add student_id for the quiz attempts join
    $quizzes_stmt = $pdo->prepare($quizzes_query);
    $quizzes_stmt->execute($params);
    $all_quizzes = $quizzes_stmt->fetchAll();

    // Filter by status if needed
    if ($status_filter !== 'all') {
        $quizzes = array_filter($all_quizzes, function ($q) use ($status_filter) {
            return $q['status'] === $status_filter;
        });
    } else {
        $quizzes = $all_quizzes;
    }

    // Calculate statistics
    $total_quizzes = count($all_quizzes);
    $not_started_count = count(array_filter($all_quizzes, fn($q) => $q['status'] === 'not_started'));
    $completed_count = count(array_filter($all_quizzes, fn($q) => $q['status'] === 'completed'));

    $completed_quizzes = array_filter($all_quizzes, fn($q) => $q['score'] !== null);
    $avg_score = !empty($completed_quizzes) ? array_sum(array_column($completed_quizzes, 'score')) / count($completed_quizzes) : 0;
} catch (PDOException $e) {
    $quizzes = [];
    $total_quizzes = 0;
    $not_started_count = 0;
    $completed_count = 0;
    $avg_score = 0;
    $error_message = "Erreur: La table 'quizzes' n'existe pas encore dans votre base de données.";
}

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('quizzes') ?? 'Quiz' ?> | TaaBia</title>

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
            color: white;
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
            color: #004085;
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(102, 126, 234, 0.1) 0%, transparent 100%);
            color: #004085;
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
            color: #004085;
        }

        .page-header p {
            color: rgb(253, 253, 253);
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

        .quizzes-grid {
            display: grid;
            gap: 1.5rem;
        }

        .quiz-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .quiz-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .quiz-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
        }

        .course-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background: #e6f0ff;
            color: #004085;
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

        .status-badge.not_started {
            background: #feebc8;
            color: #7c2d12;
        }

        .status-badge.completed {
            background: #c6f6d5;
            color: #22543d;
        }

        .quiz-description {
            color: #4a5568;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .quiz-meta {
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
            color: #004082;
        }

        .quiz-actions {
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
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
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

        .score-display {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .score-value {
            font-size: 2rem;
            font-weight: 700;
            color: #004082;
        }

        .score-details {
            flex: 1;
        }

        .passing-score {
            color: #48bb78;
            font-weight: 600;
        }

        .failing-score {
            color: #e53e3e;
            font-weight: 600;
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
                <a href="assignments.php" class="nav-item">
                    <i class="fas fa-tasks"></i>
                    <?= __('assignments') ?? 'Devoirs' ?>
                </a>
                <a href="quizzes.php" class="nav-item active">
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
                    <i class="fas fa-question-circle"></i>
                    <?= __('my_quizzes') ?? 'Mes Quiz' ?>
                </h1>
                <p><?= __('quizzes_subtitle') ?? 'Testez vos connaissances et suivez vos résultats' ?></p>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                        <?= __('quizzes_table_missing') ?? 'Veuillez contacter votre administrateur pour créer la table "quizzes" dans la base de données.' ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= __('total_quizzes') ?? 'Total Quiz' ?></h3>
                    <div class="value"><?= $total_quizzes ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('not_started') ?? 'Non Commencé' ?></h3>
                    <div class="value"><?= $not_started_count ?></div>
                    <div class="label"><?= __('to_complete') ?? 'À faire' ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('completed') ?? 'Complétés' ?></h3>
                    <div class="value"><?= $completed_count ?></div>
                    <div class="label"><?= __('finished') ?? 'Terminés' ?></div>
                </div>
                <div class="stat-card">
                    <h3><?= __('average_score') ?? 'Score Moyen' ?></h3>
                    <div class="value"><?= round($avg_score, 1) ?>%</div>
                    <div class="label"><?= $completed_count ?> <?= __('graded') ?? 'quiz' ?></div>
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
                            <option value="not_started" <?= $status_filter === 'not_started' ? 'selected' : '' ?>><?= __('not_started') ?? 'Non Commencé' ?></option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>><?= __('completed') ?? 'Complété' ?></option>
                        </select>
                    </div>

                    <button type="submit" class="btn-filter">
                        <i class="fas fa-filter"></i> <?= __('filter') ?? 'Filtrer' ?>
                    </button>
                </form>
            </div>

            <!-- Quizzes List -->
            <div class="quizzes-grid">
                <?php if (empty($quizzes)): ?>
                    <div class="no-data">
                        <i class="fas fa-question-circle"></i>
                        <h3><?= __('no_quizzes') ?? 'Aucun quiz trouvé' ?></h3>
                        <p><?= __('no_quizzes_message') ?? 'Vous n\'avez aucun quiz pour le moment ou aucun quiz ne correspond aux filtres sélectionnés.' ?></p>
                    </div>
                <?php else: ?>
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="quiz-card">
                            <div class="quiz-header">
                                <div>
                                    <div class="quiz-title"><?= htmlspecialchars($quiz['title']) ?></div>
                                    <span class="course-badge"><?= htmlspecialchars($quiz['course_title']) ?></span>
                                </div>
                                <span class="status-badge <?= $quiz['status'] ?>">
                                    <?= match ($quiz['status']) {
                                        'not_started' => __('not_started') ?? 'Non Commencé',
                                        'completed' => __('completed') ?? 'Complété',
                                        default => $quiz['status']
                                    } ?>
                                </span>
                            </div>

                            <div class="quiz-description">
                                <?= nl2br(htmlspecialchars($quiz['description'] ?? '')) ?>
                            </div>

                            <div class="quiz-meta">
                                <div class="meta-item">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($quiz['instructor_name']) ?>
                                </div>
                                <?php if ($quiz['time_limit']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        <?= __('time_limit') ?? 'Temps limite' ?>: <?= $quiz['time_limit'] ?> <?= __('minutes') ?? 'minutes' ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($quiz['passing_score']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-award"></i>
                                        <?= __('passing_score') ?? 'Score de passage' ?>: <?= $quiz['passing_score'] ?>%
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($quiz['status'] === 'completed'): ?>
                                <?php
                                $passed = $quiz['score'] >= ($quiz['passing_score'] ?? 70);
                                ?>
                                <div class="score-display">
                                    <div class="score-value"><?= round($quiz['score'], 1) ?>%</div>
                                    <div class="score-details">
                                        <strong class="<?= $passed ? 'passing-score' : 'failing-score' ?>">
                                            <?= $passed ? (__('passed') ?? 'Réussi') : (__('failed') ?? 'Échoué') ?>
                                        </strong>
                                        <p style="color: #718096; font-size: 0.875rem;">
                                            <?= __('completed_on') ?? 'Complété le' ?> <?= date('d/m/Y H:i', strtotime($quiz['completed_at'])) ?>
                                        </p>
                                        <?php if ($quiz['time_taken']): ?>
                                            <p style="color: #718096; font-size: 0.875rem;">
                                                <?= __('time_taken') ?? 'Temps pris' ?>: <?= round($quiz['time_taken'] / 60, 1) ?> <?= __('minutes') ?? 'minutes' ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="quiz-actions">
                                <?php if ($quiz['status'] === 'not_started'): ?>
                                    <a href="take_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-play"></i>
                                        <?= __('start_quiz') ?? 'Commencer le quiz' ?>
                                    </a>
                                <?php else: ?>
                                    <a href="quiz_results.php?id=<?= $quiz['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-eye"></i>
                                        <?= __('view_results') ?? 'Voir les résultats' ?>
                                    </a>
                                    <?php if ($quiz['allow_retake'] ?? false): ?>
                                        <a href="take_quiz.php?id=<?= $quiz['id'] ?>" class="btn btn-primary">
                                            <i class="fas fa-redo"></i>
                                            <?= __('retake_quiz') ?? 'Refaire le quiz' ?>
                                        </a>
                                    <?php endif; ?>
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