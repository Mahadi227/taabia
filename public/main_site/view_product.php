<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get product ID
$product_id = intval($_GET['id'] ?? 0);

if (!$product_id) {
    header('Location: shop.php');
    exit;
}

// Get product details
try {
    $query = "
        SELECT p.*, u.fullname AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.id = ? AND p.status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('Location: shop.php');
        exit;
    }
    
    // Get related products
    $related_query = "
        SELECT p.*, u.fullname AS vendor_name
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.status = 'active' 
        AND p.id != ? 
        AND p.category = ?
        ORDER BY p.created_at DESC
        LIMIT 4
    ";
    
    $related_stmt = $pdo->prepare($related_query);
    $related_stmt->execute([$product_id, $product['category']]);
    $related_products = $related_stmt->fetchAll();
    
    // Get vendor's other products
    $vendor_products_query = "
        SELECT p.*, u.fullname AS vendor_name
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.status = 'active' 
        AND p.id != ? 
        AND p.vendor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 4
    ";
    
    $vendor_products_stmt = $pdo->prepare($vendor_products_query);
    $vendor_products_stmt->execute([$product_id, $product['vendor_id']]);
    $vendor_products = $vendor_products_stmt->fetchAll();
    
} catch (PDOException $e) {
    header('Location: shop.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | TaaBia</title>
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

        .btn-success {
            background: var(--success-color);
            color: var(--text-white);
        }

        .btn-success:hover {
            background: #388e3c;
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .btn-lg {
            padding: var(--spacing-md) var(--spacing-xl);
            font-size: 1rem;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }

        .section {
            padding: var(--spacing-2xl) 0;
        }

        .product-hero {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        .product-image-placeholder {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        .product-content {
            padding: var(--spacing-2xl);
        }

        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
            gap: var(--spacing-lg);
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .product-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .meta-item i {
            color: var(--primary-color);
            width: 16px;
        }

        .product-description {
            color: var(--text-primary);
            line-height: 1.8;
            margin-bottom: var(--spacing-xl);
            font-size: 1.1rem;
        }

        .product-actions {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }

        .product-details {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }

        .details-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .product-stock {
            background: var(--success-color);
            color: white;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .product-stock.out-of-stock {
            background: var(--danger-color);
        }

        .related-products {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-light);
        }

        .related-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
        }

        .related-product {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-lg);
            transition: var(--transition);
        }

        .related-product:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-light);
        }

        .related-product-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .related-product-price {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }

        .related-product-vendor {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-md);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-xl);
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .status-badge {
            background: var(--success-color);
            color: white;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
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
            
            .product-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .product-actions {
                width: 100%;
            }
            
            .product-actions .btn {
                flex: 1;
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
            
            <ul class="nav-menu">
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

    <!-- Main Content -->
    <section class="section">
        <div class="container">
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php">Accueil</a>
                <i class="fas fa-chevron-right"></i>
                <a href="shop.php">Boutique</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= htmlspecialchars($product['name']) ?></span>
            </div>

            <!-- Product Hero -->
            <div class="product-hero">
                <?php if ($product['image_url']): ?>
                    <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                         alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="product-image">
                <?php else: ?>
                    <div class="product-image-placeholder">
                        <i class="fas fa-box"></i>
                    </div>
                <?php endif; ?>
                
                <div class="product-content">
                    <div class="product-header">
                        <div>
                            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                            <div class="status-badge">Produit disponible</div>
                        </div>
                        
                        <div class="product-actions">
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <button onclick="addToCart(<?= $product['id'] ?>, 'product')" class="btn btn-primary btn-lg">
                                    <i class="fas fa-cart-plus"></i> Ajouter au panier
                                </button>
                            <?php endif; ?>
                            
                            <a href="shop.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>

                    <div class="product-meta">
                        <div class="meta-item">
                            <i class="fas fa-store"></i>
                            <span>Vendu par <?= htmlspecialchars($product['vendor_name'] ?? 'TaaBia') ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-tags"></i>
                            <span><?= htmlspecialchars($product['category'] ?? 'Général') ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-box"></i>
                            <span><?= $product['stock_quantity'] ?> en stock</span>
                        </div>
                        
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span>Ajouté le <?= date('d/m/Y', strtotime($product['created_at'])) ?></span>
                        </div>
                    </div>

                    <div class="product-description">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                </div>
            </div>

            <!-- Product Details -->
            <div class="product-details">
                <div class="details-header">
                    <h2 class="details-title">Détails du produit</h2>
                    <div style="display: flex; align-items: center; gap: var(--spacing-lg);">
                        <div class="product-price"><?= number_format($product['price'], 2) ?> GHS</div>
                        <div class="product-stock <?= $product['stock_quantity'] <= 0 ? 'out-of-stock' : '' ?>">
                            <?= $product['stock_quantity'] > 0 ? 'En stock' : 'Rupture de stock' ?>
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-lg);">
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: var(--spacing-sm);">Informations</h4>
                        <ul style="color: var(--text-secondary); list-style: none; padding: 0;">
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-box" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                                Stock: <?= $product['stock_quantity'] ?> unités
                            </li>
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-tags" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                                Catégorie: <?= htmlspecialchars($product['category'] ?? 'Général') ?>
                            </li>
                            <li style="margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-store" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                                Vendeur: <?= htmlspecialchars($product['vendor_name'] ?? 'TaaBia') ?>
                            </li>
                        </ul>
                    </div>
                    
                    <div>
                        <h4 style="color: var(--text-primary); margin-bottom: var(--spacing-sm);">Actions</h4>
                        <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                            <?php if ($product['stock_quantity'] > 0): ?>
                                <button onclick="addToCart(<?= $product['id'] ?>, 'product')" class="btn btn-primary">
                                    <i class="fas fa-cart-plus"></i> Ajouter au panier
                                </button>
                            <?php endif; ?>
                            <a href="shop.php?category=<?= urlencode($product['category']) ?>" class="btn btn-secondary">
                                <i class="fas fa-search"></i> Voir plus de <?= htmlspecialchars($product['category'] ?? 'produits') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
                <div class="related-products">
                    <h2 class="related-title">Produits similaires</h2>
                    <div class="related-grid">
                        <?php foreach ($related_products as $related): ?>
                            <div class="related-product">
                                <h3 class="related-product-title">
                                    <a href="view_product.php?id=<?= $related['id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($related['name']) ?>
                                    </a>
                                </h3>
                                <div class="related-product-price"><?= number_format($related['price'], 2) ?> GHS</div>
                                <div class="related-product-vendor">
                                    <i class="fas fa-store"></i> <?= htmlspecialchars($related['vendor_name'] ?? 'TaaBia') ?>
                                </div>
                                <a href="view_product.php?id=<?= $related['id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> Voir détails
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Vendor's Other Products -->
            <?php if (!empty($vendor_products)): ?>
                <div class="related-products">
                    <h2 class="related-title">Autres produits de <?= htmlspecialchars($product['vendor_name'] ?? 'ce vendeur') ?></h2>
                    <div class="related-grid">
                        <?php foreach ($vendor_products as $vendor_product): ?>
                            <div class="related-product">
                                <h3 class="related-product-title">
                                    <a href="view_product.php?id=<?= $vendor_product['id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($vendor_product['name']) ?>
                                    </a>
                                </h3>
                                <div class="related-product-price"><?= number_format($vendor_product['price'], 2) ?> GHS</div>
                                <div class="related-product-vendor">
                                    <i class="fas fa-tags"></i> <?= htmlspecialchars($vendor_product['category'] ?? 'Général') ?>
                                </div>
                                <a href="view_product.php?id=<?= $vendor_product['id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> Voir détails
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
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
                    showNotification('Produit ajouté au panier !', 'success');
                    updateCartCount(data.cart_count);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erreur lors de l\'ajout au panier', 'error');
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
