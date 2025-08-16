<?php
$order_id = $_GET['order_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande confirmée | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--spacing-xl);
            text-align: center;
        }

        .success-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-3xl);
            margin-bottom: var(--spacing-xl);
        }

        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: var(--spacing-lg);
        }

        .success-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .success-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: var(--spacing-xl);
        }

        .order-details {
            background: var(--bg-secondary);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .success-actions {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .success-actions {
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
    <main class="main">
        <div class="success-container">
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="success-title">🎉 Merci pour votre commande !</h1>
                <p class="success-message">Votre commande a été confirmée avec succès. Nous vous tiendrons informé de son statut.</p>
                
                <div class="order-details">
                    <p><strong>Numéro de commande :</strong></p>
                    <p class="order-number">#<?= htmlspecialchars($order_id) ?></p>
                </div>

                <div class="success-actions">
                    <a href="shop.php" class="btn btn-secondary">
                        <i class="fas fa-shopping-bag"></i> Continuer les achats
                    </a>
                    <a href="order_history.php" class="btn btn-primary">
                        <i class="fas fa-history"></i> Voir mes commandes
                    </a>
                </div>
            </div>
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
