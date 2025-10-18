<?php
require_once '../../includes/db.php';

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Récupère le client_id correspondant à l'utilisateur
$stmt = $pdo->prepare("SELECT id FROM clients WHERE user_id = ?");
$stmt->execute([$user_id]);
$client = $stmt->fetch();

if (!$client) {
    echo "<p style='color:red; text-align:center;'>⛔ Aucun historique de commande trouvé.</p>";
    exit;
}

$client_id = $client['id'];

// Récupère les commandes de ce client
$stmt = $pdo->prepare("SELECT * FROM orders WHERE client_id = ? ORDER BY ordered_at DESC");
$stmt->execute([$client_id]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des commandes | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .orders-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .orders-header h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .orders-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .order-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-lg);
            overflow: hidden;
        }

        .order-header {
            background: var(--bg-secondary);
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }

        .order-info-item {
            display: flex;
            flex-direction: column;
        }

        .order-info-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
        }

        .order-info-value {
            font-weight: 600;
            color: var(--text-primary);
        }

        .order-status {
            display: inline-block;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: var(--warning-color);
            color: var(--text-white);
        }

        .status-confirmed {
            background: var(--success-color);
            color: var(--text-white);
        }

        .status-cancelled {
            background: var(--danger-color);
            color: var(--text-white);
        }

        .order-items {
            padding: var(--spacing-lg);
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-details {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius-sm);
        }

        .item-info h4 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .item-price {
            color: var(--primary-color);
            font-weight: 600;
        }

        .order-total {
            background: var(--bg-secondary);
            padding: var(--spacing-lg);
            text-align: right;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .empty-orders {
            text-align: center;
            padding: var(--spacing-3xl);
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .empty-orders i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
        }

        .empty-orders h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .empty-orders p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xl);
        }

        @media (max-width: 768px) {
            .order-info {
                grid-template-columns: 1fr;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
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
        <div class="orders-container">
            <div class="orders-header">
                <h1><i class="fas fa-history"></i> Historique des commandes</h1>
                <p>Consultez vos commandes précédentes et leur statut</p>
            </div>

            <?php if (empty($orders)): ?>
                <div class="empty-orders">
                    <i class="fas fa-shopping-bag"></i>
                    <h2>Aucune commande trouvée</h2>
                    <p>Vous n'avez pas encore passé de commande</p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> Découvrir nos produits
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-info">
                                <div class="order-info-item">
                                    <span class="order-info-label">Commande #</span>
                                    <span class="order-info-value"><?= $order['id'] ?></span>
                                </div>
                                <div class="order-info-item">
                                    <span class="order-info-label">Date</span>
                                    <span class="order-info-value"><?= date('d/m/Y H:i', strtotime($order['ordered_at'])) ?></span>
                                </div>
                                <div class="order-info-item">
                                    <span class="order-info-label">Statut</span>
                                    <span class="order-status status-<?= strtolower($order['status']) ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </div>
                                <div class="order-info-item">
                                    <span class="order-info-label">Total</span>
                                    <span class="order-info-value"><?= number_format($order['total_amount'], 0, ',', ' ') ?> GHS</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="order-items">
                            <?php
                            // Récupérer les articles de cette commande
                            $stmt = $pdo->prepare("
                                SELECT oi.*, p.name, p.image_url 
                                FROM order_items oi 
                                JOIN products p ON oi.product_id = p.id 
                                WHERE oi.order_id = ?
                            ");
                            $stmt->execute([$order['id']]);
                            $items = $stmt->fetchAll();
                            ?>
                            
                            <?php foreach ($items as $item): ?>
                                <div class="order-item">
                                    <div class="item-details">
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-image">
                                        <div class="item-info">
                                            <h4><?= htmlspecialchars($item['name']) ?></h4>
                                            <p>Quantité: <?= $item['quantity'] ?></p>
                                        </div>
                                    </div>
                                    <div class="item-price">
                                        <?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> GHS
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-total">
                            Total: <?= number_format($order['total_amount'], 0, ',', ' ') ?> GHS
                        </div>
                    </div>
                <?php endforeach; ?>
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
</body>
</html>