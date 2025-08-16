<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? 0;

if (!$course_id) {
    header('Location: attendance.php');
    exit;
}

try {
    // Verify student is enrolled in this course
    $stmt = $pdo->prepare("
        SELECT c.*, sc.enrolled_at, sc.progress_percent, cas.*
        FROM courses c
        JOIN student_courses sc ON c.id = sc.course_id
        LEFT JOIN course_attendance_settings cas ON c.id = cas.course_id
        WHERE c.id = ? AND sc.student_id = ?
    ");
    $stmt->execute([$course_id, $student_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: attendance.php');
        exit;
    }

    // Get all attendance sessions for this course
    $stmt = $pdo->prepare("
        SELECT 
            as2.*,
            sa.attendance_status,
            sa.check_in_time,
            sa.check_out_time,
            sa.notes,
            u.fullname as instructor_name
        FROM attendance_sessions as2
        LEFT JOIN student_attendance sa ON as2.id = sa.session_id AND sa.student_id = ?
        JOIN users u ON as2.instructor_id = u.id
        WHERE as2.course_id = ? AND as2.is_active = 1
        ORDER BY as2.session_date DESC, as2.start_time DESC
    ");
    $stmt->execute([$student_id, $course_id]);
    $attendance_sessions = $stmt->fetchAll();

    // Calculate attendance statistics for this course
    $total_sessions = count($attendance_sessions);
    $present_sessions = 0;
    $late_sessions = 0;
    $absent_sessions = 0;
    $excused_sessions = 0;

    foreach ($attendance_sessions as $session) {
        switch ($session['attendance_status']) {
            case 'present':
                $present_sessions++;
                break;
            case 'late':
                $late_sessions++;
                break;
            case 'absent':
                $absent_sessions++;
                break;
            case 'excused':
                $excused_sessions++;
                break;
        }
    }

    $attendance_percentage = $total_sessions > 0 
        ? round(($present_sessions + $late_sessions) / $total_sessions * 100, 2)
        : 0;

    // Get monthly attendance breakdown
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(as2.session_date, '%Y-%m') as month,
            COUNT(*) as total_sessions,
            SUM(CASE WHEN sa.attendance_status IN ('present', 'late') THEN 1 ELSE 0 END) as attended_sessions,
            ROUND(
                (SUM(CASE WHEN sa.attendance_status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2
            ) as monthly_percentage
        FROM attendance_sessions as2
        LEFT JOIN student_attendance sa ON as2.id = sa.session_id AND sa.student_id = ?
        WHERE as2.course_id = ? AND as2.is_active = 1
        GROUP BY DATE_FORMAT(as2.session_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ");
    $stmt->execute([$student_id, $course_id]);
    $monthly_breakdown = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in course attendance: " . $e->getMessage());
    header('Location: attendance.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('course_attendance') ?> - <?= htmlspecialchars($course['title']) ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?></p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    <?= __('discover_courses') ?>
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="course_lessons.php" class="student-nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?>
                </a>
                <a href="attendance.php" class="student-nav-item active">
                    <i class="fas fa-calendar-check"></i>
                    <?= __('attendance') ?>
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?>
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?>
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('my_profile') ?>
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><i class="fas fa-calendar-check"></i> <?= __('course_attendance') ?></h1>
                        <p><?= htmlspecialchars($course['title']) ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Course Information -->
            <div class="student-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6);">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: var(--spacing-6); align-items: center;">
                        <div>
                            <?php if ($course['image_url']): ?>
                                <img src="<?= htmlspecialchars($course['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($course['title']) ?>"
                                     style="width: 100px; height: 100px; object-fit: cover; border-radius: var(--radius-lg);">
                            <?php else: ?>
                                <div style="width: 100px; height: 100px; background: var(--primary-color); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                    <i class="fas fa-book"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h2 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-900);">
                                <?= htmlspecialchars($course['title']) ?>
                            </h2>
                            <div style="display: flex; gap: var(--spacing-4); margin-bottom: var(--spacing-3);">
                                <span style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                    <i class="fas fa-calendar"></i> <?= __('enrolled') ?>: <?= date('d/m/Y', strtotime($course['enrolled_at'])) ?>
                                </span>
                                <span style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                    <i class="fas fa-chart-line"></i> <?= __('progress') ?>: <?= $course['progress_percent'] ?>%
                                </span>
                            </div>
                            <?php if ($course['attendance_required']): ?>
                                <div style="display: flex; align-items: center; gap: var(--spacing-2);">
                                    <span class="student-badge warning">
                                        <i class="fas fa-exclamation-triangle"></i> <?= __('attendance_required') ?>
                                    </span>
                                    <span style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                        <?= __('minimum_required') ?>: <?= $course['minimum_attendance_percent'] ?>%
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Statistics Cards -->
            <div class="student-cards">
                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('attendance_rate') ?></div>
                    <div class="student-card-value"><?= $attendance_percentage ?>%</div>
                    <div class="student-card-description"><?= __('attendance_rate_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('present_sessions') ?></div>
                    <div class="student-card-value"><?= $present_sessions ?></div>
                    <div class="student-card-description"><?= __('present_sessions_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('late_sessions') ?></div>
                    <div class="student-card-value"><?= $late_sessions ?></div>
                    <div class="student-card-description"><?= __('late_sessions_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('absent_sessions') ?></div>
                    <div class="student-card-value"><?= $absent_sessions ?></div>
                    <div class="student-card-description"><?= __('absent_sessions_desc') ?></div>
                </div>
            </div>

            <!-- Monthly Attendance Breakdown -->
            <?php if (count($monthly_breakdown) > 0): ?>
                <div class="student-table-container" style="margin-top: var(--spacing-6);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-chart-bar"></i> <?= __('monthly_attendance_breakdown') ?>
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <div class="student-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?= __('month') ?></th>
                                        <th><?= __('total_sessions') ?></th>
                                        <th><?= __('attended_sessions') ?></th>
                                        <th><?= __('attendance_rate') ?></th>
                                        <th><?= __('trend') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthly_breakdown as $month): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--gray-900);">
                                                    <?= date('F Y', strtotime($month['month'] . '-01')) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= $month['total_sessions'] ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= $month['attended_sessions'] ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--primary-color);">
                                                    <?= $month['monthly_percentage'] ?>%
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $trend_class = '';
                                                $trend_icon = '';
                                                if ($month['monthly_percentage'] >= 90) {
                                                    $trend_class = 'success';
                                                    $trend_icon = 'fas fa-arrow-up';
                                                } elseif ($month['monthly_percentage'] >= 70) {
                                                    $trend_class = 'warning';
                                                    $trend_icon = 'fas fa-minus';
                                                } else {
                                                    $trend_class = 'danger';
                                                    $trend_icon = 'fas fa-arrow-down';
                                                }
                                                ?>
                                                <span class="student-badge <?= $trend_class ?>">
                                                    <i class="<?= $trend_icon ?>"></i>
                                                    <?= $month['monthly_percentage'] >= 90 ? __('excellent') : ($month['monthly_percentage'] >= 70 ? __('good') : __('needs_improvement')) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Detailed Attendance Records -->
            <div class="student-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-list"></i> <?= __('detailed_attendance_records') ?>
                    </h3>
                </div>
                
                <?php if (count($attendance_sessions) > 0): ?>
                    <div style="padding: var(--spacing-6);">
                        <div class="student-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('session') ?></th>
                                        <th><?= __('type') ?></th>
                                        <th><?= __('time') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('check_in') ?></th>
                                        <th><?= __('instructor') ?></th>
                                        <th><?= __('notes') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--gray-900);">
                                                    <?= date('d/m/Y', strtotime($session['session_date'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= htmlspecialchars($session['session_title']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="student-badge info">
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
                                                <?php 
                                                $status_class = '';
                                                $status_icon = '';
                                                switch($session['attendance_status']) {
                                                    case 'present':
                                                        $status_class = 'success';
                                                        $status_icon = 'fas fa-check-circle';
                                                        break;
                                                    case 'late':
                                                        $status_class = 'warning';
                                                        $status_icon = 'fas fa-clock';
                                                        break;
                                                    case 'absent':
                                                        $status_class = 'danger';
                                                        $status_icon = 'fas fa-times-circle';
                                                        break;
                                                    case 'excused':
                                                        $status_class = 'info';
                                                        $status_icon = 'fas fa-user-clock';
                                                        break;
                                                    default:
                                                        $status_class = 'secondary';
                                                        $status_icon = 'fas fa-question-circle';
                                                        break;
                                                }
                                                ?>
                                                <span class="student-badge <?= $status_class ?>">
                                                    <i class="<?= $status_icon ?>"></i>
                                                    <?= $session['attendance_status'] ? ucfirst($session['attendance_status']) : __('not_recorded') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($session['check_in_time']): ?>
                                                    <div style="font-size: var(--font-size-sm);">
                                                        <?= date('H:i', strtotime($session['check_in_time'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-size: var(--font-size-sm);">
                                                    <?= htmlspecialchars($session['instructor_name']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($session['notes']): ?>
                                                    <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                                        <?= htmlspecialchars($session['notes']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400);">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding: var(--spacing-6); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: var(--spacing-4);"></i>
                        <h3><?= __('no_attendance_sessions') ?></h3>
                        <p><?= __('no_attendance_sessions_desc') ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="attendance.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_attendance') ?>
                </a>
                
                <a href="view_course.php?course_id=<?= $course_id ?>" class="student-btn student-btn-primary">
                    <i class="fas fa-book"></i>
                    <?= __('view_course') ?>
                </a>
                
                <a href="course_lessons.php?course_id=<?= $course_id ?>" class="student-btn student-btn-success">
                    <i class="fas fa-play-circle"></i>
                    <?= __('view_lessons') ?>
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add fade-in animation to cards
            const cards = document.querySelectorAll('.student-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = (index * 0.1) + 's';
            });
        });
    </script>
</body>
</html> 