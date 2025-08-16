<?php
require_once '../../includes/db.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions with improved logic
if (isset($_GET['add'])) {
    $product_id = (int)$_GET['add'];
    $quantity = (int)($_GET['quantity'] ?? 1);
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            if (isset($_SESSION['cart'][$product_id])) {
                $_SESSION['cart'][$product_id]['quantity'] += $quantity;
                $message = "Produit ajouté au panier avec succès !";
            } else {
                $_SESSION['cart'][$product_id] = [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'image_url' => $product['image_url'],
                    'quantity' => $quantity
                ];
                $message = "Nouveau produit ajouté au panier !";
            }
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de l'ajout du produit";
    }
}

if (isset($_GET['remove'])) {
    $product_id = (int)$_GET['remove'];
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        $message = "Produit retiré du panier";
    }
}

if (isset($_GET['update'])) {
    $product_id = (int)$_GET['update'];
    $quantity = (int)$_GET['quantity'];
    
    if ($quantity > 0) {
        $_SESSION['cart'][$product_id]['quantity'] = $quantity;
        $message = "Quantité mise à jour";
    } else {
        unset($_SESSION['cart'][$product_id]);
        $message = "Produit retiré du panier";
    }
}

if (isset($_GET['clear'])) {
    $_SESSION['cart'] = [];
    $message = "Panier vidé";
}

// Calculate totals
$total_items = 0;
$total_price = 0;

foreach ($_SESSION['cart'] as $item) {
    $total_items += $item['quantity'];
    $total_price += $item['price'] * $item['quantity'];
}

$tax = $total_price * 0.15;
$shipping = 0; // Free shipping
$total_with_tax = $total_price + $tax + $shipping;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panier | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .cart-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .cart-header h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .cart-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .cart-stats {
            display: flex;
            justify-content: center;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .stat-item {
            text-align: center;
            padding: var(--spacing-md);
            background: var(--bg-primary);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-light);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .message {
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message.error {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .cart-stats {
            display: flex;
            justify-content: center;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .stat-item {
            text-align: center;
            padding: var(--spacing-md);
            background: var(--bg-primary);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-light);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .message {
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message.error {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .cart-empty {
            text-align: center;
            padding: var(--spacing-3xl);
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .cart-empty i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
        }

        .cart-empty h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .cart-empty p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xl);
        }

        .cart-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }

        .cart-items {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            overflow: hidden;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto auto;
            gap: var(--spacing-lg);
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: var(--transition);
        }

        .cart-item:hover {
            background: var(--bg-secondary);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-light);
        }

        .cart-item-details h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            font-size: 1.1rem;
        }

        .cart-item-price {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .quantity-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .quantity-input {
            width: 70px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-sm);
            font-weight: 600;
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .remove-btn {
            background: var(--danger-color);
            color: var(--text-white);
            border: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .remove-btn:hover {
            background: #d32f2f;
        }

        .cart-summary {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-title {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: 1.3rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-sm) 0;
        }

        .summary-row.total {
            border-top: 2px solid var(--border-color);
            padding-top: var(--spacing-md);
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .cart-actions {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
        }

        .btn-continue {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-checkout {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-clear {
            background: var(--danger-color);
            color: var(--text-white);
        }

        .cart-features {
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .feature-item i {
            color: var(--success-color);
        }

        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
            }

            .cart-item {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
                text-align: center;
            }

            .cart-item-image {
                width: 100px;
                height: 100px;
                margin: 0 auto;
            }

            .cart-stats {
                flex-direction: column;
                gap: var(--spacing-md);
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
    <main class="main">
        <div class="cart-container">
            <div class="cart-header">
                <h1><i class="fas fa-shopping-cart"></i> Mon Panier</h1>
                <p>Gérez vos articles et finalisez votre commande</p>
            </div>

            <?php if (isset($message)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($message)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart'])): ?>
                <div class="cart-empty">
                    <i class="fas fa-shopping-cart"></i>
                    <h2>Votre panier est vide</h2>
                    <p>Découvrez nos produits et ajoutez-les à votre panier</p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Continuer les achats
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_items ?></div>
                        <div class="stat-label">Articles</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= count($_SESSION['cart']) ?></div>
                        <div class="stat-label">Produits</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_price, 0, ',', ' ') ?></div>
                        <div class="stat-label">FCFA</div>
                    </div>
                </div>

                <div class="cart-content">
                    <div class="cart-items">
                        <?php foreach ($_SESSION['cart'] as $product_id => $item): ?>
                            <div class="cart-item">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image">
                                <div class="cart-item-details">
                                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                                    <p class="cart-item-price"><?= number_format($item['price'], 0, ',', ' ') ?> FCFA</p>
                                </div>
                                <div class="cart-item-quantity">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $product_id ?>, -1)" title="Diminuer">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" value="<?= $item['quantity'] ?>" min="1" class="quantity-input" onchange="updateQuantity(<?= $product_id ?>, this.value, true)" title="Quantité">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $product_id ?>, 1)" title="Augmenter">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="cart-item-actions">
                                    <button class="remove-btn" onclick="removeItem(<?= $product_id ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i> Supprimer
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-summary">
                        <h3 class="summary-title">Résumé de la commande</h3>
                        <div class="summary-row">
                            <span>Sous-total (<?= $total_items ?> articles)</span>
                            <span><?= number_format($total_price, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="summary-row">
                            <span>Livraison</span>
                            <span>Gratuit</span>
                        </div>
                        <div class="summary-row">
                            <span>Taxes (15%)</span>
                            <span><?= number_format($tax, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total</span>
                            <span><?= number_format($total_with_tax, 0, ',', ' ') ?> FCFA</span>
                        </div>
                        
                        <div class="cart-actions">
                            <a href="shop.php" class="btn btn-continue">
                                <i class="fas fa-arrow-left"></i> Continuer les achats
                            </a>
                            <a href="checkout.php" class="btn btn-checkout">
                                <i class="fas fa-credit-card"></i> Finaliser la commande
                            </a>
                            <button onclick="clearCart()" class="btn btn-clear">
                                <i class="fas fa-trash"></i> Vider le panier
                            </button>
                        </div>

                        <div class="cart-features">
                            <div class="feature-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Paiement sécurisé</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-truck"></i>
                                <span>Livraison gratuite</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-undo"></i>
                                <span>Retours acceptés</span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-headset"></i>
                                <span>Support 24/7</span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

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

    <script>
        function updateQuantity(productId, change, isDirect = false) {
            let quantity;
            if (isDirect) {
                quantity = parseInt(change);
            } else {
                const currentQuantity = parseInt(document.querySelector(`input[onchange*="${productId}"]`).value);
                quantity = currentQuantity + parseInt(change);
            }
            
            if (quantity > 0) {
                window.location.href = `basket.php?update=${productId}&quantity=${quantity}`;
            }
        }

        function removeItem(productId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) {
                window.location.href = `basket.php?remove=${productId}`;
            }
        }

        function clearCart() {
            if (confirm('Êtes-vous sûr de vouloir vider complètement votre panier ?')) {
                window.location.href = `basket.php?clear=1`;
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>
