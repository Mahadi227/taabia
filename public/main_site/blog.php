<?php
// Start output buffering to prevent any accidental output
ob_start();

// Start session first
session_start();

// Handle language switching first
require_once '../../includes/language_handler.php';
require_once '../../includes/db.php';
require_once '../../includes/i18n.php';

// Get search parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$tag = isset($_GET['tag']) ? (int)$_GET['tag'] : 0;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 9; // Increased for better grid layout
$offset = ($page - 1) * $per_page;

// Build query conditions
$where_conditions = ["p.status = 'published'"];
$params = [];
$joins = [];

if (!empty($search)) {
    $where_conditions[] = "(p.title LIKE ? OR p.content LIKE ? OR p.excerpt LIKE ? OR p.meta_title LIKE ? OR p.meta_description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category > 0) {
    $where_conditions[] = "p.category_id = ?";
    $params[] = $category;
}

if ($tag > 0) {
    $joins[] = "INNER JOIN blog_post_tags pt ON p.id = pt.post_id";
    $where_conditions[] = "pt.tag_id = ?";
    $params[] = $tag;
}

$where_clause = implode(' AND ', $where_conditions);
$join_clause = implode(' ', $joins);

// Sort options
$order_by = "p.published_at DESC";
switch ($sort) {
    case 'oldest':
        $order_by = "p.published_at ASC";
        break;
    case 'popular':
        $order_by = "p.view_count DESC";
        break;
    case 'title':
        $order_by = "p.title ASC";
        break;
}

// Get total count for pagination
$count_sql = "SELECT COUNT(DISTINCT p.id) FROM blog_posts p $join_clause WHERE $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_posts = $count_stmt->fetchColumn();
$total_pages = ceil($total_posts / $per_page);

// Get blog posts with tags
$sql = "SELECT p.*, c.name as category_name, u.fullname as author_name,
               GROUP_CONCAT(DISTINCT bt.name) as tag_names,
               GROUP_CONCAT(DISTINCT bt.id) as tag_ids
        FROM blog_posts p 
        LEFT JOIN blog_categories c ON p.category_id = c.id 
        LEFT JOIN users u ON p.author_id = u.id 
        LEFT JOIN blog_post_tags bpt ON p.id = bpt.post_id
        LEFT JOIN blog_tags bt ON bpt.tag_id = bt.id
        $join_clause
        WHERE $where_clause 
        GROUP BY p.id
        ORDER BY $order_by 
        LIMIT ? OFFSET ?";

$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate reading time for each post
foreach ($posts as &$post) {
    $word_count = str_word_count(strip_tags($post['content']));
    $post['reading_time'] = max(1, round($word_count / 200)); // 200 words per minute

    // Parse tags
    $post['tags'] = [];
    if ($post['tag_names']) {
        $tag_names = explode(',', $post['tag_names']);
        $tag_ids = explode(',', $post['tag_ids']);
        $post['tags'] = array_combine($tag_ids, $tag_names);
    }
}

// Get categories for filter
$categories_sql = "SELECT id, name, (SELECT COUNT(*) FROM blog_posts WHERE category_id = c.id AND status = 'published') as post_count 
                   FROM blog_categories c 
                   WHERE status = 'active' 
                   ORDER BY name";
$categories_stmt = $pdo->query($categories_sql);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get tags for filter
$tags_sql = "SELECT t.id, t.name, COUNT(pt.post_id) as post_count 
             FROM blog_tags t 
             LEFT JOIN blog_post_tags pt ON t.id = pt.tag_id 
             LEFT JOIN blog_posts p ON pt.post_id = p.id AND p.status = 'published'
             GROUP BY t.id, t.name 
             HAVING post_count > 0 
             ORDER BY post_count DESC, t.name ASC";
$tags_stmt = $pdo->query($tags_sql);
$tags = $tags_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent posts for sidebar
$recent_sql = "SELECT id, title, slug, published_at, featured_image, view_count 
               FROM blog_posts 
               WHERE status = 'published' 
               ORDER BY published_at DESC 
               LIMIT 5";
$recent_stmt = $pdo->query($recent_sql);
$recent_posts = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get popular posts for sidebar
$popular_sql = "SELECT id, title, slug, published_at, featured_image, view_count 
                FROM blog_posts 
                WHERE status = 'published' 
                ORDER BY view_count DESC 
                LIMIT 5";
$popular_stmt = $pdo->query($popular_sql);
$popular_posts = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get related posts (if viewing a specific category)
$related_posts = [];
if ($category > 0) {
    $related_sql = "SELECT id, title, slug, published_at, featured_image, view_count 
                    FROM blog_posts 
                    WHERE status = 'published' AND category_id = ? 
                    ORDER BY view_count DESC 
                    LIMIT 3";
    $related_stmt = $pdo->prepare($related_sql);
    $related_stmt->execute([$category]);
    $related_posts = $related_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= __('blog') ?> - Taabia | <?= __('blog_posts') ?></title>
    <meta name="description" content="<?= __('blog_welcome_description') ?>">
    <meta name="keywords" content="<?= __('blog') ?>, <?= __('blog_posts') ?>, <?= __('courses') ?>, <?= __('development') ?>, <?= __('professional') ?>">
    <meta property="og:title" content="<?= __('blog_welcome_title') ?>">
    <meta property="og:description" content="<?= __('blog_welcome_description') ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="main-styles.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Color Palette */
            --primary-color: #009688;
            --primary-dark: #00796b;
            --primary-light: #4db6ac;
            --secondary-color: #00bcd4;
            --accent-color: #ff5722;
            --accent-light: #ff8a65;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;

            /* Text Colors */
            --text-primary: #212121;
            --text-secondary: #757575;
            --text-light: #bdbdbd;
            --text-white: #ffffff;
            --text-muted: #9e9e9e;

            /* Background Colors */
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f5f5f5;
            --bg-dark: #1a237e;
            --bg-gradient: linear-gradient(135deg, #00796b 0%, #009688 100%);

            /* Border & Shadow */
            --border-color: #e0e0e0;
            --border-light: #f0f0f0;
            --border-radius: 16px;
            --border-radius-sm: 8px;
            --border-radius-lg: 24px;

            --shadow-xs: 0 1px 2px rgba(0, 0, 0, 0.05);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 8px 16px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 16px 32px rgba(0, 0, 0, 0.2);
            --shadow-2xl: 0 24px 48px rgba(0, 0, 0, 0.25);

            /* Transitions */
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);

            /* Typography */
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --font-size-xs: 0.75rem;
            --font-size-sm: 0.875rem;
            --font-size-base: 1rem;
            --font-size-lg: 1.125rem;
            --font-size-xl: 1.25rem;
            --font-size-2xl: 1.5rem;
            --font-size-3xl: 1.875rem;
            --font-size-4xl: 2.25rem;
            --font-size-5xl: 3rem;

            /* Spacing */
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
            --spacing-3xl: 4rem;

            /* Breakpoints */
            --breakpoint-sm: 640px;
            --breakpoint-md: 768px;
            --breakpoint-lg: 1024px;
            --breakpoint-xl: 1280px;
            --breakpoint-2xl: 1536px;
        }

        /* Reset & Base Styles */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
            height: 100%;
        }

        body {
            font-family: var(--font-family);
            background-color: var(--bg-secondary);
            line-height: 1.7;
            color: var(--text-primary);
            font-size: var(--font-size-base);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Typography */
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: 600;
            line-height: 1.3;
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
        }

        h1 {
            font-size: var(--font-size-4xl);
        }

        h2 {
            font-size: var(--font-size-3xl);
        }

        h3 {
            font-size: var(--font-size-2xl);
        }

        h4 {
            font-size: var(--font-size-xl);
        }

        h5 {
            font-size: var(--font-size-lg);
        }

        h6 {
            font-size: var(--font-size-base);
        }

        p {
            margin-bottom: var(--spacing-md);
            color: var(--text-secondary);
        }

        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition-fast);
        }

        a:hover {
            color: var(--primary-dark);
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        /* Utility Classes */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-lg);
            width: 100%;
            box-sizing: border-box;
        }

        .container-fluid {
            width: 100%;
            padding: 0 var(--spacing-md);
            box-sizing: border-box;
        }

        .text-center {
            text-align: center;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .hidden {
            display: none;
        }

        .visible {
            display: block;
        }

        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .blog-layout {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: var(--spacing-xl);
            margin-top: var(--spacing-xl);
            align-items: start;
            width: 100%;
            max-width: 100%;
        }

        @media (max-width: 1024px) {
            .blog-layout {
                grid-template-columns: 1fr;
                gap: var(--spacing-lg);
                margin-top: var(--spacing-lg);
            }
        }

        .blog-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            width: 100%;
        }

        /* Responsive Grid Breakpoints */
        @media (min-width: 1400px) {
            .blog-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) and (min-width: 992px) {
            .blog-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 991px) and (min-width: 769px) {
            .blog-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .blog-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .blog-grid {
                gap: var(--spacing-sm);
            }
        }

        .blog-grid.masonry {
            column-count: 3;
            column-gap: var(--spacing-xl);
        }

        .blog-grid.masonry .blog-card {
            break-inside: avoid;
            margin-bottom: var(--spacing-xl);
        }

        .blog-grid.list {
            grid-template-columns: 1fr;
        }

        .blog-grid.list .blog-card {
            display: flex;
            flex-direction: row;
            align-items: stretch;
        }

        .blog-grid.list .blog-image {
            width: 300px;
            height: 200px;
            flex-shrink: 0;
        }

        .blog-grid.list .blog-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Responsive Grid */
        @media (max-width: 768px) {
            .blog-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-lg);
            }

            .blog-grid.masonry {
                column-count: 1;
            }

            .blog-grid.list .blog-card {
                flex-direction: column;
            }

            .blog-grid.list .blog-image {
                width: 100%;
                height: 250px;
            }
        }

        @media (max-width: 480px) {
            .blog-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
        }

        .blog-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-light);
            position: relative;
            height: fit-content;
        }

        .blog-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary-light);
        }

        .blog-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bg-gradient);
            transform: scaleX(0);
            transition: var(--transition);
            z-index: 1;
        }

        .blog-card:hover::before {
            transform: scaleX(1);
        }

        .blog-card.featured {
            grid-column: span 2;
            display: flex;
            flex-direction: row;
        }

        .blog-card.featured .blog-image {
            flex: 1;
            height: auto;
        }

        .blog-card.featured .blog-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .blog-image {
            position: relative;
            height: 220px;
            overflow: hidden;
            background: var(--bg-tertiary);
        }

        .blog-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: var(--transition);
        }

        .blog-card:hover .blog-image img {
            transform: scale(1.05);
        }

        .blog-placeholder {
            width: 100% !important;
            height: 100% !important;
            background: #f5f5f5 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #9e9e9e !important;
            font-size: 48px !important;
        }

        .blog-content {
            padding: var(--spacing-xl);
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            flex: 1;
        }

        .blog-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .blog-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .blog-meta i {
            font-size: 0.75rem;
        }

        .blog-category {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .blog-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .blog-tag {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            text-decoration: none;
            transition: var(--transition);
        }

        .blog-tag:hover {
            background: var(--primary-color);
            color: white;
        }

        .reading-time {
            background: var(--accent-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .blog-title {
            margin-bottom: var(--spacing-sm);
        }

        .blog-title a {
            color: var(--text-primary);
            text-decoration: none;
            font-size: var(--font-size-xl);
            font-weight: 600;
            line-height: 1.4;
            transition: var(--transition-fast);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .blog-title a:hover {
            color: var(--primary-color);
        }

        .blog-excerpt {
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: var(--spacing-md);
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            font-size: var(--font-size-sm);
        }

        .read-more {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            font-size: var(--font-size-sm);
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            transition: var(--transition-fast);
            margin-top: auto;
        }

        .read-more:hover {
            color: var(--primary-dark);
            transform: translateX(4px);
        }

        .read-more i {
            transition: var(--transition-fast);
        }

        .read-more:hover i {
            transform: translateX(4px);
        }

        .blog-filters {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-2xl);
            border: 1px solid var(--border-light);
            position: relative;
            overflow: hidden;
        }

        .blog-filters::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bg-gradient);
        }

        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filters-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .view-toggle {
            display: flex;
            gap: 0.5rem;
        }

        .view-btn {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-secondary);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
        }

        .view-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .advanced-filters {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-primary);
        }

        .search-form {
            display: flex !important;
            gap: 1rem !important;
            align-items: center !important;
            flex-wrap: wrap !important;
        }

        .search-input-group {
            position: relative !important;
            flex: 1 !important;
            min-width: 250px !important;
        }

        .search-input {
            width: 100%;
            padding: var(--spacing-md) var(--spacing-lg);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-base);
            background: var(--bg-primary);
            transition: var(--transition);
            font-family: var(--font-family);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .search-input::placeholder {
            color: var(--text-muted);
        }

        .search-btn {
            position: absolute;
            right: var(--spacing-sm);
            top: 50%;
            transform: translateY(-50%);
            background: var(--primary-color);
            color: var(--text-white);
            border: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .search-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-50%) scale(1.05);
        }

        .category-select {
            padding: 12px 16px !important;
            border: 2px solid #e0e0e0 !important;
            border-radius: 12px !important;
            font-size: 16px !important;
            background: white !important;
            min-width: 200px !important;
        }

        .blog-sidebar {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xl);
            position: sticky;
            top: calc(70px + var(--spacing-lg));
            max-height: calc(100vh - 70px - var(--spacing-lg));
            overflow-y: auto;
        }

        .sidebar-section {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .sidebar-section:hover {
            box-shadow: var(--shadow-md);
        }

        .sidebar-section h3 {
            color: #212121 !important;
            font-size: 18px !important;
            font-weight: 600 !important;
            margin-bottom: 1rem !important;
            padding-bottom: 0.5rem !important;
            border-bottom: 2px solid #e0e0e0 !important;
        }

        .recent-post {
            display: flex !important;
            gap: 1rem !important;
            padding: 0.5rem !important;
            border-radius: 6px !important;
            margin-bottom: 0.5rem !important;
        }

        .recent-post:hover {
            background: #f5f5f5 !important;
        }

        .recent-post-image {
            width: 60px !important;
            height: 60px !important;
            border-radius: 6px !important;
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
            background: #f5f5f5 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            color: #9e9e9e !important;
            font-size: 20px !important;
        }

        .category-item {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 0.5rem 1rem !important;
            background: #f5f5f5 !important;
            color: #212121 !important;
            text-decoration: none !important;
            border-radius: 6px !important;
            margin-bottom: 0.25rem !important;
        }

        .category-item:hover {
            background: #009688 !important;
            color: white !important;
        }

        .pagination {
            display: flex !important;
            justify-content: center !important;
            align-items: center !important;
            gap: 0.5rem !important;
            margin-top: 2rem !important;
        }

        .page-link {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            padding: 10px 16px !important;
            background: white !important;
            color: #212121 !important;
            text-decoration: none !important;
            border: 2px solid #e0e0e0 !important;
            border-radius: 12px !important;
            font-weight: 500 !important;
        }

        .page-link:hover {
            background: #009688 !important;
            color: white !important;
            border-color: #009688 !important;
        }

        .page-link.active {
            background: #009688 !important;
            color: white !important;
            border-color: #009688 !important;
        }

        /* Hero Section Styles */
        .hero-section {
            background: var(--bg-gradient);
            color: var(--text-white);
            padding: var(--spacing-3xl) 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 60vh;
            display: flex;
            align-items: center;
        }

        .hero-section::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: rgba(0, 0, 0, 0.1) !important;
            z-index: 1 !important;
        }

        .hero-content {
            position: relative !important;
            z-index: 2 !important;
            max-width: 800px !important;
            margin: 0 auto !important;
            padding: 0 2rem !important;
        }

        .hero-content h1 {
            font-size: 3rem !important;
            font-weight: 700 !important;
            margin-bottom: 1rem !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
        }

        .hero-content p {
            font-size: 1.2rem !important;
            opacity: 0.9 !important;
            margin-bottom: 0 !important;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
        }

        /* Section Styles */
        .section {
            padding: 4rem 0 !important;
            background: #f8f9fa !important;
        }

        .container {
            max-width: 1200px !important;
            margin: 0 auto !important;
            padding: 0 2rem !important;
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

        /* Header Styles */
        .header {
            background: var(--bg-primary);
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-lg) var(--spacing-xl);
            max-width: 1200px;
            margin: 0 auto;
            min-height: 70px;
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

        /* Additional Styles for New Features */
        .newsletter-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .newsletter-section h3 {
            color: white;
        }

        .newsletter-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .newsletter-form input {
            padding: 0.75rem;
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
        }

        .popular-posts {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .popular-post {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem;
            background: var(--bg-tertiary);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
        }

        .popular-post:hover {
            background: var(--primary-color);
            color: white;
        }

        .popular-post-number {
            width: 30px;
            height: 30px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .popular-post:hover .popular-post-number {
            background: white;
            color: var(--primary-color);
        }

        .tags-cloud {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .tag-cloud-item {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.875rem;
        }

        .tag-cloud-item:hover {
            background: var(--primary-color);
            color: white;
        }

        .blog-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1rem;
        }

        .social-share {
            display: flex;
            gap: 0.5rem;
        }

        .share-btn {
            background: var(--bg-tertiary);
            border: none;
            padding: 0.5rem;
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
        }

        .share-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .search-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-medium);
            z-index: 1000;
        }

        .suggestion-item {
            padding: 0.75rem 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .suggestion-item:hover {
            background: var(--bg-tertiary);
        }

        .related-posts {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .related-post {
            padding: 0.75rem;
            background: var(--bg-tertiary);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
        }

        .related-post:hover {
            background: var(--primary-color);
            color: white;
        }

        .recent-post-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .recent-post:hover .recent-post-meta {
            color: white;
        }

        /* Modern Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        .blog-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .sidebar-section {
            animation: slideInLeft 0.6s ease-out;
        }

        .loading {
            animation: pulse 1.5s ease-in-out infinite;
        }

        /* Smooth Scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Focus States for Accessibility */
        .search-input:focus,
        .category-select:focus,
        .btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Screen Fitting Fixes */
        * {
            max-width: 100%;
        }

        .blog-main {
            width: 100%;
            overflow-x: hidden;
        }

        .blog-card {
            max-width: 100%;
            overflow: hidden;
        }

        .blog-image {
            max-width: 100%;
            overflow: hidden;
        }

        .blog-content {
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .blog-title a {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .blog-excerpt {
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Ensure no horizontal scroll */
        body,
        html {
            overflow-x: hidden;
            width: 100%;
        }

        /* Print Styles */
        @media print {

            .header,
            .blog-filters,
            .blog-sidebar,
            .footer {
                display: none;
            }

            .blog-layout {
                grid-template-columns: 1fr;
            }

            .blog-card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ccc;
            }
        }

        .blog-grid.list .blog-card {
            display: flex;
            flex-direction: row;
            margin-bottom: 1rem;
        }

        .blog-grid.list .blog-image {
            width: 200px;
            height: 150px;
            flex-shrink: 0;
        }

        .blog-grid.list .blog-content {
            flex: 1;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .container {
                padding: 0 var(--spacing-md);
                max-width: 100%;
            }

            .blog-layout {
                gap: var(--spacing-lg);
            }

            .blog-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        @media (max-width: 1024px) {
            .container {
                padding: 0 var(--spacing-sm);
            }

            .blog-layout {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }

            .blog-sidebar {
                position: static;
                max-height: none;
            }

            .advanced-filters {
                grid-template-columns: repeat(2, 1fr);
                gap: var(--spacing-md);
            }

            .blog-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 var(--spacing-sm);
                max-width: 100%;
            }

            .navbar {
                padding: var(--spacing-sm) var(--spacing-md);
                flex-wrap: wrap;
                min-height: 60px;
            }

            .hero-section {
                padding: var(--spacing-xl) 0;
                min-height: 40vh;
            }

            .hero-content {
                padding: 0 var(--spacing-md);
            }

            .hero-content h1 {
                font-size: var(--font-size-2xl);
                margin-bottom: var(--spacing-sm);
            }

            .hero-content p {
                font-size: var(--font-size-base);
            }

            .blog-filters {
                padding: var(--spacing-md);
                margin: var(--spacing-md) 0;
            }

            .advanced-filters {
                grid-template-columns: 1fr;
                gap: var(--spacing-sm);
            }

            .filters-header {
                flex-direction: column;
                gap: var(--spacing-sm);
                align-items: flex-start;
            }

            .view-toggle {
                align-self: flex-end;
            }

            .blog-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
                margin-bottom: var(--spacing-lg);
            }

            .blog-card {
                margin-bottom: var(--spacing-sm);
            }

            .blog-card.featured {
                flex-direction: column;
            }

            .blog-grid.list .blog-card {
                flex-direction: column;
            }

            .blog-grid.list .blog-image {
                width: 100%;
                height: 200px;
            }

            .nav-menu {
                display: none;
            }

            .hamburger {
                display: flex;
            }

            .nav-actions {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 0 var(--spacing-xs);
            }

            .hero-content h1 {
                font-size: var(--font-size-xl);
            }

            .hero-content p {
                font-size: var(--font-size-sm);
            }

            .blog-filters {
                padding: var(--spacing-sm);
                margin: var(--spacing-sm) 0;
            }

            .blog-content {
                padding: var(--spacing-md);
            }

            .sidebar-section {
                padding: var(--spacing-md);
            }

            .blog-grid {
                gap: var(--spacing-sm);
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 var(--spacing-xs);
            }

            .navbar {
                padding: var(--spacing-xs) var(--spacing-sm);
                min-height: 50px;
            }

            .hero-section {
                padding: var(--spacing-lg) 0;
                min-height: 30vh;
            }

            .hero-content h1 {
                font-size: var(--font-size-lg);
            }

            .blog-filters {
                padding: var(--spacing-xs);
                margin: var(--spacing-xs) 0;
            }

            .blog-content {
                padding: var(--spacing-sm);
            }

            .sidebar-section {
                padding: var(--spacing-sm);
            }

            .blog-grid {
                grid-template-columns: 1fr;
                gap: var(--spacing-xs);
            }
        }

        /* Hero Section Styles */
        .hero-section {
            background: linear-gradient(135deg, #00796b 0%, #009688 100%) !important;
            padding: 4rem 0 !important;
            text-align: center !important;
            color: white !important;
            position: relative !important;
            overflow: hidden !important;
        }

        .hero-section::before {
            content: '' !important;
            position: absolute !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') !important;
            opacity: 0.3 !important;
        }

        .hero-content {
            position: relative !important;
            z-index: 2 !important;
        }

        .hero-content h1 {
            font-size: 3rem !important;
            font-weight: 700 !important;
            margin-bottom: 1rem !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
            color: #ffffffff;
        }

        .hero-content p {
            font-size: 1.2rem !important;
            opacity: 0.9 !important;
            max-width: 600px !important;
            margin: 0 auto !important;
            line-height: 1.6 !important;
            color: #ffffffff;
        }

        /* Section Styles */
        .section {
            padding: 4rem 0 !important;
            background: #f8f9fa !important;
        }

        .container {
            max-width: 1200px !important;
            margin: 0 auto !important;
            padding: 0 1rem !important;
        }

        /* Footer Styles */
        .footer {
            background: #1a237e !important;
            color: white !important;
            padding: 3rem 0 1rem !important;
            margin-top: 4rem !important;
        }

        .footer-content {
            display: grid !important;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)) !important;
            gap: 2rem !important;
            margin-bottom: 2rem !important;
        }

        .footer-section h3 {
            color: #4db6ac !important;
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            margin-bottom: 1rem !important;
        }

        .footer-section h4 {
            color: #4db6ac !important;
            font-size: 1.2rem !important;
            font-weight: 600 !important;
            margin-bottom: 1rem !important;
        }

        .footer-section p {
            color: rgba(255, 255, 255, 0.8) !important;
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
            color: rgba(255, 255, 255, 0.8) !important;
            text-decoration: none !important;
            transition: color 0.3s ease !important;
        }

        .footer-section ul li a:hover {
            color: #4db6ac !important;
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

    <!-- Blog Header -->
    <section class="hero-section" style="background: linear-gradient(135deg, #00796b 0%, #009688 100%);">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">
                    <i class="fas fa-blog"></i> <?= __('blog_welcome_title') ?>
                </h1>
                <p><?= __('blog_welcome_description') ?></p>
            </div>
        </div>
    </section>

    <!-- Blog Content -->
    <section class="section">
        <div class="container">
            <div class="blog-layout">
                <!-- Main Content -->
                <div class="blog-main">
                    <!-- Advanced Search and Filter -->
                    <div class="blog-filters">
                        <div class="filters-header">
                            <h2 class="filters-title"><?= __('search_articles') ?></h2>
                            <div class="view-toggle">
                                <button type="button" class="view-btn active" data-view="grid">
                                    <i class="fas fa-th"></i>
                                </button>
                                <button type="button" class="view-btn" data-view="masonry">
                                    <i class="fas fa-th-large"></i>
                                </button>
                                <button type="button" class="view-btn" data-view="list">
                                    <i class="fas fa-list"></i>
                                </button>
                            </div>
                        </div>

                        <form method="GET" class="advanced-filters" id="searchForm">
                            <div class="filter-group">
                                <label class="filter-label"><?= __('search') ?></label>
                                <div class="search-input-group">
                                    <input type="text" name="search" placeholder="<?= __('search_article_placeholder') ?>"
                                        value="<?= htmlspecialchars($search) ?>" class="search-input" id="searchInput">
                                    <button type="submit" class="search-btn">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label"><?= __('category') ?></label>
                                <select name="category" class="category-select">
                                    <option value="0"><?= __('all_categories') ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?> (<?= $cat['post_count'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label"><?= __('popular_tags') ?></label>
                                <select name="tag" class="category-select">
                                    <option value="0"><?= __('all_tags') ?></option>
                                    <?php foreach ($tags as $tag): ?>
                                        <option value="<?= $tag['id'] ?>" <?= $tag == $tag['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tag['name']) ?> (<?= $tag['post_count'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label"><?= __('sort_by') ?></label>
                                <select name="sort" class="category-select">
                                    <option value="newest" <?= $sort == 'newest' ? 'selected' : '' ?>><?= __('newest') ?></option>
                                    <option value="oldest" <?= $sort == 'oldest' ? 'selected' : '' ?>><?= __('oldest') ?></option>
                                    <option value="popular" <?= $sort == 'popular' ? 'selected' : '' ?>><?= __('popular') ?></option>
                                    <option value="title" <?= $sort == 'title' ? 'selected' : '' ?>><?= __('title_az') ?></option>
                                </select>
                            </div>

                            <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                                <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                            </button>
                        </form>

                        <!-- Search Suggestions -->
                        <div class="search-suggestions" id="searchSuggestions" style="display: none;">
                            <div class="suggestions-list" id="suggestionsList"></div>
                        </div>
                    </div>

                    <!-- Blog Posts Grid -->
                    <?php if (empty($posts)): ?>
                        <div class="no-posts">
                            <i class="fas fa-search"></i>
                            <h3><?= __('no_articles_found') ?></h3>
                            <p><?= __('try_modifying_search') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="blog-grid" id="blogGrid">
                            <?php foreach ($posts as $index => $post): ?>
                                <article class="blog-card <?= $index === 0 ? 'featured' : '' ?>">
                                    <div class="blog-image">
                                        <?php if ($post['featured_image']): ?>
                                            <img src="../../uploads/<?= htmlspecialchars($post['featured_image']) ?>"
                                                alt="<?= htmlspecialchars($post['title']) ?>" loading="lazy">
                                        <?php else: ?>
                                            <div class="blog-placeholder">
                                                <i class="fas fa-newspaper"></i>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($post['category_name']): ?>
                                            <div class="blog-category">
                                                <span><?= htmlspecialchars($post['category_name']) ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <div class="reading-time">
                                            <i class="fas fa-clock"></i> <?= $post['reading_time'] ?> <?= __('reading_time') ?>
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
                                                <?= number_format($post['view_count']) ?> <?= __('views') ?>
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

                                        <?php if (!empty($post['tags'])): ?>
                                            <div class="blog-tags">
                                                <?php foreach ($post['tags'] as $tag_id => $tag_name): ?>
                                                    <a href="?tag=<?= $tag_id ?>" class="blog-tag">
                                                        #<?= htmlspecialchars($tag_name) ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div class="blog-actions">
                                            <a href="view_blog.php?slug=<?= $post['slug'] ?>" class="read-more">
                                                <?= __('read_more') ?> <i class="fas fa-arrow-right"></i>
                                            </a>
                                            <div class="social-share">
                                                <button class="share-btn"
                                                    onclick="sharePost('<?= $post['slug'] ?>', '<?= htmlspecialchars($post['title']) ?>')">
                                                    <i class="fas fa-share-alt"></i>
                                                </button>
                                            </div>
                                        </div>
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
                                        <i class="fas fa-chevron-left"></i> <?= __('previous') ?>
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
                                        <?= __('next') ?> <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <aside class="blog-sidebar">
                    <!-- Newsletter Subscription -->
                    <div class="sidebar-section newsletter-section">
                        <h3><i class="fas fa-envelope"></i> <?= __('newsletter_title') ?></h3>
                        <p><?= __('newsletter_description') ?></p>
                        <form class="newsletter-form" id="newsletterForm">
                            <input type="email" placeholder="<?= __('newsletter_placeholder') ?>" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> <?= __('newsletter_subscribe') ?>
                            </button>
                        </form>
                    </div>

                    <!-- Recent Posts -->
                    <div class="sidebar-section">
                        <h3><i class="fas fa-clock"></i> <?= __('recent_articles') ?></h3>
                        <div class="recent-posts">
                            <?php foreach ($recent_posts as $recent): ?>
                                <div class="recent-post">
                                    <div class="recent-post-image">
                                        <?php if ($recent['featured_image']): ?>
                                            <img src="../../uploads/<?= htmlspecialchars($recent['featured_image']) ?>"
                                                alt="<?= htmlspecialchars($recent['title']) ?>" loading="lazy">
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
                                        <div class="recent-post-meta">
                                            <span class="recent-post-date">
                                                <i class="fas fa-calendar"></i>
                                                <?= date('d/m/Y', strtotime($recent['published_at'])) ?>
                                            </span>
                                            <span class="recent-post-views">
                                                <i class="fas fa-eye"></i>
                                                <?= number_format($recent['view_count']) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Popular Posts -->
                    <div class="sidebar-section">
                        <h3><i class="fas fa-fire"></i> <?= __('popular_articles') ?></h3>
                        <div class="popular-posts">
                            <?php foreach ($popular_posts as $popular): ?>
                                <div class="popular-post">
                                    <div class="popular-post-number">
                                        <?= array_search($popular, $popular_posts) + 1 ?>
                                    </div>
                                    <div class="popular-post-content">
                                        <h4>
                                            <a href="view_blog.php?slug=<?= $popular['slug'] ?>">
                                                <?= htmlspecialchars($popular['title']) ?>
                                            </a>
                                        </h4>
                                        <span class="popular-post-views">
                                            <i class="fas fa-eye"></i> <?= number_format($popular['view_count']) ?> <?= __('views') ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Categories -->
                    <div class="sidebar-section">
                        <h3><i class="fas fa-folder"></i> <?= __('categories') ?></h3>
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

                    <!-- Tags Cloud -->
                    <div class="sidebar-section">
                        <h3><i class="fas fa-tags"></i> <?= __('popular_tags') ?></h3>
                        <div class="tags-cloud">
                            <?php foreach ($tags as $tag): ?>
                                <a href="?tag=<?= $tag['id'] ?>" class="tag-cloud-item"
                                    style="font-size: <?= min(1.2, max(0.8, $tag['post_count'] / 5)) ?>rem">
                                    #<?= htmlspecialchars($tag['name']) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Related Posts (if viewing a category) -->
                    <?php if (!empty($related_posts)): ?>
                        <div class="sidebar-section">
                            <h3><i class="fas fa-link"></i> <?= __('related_articles') ?></h3>
                            <div class="related-posts">
                                <?php foreach ($related_posts as $related): ?>
                                    <div class="related-post">
                                        <h4>
                                            <a href="view_blog.php?slug=<?= $related['slug'] ?>">
                                                <?= htmlspecialchars($related['title']) ?>
                                            </a>
                                        </h4>
                                        <span class="related-post-views">
                                            <i class="fas fa-eye"></i> <?= number_format($related['view_count']) ?> <?= __('views') ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
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
                    <p><?= __('footer_description') ?></p>
                </div>
                <div class="footer-section">
                    <h4><?= __('quick_links') ?></h4>
                    <ul>
                        <li><a href="index.php"><?= __('welcome') ?></a></li>
                        <li><a href="courses.php"><?= __('courses') ?></a></li>
                        <li><a href="shop.php"><?= __('shop') ?></a></li>
                        <li><a href="blog.php"><?= __('blog') ?></a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4><?= __('contact') ?></h4>
                    <p>Email: <?= __('email') ?></p>
                    <p><?= __('phone') ?>: <?= __('phone') ?></p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Taabia. <?= __('footer_rights_reserved') ?></p>
            </div>
        </div>
    </footer>

    <script>
        // Language translations for JavaScript
        const translations = {
            linkCopied: '<?= __('link_copied') ?>',
            thanksSubscription: '<?= __('thanks_subscription') ?>'
        };

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

        // View toggle functionality
        const viewBtns = document.querySelectorAll('.view-btn');
        const blogGrid = document.getElementById('blogGrid');

        viewBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                viewBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const view = btn.dataset.view;
                blogGrid.className = `blog-grid ${view}`;

                // Store preference
                localStorage.setItem('blogView', view);
            });
        });

        // Load saved view preference
        const savedView = localStorage.getItem('blogView') || 'grid';
        const savedBtn = document.querySelector(`[data-view="${savedView}"]`);
        if (savedBtn) {
            savedBtn.click();
        }

        // Search suggestions
        const searchInput = document.getElementById('searchInput');
        const suggestions = document.getElementById('searchSuggestions');
        const suggestionsList = document.getElementById('suggestionsList');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                suggestions.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetchSearchSuggestions(query);
            }, 300);
        });

        function fetchSearchSuggestions(query) {
            // This would typically make an AJAX call to get suggestions
            // For now, we'll use a simple client-side approach
            const allTitles = Array.from(document.querySelectorAll('.blog-title a'))
                .map(link => link.textContent.trim())
                .filter(title => title.toLowerCase().includes(query.toLowerCase()));

            if (allTitles.length > 0) {
                suggestionsList.innerHTML = allTitles
                    .slice(0, 5)
                    .map(title => `<div class="suggestion-item">${title}</div>`)
                    .join('');
                suggestions.style.display = 'block';
            } else {
                suggestions.style.display = 'none';
            }
        }

        // Hide suggestions when clicking outside
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target) && !suggestions.contains(e.target)) {
                suggestions.style.display = 'none';
            }
        });

        // Clear filters function
        function clearFilters() {
            document.getElementById('searchForm').reset();
            window.location.href = 'blog.php';
        }

        // Social sharing
        function sharePost(slug, title) {
            if (navigator.share) {
                navigator.share({
                    title: title,
                    url: `${window.location.origin}/public/main_site/view_blog.php?slug=${slug}`
                });
            } else {
                // Fallback: copy to clipboard
                const url = `${window.location.origin}/public/main_site/view_blog.php?slug=${slug}`;
                navigator.clipboard.writeText(url).then(() => {
                    alert(translations.linkCopied);
                });
            }
        }

        // Newsletter subscription
        document.getElementById('newsletterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = this.querySelector('input[type="email"]').value;

            // Here you would typically send the email to your server
            alert(translations.thanksSubscription);
            this.reset();
        });

        // Infinite scroll (optional)
        let isLoading = false;
        let currentPage = <?= $page ?>;
        const totalPages = <?= $total_pages ?>;

        function loadMorePosts() {
            if (isLoading || currentPage >= totalPages) return;

            isLoading = true;
            currentPage++;

            const url = new URL(window.location);
            url.searchParams.set('page', currentPage);

            fetch(url)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newPosts = doc.querySelectorAll('.blog-card');

                    newPosts.forEach(post => {
                        blogGrid.appendChild(post);
                    });

                    isLoading = false;
                })
                .catch(error => {
                    console.error('Error loading more posts:', error);
                    isLoading = false;
                });
        }

        // Intersection Observer for infinite scroll
        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                loadMorePosts();
            }
        }, {
            threshold: 0.1
        });

        // Observe the last post for infinite scroll
        const lastPost = document.querySelector('.blog-card:last-child');
        if (lastPost) {
            observer.observe(lastPost);
        }

        // Auto-submit form when filters change
        document.querySelectorAll('select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Lazy loading for images
        const images = document.querySelectorAll('img[loading="lazy"]');
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    </script>
</body>

</html>