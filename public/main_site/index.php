<?php
// Handle language switching first
require_once '../../includes/language_handler.php';
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get featured courses
try {
    $featured_courses = $pdo->query("
        SELECT c.*, u.full_name AS instructor_name 
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        WHERE c.status = 'published' 
        ORDER BY c.created_at DESC 
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    $featured_courses = [];
}

// Get featured products
try {
    $featured_products = $pdo->query("
        SELECT p.*, u.full_name AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.status = 'active' 
        ORDER BY p.created_at DESC 
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Get upcoming events
try {
    $upcoming_events = $pdo->query("
        SELECT e.*, u.full_name AS organizer_name 
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE e.event_date >= CURDATE() AND e.status = 'upcoming' 
        ORDER BY e.event_date ASC 
        LIMIT 3
    ")->fetchAll();
} catch (PDOException $e) {
    $upcoming_events = [];
}

// Get latest blog posts
try {
    $blog_posts = $pdo->query("
        SELECT * FROM blog_posts 
        WHERE status = 'published' 
        ORDER BY created_at DESC 
        LIMIT 3
    ")->fetchAll();
} catch (PDOException $e) {
    $blog_posts = [];
}

// Get platform statistics
try {
    // Total courses
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn();

    // Total students
    $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn();

    // Total instructors
    $total_instructors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND is_active = 1")->fetchColumn();

    // Total products
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
} catch (PDOException $e) {
    $total_courses = 0;
    $total_students = 0;
    $total_instructors = 0;
    $total_products = 0;
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaaBia - <?= __('courses') ?> & <?= __('shop') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Modern CSS Variables */
        :root {
            --primary-color: #009688;
            --primary-light: #4db6ac;
            --primary-dark: #00695c;
            --secondary-color: #00bcd4;
            --accent-color: #ff5722;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
            --text-primary: #212121;
            --text-secondary: #757575;
            --text-light: #bdbdbd;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #fafafa;
            --bg-tertiary: #f5f5f5;
            --bg-dark: #1a237e;
            --border-color: #e0e0e0;
            --border-radius: 12px;
            --border-radius-sm: 6px;
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 8px rgba(0, 0, 0, 0.12);
            --shadow-heavy: 0 8px 16px rgba(0, 0, 0, 0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

        /* Reset & Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: var(--bg-primary);
            box-shadow: var(--shadow-light);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md) var(--spacing-xl);
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: var(--spacing-xl);
            align-items: center;
        }

        .nav-link {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-actions {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }

        /* Hamburger Menu */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            padding: var(--spacing-sm);
            background: none;
            border: none;
            z-index: 1001;
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background: var(--primary-color);
            margin: 3px 0;
            transition: var(--transition);
            border-radius: 2px;
        }

        .hamburger.active span:nth-child(1) {
            transform: rotate(-45deg) translate(-5px, 6px);
        }

        .hamburger.active span:nth-child(2) {
            opacity: 0;
        }

        .hamburger.active span:nth-child(3) {
            transform: rotate(45deg) translate(-5px, -6px);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-lg {
            padding: var(--spacing-md) var(--spacing-xl);
            font-size: 1rem;
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--text-white);
            padding: var(--spacing-2xl) 0;
            text-align: center;
        }

        .hero-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }

        .hero h1 {
            font-size: 3rem;
            margin-bottom: var(--spacing-lg);
            color: var(--text-white);
        }

        .hero p {
            font-size: 1.25rem;
            margin-bottom: var(--spacing-xl);
            color: rgba(255, 255, 255, 0.9);
        }

        /* Cards */
        .card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        /* Product/Course Cards */
        .product-card,
        .course-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
            height: fit-content;
        }

        .product-card:hover,
        .course-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-4px);
        }

        .product-image,
        .course-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-content,
        .course-content {
            padding: var(--spacing-lg);
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        /* Responsive card adjustments */
        @media (max-width: 768px) {

            .product-image,
            .course-image {
                height: 180px;
            }

            .product-content,
            .course-content {
                padding: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {

            .product-image,
            .course-image {
                height: 160px;
            }

            .product-content,
            .course-content {
                padding: var(--spacing-sm);
            }
        }

        .product-title,
        .course-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .product-price,
        .course-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }

        .product-description,
        .course-description {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.5;
        }

        .course-instructor {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-sm);
        }

        /* Grid Layouts */
        .grid {
            display: grid;
            gap: var(--spacing-lg);
        }

        .grid-3 {
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        }

        .grid-4 {
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        }

        /* Responsive Grid Breakpoints */
        @media (min-width: 1400px) {
            .grid-4 {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) and (min-width: 992px) {
            .grid-4 {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 991px) and (min-width: 769px) {
            .grid-4 {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }

        .section {
            padding: var(--spacing-2xl) 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .section-title h2 {
            font-size: 2.25rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .section-title p {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }

        /* Footer */
        .footer {
            background: #00796b;
            color: var(--text-white);
            padding: var(--spacing-2xl) 0;
            margin-top: var(--spacing-2xl);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-xl);
        }

        .footer-section h3 {
            color: var(--text-white);
            margin-bottom: var(--spacing-lg);
        }

        .footer-section p,
        .footer-section a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            margin-bottom: var(--spacing-sm);
            display: block;
        }

        .footer-section a:hover {
            color: var(--text-white);
        }

        .footer-bottom {
            text-align: center;
            padding-top: var(--spacing-xl);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: var(--spacing-xl);
            color: rgba(255, 255, 255, 0.6);
        }

        /* Logo Slider Animation */
        @keyframes slide {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        .logo-slider {
            position: relative;
            overflow: hidden;
            padding: var(--spacing-lg) 0;
        }

        .logo-container {
            display: flex;
            gap: var(--spacing-2xl);
            align-items: center;
            animation: slide 20s linear infinite;
        }

        .logo-item {
            flex-shrink: 0;
            text-align: center;
            min-width: 150px;
            transition: var(--transition);
        }

        .logo-item:hover {
            transform: scale(1.05);
        }

        /* Responsive Design */
        /* Large tablets and small desktops */
        @media (max-width: 1200px) {
            .container {
                padding: 0 var(--spacing-lg);
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .section-title h2 {
                font-size: 2rem;
            }
        }

        /* Tablets */
        @media (max-width: 991px) {
            .container {
                padding: 0 var(--spacing-md);
            }

            .hero {
                padding: var(--spacing-xl) 0;
            }

            .hero h1 {
                font-size: 2.25rem;
            }

            .section {
                padding: var(--spacing-xl) 0;
            }

            .section-title h2 {
                font-size: 1.875rem;
            }

            .navbar {
                padding: var(--spacing-md) var(--spacing-lg);
            }
        }

        /* Mobile devices */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: row;
                justify-content: space-between;
                padding: var(--spacing-md) var(--spacing-lg);
            }

            .hamburger {
                display: flex;
            }

            .nav-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: 85%;
                max-width: 320px;
                height: 100vh;
                background: var(--bg-primary);
                flex-direction: column;
                gap: var(--spacing-lg);
                padding: var(--spacing-2xl) var(--spacing-lg);
                box-shadow: var(--shadow-heavy);
                transition: var(--transition);
                z-index: 1000;
                overflow-y: auto;
            }

            .nav-menu.active {
                right: 0;
            }

            .nav-actions {
                flex-direction: column;
                width: 100%;
                gap: var(--spacing-md);
            }

            .hero {
                padding: var(--spacing-lg) 0;
            }

            .hero h1 {
                font-size: 1.875rem;
                line-height: 1.2;
            }

            .hero p {
                font-size: 1rem;
                margin-bottom: var(--spacing-lg);
            }

            .container {
                padding: 0 var(--spacing-md);
            }

            .section {
                padding: var(--spacing-lg) 0;
            }

            .section-title h2 {
                font-size: 1.75rem;
                line-height: 1.3;
            }

            .section-title p {
                font-size: 1rem;
            }

            .grid {
                gap: var(--spacing-md);
            }

            .grid-3,
            .grid-4 {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }

            .card,
            .course-card,
            .product-card {
                margin-bottom: var(--spacing-md);
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: var(--spacing-lg);
            }

            .logo-container {
                animation-duration: 12s;
            }

            .logo-item {
                min-width: 120px;
            }

            /* Statistics section mobile optimization */
            .section[style*="background: var(--bg-primary)"] .grid {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-md);
            }
        }

        /* Small mobile devices */
        @media (max-width: 480px) {
            .hero h1 {
                font-size: 1.5rem;
            }

            .section-title h2 {
                font-size: 1.5rem;
            }

            .container {
                padding: 0 var(--spacing-sm);
            }

            .navbar {
                padding: var(--spacing-sm) var(--spacing-md);
            }

            .nav-menu {
                width: 90%;
                padding: var(--spacing-xl) var(--spacing-md);
            }

            /* Statistics section - single column on very small screens */
            .section[style*="background: var(--bg-primary)"] .grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-sm);
            }

            .logo-item {
                min-width: 100px;
            }

            .btn {
                padding: var(--spacing-sm) var(--spacing-md);
                font-size: 0.8rem;
            }

            .btn-lg {
                padding: var(--spacing-md) var(--spacing-lg);
                font-size: 0.9rem;
            }
        }

        /* Utility Classes */
        .text-center {
            text-align: center;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-4 {
            margin-bottom: var(--spacing-lg);
        }

        .mt-5 {
            margin-top: var(--spacing-xl);
        }

        .d-flex {
            display: flex;
        }

        .justify-center {
            justify-content: center;
        }

        .align-center {
            align-items: center;
        }

        .w-100 {
            width: 100%;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-graduation-cap"></i> TaaBia
            </a>

            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="index.php" class="nav-link"><?= __('welcome') ?></a></li>
                <li><a href="courses.php" class="nav-link"><?= __('courses') ?></a></li>
                <li><a href="shop.php" class="nav-link"><?= __('shop') ?></a></li>
                <li><a href="upcoming_events.php" class="nav-link"><?= __('events') ?></a></li>
                <li><a href="blog.php" class="nav-link"><?= __('blog') ?></a></li>
                <li><a href="about.php" class="nav-link"><?= __('about') ?></a></li>
                <li><a href="contact.php" class="nav-link"><?= __('contact') ?></a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>
                <li style="margin-left: auto;">
                    <?php include '../../includes/public_language_switcher.php'; ?>
                </li>
            </ul>

            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> <?= __('my_profile') ?>
                    </a>
                    <a href="../../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('login') ?>
                    </a>
                    <a href="../../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?= __('register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1><?= __('welcome') ?> <?= __('courses') ?></h1>
            <p><?= __('courses') ?> <?= __('and') ?> <?= __('products') ?> <?= __('for') ?> <?= __('personal') ?>
                <?= __('and') ?> <?= __('professional') ?> <?= __('development') ?>.</p>
            <div class="d-flex justify-center align-center" style="gap: var(--spacing-md);">
                <a href="courses.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-graduation-cap"></i> <?= __('discover_courses') ?>
                </a>
                <a href="shop.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-shopping-bag"></i> <?= __('visit_shop') ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="section" style="background: var(--bg-primary); padding: var(--spacing-xl) 0;">
        <div class="container">
            <div class="grid"
                style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: var(--spacing-xl);">
                <div style="text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: var(--spacing-sm);">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                        <?= number_format($total_courses) ?></h3>
                    <p style="color: var(--text-secondary);"><?= __('available_courses') ?></p>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--secondary-color); margin-bottom: var(--spacing-sm);">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                        <?= number_format($total_students) ?></h3>
                    <p style="color: var(--text-secondary);"><?= __('enrolled_students') ?></p>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--accent-color); margin-bottom: var(--spacing-sm);">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                        <?= number_format($total_instructors) ?></h3>
                    <p style="color: var(--text-secondary);"><?= __('expert_instructors') ?></p>
                </div>

                <div style="text-align: center;">
                    <div style="font-size: 2.5rem; color: var(--success-color); margin-bottom: var(--spacing-sm);">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                        <?= number_format($total_products) ?></h3>
                    <p style="color: var(--text-secondary);"><?= __('available_products') ?></p>
                </div>
            </div>
        </div>
    </section>



    <!-- Enterprise Training Section -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2><?= __('enterprise_training') ?></h2>
                <p><?= __('enterprise_training_description') ?></p>
            </div>

            <div class="grid"
                style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-xl);">
                <div class="card" style="text-align: center;">
                    <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);"><?= __('custom_training') ?></h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        <?= __('custom_training_description') ?>
                    </p>
                    <a href="courses.php?category=enterprise" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> <?= __('learn_more') ?>
                    </a>
                </div>

                <div class="card" style="text-align: center;">
                    <div style="font-size: 3rem; color: var(--secondary-color); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);"><?= __('professional_certification') ?></h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        <?= __('professional_certification_description') ?>
                    </p>
                    <a href="courses.php?category=certification" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> <?= __('learn_more') ?>
                    </a>
                </div>

                <div class="card" style="text-align: center;">
                    <div style="font-size: 3rem; color: var(--accent-color); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);"><?= __('performance_tracking') ?></h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        <?= __('performance_tracking_description') ?>
                    </p>
                    <a href="about.php#analytics" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> <?= __('learn_more') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Companies and Partners Logo Slider -->
    <section class="section" style="background: var(--bg-primary);">
        <div class="container">
            <div class="section-title">
                <h2><?= __('our_partners') ?></h2>
                <p><?= __('partners_description') ?></p>
            </div>

            <div class="logo-slider" style="overflow: hidden; position: relative;">
                <div class="logo-container"
                    style="display: flex; animation: slide 20s linear infinite; gap: var(--spacing-2xl); align-items: center;">
                    <!-- Company logos - you can replace with actual company logos -->
                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-building"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">TechCorp</h4>
                    </div>

                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div
                            style="font-size: 2.5rem; color: var(--secondary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-industry"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">InnovateLab</h4>
                    </div>

                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--accent-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">StartUpHub</h4>
                    </div>

                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--success-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-globe"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">GlobalTech</h4>
                    </div>

                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--info-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">SmartSolutions</h4>
                    </div>

                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--warning-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-lightbulb"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">CreativeMinds</h4>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2><?= __('blog_news') ?></h2>
                <p><?= __('blog_description') ?></p>
            </div>

            <div class="grid grid-4">
                <?php foreach ($blog_posts as $post): ?>
                    <div class="card">
                        <div
                            style="background: var(--primary-color); color: var(--text-white); padding: var(--spacing-md); border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0; margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-lg) calc(-1 * var(--spacing-lg));">
                            <i class="fas fa-calendar" style="margin-right: var(--spacing-sm);"></i>
                            <?= date('d/m/Y', strtotime($post['created_at'])) ?>
                        </div>
                        <h3 style="margin-bottom: var(--spacing-md);"><?= htmlspecialchars($post['title']) ?></h3>
                        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                            <?= htmlspecialchars(substr($post['content'], 0, 150)) ?>...
                        </p>
                        <a href="view_blog.php?id=<?= $post['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-right"></i> Lire l'article
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="text-center mt-5">
                <a href="blog.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-newspaper"></i> <?= __('view_all_articles') ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section class="section"></section>
    <div class="container">
        <div class="section-title">
            <h2><?= __('featured_courses') ?></h2>
            <p><?= __('featured_courses_description') ?></p>
        </div>
        <?php
        // Optional banner image for Featured Courses section
        $bannerCandidates = [
            __DIR__ . '/../../uploads/featured_courses_banner.jpg',
            __DIR__ . '/../../uploads/featured_courses_banner.png',
            __DIR__ . '/../../uploads/featured_courses_banner.webp'
        ];
        $bannerPublicPath = null;
        foreach ($bannerCandidates as $candidate) {
            if (file_exists($candidate)) {
                $bannerPublicPath = '../../uploads/' . basename($candidate);
                break;
            }
        }
        if ($bannerPublicPath): ?>
            <div style="margin-bottom: var(--spacing-xl);">
                <img src="<?= htmlspecialchars($bannerPublicPath) ?>" alt="<?= __('featured_courses') ?>" class="w-100"
                    style="border-radius: var(--border-radius); max-height: 320px; object-fit: cover; box-shadow: var(--shadow-light);">
            </div>
        <?php endif; ?>

        <div class="grid grid-4">
            <?php if (!empty($featured_courses)): ?>
                <?php foreach ($featured_courses as $course): ?>
                    <div class="course-card">
                        <?php if ($course['image_url']): ?>
                            <img src="../../uploads/<?= htmlspecialchars($course['image_url']) ?>"
                                alt="<?= htmlspecialchars($course['title']) ?>" class="course-image">
                        <?php else: ?>
                            <div class="course-image"
                                style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        <?php endif; ?>
                        <div class="course-content">
                            <div
                                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-sm);">
                                <span
                                    style="background: var(--primary-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                    <?= htmlspecialchars($course['u'] ?? __('course_category')) ?>
                                </span>
                                <span
                                    style="background: var(--secondary-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                    <?= htmlspecialchars($course['level'] ?? __('all_levels')) ?>
                                </span>
                            </div>
                            <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                            <div class="course-instructor">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($course['instructor_name'] ?? 'Instructeur') ?>
                            </div>
                            <div class="course-price">
                                <?php if ((float)($course['price'] ?? 0) <= 0): ?>
                                    Gratuit
                                <?php else: ?>
                                    <?= number_format($course['price'], 0, ',', ' ') ?> GHS
                                <?php endif; ?>
                            </div>
                            <p class="course-description">
                                <?= htmlspecialchars(substr($course['description'], 0, 120)) ?>...
                            </p>
                            <div style="display: flex; gap: var(--spacing-sm);">
                                <a href="view_course.php?id=<?= $course['id'] ?>" class="btn btn-primary" style="flex: 1;">
                                    <i class="fas fa-eye"></i> Voir les détails
                                </a>
                                <a href="view_course.php?id=<?= $course['id'] ?>&enroll=1" class="btn btn-secondary">
                                    <i class="fas fa-graduation-cap"></i> <?= __('enroll') ?>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: var(--spacing-2xl);">
                    <div style="font-size: 4rem; color: var(--text-light); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);"><?= __('no_courses_available') ?></h3>
                    <p style="color: var(--text-light);"><?= __('no_courses_description') ?></p>
                </div>
            <?php endif; ?>
        </div>

        <div class="text-center mt-5">
            <a href="courses.php" class="btn btn-primary btn-lg">
                <i class="fas fa-list"></i> <?= __('view_all_courses') ?>
            </a>
        </div>
    </div>
    </section>

    <!-- Featured Products Section -->
    <section class="section" style="background: var(--bg-primary);">
        <div class="container">
            <div class="section-title">
                <h2><?= __('featured_products') ?></h2>
                <p><?= __('featured_products_description') ?></p>
            </div>

            <div class="grid grid-4">
                <?php if (!empty($featured_products)): ?>
                    <?php foreach ($featured_products as $product): ?>
                        <div class="product-card">
                            <?php if ($product['image_url']): ?>
                                <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image"
                                    style="background: linear-gradient(135deg, var(--accent-color), var(--warning-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            <?php endif; ?>
                            <div class="product-content">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-sm);">
                                    <span
                                        style="background: var(--accent-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                        <?= htmlspecialchars($product['category'] ?? 'Produit') ?>
                                    </span>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <span
                                            style="background: var(--success-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                            <?= __('in_stock') ?>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            style="background: var(--danger-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                            <?= __('out_of_stock') ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <div
                                    style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--spacing-sm);">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($product['vendor_name'] ?? __('vendor')) ?>
                                </div>
                                <div class="product-price"><?= number_format($product['price'], 0, ',', ' ') ?> GHS</div>
                                <p class="product-description">
                                    <?= htmlspecialchars(substr($product['description'], 0, 120)) ?>...
                                </p>
                                <div style="display: flex; gap: var(--spacing-sm);">
                                    <a href="view_product.php?id=<?= $product['id'] ?>" class="btn btn-primary"
                                        style="flex: 1;">
                                        <i class="fas fa-eye"></i> Voir les détails
                                    </a>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <a href="#" onclick="addProductToCart(<?= $product['id'] ?>, this); return false;"
                                            class="btn btn-secondary">
                                            <i class="fas fa-cart-plus"></i> <?= __('add_to_cart') ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: var(--spacing-2xl);">
                        <div style="font-size: 4rem; color: var(--text-light); margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);"><?= __('no_products_available') ?>
                        </h3>
                        <p style="color: var(--text-light);"><?= __('no_products_description') ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="text-center mt-5">
                <a href="shop.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> <?= __('visit_shop') ?>
                </a>
            </div>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <?php if (!empty($upcoming_events)): ?>
        <section class="section">
            <div class="container">
                <div class="section-title">
                    <h2><?= __('upcoming_events') ?></h2>
                    <p><?= __('upcoming_events_description') ?></p>
                </div>

                <div class="grid grid-4">
                    <?php if (!empty($upcoming_events)): ?>
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="card">
                                <div
                                    style="background: var(--primary-color); color: white; padding: var(--spacing-md); border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0; margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-lg) calc(-1 * var(--spacing-lg)); text-align: center;">
                                    <i class="fas fa-calendar-alt" style="font-size: 1.5rem; margin-bottom: var(--spacing-sm);"></i>
                                    <div style="font-size: 1.25rem; font-weight: 600;">
                                        <?= date('d/m/Y', strtotime($event['event_date'])) ?></div>
                                    <div style="font-size: 0.875rem; opacity: 0.9;">
                                        <?= date('H:i', strtotime($event['event_date'])) ?></div>
                                </div>
                                <h3 class="course-title"><?= htmlspecialchars($event['title']) ?></h3>
                                <div class="course-instructor">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($event['organizer_name'] ?? __('organizer')) ?>
                                </div>
                                <?php if ($event['location']): ?>
                                    <div class="course-instructor">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                    </div>
                                <?php endif; ?>
                                <p class="course-description">
                                    <?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...
                                </p>
                                <div style="display: flex; gap: var(--spacing-sm);">
                                    <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-calendar-plus"></i> <?= __('register') ?>
                                    </a>
                                    <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-eye"></i> <?= __('details') ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: var(--spacing-2xl);">
                            <div style="font-size: 4rem; color: var(--text-light); margin-bottom: var(--spacing-lg);">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);"><?= __('no_events_upcoming') ?>
                            </h3>
                            <p style="color: var(--text-light);"><?= __('no_events_description') ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-5">
                    <a href="upcoming_events.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-alt"></i> <?= __('view_all_events') ?>
                    </a>
                </div>
            </div>
        </section>
    <?php endif; ?>


    <!-- Footer -->
    <footer class="footer">
        <!-- Professional Services Hero Section -->
        <section class="section"
            style="background: linear-gradient(135deg, var(--bg-dark), var(--primary-dark)); color: var(--text-white);">
            <div class="container">
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--spacing-2xl); align-items: center;">
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: var(--spacing-lg); color: var(--text-white);">
                            <?= __('professional_services_excellence') ?>
                        </h2>
                        <p
                            style="font-size: 1.125rem; margin-bottom: var(--spacing-xl); color: rgba(255, 255, 255, 0.9);">
                            <?= __('professional_services_description') ?>
                        </p>
                        <div class="d-flex" style="gap: var(--spacing-md);">
                            <a href="contact.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-phone"></i> <?= __('request_quote') ?>
                            </a>
                            <a href="about.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-info-circle"></i> <?= __('learn_more_about_us') ?>
                            </a>
                        </div>
                    </div>
                    <div style="text-align: center;">
                        <div style="font-size: 4rem; color: var(--primary-light); margin-bottom: var(--spacing-md);">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 style="color: var(--text-white); margin-bottom: var(--spacing-md);">
                            <?= __('enterprise_solutions') ?>
                        </h3>
                        <ul style="text-align: left; color: rgba(255, 255, 255, 0.9);">
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-check"
                                    style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                                <?= __('custom_training_solution') ?>
                            </li>
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-check"
                                    style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                                <?= __('professional_certification_solution') ?>
                            </li>
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-check"
                                    style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                                <?= __('strategic_accompaniment') ?>
                            </li>
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-check"
                                    style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                                <?= __('technical_support_24_7') ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                <p><?= __('taabia_description') ?></p>
            </div>

            <div class="footer-section">
                <h3><?= __('quick_links') ?></h3>
                <a href="courses.php"><?= __('courses') ?></a>
                <a href="shop.php"><?= __('shop') ?></a>
                <a href="upcoming_events.php"><?= __('events') ?></a>
                <a href="about.php"><?= __('about') ?></a>
                <a href="contact.php"><?= __('contact') ?></a>
            </div>

            <div class="footer-section">
                <h3><?= __('support') ?></h3>
                <a href="contact.php"><?= __('help_support') ?></a>
                <a href="about.php"><?= __('about_us') ?></a>
                <a href="../auth/register.php"><?= __('create_account') ?></a>
                <a href="../auth/login.php"><?= __('connect') ?></a>
            </div>

            <div class="footer-section">
                <h3><?= __('contact_info') ?></h3>
                <p><i class="fas fa-envelope"></i> <?= __('email') ?></p>
                <p><i class="fas fa-phone"></i> <?= __('phone') ?></p>
                <p><i class="fas fa-map-marker-alt"></i> <?= __('location') ?></p>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 TaaBia. <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>

    <script>
        // Hamburger Menu Functionality
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('nav-menu');

        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });

        // Notifications
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.innerHTML = `
                <div style="position: fixed; top: 20px; right: 20px; z-index: 1000; padding: 15px 20px; border-radius: 8px; color: white; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 300px; background: ${type==='success' ? '#4caf50' : type==='error' ? '#f44336' : '#009688'};">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                </div>`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Cart badge update
        function updateCartCount(count) {
            const cartLink = document.querySelector('.nav-link[href="basket.php"]');
            if (cartLink) {
                const existing = cartLink.querySelector('.cart-count');
                if (existing) existing.remove();
                if (count > 0) {
                    const badge = document.createElement('span');
                    badge.className = 'cart-count';
                    badge.textContent = count;
                    badge.style.cssText =
                        `position:absolute;top:-8px;right:-8px;background:#f44336;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;`;
                    cartLink.style.position = 'relative';
                    cartLink.appendChild(badge);
                }
            }
        }

        // Initialize cart count
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_cart_count.php').then(r => r.json()).then(data => {
                if (data.success) updateCartCount(data.cart_count);
            }).catch(() => {});
        });

        // Add product to cart via AJAX
        function addProductToCart(productId, btnEl) {
            const button = btnEl;
            const original = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
            button.classList.add('disabled');
            fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'product_id=' + encodeURIComponent(productId) + '&quantity=1'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Produit ajouté au panier', 'success');
                        updateCartCount(data.cart_count);
                    } else {
                        showNotification(data.message || 'Erreur lors de l\'ajout', 'error');
                    }
                })
                .catch(() => showNotification('Erreur de connexion', 'error'))
                .finally(() => {
                    button.innerHTML = original;
                    button.classList.remove('disabled');
                });
        }
    </script>
</body>

</html>