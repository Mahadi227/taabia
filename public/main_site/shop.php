<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get products with vendor information
try {
    $products = $pdo->query("
        SELECT p.*, u.full_name AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.status = 'active' 
        ORDER BY p.created_at DESC
    ")->fetchAll();
} catch (PDOException $e) {
    $products = [];
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
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

        .w-100 { width: 100%; }
        .text-center { text-align: center; }
        .mb-4 { margin-bottom: var(--spacing-lg); }
        .mt-5 { margin-top: var(--spacing-xl); }

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

        .footer-section p, .footer-section a {
            color: var(--text-secondary);
            text-decoration: none;
            margin-bottom: var(--spacing-sm);
            display: block;
        }

        .footer-section a:hover {
            color: var(--primary-light);33
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
            
            <div class="products">
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $product): ?>
                        <div class="product">
                            <?php if ($product['image_url']): ?>
                                <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>">
                            <?php else: ?>
                                <div style="width: 100%; height: 200px; background: var(--bg-secondary); display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-image" style="font-size: 3rem; color: var(--text-secondary);"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                                <div class="product-vendor">
                                    <i class="fas fa-store"></i> <?= htmlspecialchars($product['vendor_name'] ?? 'Vendeur') ?>
                                </div>
                                <div class="product-price"><?= number_format($product['price'], 2) ?> GHS</div>
                                <p class="product-description">
                                    <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...
                                </p>
                                                                 <a href="view_product.php?id=<?= $product['id'] ?>" class="btn btn-primary w-100">
                                     <i class="fas fa-eye"></i> <?= __('view_details') ?>
                                 </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                        <i class="fas fa-box-open" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></i>
                                                 <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);"><?= __('no_products_available') ?></h3>
                         <p style="color: var(--text-secondary);"><?= __('no_products_description') ?></p>
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
                     <a href="courses.php"><?= __('courses') ?></a>
                     <a href="shop.php"><?= __('shop') ?></a>
                     <a href="upcoming_events.php"><?= __('events') ?></a>
                     <a href="contact.php"><?= __('contact') ?></a>
                </div>
                
                <div class="footer-section">
                                         <h3><?= __('footer_contact') ?></h3>
                     <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                     <p><i class="fas fa-phone"></i> +233 XX XXX XXXX</p>
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
    </script>
</body>
</html>
