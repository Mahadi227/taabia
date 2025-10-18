<?php
// Handle language switching first
require_once '../../includes/language_handler.php';
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$vendor = trim($_GET['vendor'] ?? '');
$min_price = floatval($_GET['min_price'] ?? 0);
$max_price = floatval($_GET['max_price'] ?? 999999);
$sort = $_GET['sort'] ?? 'newest';

// Pagination settings
$products_per_page = 15;
$current_page = max(1, intval($_GET['page'] ?? 1));
$offset = ($current_page - 1) * $products_per_page;

// Build the query
$where_conditions = ["p.status = 'active'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $where_conditions[] = "p.category = ?";
    $params[] = $category;
}

if (!empty($vendor)) {
    $where_conditions[] = "u.fullname LIKE ?";
    $params[] = "%$vendor%";
}

if ($min_price > 0) {
    $where_conditions[] = "p.price >= ?";
    $params[] = $min_price;
}

if ($max_price < 999999) {
    $where_conditions[] = "p.price <= ?";
    $params[] = $max_price;
}

$where_clause = implode(' AND ', $where_conditions);

// Sort options
$order_by = match ($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'name' => 'p.name ASC',
    'oldest' => 'p.created_at ASC',
    default => 'p.created_at DESC'
};

// Get total count for pagination
try {
    $count_query = "
        SELECT COUNT(*) 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE $where_clause
    ";

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
    $total_pages = ceil($total_products / $products_per_page);
} catch (PDOException $e) {
    $total_products = 0;
    $total_pages = 1;
}

// Get products with vendor information and pagination
try {
    $query = "
        SELECT p.*, u.fullname AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE $where_clause 
        ORDER BY $order_by
        LIMIT $products_per_page OFFSET $offset
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    // Get categories for filter
    $categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);

    // Get vendors for filter
    $vendors = $pdo->query("
        SELECT DISTINCT u.fullname 
        FROM users u 
        JOIN products p ON u.id = p.vendor_id 
        WHERE p.status = 'active' AND u.fullname IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Get shop statistics
    $shop_stats = [
        'total_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
        'total_vendors' => $pdo->query("SELECT COUNT(DISTINCT vendor_id) FROM products WHERE status = 'active'")->fetchColumn(),
        'avg_price' => $pdo->query("SELECT AVG(price) FROM products WHERE status = 'active'")->fetchColumn(),
        'categories_count' => $pdo->query("SELECT COUNT(DISTINCT category) FROM products WHERE status = 'active'")->fetchColumn()
    ];
} catch (PDOException $e) {
    $products = [];
    $categories = [];
    $vendors = [];
    $shop_stats = ['total_products' => 0, 'total_vendors' => 0, 'avg_price' => 0, 'categories_count' => 0];
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('shop') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            --shadow-light: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 4px 8px rgba(0, 0, 0, 0.12);
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

        .products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
        }

        /* Responsive Grid Breakpoints */
        @media (min-width: 1400px) {
            .products {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) and (min-width: 992px) {
            .products {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 991px) and (min-width: 769px) {
            .products {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .products {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .products {
                gap: var(--spacing-sm);
            }
        }

        .product {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .product:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-4px);
        }

        .product img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: var(--spacing-lg);
        }

        .product-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .product-price {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: var(--spacing-md);
        }

        .product-description {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.5;
        }

        .product-vendor {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-md);
        }

        .w-100 {
            width: 100%;
        }

        .text-center {
            text-align: center;
        }

        .mb-4 {
            margin-bottom: var(--spacing-lg);
        }

        .mt-5 {
            margin-top: var(--spacing-xl);
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: var(--spacing-md);
                padding: var(--spacing-md);
            }

            .nav-menu {
                flex-direction: column;
                gap: var(--spacing-md);
            }

            .nav-actions {
                flex-direction: column;
                width: 100%;
            }

            .products {
                grid-template-columns: 1fr;
            }
        }

        .footer {
            background: var(--text-primary);
            color: var(--text-white);
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
            color: var(--primary-light);
        }

        .footer-section p,
        .footer-section a {
            color: var(--text-secondary);
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

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-2xl);
            padding: var(--spacing-lg);
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-right: var(--spacing-lg);
        }

        .pagination-nav {
            display: flex;
            gap: var(--spacing-sm);
            align-items: center;
        }

        .pagination-btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
            cursor: pointer;
        }

        .pagination-btn:hover:not(.disabled) {
            background: var(--primary-color);
            color: var(--text-white);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .pagination-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .pagination-btn.active {
            background: var(--primary-color);
            color: var(--text-white);
            border-color: var(--primary-color);
        }

        .pagination-page {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            transition: var(--transition);
        }

        .pagination-page:hover {
            background: var(--primary-color);
            color: var(--text-white);
            border-color: var(--primary-color);
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .pagination-page.active {
            background: var(--primary-color);
            color: var(--text-white);
            border-color: var(--primary-color);
        }

        .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .pagination {
                flex-direction: column;
                gap: var(--spacing-md);
            }

            .pagination-info {
                margin-right: 0;
                margin-bottom: var(--spacing-sm);
            }

            .pagination-nav {
                flex-wrap: wrap;
                justify-content: center;
            }
        }

        /* Dynamic Stock Indicators */
        .stock-indicator {
            position: relative;
        }

        .stock-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            color: white;
            transition: var(--transition);
            cursor: help;
        }

        .stock-badge.in-stock {
            background: var(--success-color);
        }

        .stock-badge.medium-stock {
            background: var(--warning-color);
        }

        .stock-badge.low-stock {
            background: var(--danger-color);
            animation: pulse 2s infinite;
        }

        .stock-badge.out-of-stock {
            background: var(--danger-color);
            opacity: 0.8;
        }

        .stock-badge:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .stock-badge i {
            font-size: 0.7rem;
        }

        @keyframes pulse {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.7;
            }

            100% {
                opacity: 1;
            }
        }

        .stock-update-animation {
            animation: stockUpdate 0.5s ease-in-out;
        }

        @keyframes stockUpdate {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .stock-notification {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--warning-color);
            color: white;
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            z-index: 10001;
            animation: slideDown 0.3s ease-out;
            box-shadow: var(--shadow-medium);
        }

        @keyframes slideDown {
            from {
                transform: translateX(-50%) translateY(-100%);
                opacity: 0;
            }

            to {
                transform: translateX(-50%) translateY(0);
                opacity: 1;
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
    <!-- Main Content -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h1><i class="fas fa-shopping-bag"></i> <?= __('shop') ?> TaaBia</h1>
                <p><?= __('shop_description') ?></p>
            </div>

            <!-- Statistics Section -->
            <div style="background: var(--bg-primary); border-radius: var(--border-radius); padding: var(--spacing-xl); margin-bottom: var(--spacing-2xl); box-shadow: var(--shadow-light);">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-xl);">
                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-box"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);"><?= number_format($shop_stats['total_products']) ?></h3>
                        <p style="color: var(--text-secondary);"><?= __('total_products') ?></p>
                    </div>

                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--secondary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-store"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);"><?= number_format($shop_stats['total_vendors']) ?></h3>
                        <p style="color: var(--text-secondary);"><?= __('total_vendors') ?></p>
                    </div>

                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--success-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);"><?= number_format($shop_stats['categories_count']) ?></h3>
                        <p style="color: var(--text-secondary);"><?= __('categories') ?></p>
                    </div>

                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--warning-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);"><?= number_format($shop_stats['avg_price'], 2) ?> GHS</h3>
                        <p style="color: var(--text-secondary);"><?= __('average_price') ?></p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div style="background: var(--bg-primary); border-radius: var(--border-radius); padding: var(--spacing-xl); margin-bottom: var(--spacing-2xl); box-shadow: var(--shadow-light);">
                <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-lg); align-items: end;">
                    <div>
                        <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('search') ?></label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_products') ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('category') ?></label>
                        <select name="category" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value=""><?= __('all_categories') ?></option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('vendor') ?></label>
                        <select name="vendor" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value=""><?= __('all_vendors') ?></option>
                            <?php foreach ($vendors as $ven): ?>
                                <option value="<?= htmlspecialchars($ven) ?>" <?= $vendor === $ven ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ven) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('min_price') ?></label>
                        <input type="number" name="min_price" value="<?= htmlspecialchars($min_price) ?>" min="0" step="0.01"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('max_price') ?></label>
                        <input type="number" name="max_price" value="<?= htmlspecialchars($max_price) ?>" min="0" step="0.01"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('sort_by') ?></label>
                        <select name="sort" style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= __('newest') ?></option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>><?= __('oldest') ?></option>
                            <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>><?= __('price_low_to_high') ?></option>
                            <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>><?= __('price_high_to_low') ?></option>
                            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>><?= __('name') ?></option>
                        </select>
                    </div>

                    <div style="display: flex; gap: var(--spacing-sm);">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> <?= __('search') ?>
                        </button>
                        <a href="shop.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?= __('clear') ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Summary -->
            <div style="background: var(--bg-primary); border-radius: var(--border-radius); padding: var(--spacing-lg); margin-bottom: var(--spacing-xl); box-shadow: var(--shadow-light);">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--spacing-md);">
                    <div>
                        <h3 style="color: var(--text-primary); margin-bottom: var(--spacing-sm);"><?= __('search_results') ?></h3>
                        <p style="color: var(--text-secondary);">
                            <?= $total_products ?> <?= __('products_found') ?>
                            <?php if ($total_pages > 1): ?>
                                (<?= __('page') ?> <?= $current_page ?> <?= __('of') ?> <?= $total_pages ?>)
                            <?php endif; ?>
                            <?php if (!empty($search) || !empty($category) || !empty($vendor) || $min_price > 0 || $max_price < 999999): ?>
                                <?= __('with_filters') ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if (!empty($search) || !empty($category) || !empty($vendor) || $min_price > 0 || $max_price < 999999): ?>
                        <a href="shop.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="products">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product">
                            <?php if ($product['image_url']): ?>
                                <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>"
                                    alt="<?= htmlspecialchars($product['name']) ?>">
                            <?php else: ?>
                                <div style="width: 100%; height: 200px; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                                    <i class="fas fa-box"></i>
                                </div>
                            <?php endif; ?>

                            <div class="product-info">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-sm);">
                                    <span style="background: var(--primary-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                        <?= htmlspecialchars($product['category'] ?? 'Général') ?>
                                    </span>
                                    <div class="stock-indicator" data-product-id="<?= $product['id'] ?>" data-current-stock="<?= $product['stock_quantity'] ?>">
                                        <?php if ($product['stock_quantity'] > 0): ?>
                                            <?php if ($product['stock_quantity'] <= 5): ?>
                                                <span class="stock-badge low-stock" title="<?= __('low_stock') ?> - <?= $product['stock_quantity'] ?> <?= __('remaining') ?>(s)">
                                                    <i class="fas fa-exclamation-triangle"></i> <?= $product['stock_quantity'] ?> <?= __('remaining') ?>(s)
                                                </span>
                                            <?php elseif ($product['stock_quantity'] <= 20): ?>
                                                <span class="stock-badge medium-stock" title="<?= __('limited_stock') ?> - <?= $product['stock_quantity'] ?> <?= __('available') ?>(s)">
                                                    <i class="fas fa-info-circle"></i> <?= $product['stock_quantity'] ?> <?= __('available') ?>(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="stock-badge in-stock" title="<?= __('in_stock') ?> - <?= $product['stock_quantity'] ?> <?= __('available') ?>(s)">
                                                    <i class="fas fa-check-circle"></i> <?= __('in_stock') ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="stock-badge out-of-stock" title="<?= __('out_of_stock') ?>">
                                                <i class="fas fa-times-circle"></i> <?= __('out_of_stock') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>

                                <div class="product-vendor">
                                    <i class="fas fa-store"></i> <?= htmlspecialchars($product['vendor_name'] ?? __('vendor')) ?>
                                </div>

                                <div class="product-price"><?= number_format($product['price'], 2) ?> GHS</div>

                                <p class="product-description">
                                    <?= htmlspecialchars(substr($product['description'], 0, 120)) ?>...
                                </p>

                                <div style="display: flex; gap: var(--spacing-sm); margin-top: var(--spacing-lg);">
                                    <a href="view_product.php?id=<?= $product['id'] ?>" class="btn btn-primary" style="flex: 1;">
                                        <i class="fas fa-eye"></i> <?= __('view_details') ?>
                                    </a>
                                    <?php if ($product['stock_quantity'] > 0): ?>
                                        <button onclick="addToCart(<?= $product['id'] ?>, 'product')" class="btn btn-secondary">
                                            <i class="fas fa-cart-plus"></i> <?= __('add_to_cart') ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                        <div style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-box-open"></i>
                        </div>
                        <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);"><?= __('no_products_available') ?></h3>
                        <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                            <?php if (!empty($search) || !empty($category) || !empty($vendor) || $min_price > 0 || $max_price < 999999): ?>
                                <?= __('no_products_with_filters') ?>
                            <?php else: ?>
                                <?= __('no_products_description') ?>
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search) || !empty($category) || !empty($vendor) || $min_price > 0 || $max_price < 999999): ?>
                            <a href="shop.php" class="btn btn-primary">
                                <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Affichage de <?= ($offset + 1) ?> à <?= min($offset + $products_per_page, $total_products) ?>
                        sur <?= $total_products ?> produits
                    </div>

                    <div class="pagination-nav">
                        <!-- Previous button -->
                        <?php if ($current_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page - 1])) ?>"
                                class="pagination-btn">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </span>
                        <?php endif; ?>

                        <!-- Page numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);

                        // Show first page if not in range
                        if ($start_page > 1) {
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '" class="pagination-page">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                        }

                        // Show pages in range
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="pagination-page active">' . $i . '</span>';
                            } else {
                                echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $i])) . '" class="pagination-page">' . $i . '</a>';
                            }
                        }

                        // Show last page if not in range
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="pagination-ellipsis">...</span>';
                            }
                            echo '<a href="?' . http_build_query(array_merge($_GET, ['page' => $total_pages])) . '" class="pagination-page">' . $total_pages . '</a>';
                        }
                        ?>

                        <!-- Next button -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $current_page + 1])) ?>"
                                class="pagination-btn">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn disabled">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
                    <a href="courses.php"><?= __('courses') ?></a>
                    <a href="shop.php"><?= __('shop') ?></a>
                    <a href="upcoming_events.php"><?= __('events') ?></a>
                    <a href="contact.php"><?= __('contact') ?></a>
                </div>

                <div class="footer-section">
                    <h3><?= __('footer_contact') ?></h3>
                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                    <p><i class="fas fa-phone"></i> +233348999</p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
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

        // Add to Cart Functionality
        function addToCart(productId, type) {
            fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&type=${type}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('<?= __('product_added_to_cart') ?>', 'success');
                        updateCartCount(data.cart_count);
                    } else {
                        showNotification(data.message || '<?= __('error_adding_to_cart') ?>', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('<?= __('error_adding_to_cart') ?>', 'error');
                });
        }

        // Show notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: var(--spacing-md) var(--spacing-lg);
                border-radius: var(--border-radius-sm);
                color: white;
                font-weight: 500;
                z-index: 10000;
                animation: slideIn 0.3s ease-out;
                background: ${type === 'success' ? 'var(--success-color)' : 'var(--danger-color)'};
            `;
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Update cart count
        function updateCartCount(count) {
            const cartLink = document.querySelector('a[href="basket.php"]');
            if (cartLink) {
                let badge = cartLink.querySelector('.cart-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'cart-badge';
                    badge.style.cssText = `
                        position: absolute;
                        top: -8px;
                        right: -8px;
                        background: var(--danger-color);
                        color: white;
                        border-radius: 50%;
                        width: 20px;
                        height: 20px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 0.75rem;
                        font-weight: 600;
                    `;
                    cartLink.style.position = 'relative';
                    cartLink.appendChild(badge);
                }
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        }

        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('form');
            const filterInputs = filterForm.querySelectorAll('select, input[type="number"]');

            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });

            // Debounced search
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500);
            });
        });

        // Dynamic Stock Management
        let stockUpdateInterval;
        let lastStockData = {};

        // Initialize stock monitoring
        function initializeStockMonitoring() {
            // Store initial stock data
            document.querySelectorAll('.stock-indicator').forEach(indicator => {
                const productId = indicator.dataset.productId;
                const currentStock = parseInt(indicator.dataset.currentStock);
                lastStockData[productId] = currentStock;
            });

            // Start periodic stock updates
            stockUpdateInterval = setInterval(updateStockStatus, 30000); // Update every 30 seconds

            // Add click handlers for stock indicators
            document.querySelectorAll('.stock-indicator').forEach(indicator => {
                indicator.addEventListener('click', function() {
                    const productId = this.dataset.productId;
                    const currentStock = parseInt(this.dataset.currentStock);
                    showStockDetails(productId, currentStock);
                });
            });
        }

        // Update stock status from server
        function updateStockStatus() {
            const productIds = Object.keys(lastStockData);
            if (productIds.length === 0) return;

            fetch('get_stock_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        product_ids: productIds
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        data.stocks.forEach(stock => {
                            updateStockIndicator(stock.product_id, stock.stock, stock.last_updated);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error updating stock status:', error);
                });
        }

        // Update individual stock indicator
        function updateStockIndicator(productId, newStock, lastUpdated) {
            const indicator = document.querySelector(`[data-product-id="${productId}"]`);
            if (!indicator) return;

            const oldStock = lastStockData[productId];
            if (oldStock === newStock) return;

            // Update the indicator
            indicator.dataset.currentStock = newStock;
            lastStockData[productId] = newStock;

            // Remove old badge
            const oldBadge = indicator.querySelector('.stock-badge');
            if (oldBadge) {
                oldBadge.remove();
            }

            // Create new badge
            let newBadge;
            if (newStock > 0) {
                if (newStock <= 5) {
                    newBadge = `<span class="stock-badge low-stock" title="<?= __('low_stock') ?> - ${newStock} <?= __('remaining') ?>(s)">
                        <i class="fas fa-exclamation-triangle"></i> ${newStock} <?= __('remaining') ?>(s)
                    </span>`;
                } else if (newStock <= 20) {
                    newBadge = `<span class="stock-badge medium-stock" title="<?= __('limited_stock') ?> - ${newStock} <?= __('available') ?>(s)">
                        <i class="fas fa-info-circle"></i> ${newStock} <?= __('available') ?>(s)
                    </span>`;
                } else {
                    newBadge = `<span class="stock-badge in-stock" title="<?= __('in_stock') ?> - ${newStock} <?= __('available') ?>(s)">
                        <i class="fas fa-check-circle"></i> <?= __('in_stock') ?>
                    </span>`;
                }
            } else {
                newBadge = `<span class="stock-badge out-of-stock" title="<?= __('out_of_stock') ?>">
                    <i class="fas fa-times-circle"></i> <?= __('out_of_stock') ?>
                </span>`;
            }

            indicator.innerHTML = newBadge;

            // Add update animation
            indicator.classList.add('stock-update-animation');
            setTimeout(() => {
                indicator.classList.remove('stock-update-animation');
            }, 500);

            // Show notification for significant changes
            if (oldStock > 0 && newStock === 0) {
                showStockNotification('<?= __('product_out_of_stock') ?>', 'warning');
            } else if (oldStock === 0 && newStock > 0) {
                showStockNotification('<?= __('product_back_in_stock') ?>', 'success');
            } else if (oldStock > 5 && newStock <= 5) {
                showStockNotification('<?= __('low_stock_detected') ?>', 'warning');
            }

            // Update add to cart button
            updateAddToCartButton(productId, newStock);
        }

        // Update add to cart button based on stock
        function updateAddToCartButton(productId, stock) {
            const productCard = document.querySelector(`[data-product-id="${productId}"]`).closest('.product');
            const addToCartBtn = productCard.querySelector('button[onclick*="addToCart"]');

            if (stock > 0) {
                if (!addToCartBtn) {
                    const viewDetailsBtn = productCard.querySelector('a[href*="view_product.php"]');
                    const newBtn = document.createElement('button');
                    newBtn.className = 'btn btn-secondary';
                    newBtn.onclick = () => addToCart(productId, 'product');
                    newBtn.innerHTML = '<i class="fas fa-cart-plus"></i> <?= __('add_to_cart') ?>';
                    viewDetailsBtn.parentNode.appendChild(newBtn);
                }
            } else {
                if (addToCartBtn) {
                    addToCartBtn.remove();
                }
            }
        }

        // Show stock details modal
        function showStockDetails(productId, currentStock) {
            const productName = document.querySelector(`[data-product-id="${productId}"]`).closest('.product').querySelector('.product-title').textContent;

            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10000;
            `;

            modal.innerHTML = `
                <div style="background: white; padding: var(--spacing-xl); border-radius: var(--border-radius); max-width: 400px; width: 90%;">
                    <h3 style="margin-bottom: var(--spacing-lg);">Détails du stock</h3>
                    <p><strong>Produit:</strong> ${productName}</p>
                    <p><strong>Stock actuel:</strong> ${currentStock} unité(s)</p>
                    <p><strong>Dernière mise à jour:</strong> ${new Date().toLocaleString()}</p>
                    <div style="margin-top: var(--spacing-lg); text-align: right;">
                        <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" class="btn btn-primary">Fermer</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.remove();
                }
            });
        }

        // Show stock notification
        function showStockNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = 'stock-notification';
            notification.style.background = type === 'success' ? 'var(--success-color)' : 'var(--warning-color)';
            notification.textContent = message;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Enhanced add to cart with stock check
        function addToCart(productId, type) {
            const indicator = document.querySelector(`[data-product-id="${productId}"]`);
            const currentStock = parseInt(indicator.dataset.currentStock);

            if (currentStock <= 0) {
                showNotification('Ce produit est en rupture de stock', 'error');
                return;
            }

            fetch('add_to_cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `product_id=${productId}&type=${type}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('<?= __('product_added_to_cart') ?>', 'success');
                        updateCartCount(data.cart_count);

                        // Update stock immediately after adding to cart
                        if (data.new_stock !== undefined) {
                            updateStockIndicator(productId, data.new_stock, new Date().toISOString());
                        }
                    } else {
                        showNotification(data.message || '<?= __('error_adding_to_cart') ?>', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('<?= __('error_adding_to_cart') ?>', 'error');
                });
        }

        // Initialize stock monitoring when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeStockMonitoring();
        });

        // Clean up interval when page unloads
        window.addEventListener('beforeunload', function() {
            if (stockUpdateInterval) {
                clearInterval(stockUpdateInterval);
            }
        });

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>