<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get instructor's courses with attendance data
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.title,
            c.image_url,
            c.status,
            COUNT(DISTINCT as2.id) as total_sessions,
            COUNT(DISTINCT sc.student_id) as enrolled_students,
            cas.attendance_required,
            cas.minimum_attendance_percent
        FROM courses c
        LEFT JOIN attendance_sessions as2 ON c.id = as2.course_id AND as2.is_active = 1
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        LEFT JOIN course_attendance_settings cas ON c.id = cas.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id, c.title, c.image_url, c.status, cas.attendance_required, cas.minimum_attendance_percent
        ORDER BY c.title ASC
    ");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    // Get recent attendance sessions
    $stmt = $pdo->prepare("
        SELECT 
            as2.*,
            c.title as course_title,
            COUNT(sa.id) as attendance_count,
            COUNT(CASE WHEN sa.attendance_status IN ('present', 'late') THEN 1 END) as present_count
        FROM attendance_sessions as2
        JOIN courses c ON as2.course_id = c.id
        LEFT JOIN student_attendance sa ON as2.id = sa.session_id
        WHERE as2.instructor_id = ?
        GROUP BY as2.id, as2.session_title, as2.session_date, as2.start_time, as2.end_time, as2.session_type, c.title
        ORDER BY as2.session_date DESC, as2.start_time DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id]);
    $recent_sessions = $stmt->fetchAll();

    // Get attendance statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT as2.id) as total_sessions,
            COUNT(DISTINCT sa.id) as total_attendance_records,
            COUNT(DISTINCT CASE WHEN sa.attendance_status = 'present' THEN sa.id END) as present_records,
            COUNT(DISTINCT CASE WHEN sa.attendance_status = 'absent' THEN sa.id END) as absent_records,
            COUNT(DISTINCT CASE WHEN sa.attendance_status = 'late' THEN sa.id END) as late_records,
            COUNT(DISTINCT CASE WHEN sa.attendance_status = 'excused' THEN sa.id END) as excused_records
        FROM attendance_sessions as2
        LEFT JOIN student_attendance sa ON as2.id = sa.session_id
        WHERE as2.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $attendance_stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Database error in instructor attendance management: " . $e->getMessage());
    $courses = [];
    $recent_sessions = [];
    $attendance_stats = [
        'total_sessions' => 0,
        'total_attendance_records' => 0,
        'present_records' => 0,
        'absent_records' => 0,
        'late_records' => 0,
        'excused_records' => 0
    ];
}

// Calculate overall attendance percentage
$overall_percentage = $attendance_stats['total_attendance_records'] > 0 
    ? round(($attendance_stats['present_records'] + $attendance_stats['late_records']) / $attendance_stats['total_attendance_records'] * 100, 2)
    : 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la présence | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p>Espace Formateur</p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    Nouveau cours
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="attendance_management.php" class="instructor-nav-item active">
                    <i class="fas fa-calendar-check"></i>
                    Gestion de la présence
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    Mes étudiants
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    Devoirs à valider
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    Mes gains
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Transactions
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    Paiements
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><i class="fas fa-calendar-check"></i> Gestion de la présence</h1>
                        <p>Gérez les sessions de présence et suivez l'assiduité de vos étudiants</p>
                    </div>
                    <div>
                        <a href="create_attendance_session.php" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            Nouvelle session
                        </a>
                    </div>
                </div>
            </div>

            <!-- Attendance Statistics Cards -->
            <div class="instructor-cards">
                <div class="instructor-card instructor-fade-in">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Sessions créées</div>
                    <div class="instructor-card-value"><?= $attendance_stats['total_sessions'] ?></div>
                    <div class="instructor-card-description">Total des sessions de présence</div>
                </div>

                <div class="instructor-card instructor-fade-in">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Taux de présence</div>
                    <div class="instructor-card-value"><?= $overall_percentage ?>%</div>
                    <div class="instructor-card-description">Moyenne générale de présence</div>
                </div>

                <div class="instructor-card instructor-fade-in">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Enregistrements</div>
                    <div class="instructor-card-value"><?= $attendance_stats['total_attendance_records'] ?></div>
                    <div class="instructor-card-description">Total des enregistrements</div>
                </div>

                <div class="instructor-card instructor-fade-in">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Présents</div>
                    <div class="instructor-card-value"><?= $attendance_stats['present_records'] + $attendance_stats['late_records'] ?></div>
                    <div class="instructor-card-description">Étudiants présents et en retard</div>
                </div>
            </div>

            <!-- Course Attendance Overview -->
            <div class="instructor-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-book"></i> Vue d'ensemble par cours
                    </h3>
                </div>
                
                <?php if (count($courses) > 0): ?>
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-course-grid">
                            <?php foreach ($courses as $course): ?>
                                <div class="instructor-course-card">
                                    <div class="instructor-course-image">
                                        <?php if ($course['image_url']): ?>
                                            <img src="<?= htmlspecialchars($course['image_url']) ?>" alt="<?= htmlspecialchars($course['title']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-book"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="instructor-course-content">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-3);">
                                            <h3 class="instructor-course-title">
                                                <?= htmlspecialchars($course['title']) ?>
                                            </h3>
                                            <span class="instructor-badge <?= $course['status'] == 'active' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($course['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="instructor-course-stats">
                                            <span>
                                                <i class="fas fa-calendar-check"></i>
                                                <?= $course['total_sessions'] ?> sessions
                                            </span>
                                            <span>
                                                <i class="fas fa-users"></i>
                                                <?= $course['enrolled_students'] ?> étudiants
                                            </span>
                                        </div>
                                        
                                        <?php if ($course['attendance_required']): ?>
                                            <div style="margin-top: var(--spacing-2);">
                                                <span class="instructor-badge warning">
                                                    <i class="fas fa-exclamation-triangle"></i> Présence requise
                                                </span>
                                                <div style="font-size: var(--font-size-sm); color: var(--gray-600); margin-top: var(--spacing-1);">
                                                    Minimum: <?= $course['minimum_attendance_percent'] ?>%
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="instructor-course-footer">
                                            <a href="course_attendance.php?course_id=<?= $course['id'] ?>" 
                                               class="instructor-btn instructor-btn-primary"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-eye"></i>
                                                Voir les détails
                                            </a>
                                            
                                            <a href="create_attendance_session.php?course_id=<?= $course['id'] ?>" 
                                               class="instructor-btn instructor-btn-success"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-plus"></i>
                                                Nouvelle session
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding: var(--spacing-6); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: var(--spacing-4);"></i>
                        <h3>Aucun cours trouvé</h3>
                        <p>Vous devez d'abord créer des cours pour gérer la présence</p>
                        <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            Créer un cours
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Attendance Sessions -->
            <?php if (count($recent_sessions) > 0): ?>
                <div class="instructor-table-container" style="margin-top: var(--spacing-6);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-history"></i> Sessions récentes
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Cours</th>
                                        <th>Session</th>
                                        <th>Type</th>
                                        <th>Heure</th>
                                        <th>Présence</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--gray-900);">
                                                    <?= date('d/m/Y', strtotime($session['session_date'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= htmlspecialchars($session['course_title']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= htmlspecialchars($session['session_title']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="instructor-badge info">
                                                    <?= ucfirst($session['session_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($session['start_time'] && $session['end_time']): ?>
                                                    <div style="font-size: var(--font-size-sm);">
                                                        <?= date('H:i', strtotime($session['start_time'])) ?> - <?= date('H:i', strtotime($session['end_time'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-size: var(--font-size-sm);">
                                                    <?= $session['present_count'] ?>/<?= $session['attendance_count'] ?>
                                                </div>
                                                <?php 
                                                $percentage = $session['attendance_count'] > 0 
                                                    ? round($session['present_count'] / $session['attendance_count'] * 100, 1)
                                                    : 0;
                                                ?>
                                                <div style="font-size: var(--font-size-xs); color: var(--gray-500);">
                                                    <?= $percentage ?>%
                                                </div>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: var(--spacing-1);">
                                                    <a href="view_attendance_session.php?session_id=<?= $session['id'] ?>" 
                                                       class="instructor-btn instructor-btn-primary"
                                                       style="padding: var(--spacing-1); font-size: var(--font-size-xs);">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <a href="edit_attendance_session.php?session_id=<?= $session['id'] ?>" 
                                                       class="instructor-btn instructor-btn-secondary"
                                                       style="padding: var(--spacing-1); font-size: var(--font-size-xs);">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="create_attendance_session.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-plus"></i>
                    Nouvelle session de présence
                </a>
                
                <a href="attendance_reports.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-chart-bar"></i>
                    Rapports de présence
                </a>
                
                <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.instructor-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        });
    </script>
</body>
</html> 