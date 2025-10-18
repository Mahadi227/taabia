<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: users.php');
    exit();
}

// Get user details
$user_query = "
    SELECT u.*, 
           COUNT(DISTINCT sc.course_id) as enrolled_courses,
           COUNT(DISTINCT c.id) as created_courses,
           COUNT(DISTINCT o.id) as total_orders,
           SUM(o.total_amount) as total_spent
    FROM users u
    LEFT JOIN student_courses sc ON u.id = sc.student_id
    LEFT JOIN courses c ON u.id = c.instructor_id
    LEFT JOIN orders o ON u.id = o.buyer_id
    WHERE u.id = ?
    GROUP BY u.id
";

$user_stmt = $pdo->prepare($user_query);
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit();
}

// Get user activity data (simplified to use only existing tables)
$activity_query = "
    SELECT 
        'course_enrollment' as activity_type,
        sc.enrolled_at as activity_date,
        c.title as activity_title,
        c.id as related_id,
        'enrolled in course' as description,
        NULL as additional_data
    FROM student_courses sc
    INNER JOIN courses c ON sc.course_id = c.id
    WHERE sc.student_id = ?
    
    UNION ALL
    
    SELECT 
        'course_completion' as activity_type,
        sc.enrolled_at as activity_date,
        c.title as activity_title,
        c.id as related_id,
        'completed course' as description,
        CONCAT('Progress: ', sc.progress_percent, '%') as additional_data
    FROM student_courses sc
    INNER JOIN courses c ON sc.course_id = c.id
    WHERE sc.student_id = ? AND sc.progress_percent >= 100
    
    UNION ALL
    
    SELECT 
        'order_placed' as activity_type,
        o.created_at as activity_date,
        CONCAT('Order #', o.id) as activity_title,
        o.id as related_id,
        'placed order' as description,
        CONCAT('Amount: $', o.total_amount) as additional_data
    FROM orders o
    WHERE o.buyer_id = ?
    
    UNION ALL
    
    SELECT 
        'certificate_earned' as activity_type,
        cc.issue_date as activity_date,
        c.title as activity_title,
        cc.id as related_id,
        'earned certificate' as description,
        CONCAT('Grade: ', cc.final_grade, '%') as additional_data
    FROM course_certificates cc
    INNER JOIN courses c ON cc.course_id = c.id
    WHERE cc.student_id = ?
    
    ORDER BY activity_date DESC
    LIMIT 50
";

$activity_stmt = $pdo->prepare($activity_query);
$activity_stmt->execute([$user_id, $user_id, $user_id, $user_id]);
$activities = $activity_stmt->fetchAll();

// Get user's enrolled courses
$enrolled_courses_query = "
    SELECT c.*, sc.enrolled_at, sc.progress_percent
    FROM student_courses sc
    INNER JOIN courses c ON sc.course_id = c.id
    WHERE sc.student_id = ?
    ORDER BY sc.enrolled_at DESC
";

$enrolled_stmt = $pdo->prepare($enrolled_courses_query);
$enrolled_stmt->execute([$user_id]);
$enrolled_courses = $enrolled_stmt->fetchAll();

// Get user's created courses (if instructor)
$created_courses_query = "
    SELECT c.*, 
           COUNT(DISTINCT sc.student_id) as enrolled_students,
           COUNT(DISTINCT l.id) as total_lessons
    FROM courses c
    LEFT JOIN student_courses sc ON c.id = sc.course_id
    LEFT JOIN lessons l ON c.id = l.course_id
    WHERE c.instructor_id = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
";

$created_stmt = $pdo->prepare($created_courses_query);
$created_stmt->execute([$user_id]);
$created_courses = $created_stmt->fetchAll();

// Get user's orders
$orders_query = "
    SELECT o.*, 
           COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.buyer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
    LIMIT 10
";

$orders_stmt = $pdo->prepare($orders_query);
$orders_stmt->execute([$user_id]);
$orders = $orders_stmt->fetchAll();

// Get user's certificates
$certificates_query = "
    SELECT cc.*, c.title as course_title
    FROM course_certificates cc
    INNER JOIN courses c ON cc.course_id = c.id
    WHERE cc.student_id = ?
    ORDER BY cc.issue_date DESC
";

$certificates_stmt = $pdo->prepare($certificates_query);
$certificates_stmt->execute([$user_id]);
$certificates = $certificates_stmt->fetchAll();

// Get user statistics (simplified to use only existing tables)
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM student_courses WHERE student_id = ?) as total_enrolled,
        (SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND progress_percent >= 100) as completed_courses,
        0 as completed_lessons,
        (SELECT COUNT(*) FROM course_certificates WHERE student_id = ?) as earned_certificates,
        0 as submitted_assignments,
        0 as completed_quizzes,
        0 as average_quiz_score,
        (SELECT COUNT(*) FROM orders WHERE buyer_id = ?) as total_orders,
        (SELECT SUM(total_amount) FROM orders WHERE buyer_id = ?) as total_spent
";

$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
$stats = $stats_stmt->fetch();

// Get role-specific data
$role_specific_data = [];

if ($user['role'] === 'admin') {
    // Admin-specific statistics
    $admin_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM courses WHERE status = 'published') as total_courses,
            (SELECT COUNT(*) FROM products WHERE is_active = 1) as total_products,
            (SELECT COUNT(*) FROM orders) as total_orders,
            (SELECT SUM(total_amount) FROM orders WHERE status = 'paid') as total_revenue,
            (SELECT COUNT(*) FROM course_certificates) as total_certificates,
            (SELECT COUNT(*) FROM blog_posts WHERE status = 'published') as total_blog_posts,
            (SELECT COUNT(*) FROM events WHERE status = 'active') as total_events
    ";
    $admin_stats_stmt = $pdo->prepare($admin_stats_query);
    $admin_stats_stmt->execute();
    $role_specific_data = $admin_stats_stmt->fetch();
} elseif ($user['role'] === 'vendor') {
    // Vendor-specific data
    $vendor_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM products WHERE vendor_id = ?) as total_products,
            (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND status = 'active') as active_products,
            (SELECT COUNT(*) FROM products WHERE vendor_id = ? AND stock > 0) as in_stock_products,
            (SELECT SUM(stock) FROM products WHERE vendor_id = ?) as total_stock,
            (SELECT AVG(price) FROM products WHERE vendor_id = ?) as avg_product_price,
            (SELECT SUM(earning_amount) FROM vendor_earnings WHERE vendor_id = ? AND status = 'paid') as total_earnings,
            (SELECT SUM(earning_amount) FROM vendor_earnings WHERE vendor_id = ? AND status = 'available') as available_earnings,
            (SELECT SUM(earning_amount) FROM vendor_earnings WHERE vendor_id = ? AND status = 'pending') as pending_earnings
    ";
    $vendor_stats_stmt = $pdo->prepare($vendor_stats_query);
    $vendor_stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $role_specific_data = $vendor_stats_stmt->fetch();

    // Get vendor's products
    $vendor_products_query = "
        SELECT p.*, 
               COUNT(oi.id) as total_orders,
               SUM(oi.quantity) as total_sold
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.vendor_id = ?
        GROUP BY p.id
        ORDER BY p.created_at DESC
        LIMIT 10
    ";
    $vendor_products_stmt = $pdo->prepare($vendor_products_query);
    $vendor_products_stmt->execute([$user_id]);
    $vendor_products = $vendor_products_stmt->fetchAll();

    // Get vendor's recent earnings
    $vendor_earnings_query = "
        SELECT ve.*, p.name as product_name, o.id as order_id
        FROM vendor_earnings ve
        LEFT JOIN products p ON ve.product_id = p.id
        LEFT JOIN orders o ON ve.order_id = o.id
        WHERE ve.vendor_id = ?
        ORDER BY ve.created_at DESC
        LIMIT 10
    ";
    $vendor_earnings_stmt = $pdo->prepare($vendor_earnings_query);
    $vendor_earnings_stmt->execute([$user_id]);
    $vendor_earnings = $vendor_earnings_stmt->fetchAll();
} elseif ($user['role'] === 'instructor') {
    // Instructor-specific data
    $instructor_stats_query = "
        SELECT 
            (SELECT COUNT(*) FROM courses WHERE instructor_id = ?) as total_courses,
            (SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'published') as published_courses,
            (SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'draft') as draft_courses,
            (SELECT COUNT(DISTINCT sc.student_id) FROM student_courses sc 
             INNER JOIN courses c ON sc.course_id = c.id WHERE c.instructor_id = ?) as total_students,
            (SELECT SUM(earning_amount) FROM instructor_earnings WHERE instructor_id = ? AND status = 'paid') as total_earnings,
            (SELECT SUM(earning_amount) FROM instructor_earnings WHERE instructor_id = ? AND status = 'available') as available_earnings,
            (SELECT SUM(earning_amount) FROM instructor_earnings WHERE instructor_id = ? AND status = 'pending') as pending_earnings,
            0 as avg_rating
    ";
    $instructor_stats_stmt = $pdo->prepare($instructor_stats_query);
    $instructor_stats_stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
    $role_specific_data = $instructor_stats_stmt->fetch();

    // Get instructor's recent earnings
    $instructor_earnings_query = "
        SELECT ie.*, c.title as course_title, o.id as order_id
        FROM instructor_earnings ie
        LEFT JOIN courses c ON ie.course_id = c.id
        LEFT JOIN orders o ON ie.order_id = o.id
        WHERE ie.instructor_id = ?
        ORDER BY ie.created_at DESC
        LIMIT 10
    ";
    $instructor_earnings_stmt = $pdo->prepare($instructor_earnings_query);
    $instructor_earnings_stmt->execute([$user_id]);
    $instructor_earnings = $instructor_earnings_stmt->fetchAll();
}

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_profile') ?> - <?= htmlspecialchars($user['full_name']) ?> | <?= __('admin_panel') ?></title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .profile-info {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            align-items: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.2);
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }

        .profile-details h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
            font-weight: 600;
        }

        .profile-meta {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.875rem;
            opacity: 0.8;
        }

        .meta-value {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .profile-actions {
            display: flex;
            gap: 1rem;
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
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.courses {
            background: #667eea;
        }

        .stat-icon.completed {
            background: #38a169;
        }

        .stat-icon.certificates {
            background: #ed8936;
        }

        .stat-icon.orders {
            background: #3182ce;
        }

        .stat-icon.lessons {
            background: #805ad5;
        }

        .stat-icon.quizzes {
            background: #e53e3e;
        }

        .stat-icon.assignments {
            background: #d69e2e;
        }

        .stat-icon.spent {
            background: #319795;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .content-section {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        .section-title {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-content {
            padding: 1.5rem;
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            flex-shrink: 0;
        }

        .activity-icon.enrollment {
            background: #667eea;
        }

        .activity-icon.completion {
            background: #38a169;
        }

        .activity-icon.order {
            background: #3182ce;
        }

        .activity-icon.lesson {
            background: #805ad5;
        }

        .activity-icon.certificate {
            background: #ed8936;
        }

        .activity-icon.assignment {
            background: #d69e2e;
        }

        .activity-icon.quiz {
            background: #e53e3e;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }

        .activity-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .activity-meta {
            font-size: 0.8rem;
            color: #999;
        }

        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-info h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
        }

        .course-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .progress-bar {
            width: 100px;
            height: 8px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: #38a169;
            transition: width 0.3s ease;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-info h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
        }

        .order-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .certificate-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .certificate-item:last-child {
            border-bottom: none;
        }

        .certificate-info h4 {
            margin: 0 0 0.25rem 0;
            color: #333;
        }

        .certificate-meta {
            font-size: 0.9rem;
            color: #666;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border: 1px solid #667eea;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .back-link:hover {
            background: #667eea;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-admin {
            background: #f8d7da;
            color: #721c24;
        }

        .role-instructor {
            background: #fff3cd;
            color: #856404;
        }

        .role-student {
            background: #d4edda;
            color: #155724;
        }

        .role-vendor {
            background: #d1ecf1;
            color: #0c5460;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .profile-info {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .profile-meta {
                justify-content: center;
            }

            .profile-actions {
                justify-content: center;
            }
        }

        /* Role-specific stat card colors */
        .stat-icon.users {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.products {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.revenue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon.blog {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .stat-icon.events {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-icon.active {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }

        .stat-icon.stock {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }

        .stat-icon.inventory {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .stat-icon.price {
            background: linear-gradient(135deg, #ff8a80 0%, #ff80ab 100%);
        }

        .stat-icon.earnings {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }

        .stat-icon.available {
            background: linear-gradient(135deg, #a8c0ff 0%, #3f2b96 100%);
        }

        .stat-icon.pending {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
        }

        .stat-icon.published {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.draft {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.rating {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="profile-container">
                <a href="users.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_users') ?>
                </a>

                <!-- Profile Header -->
                <div class="profile-header">
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>

                        <div class="profile-details">
                            <h1><?= htmlspecialchars($user['full_name']) ?></h1>
                            <p style="margin: 0; opacity: 0.9;"><?= htmlspecialchars($user['email']) ?></p>

                            <div class="profile-meta">
                                <div class="meta-item">
                                    <span class="meta-label"><?= __('user_id') ?></span>
                                    <span class="meta-value">#<?= $user['id'] ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label"><?= __('role') ?></span>
                                    <span class="meta-value">
                                        <span class="role-badge role-<?= $user['role'] ?>"><?= ucfirst($user['role']) ?></span>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label"><?= __('status') ?></span>
                                    <span class="meta-value">
                                        <span class="status-badge status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                                            <?= $user['is_active'] ? __('active') : __('inactive') ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label"><?= __('member_since') ?></span>
                                    <span class="meta-value"><?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-actions">
                            <a href="user_edit.php?id=<?= $user['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> <?= __('edit_profile') ?>
                            </a>
                            <a href="user_toggle.php?id=<?= $user['id'] ?>&action=<?= $user['is_active'] ? 'deactivate' : 'activate' ?>"
                                class="btn <?= $user['is_active'] ? 'btn-warning' : 'btn-success' ?>"
                                onclick="return confirm('<?= $user['is_active'] ? __('confirm_deactivate') : __('confirm_activate') ?>')">
                                <i class="fas <?= $user['is_active'] ? 'fa-ban' : 'fa-check' ?>"></i>
                                <?= $user['is_active'] ? __('deactivate') : __('activate') ?>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <?php if ($user['role'] === 'admin'): ?>
                        <!-- Admin Statistics -->
                        <div class="stat-card">
                            <div class="stat-icon users">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_users'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_users') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon courses">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_courses'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_courses') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon products">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_products'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_products') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon orders">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_orders'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_orders') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon revenue">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['total_revenue'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('total_revenue') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon certificates">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_certificates'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_certificates') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon blog">
                                <i class="fas fa-newspaper"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_blog_posts'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('blog_posts') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon events">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_events'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_events') ?></div>
                        </div>

                    <?php elseif ($user['role'] === 'vendor'): ?>
                        <!-- Vendor Statistics -->
                        <div class="stat-card">
                            <div class="stat-icon products">
                                <i class="fas fa-box"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_products'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_products') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon active">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['active_products'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('active_products') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon stock">
                                <i class="fas fa-warehouse"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['in_stock_products'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('in_stock_products') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon inventory">
                                <i class="fas fa-boxes"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_stock'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_stock') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon price">
                                <i class="fas fa-tag"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['avg_product_price'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('avg_product_price') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon earnings">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['total_earnings'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('total_earnings') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon available">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['available_earnings'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('available_earnings') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['pending_earnings'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('pending_earnings') ?></div>
                        </div>

                    <?php elseif ($user['role'] === 'instructor'): ?>
                        <!-- Instructor Statistics -->
                        <div class="stat-card">
                            <div class="stat-icon courses">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_courses'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_courses') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon published">
                                <i class="fas fa-eye"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['published_courses'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('published_courses') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon draft">
                                <i class="fas fa-edit"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['draft_courses'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('draft_courses') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon students">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['total_students'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('total_students') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon earnings">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['total_earnings'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('total_earnings') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon available">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['available_earnings'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('available_earnings') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon pending">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($role_specific_data['pending_earnings'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('pending_earnings') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon rating">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="stat-number"><?= number_format($role_specific_data['avg_rating'] ?? 0, 1) ?></div>
                            <div class="stat-label"><?= __('avg_rating') ?></div>
                        </div>

                    <?php else: ?>
                        <!-- Student Statistics (default) -->
                        <div class="stat-card">
                            <div class="stat-icon courses">
                                <i class="fas fa-book"></i>
                            </div>
                            <div class="stat-number"><?= $stats['total_enrolled'] ?></div>
                            <div class="stat-label"><?= __('enrolled_courses') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon completed">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?= $stats['completed_courses'] ?></div>
                            <div class="stat-label"><?= __('completed_courses') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon lessons">
                                <i class="fas fa-play-circle"></i>
                            </div>
                            <div class="stat-number"><?= $stats['completed_lessons'] ?></div>
                            <div class="stat-label"><?= __('completed_lessons') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon certificates">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <div class="stat-number"><?= $stats['earned_certificates'] ?></div>
                            <div class="stat-label"><?= __('earned_certificates') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon assignments">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <div class="stat-number"><?= $stats['submitted_assignments'] ?></div>
                            <div class="stat-label"><?= __('submitted_assignments') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon quizzes">
                                <i class="fas fa-question-circle"></i>
                            </div>
                            <div class="stat-number"><?= $stats['completed_quizzes'] ?></div>
                            <div class="stat-label"><?= __('completed_quizzes') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon orders">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-number"><?= $stats['total_orders'] ?></div>
                            <div class="stat-label"><?= __('total_orders') ?></div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon spent">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?= number_format($stats['total_spent'] ?? 0, 2) ?></div>
                            <div class="stat-label"><?= __('total_spent') ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Content Grid -->
                <div class="content-grid">
                    <!-- Recent Activity -->
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-history"></i>
                                <?= __('recent_activity') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php if (empty($activities)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-history"></i>
                                    <p><?= __('no_recent_activity') ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $activity['activity_type'] ?>">
                                            <?php
                                            $icons = [
                                                'course_enrollment' => 'fa-book',
                                                'course_completion' => 'fa-check-circle',
                                                'order_placed' => 'fa-shopping-cart',
                                                'lesson_completion' => 'fa-play-circle',
                                                'certificate_earned' => 'fa-certificate',
                                                'assignment_submission' => 'fa-tasks',
                                                'quiz_attempt' => 'fa-question-circle'
                                            ];
                                            echo '<i class="fas ' . ($icons[$activity['activity_type']] ?? 'fa-circle') . '"></i>';
                                            ?>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title"><?= htmlspecialchars($activity['activity_title']) ?></div>
                                            <div class="activity-description"><?= htmlspecialchars($activity['description']) ?></div>
                                            <div class="activity-meta">
                                                <?= date('M j, Y g:i A', strtotime($activity['activity_date'])) ?>
                                                <?php if ($activity['additional_data']): ?>
                                                    • <?= htmlspecialchars($activity['additional_data']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Enrolled Courses -->
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-book"></i>
                                <?= __('enrolled_courses') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php if (empty($enrolled_courses)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-book"></i>
                                    <p><?= __('no_enrolled_courses') ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($enrolled_courses as $course): ?>
                                    <div class="course-item">
                                        <div class="course-info">
                                            <h4><?= htmlspecialchars($course['title']) ?></h4>
                                            <div class="course-meta">
                                                <?= __('enrolled') ?>: <?= date('M j, Y', strtotime($course['enrolled_at'])) ?>
                                                • <?= __('progress') ?>: <?= $course['progress_percent'] ?>%
                                            </div>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= $course['progress_percent'] ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Second Row -->
                <div class="content-grid">
                    <!-- Recent Orders -->
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-shopping-cart"></i>
                                <?= __('recent_orders') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php if (empty($orders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p><?= __('no_orders_found') ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-info">
                                            <h4><?= __('order') ?> #<?= $order['id'] ?></h4>
                                            <div class="order-meta">
                                                <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                                • <?= $order['item_count'] ?> <?= __('items') ?>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge badge-<?= $order['status'] === 'completed' ? 'success' : ($order['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                <?= ucfirst($order['status']) ?>
                                            </span>
                                            <div style="font-weight: 600; color: #333; margin-top: 0.25rem;">
                                                $<?= number_format($order['total_amount'], 2) ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Earned Certificates -->
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-certificate"></i>
                                <?= __('earned_certificates') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php if (empty($certificates)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-certificate"></i>
                                    <p><?= __('no_certificates_earned') ?></p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($certificates as $certificate): ?>
                                    <div class="certificate-item">
                                        <div class="certificate-info">
                                            <h4><?= htmlspecialchars($certificate['course_title']) ?></h4>
                                            <div class="certificate-meta">
                                                <?= __('issued') ?>: <?= date('M j, Y', strtotime($certificate['issue_date'])) ?>
                                                <?php if ($certificate['final_grade']): ?>
                                                    • <?= __('grade') ?>: <?= round($certificate['final_grade'], 1) ?>%
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="badge badge-info"><?= htmlspecialchars($certificate['certificate_number']) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Created Courses (for instructors) -->
                <?php if (!empty($created_courses) && $user['role'] === 'instructor'): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <?= __('created_courses') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php foreach ($created_courses as $course): ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <h4><?= htmlspecialchars($course['title']) ?></h4>
                                        <div class="course-meta">
                                            <?= __('created') ?>: <?= date('M j, Y', strtotime($course['created_at'])) ?>
                                            • <?= $course['total_lessons'] ?> <?= __('lessons') ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?= $course['status'] === 'published' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($course['status']) ?>
                                        </span>
                                        <div style="font-size: 0.9rem; color: #666; margin-top: 0.25rem;">
                                            <?= $course['enrolled_students'] ?> <?= __('students') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Vendor Products Section -->
                <?php if ($user['role'] === 'vendor' && !empty($vendor_products)): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-box"></i>
                                <?= __('vendor_products') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php foreach ($vendor_products as $product): ?>
                                <div class="course-item">
                                    <div class="course-info">
                                        <h4><?= htmlspecialchars($product['name']) ?></h4>
                                        <div class="course-meta">
                                            <?= __('created') ?>: <?= date('M j, Y', strtotime($product['created_at'])) ?>
                                            • <?= __('stock') ?>: <?= $product['stock'] ?> <?= __('units') ?>
                                            • <?= __('sold') ?>: <?= $product['total_sold'] ?? 0 ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?= $product['status'] === 'active' ? 'success' : 'warning' ?>">
                                            <?= ucfirst($product['status']) ?>
                                        </span>
                                        <div style="font-size: 0.9rem; color: #666; margin-top: 0.25rem;">
                                            $<?= number_format($product['price'], 2) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Vendor Earnings Section -->
                <?php if ($user['role'] === 'vendor' && !empty($vendor_earnings)): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-dollar-sign"></i>
                                <?= __('vendor_earnings') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php foreach ($vendor_earnings as $earning): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <h4><?= $earning['product_name'] ? htmlspecialchars($earning['product_name']) : __('general_earning') ?></h4>
                                        <div class="order-meta">
                                            <?= date('M j, Y g:i A', strtotime($earning['created_at'])) ?>
                                            <?php if ($earning['order_id']): ?>
                                                • <?= __('order') ?> #<?= $earning['order_id'] ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?= $earning['status'] === 'paid' ? 'success' : ($earning['status'] === 'available' ? 'info' : 'warning') ?>">
                                            <?= ucfirst($earning['status']) ?>
                                        </span>
                                        <div style="font-weight: 600; color: #333; margin-top: 0.25rem;">
                                            $<?= number_format($earning['earning_amount'], 2) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Instructor Earnings Section -->
                <?php if ($user['role'] === 'instructor' && !empty($instructor_earnings)): ?>
                    <div class="content-section">
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-dollar-sign"></i>
                                <?= __('instructor_earnings') ?>
                            </h3>
                        </div>
                        <div class="section-content">
                            <?php foreach ($instructor_earnings as $earning): ?>
                                <div class="order-item">
                                    <div class="order-info">
                                        <h4><?= $earning['course_title'] ? htmlspecialchars($earning['course_title']) : __('general_earning') ?></h4>
                                        <div class="order-meta">
                                            <?= date('M j, Y g:i A', strtotime($earning['created_at'])) ?>
                                            <?php if ($earning['order_id']): ?>
                                                • <?= __('order') ?> #<?= $earning['order_id'] ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="badge badge-<?= $earning['status'] === 'paid' ? 'success' : ($earning['status'] === 'available' ? 'info' : 'warning') ?>">
                                            <?= ucfirst($earning['status']) ?>
                                        </span>
                                        <div style="font-weight: 600; color: #333; margin-top: 0.25rem;">
                                            $<?= number_format($earning['earning_amount'], 2) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>