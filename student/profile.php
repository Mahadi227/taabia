<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

try {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../auth/logout.php');
        exit;
    }

    // Get user statistics
    $stmtCourses = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ?");
    $stmtCourses->execute([$student_id]);
    $total_courses = $stmtCourses->fetchColumn();

    $stmtOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE buyer_id = ?");
    $stmtOrders->execute([$student_id]);
    $total_orders = $stmtOrders->fetchColumn();

    $stmtProgress = $pdo->prepare("SELECT AVG(progress_percent) FROM student_courses WHERE student_id = ?");
    $stmtProgress->execute([$student_id]);
    $avg_progress = round($stmtProgress->fetchColumn() ?? 0);

    $stmtSpent = $pdo->prepare("SELECT SUM(total_amount) FROM orders WHERE buyer_id = ? AND status = 'completed'");
    $stmtSpent->execute([$student_id]);
    $total_spent = $stmtSpent->fetchColumn() ?? 0;

    // Get recent activity
    $stmtRecent = $pdo->prepare("
        SELECT 'course' as type, c.title as name, sc.enrolled_at as date, sc.progress_percent as progress
        FROM student_courses sc
        JOIN courses c ON sc.course_id = c.id
        WHERE sc.student_id = ?
        UNION ALL
        SELECT 'order' as type, p.name as name, o.ordered_at as date, NULL as progress
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.buyer_id = ?
        ORDER BY date DESC
        LIMIT 5
    ");
    $stmtRecent->execute([$student_id, $student_id]);
    $recent_activity = $stmtRecent->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in profile: " . $e->getMessage());
    $user = [];
    $total_courses = 0;
    $total_orders = 0;
    $avg_progress = 0;
    $total_spent = 0;
    $recent_activity = [];
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_profile_title') ?> | TaaBia</title>
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
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?>
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?>
                </a>
                <a href="profile.php" class="student-nav-item active">
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
                        <h1><?= __('my_profile_title') ?></h1>
                        <p><?= __('profile_description') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Overview -->
            <div class="student-table-container" style="margin-bottom: var(--spacing-6);">
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
                          <?php if (!empty($user['profile_image'])): ?>
    <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>" 
         alt="<?= __('profile_image') ?>" 
         style="width: 100%; height: 100%; object-fit: cover;">
<?php else: ?>
    <i class="fas fa-user" style="font-size: var(--font-size-3xl); color: var(--gray-400);"></i>
<?php endif; ?>

                        </div>
                        
                        <div style="flex: 1; min-width: 300px;">
                            <h2 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-900);">
                            <?= htmlspecialchars($user['fullname'] ?? 'Nom inconnu') ?>
                            </h2>
                            <p style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($user['email'] ?? 'Email inconnu') ?>                            </p>
                            <p style="margin: 0; color: var(--gray-500); font-size: var(--font-size-sm);">
                                <i class="fas fa-calendar"></i>
                                <?= isset($user['created_at']) ? date('d/m/Y', strtotime($users['created_at'])) : 'Date inconnue' ?>                            </p>
                            
                            <div style="margin-top: var(--spacing-4); display: flex; gap: var(--spacing-3); flex-wrap: wrap;">
                                <a href="edit_profile.php" class="student-btn student-btn-primary">
                                    <i class="fas fa-edit"></i>
                                    <?= __('edit_profile') ?>
                                </a>
                                
                                <a href="change_password.php" class="student-btn student-btn-secondary">
                                    <i class="fas fa-key"></i>
                                    <?= __('change_password') ?>
                                </a>
                                
                                <a href="language_settings.php" class="student-btn student-btn-secondary">
                                    <i class="fas fa-globe"></i>
                                    <?= __('language') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="student-cards">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('enrolled_courses') ?></div>
                    <div class="student-card-value"><?= $total_courses ?></div>
                    <div class="student-card-description"><?= __('enrolled_courses_desc') ?></div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('average_progress') ?></div>
                    <div class="student-card-value"><?= $avg_progress ?>%</div>
                    <div class="student-card-description"><?= __('average_progress_desc') ?></div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                    <div class="student-card-title"><?= __('orders') ?></div>
                    <div class="student-card-value"><?= $total_orders ?></div>
                    <div class="student-card-description"><?= __('orders_desc') ?></div>
                </div>

                <div class="student-card">
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

            <!-- Recent Activity -->
            <div class="student-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-clock"></i> <?= __('recent_activity') ?>
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
                                        background: <?= $activity['type'] == 'course' ? 'var(--success-color)' : 'var(--primary-color)' ?>;
                                        color: var(--white);
                                    ">
                                        <i class="fas fa-<?= $activity['type'] == 'course' ? 'book' : 'shopping-cart' ?>"></i>
                                    </div>
                                    
                                    <div>
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                            <?= htmlspecialchars($activity['name'] ?? 'Activité') ?>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                            <?= $activity['type'] == 'course' ? __('course_enrollment') : __('purchase_made') ?>
                                            • <?= date('d/m/Y', strtotime($activity['date'])) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($activity['type'] == 'course' && $activity['progress'] !== null): ?>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: var(--primary-color);">
                                            <?= (int)$activity['progress'] ?>%
                                        </div>
                                        <div class="student-progress" style="width: 100px; margin-top: var(--spacing-1);">
                                            <div class="student-progress-bar" style="width: <?= (int)$activity['progress'] ?>%;"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="student-empty">
                        <div class="student-empty-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="student-empty-title"><?= __('no_recent_activity') ?></div>
                        <div class="student-empty-description">
                            <?= __('no_activity_desc') ?>
                        </div>
                        <a href="all_courses.php" class="student-btn student-btn-primary">
                            <i class="fas fa-book-open"></i>
                            <?= __('explore_courses') ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Account Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="edit_profile.php" class="student-btn student-btn-primary">
                    <i class="fas fa-user-edit"></i>
                    <?= __('edit_profile') ?>
                </a>
                
                <a href="change_password.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-key"></i>
                    <?= __('change_password') ?>
                </a>
                
                <a href="my_courses.php" class="student-btn student-btn-success">
                    <i class="fas fa-graduation-cap"></i>
                    <?= __('my_courses') ?>
                </a>
                
                <a href="../auth/logout.php" class="student-btn" style="background: var(--danger-color); color: var(--white);">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars on load
            const progressBars = document.querySelectorAll('.student-progress-bar');
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
