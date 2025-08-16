<?php
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
        WHERE e.event_date >= CURDATE() AND e.status = 'active' 
        ORDER BY e.event_date ASC 
        LIMIT 3
    ")->fetchAll();
} catch (PDOException $e) {
    $upcoming_events = [];
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
            --shadow-light: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 8px rgba(0,0,0,0.12);
            --shadow-heavy: 0 8px 16px rgba(0,0,0,0.15);
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
        .product-card, .course-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .product-card:hover, .course-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-4px);
        }

        .product-image, .course-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-content, .course-content {
            padding: var(--spacing-lg);
        }

        .product-title, .course-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .product-price, .course-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }

        .product-description, .course-description {
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
        @media (max-width: 768px) {
            .navbar {
                flex-direction: row;
                justify-content: space-between;
                padding: var(--spacing-md) var(--spacing-xl);
            }
            
            .hamburger {
                display: flex;
            }
            
            .nav-menu {
                position: fixed;
                top: 0;
                right: -100%;
                width: 80%;
                max-width: 300px;
                height: 100vh;
                background: var(--bg-primary);
                flex-direction: column;
                gap: var(--spacing-lg);
                padding: var(--spacing-2xl) var(--spacing-xl);
                box-shadow: var(--shadow-heavy);
                transition: var(--transition);
                z-index: 1000;
            }
            
            .nav-menu.active {
                right: 0;
            }
            
            .nav-actions {
                flex-direction: column;
                width: 100%;
                gap: var(--spacing-md);
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .grid-3 {
                grid-template-columns: 1fr;
            }
            
            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .logo-container {
                animation-duration: 15s;
            }
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .mb-0 { margin-bottom: 0; }
        .mb-4 { margin-bottom: var(--spacing-lg); }
        .mt-5 { margin-top: var(--spacing-xl); }
        .d-flex { display: flex; }
        .justify-center { justify-content: center; }
        .align-center { align-items: center; }
        .w-100 { width: 100%; }
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
            <p><?= __('courses') ?> <?= __('and') ?> <?= __('products') ?> <?= __('for') ?> <?= __('personal') ?> <?= __('and') ?> <?= __('professional') ?> <?= __('development') ?>.</p>
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

    

    <!-- Enterprise Training Section -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>Formation Entreprise</h2>
                <p>Solutions de formation personnalisées pour optimiser les compétences de vos équipes</p>
            </div>
            
            <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-xl);">
                <div class="card" style="text-align: center;">
                    <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);">Formation sur Mesure</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        Programmes adaptés aux besoins spécifiques de votre organisation, 
                        avec des contenus personnalisés et des objectifs alignés sur vos enjeux.
                    </p>
                    <a href="contact.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> En savoir plus
                    </a>
                </div>
                
                <div class="card" style="text-align: center;">
                    <div style="font-size: 3rem; color: var(--secondary-color); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-certificate"></i>
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);">Certification Professionnelle</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        Certifications reconnues par l'industrie pour valider les compétences 
                        de vos collaborateurs et renforcer leur crédibilité professionnelle.
                    </p>
                    <a href="contact.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> En savoir plus
                    </a>
                </div>
                
                <div class="card" style="text-align: center;">
                    <div style="font-size: 3rem; color: var(--accent-color); margin-bottom: var(--spacing-lg);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);">Suivi des Performances</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        Outils de suivi et d'évaluation pour mesurer l'impact de la formation 
                        sur les performances individuelles et collectives de votre équipe.
                    </p>
                    <a href="contact.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> En savoir plus
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Companies and Partners Logo Slider -->
    <section class="section" style="background: var(--bg-primary);">
        <div class="container">
            <div class="section-title">
                <h2>Nos Partenaires</h2>
                <p>Ils nous font confiance pour leurs besoins en formation</p>
            </div>
            
            <div class="logo-slider" style="overflow: hidden; position: relative;">
                <div class="logo-container" style="display: flex; animation: slide 20s linear infinite; gap: var(--spacing-2xl); align-items: center;">
                    <!-- Company logos - you can replace with actual company logos -->
                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-building"></i>
                        </div>
                        <h4 style="font-size: 0.875rem; color: var(--text-secondary);">TechCorp</h4>
                    </div>
                    
                    <div class="logo-item" style="flex-shrink: 0; text-align: center; min-width: 150px;">
                        <div style="font-size: 2.5rem; color: var(--secondary-color); margin-bottom: var(--spacing-sm);">
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
                <h2>Blog & Actualités</h2>
                <p>Restez informé des dernières tendances en formation et développement professionnel</p>
            </div>
            
            <div class="grid grid-3">
                <div class="card">
                    <div style="background: var(--primary-color); color: var(--text-white); padding: var(--spacing-md); border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0; margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-lg) calc(-1 * var(--spacing-lg));">
                        <i class="fas fa-calendar" style="margin-right: var(--spacing-sm);"></i>
                        15 Janvier 2024
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);">Les Tendances de la Formation en 2024</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        Découvrez les nouvelles approches de formation qui révolutionnent 
                        l'apprentissage professionnel et améliorent l'engagement des apprenants.
                    </p>
                    <a href="view_blog.php?id" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Lire l'article
                    </a>
                </div>
                
                <div class="card">
                    <div style="background: var(--secondary-color); color: var(--text-white); padding: var(--spacing-md); border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0; margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-lg) calc(-1 * var(--spacing-lg));">
                        <i class="fas fa-calendar" style="margin-right: var(--spacing-sm);"></i>
                        10 Janvier 2024
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);">L'Importance de la Formation Continue</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        Pourquoi la formation continue est essentielle pour maintenir 
                        la compétitivité dans un marché en constante évolution.
                    </p>
                    <a href="#" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Lire l'article
                    </a>
                </div>
                
                <div class="card">
                    <div style="background: var(--accent-color); color: var(--text-white); padding: var(--spacing-md); border-radius: var(--border-radius-sm) var(--border-radius-sm) 0 0; margin: calc(-1 * var(--spacing-lg)) calc(-1 * var(--spacing-lg)) var(--spacing-lg) calc(-1 * var(--spacing-lg));">
                        <i class="fas fa-calendar" style="margin-right: var(--spacing-sm);"></i>
                        5 Janvier 2024
                    </div>
                    <h3 style="margin-bottom: var(--spacing-md);">Formation à Distance : Bonnes Pratiques</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                        Conseils et stratégies pour optimiser l'efficacité de vos 
                        programmes de formation à distance.
                    </p>
                    <a href="#" class="btn btn-primary">
                        <i class="fas fa-arrow-right"></i> Lire l'article
                    </a>
                </div>
            </div>
            
            <div class="text-center mt-5">
                <a href="#" class="btn btn-primary btn-lg">
                    <i class="fas fa-newspaper"></i> Voir tous les articles
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Courses Section -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>Formations vedettes</h2>
                <p>Découvrez nos formations les plus populaires et enrichissantes</p>
            </div>
            
            <div class="grid grid-3">
                <?php foreach ($featured_courses as $course): ?>
                    <div class="course-card">
                        <div class="course-content">
                            <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                            <div class="course-instructor">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor_name'] ?? 'Instructeur') ?>
                            </div>
                            <div class="course-price"><?= number_format($course['price'], 2) ?> GHS</div>
                            <p class="course-description">
                                <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>...
                            </p>
                            <a href="view_course.php?id=<?= $course['id'] ?>" class="btn btn-primary w-100">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="courses.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-list"></i> Voir toutes les formations
                </a>
            </div>
        </div>
    </section>

    <!-- Featured Products Section -->
    <section class="section" style="background: var(--bg-primary);">
        <div class="container">
            <div class="section-title">
                <h2>Produits vedettes</h2>
                <p>Découvrez nos produits les plus populaires et innovants</p>
            </div>
            
            <div class="grid grid-3">
                <?php foreach ($featured_products as $product): ?>
                    <div class="product-card">
                        <?php if ($product['image_url']): ?>
                            <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-image">
                        <?php endif; ?>
                        <div class="product-content">
                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <div class="product-price"><?= number_format($product['price'], 2) ?> GHS</div>
                            <p class="product-description">
                                <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...
                            </p>
                            <a href="view_product.php?id=<?= $product['id'] ?>" class="btn btn-primary w-100">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="shop.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-shopping-bag"></i> Visiter la boutique
                </a>
            </div>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <?php if (!empty($upcoming_events)): ?>
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h2>Événements à venir</h2>
                <p>Ne manquez pas nos prochains événements enrichissants</p>
            </div>
            
            <div class="grid grid-3">
                <?php foreach ($upcoming_events as $event): ?>
                    <div class="card">
                        <h3 class="course-title"><?= htmlspecialchars($event['title']) ?></h3>
                        <div class="course-instructor">
                            <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($event['event_date'])) ?>
                        </div>
                        <div class="course-instructor">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($event['organizer_name'] ?? 'Organisateur') ?>
                        </div>
                        <p class="course-description">
                            <?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...
                        </p>
                        <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-primary w-100">
                            <i class="fas fa-calendar-plus"></i> S'inscrire
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-5">
                <a href="upcoming_events.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-calendar-alt"></i> Voir tous les événements
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    
    <!-- Footer -->
    <footer class="footer">
        <!-- Professional Services Hero Section -->
    <section class="section" style="background: linear-gradient(135deg, var(--bg-dark), var(--primary-dark)); color: var(--text-white);">
        <div class="container">
            <div class="grid" style="grid-template-columns: 1fr 1fr; gap: var(--spacing-2xl); align-items: center;">
                <div>
                    <h2 style="font-size: 2.5rem; margin-bottom: var(--spacing-lg); color: var(--text-white);">
                        Services Professionnels d'Excellence
                    </h2>
                    <p style="font-size: 1.125rem; margin-bottom: var(--spacing-xl); color: rgba(255, 255, 255, 0.9);">
                        Nous offrons des solutions de formation sur mesure pour les entreprises, 
                        des programmes de certification reconnus et des services de consultation 
                        pour optimiser votre développement organisationnel.
                    </p>
                    <div class="d-flex" style="gap: var(--spacing-md);">
                        <a href="contact.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-phone"></i> Demander un devis
                        </a>
                        <a href="about.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-info-circle"></i> En savoir plus
                        </a>
                    </div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 4rem; color: var(--primary-light); margin-bottom: var(--spacing-md);">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 style="color: var(--text-white); margin-bottom: var(--spacing-md);">
                        Solutions Entreprise
                    </h3>
                    <ul style="text-align: left; color: rgba(255, 255, 255, 0.9);">
                        <li style="margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-check" style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                            Formation sur mesure
                        </li>
                        <li style="margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-check" style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                            Certification professionnelle
                        </li>
                        <li style="margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-check" style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                            Accompagnement stratégique
                        </li>
                        <li style="margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-check" style="color: var(--success-color); margin-right: var(--spacing-sm);"></i>
                            Support technique 24/7
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>
        <div class="footer-content">
            <div class="footer-section">
                <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                <p>Votre plateforme de formation et e-commerce de confiance. Découvrez des formations de qualité, des produits innovants et des événements enrichissants.</p>
            </div>
            
            <div class="footer-section">
                <h3>Liens rapides</h3>
                <a href="courses.php">Formations</a>
                <a href="shop.php">Boutique</a>
                <a href="upcoming_events.php">Événements</a>
                <a href="about.php">À propos</a>
                <a href="contact.php">Contact</a>
            </div>
            
            <div class="footer-section">
                <h3>Support</h3>
                <a href="contact.php">Aide et support</a>
                <a href="about.php">À propos de nous</a>
                <a href="../auth/register.php">Créer un compte</a>
                <a href="../auth/login.php">Se connecter</a>
            </div>
            
            <div class="footer-section">
                <h3>Contact</h3>
                <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                <p><i class="fas fa-phone"></i> +233534918333</p>
                <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; 2024 TaaBia. Tous droits réservés.</p>
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
    </script>
</body>
</html>