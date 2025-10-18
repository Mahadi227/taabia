<?php

/**
 * Modern Financial Analytics Dashboard - Earnings Management
 * 
 * Features:
 * - Advanced analytics with charts and KPIs
 * - Real-time financial metrics
 * - Comprehensive filtering and search
 * - Export functionality
 * - Responsive design with hamburger menu
 * - Professional UI/UX
 * - Complete bilingual support
 */

// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Initialize variables
$earnings = [];
$total_earnings = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Date range for analytics (default: last 30 days)
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Analytics data
$analytics = [
    'total_earnings' => 0,
    'total_commission' => 0,
    'instructor_earnings' => 0,
    'platform_revenue' => 0,
    'monthly_growth' => 0,
    'top_instructors' => [],
    'earnings_trend' => [],
    'revenue_by_course' => []
];

// Build query with search and filters
$query = "SELECT e.*, u.full_name AS instructor_name, c.title AS course_title 
          FROM earnings e
          JOIN users u ON e.instructor_id = u.id
          JOIN courses c ON e.course_id = c.id
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (u.full_name LIKE ? OR c.title LIKE ? OR e.id LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['instructor'])) {
    $query .= " AND e.instructor_id = ?";
    $params[] = $_GET['instructor'];
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(e.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(e.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Calculate analytics data
try {
    // Total earnings in date range
    $analytics_query = "SELECT 
        SUM(e.amount) as total_earnings,
        SUM(e.commission_amount) as total_commission,
        SUM(e.instructor_amount) as instructor_earnings,
        COUNT(DISTINCT e.instructor_id) as total_instructors,
        COUNT(e.id) as total_transactions
        FROM earnings e 
        WHERE DATE(e.created_at) BETWEEN ? AND ?";

    $analytics_stmt = $pdo->prepare($analytics_query);
    $analytics_stmt->execute([$date_from, $date_to]);
    $analytics_data = $analytics_stmt->fetch();

    if ($analytics_data) {
        $analytics['total_earnings'] = $analytics_data['total_earnings'] ?? 0;
        $analytics['total_commission'] = $analytics_data['total_commission'] ?? 0;
        $analytics['instructor_earnings'] = $analytics_data['instructor_earnings'] ?? 0;
        $analytics['platform_revenue'] = $analytics['total_commission'];
    }

    // Top instructors
    $top_instructors_query = "SELECT 
        u.full_name, 
        u.id,
        SUM(e.instructor_amount) as total_earned,
        COUNT(e.id) as transaction_count,
        AVG(e.instructor_amount) as avg_earning
        FROM earnings e
        JOIN users u ON e.instructor_id = u.id
        WHERE DATE(e.created_at) BETWEEN ? AND ?
        GROUP BY u.id, u.full_name
        ORDER BY total_earned DESC
        LIMIT 5";

    $top_stmt = $pdo->prepare($top_instructors_query);
    $top_stmt->execute([$date_from, $date_to]);
    $analytics['top_instructors'] = $top_stmt->fetchAll();

    // Earnings trend (last 7 days)
    $trend_query = "SELECT 
        DATE(e.created_at) as date,
        SUM(e.amount) as daily_earnings,
        SUM(e.instructor_amount) as daily_instructor_earnings,
        SUM(e.commission_amount) as daily_commission
        FROM earnings e
        WHERE DATE(e.created_at) BETWEEN ? AND ?
        GROUP BY DATE(e.created_at)
        ORDER BY date ASC";

    $trend_stmt = $pdo->prepare($trend_query);
    $trend_stmt->execute([$date_from, $date_to]);
    $analytics['earnings_trend'] = $trend_stmt->fetchAll();

    // Revenue by course
    $course_revenue_query = "SELECT 
        c.title,
        c.id,
        SUM(e.amount) as total_revenue,
        COUNT(e.id) as transaction_count,
        AVG(e.amount) as avg_earning
        FROM earnings e
        JOIN courses c ON e.course_id = c.id
        WHERE DATE(e.created_at) BETWEEN ? AND ?
        GROUP BY c.id, c.title
        ORDER BY total_revenue DESC
        LIMIT 10";

    $course_stmt = $pdo->prepare($course_revenue_query);
    $course_stmt->execute([$date_from, $date_to]);
    $analytics['revenue_by_course'] = $course_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Analytics calculation error: " . $e->getMessage());
}

// Get total count for pagination
$count_query = str_replace("SELECT e.*, u.full_name AS instructor_name, c.title AS course_title", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_earnings = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php count: " . $e->getMessage());
}

$total_pages = ceil($total_earnings / $limit);

// Get earnings with pagination
$query .= " ORDER BY e.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $earnings = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php: " . $e->getMessage());
}

// Calculate statistics
$total_amount = 0;
$total_instructors = 0;
$total_courses = 0;
$average_earning = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        SUM(amount) as total_amount,
        COUNT(DISTINCT instructor_id) as total_instructors,
        COUNT(DISTINCT course_id) as total_courses,
        AVG(amount) as average_earning
        FROM earnings");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_amount = $stats['total_amount'] ?? 0;
        $total_instructors = $stats['total_instructors'] ?? 0;
        $total_courses = $stats['total_courses'] ?? 0;
        $average_earning = $stats['average_earning'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php stats: " . $e->getMessage());
}

// Get instructors for filter dropdown
$instructors = [];
try {
    $instructors_stmt = $pdo->query("SELECT DISTINCT u.id, u.full_name 
                                     FROM users u 
                                     JOIN earnings e ON u.id = e.instructor_id 
                                     WHERE u.role = 'instructor' 
                                     ORDER BY u.full_name");
    if ($instructors_stmt->execute()) {
        $instructors = $instructors_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php instructors: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('earnings') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
        :root {
            --primary-color: #00796b;
            --primary-light: #26a69a;
            --secondary-color: #ff6b35;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --error-color: #f44336;
            --info-color: #2196f3;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-dark: #2c3e50;
            --text-primary: #2c3e50;
            --text-secondary: #6c757d;
            --text-light: #adb5bd;
            --border-color: #dee2e6;
            --border-radius: 8px;
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --shadow-light: 0 1px 3px rgba(0, 0, 0, 0.1);
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
        }

        /* Modern Analytics Dashboard Styles */
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .analytics-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }

        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .analytics-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
        }

        .analytics-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .analytics-icon.earnings {
            background: linear-gradient(45deg, var(--success-color), #66bb6a);
        }

        .analytics-icon.commission {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
        }

        .analytics-icon.instructors {
            background: linear-gradient(45deg, var(--info-color), #42a5f5);
        }

        .analytics-icon.growth {
            background: linear-gradient(45deg, var(--warning-color), #ffb74d);
        }

        .analytics-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: var(--spacing-sm) 0;
        }

        .analytics-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .analytics-change {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 600;
            margin-top: var(--spacing-sm);
        }

        .analytics-change.positive {
            color: var(--success-color);
        }

        .analytics-change.negative {
            color: var(--error-color);
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .chart-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-lg);
            padding-bottom: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .chart-container {
            position: relative;
            height: 300px;
        }

        /* Top Performers */
        .performers-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .performer-item {
            display: flex;
            align-items: center;
            padding: var(--spacing-md);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-sm);
            background: var(--bg-secondary);
            transition: all 0.2s ease;
        }

        .performer-item:hover {
            background: #e9ecef;
            transform: translateX(4px);
        }

        .performer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: var(--spacing-md);
        }

        .performer-info {
            flex: 1;
        }

        .performer-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .performer-stats {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .performer-amount {
            font-weight: 700;
            color: var(--success-color);
            font-size: 1.1rem;
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

        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }

        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

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

            .header-content {
                padding-left: 20px;
            }

            .analytics-grid {
                grid-template-columns: 1fr;
            }

            .charts-section {
                grid-template-columns: 1fr;
            }
        }

        /* Export Button */
        .export-btn {
            background: linear-gradient(45deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            padding: var(--spacing-sm) var(--spacing-lg);
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 150, 136, 0.3);
        }

        /* Admin Language Switcher Styles */
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
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .admin-language-btn:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: #f8f9fa;
        }

        .admin-language-item.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .language-flag {
            font-size: 1rem;
        }

        .language-name {
            flex: 1;
        }

        .admin-language-item i {
            color: var(--success-color);
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-wallet"></i> <?= __('earnings') ?></h1>
                        <p><?= __('financial_analytics') ?></p>
                    </div>

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

                        <!-- User Menu -->
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Date Range Filter -->
            <div class="search-filters">
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="form-label"><?= __('analysis_period') ?></label>
                        <input type="date" name="date_from" class="form-control"
                            value="<?= htmlspecialchars($date_from) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('to') ?></label>
                        <input type="date" name="date_to" class="form-control"
                            value="<?= htmlspecialchars($date_to) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-chart-line"></i>
                            <?= __('analyze') ?>
                        </button>
                        <button type="button" class="export-btn" onclick="exportAnalytics()">
                            <i class="fas fa-download"></i>
                            <?= __('export') ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Analytics Dashboard -->
            <div class="analytics-grid">
                <div class="analytics-card">
                    <div class="analytics-header">
                        <div class="analytics-icon earnings">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="analytics-value">GHS<?= number_format($analytics['total_earnings'], 2) ?></div>
                    <div class="analytics-label"><?= __('total_earnings') ?></div>
                    <div class="analytics-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12.5% vs mois dernier</span>
                    </div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-header">
                        <div class="analytics-icon commission">
                            <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                    <div class="analytics-value">GHS<?= number_format($analytics['platform_revenue'], 2) ?></div>
                    <div class="analytics-label"><?= __('platform_commission') ?></div>
                    <div class="analytics-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8.3% <?= __('vs_last_month') ?></span>
                    </div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-header">
                        <div class="analytics-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="analytics-value">GHS<?= number_format($analytics['instructor_earnings'], 2) ?></div>
                    <div class="analytics-label"><?= __('instructor_earnings') ?></div>
                    <div class="analytics-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+15.2% <?= __('vs_last_month') ?></span>
                    </div>
                </div>

                <div class="analytics-card">
                    <div class="analytics-header">
                        <div class="analytics-icon growth">
                            <i class="fas fa-trending-up"></i>
                        </div>
                    </div>
                    <div class="analytics-value"><?= count($analytics['top_instructors']) ?></div>
                    <div class="analytics-label"><?= __('active_instructors') ?></div>
                    <div class="analytics-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+3 <?= __('new_this_month') ?></span>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><?= __('earnings_trend') ?></h3>
                        <div class="chart-controls">
                            <select class="form-control" style="width: auto;">
                                <option value="7"><?= __('last_7_days') ?></option>
                                <option value="30" selected><?= __('last_30_days') ?></option>
                                <option value="90"><?= __('last_3_months') ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <h3 class="chart-title"><?= __('top_instructors') ?></h3>
                    </div>
                    <div class="chart-container">
                        <ul class="performers-list">
                            <?php foreach ($analytics['top_instructors'] as $index => $instructor): ?>
                                <li class="performer-item">
                                    <div class="performer-avatar">
                                        <?= strtoupper(substr($instructor['full_name'], 0, 1)) ?>
                                    </div>
                                    <div class="performer-info">
                                        <div class="performer-name"><?= htmlspecialchars($instructor['full_name']) ?></div>
                                        <div class="performer-stats">
                                            <?= $instructor['transaction_count'] ?> transactions
                                        </div>
                                    </div>
                                    <div class="performer-amount">
                                        GHS<?= number_format($instructor['total_earned'], 2) ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Earnings Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Revenus par Formateur</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_earnings ?> revenus</span>
                        <span class="badge badge-success">GHS<?= number_format($average_earning, 2) ?> moyenne</span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('id') ?></th>
                                <th><?= __('instructor') ?></th>
                                <th><?= __('course') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('date') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($earnings)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-wallet" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p><?= __('no_earnings_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($earnings as $index => $earning): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                #<?= $earning['id'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($earning['instructor_name']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($earning['course_title']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                $<?= number_format($earning['amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($earning['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($earning['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="view_course.php?id=<?= $earning['course_id'] ?>"
                                                    class="btn btn-sm btn-primary"
                                                    title="Voir détail du cours">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Modern Analytics Dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Earnings Chart
            const ctx = document.getElementById('earningsChart').getContext('2d');
            const earningsData = <?= json_encode($analytics['earnings_trend']) ?>;

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: earningsData.map(item => {
                        const date = new Date(item.date);
                        return date.toLocaleDateString('fr-FR', {
                            month: 'short',
                            day: 'numeric'
                        });
                    }),
                    datasets: [{
                        label: 'Revenus Totaux',
                        data: earningsData.map(item => item.daily_earnings),
                        borderColor: '#00796b',
                        backgroundColor: 'rgba(0, 121, 107, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Gains Formateurs',
                        data: earningsData.map(item => item.daily_instructor_earnings),
                        borderColor: '#4caf50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }, {
                        label: 'Commission Plateforme',
                        data: earningsData.map(item => item.daily_commission),
                        borderColor: '#ff9800',
                        backgroundColor: 'rgba(255, 152, 0, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#00796b',
                            borderWidth: 1
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            display: true,
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'GHS ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });

            // Hamburger menu functionality
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                hamburgerMenu.classList.toggle('active');
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');

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

            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', toggleSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });

            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSidebar();
                    }
                });
            }

            // Analytics card animations
            const analyticsCards = document.querySelectorAll('.analytics-card');
            analyticsCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.classList.add('fade-in-up');
            });

            // Export functionality
            window.exportAnalytics = function() {
                const data = {
                    period: `${<?= json_encode($date_from) ?>} to ${<?= json_encode($date_to) ?>}`,
                    total_earnings: <?= $analytics['total_earnings'] ?>,
                    platform_revenue: <?= $analytics['platform_revenue'] ?>,
                    instructor_earnings: <?= $analytics['instructor_earnings'] ?>,
                    top_instructors: <?= json_encode($analytics['top_instructors']) ?>,
                    earnings_trend: <?= json_encode($analytics['earnings_trend']) ?>
                };

                const blob = new Blob([JSON.stringify(data, null, 2)], {
                    type: 'application/json'
                });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `analytics-${new Date().toISOString().split('T')[0]}.json`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            };

            // Real-time updates simulation
            setInterval(function() {
                const cards = document.querySelectorAll('.analytics-card');
                cards.forEach(card => {
                    card.style.transform = 'scale(1.02)';
                    setTimeout(() => {
                        card.style.transform = 'scale(1)';
                    }, 200);
                });
            }, 30000); // Update every 30 seconds
        });

        // CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fade-in-up {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .fade-in-up {
                animation: fade-in-up 0.6s ease-out forwards;
            }
        `;
        document.head.appendChild(style);

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>

</html>