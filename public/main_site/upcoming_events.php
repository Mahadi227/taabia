<?php
require_once '../../includes/db.php';

// Get upcoming events
try {
    $events = $pdo->query("
        SELECT e.*, e.instructor_name AS organizer_name 
        FROM events e 
        WHERE e.status = 'upcoming' AND e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $events = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Événements à venir | TaaBia</title>
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

        .events {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
        }

        .event {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            transition: var(--transition);
        }

        .event:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-4px);
        }

        .event-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .event-content {
            padding: var(--spacing-lg);
        }

        .event-title {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .event-date {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: var(--spacing-md);
        }

        .event-description {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
            line-height: 1.5;
        }

        .event-organizer {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-md);
        }

        .event-location {
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
            
            .events {
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
            <div class="section-title">
                <h1><i class="fas fa-calendar-alt"></i> Événements à venir</h1>
                <p>Découvrez nos prochains événements enrichissants</p>
            </div>
            
            <div class="events">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event">
                            <div class="event-content">
                                <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i> <?= date('d/m/Y à H:i', strtotime($event['event_date'])) ?>
                                </div>
                                <div class="event-location">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location'] ?? 'Lieu à préciser') ?>
                                </div>
                                <div class="event-organizer">
                                    <i class="fas fa-user"></i> Organisé par <?= htmlspecialchars($event['organizer_name'] ?? 'TaaBia') ?>
                                </div>
                                <p class="event-description">
                                    <?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...
                                </p>
                                <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-primary w-100">
                                    <i class="fas fa-ticket-alt"></i> S'inscrire
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                        <i class="fas fa-calendar-alt" style="font-size: 4rem; color: var(--text-secondary); margin-bottom: var(--spacing-lg);"></i>
                        <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">Aucun événement à venir</h3>
                        <p style="color: var(--text-secondary);">Aucun événement n'est actuellement programmé.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</body>
</html>
