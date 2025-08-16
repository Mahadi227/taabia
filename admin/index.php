<?php
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
    usort($all_activities, function($a, $b) {
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
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaaBia Admin - <?= __('dashboard') ?></title>
    
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
            --shadow-light: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 8px rgba(0,0,0,0.12);
            --shadow-heavy: 0 8px 16px rgba(0,0,0,0.15);
            
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }

        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(4px);
        }

        .nav-link.active {
            background: rgba(255,255,255,0.15);
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

        .stat-icon.courses { background: linear-gradient(45deg, #4caf50, #66bb6a); }
        .stat-icon.products { background: linear-gradient(45deg, #2196f3, #42a5f5); }
        .stat-icon.orders { background: linear-gradient(45deg, #ff9800, #ffb74d); }
        .stat-icon.users { background: linear-gradient(45deg, #9c27b0, #ba68c8); }
        .stat-icon.events { background: linear-gradient(45deg, #f44336, #ef5350); }
        .stat-icon.participants { background: linear-gradient(45deg, #00bcd4, #26c6da); }
        .stat-icon.students { background: linear-gradient(45deg, #4caf50, #66bb6a); }
        .stat-icon.instructors { background: linear-gradient(45deg, #ff5722, #ff7043); }
        .stat-icon.vendors { background: linear-gradient(45deg, #607d8b, #78909c); }
        .stat-icon.blog { background: linear-gradient(45deg, #e91e63, #f06292); }
        .stat-icon.revenue { background: linear-gradient(45deg, #4caf50, #66bb6a); }

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

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.open {
                transform: translateX(0);
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

        .stat-card:nth-child(1) { animation-delay: 0.1s; }
        .stat-card:nth-child(2) { animation-delay: 0.2s; }
        .stat-card:nth-child(3) { animation-delay: 0.3s; }
        .stat-card:nth-child(4) { animation-delay: 0.4s; }
        .stat-card:nth-child(5) { animation-delay: 0.5s; }
        .stat-card:nth-child(6) { animation-delay: 0.6s; }
        .stat-card:nth-child(7) { animation-delay: 0.7s; }
        .stat-card:nth-child(8) { animation-delay: 0.8s; }
        .stat-card:nth-child(9) { animation-delay: 0.9s; }
        .stat-card:nth-child(10) { animation-delay: 1s; }

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
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
                 <div class="sidebar-header">
             <h2>TaaBia Admin</h2>
                                     <?= htmlspecialchars($current_user['full_name'] ?? 'Admin') ?>
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
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <h1 class="page-title"><?= __('dashboard') ?></h1>
                    
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <?php include '../includes/language_switcher.php'; ?>
                        
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
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_orders) ?></div>
                            <div class="stat-label"><?= __('total_orders') ?></div>
                            <div class="stat-change positive">+15% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_users) ?></div>
                            <div class="stat-label"><?= __('total_users') ?></div>
                            <div class="stat-change positive">+5% <?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>

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
                        <div class="stat-icon participants">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_participants) ?></div>
                            <div class="stat-label"><?= __('total_participants') ?></div>
                            <div class="stat-change positive">+20% <?= __('this_month') ?></div>
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
                            <div class="stat-label">Étudiants</div>
                            <div class="stat-change positive">+7% ce mois</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_instructors) ?></div>
                            <div class="stat-label">Instructeurs</div>
                            <div class="stat-change positive">+2% ce mois</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon vendors">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_vendor) ?></div>
                            <div class="stat-label">Vendeurs</div>
                            <div class="stat-change positive">+4% ce mois</div>
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
                            <div class="stat-label">Articles de Blog</div>
                            <div class="stat-change positive">+10% ce mois</div>
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
                            <div class="stat-label">Revenus totaux</div>
                            <div class="stat-change positive">+25% ce mois</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Évolution des revenus</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="activity-card">
                    <div class="chart-header">
                        <h3 class="chart-title">Activité récente</h3>
                    </div>
                    
                    <?php if (empty($recent_activities)): ?>
                        <div class="activity-item">
                            <div class="activity-content">
                                <div class="activity-title">Aucune activité récente</div>
                                <div class="activity-time">Aucune donnée disponible</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon" style="background: linear-gradient(45deg, 
                                    <?php 
                                    switch($activity['type']) {
                                        case 'user': echo '#4caf50, #66bb6a'; break;
                                        case 'course': echo '#ff9800, #ffb74d'; break;
                                        case 'order': echo '#2196f3, #42a5f5'; break;
                                        case 'event': echo '#9c27b0, #ba68c8'; break;
                                        case 'blog': echo '#e91e63, #f06292'; break;
                                        default: echo '#607d8b, #78909c'; break;
                                    }
                                    ?>);">
                                    <i class="fas <?php 
                                        switch($activity['type']) {
                                            case 'user': echo 'fa-user-plus'; break;
                                            case 'course': echo 'fa-book'; break;
                                            case 'order': echo 'fa-shopping-cart'; break;
                                            case 'event': echo 'fa-calendar'; break;
                                            case 'blog': echo 'fa-newspaper'; break;
                                            default: echo 'fa-info-circle'; break;
                                        }
                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php 
                                        switch($activity['type']) {
                                            case 'user': echo 'Nouvel utilisateur: ' . htmlspecialchars($activity['title']); break;
                                            case 'course': echo 'Nouvelle formation: ' . htmlspecialchars($activity['title']); break;
                                            case 'order': echo htmlspecialchars($activity['title']); break;
                                            case 'event': echo 'Nouvel événement: ' . htmlspecialchars($activity['title']); break;
                                            case 'blog': echo 'Nouvel article: ' . htmlspecialchars($activity['title']); break;
                                            default: echo htmlspecialchars($activity['title']); break;
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
                labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
                datasets: [{
                    label: 'Revenus mensuels',
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
    </script>
</body>
</html>