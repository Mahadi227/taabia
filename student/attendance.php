<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

try {
    // Get student's enrolled courses with attendance data
    $stmt = $pdo->prepare("
        SELECT 
            c.id as course_id,
            c.title as course_title,
            c.image_url,
            sc.enrolled_at,
            sc.progress_percent,
            cas.attendance_required,
            cas.minimum_attendance_percent,
            COUNT(DISTINCT as2.id) as total_sessions,
            COUNT(DISTINCT sa.id) as attended_sessions,
            ROUND(
                (COUNT(DISTINCT sa.id) / NULLIF(COUNT(DISTINCT as2.id), 0)) * 100, 2
            ) as attendance_percentage
        FROM student_courses sc
        JOIN courses c ON sc.course_id = c.id
        LEFT JOIN course_attendance_settings cas ON c.id = cas.course_id
        LEFT JOIN attendance_sessions as2 ON c.id = as2.course_id AND as2.is_active = 1
        LEFT JOIN student_attendance sa ON as2.id = sa.session_id AND sa.student_id = ? AND sa.attendance_status IN ('present', 'late')
        WHERE sc.student_id = ?
        GROUP BY c.id, c.title, c.image_url, sc.enrolled_at, sc.progress_percent, cas.attendance_required, cas.minimum_attendance_percent
        ORDER BY sc.enrolled_at DESC
    ");
    $stmt->execute([$student_id, $student_id]);
    $enrolled_courses = $stmt->fetchAll();

    // Get recent attendance records
    $stmt = $pdo->prepare("
        SELECT 
            sa.*,
            as2.session_title,
            as2.session_date,
            as2.start_time,
            as2.end_time,
            as2.session_type,
            c.title as course_title,
            u.fullname as instructor_name
        FROM student_attendance sa
        JOIN attendance_sessions as2 ON sa.session_id = as2.id
        JOIN courses c ON as2.course_id = c.id
        JOIN users u ON as2.instructor_id = u.id
        WHERE sa.student_id = ?
        ORDER BY as2.session_date DESC, as2.start_time DESC
        LIMIT 20
    ");
    $stmt->execute([$student_id]);
    $recent_attendance = $stmt->fetchAll();

    // Get attendance statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_records,
            SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN attendance_status = 'absent' THEN 1 ELSE 0 END) as absent_count,
            SUM(CASE WHEN attendance_status = 'late' THEN 1 ELSE 0 END) as late_count,
            SUM(CASE WHEN attendance_status = 'excused' THEN 1 ELSE 0 END) as excused_count
        FROM student_attendance sa
        JOIN attendance_sessions as2 ON sa.session_id = as2.id
        WHERE sa.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $attendance_stats = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Database error in student attendance: " . $e->getMessage());
    $enrolled_courses = [];
    $recent_attendance = [];
    $attendance_stats = [
        'total_records' => 0,
        'present_count' => 0,
        'absent_count' => 0,
        'late_count' => 0,
        'excused_count' => 0
    ];
}

// Calculate overall attendance percentage
$overall_percentage = $attendance_stats['total_records'] > 0 
    ? round(($attendance_stats['present_count'] + $attendance_stats['late_count']) / $attendance_stats['total_records'] * 100, 2)
    : 0;
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('attendance') ?> | TaaBia</title>
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
                        <h1><i class="fas fa-calendar-check"></i> <?= __('attendance') ?></h1>
                        <p><?= __('attendance_description') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
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
                    <div class="student-card-title"><?= __('overall_attendance') ?></div>
                    <div class="student-card-value"><?= $overall_percentage ?>%</div>
                    <div class="student-card-description"><?= __('overall_attendance_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('present_sessions') ?></div>
                    <div class="student-card-value"><?= $attendance_stats['present_count'] ?></div>
                    <div class="student-card-description"><?= __('present_sessions_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('late_sessions') ?></div>
                    <div class="student-card-value"><?= $attendance_stats['late_count'] ?></div>
                    <div class="student-card-description"><?= __('late_sessions_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('absent_sessions') ?></div>
                    <div class="student-card-value"><?= $attendance_stats['absent_count'] ?></div>
                    <div class="student-card-description"><?= __('absent_sessions_desc') ?></div>
                </div>
            </div>

            <!-- Course Attendance Overview -->
            <div class="student-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-book"></i> <?= __('course_attendance_overview') ?>
                    </h3>
                </div>
                
                <?php if (count($enrolled_courses) > 0): ?>
                    <div style="padding: var(--spacing-6);">
                        <div class="student-course-grid">
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="student-course-card">
                                    <div class="student-course-image">
                                        <?php if ($course['image_url']): ?>
                                            <img src="<?= htmlspecialchars($course['image_url']) ?>" alt="<?= htmlspecialchars($course['course_title']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-book"></i>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="student-course-content">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-3);">
                                            <h3 class="student-course-title">
                                                <?= htmlspecialchars($course['course_title']) ?>
                                            </h3>
                                            <?php if ($course['attendance_required']): ?>
                                                <span class="student-badge warning">
                                                    <i class="fas fa-exclamation-triangle"></i> <?= __('attendance_required') ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="student-course-stats">
                                            <span>
                                                <i class="fas fa-calendar-check"></i>
                                                <?= $course['attended_sessions'] ?>/<?= $course['total_sessions'] ?> <?= __('sessions') ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-percentage"></i>
                                                <?= $course['attendance_percentage'] ?? 0 ?>% <?= __('attendance') ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($course['attendance_required']): ?>
                                            <div style="margin-top: var(--spacing-2);">
                                                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                                    <?= __('minimum_required') ?>: <?= $course['minimum_attendance_percent'] ?>%
                                                </div>
                                                <?php 
                                                $attendance_status = ($course['attendance_percentage'] ?? 0) >= $course['minimum_attendance_percent'] ? 'success' : 'danger';
                                                $status_text = ($course['attendance_percentage'] ?? 0) >= $course['minimum_attendance_percent'] ? __('meeting_requirements') : __('below_requirements');
                                                ?>
                                                <span class="student-badge <?= $attendance_status ?>">
                                                    <?= $status_text ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="student-course-footer">
                                            <a href="course_attendance.php?course_id=<?= $course['course_id'] ?>" 
                                               class="student-btn student-btn-primary"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-eye"></i>
                                                <?= __('view_details') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="padding: var(--spacing-6); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: var(--spacing-4);"></i>
                        <h3><?= __('no_courses_enrolled') ?></h3>
                        <p><?= __('no_attendance_data') ?></p>
                        <a href="all_courses.php" class="student-btn student-btn-primary">
                            <i class="fas fa-book-open"></i>
                            <?= __('browse_courses') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Attendance Records -->
            <?php if (count($recent_attendance) > 0): ?>
                <div class="student-table-container" style="margin-top: var(--spacing-6);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-history"></i> <?= __('recent_attendance_records') ?>
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <div class="student-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th><?= __('date') ?></th>
                                        <th><?= __('course') ?></th>
                                        <th><?= __('session') ?></th>
                                        <th><?= __('status') ?></th>
                                        <th><?= __('time') ?></th>
                                        <th><?= __('instructor') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $record): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--gray-900);">
                                                    <?= date('d/m/Y', strtotime($record['session_date'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= htmlspecialchars($record['course_title']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= htmlspecialchars($record['session_title']) ?>
                                                </div>
                                                <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                                    <?= ucfirst($record['session_type']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $status_class = '';
                                                $status_icon = '';
                                                switch($record['attendance_status']) {
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
                                                }
                                                ?>
                                                <span class="student-badge <?= $status_class ?>">
                                                    <i class="<?= $status_icon ?>"></i>
                                                    <?= ucfirst($record['attendance_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($record['check_in_time']): ?>
                                                    <div style="font-size: var(--font-size-sm);">
                                                        <?= date('H:i', strtotime($record['check_in_time'])) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span style="color: var(--gray-400);">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="font-size: var(--font-size-sm);">
                                                    <?= htmlspecialchars($record['instructor_name']) ?>
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
                <a href="my_courses.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_courses') ?>
                </a>
                
                <a href="all_courses.php" class="student-btn student-btn-success">
                    <i class="fas fa-book-open"></i>
                    <?= __('browse_courses') ?>
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