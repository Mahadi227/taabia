<?php
require_once '../../includes/db.php';
require_once '../../includes/session.php';

// Get the blog post slug from URL
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header('Location: blog.php');
    exit;
}

try {
    // Get the blog post with author and category information
    $sql = "SELECT p.*, c.name as category_name, c.slug as category_slug, u.fullname as author_name 
            FROM blog_posts p 
            LEFT JOIN blog_categories c ON p.category_id = c.id 
            LEFT JOIN users u ON p.author_id = u.id 
            WHERE p.slug = ? AND p.status = 'published'";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$slug]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        header('Location: blog.php');
        exit;
    }
    
    // Update view count
    $update_sql = "UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$post['id']]);
    
    // Get related posts (same category, excluding current post)
    $related_sql = "SELECT id, title, slug, excerpt, featured_image, published_at 
                    FROM blog_posts 
                    WHERE category_id = ? AND id != ? AND status = 'published' 
                    ORDER BY published_at DESC 
                    LIMIT 3";
    $related_stmt = $pdo->prepare($related_sql);
    $related_stmt->execute([$post['category_id'], $post['id']]);
    $related_posts = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent posts for sidebar
    $recent_sql = "SELECT id, title, slug, published_at, featured_image 
                   FROM blog_posts 
                   WHERE status = 'published' AND id != ? 
                   ORDER BY published_at DESC 
                   LIMIT 5";
    $recent_stmt = $pdo->prepare($recent_sql);
    $recent_stmt->execute([$post['id']]);
    $recent_posts = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get categories for sidebar
    $categories_sql = "SELECT id, name, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id AND status = 'published') as post_count 
                       FROM blog_categories c 
                       WHERE status = 'active' 
                       ORDER BY name";
    $categories_stmt = $pdo->query($categories_sql);
    $categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    header('Location: blog.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - Blog Taabia</title>
    <meta name="description" content="<?= htmlspecialchars($post['meta_description'] ?: $post['excerpt']) ?>">
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
    <!-- Blog Post Content -->
    <section class="section">
        <div class="container">
            <div class="blog-layout">
                <!-- Main Content -->
                <div class="blog-main">
                    <article class="blog-post">
                        <!-- Breadcrumb -->
                        <nav class="breadcrumb">
                            <a href="index.php">Accueil</a>
                            <span class="breadcrumb-separator">/</span>
                            <a href="blog.php">Blog</a>
                            <span class="breadcrumb-separator">/</span>
                            <span class="breadcrumb-current"><?= htmlspecialchars($post['title']) ?></span>
                        </nav>

                        <!-- Post Header -->
                        <header class="post-header">
                            <div class="post-category">
                                <a href="blog.php?category=<?= $post['category_id'] ?>">
                                    <?= htmlspecialchars($post['category_name']) ?>
                                </a>
                            </div>
                            <h1 class="post-title"><?= htmlspecialchars($post['title']) ?></h1>
                            <div class="post-meta">
                                <span class="post-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y', strtotime($post['published_at'])) ?>
                                </span>
                                <span class="post-author">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($post['author_name']) ?>
                                </span>
                                <span class="post-views">
                                    <i class="fas fa-eye"></i>
                                    <?= $post['view_count'] ?> vues
                                </span>
                            </div>
                        </header>

                        <!-- Featured Image -->
                        <?php if ($post['featured_image']): ?>
                            <div class="post-image">
                                <img src="../../uploads/<?= htmlspecialchars($post['featured_image']) ?>" 
                                     alt="<?= htmlspecialchars($post['title']) ?>">
                            </div>
                        <?php endif; ?>

                        <!-- Post Content -->
                        <div class="post-content">
                            <?= $post['content'] ?>
                        </div>

                        <!-- Post Footer -->
                        <footer class="post-footer">
                            <div class="post-tags">
                                <h4>Tags:</h4>
                                <div class="tags">
                                    <span class="tag">Formation</span>
                                    <span class="tag">Développement</span>
                                    <span class="tag">Apprentissage</span>
                                </div>
                            </div>
                            
                            <div class="post-share">
                                <h4>Partager:</h4>
                                <div class="share-buttons">
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                       target="_blank" class="share-btn facebook">
                                        <i class="fab fa-facebook-f"></i>
                                    </a>
                                    <a href="https://twitter.com/intent/tweet?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>&text=<?= urlencode($post['title']) ?>" 
                                       target="_blank" class="share-btn twitter">
                                        <i class="fab fa-twitter"></i>
                                    </a>
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                       target="_blank" class="share-btn linkedin">
                                        <i class="fab fa-linkedin-in"></i>
                                    </a>
                                    <a href="mailto:?subject=<?= urlencode($post['title']) ?>&body=<?= urlencode($_SERVER['REQUEST_URI']) ?>" 
                                       class="share-btn email">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                </div>
                            </div>
                        </footer>
                    </article>

                    <!-- Related Posts -->
                    <?php if (!empty($related_posts)): ?>
                        <section class="related-posts">
                            <h3>Articles similaires</h3>
                            <div class="related-grid">
                                <?php foreach ($related_posts as $related): ?>
                                    <article class="related-post">
                                        <div class="related-post-image">
                                            <?php if ($related['featured_image']): ?>
                                                <img src="../../uploads/<?= htmlspecialchars($related['featured_image']) ?>" 
                                                     alt="<?= htmlspecialchars($related['title']) ?>">
                                            <?php else: ?>
                                                <div class="related-placeholder">
                                                    <i class="fas fa-newspaper"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="related-post-content">
                                            <h4>
                                                <a href="view_blog.php?slug=<?= $related['slug'] ?>">
                                                    <?= htmlspecialchars($related['title']) ?>
                                                </a>
                                            </h4>
                                            <p><?= htmlspecialchars($related['excerpt'] ?: substr(strip_tags($related['content']), 0, 100) . '...') ?></p>
                                            <span class="related-post-date">
                                                <?= date('d/m/Y', strtotime($related['published_at'])) ?>
                                            </span>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
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
                                <a href="blog.php?category=<?= $cat['id'] ?>" class="category-item">
                                    <span class="category-name"><?= htmlspecialchars($cat['name']) ?></span>
                                    <span class="category-count"><?= $cat['post_count'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Newsletter Signup -->
                    <div class="sidebar-section">
                        <h3>Newsletter</h3>
                        <p>Restez informé de nos derniers articles et actualités.</p>
                        <form class="newsletter-form">
                            <input type="email" placeholder="Votre email" class="newsletter-input">
                            <button type="submit" class="newsletter-btn">S'abonner</button>
                        </form>
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
    </script>
</body>
</html> 