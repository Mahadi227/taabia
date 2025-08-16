<?php
require_once '../../includes/db.php';
session_start();

// On récupère l'ID de l'événement depuis GET ou POST
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($event_id && $name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // Inscription en base
$stmt = $pdo->prepare("INSERT INTO event_registrations (event_id, name, email, registered_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$event_id, $name, $email]);

        // Confirmation à l'utilisateur
        $message = "✅ Inscription confirmée !";
    } else {
        $message = "❌ Tous les champs sont obligatoires et l'email doit être valide.";
    }

    // On redirige vers la page des événements à venir avec un message
    header("Location: upcoming_events.php?msg=" . urlencode($message));
    exit;
}

// Si on arrive ici en GET, on affiche le formulaire

// Récupération de l'événement pour affichage
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    echo "<p style='color:red; text-align:center;'>⛔ Événement introuvable.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription à « <?= htmlspecialchars($event['title']) ?> » | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .registration-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .registration-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .registration-header h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .registration-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .event-details {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .event-details h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
        }

        .event-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .event-info-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .event-info-item i {
            color: var(--primary-color);
            width: 20px;
        }

        .registration-form {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
        }

        .btn-back {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .form-actions {
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
        <div class="registration-container">
            <div class="registration-header">
                <h1><i class="fas fa-calendar-plus"></i> Inscription à l'événement</h1>
                <p>Remplissez le formulaire ci-dessous pour vous inscrire</p>
            </div>

            <div class="event-details">
                <h2><?= htmlspecialchars($event['title']) ?></h2>
                <div class="event-info">
                    <div class="event-info-item">
                        <i class="fas fa-calendar"></i>
                        <span><?= date('d/m/Y', strtotime($event['event_date'])) ?></span>
                    </div>
                    <div class="event-info-item">
                        <i class="fas fa-clock"></i>
                        <span><?= date('H:i', strtotime($event['event_date'])) ?></span>
                    </div>
                    <div class="event-info-item">
                        <i class="fas fa-user"></i>
                        <span><?= htmlspecialchars($event['instructor_name']) ?></span>
                    </div>
                    <div class="event-info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?= htmlspecialchars($event['location']) ?></span>
                    </div>
                </div>
                <p><?= htmlspecialchars($event['description']) ?></p>
            </div>

            <div class="registration-form">
                <h3>Formulaire d'inscription</h3>
                <form method="post" action="">
                    <input type="hidden" name="event_id" value="<?= $event_id ?>">
                    
                    <div class="form-group">
                        <label for="name">Votre nom complet</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Votre adresse email</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-actions">
                        <a href="upcoming_events.php" class="btn btn-back">
                            <i class="fas fa-arrow-left"></i> Retour aux événements
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> S'inscrire
                        </button>
                    </div>
                </form>
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
