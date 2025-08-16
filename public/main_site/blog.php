<?php
require_once '../../includes/db.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 6;
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ["p.status = 'published'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) FROM blog_posts p WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_posts = $count_stmt->fetchColumn();
$total_pages = ceil($total_posts / $per_page);

// Get blog posts
$sql = "SELECT p.*, c.name as category_name, u.fullname as author_name 
        FROM blog_posts p 
        LEFT JOIN blog_categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.author_id = u.id 
        WHERE $where_clause 
        ORDER BY p.published_at DESC 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categories_sql = "SELECT id, name, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id AND status = 'published') as post_count 
                   FROM blog_categories c 
                   WHERE status = 'active' 
                   ORDER BY name";
$categories_stmt = $pdo->query($categories_sql);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent posts for sidebar
$recent_sql = "SELECT id, title, slug, published_at, featured_image 
               FROM blog_posts 
               WHERE status = 'published' 
               ORDER BY published_at DESC 
               LIMIT 5";
$recent_stmt = $pdo->query($recent_sql);
$recent_posts = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog - Taabia</title>
    <link rel="stylesheet" href="main-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                <li><a href="index.php" class="nav-link">Accueil</a></li>
                <li><a href="courses.php" class="nav-link">Formations</a></li>
                <li><a href="shop.php" class="nav-link">Boutique</a></li>
                <li><a href="upcoming_events.php" class="nav-link">Événements</a></li>
                <li><a href="blog.php" class="nav-link">Blog</a></li>
                <li><a href="about.php" class="nav-link">À propos</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>

            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> Mon Compte
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a>
                    <a href="../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Inscription
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <!-- Blog Header -->
    <section class="hero-section" style="background: linear-gradient(135deg, #00796b 0%, #009688 100%);">
        <div class="container">
            <div class="hero-content">
                <h1>Blog Taabia</h1>
                <p>Découvrez nos derniers articles, conseils et actualités</p>
            </div>
        </div>
    </section>

    <!-- Blog Content -->
    <section class="section">
        <div class="container">
            <div class="blog-layout">
                <!-- Main Content -->
                <div class="blog-main">
                    <!-- Search and Filter -->
                    <div class="blog-filters">
                        <form method="GET" class="search-form">
                            <div class="search-input-group">
                                <input type="text" name="search" placeholder="Rechercher un article..." 
                                       value="<?= htmlspecialchars($search) ?>" class="search-input">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <select name="category" class="category-select">
                                <option value="0">Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['name']) ?> (<?= $cat['post_count'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>

                    <!-- Blog Posts Grid -->
                    <?php if (empty($posts)): ?>
                        <div class="no-posts">
                            <i class="fas fa-search"></i>
                            <h3>Aucun article trouvé</h3>
                            <p>Essayez de modifier vos critères de recherche</p>
                        </div>
                    <?php else: ?>
                        <div class="blog-grid">
                            <?php foreach ($posts as $post): ?>
                                <article class="blog-card">
                                    <div class="blog-image">
                                        <?php if ($post['featured_image']): ?>
                                            <img src="../../uploads/<?= htmlspecialchars($post['featured_image']) ?>" 
                                                 alt="<?= htmlspecialchars($post['title']) ?>">
                                        <?php else: ?>
                                            <div class="blog-placeholder">
                                                <i class="fas fa-newspaper"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="blog-category">
                                            <span><?= htmlspecialchars($post['category_name']) ?></span>
                                        </div>
                                    </div>
                                    <div class="blog-content">
                                        <div class="blog-meta">
                                            <span class="blog-date">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('d/m/Y', strtotime($post['published_at'])) ?>
                                            </span>
                                            <span class="blog-author">
                                                <i class="fas fa-user"></i>
                                                <?= htmlspecialchars($post['author_name']) ?>
                                            </span>
                                            <span class="blog-views">
                                                <i class="fas fa-eye"></i>
                                                <?= $post['view_count'] ?> vues
                                            </span>
                                        </div>
                                        <h3 class="blog-title">
                                            <a href="view_blog.php?slug=<?= $post['slug'] ?>">
                                                <?= htmlspecialchars($post['title']) ?>
                                            </a>
                                        </h3>
                                        <p class="blog-excerpt">
                                            <?= htmlspecialchars($post['excerpt'] ?: substr(strip_tags($post['content']), 0, 150) . '...') ?>
                                        </p>
                                        <a href="view_blog.php?slug=<?= $post['slug'] ?>" class="read-more">
                                            Lire la suite <i class="fas fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>" 
                                       class="page-link">
                                        <i class="fas fa-chevron-left"></i> Précédent
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>" 
                                       class="page-link <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= $category ?>" 
                                       class="page-link">
                                        Suivant <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <aside class="blog-sidebar">
                    <!-- Recent Posts -->
                    <div class="sidebar-section">
                        <h3>Articles récents</h3>
                        <div class="recent-posts">
                            <?php foreach ($recent_posts as $recent): ?>
                                <div class="recent-post">
                                    <div class="recent-post-image">
                                        <?php if ($recent['featured_image']): ?>
                                            <img src="../../uploads/<?= htmlspecialchars($recent['featured_image']) ?>" 
                                                 alt="<?= htmlspecialchars($recent['title']) ?>">
                                        <?php else: ?>
                                            <div class="recent-placeholder">
                                                <i class="fas fa-newspaper"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="recent-post-content">
                                        <h4>
                                            <a href="view_blog.php?slug=<?= $recent['slug'] ?>">
                                                <?= htmlspecialchars($recent['title']) ?>
                                            </a>
                                        </h4>
                                        <span class="recent-post-date">
                                            <?= date('d/m/Y', strtotime($recent['published_at'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="sidebar-section">
                        <h3>Catégories</h3>
                        <div class="category-list">
                            <?php foreach ($categories as $cat): ?>
                                <a href="?category=<?= $cat['id'] ?>" 
                                   class="category-item <?= $category == $cat['id'] ? 'active' : '' ?>">
                                    <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                                    <span class="category-count"><?= $cat['post_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Taabia</h3>
                    <p>Votre plateforme de formation et d'apprentissage en ligne.</p>
                </div>
                <div class="footer-section">
                    <h4>Liens rapides</h4>
                    <ul>
                        <li><a href="index.php">Accueil</a></li>
                        <li><a href="courses.php">Cours</a></li>
                        <li><a href="shop.php">Boutique</a></li>
                        <li><a href="blog.php">Blog</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Contact</h4>
                    <p>Email: contact@taabia.com</p>
                    <p>Téléphone: +123 456 789</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Taabia. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <script>
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

        // Auto-submit form when category changes
        document.querySelector('.category-select').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html> 