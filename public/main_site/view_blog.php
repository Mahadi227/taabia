<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once '../../includes/language_handler.php';

// Now load the session and other includes
require_once '../../includes/db.php';
require_once '../../includes/session.php';
require_once '../../includes/function.php';
require_once '../../includes/i18n.php';

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
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($post['title']) ?> - <?= __('blog') ?> TaaBia</title>
    <meta name="description" content="<?= htmlspecialchars($post['meta_description'] ?: $post['excerpt']) ?>">
    <link rel="stylesheet" href="main-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Blog Post View Styles */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            background-color: #f8f9fa !important;
            line-height: 1.6 !important;
        }

        /* Header Styles */
        .header {
            background: white !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
            position: sticky !important;
            top: 0 !important;
            z-index: 1000 !important;
        }

        .navbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 1rem 2rem !important;
            max-width: 1200px !important;
            margin: 0 auto !important;
        }

        .logo {
            font-size: 1.5rem !important;
            font-weight: 700 !important;
            color: #009688 !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }

        .logo i {
            font-size: 1.8rem !important;
        }

        .nav-menu {
            display: flex !important;
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
            gap: 2rem !important;
            align-items: center !important;
        }

        .nav-link {
            color: #333 !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            transition: color 0.3s ease !important;
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }

        .nav-link:hover {
            color: #009688 !important;
        }

        .nav-actions {
            display: flex !important;
            gap: 1rem !important;
            align-items: center !important;
        }

        .btn {
            padding: 0.75rem 1.5rem !important;
            border-radius: 8px !important;
            text-decoration: none !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
        }

        .btn-primary {
            background: #009688 !important;
            color: white !important;
            border: 2px solid #009688 !important;
        }

        .btn-primary:hover {
            background: #00796b !important;
            border-color: #00796b !important;
            transform: translateY(-2px) !important;
        }

        .btn-secondary {
            background: transparent !important;
            color: #009688 !important;
            border: 2px solid #009688 !important;
        }

        .btn-secondary:hover {
            background: #009688 !important;
            color: white !important;
        }

        .hamburger {
            display: none !important;
            flex-direction: column !important;
            cursor: pointer !important;
            padding: 0.5rem !important;
        }

        .hamburger span {
            width: 25px !important;
            height: 3px !important;
            background: #333 !important;
            margin: 3px 0 !important;
            transition: 0.3s !important;
        }

        /* Section Styles */
        .section {
            padding: 2rem 0 !important;
            background: #f8f9fa !important;
        }

        .container {
            max-width: 1200px !important;
            margin: 0 auto !important;
            padding: 0 2rem !important;
        }

        /* Blog Layout */
        .blog-layout {
            display: grid !important;
            grid-template-columns: 1fr 300px !important;
            gap: 3rem !important;
            margin-top: 2rem !important;
        }

        /* Blog Post Styles */
        .blog-post {
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
            overflow: hidden !important;
            margin-bottom: 2rem !important;
        }

        /* Breadcrumb */
        .breadcrumb {
            padding: 1.5rem 2rem 0 2rem !important;
            background: #f8f9fa !important;
            border-bottom: 1px solid #e9ecef !important;
        }

        .breadcrumb a {
            color: #009688 !important;
            text-decoration: none !important;
            font-weight: 500 !important;
        }

        .breadcrumb a:hover {
            text-decoration: underline !important;
        }

        .breadcrumb-separator {
            margin: 0 0.5rem !important;
            color: #6c757d !important;
        }

        .breadcrumb-current {
            color: #6c757d !important;
            font-weight: 500 !important;
        }

        /* Post Header */
        .post-header {
            padding: 2rem !important;
            border-bottom: 1px solid #e9ecef !important;
        }

        .post-category {
            margin-bottom: 1rem !important;
        }

        .post-category a {
            background: #009688 !important;
            color: white !important;
            padding: 0.5rem 1rem !important;
            border-radius: 20px !important;
            text-decoration: none !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
        }

        .post-category a:hover {
            background: #00796b !important;
        }

        .post-title {
            font-size: 2.5rem !important;
            font-weight: 700 !important;
            color: #212121 !important;
            margin: 1rem 0 !important;
            line-height: 1.2 !important;
        }

        .post-meta {
            display: flex !important;
            gap: 2rem !important;
            margin-top: 1rem !important;
            flex-wrap: wrap !important;
        }

        .post-meta span {
            display: flex !important;
            align-items: center !important;
            gap: 0.5rem !important;
            color: #6c757d !important;
            font-size: 0.9rem !important;
        }

        .post-meta i {
            color: #009688 !important;
            font-size: 0.8rem !important;
        }

        /* Post Image */
        .post-image {
            width: 100% !important;
            height: 400px !important;
            overflow: hidden !important;
        }

        .post-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
            transition: transform 0.3s ease !important;
        }

        .post-image:hover img {
            transform: scale(1.02) !important;
        }

        /* Post Content */
        .post-content {
            padding: 2rem !important;
            font-size: 1.1rem !important;
            line-height: 1.8 !important;
            color: #333 !important;
        }

        .post-content h1,
        .post-content h2,
        .post-content h3,
        .post-content h4,
        .post-content h5,
        .post-content h6 {
            color: #212121 !important;
            margin: 2rem 0 1rem 0 !important;
            font-weight: 600 !important;
        }

        .post-content h2 {
            font-size: 1.8rem !important;
            border-bottom: 2px solid #e9ecef !important;
            padding-bottom: 0.5rem !important;
        }

        .post-content h3 {
            font-size: 1.5rem !important;
        }

        .post-content p {
            margin-bottom: 1.5rem !important;
        }

        .post-content ul,
        .post-content ol {
            margin: 1rem 0 !important;
            padding-left: 2rem !important;
        }

        .post-content li {
            margin-bottom: 0.5rem !important;
        }

        .post-content blockquote {
            border-left: 4px solid #009688 !important;
            padding: 1rem 2rem !important;
            margin: 2rem 0 !important;
            background: #f8f9fa !important;
            font-style: italic !important;
            color: #555 !important;
        }

        .post-content code {
            background: #f1f3f4 !important;
            padding: 0.2rem 0.4rem !important;
            border-radius: 4px !important;
            font-family: 'Courier New', monospace !important;
            font-size: 0.9rem !important;
        }

        .post-content pre {
            background: #f1f3f4 !important;
            padding: 1rem !important;
            border-radius: 8px !important;
            overflow-x: auto !important;
            margin: 1rem 0 !important;
        }

        .post-content img {
            max-width: 100% !important;
            height: auto !important;
            border-radius: 8px !important;
            margin: 1rem 0 !important;
        }

        /* Post Footer */
        .post-footer {
            padding: 2rem !important;
            border-top: 1px solid #e9ecef !important;
            background: #f8f9fa !important;
        }

        .post-tags {
            margin-bottom: 2rem !important;
        }

        .post-tags h4 {
            color: #212121 !important;
            margin-bottom: 1rem !important;
            font-size: 1.1rem !important;
        }

        .tags {
            display: flex !important;
            gap: 0.5rem !important;
            flex-wrap: wrap !important;
        }

        .tag {
            background: #e9ecef !important;
            color: #495057 !important;
            padding: 0.4rem 0.8rem !important;
            border-radius: 20px !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
        }

        .post-share h4 {
            color: #212121 !important;
            margin-bottom: 1rem !important;
            font-size: 1.1rem !important;
        }

        .share-buttons {
            display: flex !important;
            gap: 0.5rem !important;
        }

        .share-btn {
            width: 40px !important;
            height: 40px !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            text-decoration: none !important;
            color: white !important;
            font-size: 1.1rem !important;
            transition: all 0.3s ease !important;
        }

        .share-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2) !important;
        }

        .share-btn.facebook {
            background: #3b5998 !important;
        }

        .share-btn.twitter {
            background: #1da1f2 !important;
        }

        .share-btn.linkedin {
            background: #0077b5 !important;
        }

        .share-btn.email {
            background: #6c757d !important;
        }

        /* Related Posts */
        .related-posts {
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
            padding: 2rem !important;
            margin-top: 2rem !important;
        }

        .related-posts h3 {
            color: #212121 !important;
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            margin-bottom: 1.5rem !important;
            padding-bottom: 0.5rem !important;
            border-bottom: 2px solid #e9ecef !important;
        }

        .related-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
            gap: 1.5rem !important;
        }

        .related-post {
            display: flex !important;
            gap: 1rem !important;
            padding: 1rem !important;
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
        }

        .related-post:hover {
            border-color: #009688 !important;
            box-shadow: 0 2px 8px rgba(0, 150, 136, 0.1) !important;
        }

        .related-post-image {
            width: 80px !important;
            height: 80px !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            flex-shrink: 0 !important;
        }

        .related-post-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        .related-placeholder {
            width: 100% !important;
            height: 100% !important;
            background: #f8f9fa !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #6c757d !important;
            font-size: 1.5rem !important;
        }

        .related-post-content {
            flex: 1 !important;
        }

        .related-post-content h4 {
            margin: 0 0 0.5rem 0 !important;
            font-size: 1rem !important;
            line-height: 1.4 !important;
        }

        .related-post-content h4 a {
            color: #212121 !important;
            text-decoration: none !important;
            transition: color 0.3s ease !important;
        }

        .related-post-content h4 a:hover {
            color: #009688 !important;
        }

        .related-post-content p {
            color: #6c757d !important;
            font-size: 0.9rem !important;
            margin-bottom: 0.5rem !important;
            line-height: 1.4 !important;
        }

        .related-post-date {
            color: #6c757d !important;
            font-size: 0.8rem !important;
        }

        /* Sidebar Styles */
        .blog-sidebar {
            display: flex !important;
            flex-direction: column !important;
            gap: 2rem !important;
        }

        .sidebar-section {
            background: white !important;
            padding: 1.5rem !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }

        .sidebar-section h3 {
            color: #212121 !important;
            font-size: 1.2rem !important;
            font-weight: 600 !important;
            margin-bottom: 1rem !important;
            padding-bottom: 0.5rem !important;
            border-bottom: 2px solid #e9ecef !important;
        }

        .sidebar-section p {
            color: #6c757d !important;
            margin-bottom: 1rem !important;
            line-height: 1.5 !important;
        }

        /* Recent Posts Sidebar */
        .recent-posts {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
        }

        .recent-post {
            display: flex !important;
            gap: 1rem !important;
            padding: 0.75rem !important;
            border-radius: 8px !important;
            transition: background 0.3s ease !important;
        }

        .recent-post:hover {
            background: #f8f9fa !important;
        }

        .recent-post-image {
            width: 60px !important;
            height: 60px !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            flex-shrink: 0 !important;
        }

        .recent-post-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        .recent-placeholder {
            width: 100% !important;
            height: 100% !important;
            background: #f8f9fa !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #6c757d !important;
            font-size: 1.2rem !important;
        }

        .recent-post-content {
            flex: 1 !important;
        }

        .recent-post-content h4 {
            margin: 0 0 0.25rem 0 !important;
            font-size: 0.9rem !important;
            line-height: 1.3 !important;
        }

        .recent-post-content h4 a {
            color: #212121 !important;
            text-decoration: none !important;
            transition: color 0.3s ease !important;
        }

        .recent-post-content h4 a:hover {
            color: #009688 !important;
        }

        .recent-post-date {
            color: #6c757d !important;
            font-size: 0.8rem !important;
        }

        /* Categories Sidebar */
        .category-list {
            display: flex !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
        }

        .category-item {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 0.75rem 1rem !important;
            background: #f8f9fa !important;
            color: #212121 !important;
            text-decoration: none !important;
            border-radius: 8px !important;
            transition: all 0.3s ease !important;
            border: 1px solid transparent !important;
        }

        .category-item:hover {
            background: #009688 !important;
            color: white !important;
            transform: translateX(5px) !important;
        }

        .category-name {
            font-weight: 500 !important;
        }

        .category-count {
            background: rgba(0, 0, 0, 0.1) !important;
            padding: 0.2rem 0.6rem !important;
            border-radius: 12px !important;
            font-size: 0.8rem !important;
            font-weight: 600 !important;
        }

        .category-item:hover .category-count {
            background: rgba(255, 255, 255, 0.2) !important;
        }

        /* Newsletter Form */
        .newsletter-form {
            display: flex !important;
            flex-direction: column !important;
            gap: 1rem !important;
        }

        .newsletter-input {
            padding: 0.75rem 1rem !important;
            border: 2px solid #e9ecef !important;
            border-radius: 8px !important;
            font-size: 1rem !important;
            transition: border-color 0.3s ease !important;
        }

        .newsletter-input:focus {
            outline: none !important;
            border-color: #009688 !important;
        }

        .newsletter-btn {
            background: #009688 !important;
            color: white !important;
            border: none !important;
            padding: 0.75rem 1rem !important;
            border-radius: 8px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        .newsletter-btn:hover {
            background: #00796b !important;
            transform: translateY(-2px) !important;
        }

        /* Footer Styles */
        .footer {
            background: #1a237e !important;
            color: white !important;
            padding: 3rem 0 1rem 0 !important;
            margin-top: 4rem !important;
        }

        .footer-content {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
            gap: 2rem !important;
            margin-bottom: 2rem !important;
        }

        .footer-section h3 {
            color: white !important;
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            margin-bottom: 1rem !important;
        }

        .footer-section h4 {
            color: #e3f2fd !important;
            font-size: 1.2rem !important;
            font-weight: 600 !important;
            margin-bottom: 1rem !important;
        }

        .footer-section p {
            color: #b3c6ff !important;
            line-height: 1.6 !important;
            margin-bottom: 0.5rem !important;
        }

        .footer-section ul {
            list-style: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .footer-section ul li {
            margin-bottom: 0.5rem !important;
        }

        .footer-section ul li a {
            color: #b3c6ff !important;
            text-decoration: none !important;
            transition: color 0.3s ease !important;
        }

        .footer-section ul li a:hover {
            color: white !important;
        }

        .footer-bottom {
            border-top: 1px solid #3f51b5 !important;
            padding-top: 1rem !important;
            text-align: center !important;
        }

        .footer-bottom p {
            color: #b3c6ff !important;
            margin: 0 !important;
            font-size: 0.9rem !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .blog-layout {
                grid-template-columns: 1fr !important;
                gap: 2rem !important;
            }

            .post-title {
                font-size: 2rem !important;
            }

            .post-meta {
                flex-direction: column !important;
                gap: 0.5rem !important;
            }

            .related-grid {
                grid-template-columns: 1fr !important;
            }

            .related-post {
                flex-direction: column !important;
                text-align: center !important;
            }

            .related-post-image {
                width: 100% !important;
                height: 150px !important;
            }

            .nav-menu {
                display: none !important;
            }

            .hamburger {
                display: flex !important;
            }

            .nav-actions {
                display: none !important;
            }

            .container {
                padding: 0 1rem !important;
            }

            .post-content {
                padding: 1.5rem !important;
            }

            .post-header {
                padding: 1.5rem !important;
            }

            .post-footer {
                padding: 1.5rem !important;
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
                <li><a href="index.php" class="nav-link"><?= __('home') ?></a></li>
                <li><a href="courses.php" class="nav-link"><?= __('courses') ?></a></li>
                <li><a href="shop.php" class="nav-link"><?= __('shop') ?></a></li>
                <li><a href="upcoming_events.php" class="nav-link"><?= __('events') ?></a></li>
                <li><a href="blog.php" class="nav-link"><?= __('blog') ?></a></li>
                <li><a href="about.php" class="nav-link"><?= __('about') ?></a></li>
                <li><a href="contact.php" class="nav-link"><?= __('contact') ?></a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>

            </ul>

            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> <?= __('my_account') ?>
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('login') ?>
                    </a>
                    <a href="../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?= __('register') ?>
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
                            <a href="index.php"><?= __('home') ?></a>
                            <span class="breadcrumb-separator">/</span>
                            <a href="blog.php"><?= __('blog') ?></a>
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
                                    <?= $post['view_count'] ?> <?= __('views') ?>
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
                            <h3><?= __('related_articles') ?></h3>
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
                        <h3><?= __('recent_articles') ?></h3>
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
                        <h3><?= __('newsletter') ?></h3>
                        <p><?= __('newsletter_description') ?></p>
                        <form class="newsletter-form">
                            <input type="email" placeholder="<?= __('newsletter_placeholder') ?>" class="newsletter-input">
                            <button type="submit" class="newsletter-btn"><?= __('newsletter_subscribe') ?></button>
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
                    <h3>TaaBia</h3>
                    <p><?= __('footer_description') ?></p>
                </div>
                <div class="footer-section">
                    <h4><?= __('quick_links') ?></h4>
                    <ul>
                        <li><a href="index.php"><?= __('home') ?></a></li>
                        <li><a href="courses.php"><?= __('courses') ?></a></li>
                        <li><a href="shop.php"><?= __('shop') ?></a></li>
                        <li><a href="blog.php"><?= __('blog') ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?= __('contact') ?></h4>
                    <p><?= __('email') ?>: contact@taabia.com</p>
                    <p><?= __('phone') ?>: +123 456 789</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. <?= __('footer_rights_reserved') ?></p>
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