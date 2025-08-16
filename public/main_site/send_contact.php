<?php
// Traitement du formulaire de contact

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sécurité : échapper les entrées
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    // Validation simple
    if (empty($name) || empty($email) || empty($message)) {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        // Envoi par mail (optionnel) – ou enregistrement en base
        $to = 'contact@taabia.com';  // À remplacer par ton adresse e-mail
        $subject = "📩 Nouveau message de $name via le site TaaBia";
        $body = "Nom: $name\nEmail: $email\n\nMessage:\n$message";
        $headers = "From: $email";

        if (mail($to, $subject, $body, $headers)) {
            $success = '✅ Merci ! Votre message a été envoyé avec succès.';
        } else {
            $error = '❌ Une erreur est survenue. Veuillez réessayer.';
        }
    }
} else {
    // Accès direct non autorisé
    header('Location: contact.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .message-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--spacing-xl);
            text-align: center;
        }

        .message-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-3xl);
            margin-bottom: var(--spacing-xl);
        }

        .message-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
        }

        .success-icon {
            color: var(--success-color);
        }

        .error-icon {
            color: var(--danger-color);
        }

        .message-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .message-text {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: var(--spacing-xl);
        }

        .message-actions {
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
            .message-actions {
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
        <div class="message-container">
            <div class="message-card">
                <?php if (isset($success)): ?>
                    <div class="message-icon success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="message-title">Message envoyé !</h1>
                    <p class="message-text"><?= htmlspecialchars($success) ?></p>
                <?php elseif (isset($error)): ?>
                    <div class="message-icon error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h1 class="message-title">Erreur</h1>
                    <p class="message-text"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <div class="message-actions">
                    <a href="contact.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour au contact
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> Accueil
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
