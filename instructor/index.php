<?php
// Start session first to ensure proper language handling
require_once '../includes/session.php';

// Handle language switching
require_once '../includes/language_handler.php';

// Load other includes
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get comprehensive statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_courses = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id)
        FROM student_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        WHERE c.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $total_students = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM course_contents
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmt->execute([$instructor_id]);
    $total_contents = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM lessons
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmt->execute([$instructor_id]);
    $total_lessons = $stmt->fetchColumn();

    // Get earnings data
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total 
        FROM transactions 
        WHERE instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $earnings = $stmt->fetch();
    $total_sales = $earnings['count'] ?? 0;
    $total_earnings = $earnings['total'] ?? 0;

    // Get recent transactions
    $stmt = $pdo->prepare("
        SELECT t.id, t.type, t.amount, u.full_name AS buyer_name, t.created_at,
               c.title as course_title
        FROM transactions t
        JOIN users u ON t.student_id = u.id
        LEFT JOIN courses c ON t.course_id = c.id
        WHERE t.instructor_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id]);
    $recent_transactions = $stmt->fetchAll();

    // Get monthly earnings for chart
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total
        FROM transactions
        WHERE instructor_id = ?
        GROUP BY month
        ORDER BY month ASC
        LIMIT 12
    ");
    $stmt->execute([$instructor_id]);
    $monthly_earnings = $stmt->fetchAll();

    // Get pending submissions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM submissions 
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?) 
        AND status = 'pending'
    ");
    $stmt->execute([$instructor_id]);
    $pending_submissions = $stmt->fetchColumn();

    // Get course performance
    $stmt = $pdo->prepare("
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
    $stmt->execute([$instructor_id]);
    $top_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in instructor dashboard: " . $e->getMessage());
    $total_courses = 0;
    $total_students = 0;
    $total_contents = 0;
    $total_lessons = 0;
    $total_sales = 0;
    $total_earnings = 0;
    $recent_transactions = [];
    $monthly_earnings = [];
    $pending_submissions = 0;
    $top_courses = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?> <?= __('instructor_space') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Dynamic Dashboard Styles */
        .chart-controls,
        .transactions-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .chart-btn {
            padding: 6px 12px;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chart-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .chart-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .refresh-btn,
        .auto-refresh-btn {
            padding: 8px;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: 6px;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
        }

        .refresh-btn:hover,
        .auto-refresh-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .auto-refresh-btn.active {
            background: var(--success-color);
            color: white;
            border-color: var(--success-color);
        }

        .refresh-btn.spinning i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }
        }

        .transaction-row-new {
            background: rgba(72, 187, 120, 0.1);
            animation: highlightNew 2s ease-out;
        }

        @keyframes highlightNew {
            0% {
                background: rgba(72, 187, 120, 0.3);
            }

            100% {
                background: transparent;
            }
        }

        .chart-summary-item {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            background: var(--gray-100);
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .chart-summary-item.positive {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
        }

        .chart-summary-item.negative {
            background: rgba(245, 101, 101, 0.1);
            color: var(--error-color);
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }

        .stats-cards-dynamic {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stats-card-dynamic {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease;
        }

        .stats-card-dynamic:hover {
            transform: translateY(-2px);
        }

        .stats-card-dynamic .title {
            font-size: 14px;
            color: var(--gray-600);
            margin-bottom: 8px;
        }

        .stats-card-dynamic .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .stats-card-dynamic .change {
            font-size: 12px;
            font-weight: 500;
        }

        .stats-card-dynamic .change.positive {
            color: var(--success-color);
        }

        .stats-card-dynamic .change.negative {
            color: var(--error-color);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {

            .chart-controls,
            .transactions-controls {
                flex-wrap: wrap;
                gap: 6px;
            }

            .chart-btn {
                padding: 4px 8px;
                font-size: 11px;
            }

            .refresh-btn,
            .auto-refresh-btn {
                width: 32px;
                height: 32px;
                padding: 6px;
            }
        }

        /* Hamburger Menu Styles */
        .hamburger-menu-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: all 0.3s ease;
        }

        .hamburger-line {
            width: 100%;
            height: 3px;
            background: var(--primary-color, rgb(226, 28, 28));
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        .hamburger-menu-btn:hover .hamburger-line {
            background: linear-gradient(135deg, rgb(235, 37, 37) 0%, rgb(216, 29, 29) 100%) !important;
            color: #e2e8f0;
        }

        /* Responsive Sidebar */
        .instructor-sidebar {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background: linear-gradient(135deg, rgb(235, 37, 37) 0%, rgb(216, 29, 29) 100%) !important;
        }

        .instructor-sidebar-header {
            border-bottom: 1px solid rgb(255, 255, 255) !important;
        }

        .instructor-sidebar-header h2 i {
            color: rgb(255, 255, 255) !important;
        }

        .instructor-nav-item {
            color: #e2e8f0 !important;
        }

        .instructor-nav-item:hover {
            background-color: rgb(251, 253, 255) !important;
            color: rgb(246, 246, 246) !important;
        }

        .instructor-nav-item.active {
            background-color: rgb(254, 254, 255) !important;
            color: rgb(206, 11, 11) !important;
        }

        .instructor-nav-item i {
            color: rgb(255, 255, 255) !important;
        }

        .instructor-nav-item:hover i,
        .instructor-nav-item.active i {
            color: #ffffff !important;
        }

        .instructor-sidebar.mobile-hidden {
            transform: translateX(-100%);
        }

        .instructor-sidebar.mobile-visible {
            transform: translateX(0);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Enhanced Top Courses Section */
        .top-courses-container {
            background: linear-gradient(135deg, #004085 0%, #004075 100%);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .top-courses-container::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            50% {
                transform: translate(-20px, -20px) rotate(180deg);
            }
        }

        .top-courses-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
            z-index: 2;
        }

        .top-courses-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .top-courses-refresh {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .top-courses-refresh:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .course-performance-card {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .course-performance-card:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .course-performance-card:last-child {
            margin-bottom: 0;
        }

        .course-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .course-details h4 {
            margin: 0 0 8px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
        }

        .course-stats {
            display: flex;
            gap: 16px;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .course-progress-section {
            text-align: right;
            min-width: 120px;
        }

        .progress-percentage {
            font-size: 1.2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }

        .progress-bar-container {
            width: 80px;
            height: 6px;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
            overflow: hidden;
            margin: 0 auto;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ade80, #22c55e);
            border-radius: 3px;
            transition: width 0.6s ease;
        }

        .course-rank {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .top-courses-empty {
            text-align: center;
            padding: 40px 20px;
            position: relative;
            z-index: 2;
        }

        .top-courses-empty i {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 16px;
        }

        .top-courses-empty h3 {
            color: white;
            margin-bottom: 8px;
        }

        .top-courses-empty p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
        }

        .create-course-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .create-course-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
            color: white;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .hamburger-menu-btn {
                display: flex;
            }

            .instructor-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                transform: translateX(-100%);
                box-shadow: none;
            }

            .instructor-sidebar.mobile-visible {
                transform: translateX(0);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }

            .instructor-main {
                margin-left: 0;
                width: 100%;
            }

            .instructor-header {
                padding-left: 60px;
            }

            .top-courses-container {
                margin: 0 15px 20px 15px;
                padding: 20px;
            }

            .top-courses-title {
                font-size: 1.3rem;
            }

            .course-performance-card {
                padding: 12px;
            }

            .course-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .course-progress-section {
                text-align: left;
                align-self: stretch;
            }

            .progress-bar-container {
                width: 100%;
                margin: 4px 0 0 0;
            }

            .instructor-cards {
                grid-template-columns: 1fr;
                gap: 15px;
                margin: 0 15px 20px 15px;
            }

            .instructor-table-container {
                margin: 0 15px 20px 15px;
            }

            .instructor-language-switcher {
                margin-top: 10px;
            }
        }

        @media (max-width: 480px) {
            .hamburger-menu-btn {
                top: 15px;
                left: 15px;
                width: 25px;
                height: 25px;
            }

            .hamburger-line {
                height: 2px;
            }

            .instructor-header {
                padding: 20px 15px 20px 50px;
            }

            .instructor-header h1 {
                font-size: 1.5rem;
            }

            .instructor-header p {
                font-size: 0.9rem;
            }

            .top-courses-container {
                margin: 0 10px 15px 10px;
                padding: 15px;
            }

            .top-courses-title {
                font-size: 1.2rem;
            }

            .course-details h4 {
                font-size: 1rem;
            }

            .course-stats {
                flex-direction: column;
                gap: 4px;
            }

            .instructor-header>div {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Tablet Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .instructor-sidebar {
                width: 250px;
            }

            .instructor-main {
                margin-left: 250px;
            }

            .instructor-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Animation for smooth transitions */
        .instructor-layout {
            transition: all 0.3s ease;
        }

        /* Focus styles for accessibility */
        .hamburger-menu-btn:focus {
            outline: 2px solid var(--primary-color, #2563eb);
            outline-offset: 2px;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .hamburger-line {
                background: #ffffff;
            }

            .hamburger-menu-btn:hover .hamburger-line {
                background: #e5e7eb;
            }

            .mobile-overlay {
                background: rgba(0, 0, 0, 0.7);
            }
        }

        /* ======================================================================== */
        /* FOOTER STYLES */
        /* ======================================================================== */
        .instructor-footer {
            background: linear-gradient(135deg, #1e293b 0%, #334155 50%, #475569 100%);
            color: #ffffff;
            margin-top: 3rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 3rem 0 2rem 0;
        }

        .footer-section h4 {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
            display: inline-block;
        }

        .footer-logo h3 {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .footer-logo p {
            color: #cbd5e1;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .footer-social {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff;
            border-radius: 50%;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .social-link:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.5rem;
        }

        .footer-links a {
            color: #cbd5e1;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.25rem 0;
            transition: all 0.3s ease;
            border-radius: 4px;
        }

        .footer-links a:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            padding-left: 0.5rem;
        }

        .footer-links a i {
            width: 16px;
            text-align: center;
            color: var(--primary-color);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 1.5rem 0;
        }

        .footer-bottom-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-copyright p {
            color: #94a3b8;
            font-size: 0.9rem;
            margin: 0;
        }

        .footer-links-bottom {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-links-bottom a {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s ease;
        }

        .footer-links-bottom a:hover {
            color: #ffffff;
        }

        .footer-language {
            display: flex;
            align-items: center;
        }

        /* Footer Responsive Design */
        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 2rem 0 1.5rem 0;
            }

            .footer-bottom-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .footer-links-bottom {
                justify-content: center;
            }

            .footer-social {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .footer-content {
                padding: 1.5rem 0 1rem 0;
            }

            .footer-section h4 {
                font-size: 1rem;
            }

            .footer-links a {
                font-size: 0.85rem;
            }

            .footer-links-bottom {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        /* Footer Animation */
        .footer-links a {
            position: relative;
            overflow: hidden;
        }

        .footer-links a::before {
            content: '';
            position: absolute;
            left: -100%;
            top: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .footer-links a:hover::before {
            left: 100%;
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Sidebar -->
        <div class="instructor-sidebar" id="sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p><?= __('instructor_space') ?></p>
            </div>

            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <?= __('new_course') ?>
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
                    <i class="fas fa-chart-line"></i>
                    <?= __('my_earnings') ?>
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('transactions') ?>
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= __('payments') ?>
                </a>
                <a href="profile.php" class="instructor-nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?>
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Mobile Hamburger Menu Button -->
        <button class="hamburger-menu-btn" id="hamburgerMenuBtn" aria-label="<?= __('toggle_navigation') ?>">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('dashboard') ?> <?= __('instructor_space') ?></h1>
                        <p><?= __('welcome') ?> <?= __('instructor_space') ?> - <?= __('manage_courses_students') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/instructor_language_switcher.php'; ?>
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
                    <div class="instructor-card-description"><?= __('active_courses') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('enrolled_students') ?></div>
                    <div class="instructor-card-value"><?= $total_students ?></div>
                    <div class="instructor-card-description"><?= __('active_students') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('created_lessons') ?></div>
                    <div class="instructor-card-value"><?= $total_lessons ?></div>
                    <div class="instructor-card-description"><?= __('available_content') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('pending_submissions') ?></div>
                    <div class="instructor-card-value"><?= $pending_submissions ?></div>
                    <div class="instructor-card-description"><?= __('pending') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon accent">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_sales') ?></div>
                    <div class="instructor-card-value"><?= $total_sales ?></div>
                    <div class="instructor-card-description"><?= __('transactions') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_earnings') ?></div>
                    <div class="instructor-card-value"><?= number_format($total_earnings, 2) ?> <?= __('currency') ?></div>
                    <div class="instructor-card-description"><?= __('generated_revenue') ?></div>
                </div>
            </div>

            <!-- Charts and Analytics -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-8);">
                <!-- Earnings Chart -->
                <div class="instructor-table-container">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                                <i class="fas fa-chart-line"></i> <?= __('earnings_evolution') ?>
                            </h3>
                            <div class="chart-controls">
                                <button class="chart-btn active" data-period="12"><?= __('12_months') ?></button>
                                <button class="chart-btn" data-period="6"><?= __('6_months') ?></button>
                                <button class="chart-btn" data-period="3"><?= __('3_months') ?></button>
                                <button class="chart-btn" data-period="1"><?= __('1_month') ?></button>
                                <button class="refresh-btn" onclick="refreshChart()" title="<?= __('refresh') ?>">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                            </div>
                        </div>
                        <div style="margin-top: 10px; display: flex; gap: 15px; font-size: 14px; color: var(--gray-600);">
                            <span id="chart-summary"><?= __('loading') ?>...</span>
                        </div>
                    </div>
                    <div style="padding: var(--spacing-6);">
                        <div id="chart-loading" style="display: none; text-align: center; padding: 40px;">
                            <i class="fas fa-spinner fa-spin"></i> <?= __('loading') ?>...
                        </div>
                        <canvas id="earningsChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Enhanced Top Courses Section -->
                <div class="top-courses-container">
                    <div class="top-courses-header">
                        <h3 class="top-courses-title">
                            <i class="fas fa-trophy"></i> <?= __('top_courses') ?>
                        </h3>
                        <button class="top-courses-refresh" onclick="refreshTopCourses()" title="<?= __('refresh') ?>">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>

                    <?php if (count($top_courses) > 0): ?>
                        <?php foreach ($top_courses as $index => $course): ?>
                            <div class="course-performance-card">
                                <div class="course-rank"><?= $index + 1 ?></div>
                                <div class="course-info">
                                    <div class="course-details">
                                        <h4><?= htmlspecialchars($course['title']) ?></h4>
                                        <div class="course-stats">
                                            <span><i class="fas fa-users"></i> <?= $course['enrollment_count'] ?> <?= __('enrolled') ?></span>
                                            <span><i class="fas fa-chart-line"></i> <?= __('performance') ?></span>
                                        </div>
                                    </div>
                                    <div class="course-progress-section">
                                        <div class="progress-percentage"><?= round($course['avg_progress'] ?? 0) ?>%</div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar-fill" style="width: <?= $course['avg_progress'] ?? 0 ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="top-courses-empty">
                            <i class="fas fa-trophy"></i>
                            <h3><?= __('no_courses') ?></h3>
                            <p><?= __('create_your_first_course') ?></p>
                            <a href="add_course.php" class="create-course-btn">
                                <i class="fas fa-plus"></i>
                                <?= __('create_course') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-clock"></i> <?= __('recent_transactions') ?>
                        </h3>
                        <div class="transactions-controls">
                            <button class="refresh-btn" onclick="refreshTransactions()" title="<?= __('refresh') ?>">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="auto-refresh-btn" onclick="toggleAutoRefresh()" title="<?= __('auto_refresh') ?>">
                                <i class="fas fa-play" id="auto-refresh-icon"></i>
                            </button>
                        </div>
                    </div>
                    <div style="margin-top: 10px; font-size: 14px; color: var(--gray-600);">
                        <span id="transactions-summary"><?= __('loading') ?>...</span>
                        <span id="auto-refresh-status" style="margin-left: 15px; display: none;">
                            <i class="fas fa-circle" style="color: #48bb78; animation: pulse 2s infinite;"></i> <?= __('auto_refresh_enabled') ?>
                        </span>
                    </div>
                </div>

                <div id="transactions-loading" style="display: none; text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin"></i> <?= __('loading') ?>...
                </div>
                <div id="transactions-content">
                    <?php if (count($recent_transactions) > 0): ?>
                        <table class="instructor-table" id="transactions-table">
                            <thead>
                                <tr>
                                    <th><?= __('id') ?></th>
                                    <th><?= __('type') ?></th>
                                    <th><?= __('course') ?></th>
                                    <th><?= __('student') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('date') ?></th>
                                </tr>
                            </thead>
                            <tbody id="transactions-tbody">
                                <?php foreach ($recent_transactions as $transaction): ?>
                                    <tr>
                                        <td>#<?= $transaction['id'] ?></td>
                                        <td>
                                            <span class="instructor-badge <?= $transaction['type'] == 'course' ? 'success' : 'info' ?>">
                                                <?= ucfirst($transaction['type']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($transaction['course_title'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($transaction['buyer_name']) ?></td>
                                        <td>
                                            <span style="font-weight: 600; color: var(--success-color);">
                                                <?= number_format($transaction['amount'], 2) ?> <?= __('currency') ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="instructor-empty">
                            <div class="instructor-empty-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="instructor-empty-title"><?= __('no_transactions_found') ?></div>
                            <div class="instructor-empty-description">
                                <?= __('no_recent_transactions_desc') ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-plus"></i>
                    <?= __('create_new_course') ?>
                </a>

                <a href="validate_submissions.php" class="instructor-btn instructor-btn-warning">
                    <i class="fas fa-check-circle"></i>
                    <?= __('validate_assignments') ?>
                </a>

                <a href="students.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-users"></i>
                    <?= __('manage_my_students') ?>
                </a>

                <a href="earnings.php" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-chart-line"></i>
                    <?= __('view_my_earnings') ?>
                </a>
            </div>
        </div>
    </div>
    <!-- ======================================================================== -->
    <!-- FOOTER WITH SITE MAP -->
    <!-- ======================================================================== -->
    <footer class="instructor-footer">
        <div class="footer-container">
            <div class="footer-content">
                <!-- Company Info -->
                <div class="footer-section">
                    <div class="footer-logo">
                        <h3><i class="fas fa-chalkboard-teacher"></i> TaaBia</h3>
                        <p><?= __('leading_online_learning_platform') ?></p>
                    </div>
                    <div class="footer-social">
                        <a href="#" class="social-link" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="social-link" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="social-link" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="social-link" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>

                <!-- Instructor Dashboard -->
                <div class="footer-section">
                    <h4><?= __('instructor_dashboard') ?></h4>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> <?= __('dashboard') ?></a></li>
                        <li><a href="my_courses.php"><i class="fas fa-book"></i> <?= __('my_courses') ?></a></li>
                        <li><a href="add_course.php"><i class="fas fa-plus-circle"></i> <?= __('new_course') ?></a></li>
                        <li><a href="students.php"><i class="fas fa-users"></i> <?= __('my_students') ?></a></li>
                        <li><a href="validate_submissions.php"><i class="fas fa-check-circle"></i> <?= __('pending_submissions') ?></a></li>
                    </ul>
                </div>

                <!-- Analytics & Reports -->
                <div class="footer-section">
                    <h4><?= __('analytics_reports') ?></h4>
                    <ul class="footer-links">
                        <li><a href="earnings.php"><i class="fas fa-chart-line"></i> <?= __('my_earnings') ?></a></li>
                        <li><a href="transactions.php"><i class="fas fa-shopping-cart"></i> <?= __('transactions') ?></a></li>
                        <li><a href="payouts.php"><i class="fas fa-money-bill-wave"></i> <?= __('payments') ?></a></li>
                        <li><a href="analytics.php"><i class="fas fa-chart-bar"></i> <?= __('analytics') ?></a></li>
                        <li><a href="reports.php"><i class="fas fa-file-alt"></i> <?= __('reports') ?></a></li>
                    </ul>
                </div>

                <!-- Course Management -->
                <div class="footer-section">
                    <h4><?= __('course_management') ?></h4>
                    <ul class="footer-links">
                        <li><a href="course_lessons.php"><i class="fas fa-list"></i> <?= __('lessons') ?></a></li>
                        <li><a href="add_lesson.php"><i class="fas fa-plus"></i> <?= __('add_lesson') ?></a></li>
                        <li><a href="assignments.php"><i class="fas fa-tasks"></i> <?= __('assignments') ?></a></li>
                        <li><a href="quizzes.php"><i class="fas fa-question-circle"></i> <?= __('quizzes') ?></a></li>
                        <li><a href="materials.php"><i class="fas fa-folder"></i> <?= __('materials') ?></a></li>
                    </ul>
                </div>

                <!-- Student Management -->
                <div class="footer-section">
                    <h4><?= __('student_management') ?></h4>
                    <ul class="footer-links">
                        <li><a href="student_progress.php"><i class="fas fa-chart-pie"></i> <?= __('student_progress') ?></a></li>
                        <li><a href="student_communications.php"><i class="fas fa-comments"></i> <?= __('communications') ?></a></li>
                        <li><a href="student_feedback.php"><i class="fas fa-star"></i> <?= __('feedback') ?></a></li>
                        <li><a href="student_certificates.php"><i class="fas fa-certificate"></i> <?= __('certificates') ?></a></li>
                        <li><a href="student_support.php"><i class="fas fa-life-ring"></i> <?= __('support') ?></a></li>
                    </ul>
                </div>

                <!-- Account & Settings -->
                <div class="footer-section">
                    <h4><?= __('account_settings') ?></h4>
                    <ul class="footer-links">
                        <li><a href="profile.php"><i class="fas fa-user"></i> <?= __('profile') ?></a></li>
                        <li><a href="settings.php"><i class="fas fa-cog"></i> <?= __('settings') ?></a></li>
                        <li><a href="notifications.php"><i class="fas fa-bell"></i> <?= __('notifications') ?></a></li>
                        <li><a href="security.php"><i class="fas fa-shield-alt"></i> <?= __('security') ?></a></li>
                        <li><a href="help.php"><i class="fas fa-question-circle"></i> <?= __('help_center') ?></a></li>
                    </ul>
                </div>

                <!-- Main Site Links -->
                <div class="footer-section">
                    <h4><?= __('main_site') ?></h4>
                    <ul class="footer-links">
                        <li><a href="../index.php"><i class="fas fa-home"></i> <?= __('home') ?></a></li>
                        <li><a href="../courses.php"><i class="fas fa-graduation-cap"></i> <?= __('all_courses') ?></a></li>
                        <li><a href="../categories.php"><i class="fas fa-th-large"></i> <?= __('categories') ?></a></li>
                        <li><a href="../instructors.php"><i class="fas fa-chalkboard-teacher"></i> <?= __('instructors') ?></a></li>
                        <li><a href="../about.php"><i class="fas fa-info-circle"></i> <?= __('about_us') ?></a></li>
                    </ul>
                </div>

                <!-- Support & Legal -->
                <div class="footer-section">
                    <h4><?= __('support_legal') ?></h4>
                    <ul class="footer-links">
                        <li><a href="../contact.php"><i class="fas fa-envelope"></i> <?= __('contact_us') ?></a></li>
                        <li><a href="../faq.php"><i class="fas fa-question"></i> <?= __('faq') ?></a></li>
                        <li><a href="../privacy.php"><i class="fas fa-user-shield"></i> <?= __('privacy_policy') ?></a></li>
                        <li><a href="../terms.php"><i class="fas fa-file-contract"></i> <?= __('terms_of_service') ?></a></li>
                        <li><a href="../refund.php"><i class="fas fa-undo"></i> <?= __('refund_policy') ?></a></li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom -->
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <div class="footer-copyright">
                        <p>&copy; <?= date('Y') ?> TaaBia. <?= __('all_rights_reserved') ?>.</p>
                    </div>
                    <div class="footer-links-bottom">
                        <a href="../privacy.php"><?= __('privacy') ?></a>
                        <a href="../terms.php"><?= __('terms') ?></a>
                        <a href="../cookies.php"><?= __('cookies') ?></a>
                        <a href="../sitemap.php"><?= __('sitemap') ?></a>
                    </div>
                    <div class="footer-language">
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </footer>


    <script>
        // Global variables
        let earningsChart = null;
        let autoRefreshInterval = null;
        let isAutoRefreshEnabled = false;
        let lastTransactionIds = [];

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
            initializeTransactions();
            setupEventListeners();
            animateCards();
            initializeHamburgerMenu();
        });

        // Chart initialization
        function initializeChart() {
            const ctx = document.getElementById('earningsChart').getContext('2d');
            earningsChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: '<?= __('monthly_earnings') ?> (<?= __('currency') ?>)',
                        data: [],
                        borderColor: 'var(--primary-color)',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: 'var(--primary-color)',
                        pointBorderColor: 'white',
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
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            titleColor: 'white',
                            bodyColor: 'white',
                            borderColor: 'var(--primary-color)',
                            borderWidth: 1,
                            cornerRadius: 8,
                            displayColors: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString() + ' <?= __('currency') ?>';
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuart'
                    }
                }
            });

            loadChartData(12);
        }

        // Load chart data
        async function loadChartData(months = 12) {
            showChartLoading(true);

            try {
                const response = await fetch('./api/get_dashboard_data.php');
                const result = await response.json();

                if (result.success) {
                    const earnings = result.data.monthly_earnings;
                    const periodData = earnings.slice(-months);

                    updateChart(periodData);
                    updateChartSummary(periodData);
                } else {
                    console.error('Error loading chart data:', result.error);
                }
            } catch (error) {
                console.error('Error fetching chart data:', error);
            } finally {
                showChartLoading(false);
            }
        }

        // Update chart with new data
        function updateChart(data) {
            const labels = data.map(item => {
                const date = new Date(item.month + '-01');
                return date.toLocaleDateString('<?= getCurrentLanguage() === 'fr' ? 'fr-FR' : 'en-US' ?>', {
                    month: 'short',
                    year: 'numeric'
                });
            });

            const values = data.map(item => parseFloat(item.total) || 0);

            earningsChart.data.labels = labels;
            earningsChart.data.datasets[0].data = values;
            earningsChart.update('active');
        }

        // Update chart summary
        function updateChartSummary(data) {
            const total = data.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);
            const avg = data.length > 0 ? total / data.length : 0;
            const latest = data.length > 1 ? parseFloat(data[data.length - 1].total) || 0 : 0;
            const previous = data.length > 1 ? parseFloat(data[data.length - 2].total) || 0 : 0;
            const change = previous > 0 ? ((latest - previous) / previous * 100) : 0;

            const summaryHtml = `
                <span class="chart-summary-item">
                    <i class="fas fa-chart-line"></i>
                    <?= __('total') ?>: ${total.toLocaleString()} <?= __('currency') ?>
                </span>
                <span class="chart-summary-item">
                    <i class="fas fa-calculator"></i>
                    <?= __('average') ?>: ${avg.toLocaleString()} <?= __('currency') ?>
                </span>
                <span class="chart-summary-item ${change >= 0 ? 'positive' : 'negative'}">
                    <i class="fas fa-arrow-${change >= 0 ? 'up' : 'down'}"></i>
                    ${change >= 0 ? '+' : ''}${change.toFixed(1)}%
                </span>
            `;

            document.getElementById('chart-summary').innerHTML = summaryHtml;
        }

        // Show/hide chart loading
        function showChartLoading(show) {
            const loading = document.getElementById('chart-loading');
            loading.style.display = show ? 'block' : 'none';
        }

        // Initialize transactions
        function initializeTransactions() {
            loadTransactions();
            // Store initial transaction IDs for comparison
            const rows = document.querySelectorAll('#transactions-tbody tr');
            lastTransactionIds = Array.from(rows).map(row => row.querySelector('td').textContent.replace('#', ''));
        }

        // Load transactions data
        async function loadTransactions() {
            showTransactionsLoading(true);

            try {
                const response = await fetch('./api/get_dashboard_data.php');
                const result = await response.json();

                if (result.success) {
                    updateTransactions(result.data.recent_transactions);
                    updateTransactionsSummary(result.data.recent_transactions);
                } else {
                    console.error('Error loading transactions:', result.error);
                }
            } catch (error) {
                console.error('Error fetching transactions:', error);
            } finally {
                showTransactionsLoading(false);
            }
        }

        // Update transactions table
        function updateTransactions(transactions) {
            const tbody = document.getElementById('transactions-tbody');
            const container = document.getElementById('transactions-content');

            if (!tbody || transactions.length === 0) {
                container.innerHTML = `
                    <div class="instructor-empty">
                        <div class="instructor-empty-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="instructor-empty-title"><?= __('no_transactions_found') ?></div>
                        <div class="instructor-empty-description">
                            <?= __('no_recent_transactions_desc') ?>
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            transactions.forEach((transaction, index) => {
                const isNew = !lastTransactionIds.includes(transaction.id.toString());
                const rowClass = isNew ? 'transaction-row-new' : '';

                html += `
                    <tr class="${rowClass}">
                        <td>#${transaction.id}</td>
                        <td>
                            <span class="instructor-badge ${transaction.type === 'course' ? 'success' : 'info'}">
                                ${transaction.type.charAt(0).toUpperCase() + transaction.type.slice(1)}
                            </span>
                        </td>
                        <td>${transaction.course_title || 'N/A'}</td>
                        <td>${transaction.buyer_name}</td>
                        <td>
                            <span style="font-weight: 600; color: var(--success-color);">
                                ${parseFloat(transaction.amount).toLocaleString()} <?= __('currency') ?>
                            </span>
                        </td>
                        <td>${formatDate(transaction.created_at)}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;

            // Update last transaction IDs
            lastTransactionIds = transactions.map(t => t.id.toString());
        }

        // Update transactions summary
        function updateTransactionsSummary(transactions) {
            const count = transactions.length;
            const total = transactions.reduce((sum, t) => sum + parseFloat(t.amount), 0);
            const today = transactions.filter(t => isToday(t.created_at)).length;

            document.getElementById('transactions-summary').innerHTML = `
                ${count} <?= __('transactions') ?> • 
                ${total.toLocaleString()} <?= __('currency') ?> • 
                ${today} <?= __('today') ?>
            `;
        }

        // Show/hide transactions loading
        function showTransactionsLoading(show) {
            const loading = document.getElementById('transactions-loading');
            loading.style.display = show ? 'block' : 'none';
        }

        // Setup event listeners
        function setupEventListeners() {
            // Chart period buttons
            document.querySelectorAll('.chart-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const period = parseInt(this.dataset.period);
                    loadChartData(period);
                });
            });

            // Auto-refresh toggle
            document.querySelector('.auto-refresh-btn').addEventListener('click', toggleAutoRefresh);
        }

        // Refresh chart
        function refreshChart() {
            const btn = document.querySelector('.refresh-btn');
            btn.classList.add('spinning');
            loadChartData().finally(() => {
                setTimeout(() => btn.classList.remove('spinning'), 500);
            });
        }

        // Refresh transactions
        function refreshTransactions() {
            const btn = document.querySelector('.transactions-controls .refresh-btn');
            btn.classList.add('spinning');
            loadTransactions().finally(() => {
                setTimeout(() => btn.classList.remove('spinning'), 500);
            });
        }

        // Toggle auto-refresh
        function toggleAutoRefresh() {
            const btn = document.querySelector('.auto-refresh-btn');
            const icon = document.getElementById('auto-refresh-icon');
            const status = document.getElementById('auto-refresh-status');

            if (isAutoRefreshEnabled) {
                clearInterval(autoRefreshInterval);
                isAutoRefreshEnabled = false;
                btn.classList.remove('active');
                icon.className = 'fas fa-play';
                status.style.display = 'none';
            } else {
                autoRefreshInterval = setInterval(() => {
                    loadTransactions();
                }, 30000); // Refresh every 30 seconds
                isAutoRefreshEnabled = true;
                btn.classList.add('active');
                icon.className = 'fas fa-pause';
                status.style.display = 'inline';
            }
        }

        // Utility functions
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('<?= getCurrentLanguage() === 'fr' ? 'fr-FR' : 'en-US' ?>', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function isToday(dateString) {
            const date = new Date(dateString);
            const today = new Date();
            return date.toDateString() === today.toDateString();
        }

        // Animate cards
        function animateCards() {
            const cards = document.querySelectorAll('.instructor-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Animate progress bars
            const progressBars = document.querySelectorAll('.instructor-progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        });

        // Hamburger Menu Functionality
        function initializeHamburgerMenu() {
            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            let isMenuOpen = false;

            function toggleMenu() {
                isMenuOpen = !isMenuOpen;

                if (isMenuOpen) {
                    hamburgerBtn.classList.add('active');
                    sidebar.classList.remove('mobile-hidden');
                    sidebar.classList.add('mobile-visible');
                    mobileOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            function closeMenu() {
                if (isMenuOpen) {
                    isMenuOpen = false;
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleMenu);
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMenu);
            }

            const sidebarLinks = document.querySelectorAll('.instructor-nav-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', closeMenu);
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeMenu();
                    document.body.style.overflow = '';
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isMenuOpen) {
                    closeMenu();
                }
            });

            if (window.innerWidth <= 768) {
                sidebar.classList.add('mobile-hidden');
            }
        }

        // Enhanced mobile interactions
        function setupMobileInteractions() {
            let startX = 0;
            let startY = 0;
            const sidebar = document.getElementById('sidebar');

            if (sidebar) {
                sidebar.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                });

                sidebar.addEventListener('touchmove', function(e) {
                    if (!startX || !startY) return;

                    const diffX = startX - e.touches[0].clientX;
                    const diffY = startY - e.touches[0].clientY;

                    if (Math.abs(diffX) > Math.abs(diffY)) {
                        if (diffX > 50) {
                            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
                            if (hamburgerBtn && hamburgerBtn.classList.contains('active')) {
                                hamburgerBtn.click();
                            }
                        }
                    }

                    startX = 0;
                    startY = 0;
                });
            }
        }

        // Initialize mobile interactions
        setupMobileInteractions();

        // Top Courses Refresh Functionality
        function refreshTopCourses() {
            const refreshBtn = document.querySelector('.top-courses-refresh');
            const icon = refreshBtn.querySelector('i');

            // Add loading animation
            icon.classList.add('fa-spin');
            refreshBtn.disabled = true;

            // Simulate refresh (in a real implementation, this would make an AJAX call)
            setTimeout(() => {
                icon.classList.remove('fa-spin');
                refreshBtn.disabled = false;

                // Add success feedback
                refreshBtn.style.background = 'rgba(34, 197, 94, 0.3)';
                setTimeout(() => {
                    refreshBtn.style.background = '';
                }, 1000);
            }, 1500);
        }

        // Animate course performance cards on load
        function animateCourseCards() {
            const cards = document.querySelectorAll('.course-performance-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }

        // Initialize course card animations
        animateCourseCards();
    </script>
</body>

</html>