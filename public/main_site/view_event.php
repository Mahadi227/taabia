<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get event ID
$event_id = intval($_GET['id'] ?? 0);

if (!$event_id) {
    header('Location: upcoming_events.php');
    exit;
}

// Get event details
try {
    $query = "
        SELECT e.*, e.instructor_name AS organizer_name,
               COUNT(er.id) as registration_count
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id
        WHERE e.id = ? AND e.status = 'upcoming'
        GROUP BY e.id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();

    if (!$event) {
        header('Location: upcoming_events.php');
        exit;
    }

    // Check if user is already registered
    $is_registered = false;
    if (isset($_SESSION['user_id'])) {
        $check_query = "SELECT id FROM event_registrations WHERE event_id = ? AND user_id = ?";
        $check_stmt = $pdo->prepare($check_query);
        $check_stmt->execute([$event_id, $_SESSION['user_id']]);
        $is_registered = $check_stmt->fetch() !== false;
    }

    // Get related events
    $related_query = "
        SELECT e.*, e.instructor_name AS organizer_name
        FROM events e
        WHERE e.status = 'upcoming' 
        AND e.id != ? 
        AND e.event_date >= CURDATE()
        ORDER BY e.event_date ASC
        LIMIT 3
    ";

    $related_stmt = $pdo->prepare($related_query);
    $related_stmt->execute([$event_id]);
    $related_events = $related_stmt->fetchAll();
} catch (PDOException $e) {
    header('Location: upcoming_events.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($event['title']) ?> | TaaBia</title>
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

        .event-hero {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .event-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }

        .event-image-placeholder {
            width: 100%;
            height: 400px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        .event-content {
            padding: var(--spacing-2xl);
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-xl);
            flex-wrap: wrap;
            gap: var(--spacing-lg);
        }

        .event-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .event-meta {
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

        .event-description {
            color: var(--text-primary);
            line-height: 1.8;
            margin-bottom: var(--spacing-xl);
            font-size: 1.1rem;
        }

        .event-actions {
            display: flex;
            gap: var(--spacing-md);
            flex-wrap: wrap;
        }

        .registration-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: var(--spacing-xl);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .registration-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-lg);
        }

        .registration-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .registration-count {
            background: var(--primary-color);
            color: white;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .related-events {
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

        .related-event {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-lg);
            transition: var(--transition);
        }

        .related-event:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow-light);
        }

        .related-event-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .related-event-date {
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }

        .related-event-organizer {
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

            .event-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .event-title {
                font-size: 2rem;
            }

            .event-actions {
                width: 100%;
            }

            .event-actions .btn {
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
                <a href="upcoming_events.php">Événements</a>
                <i class="fas fa-chevron-right"></i>
                <span><?= htmlspecialchars($event['title']) ?></span>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div style="background: #e8f5e8; border: 1px solid #4caf50; color: #2e7d32; padding: var(--spacing-lg); border-radius: var(--border-radius); margin-bottom: var(--spacing-xl);">
                    <h4 style="margin-bottom: var(--spacing-sm); display: flex; align-items: center; gap: var(--spacing-sm);">
                        <i class="fas fa-check-circle"></i> Succès
                    </h4>
                    <p><?= htmlspecialchars($_GET['success']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Event Hero -->
            <div class="event-hero">
                <?php if ($event['image_url']): ?>
                    <img src="../../uploads/<?= htmlspecialchars($event['image_url']) ?>"
                        alt="<?= htmlspecialchars($event['title']) ?>"
                        class="event-image">
                <?php else: ?>
                    <div class="event-image-placeholder">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                <?php endif; ?>

                <div class="event-content">
                    <div class="event-header">
                        <div>
                            <h1 class="event-title"><?= htmlspecialchars($event['title']) ?></h1>
                            <div class="status-badge">Événement à venir</div>
                        </div>

                        <div class="event-actions">
                            <?php if ($is_registered): ?>
                                <span class="btn btn-success" style="cursor: default;">
                                    <i class="fas fa-check"></i> Inscrit
                                </span>
                            <?php else: ?>
                                <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-lg">
                                    <i class="fas fa-ticket-alt"></i> S'inscrire
                                </a>
                            <?php endif; ?>

                            <a href="upcoming_events.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                        </div>
                    </div>

                    <div class="event-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?= date('d/m/Y à H:i', strtotime($event['event_date'])) ?></span>
                        </div>

                        <?php if ($event['location']): ?>
                            <div class="meta-item">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?= htmlspecialchars($event['location']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="meta-item">
                            <i class="fas fa-user"></i>
                            <span>Organisé par <?= htmlspecialchars($event['organizer_name'] ?? 'TaaBia') ?></span>
                        </div>

                        <div class="meta-item">
                            <i class="fas fa-users"></i>
                            <span><?= number_format($event['registration_count']) ?> inscrits</span>
                        </div>
                    </div>

                    <div class="event-description">
                        <?= nl2br(htmlspecialchars($event['description'])) ?>
                    </div>
                </div>
            </div>

            <!-- Registration Card -->
            <div class="registration-card">
                <div class="registration-header">
                    <h2 class="registration-title">Inscriptions</h2>
                    <div class="registration-count">
                        <?= number_format($event['registration_count']) ?> participants
                    </div>
                </div>

                <p style="color: var(--text-secondary); margin-bottom: var(--spacing-lg);">
                    Rejoignez cet événement enrichissant et connectez-vous avec d'autres participants passionnés.
                </p>

                <div class="event-actions">
                    <?php if ($is_registered): ?>
                        <span class="btn btn-success" style="cursor: default;">
                            <i class="fas fa-check"></i> Vous êtes inscrit à cet événement
                        </span>
                    <?php else: ?>
                        <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-primary btn-lg">
                            <i class="fas fa-ticket-alt"></i> S'inscrire maintenant
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Related Events -->
            <?php if (!empty($related_events)): ?>
                <div class="related-events">
                    <h2 class="related-title">Autres événements à venir</h2>
                    <div class="related-grid">
                        <?php foreach ($related_events as $related): ?>
                            <div class="related-event">
                                <h3 class="related-event-title">
                                    <a href="view_event.php?id=<?= $related['id'] ?>" style="color: inherit; text-decoration: none;">
                                        <?= htmlspecialchars($related['title']) ?>
                                    </a>
                                </h3>
                                <div class="related-event-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('d/m/Y à H:i', strtotime($related['event_date'])) ?>
                                </div>
                                <div class="related-event-organizer">
                                    <i class="fas fa-user"></i>
                                    <?= htmlspecialchars($related['organizer_name'] ?? 'TaaBia') ?>
                                </div>
                                <a href="view_event.php?id=<?= $related['id'] ?>" class="btn btn-secondary">
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
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>

</html>