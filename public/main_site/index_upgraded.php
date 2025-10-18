<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';
require_once '../../includes/function.php';
session_start();

// Get site settings
$platform_name = get_setting('platform_name', 'TaaBia Skills & Market');
$platform_description = get_setting('platform_description', 'Integrated learning and e-commerce platform');
$currency = get_setting('currency', 'GHS');
$contact_email = get_setting('contact_email', 'contact@taabia.com');
$contact_phone = get_setting('contact_phone', '+233534918333');
$address = get_setting('address', 'Accra, Ghana');

// Get featured courses with enhanced data
try {
    $featured_courses = $pdo->query("
        SELECT c.*, u.fullname AS instructor_name, 
               COUNT(DISTINCT cc.id) as lesson_count,
               AVG(cr.rating) as avg_rating,
               COUNT(DISTINCT cr.id) as review_count
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        LEFT JOIN course_contents cc ON c.id = cc.course_id AND cc.is_active = 1
        LEFT JOIN course_reviews cr ON c.id = cr.course_id
        WHERE c.status = 'published' AND c.is_active = 1
        GROUP BY c.id
        ORDER BY c.created_at DESC 
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    $featured_courses = [];
}

// Get featured products with enhanced data
try {
    $featured_products = $pdo->query("
        SELECT p.*, u.fullname AS vendor_name,
               COUNT(DISTINCT oi.id) as order_count
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        LEFT JOIN order_items oi ON p.id = oi.product_id
        WHERE p.status = 'active' AND p.is_active = 1
        GROUP BY p.id
        ORDER BY p.created_at DESC 
        LIMIT 6
    ")->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Get upcoming events
try {
    $upcoming_events = $pdo->query("
        SELECT e.*, u.fullname AS organizer_name 
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

// Get platform statistics with enhanced metrics
try {
    $total_courses = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published' AND is_active = 1")->fetchColumn();
    $total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn();
    $total_instructors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND is_active = 1")->fetchColumn();
    $total_products = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND is_active = 1")->fetchColumn();
    $total_events = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming'")->fetchColumn();
    $total_blog_posts = $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn();
} catch (PDOException $e) {
    $total_courses = $total_students = $total_instructors = $total_products = $total_events = $total_blog_posts = 0;
}

// Get current site images
$site_logo = get_site_image('site_logo');
$featured_banner = get_site_image('featured_courses_banner');
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>" class="no-js">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($platform_description) ?>">
    <meta name="keywords" content="formation, cours, e-commerce, développement professionnel, Ghana">
    <meta name="author" content="<?= htmlspecialchars($platform_name) ?>">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="<?= htmlspecialchars($platform_name) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($platform_description) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $_SERVER['REQUEST_URI'] ?>">

    <title><?= htmlspecialchars($platform_name) ?> - <?= __('courses') ?> & <?= __('shop') ?></title>

    <!-- Preload critical resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style">

    <!-- Stylesheets -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="../../assets/img/favicon.ico">

    <!-- Structured Data -->
    <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "EducationalOrganization",
            "name": "<?= htmlspecialchars($platform_name) ?>",
            "description": "<?= htmlspecialchars($platform_description) ?>",
            "url": "<?= $_SERVER['REQUEST_URI'] ?>",
            "contactPoint": {
                "@type": "ContactPoint",
                "telephone": "<?= htmlspecialchars($contact_phone) ?>",
                "contactType": "customer service",
                "email": "<?= htmlspecialchars($contact_email) ?>"
            },
            "address": {
                "@type": "PostalAddress",
                "addressLocality": "<?= htmlspecialchars($address) ?>"
            }
        }
    </script>
</head>

<body>
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-link">Aller au contenu principal</a>

    <!-- Loading Screen -->
    <div id="loading-screen" class="loading-screen">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>Chargement...</p>
        </div>
    </div>

    <!-- Header -->
    <header class="header" role="banner">
        <nav class="navbar" role="navigation" aria-label="Navigation principale">
            <div class="navbar-brand">
                <a href="index.php" class="logo" aria-label="Accueil <?= htmlspecialchars($platform_name) ?>">
                    <?php if ($site_logo): ?>
                        <img src="../../<?= htmlspecialchars($site_logo) ?>" alt="<?= htmlspecialchars($platform_name) ?>" class="logo-image">
                    <?php else: ?>
                        <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($platform_name) ?></span>
                    <?php endif; ?>
                </a>
            </div>

            <button class="hamburger" id="hamburger" aria-label="Ouvrir le menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <ul class="nav-menu" id="nav-menu" role="menubar">
                <li role="none"><a href="index.php" class="nav-link" role="menuitem"><?= __('welcome') ?></a></li>
                <li role="none"><a href="courses.php" class="nav-link" role="menuitem"><?= __('courses') ?></a></li>
                <li role="none"><a href="shop.php" class="nav-link" role="menuitem"><?= __('shop') ?></a></li>
                <li role="none"><a href="upcoming_events.php" class="nav-link" role="menuitem"><?= __('events') ?></a></li>
                <li role="none"><a href="blog.php" class="nav-link" role="menuitem"><?= __('blog') ?></a></li>
                <li role="none"><a href="about.php" class="nav-link" role="menuitem"><?= __('about') ?></a></li>
                <li role="none"><a href="contact.php" class="nav-link" role="menuitem"><?= __('contact') ?></a></li>
                <li role="none" class="nav-cart">
                    <a href="basket.php" class="nav-link cart-link" role="menuitem" aria-label="Panier d'achat">
                        <i class="fas fa-shopping-cart" aria-hidden="true"></i>
                        <span class="cart-count" id="cart-count" aria-live="polite"></span>
                    </a>
                </li>
                <li role="none" class="nav-language">
                    <?php include '../../includes/public_language_switcher.php'; ?>
                </li>
            </ul>

            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user" aria-hidden="true"></i> <?= __('my_profile') ?>
                    </a>
                    <a href="../../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i> <?= __('login') ?>
                    </a>
                    <a href="../../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> <?= __('register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main id="main-content" role="main">
        <!-- Hero Section with Enhanced Features -->
        <section class="hero" role="banner">
            <div class="hero-background">
                <div class="hero-particles" id="hero-particles"></div>
            </div>
            <div class="hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">
                        <span class="hero-title-main"><?= __('welcome') ?> <?= __('courses') ?></span>
                        <span class="hero-title-sub"><?= htmlspecialchars($platform_name) ?></span>
                    </h1>
                    <p class="hero-description"><?= htmlspecialchars($platform_description) ?></p>
                    <div class="hero-actions">
                        <a href="courses.php" class="btn btn-primary btn-lg hero-btn">
                            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                            <span><?= __('discover_courses') ?></span>
                        </a>
                        <a href="shop.php" class="btn btn-secondary btn-lg hero-btn">
                            <i class="fas fa-shopping-bag" aria-hidden="true"></i>
                            <span><?= __('visit_shop') ?></span>
                        </a>
                    </div>
                </div>
                <div class="hero-visual">
                    <div class="hero-card">
                        <div class="hero-card-content">
                            <i class="fas fa-rocket" aria-hidden="true"></i>
                            <h3>Développez vos compétences</h3>
                            <p>Formations de qualité adaptées à vos besoins</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hero-scroll-indicator">
                <div class="scroll-arrow">
                    <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </div>
            </div>
        </section>

        <!-- Enhanced Statistics Section -->
        <section class="stats-section" role="region" aria-labelledby="stats-heading">
            <div class="container">
                <h2 id="stats-heading" class="section-title">Nos chiffres clés</h2>
                <div class="stats-grid">
                    <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" data-count="<?= $total_courses ?>">0</div>
                            <div class="stat-label">Formations disponibles</div>
                        </div>
                    </div>

                    <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                        <div class="stat-icon">
                            <i class="fas fa-users" aria-hidden="true"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" data-count="<?= $total_students ?>">0</div>
                            <div class="stat-label">Étudiants inscrits</div>
                        </div>
                    </div>

                    <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                        <div class="stat-icon">
                            <i class="fas fa-chalkboard-teacher" aria-hidden="true"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" data-count="<?= $total_instructors ?>">0</div>
                            <div class="stat-label">Instructeurs experts</div>
                        </div>
                    </div>

                    <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag" aria-hidden="true"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number" data-count="<?= $total_products ?>">0</div>
                            <div class="stat-label">Produits disponibles</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Certificate Verification Showcase -->
        <section class="certificate-showcase" role="region" aria-labelledby="certificate-heading">
            <div class="container">
                <div class="showcase-content">
                    <div class="showcase-text">
                        <h2 id="certificate-heading" class="section-title">Vérifiez vos certificats</h2>
                        <p class="showcase-description">
                            Tous nos certificats sont vérifiables en ligne pour garantir leur authenticité et leur valeur professionnelle.
                        </p>
                        <div class="showcase-features">
                            <div class="feature-item">
                                <i class="fas fa-shield-alt" aria-hidden="true"></i>
                                <span>Certificats sécurisés</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-check-circle" aria-hidden="true"></i>
                                <span>Vérification instantanée</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-globe" aria-hidden="true"></i>
                                <span>Reconnaissance internationale</span>
                            </div>
                        </div>
                        <a href="../verify_certificate.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-certificate" aria-hidden="true"></i>
                            Vérifier un certificat
                        </a>
                    </div>
                    <div class="showcase-visual">
                        <div class="certificate-preview">
                            <div class="certificate-card">
                                <div class="certificate-header">
                                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                                    <h3>Certificat de Formation</h3>
                                </div>
                                <div class="certificate-body">
                                    <p><strong>Nom:</strong> [Nom du participant]</p>
                                    <p><strong>Formation:</strong> [Titre de la formation]</p>
                                    <p><strong>Date:</strong> [Date d'obtention]</p>
                                    <div class="verification-code">
                                        <span>Code de vérification:</span>
                                        <code>VER-1234567890-ABC12345</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="testimonials-section" role="region" aria-labelledby="testimonials-heading">
            <div class="container">
                <h2 id="testimonials-heading" class="section-title">Ce que disent nos participants</h2>
                <div class="testimonials-slider" id="testimonials-slider">
                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <div class="testimonial-rating">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                            </div>
                            <blockquote>
                                "Une plateforme exceptionnelle qui m'a permis d'acquérir des compétences précieuses.
                                Les formations sont de qualité et les instructeurs très compétents."
                            </blockquote>
                            <div class="testimonial-author">
                                <div class="author-avatar">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                </div>
                                <div class="author-info">
                                    <h4>Marie Kouassi</h4>
                                    <p>Développeuse Web</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <div class="testimonial-rating">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                            </div>
                            <blockquote>
                                "Excellent service client et des cours très bien structurés.
                                J'ai pu progresser rapidement dans mon domaine grâce à cette plateforme."
                            </blockquote>
                            <div class="testimonial-author">
                                <div class="author-avatar">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                </div>
                                <div class="author-info">
                                    <h4>Kwame Asante</h4>
                                    <p>Chef de Projet</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="testimonial-card">
                        <div class="testimonial-content">
                            <div class="testimonial-rating">
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                                <i class="fas fa-star" aria-hidden="true"></i>
                            </div>
                            <blockquote>
                                "Les certificats obtenus sont reconnus et m'ont aidé à décrocher un meilleur emploi.
                                Je recommande vivement cette plateforme."
                            </blockquote>
                            <div class="testimonial-author">
                                <div class="author-avatar">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                </div>
                                <div class="author-info">
                                    <h4>Fatou Diallo</h4>
                                    <p>Analyste Financière</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Courses Section -->
        <section class="featured-courses" role="region" aria-labelledby="courses-heading">
            <div class="container">
                <div class="section-header">
                    <h2 id="courses-heading" class="section-title">Formations en vedette</h2>
                    <p class="section-subtitle">Découvrez nos formations les plus populaires</p>
                </div>

                <?php if ($featured_banner): ?>
                    <div class="courses-banner">
                        <img src="../../<?= htmlspecialchars($featured_banner) ?>"
                            alt="Bannière des formations"
                            class="banner-image">
                    </div>
                <?php endif; ?>

                <div class="courses-grid">
                    <?php if (!empty($featured_courses)): ?>
                        <?php foreach ($featured_courses as $course): ?>
                            <article class="course-card" data-aos="fade-up" data-aos-delay="<?= $loop->index * 100 ?>">
                                <div class="course-image-container">
                                    <?php if ($course['image_url']): ?>
                                        <img src="../../uploads/<?= htmlspecialchars($course['image_url']) ?>"
                                            alt="<?= htmlspecialchars($course['title']) ?>"
                                            class="course-image">
                                    <?php else: ?>
                                        <div class="course-image-placeholder">
                                            <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="course-badges">
                                        <?php if ($course['price'] <= 0): ?>
                                            <span class="badge badge-free">Gratuit</span>
                                        <?php endif; ?>
                                        <?php if ($course['avg_rating']): ?>
                                            <span class="badge badge-rating">
                                                <i class="fas fa-star" aria-hidden="true"></i>
                                                <?= round($course['avg_rating'], 1) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="course-content">
                                    <div class="course-meta">
                                        <span class="course-category"><?= htmlspecialchars($course['category'] ?? 'Formation') ?></span>
                                        <span class="course-level"><?= htmlspecialchars($course['level'] ?? 'Tous niveaux') ?></span>
                                    </div>

                                    <h3 class="course-title">
                                        <a href="view_course.php?id=<?= $course['id'] ?>">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </a>
                                    </h3>

                                    <div class="course-instructor">
                                        <i class="fas fa-user" aria-hidden="true"></i>
                                        <span><?= htmlspecialchars($course['instructor_name'] ?? 'Instructeur') ?></span>
                                    </div>

                                    <p class="course-description">
                                        <?= htmlspecialchars(substr($course['description'], 0, 120)) ?>...
                                    </p>

                                    <div class="course-stats">
                                        <div class="stat">
                                            <i class="fas fa-play-circle" aria-hidden="true"></i>
                                            <span><?= $course['lesson_count'] ?> leçons</span>
                                        </div>
                                        <?php if ($course['review_count']): ?>
                                            <div class="stat">
                                                <i class="fas fa-comments" aria-hidden="true"></i>
                                                <span><?= $course['review_count'] ?> avis</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="course-footer">
                                        <div class="course-price">
                                            <?php if ($course['price'] <= 0): ?>
                                                <span class="price-free">Gratuit</span>
                                            <?php else: ?>
                                                <span class="price-amount"><?= number_format($course['price'], 0, ',', ' ') ?> <?= $currency ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="course-actions">
                                            <a href="view_course.php?id=<?= $course['id'] ?>"
                                                class="btn btn-outline">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                                Voir
                                            </a>
                                            <a href="view_course.php?id=<?= $course['id'] ?>&enroll=1"
                                                class="btn btn-primary">
                                                <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                                                S'inscrire
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                            </div>
                            <h3>Aucune formation disponible</h3>
                            <p>De nouvelles formations seront bientôt ajoutées.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-footer">
                    <a href="courses.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-list" aria-hidden="true"></i>
                        Voir toutes les formations
                    </a>
                </div>
            </div>
        </section>

        <!-- Featured Products Section -->
        <section class="featured-products" role="region" aria-labelledby="products-heading">
            <div class="container">
                <div class="section-header">
                    <h2 id="products-heading" class="section-title">Produits en vedette</h2>
                    <p class="section-subtitle">Découvrez nos produits les plus populaires</p>
                </div>

                <div class="products-grid">
                    <?php if (!empty($featured_products)): ?>
                        <?php foreach ($featured_products as $product): ?>
                            <article class="product-card" data-aos="fade-up" data-aos-delay="<?= $loop->index * 100 ?>">
                                <div class="product-image-container">
                                    <?php if ($product['image_url']): ?>
                                        <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>"
                                            alt="<?= htmlspecialchars($product['name']) ?>"
                                            class="product-image">
                                    <?php else: ?>
                                        <div class="product-image-placeholder">
                                            <i class="fas fa-shopping-bag" aria-hidden="true"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="product-badges">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <span class="badge badge-stock">En stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-out">Rupture</span>
                                        <?php endif; ?>
                                        <?php if ($product['order_count'] > 0): ?>
                                            <span class="badge badge-popular">Populaire</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="product-content">
                                    <div class="product-meta">
                                        <span class="product-category"><?= htmlspecialchars($product['category'] ?? 'Produit') ?></span>
                                    </div>

                                    <h3 class="product-title">
                                        <a href="view_product.php?id=<?= $product['id'] ?>">
                                            <?= htmlspecialchars($product['name']) ?>
                                        </a>
                                    </h3>

                                    <div class="product-vendor">
                                        <i class="fas fa-store" aria-hidden="true"></i>
                                        <span><?= htmlspecialchars($product['vendor_name'] ?? 'Vendeur') ?></span>
                                    </div>

                                    <p class="product-description">
                                        <?= htmlspecialchars(substr($product['description'], 0, 120)) ?>...
                                    </p>

                                    <div class="product-footer">
                                        <div class="product-price">
                                            <span class="price-amount"><?= number_format($product['price'], 0, ',', ' ') ?> <?= $currency ?></span>
                                        </div>

                                        <div class="product-actions">
                                            <a href="view_product.php?id=<?= $product['id'] ?>"
                                                class="btn btn-outline">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                                Voir
                                            </a>
                                            <?php if ($product['stock_quantity'] > 0): ?>
                                                <button onclick="addProductToCart(<?= $product['id'] ?>, this)"
                                                    class="btn btn-primary add-to-cart-btn">
                                                    <i class="fas fa-cart-plus" aria-hidden="true"></i>
                                                    Ajouter
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-disabled" disabled>
                                                    <i class="fas fa-times" aria-hidden="true"></i>
                                                    Indisponible
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-shopping-bag" aria-hidden="true"></i>
                            </div>
                            <h3>Aucun produit disponible</h3>
                            <p>De nouveaux produits seront bientôt ajoutés.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="section-footer">
                    <a href="shop.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-shopping-bag" aria-hidden="true"></i>
                        Visiter la boutique
                    </a>
                </div>
            </div>
        </section>

        <!-- Newsletter Subscription -->
        <section class="newsletter-section" role="region" aria-labelledby="newsletter-heading">
            <div class="container">
                <div class="newsletter-content">
                    <div class="newsletter-text">
                        <h2 id="newsletter-heading">Restez informé</h2>
                        <p>Recevez nos dernières actualités, formations et offres spéciales directement dans votre boîte mail.</p>
                    </div>
                    <form class="newsletter-form" id="newsletter-form">
                        <div class="form-group">
                            <input type="email"
                                id="newsletter-email"
                                name="email"
                                placeholder="Votre adresse email"
                                required
                                aria-label="Adresse email pour la newsletter">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane" aria-hidden="true"></i>
                                S'abonner
                            </button>
                        </div>
                        <div class="form-message" id="newsletter-message" aria-live="polite"></div>
                    </form>
                </div>
            </div>
        </section>

        <!-- Upcoming Events Section -->
        <?php if (!empty($upcoming_events)): ?>
            <section class="upcoming-events" role="region" aria-labelledby="events-heading">
                <div class="container">
                    <div class="section-header">
                        <h2 id="events-heading" class="section-title">Événements à venir</h2>
                        <p class="section-subtitle">Participez à nos événements et formations en direct</p>
                    </div>

                    <div class="events-grid">
                        <?php foreach ($upcoming_events as $event): ?>
                            <article class="event-card" data-aos="fade-up">
                                <div class="event-date">
                                    <div class="date-day"><?= date('d', strtotime($event['event_date'])) ?></div>
                                    <div class="date-month"><?= date('M', strtotime($event['event_date'])) ?></div>
                                    <div class="date-year"><?= date('Y', strtotime($event['event_date'])) ?></div>
                                </div>

                                <div class="event-content">
                                    <h3 class="event-title">
                                        <a href="view_event.php?id=<?= $event['id'] ?>">
                                            <?= htmlspecialchars($event['title']) ?>
                                        </a>
                                    </h3>

                                    <div class="event-meta">
                                        <div class="event-organizer">
                                            <i class="fas fa-user" aria-hidden="true"></i>
                                            <span><?= htmlspecialchars($event['organizer_name'] ?? 'Organisateur') ?></span>
                                        </div>
                                        <?php if ($event['location']): ?>
                                            <div class="event-location">
                                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                                <span><?= htmlspecialchars($event['location']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="event-time">
                                            <i class="fas fa-clock" aria-hidden="true"></i>
                                            <span><?= date('H:i', strtotime($event['event_date'])) ?></span>
                                        </div>
                                    </div>

                                    <p class="event-description">
                                        <?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...
                                    </p>

                                    <div class="event-actions">
                                        <a href="view_event.php?id=<?= $event['id'] ?>"
                                            class="btn btn-outline">
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                            Détails
                                        </a>
                                        <a href="register_event.php?id=<?= $event['id'] ?>"
                                            class="btn btn-primary">
                                            <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                                            S'inscrire
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-footer">
                        <a href="upcoming_events.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-calendar-alt" aria-hidden="true"></i>
                            Voir tous les événements
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Blog Section -->
        <?php if (!empty($blog_posts)): ?>
            <section class="blog-section" role="region" aria-labelledby="blog-heading">
                <div class="container">
                    <div class="section-header">
                        <h2 id="blog-heading" class="section-title">Actualités & Blog</h2>
                        <p class="section-subtitle">Découvrez nos derniers articles et actualités</p>
                    </div>

                    <div class="blog-grid">
                        <?php foreach ($blog_posts as $post): ?>
                            <article class="blog-card" data-aos="fade-up">
                                <div class="blog-image-container">
                                    <div class="blog-date">
                                        <span class="date-day"><?= date('d', strtotime($post['created_at'])) ?></span>
                                        <span class="date-month"><?= date('M', strtotime($post['created_at'])) ?></span>
                                    </div>
                                </div>

                                <div class="blog-content">
                                    <h3 class="blog-title">
                                        <a href="view_blog.php?id=<?= $post['id'] ?>">
                                            <?= htmlspecialchars($post['title']) ?>
                                        </a>
                                    </h3>

                                    <p class="blog-excerpt">
                                        <?= htmlspecialchars(substr($post['content'], 0, 150)) ?>...
                                    </p>

                                    <div class="blog-meta">
                                        <span class="blog-date">
                                            <i class="fas fa-calendar" aria-hidden="true"></i>
                                            <?= date('d/m/Y', strtotime($post['created_at'])) ?>
                                        </span>
                                    </div>

                                    <a href="view_blog.php?id=<?= $post['id'] ?>" class="btn btn-outline">
                                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                                        Lire l'article
                                    </a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="section-footer">
                        <a href="blog.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-newspaper" aria-hidden="true"></i>
                            Voir tous les articles
                        </a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Enhanced Footer -->
    <footer class="footer" role="contentinfo">
        <!-- Professional Services Section -->
        <section class="professional-services">
            <div class="container">
                <div class="services-content">
                    <div class="services-text">
                        <h2>Services professionnels d'excellence</h2>
                        <p>Nous offrons des solutions complètes pour le développement des compétences et la croissance professionnelle.</p>
                        <div class="services-features">
                            <div class="service-feature">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Solutions de formation personnalisées</span>
                            </div>
                            <div class="service-feature">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Certification professionnelle reconnue</span>
                            </div>
                            <div class="service-feature">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Accompagnement stratégique</span>
                            </div>
                            <div class="service-feature">
                                <i class="fas fa-check" aria-hidden="true"></i>
                                <span>Support technique 24/7</span>
                            </div>
                        </div>
                        <div class="services-actions">
                            <a href="contact.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-phone" aria-hidden="true"></i>
                                Demander un devis
                            </a>
                            <a href="about.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-info-circle" aria-hidden="true"></i>
                                En savoir plus
                            </a>
                        </div>
                    </div>
                    <div class="services-visual">
                        <div class="services-icon">
                            <i class="fas fa-building" aria-hidden="true"></i>
                        </div>
                        <h3>Solutions entreprise</h3>
                    </div>
                </div>
            </div>
        </section>

        <div class="footer-content">
            <div class="footer-section">
                <h3>
                    <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                    <?= htmlspecialchars($platform_name) ?>
                </h3>
                <p><?= htmlspecialchars($platform_description) ?></p>
                <div class="social-links">
                    <a href="#" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fab fa-twitter" aria-hidden="true"></i></a>
                    <a href="#" aria-label="LinkedIn"><i class="fab fa-linkedin" aria-hidden="true"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true"></i></a>
                </div>
            </div>

            <div class="footer-section">
                <h3>Liens rapides</h3>
                <ul>
                    <li><a href="courses.php"><?= __('courses') ?></a></li>
                    <li><a href="shop.php"><?= __('shop') ?></a></li>
                    <li><a href="upcoming_events.php"><?= __('events') ?></a></li>
                    <li><a href="blog.php"><?= __('blog') ?></a></li>
                    <li><a href="about.php"><?= __('about') ?></a></li>
                    <li><a href="contact.php"><?= __('contact') ?></a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Support</h3>
                <ul>
                    <li><a href="contact.php"><?= __('help_support') ?></a></li>
                    <li><a href="about.php"><?= __('about_us') ?></a></li>
                    <li><a href="../auth/register.php"><?= __('create_account') ?></a></li>
                    <li><a href="../auth/login.php"><?= __('connect') ?></a></li>
                    <li><a href="../verify_certificate.php">Vérifier un certificat</a></li>
                </ul>
            </div>

            <div class="footer-section">
                <h3>Informations de contact</h3>
                <div class="contact-info">
                    <p><i class="fas fa-envelope" aria-hidden="true"></i> <?= htmlspecialchars($contact_email) ?></p>
                    <p><i class="fas fa-phone" aria-hidden="true"></i> <?= htmlspecialchars($contact_phone) ?></p>
                    <p><i class="fas fa-map-marker-alt" aria-hidden="true"></i> <?= htmlspecialchars($address) ?></p>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-content">
                    <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($platform_name) ?>. Tous droits réservés.</p>
                    <div class="footer-links">
                        <a href="privacy.php">Politique de confidentialité</a>
                        <a href="terms.php">Conditions d'utilisation</a>
                        <a href="cookies.php">Politique des cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="back-to-top" class="back-to-top" aria-label="Retour en haut">
        <i class="fas fa-chevron-up" aria-hidden="true"></i>
    </button>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        // Initialize AOS (Animate On Scroll)
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });

        // Loading screen
        window.addEventListener('load', function() {
            const loadingScreen = document.getElementById('loading-screen');
            if (loadingScreen) {
                loadingScreen.style.opacity = '0';
                setTimeout(() => {
                    loadingScreen.style.display = 'none';
                }, 500);
            }
        });

        // Hamburger menu functionality
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('nav-menu');

        if (hamburger && navMenu) {
            hamburger.addEventListener('click', () => {
                hamburger.classList.toggle('active');
                navMenu.classList.toggle('active');
                hamburger.setAttribute('aria-expanded',
                    hamburger.getAttribute('aria-expanded') === 'false' ? 'true' : 'false'
                );
            });

            // Close menu when clicking on a link
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    hamburger.classList.remove('active');
                    navMenu.classList.remove('active');
                    hamburger.setAttribute('aria-expanded', 'false');
                });
            });

            // Close menu when clicking outside
            document.addEventListener('click', (e) => {
                if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                    hamburger.classList.remove('active');
                    navMenu.classList.remove('active');
                    hamburger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        // Counter animation for statistics
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number[data-count]');
            counters.forEach(counter => {
                const target = parseInt(counter.getAttribute('data-count'));
                const duration = 2000;
                const increment = target / (duration / 16);
                let current = 0;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current).toLocaleString();
                }, 16);
            });
        }

        // Intersection Observer for counter animation
        const statsSection = document.querySelector('.stats-section');
        if (statsSection) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        animateCounters();
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.5
            });

            observer.observe(statsSection);
        }

        // Back to top button
        const backToTop = document.getElementById('back-to-top');
        if (backToTop) {
            window.addEventListener('scroll', () => {
                if (window.pageYOffset > 300) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            });

            backToTop.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        }

        // Newsletter subscription
        const newsletterForm = document.getElementById('newsletter-form');
        if (newsletterForm) {
            newsletterForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const email = document.getElementById('newsletter-email').value;
                const message = document.getElementById('newsletter-message');

                // Simulate newsletter subscription
                message.innerHTML = '<i class="fas fa-check-circle"></i> Merci pour votre inscription !';
                message.className = 'form-message success';

                // Reset form
                newsletterForm.reset();

                // Hide message after 5 seconds
                setTimeout(() => {
                    message.innerHTML = '';
                    message.className = 'form-message';
                }, 5000);
            });
        }

        // Testimonials slider
        const testimonialsSlider = document.getElementById('testimonials-slider');
        if (testimonialsSlider) {
            let currentSlide = 0;
            const slides = testimonialsSlider.querySelectorAll('.testimonial-card');
            const totalSlides = slides.length;

            function showSlide(index) {
                slides.forEach((slide, i) => {
                    slide.style.display = i === index ? 'block' : 'none';
                });
            }

            function nextSlide() {
                currentSlide = (currentSlide + 1) % totalSlides;
                showSlide(currentSlide);
            }

            // Initialize slider
            showSlide(0);

            // Auto-advance slides every 5 seconds
            setInterval(nextSlide, 5000);
        }

        // Cart functionality
        function updateCartCount(count) {
            const cartCount = document.getElementById('cart-count');
            if (cartCount) {
                cartCount.textContent = count;
                cartCount.style.display = count > 0 ? 'inline' : 'none';
            }
        }

        // Add product to cart
        function addProductToCart(productId, btnEl) {
            const button = btnEl;
            const original = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Ajout...';
            button.disabled = true;

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
                    button.disabled = false;
                });
        }

        // Notification system
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;

            document.body.appendChild(notification);

            // Animate in
            setTimeout(() => notification.classList.add('show'), 100);

            // Remove after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Initialize cart count on page load
        document.addEventListener('DOMContentLoaded', function() {
            fetch('get_cart_count.php')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        updateCartCount(data.cart_count);
                    }
                })
                .catch(() => {});
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Lazy loading for images
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    </script>
</body>

</html>