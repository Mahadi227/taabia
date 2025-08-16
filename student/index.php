<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Enhanced statistics with better error handling
try {
    // Total enrolled courses
    $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ?");
    $stmt1->execute([$student_id]);
    $total_courses = $stmt1->fetchColumn();

    // Total orders placed
    $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
    $stmt2->execute([$student_id]);
    $total_orders = $stmt2->fetchColumn();

    // Average progress across all courses
    $stmt3 = $pdo->prepare("SELECT AVG(progress_percent) FROM student_courses WHERE student_id = ?");
    $stmt3->execute([$student_id]);
    $average_progress = round($stmt3->fetchColumn() ?? 0);

    // Total spent on courses
    $stmt4 = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE buyer_id = ? AND status = 'completed'");
    $stmt4->execute([$student_id]);
    $total_spent = $stmt4->fetchColumn() ?? 0;

    // Recent courses (last 5) with progress
    $stmt5 = $pdo->prepare("
        SELECT c.title, c.id, sc.enrolled_at, sc.progress_percent, sc.last_accessed,
               u.full_name as instructor_name,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as total_lessons,
               (SELECT COUNT(*) FROM student_lessons sl 
                JOIN lessons l ON sl.lesson_id = l.id 
                WHERE l.course_id = c.id AND sl.student_id = ?) as completed_lessons
        FROM student_courses sc 
        JOIN courses c ON sc.course_id = c.id 
        JOIN users u ON c.instructor_id = u.id
        WHERE sc.student_id = ? 
        ORDER BY sc.last_accessed DESC, sc.enrolled_at DESC 
        LIMIT 5
    ");
    $stmt5->execute([$student_id, $student_id]);
    $recent_courses = $stmt5->fetchAll();

    // Recent orders with product details
    $stmt6 = $pdo->prepare("
        SELECT o.*, p.name as product_name, p.description as product_description,
               CASE 
                   WHEN o.status = 'completed' THEN 'success'
                   WHEN o.status = 'pending' THEN 'warning'
                   WHEN o.status = 'cancelled' THEN 'danger'
                   ELSE 'info'
               END as status_class
        FROM orders o 
        LEFT JOIN products p ON o.product_id = p.id 
        WHERE o.buyer_id = ? 
        ORDER BY o.ordered_at DESC 
        LIMIT 5
    ");
    $stmt6->execute([$student_id]);
    $recent_orders = $stmt6->fetchAll();

    // Course recommendations based on enrolled courses
    $stmt7 = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.price, c.created_at,
               u.full_name as instructor_name,
               (SELECT COUNT(*) FROM student_courses WHERE course_id = c.id) as enrollment_count,
               (SELECT COUNT(*) FROM lessons WHERE course_id = c.id) as lesson_count
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.status = 'published' 
        AND c.is_active = 1
        AND c.id NOT IN (SELECT course_id FROM student_courses WHERE student_id = ?)
        AND c.instructor_id IN (
            SELECT DISTINCT c2.instructor_id 
            FROM student_courses sc2 
            JOIN courses c2 ON sc2.course_id = c2.id 
            WHERE sc2.student_id = ?
        )
        ORDER BY enrollment_count DESC, c.created_at DESC
        LIMIT 3
    ");
    $stmt7->execute([$student_id, $student_id]);
    $recommended_courses = $stmt7->fetchAll();

    // Learning streak (consecutive days of activity)
    $stmt8 = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(last_accessed)) as active_days
        FROM student_courses 
        WHERE student_id = ? 
        AND last_accessed >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt8->execute([$student_id]);
    $learning_streak = $stmt8->fetchColumn();

    // Unread messages count
    $stmt9 = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt9->execute([$student_id]);
    $unread_messages = $stmt9->fetchColumn();

} catch (PDOException $e) {
    error_log("Database error in student dashboard: " . $e->getMessage());
    $total_courses = 0;
    $total_orders = 0;
    $average_progress = 0;
    $total_spent = 0;
    $recent_courses = [];
    $recent_orders = [];
    $recommended_courses = [];
    $learning_streak = 0;
    $unread_messages = 0;
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?> <?= __('student_space') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <a href="index.php" class="student-nav-item active">
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
                <a href="attendance.php" class="student-nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <?= __('attendance') ?>
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?>
                </a>
                <a href="messages.php" class="student-nav-item <?= $unread_messages > 0 ? 'has-notification' : '' ?>">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?>
                    <?php if ($unread_messages > 0): ?>
                        <span class="notification-badge"><?= $unread_messages ?></span>
                    <?php endif; ?>
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
                        <h1><?= __('welcome') ?>, <?= htmlspecialchars($_SESSION['full_name'] ?? __('student_space')) ?> ! 👋</h1>
                        <p><?= __('dashboard') ?> - <?= __('profile_description') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="student-cards">
                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('enrolled_courses') ?></div>
                    <div class="student-card-value"><?= $total_courses ?></div>
                    <div class="student-card-description"><?= __('enrolled_courses_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('average_progress') ?></div>
                    <div class="student-card-value"><?= $average_progress ?>%</div>
                    <div class="student-card-description"><?= __('average_progress_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-fire"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('learning_streak') ?></div>
                    <div class="student-card-value"><?= $learning_streak ?></div>
                    <div class="student-card-description"><?= __('learning_streak_desc') ?></div>
                </div>

                <div class="student-card student-fade-in">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('total_spent') ?></div>
                    <div class="student-card-value"><?= number_format($total_spent, 2) ?> <?= __('currency') ?></div>
                    <div class="student-card-description"><?= __('total_spent_desc') ?></div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="student-grid" style="grid-template-columns: 2fr 1fr; gap: var(--spacing-6);">
                <!-- Recent Activity Section -->
                <div class="student-table-container">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-clock"></i> <?= __('recent_activity') ?>
                        </h3>
                    </div>
                    
                    <?php if (count($recent_courses) > 0): ?>
                        <div style="padding: var(--spacing-4);">
                            <?php foreach ($recent_courses as $course): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--spacing-3) 0; border-bottom: 1px solid var(--gray-100);">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                                            <a href="view_course.php?course_id=<?= $course['id'] ?>" style="color: inherit; text-decoration: none;">
                                                <?= htmlspecialchars($course['title']) ?>
                                            </a>
                                        </div>
                                        <div style="font-size: 0.875rem; color: var(--gray-600);">
                                            <?= __('by') ?> <?= htmlspecialchars($course['instructor_name']) ?>
                                        </div>
                                        <div style="margin-top: 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <div style="flex: 1;">
                                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                                        <span style="font-size: 0.875rem; color: var(--gray-600);"><?= __('progress') ?></span>
                                                        <span style="font-size: 0.875rem; font-weight: 500;"><?= $course['progress_percent'] ?>%</span>
                                                    </div>
                                                    <div style="background: var(--gray-200); height: 6px; border-radius: 3px; overflow: hidden;">
                                                        <div style="background: var(--primary-color); height: 100%; width: <?= $course['progress_percent'] ?>%; transition: width 0.3s ease;"></div>
                                                    </div>
                                                </div>
                                                <div style="text-align: center; min-width: 60px;">
                                                    <div style="font-size: 0.75rem; color: var(--gray-500);"><?= __('lessons') ?></div>
                                                    <div style="font-weight: 600; color: var(--gray-700);">
                                                        <?= $course['completed_lessons'] ?>/<?= $course['total_lessons'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-left: 1rem;">
                                        <a href="view_course.php?course_id=<?= $course['id'] ?>" class="student-btn student-btn-primary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                            <i class="fas fa-play"></i> <?= __('continue') ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="padding: var(--spacing-8); text-align: center; color: var(--gray-500);">
                            <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p><?= __('no_courses_enrolled') ?></p>
                            <a href="all_courses.php" class="student-btn student-btn-primary">
                                <i class="fas fa-search"></i> <?= __('discover_courses') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar Content -->
                <div style="display: flex; flex-direction: column; gap: var(--spacing-6);">
                    <!-- Recommended Courses -->
                    <?php if (count($recommended_courses) > 0): ?>
                        <div class="student-card">
                            <div style="padding: var(--spacing-4); border-bottom: 1px solid var(--gray-200);">
                                <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                                    <i class="fas fa-lightbulb"></i> <?= __('recommended_courses') ?>
                                </h3>
                            </div>
                            <div style="padding: var(--spacing-4);">
                                <?php foreach ($recommended_courses as $course): ?>
                                    <div style="padding: var(--spacing-3) 0; border-bottom: 1px solid var(--gray-100);">
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </div>
                                        <div style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                                            <?= __('by') ?> <?= htmlspecialchars($course['instructor_name']) ?>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="font-size: 0.875rem; color: var(--gray-600);">
                                                <i class="fas fa-users"></i> <?= $course['enrollment_count'] ?> <?= __('students') ?>
                                            </div>
                                            <a href="enroll.php?course_id=<?= $course['id'] ?>" class="student-btn student-btn-secondary" style="padding: 0.25rem 0.75rem; font-size: 0.75rem;">
                                                <?= $course['price'] > 0 ? number_format($course['price'], 2) . ' ' . __('currency') : __('free') ?>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Orders -->
                    <?php if (count($recent_orders) > 0): ?>
                        <div class="student-card">
                            <div style="padding: var(--spacing-4); border-bottom: 1px solid var(--gray-200);">
                                <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                                    <i class="fas fa-shopping-bag"></i> <?= __('recent_orders') ?>
                                </h3>
                            </div>
                            <div style="padding: var(--spacing-4);">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div style="padding: var(--spacing-3) 0; border-bottom: 1px solid var(--gray-100);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                                            <div style="font-weight: 600; color: var(--gray-900);">
                                                <?= htmlspecialchars($order['product_name'] ?? 'Order #' . $order['id']) ?>
                                            </div>
                                            <span class="student-badge student-badge-<?= $order['status_class'] ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                        </div>
                                        <div style="font-size: 0.875rem; color: var(--gray-600);">
                                            <?= number_format($order['total_amount'], 2) ?> <?= __('currency') ?>
                                        </div>
                                        <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                                            <?= date('M j, Y', strtotime($order['ordered_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars
            const progressBars = document.querySelectorAll('.student-card-value');
            progressBars.forEach(bar => {
                const value = parseInt(bar.textContent);
                if (!isNaN(value)) {
                    bar.style.opacity = '0';
                    setTimeout(() => {
                        bar.style.transition = 'opacity 0.5s ease';
                        bar.style.opacity = '1';
                    }, 200);
                }
            });

            // Add hover effects to course cards
            const courseItems = document.querySelectorAll('.student-table-container > div > div');
            courseItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'var(--gray-50)';
                    this.style.transform = 'translateX(5px)';
                });
                
                item.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = 'transparent';
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>
</body>
</html>