<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$instructor_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../auth/logout.php');
        exit;
    }

    // Get instructor statistics
    $stmtCourses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmtCourses->execute([$instructor_id]);
    $total_courses = $stmtCourses->fetchColumn();

    $stmtStudents = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id)
        FROM student_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        WHERE c.instructor_id = ?
    ");
    $stmtStudents->execute([$instructor_id]);
    $total_students = $stmtStudents->fetchColumn();

    $stmtContents = $pdo->prepare("
        SELECT COUNT(*) FROM course_contents
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmtContents->execute([$instructor_id]);
    $total_contents = $stmtContents->fetchColumn();

    $stmtLessons = $pdo->prepare("
        SELECT COUNT(*) FROM lessons
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmtLessons->execute([$instructor_id]);
    $total_lessons = $stmtLessons->fetchColumn();

    // Get earnings data
    $stmtEarnings = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total 
        FROM transactions 
        WHERE instructor_id = ?
    ");
    $stmtEarnings->execute([$instructor_id]);
    $earnings = $stmtEarnings->fetch();
    $total_sales = $earnings['count'] ?? 0;
    $total_earnings = $earnings['total'] ?? 0;

    // Get recent teaching activity
    $stmtRecent = $pdo->prepare("
        SELECT 'course' as type, c.title as name, c.created_at as date, NULL as progress
        FROM courses c
        WHERE c.instructor_id = ?
        UNION ALL
        SELECT 'lesson' as type, l.title as name, l.created_at as date, NULL as progress
        FROM lessons l
        INNER JOIN courses c ON l.course_id = c.id
        WHERE c.instructor_id = ?
        UNION ALL
        SELECT 'enrollment' as type, CONCAT(u.full_name, ' enrolled in ', c.title) as name, sc.enrolled_at as date, sc.progress_percent as progress
        FROM student_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        INNER JOIN users u ON sc.student_id = u.id
        WHERE c.instructor_id = ?
        ORDER BY date DESC
        LIMIT 10
    ");
    $stmtRecent->execute([$instructor_id, $instructor_id, $instructor_id]);
    $recent_activity = $stmtRecent->fetchAll();

    // Get top performing courses
    $stmtTopCourses = $pdo->prepare("
        SELECT c.title, c.id,
               COUNT(sc.student_id) as enrollment_count,
               AVG(sc.progress_percent) as avg_progress
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 5
    ");
    $stmtTopCourses->execute([$instructor_id]);
    $top_courses = $stmtTopCourses->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in instructor profile: " . $e->getMessage());
    $user = [];
    $total_courses = 0;
    $total_students = 0;
    $total_contents = 0;
    $total_lessons = 0;
    $total_sales = 0;
    $total_earnings = 0;
    $recent_activity = [];
    $top_courses = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('instructor_profile') ?> | TaaBia</title>
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
                <p><?= __('instructor_space') ?></p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus"></i>
                    <?= __('new_course') ?>
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <?= __('add') ?> <?= __('lesson') ?>
                </a>
                <a href="attendance_management.php" class="instructor-nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <?= __('attendance') ?>
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    <?= __('my_students') ?>
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    <?= __('pending_submissions') ?>
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-coins"></i>
                    <?= __('my_earnings') ?>
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    <?= __('transactions') ?>
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= __('payouts') ?>
                </a>
                <a href="profile.php" class="instructor-nav-item active">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?>
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('instructor_profile') ?></h1>
                        <p><?= __('instructor_profile_description') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Overview -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6);">
                    <div style="display: flex; align-items: center; gap: var(--spacing-6); flex-wrap: wrap;">
                        <div style="
                            width: 120px; 
                            height: 120px; 
                            border-radius: 50%; 
                            overflow: hidden; 
                            border: 4px solid var(--primary-color);
                            background: var(--gray-100);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">
                            <?php if ($user['profile_image']): ?>
                                <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>" 
                                     alt="<?= __('profile_image') ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user" style="font-size: var(--font-size-3xl); color: var(--gray-400);"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div style="flex: 1; min-width: 300px;">
                            <h2 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-900);">
                                <?= htmlspecialchars($user['fullname']) ?>
                            </h2>
                            <p style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($user['email']) ?>
                            </p>
                            <p style="margin: 0; color: var(--gray-500); font-size: var(--font-size-sm);">
                                <i class="fas fa-calendar"></i>
                                <?= __('member_since') ?> <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </p>
                            
                            <div style="margin-top: var(--spacing-4); display: flex; gap: var(--spacing-3); flex-wrap: wrap;">
                                <a href="edit_profile.php" class="instructor-btn instructor-btn-primary">
                                    <i class="fas fa-edit"></i>
                                    <?= __('edit_profile') ?>
                                </a>
                                
                                <a href="change_password.php" class="instructor-btn instructor-btn-secondary">
                                    <i class="fas fa-key"></i>
                                    <?= __('change_password') ?>
                                </a>
                                
                                <a href="language_settings.php" class="instructor-btn instructor-btn-secondary">
                                    <i class="fas fa-globe"></i>
                                    <?= __('language') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="instructor-cards">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('published_courses') ?></div>
                    <div class="instructor-card-value"><?= $total_courses ?></div>
                    <div class="instructor-card-description"><?= __('courses') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('enrolled_students') ?></div>
                    <div class="instructor-card-value"><?= $total_students ?></div>
                    <div class="instructor-card-description"><?= __('students') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_earnings') ?></div>
                    <div class="instructor-card-value"><?= number_format($total_earnings, 2) ?> <?= __('currency') ?></div>
                    <div class="instructor-card-description"><?= __('total_earnings_desc') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_sales') ?></div>
                    <div class="instructor-card-value"><?= $total_sales ?></div>
                    <div class="instructor-card-description"><?= __('total_sales_desc') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-file-alt"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_contents') ?></div>
                    <div class="instructor-card-value"><?= $total_contents ?></div>
                    <div class="instructor-card-description"><?= __('total_contents_desc') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_lessons') ?></div>
                    <div class="instructor-card-value"><?= $total_lessons ?></div>
                    <div class="instructor-card-description"><?= __('total_lessons_desc') ?></div>
                </div>
            </div>

            <!-- Top Performing Courses -->
            <?php if (count($top_courses) > 0): ?>
            <div class="instructor-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-trophy"></i> <?= __('top_courses') ?>
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-4);">
                    <?php foreach ($top_courses as $course): ?>
                        <div style="
                            display: flex; 
                            align-items: center; 
                            justify-content: space-between; 
                            padding: var(--spacing-3) 0; 
                            border-bottom: 1px solid var(--gray-100);
                        ">
                            <div style="display: flex; align-items: center; gap: var(--spacing-3);">
                                <div style="
                                    width: 40px; 
                                    height: 40px; 
                                    border-radius: 50%; 
                                    display: flex; 
                                    align-items: center; 
                                    justify-content: center;
                                    background: var(--primary-color);
                                    color: var(--white);
                                ">
                                    <i class="fas fa-book"></i>
                                </div>
                                
                                <div>
                                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                        <?= htmlspecialchars($course['title']) ?>
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                        <?= $course['enrollment_count'] ?> <?= __('students') ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div style="text-align: right;">
                                <div style="font-weight: 600; color: var(--primary-color);">
                                    <?= round($course['avg_progress'] ?? 0) ?>%
                                </div>
                                <div class="instructor-progress" style="width: 100px; margin-top: var(--spacing-1);">
                                    <div class="instructor-progress-bar" style="width: <?= round($course['avg_progress'] ?? 0) ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Teaching Activity -->
            <div class="instructor-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-clock"></i> <?= __('recent_teaching_activity') ?>
                    </h3>
                </div>
                
                <?php if (count($recent_activity) > 0): ?>
                    <div style="padding: var(--spacing-4);">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div style="
                                display: flex; 
                                align-items: center; 
                                justify-content: space-between; 
                                padding: var(--spacing-3) 0; 
                                border-bottom: 1px solid var(--gray-100);
                            ">
                                <div style="display: flex; align-items: center; gap: var(--spacing-3);">
                                    <div style="
                                        width: 40px; 
                                        height: 40px; 
                                        border-radius: 50%; 
                                        display: flex; 
                                        align-items: center; 
                                        justify-content: center;
                                        background: <?= $activity['type'] == 'course' ? 'var(--primary-color)' : ($activity['type'] == 'lesson' ? 'var(--success-color)' : 'var(--warning-color)') ?>;
                                        color: var(--white);
                                    ">
                                        <i class="fas fa-<?= $activity['type'] == 'course' ? 'book' : ($activity['type'] == 'lesson' ? 'play-circle' : 'user-plus') ?>"></i>
                                    </div>
                                    
                                    <div>
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                            <?= htmlspecialchars($activity['name'] ?? 'Activity') ?>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                            <?= $activity['type'] == 'course' ? __('course_created') : ($activity['type'] == 'lesson' ? __('lesson_added') : __('student_enrolled')) ?>
                                            • <?= date('d/m/Y', strtotime($activity['date'])) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($activity['type'] == 'enrollment' && $activity['progress'] !== null): ?>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: var(--primary-color);">
                                            <?= (int)$activity['progress'] ?>%
                                        </div>
                                        <div class="instructor-progress" style="width: 100px; margin-top: var(--spacing-1);">
                                            <div class="instructor-progress-bar" style="width: <?= (int)$activity['progress'] ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="instructor-empty">
                        <div class="instructor-empty-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="instructor-empty-title"><?= __('no_teaching_activity') ?></div>
                        <div class="instructor-empty-description">
                            <?= __('no_teaching_activity_desc') ?>
                        </div>
                        <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            <?= __('create_first_course') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Account Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="edit_profile.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-user-edit"></i>
                    <?= __('edit_profile') ?>
                </a>
                
                <a href="change_password.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-key"></i>
                    <?= __('change_password') ?>
                </a>
                
                <a href="my_courses.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                
                <a href="add_course.php" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-plus"></i>
                    <?= __('new_course') ?>
                </a>
                
                <a href="../auth/logout.php" class="instructor-btn" style="background: var(--danger-color); color: var(--white);">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars on load
            const progressBars = document.querySelectorAll('.instructor-progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html> 