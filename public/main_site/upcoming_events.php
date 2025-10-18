<?php
require_once '../../includes/db.php';
require_once '../../includes/i18n.php';

// Get search and filter parameters
$search = trim($_GET['search'] ?? '');
$organizer = trim($_GET['organizer'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort = $_GET['sort'] ?? 'date_asc';

// Build the query
$where_conditions = ["e.status = 'upcoming'", "e.event_date >= CURDATE()"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($organizer)) {
    $where_conditions[] = "e.instructor_name LIKE ?";
    $params[] = "%$organizer%";
}

if (!empty($date_from)) {
    $where_conditions[] = "e.event_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "e.event_date <= ?";
    $params[] = $date_to;
}

$where_clause = implode(' AND ', $where_conditions);

// Sort options
$order_by = match ($sort) {
    'date_desc' => 'e.event_date DESC',
    'title' => 'e.title ASC',
    'organizer' => 'e.instructor_name ASC',
    default => 'e.event_date ASC'
};

// Get upcoming events
try {
    $query = "
        SELECT e.*, e.instructor_name AS organizer_name 
        FROM events e 
        WHERE $where_clause
        ORDER BY $order_by
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    // Get organizers for filter
    $organizers = $pdo->query("
        SELECT DISTINCT instructor_name 
        FROM events 
        WHERE status = 'upcoming' AND instructor_name IS NOT NULL
        ORDER BY instructor_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Get event statistics
    $event_stats = [
        'total_upcoming' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming' AND event_date >= CURDATE()")->fetchColumn(),
        'this_month' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming' AND MONTH(event_date) = MONTH(CURDATE())")->fetchColumn(),
        'next_month' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'upcoming' AND MONTH(event_date) = MONTH(DATE_ADD(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn(),
        'total_organizers' => $pdo->query("SELECT COUNT(DISTINCT instructor_name) FROM events WHERE status = 'upcoming'")->fetchColumn()
    ];
} catch (PDOException $e) {
    $events = [];
    $organizers = [];
    $event_stats = ['total_upcoming' => 0, 'this_month' => 0, 'next_month' => 0, 'total_organizers' => 0];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('upcoming_events') ?> | TaaBia</title>
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
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: var(--spacing-lg);
        }

        /* Responsive Grid Breakpoints */
        @media (min-width: 1400px) {
            .events {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) and (min-width: 992px) {
            .events {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 991px) and (min-width: 769px) {
            .events {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .events {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
            }
        }

        @media (max-width: 480px) {
            .events {
                gap: var(--spacing-sm);
            }
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

        .w-100 {
            width: 100%;
        }

        .text-center {
            text-align: center;
        }

        .mb-4 {
            margin-bottom: var(--spacing-lg);
        }

        .mt-5 {
            margin-top: var(--spacing-xl);
        }

        .btn-lg {
            padding: var(--spacing-md) var(--spacing-xl);
            font-size: 1rem;
        }

        /* Enhanced event card styles */
        .event {
            position: relative;
            overflow: hidden;
        }

        .event::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .event:hover::before {
            height: 6px;
            transition: var(--transition);
        }

        /* Filter form styles */
        input,
        select {
            transition: var(--transition);
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        /* Statistics animation */
        .stat-number {
            animation: countUp 2s ease-out;
        }

        @keyframes countUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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
                    <a href="../../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('login') ?>
                    </a>
                    <a href="../../auth/register.php" class="btn btn-primary">
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
                <h1><i class="fas fa-calendar-alt"></i> <?= __('events_title') ?></h1>
                <p><?= __('events_description') ?></p>
            </div>

            <!-- Statistics Section -->
            <div
                style="background: var(--bg-primary); border-radius: var(--border-radius); padding: var(--spacing-xl); margin-bottom: var(--spacing-2xl); box-shadow: var(--shadow-light);">
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-xl);">
                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--primary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                            <?= number_format($event_stats['total_upcoming']) ?></h3>
                        <p style="color: var(--text-secondary);"><?= __('total_upcoming_events') ?></p>
                    </div>

                    <div style="text-align: center;">
                        <div
                            style="font-size: 2.5rem; color: var(--secondary-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                            <?= number_format($event_stats['this_month']) ?></h3>
                        <p style="color: var(--text-secondary);">Ce mois-ci</p>
                    </div>

                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--accent-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                            <?= number_format($event_stats['next_month']) ?></h3>
                        <p style="color: var(--text-secondary);">Mois prochain</p>
                    </div>

                    <div style="text-align: center;">
                        <div style="font-size: 2.5rem; color: var(--success-color); margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 style="font-size: 2rem; color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                            <?= number_format($event_stats['total_organizers']) ?></h3>
                        <p style="color: var(--text-secondary);">Organisateurs</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div
                style="background: var(--bg-primary); border-radius: var(--border-radius); padding: var(--spacing-xl); margin-bottom: var(--spacing-2xl); box-shadow: var(--shadow-light);">
                <form method="GET"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg); align-items: end;">
                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('search_events') ?></label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="<?= __('search_events_placeholder') ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);">Organisateur</label>
                        <select name="organizer"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value="">Tous les organisateurs</option>
                            <?php foreach ($organizers as $org): ?>
                                <option value="<?= htmlspecialchars($org) ?>" <?= $organizer === $org ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('start_date') ?></label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('end_date') ?></label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);">Trier
                            par</label>
                        <select name="sort"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>><?= __('date_closest') ?>
                            </option>
                            <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>><?= __('date_farthest') ?></option>
                            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Titre</option>
                            <option value="organizer" <?= $sort === 'organizer' ? 'selected' : '' ?>>Organisateur
                            </option>
                        </select>
                    </div>

                    <div style="display: flex; gap: var(--spacing-sm);">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> <?= __('search_button') ?>
                        </button>
                        <a href="upcoming_events.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Effacer
                        </a>
                    </div>
                </form>
            </div>

            <!-- Search and Filter Section -->
            <div
                style="background: var(--bg-primary); border-radius: var(--border-radius); padding: var(--spacing-xl); margin-bottom: var(--spacing-2xl); box-shadow: var(--shadow-light);">
                <form method="GET"
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg); align-items: end;">
                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('search_events') ?></label>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                            placeholder="<?= __('search_events_placeholder') ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);">Organisateur</label>
                        <select name="organizer"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value="">Tous les organisateurs</option>
                            <?php foreach ($organizers as $org): ?>
                                <option value="<?= htmlspecialchars($org) ?>" <?= $organizer === $org ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($org) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('start_date') ?></label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);"><?= __('end_date') ?></label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                    </div>

                    <div>
                        <label
                            style="display: block; margin-bottom: var(--spacing-sm); font-weight: 500; color: var(--text-primary);">Trier
                            par</label>
                        <select name="sort"
                            style="width: 100%; padding: var(--spacing-md); border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-size: 0.875rem;">
                            <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>><?= __('date_closest') ?>
                            </option>
                            <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>><?= __('date_farthest') ?></option>
                            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Titre</option>
                            <option value="organizer" <?= $sort === 'organizer' ? 'selected' : '' ?>>Organisateur
                            </option>
                        </select>
                    </div>

                    <div style="display: flex; gap: var(--spacing-sm);">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> <?= __('search_button') ?>
                        </button>
                        <a href="upcoming_events.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Effacer
                        </a>
                    </div>
                </form>
            </div>

            <div class="events">
                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event): ?>
                        <div class="event">
                            <?php if ($event['image_url']): ?>
                                <img src="../../uploads/<?= htmlspecialchars($event['image_url']) ?>"
                                    alt="<?= htmlspecialchars($event['title']) ?>" class="event-image">
                            <?php else: ?>
                                <div class="event-image"
                                    style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: white; font-size: 3rem;">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            <?php endif; ?>
                            <div class="event-content">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-sm);">
                                    <span
                                        style="background: var(--primary-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                        Événement
                                    </span>
                                    <span
                                        style="background: var(--success-color); color: white; padding: 0.25rem 0.5rem; border-radius: var(--border-radius-sm); font-size: 0.75rem;">
                                        Inscriptions ouvertes
                                    </span>
                                </div>
                                <h3 class="event-title"><?= htmlspecialchars($event['title']) ?></h3>
                                <div class="event-date">
                                    <i class="fas fa-calendar"></i> <?= date('d/m/Y à H:i', strtotime($event['event_date'])) ?>
                                </div>
                                <?php if ($event['location']): ?>
                                    <div class="event-location">
                                        <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="event-organizer">
                                    <i class="fas fa-user"></i> Organisé par
                                    <?= htmlspecialchars($event['organizer_name'] ?? 'TaaBia') ?>
                                </div>
                                <p class="event-description">
                                    <?= htmlspecialchars(substr($event['description'], 0, 150)) ?>...
                                </p>
                                <div style="display: flex; gap: var(--spacing-sm);">
                                    <a href="register_event.php?id=<?= $event['id'] ?>" class="btn btn-primary"
                                        style="flex: 1;">
                                        <i class="fas fa-ticket-alt"></i> S'inscrire
                                    </a>
                                    <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center" style="grid-column: 1 / -1; padding: var(--spacing-2xl);">
                        <div style="font-size: 4rem; color: var(--text-light); margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3 style="color: var(--text-secondary); margin-bottom: var(--spacing-md);">Aucun événement trouvé
                        </h3>
                        <p style="color: var(--text-light); margin-bottom: var(--spacing-lg);">
                            <?php if (!empty($search) || !empty($organizer) || !empty($date_from) || !empty($date_to)): ?>
                                Aucun événement ne correspond à vos critères de recherche.
                            <?php else: ?>
                                Aucun événement n'est actuellement programmé.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search) || !empty($organizer) || !empty($date_from) || !empty($date_to)): ?>
                            <a href="upcoming_events.php" class="btn btn-primary">
                                <i class="fas fa-times"></i> Effacer les filtres
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Call to Action -->
            <?php if (!empty($events)): ?>
                <div style="text-align: center; margin-top: var(--spacing-2xl);">
                    <h3 style="color: var(--text-primary); margin-bottom: var(--spacing-lg);">Restez informé de nos
                        événements</h3>
                    <p style="color: var(--text-secondary); margin-bottom: var(--spacing-xl);">
                        Inscrivez-vous à notre newsletter pour recevoir les dernières informations sur nos événements
                    </p>
                    <a href="contact.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-envelope"></i> S'abonner à la newsletter
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script>
        // Enhanced interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit form on filter change
            const filterForm = document.querySelector('form');
            const filterInputs = filterForm.querySelectorAll('select, input[type="date"]');

            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });

            // Smooth scroll to top
            const scrollToTop = () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            };

            // Add scroll to top button
            const scrollBtn = document.createElement('button');
            scrollBtn.innerHTML = '<i class="fas fa-arrow-up"></i>';
            scrollBtn.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: var(--primary-color);
                color: white;
                border: none;
                cursor: pointer;
                box-shadow: var(--shadow-medium);
                transition: var(--transition);
                z-index: 1000;
                display: none;
            `;

            scrollBtn.addEventListener('click', scrollToTop);
            document.body.appendChild(scrollBtn);

            // Show/hide scroll button
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    scrollBtn.style.display = 'block';
                } else {
                    scrollBtn.style.display = 'none';
                }
            });

            // Enhanced search with debounce
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;

            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500);
            });

            // Add loading state to form submission
            filterForm.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recherche...';
                submitBtn.disabled = true;

                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 2000);
            });
        });
    </script>
</body>

</html>