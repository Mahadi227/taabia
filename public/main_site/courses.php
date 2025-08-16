<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get all categories for filter
try {
    $categories = $pdo->query("
        SELECT DISTINCT category 
        FROM courses 
        WHERE status = 'published' AND category IS NOT NULL AND category != ''
        ORDER BY category
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}

// Handle search and filter
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$level_filter = $_GET['level'] ?? '';

// Build query with filters
$where_conditions = ["c.status = 'published'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.category = ?";
    $params[] = $category_filter;
}

if (!empty($level_filter)) {
    $where_conditions[] = "c.level = ?";
    $params[] = $level_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get courses with instructor information and filters
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS instructor_name 
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        WHERE $where_clause
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll();
} catch (PDOException $e) {
    $courses = [];
}

// Get course levels for filter
$levels = ['beginner', 'intermediate', 'advanced'];
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('page_title') ?> | TaaBia</title>
    <link rel="stylesheet" href="main-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #009688;
            --primary-light: #4db6ac;
            --primary-dark: #00695c;
            --secondary-color: #00bcd4;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --text-primary: #212121;
            --text-secondary: #757575;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #fafafa;
            --border-color: #e0e0e0;
            --border-radius: 12px;
            --border-radius-sm: 6px;
            --shadow-light: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 8px rgba(0,0,0,0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

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
            color: #00796b;
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
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-actions {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }

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

        .section-title h1 {
            font-size: 2.25rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .section-title p {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }

        /* Search and Filter Styles */
        .search-filter-section {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-xl);
        }

        .search-filter-form {
            display: grid;
            grid-template-columns: 1fr auto auto auto;
            gap: var(--spacing-md);
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .form-control {
            padding: var(--spacing-sm) var(--spacing-md);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .search-btn {
            background: var(--primary-color);
            color: var(--text-white);
            border: none;
            padding: var(--spacing-sm) var(--spacing-lg);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .search-btn:hover {
            background: var(--primary-dark);
        }

        .clear-btn {
            background: var(--text-secondary);
            color: var(--text-white);
            border: none;
            padding: var(--spacing-sm) var(--spacing-lg);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }

        .clear-btn:hover {
            background: var(--text-primary);
        }

        .results-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--bg-primary);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-light);
        }

        .results-count {
            font-weight: 600;
            color: var(--text-primary);
        }

        .active-filters {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }

        .filter-tag {
            background: var(--primary-light);
            color: var(--text-white);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
        }

        .courses {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        .course {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .course:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-4px);
        }

        .course-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .course-content {
            padding: var(--spacing-lg);
        }

        .course-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .course-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }

        .course-description {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.5;
        }

        .course-instructor {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-md);
        }

        .course-stats {
            display: flex;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .course-category {
            background: var(--primary-light);
            color: var(--text-white);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: var(--spacing-sm);
        }

        .course-level {
            background: var(--warning-color);
            color: var(--text-white);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
            margin-bottom: var(--spacing-sm);
            margin-left: var(--spacing-sm);
        }

        .w-100 { width: 100%; }
        .text-center { text-align: center; }
        .mb-4 { margin-bottom: var(--spacing-lg); }
        .mt-5 { margin-top: var(--spacing-xl); }

        /* Hamburger Menu Styles */
        .hamburger {
            display: none;
            flex-direction: column;
            cursor: pointer;
            background: none;
            border: none;
            padding: var(--spacing-sm);
        }

        .hamburger span {
            width: 25px;
            height: 3px;
            background: var(--text-primary);
            margin: 3px 0;
            transition: var(--transition);
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

        @media (max-width: 768px) {
            .hamburger {
                display: flex;
            }
            
            .navbar {
                flex-direction: column;
                gap: var(--spacing-md);
                padding: var(--spacing-md);
            }
            
            .nav-menu {
                display: none;
                flex-direction: column;
                gap: var(--spacing-md);
                width: 100%;
                text-align: center;
            }
            
            .nav-menu.active {
                display: flex;
            }
            
            .nav-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .search-filter-form {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
            
            .courses {
                grid-template-columns: 1fr;
            }
            
            .results-info {
                flex-direction: column;
                gap: var(--spacing-md);
                align-items: flex-start;
            }
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
                <li><a href="index.php" class="nav-link"><?= __('nav_home') ?></a></li>
                <li><a href="courses.php" class="nav-link"><?= __('nav_courses') ?></a></li>
                <li><a href="shop.php" class="nav-link"><?= __('nav_shop') ?></a></li>
                <li><a href="upcoming_events.php" class="nav-link"><?= __('nav_events') ?></a></li>
                <li><a href="blog.php" class="nav-link"><?= __('nav_blog') ?></a></li>
                <li><a href="about.php" class="nav-link"><?= __('nav_about') ?></a></li>
                <li><a href="contact.php" class="nav-link"><?= __('nav_contact') ?></a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>
            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> <?= __('nav_my_account') ?>
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('nav_logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('nav_login') ?>
                    </a>
                    <a href="../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?= __('nav_register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h1><i class="fas fa-graduation-cap"></i> <?= __('main_title') ?></h1>
                <p><?= __('main_description') ?></p>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-filter-section">
                <form method="GET" class="search-filter-form">
                    <div class="form-group">
                        <label for="search">Rechercher des cours</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>" 
                               class="form-control" 
                               placeholder="Rechercher par titre ou description...">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Catégorie</label>
                        <select id="category" name="category" class="form-control">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" 
                                        <?= $category_filter === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="level">Niveau</label>
                        <select id="level" name="level" class="form-control">
                            <option value="">Tous les niveaux</option>
                            <?php foreach ($levels as $level): ?>
                                <option value="<?= htmlspecialchars($level) ?>" 
                                        <?= $level_filter === $level ? 'selected' : '' ?>>
                                    <?= ucfirst(htmlspecialchars($level)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="search-btn">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($category_filter) || !empty($level_filter)): ?>
                    <div style="margin-top: var(--spacing-md); text-align: center;">
                        <a href="courses.php" class="clear-btn">
                            <i class="fas fa-times"></i> Effacer les filtres
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Results Info -->
            <div class="results-info">
                <div class="results-count">
                    <?= count($courses) ?> cours trouvé<?= count($courses) > 1 ? 's' : '' ?>
                </div>
                <div class="active-filters">
                    <?php if (!empty($search)): ?>
                        <span class="filter-tag">Recherche: "<?= htmlspecialchars($search) ?>"</span>
                    <?php endif; ?>
                    <?php if (!empty($category_filter)): ?>
                        <span class="filter-tag">Catégorie: <?= htmlspecialchars($category_filter) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($level_filter)): ?>
                        <span class="filter-tag">Niveau: <?= ucfirst(htmlspecialchars($level_filter)) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="courses">
                <?php if (!empty($courses)): ?>
                    <?php foreach ($courses as $course): ?>
                        <div class="course">
                            <?php if (!empty($course['image_url'])): ?>
                                <img src="../../uploads/<?= htmlspecialchars($course['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($course['title']) ?>" 
                                     class="course-image">
                            <?php else: ?>
                                <div class="course-image" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-graduation-cap" style="font-size: 3rem; color: white;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="course-content">
                                <h3 class="course-title"><?= htmlspecialchars($course['title']) ?></h3>
                                
                                <div style="margin-bottom: var(--spacing-sm);">
                                    <?php if (!empty($course['category'])): ?>
                                        <span class="course-category"><?= htmlspecialchars($course['category']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($course['level'])): ?>
                                        <span class="course-level"><?= ucfirst(htmlspecialchars($course['level'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-instructor">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($course['instructor_name'] ?? __('instructor')) ?>
                                </div>
                                
                                <div class="course-stats">
                                    <span><i class="fas fa-calendar"></i> <?= __('course_created_at') ?> <?= date('d/m/Y', strtotime($course['created_at'])) ?></span>
                                    <?php if (!empty($course['duration'])): ?>
                                        <span><i class="fas fa-clock"></i> <?= htmlspecialchars($course['duration']) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="course-price"><?= number_format($course['price'], 2) ?> GHS</div>
                                
                                <p class="course-description">
                                    <?= htmlspecialchars(substr($course['description'], 0, 150)) ?>...
                                </p>
                                
                                <a href="view_course.php?id=<?= $course['id'] ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-eye"></i> <?= __('view_details') ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                        <i class="fas fa-search" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></i>
                        <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">
                            <?= !empty($search) || !empty($category_filter) || !empty($level_filter) ? 'Aucun cours trouvé' : __('no_courses_available') ?>
                        </h3>
                        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                            <?= !empty($search) || !empty($category_filter) || !empty($level_filter) ? 'Essayez de modifier vos critères de recherche.' : __('no_courses_description') ?>
                        </p>
                        <?php if (!empty($search) || !empty($category_filter) || !empty($level_filter)): ?>
                            <a href="courses.php" class="btn btn-primary">
                                <i class="fas fa-undo"></i> Voir tous les cours
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                    <p><?= __('footer_description') ?></p>
                    <p><?= __('footer_mission') ?></p>
                </div>
                
                <div class="footer-section">
                    <h3><?= __('footer_services') ?></h3>
                    <a href="courses.php"><?= __('footer_services_courses') ?></a>
                    <a href="shop.php"><?= __('footer_services_shop') ?></a>
                    <a href="upcoming_events.php"><?= __('footer_services_events') ?></a>
                    <a href="contact.php"><?= __('footer_services_support') ?></a>
                </div>
                
                <div class="footer-section">
                    <h3><?= __('footer_contact') ?></h3>
                    <p><i class="fas fa-envelope"></i> <?= __('footer_email') ?></p>
                    <p><i class="fas fa-phone"></i> +233534918333</p>
                    <p><i class="fas fa-map-marker-alt"></i> <?= __('footer_address') ?></p>
                </div>
                
                <div class="footer-section">
                    <h3><?= __('footer_follow_us') ?></h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. <?= __('footer_rights_reserved') ?>.</p>
            </div>
        </div>
    </footer>

    <style>
        .footer {
            background: #00796b;
            color: #ffffff;
            padding: var(--spacing-2xl) 0;
            margin-top: var(--spacing-2xl);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-xl);
        }

        .footer-section h3 {
            margin-bottom: var(--spacing-md);
            color: #ffffffff;
        }

        .footer-section p, .footer-section a {
            color: #ffffff;
            text-decoration: none;
            margin-bottom: var(--spacing-sm);
            display: block;
        }

        .footer-section a:hover {
            color: var(--primary-light);
        }

        .footer-bottom {
            border-top: 1px solid var(--text-secondary);
            padding-top: var(--spacing-lg);
            margin-top: var(--spacing-xl);
            text-align: center;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>

         <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
     <script>
         // Initialize AOS
         AOS.init({
             duration: 800,
             easing: 'ease-in-out',
             once: true,
             offset: 100
         });

         // Hamburger menu functionality
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

        // Dynamic search and filter functionality
        let searchTimeout;
        const searchInput = document.getElementById('search');
        const categorySelect = document.getElementById('category');
        const levelSelect = document.getElementById('level');
        const coursesContainer = document.querySelector('.courses');
        const resultsCount = document.querySelector('.results-count');
        const activeFilters = document.querySelector('.active-filters');

        // Real-time search with debouncing
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500);
        });

        // Auto-submit form when filters change
        categorySelect.addEventListener('change', function() {
            performSearch();
        });

        levelSelect.addEventListener('change', function() {
            performSearch();
        });

        // Perform search using AJAX
        function performSearch() {
            const searchValue = searchInput.value;
            const categoryValue = categorySelect.value;
            const levelValue = levelSelect.value;

            // Show loading state
            coursesContainer.innerHTML = '<div class="loading" style="grid-column: 1 / -1; text-align: center; padding: 2rem;"><i class="fas fa-spinner fa-spin" style="font-size: 2rem; color: var(--primary-color);"></i><p style="margin-top: 1rem;">Recherche en cours...</p></div>';

            // Create URL with parameters
            const params = new URLSearchParams();
            if (searchValue) params.append('search', searchValue);
            if (categoryValue) params.append('category', categoryValue);
            if (levelValue) params.append('level', levelValue);

            // Fetch results
            fetch(`courses_ajax.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    updateResults(data);
                })
                .catch(error => {
                    console.error('Error:', error);
                    coursesContainer.innerHTML = '<div class="error" style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--danger-color);"><i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i><p style="margin-top: 1rem;">Erreur lors de la recherche</p></div>';
                });
        }

        // Update results display
        function updateResults(data) {
            // Update results count
            resultsCount.textContent = `${data.count} cours trouvé${data.count > 1 ? 's' : ''}`;

            // Update active filters
            updateActiveFilters(data.filters);

            // Update courses display
            if (data.courses.length > 0) {
                coursesContainer.innerHTML = data.courses.map(course => `
                    <div class="course" data-aos="fade-up">
                        ${course.image_url ? 
                            `<img src="../../uploads/${course.image_url}" alt="${course.title}" class="course-image">` :
                            `<div class="course-image" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-graduation-cap" style="font-size: 3rem; color: white;"></i>
                            </div>`
                        }
                        <div class="course-content">
                            <h3 class="course-title">${course.title}</h3>
                            <div style="margin-bottom: var(--spacing-sm);">
                                ${course.category ? `<span class="course-category">${course.category}</span>` : ''}
                                ${course.level ? `<span class="course-level">${course.level.charAt(0).toUpperCase() + course.level.slice(1)}</span>` : ''}
                            </div>
                            <div class="course-instructor">
                                <i class="fas fa-user"></i> ${course.instructor_name || 'Instructeur'}
                            </div>
                            <div class="course-stats">
                                <span><i class="fas fa-calendar"></i> Créé le ${new Date(course.created_at).toLocaleDateString('fr-FR')}</span>
                                ${course.duration ? `<span><i class="fas fa-clock"></i> ${course.duration}</span>` : ''}
                            </div>
                            <div class="course-price">${parseFloat(course.price).toLocaleString('fr-FR', {minimumFractionDigits: 2})} GHS</div>
                            <p class="course-description">${course.description.substring(0, 150)}...</p>
                            <a href="view_course.php?id=${course.id}" class="btn btn-primary w-100">
                                <i class="fas fa-eye"></i> Voir les détails
                            </a>
                        </div>
                    </div>
                `).join('');
            } else {
                coursesContainer.innerHTML = `
                    <div class="text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                        <i class="fas fa-search" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></i>
                        <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">
                            ${data.filters.search || data.filters.category || data.filters.level ? 'Aucun cours trouvé' : 'Aucun cours disponible'}
                        </h3>
                        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                            ${data.filters.search || data.filters.category || data.filters.level ? 'Essayez de modifier vos critères de recherche.' : 'Aucun cours n\'est disponible pour le moment.'}
                        </p>
                        ${data.filters.search || data.filters.category || data.filters.level ? 
                            '<a href="courses.php" class="btn btn-primary"><i class="fas fa-undo"></i> Voir tous les cours</a>' : ''
                        }
                    </div>
                `;
            }

            // Add animation classes
            setTimeout(() => {
                document.querySelectorAll('.course').forEach((course, index) => {
                    course.style.animationDelay = `${index * 0.1}s`;
                    course.classList.add('fade-in');
                });
            }, 100);
        }

        // Update active filters display
        function updateActiveFilters(filters) {
            const filterTags = [];
            if (filters.search) filterTags.push(`<span class="filter-tag">Recherche: "${filters.search}"</span>`);
            if (filters.category) filterTags.push(`<span class="filter-tag">Catégorie: ${filters.category}</span>`);
            if (filters.level) filterTags.push(`<span class="filter-tag">Niveau: ${filters.level.charAt(0).toUpperCase() + filters.level.slice(1)}</span>`);
            
            activeFilters.innerHTML = filterTags.join('');
        }

                 // Add smooth animations and enhanced features
         document.addEventListener('DOMContentLoaded', function() {
             // Add fade-in animation for courses with staggered effect
             const courses = document.querySelectorAll('.course');
             courses.forEach((course, index) => {
                 course.style.opacity = '0';
                 course.style.transform = 'translateY(30px)';
                 course.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
                 
                 setTimeout(() => {
                     course.style.opacity = '1';
                     course.style.transform = 'translateY(0)';
                 }, index * 150);
             });

             // Add hover effects for course cards
             courses.forEach(course => {
                 course.addEventListener('mouseenter', function() {
                     this.style.transform = 'translateY(-8px) scale(1.02)';
                     this.style.boxShadow = '0 12px 24px rgba(0,0,0,0.15)';
                 });
                 
                 course.addEventListener('mouseleave', function() {
                     this.style.transform = 'translateY(0) scale(1)';
                     this.style.boxShadow = 'var(--shadow-light)';
                 });
             });

             // Add loading animation for search
             const searchBtn = document.querySelector('.search-btn');
             if (searchBtn) {
                 searchBtn.addEventListener('click', function() {
                     this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recherche...';
                     this.disabled = true;
                     
                     setTimeout(() => {
                         this.innerHTML = '<i class="fas fa-search"></i> Rechercher';
                         this.disabled = false;
                     }, 2000);
                 });
             }

             // Add smooth scroll to top functionality
             const scrollToTopBtn = document.createElement('button');
             scrollToTopBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
             scrollToTopBtn.className = 'scroll-to-top';
             scrollToTopBtn.style.cssText = `
                 position: fixed;
                 bottom: 20px;
                 right: 20px;
                 width: 50px;
                 height: 50px;
                 border-radius: 50%;
                 background: var(--primary-color);
                 color: white;
                 border: none;
                 cursor: pointer;
                 opacity: 0;
                 visibility: hidden;
                 transition: all 0.3s ease;
                 z-index: 1000;
                 box-shadow: 0 4px 12px rgba(0,0,0,0.15);
             `;
             document.body.appendChild(scrollToTopBtn);

             // Show/hide scroll to top button
             window.addEventListener('scroll', function() {
                 if (window.pageYOffset > 300) {
                     scrollToTopBtn.style.opacity = '1';
                     scrollToTopBtn.style.visibility = 'visible';
                 } else {
                     scrollToTopBtn.style.opacity = '0';
                     scrollToTopBtn.style.visibility = 'hidden';
                 }
             });

             // Scroll to top functionality
             scrollToTopBtn.addEventListener('click', function() {
                 window.scrollTo({
                     top: 0,
                     behavior: 'smooth'
                 });
             });

             // Add search suggestions functionality
             const searchSuggestions = [
                 'Développement web', 'JavaScript', 'PHP', 'Python', 'Design', 
                 'Marketing', 'Business', 'Photographie', 'Musique', 'Langues'
             ];

             // Create suggestions dropdown
             const suggestionsDropdown = document.createElement('div');
             suggestionsDropdown.className = 'search-suggestions';
             suggestionsDropdown.style.cssText = `
                 position: absolute;
                 top: 100%;
                 left: 0;
                 right: 0;
                 background: white;
                 border: 1px solid var(--border-color);
                 border-radius: var(--border-radius-sm);
                 box-shadow: var(--shadow-medium);
                 z-index: 1000;
                 max-height: 200px;
                 overflow-y: auto;
                 display: none;
             `;
             
             const searchContainer = searchInput.parentElement;
             searchContainer.style.position = 'relative';
             searchContainer.appendChild(suggestionsDropdown);

             // Show suggestions on focus
             searchInput.addEventListener('focus', function() {
                 if (this.value.length < 2) {
                     showSuggestions(searchSuggestions);
                 }
             });

             // Show suggestions on input
             searchInput.addEventListener('input', function() {
                 if (this.value.length >= 2) {
                     const filtered = searchSuggestions.filter(suggestion => 
                         suggestion.toLowerCase().includes(this.value.toLowerCase())
                     );
                     showSuggestions(filtered);
                 } else if (this.value.length === 0) {
                     showSuggestions(searchSuggestions);
                 } else {
                     hideSuggestions();
                 }
             });

             function showSuggestions(suggestions) {
                 if (suggestions.length === 0) {
                     hideSuggestions();
                     return;
                 }
                 
                 suggestionsDropdown.innerHTML = suggestions.map(suggestion => 
                     `<div class="suggestion-item" style="padding: 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; transition: background 0.2s ease;">${suggestion}</div>`
                 ).join('');
                 
                 suggestionsDropdown.style.display = 'block';
                 
                 // Add click handlers
                 suggestionsDropdown.querySelectorAll('.suggestion-item').forEach(item => {
                     item.addEventListener('click', function() {
                         searchInput.value = this.textContent;
                         hideSuggestions();
                         performSearch();
                     });
                     
                     item.addEventListener('mouseenter', function() {
                         this.style.background = '#f8f9fa';
                     });
                     
                     item.addEventListener('mouseleave', function() {
                         this.style.background = 'white';
                     });
                 });
             }

             function hideSuggestions() {
                 suggestionsDropdown.style.display = 'none';
             }

             // Hide suggestions when clicking outside
             document.addEventListener('click', function(e) {
                 if (!searchContainer.contains(e.target)) {
                     hideSuggestions();
                 }
             });

            // Add fade-in animation for courses
            const courses = document.querySelectorAll('.course');
            courses.forEach((course, index) => {
                course.style.opacity = '0';
                course.style.transform = 'translateY(20px)';
                course.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                
                setTimeout(() => {
                    course.style.opacity = '1';
                    course.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
            
            // Escape to clear search
            if (e.key === 'Escape' && document.activeElement === searchInput) {
                searchInput.value = '';
                performSearch();
            }
        });

                 // Add advanced search features
         let searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]');
         
         // Add to search history
         function addToSearchHistory(term) {
             if (term && !searchHistory.includes(term)) {
                 searchHistory.unshift(term);
                 searchHistory = searchHistory.slice(0, 5); // Keep only last 5 searches
                 localStorage.setItem('searchHistory', JSON.stringify(searchHistory));
             }
         }

         // Show search history
         function showSearchHistory() {
             if (searchHistory.length > 0) {
                 const historyItems = searchHistory.map(term => 
                     `<div class="history-item" style="padding: 8px 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; font-size: 0.9em; color: #666;">
                         <i class="fas fa-history" style="margin-right: 8px;"></i>${term}
                     </div>`
                 ).join('');
                 
                 suggestionsDropdown.innerHTML = `
                     <div style="padding: 8px 10px; font-weight: 600; color: #333; border-bottom: 2px solid var(--primary-color);">
                         <i class="fas fa-clock"></i> Recherches récentes
                     </div>
                     ${historyItems}
                 `;
                 
                 suggestionsDropdown.style.display = 'block';
                 
                 // Add click handlers for history items
                 suggestionsDropdown.querySelectorAll('.history-item').forEach(item => {
                     item.addEventListener('click', function() {
                         const term = this.textContent.replace('🕒', '').trim();
                         searchInput.value = term;
                         hideSuggestions();
                         performSearch();
                     });
                 });
             }
         }

         // Enhanced search input behavior
         searchInput.addEventListener('focus', function() {
             if (this.value.length === 0) {
                 showSearchHistory();
             }
         });

         // Add keyboard navigation for suggestions
         let selectedSuggestionIndex = -1;
         
         searchInput.addEventListener('keydown', function(e) {
             const suggestions = suggestionsDropdown.querySelectorAll('.suggestion-item, .history-item');
             
             if (e.key === 'ArrowDown') {
                 e.preventDefault();
                 selectedSuggestionIndex = Math.min(selectedSuggestionIndex + 1, suggestions.length - 1);
                 updateSelectedSuggestion(suggestions);
             } else if (e.key === 'ArrowUp') {
                 e.preventDefault();
                 selectedSuggestionIndex = Math.max(selectedSuggestionIndex - 1, -1);
                 updateSelectedSuggestion(suggestions);
             } else if (e.key === 'Enter' && selectedSuggestionIndex >= 0) {
                 e.preventDefault();
                 if (suggestions[selectedSuggestionIndex]) {
                     suggestions[selectedSuggestionIndex].click();
                 }
             }
         });

         function updateSelectedSuggestion(suggestions) {
             suggestions.forEach((item, index) => {
                 if (index === selectedSuggestionIndex) {
                     item.style.background = 'var(--primary-color)';
                     item.style.color = 'white';
                 } else {
                     item.style.background = '';
                     item.style.color = '';
                 }
             });
         }

         // Add search analytics
         function trackSearch(searchTerm) {
             addToSearchHistory(searchTerm);
             // Here you could add analytics tracking
             console.log('Search performed:', searchTerm);
         }

         // Enhanced performSearch function
         const originalPerformSearch = performSearch;
         performSearch = function() {
             const searchValue = searchInput.value.trim();
             if (searchValue) {
                 trackSearch(searchValue);
             }
             originalPerformSearch();
         };
    </script>
</body>
</html>
