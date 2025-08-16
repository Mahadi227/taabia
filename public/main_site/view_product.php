<?php
require_once '../../includes/db.php';

$product_id = $_GET['id'] ?? null;

if (!$product_id) {
    header('Location: shop.php');
    exit;
}

try {
    // Get product details with vendor information
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.id = ? AND p.status = 'active'
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        header('Location: shop.php');
        exit;
    }

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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }

        .section {
            padding: var(--spacing-2xl) 0;
        }

        .product-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-2xl);
            margin-top: var(--spacing-xl);
        }

        .product-image {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            text-align: center;
        }

        .product-image img {
            max-width: 100%;
            height: auto;
            border-radius: var(--border-radius-sm);
        }

        .product-details {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .product-title {
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .product-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: var(--spacing-lg);
        }

        .product-meta {
            display: flex;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--text-secondary);
        }

        .product-description {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: var(--spacing-xl);
        }

        .product-features {
            margin-bottom: var(--spacing-xl);
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }

        .feature-item i {
            color: var(--success-color);
            margin-right: var(--spacing-sm);
        }

        .quantity-selector {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .quantity-btn {
            width: 40px;
            height: 40px;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .quantity-btn:hover {
            background: var(--bg-secondary);
        }

        .quantity-input {
            width: 60px;
            height: 40px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
        }

        .action-buttons {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }

        .btn-large {
            padding: var(--spacing-md) var(--spacing-xl);
            font-size: 1rem;
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
            
            .product-content {
                grid-template-columns: 1fr;
            }
            
            .product-meta {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
            
            .action-buttons {
                flex-direction: column;
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
            <div class="product-content">
                <div class="product-image">
                    <?php if ($product['image_url']): ?>
                        <img src="../../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <div style="
                            width: 100%; 
                            height: 300px; 
                            background: var(--bg-secondary); 
                            display: flex; 
                            align-items: center; 
                            justify-content: center;
                            border-radius: var(--border-radius-sm);
                        ">
                            <i class="fas fa-image" style="font-size: 3rem; color: var(--text-secondary);"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="product-details">
                    <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <div class="product-price"><?= number_format($product['price'], 2) ?> GHS</div>
                    
                    <div class="product-meta">
                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>Vendeur: <?= htmlspecialchars($product['vendor_name'] ?? 'TaaBia') ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-box"></i>
                            <span>En stock</span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-star"></i>
                            <span>4.5/5 (12 avis)</span>
                        </div>
                    </div>
                    
                    <p class="product-description">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </p>
                    
                    <div class="product-features">
                        <h3 style="margin-bottom: var(--spacing-md); color: var(--text-primary);">
                            <i class="fas fa-check-circle"></i> Caractéristiques
                        </h3>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Qualité garantie</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Livraison rapide</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Support client 24/7</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Retour gratuit sous 30 jours</span>
                        </div>
                    </div>
                    
                    <div class="quantity-selector">
                        <label style="font-weight: 500; color: var(--text-primary);">Quantité:</label>
                        <button class="quantity-btn" onclick="updateQuantity(-1)">-</button>
                        <input type="number" id="quantity" value="1" min="1" class="quantity-input">
                        <button class="quantity-btn" onclick="updateQuantity(1)">+</button>
                    </div>
                    
                    <div class="action-buttons">
                        <a href="basket.php?add=<?= $product['id'] ?>" class="btn btn-primary btn-large">
                            <i class="fas fa-shopping-cart"></i> Ajouter au panier
                        </a>
                        <a href="checkout.php?product=<?= $product['id'] ?>" class="btn btn-secondary btn-large">
                            <i class="fas fa-credit-card"></i> Acheter maintenant
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        function updateQuantity(change) {
            const input = document.getElementById('quantity');
            const newValue = parseInt(input.value) + change;
            if (newValue >= 1) {
                input.value = newValue;
            }
        }
    </script>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                    <p>Votre plateforme d'apprentissage et d'innovation en Afrique</p>
                    <p>Démocratiser l'accès à l'éducation et aux produits innovants</p>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <a href="courses.php">Formations</a>
                    <a href="shop.php">Boutique</a>
                    <a href="upcoming_events.php">Événements</a>
                    <a href="contact.php">Support</a>
                </div>
                
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                    <p><i class="fas fa-phone"></i> +233 XX XXX XXXX</p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
                </div>
                
                <div class="footer-section">
                    <h3>Suivez-nous</h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <style>
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
</body>
</html>
