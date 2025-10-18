<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Initialize variables with default values
$total_courses = 0;
$total_products = 0;
$total_orders = 0;
$total_users = 0;
$total_events = 0;
$total_participants = 0;
$total_students = 0;
$total_instructors = 0;
$total_vendor = 0;
$total_revenue = 0;
$total_blog_posts = 0;

try {
    // Check if tables exist before querying
    $tables = ['courses', 'products', 'orders', 'users', 'events', 'event_registrations', 'transactions', 'blog_posts'];

    foreach ($tables as $table) {
        $table_exists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
        if ($table_exists) {
            switch ($table) {
                case 'courses':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'");
                    if ($stmt->execute()) {
                        $total_courses = $stmt->fetchColumn();
                    }
                    break;
                case 'products':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
                    if ($stmt->execute()) {
                        $total_products = $stmt->fetchColumn();
                    }
                    break;
                case 'orders':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
                    if ($stmt->execute()) {
                        $total_orders = $stmt->fetchColumn();
                    }
                    break;
                case 'users':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1");
                    if ($stmt->execute()) {
                        $total_users = $stmt->fetchColumn();
                    }
                    break;
                case 'events':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
                    if ($stmt->execute()) {
                        $total_events = $stmt->fetchColumn();
                    }
                    break;
                case 'event_registrations':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM event_registrations");
                    if ($stmt->execute()) {
                        $total_participants = $stmt->fetchColumn();
                    }
                    break;
                case 'transactions':
                    $stmt = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM transactions");
                    if ($stmt->execute()) {
                        $total_revenue = $stmt->fetchColumn();
                    }
                    break;
                case 'blog_posts':
                    $stmt = $pdo->query("SELECT COUNT(*) FROM blog_posts");
                    if ($stmt->execute()) {
                        $total_blog_posts = $stmt->fetchColumn();
                    }
                    break;
            }
        }
    }

    // Get user counts by role
    $user_roles = ['student', 'instructor', 'vendor'];
    foreach ($user_roles as $role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ? AND is_active = 1");
        if ($stmt->execute([$role])) {
            switch ($role) {
                case 'student':
                    $total_students = $stmt->fetchColumn();
                    break;
                case 'instructor':
                    $total_instructors = $stmt->fetchColumn();
                    break;
                case 'vendor':
                    $total_vendor = $stmt->fetchColumn();
                    break;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in admin/index.php: " . $e->getMessage());
}

// Get recent activities
$recent_activities = [];
try {
    // Get recent users
    $stmt = $pdo->query("SELECT 'user' as type, full_name as title, created_at as time FROM users ORDER BY created_at DESC LIMIT 3");
    $recent_users = $stmt->fetchAll();

    // Get recent courses
    $stmt = $pdo->query("SELECT 'course' as type, title, created_at as time FROM courses ORDER BY created_at DESC LIMIT 3");
    $recent_courses = $stmt->fetchAll();

    // Get recent orders
    $stmt = $pdo->query("SELECT 'order' as type, CONCAT('Commande #', id) as title, created_at as time FROM orders ORDER BY created_at DESC LIMIT 3");
    $recent_orders = $stmt->fetchAll();

    // Get recent events
    $stmt = $pdo->query("SELECT 'event' as type, title, created_at as time FROM events ORDER BY created_at DESC LIMIT 3");
    $recent_events = $stmt->fetchAll();

    // Get recent blog posts
    $stmt = $pdo->query("SELECT 'blog' as type, title, created_at as time FROM blog_posts ORDER BY created_at DESC LIMIT 3");
    $recent_blog_posts = $stmt->fetchAll();

    // Combine all activities and sort by time
    $all_activities = array_merge($recent_users, $recent_courses, $recent_orders, $recent_events, $recent_blog_posts);
    usort($all_activities, function ($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    $recent_activities = array_slice($all_activities, 0, 5);
} catch (PDOException $e) {
    error_log("Database error in admin/index.php activities: " . $e->getMessage());
}

// Get monthly revenue data for chart
$monthly_revenue = [];
try {
    $stmt = $pdo->query("
        SELECT 
            MONTH(created_at) as month,
            SUM(amount) as total
        FROM transactions 
        WHERE YEAR(created_at) = YEAR(CURDATE())
        GROUP BY MONTH(created_at)
        ORDER BY month
    ");
    $revenue_data = $stmt->fetchAll();

    // Initialize all months with 0
    for ($i = 1; $i <= 12; $i++) {
        $monthly_revenue[$i] = 0;
    }

    // Fill in actual data
    foreach ($revenue_data as $row) {
        $monthly_revenue[$row['month']] = (float)$row['total'];
    }
} catch (PDOException $e) {
    error_log("Database error in admin/index.php revenue: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('admin_dashboard') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary-color: #00796b;
            --primary-light: #00796b;
            --primary-dark: #000051;
            --secondary-color: #00bcd4;
            --accent-color: #ff5722;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;

            --text-primary: #212121;
            --text-secondary: #757575;
            --text-light: #bdbdbd;

            --bg-primary: #ffffff;
            --bg-secondary: #fafafa;
            --bg-tertiary: #f5f5f5;

            --border-color: #e0e0e0;
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 8px rgba(0, 0, 0, 0.12);
            --shadow-heavy: 0 8px 16px rgba(0, 0, 0, 0.15);

            --border-radius: 12px;
            --border-radius-sm: 6px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            z-index: 1000;
            transition: var(--transition);
            box-shadow: var(--shadow-heavy);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(45deg, #fff, #e3f2fd);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            opacity: 0.8;
            font-weight: 300;
        }

        .sidebar-nav {
            padding: 1.5rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            box-shadow: var(--shadow-light);
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--secondary-color);
            transform: scaleY(0);
            transition: var(--transition);
        }

        .nav-link:hover::before,
        .nav-link.active::before {
            transform: scaleY(1);
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: var(--transition);
        }

        .header {
            background: var(--bg-primary);
            padding: 1.5rem 2rem;
            box-shadow: var(--shadow-light);
            border-bottom: 1px solid var(--border-color);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }

        .user-menu:hover {
            background: var(--bg-tertiary);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Dashboard Content */
        .dashboard-content {
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            position: relative;
        }

        .stat-icon.courses {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
        }

        .stat-icon.products {
            background: linear-gradient(45deg, #2196f3, #42a5f5);
        }

        .stat-icon.orders {
            background: linear-gradient(45deg, #ff9800, #ffb74d);
        }

        .stat-icon.users {
            background: linear-gradient(45deg, #9c27b0, #ba68c8);
        }

        .stat-icon.events {
            background: linear-gradient(45deg, #f44336, #ef5350);
        }

        .stat-icon.participants {
            background: linear-gradient(45deg, #00bcd4, #26c6da);
        }

        .stat-icon.students {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
        }

        .stat-icon.instructors {
            background: linear-gradient(45deg, #ff5722, #ff7043);
        }

        .stat-icon.vendors {
            background: linear-gradient(45deg, #607d8b, #78909c);
        }

        .stat-icon.blog {
            background: linear-gradient(45deg, #e91e63, #f06292);
        }

        .stat-icon.revenue {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
        }

        .stat-info {
            flex: 1;
            margin-left: 1rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-change {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            margin-top: 0.5rem;
            display: inline-block;
        }

        .stat-change.positive {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
        }

        .stat-change.negative {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .chart-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Recent Activity */
        .activity-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
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
            margin-right: 1rem;
            font-size: 1rem;
            color: white;
        }

        .activity-content {
            flex: 1;
        }

        .activity-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .activity-time {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        /* Hamburger Menu Styles */
        .hamburger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .hamburger-line {
            width: 100%;
            height: 3px;
            background-color: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        /* Hamburger menu animation */
        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }

        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .hamburger-menu {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
                padding-left: 20px;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .stat-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .stat-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stat-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        .stat-card:nth-child(5) {
            animation-delay: 0.5s;
        }

        .stat-card:nth-child(6) {
            animation-delay: 0.6s;
        }

        .stat-card:nth-child(7) {
            animation-delay: 0.7s;
        }

        .stat-card:nth-child(8) {
            animation-delay: 0.8s;
        }

        .stat-card:nth-child(9) {
            animation-delay: 0.9s;
        }

        .stat-card:nth-child(10) {
            animation-delay: 1s;
        }

        /* Loading States */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-light);
        }

        /* Admin Language Switcher */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-medium);
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--bg-secondary);
        }

        .admin-language-item.active {
            background: var(--primary-color);
            color: white;
        }

        .language-flag {
            font-size: 16px;
        }

        .language-name {
            flex: 1;
            font-size: 14px;
        }

        .admin-language-item i {
            font-size: 12px;
            margin-left: auto;
        }

        /* Improved Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .activity-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            max-height: 400px;
            overflow-y: auto;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 0.875rem;
            color: white;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-title {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            line-height: 1.3;
            word-wrap: break-word;
        }

        .activity-time {
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Responsive improvements */
        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }

            .activity-card {
                max-height: 300px;
            }
        }

        @media (max-width: 768px) {
            .charts-section {
                gap: 1rem;
            }

            .activity-card {
                padding: 1rem;
                max-height: 250px;
            }

            .activity-item {
                padding: 0.5rem 0;
            }

            .activity-icon {
                width: 30px;
                height: 30px;
                font-size: 0.75rem;
                margin-right: 0.5rem;
            }

            .activity-title {
                font-size: 0.8rem;
            }

            .activity-time {
                font-size: 0.7rem;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2><?= __('admin_panel') ?></h2>
            <p><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></p>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link active">
                    <i class="fas fa-chart-line"></i>
                    <span><?= __('dashboard') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span><?= __('users') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span><?= __('courses') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="fas fa-box"></i>
                    <span><?= __('products') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span><?= __('orders') ?></span>
                </a>
            </div>

            <li class="nav-item">
                <a class="nav-link" href="communities.php">
                    <i class="fas fa-users"></i> Communities
                </a>
            </li>

            <div class="nav-item">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span><?= __('events') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span><?= __('messages') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="blog_posts.php" class="nav-link">
                    <i class="fas fa-newspaper"></i>
                    <span><?= __('blog_posts') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="transactions.php" class="nav-link">
                    <i class="fas fa-exchange-alt"></i>
                    <span><?= __('transactions') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="payout_requests.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span><?= __('payout_requests') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="earnings.php" class="nav-link">
                    <i class="fas fa-wallet"></i>
                    <span><?= __('earnings') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="payments.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span><?= __('payments') ?></span>
                </a>
            </div>

            <div class="nav-item">
                <a href="payment_stats.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span><?= __('statistics') ?></span>
                </a>
            </div>

            <div class="nav-item" style="margin-top: 2rem;">
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span><?= __('logout') ?></span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <!-- Hamburger Menu Button -->
                <button class="hamburger-menu" id="hamburgerMenu" aria-label="Toggle navigation menu">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>

                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <h1 class="page-title"><?= __('dashboard') ?></h1>

                    <div style="display: flex; align-items: center; gap: 20px;">
                        <!-- Language Switcher -->
                        <div class="admin-language-switcher">
                            <div class="admin-language-dropdown">
                                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                                    <i class="fas fa-globe"></i>
                                    <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="admin-language-menu" id="adminLanguageDropdown">
                                    <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                        <span class="language-flag">🇫🇷</span>
                                        <span class="language-name">Français</span>
                                        <?php if (getCurrentLanguage() == 'fr'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                        <span class="language-flag">🇬🇧</span>
                                        <span class="language-name">English</span>
                                        <?php if (getCurrentLanguage() == 'en'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="user-menu">
                            <div class="user-avatar">
                                <?php
                                $current_user = null;
                                try {
                                    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
                                    $stmt->execute([current_user_id()]);
                                    $current_user = $stmt->fetch();
                                } catch (PDOException $e) {
                                    error_log("Error fetching current user: " . $e->getMessage());
                                }
                                ?>
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;">
                                    <?= htmlspecialchars($current_user['full_name'] ?? 'Admin') ?>
                                </div>
                                <div style="font-size: 0.75rem; opacity: 0.7;">
                                    <?= htmlspecialchars($current_user['email'] ?? 'admin@taabia.com') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="dashboard-content">
            <!-- Statistics Grid -->
            <div class="stats-grid">






                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon events">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_events) ?></div>
                            <div class="stat-label"><?= __('total_events') ?></div>
                            <div class="stat-change positive">+3% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>



                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_students) ?></div>
                            <div class="stat-label"><?= __('total_students') ?></div>
                            <div class="stat-change positive">+7% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>



                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon blog">
                            <i class="fas fa-newspaper"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_blog_posts) ?></div>
                            <div class="stat-label"><?= __('total_blog_posts') ?></div>
                            <div class="stat-change positive">+10% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_products) ?></div>
                            <div class="stat-label"><?= __('total_products') ?></div>
                            <div class="stat-change positive">+8% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon courses">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_courses) ?></div>
                            <div class="stat-label"><?= __('total_courses') ?></div>
                            <div class="stat-change positive">+12% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">$<?= number_format($total_revenue, 2) ?></div>
                            <div class="stat-label"><?= __('total_revenue') ?></div>
                            <div class="stat-change positive">+25% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><?= __('revenue_evolution') ?></h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><?= __('recent_activity') ?></h3>
                    </div>

                    <?php if (empty($recent_activities)): ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <div class="activity-title"><?= __('no_recent_activity') ?></div>
                                <div class="activity-time"><?= __('no_data_available') ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: linear-gradient(45deg, 
                                    <?php
                                    switch ($activity['type']) {
                                        case 'user':
                                            echo '#4caf50, #66bb6a';
                                            break;
                                        case 'course':
                                            echo '#ff9800, #ffb74d';
                                            break;
                                        case 'order':
                                            echo '#2196f3, #42a5f5';
                                            break;
                                        case 'event':
                                            echo '#9c27b0, #ba68c8';
                                            break;
                                        case 'blog':
                                            echo '#e91e63, #f06292';
                                            break;
                                        default:
                                            echo '#607d8b, #78909c';
                                            break;
                                    }
                                    ?>);">
                                    <i class="fas <?php
                                                    switch ($activity['type']) {
                                                        case 'user':
                                                            echo 'fa-user-plus';
                                                            break;
                                                        case 'course':
                                                            echo 'fa-book';
                                                            break;
                                                        case 'order':
                                                            echo 'fa-shopping-cart';
                                                            break;
                                                        case 'event':
                                                            echo 'fa-calendar';
                                                            break;
                                                        case 'blog':
                                                            echo 'fa-newspaper';
                                                            break;
                                                        default:
                                                            echo 'fa-info-circle';
                                                            break;
                                                    }
                                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php
                                        switch ($activity['type']) {
                                            case 'user':
                                                echo __('new_user') . ': ' . htmlspecialchars($activity['title']);
                                                break;
                                            case 'course':
                                                echo __('new_course') . ': ' . htmlspecialchars($activity['title']);
                                                break;
                                            case 'order':
                                                echo htmlspecialchars($activity['title']);
                                                break;
                                            case 'event':
                                                echo __('new_event') . ': ' . htmlspecialchars($activity['title']);
                                                break;
                                            case 'blog':
                                                echo __('new_article') . ': ' . htmlspecialchars($activity['title']);
                                                break;
                                            default:
                                                echo htmlspecialchars($activity['title']);
                                                break;
                                        }
                                        ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= timeAgo($activity['time']) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['<?= __('jan') ?>', '<?= __('feb') ?>', '<?= __('mar') ?>', '<?= __('apr') ?>', '<?= __('may') ?>', '<?= __('jun') ?>', '<?= __('jul') ?>', '<?= __('aug') ?>', '<?= __('sep') ?>', '<?= __('oct') ?>', '<?= __('nov') ?>', '<?= __('dec') ?>'],
                datasets: [{
                    label: '<?= __('monthly_revenue') ?>',
                    data: [<?= implode(',', $monthly_revenue) ?>],
                    borderColor: '#1a237e',
                    backgroundColor: 'rgba(26, 35, 126, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#1a237e',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return 'GHS' + value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close admin language dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const switcher = document.querySelector('.admin-language-switcher');

            if (switcher && !switcher.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Add smooth scrolling and other interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add click effects to nav links
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });

        // Hamburger menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                hamburgerMenu.classList.toggle('active');
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');

                // Prevent body scroll when sidebar is open
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }

            function closeSidebar() {
                hamburgerMenu.classList.remove('active');
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            // Event listeners for hamburger menu
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            // Close sidebar when clicking on nav links (mobile)
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });

            // Close sidebar on window resize if screen becomes larger
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });

            // Keyboard navigation for hamburger menu
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSidebar();
                    }
                });
            }
        });
    </script>
</body>

</html>