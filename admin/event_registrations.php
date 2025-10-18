<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

// Initialize variables
$registrations = [];
$total_registrations = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

try {
    // Build query with search and filters
    $query = "
        SELECT er.id, er.name, er.email, er.phone, er.registered_at,
               e.title AS event_title, e.event_date, e.status as event_status
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        WHERE 1
    ";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " AND (er.name LIKE ? OR er.email LIKE ? OR er.phone LIKE ? OR e.title LIKE ?)";
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['event_id'])) {
        $query .= " AND er.event_id = ?";
        $params[] = $_GET['event_id'];
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM event_registrations er JOIN events e ON er.event_id = e.id WHERE 1";
    $count_params = [];

    if (!empty($_GET['search'])) {
        $count_query .= " AND (er.name LIKE ? OR er.email LIKE ? OR er.phone LIKE ? OR e.title LIKE ?)";
        $count_params[] = '%' . $_GET['search'] . '%';
        $count_params[] = '%' . $_GET['search'] . '%';
        $count_params[] = '%' . $_GET['search'] . '%';
        $count_params[] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['event_id'])) {
        $count_query .= " AND er.event_id = ?";
        $count_params[] = $_GET['event_id'];
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_registrations = $count_stmt->fetchColumn();

    $total_pages = ceil($total_registrations / $limit);

    // Get registrations with pagination
    $query .= " ORDER BY er.registered_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll();

    // Get events for filter
    $events = [];
    $event_stmt = $pdo->query("SELECT id, title FROM events ORDER BY title");
    $events = $event_stmt->fetchAll();

    // Get current user info for dynamic header
    $current_user = null;
    try {
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Database error in admin/event_registrations.php: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des inscriptions.";
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('event_registrations') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">

    <style>
        /* Custom scrollbar styling for both horizontal and vertical */
        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Corner where both scrollbars meet */
        .table-container::-webkit-scrollbar-corner {
            background: #f1f1f1;
        }

        /* Firefox scrollbar styling */
        .table-container {
            scrollbar-width: thin;
            scrollbar-color: #c1c1c1 #f1f1f1;
        }

        /* Sticky header for better UX */
        .table thead th {
            position: sticky;
            top: 0;
            background: var(--bg-primary);
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Smooth scrolling */
        .table-container {
            scroll-behavior: smooth;
        }

        /* Ensure table doesn't break layout */
        .table {
            min-width: 100%;
            white-space: nowrap;
        }

        /* Responsive table cells */
        .table td,
        .table th {
            min-width: 120px;
            padding: 12px 16px;
        }

        /* Specific column widths for better layout */
        .table th:nth-child(1),
        .table td:nth-child(1) {
            min-width: 200px;
        }

        /* Participant */
        .table th:nth-child(2),
        .table td:nth-child(2) {
            min-width: 150px;
        }

        /* Téléphone */
        .table th:nth-child(3),
        .table td:nth-child(3) {
            min-width: 180px;
        }

        /* Événement */
        .table th:nth-child(4),
        .table td:nth-child(4) {
            min-width: 140px;
        }

        /* Date événement */
        .table th:nth-child(5),
        .table td:nth-child(5) {
            min-width: 120px;
        }

        /* Statut */
        .table th:nth-child(6),
        .table td:nth-child(6) {
            min-width: 140px;
        }

        /* Date inscription */
        .table th:nth-child(7),
        .table td:nth-child(7) {
            min-width: 100px;
        }

        /* Actions */
        /* Hamburger Menu Styles */
        .hamburger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 1001;
            transition: all 0.3s ease;
        }

        .hamburger-line {
            width: 100%;
            height: 3px;
            background-color: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        /* Hamburger menu animation */
        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }

        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .hamburger-menu {
                display: flex;
            }

            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .sidebar-overlay {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .content-header {
                padding-left: 20px;
            }
        }

        /* Admin Language Switcher */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: white;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--light-color);
        }

        .admin-language-item.active {
            background: var(--primary-color);
            color: white;
        }

        .language-flag {
            font-size: 16px;
        }

        .language-name {
            flex: 1;
            font-size: 14px;
        }

        .admin-language-item i {
            font-size: 12px;
            margin-left: auto;
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-user-check"></i> <?= __('event_registrations') ?></h1>
                        <p><?= __('manage_event_registrations') ?></p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 20px;">
                        <!-- Language Switcher -->
                        <div class="admin-language-switcher">
                            <div class="admin-language-dropdown">
                                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                                    <i class="fas fa-globe"></i>
                                    <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="admin-language-menu" id="adminLanguageDropdown">
                                    <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                        <span class="language-flag">🇫🇷</span>
                                        <span class="language-name">Français</span>
                                        <?php if (getCurrentLanguage() == 'fr'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                        <span class="language-flag">🇬🇧</span>
                                        <span class="language-name">English</span>
                                        <?php if (getCurrentLanguage() == 'en'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <a href="events.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <?= __('back_to_events') ?>
                        </a>

                        <!-- User Menu -->
                        <div class="user-menu">
                            <div class="user-avatar"><i class="fas fa-user"></i></div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('filters_and_search') ?></h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="search" class="form-label"><?= __('search') ?></label>
                                <input type="text" id="search" name="search" class="form-control"
                                    placeholder="<?= __('search_registration_placeholder') ?>"
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="event_id" class="form-label"><?= __('event') ?></label>
                                <select id="event_id" name="event_id" class="form-control">
                                    <option value=""><?= __('all_events') ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>" <?= ($_GET['event_id'] ?? '') == $event['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($event['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> <?= __('filter') ?>
                                    </button>
                                    <a href="event_registrations.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> <?= __('reset') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon registrations">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_registrations) ?></div>
                            <div class="stat-label"><?= __('total_registrations') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon events">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count(array_unique(array_column($registrations, 'event_id')))) ?></div>
                            <div class="stat-label"><?= __('events_with_registrations') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon participants">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count(array_unique(array_column($registrations, 'email')))) ?></div>
                            <div class="stat-label"><?= __('unique_participants') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon recent">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count(array_filter($registrations, function ($r) {
                                                        return strtotime($r['registered_at']) > strtotime('-7 days');
                                                    }))) ?></div>
                            <div class="stat-label"><?= __('recent_registrations_7d') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registrations Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('registrations_list') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_registrations ?> <?= __('registrations_count') ?></span>
                    </div>
                </div>

                <div class="table-container" style="max-height: 600px; overflow: auto; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('participant') ?></th>
                                <th><?= __('phone') ?></th>
                                <th><?= __('event') ?></th>
                                <th><?= __('event_date') ?></th>
                                <th><?= __('event_status') ?></th>
                                <th><?= __('registration_date') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registrations)): ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-user-check" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p><?= __('no_registrations_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($registrations as $registration): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #9c27b0, #ba68c8); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($registration['name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?= htmlspecialchars($registration['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($registration['phone'])): ?>
                                                <div style="font-weight: 500; color: var(--text-primary);">
                                                    <i class="fas fa-phone" style="margin-right: 0.5rem; color: var(--primary-color);"></i>
                                                    <?= htmlspecialchars($registration['phone']) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-light); font-style: italic;">Non renseigné</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($registration['event_title']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= date('d/m/Y', strtotime($registration['event_date'])) ?></div>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('H:i', strtotime($registration['event_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $event_status_labels = [
                                                'upcoming' => [__('upcoming'), 'badge-primary'],
                                                'ongoing' => [__('ongoing'), 'badge-success'],
                                                'completed' => [__('completed'), 'badge-secondary'],
                                                'cancelled' => [__('cancelled'), 'badge-danger']
                                            ];
                                            $event_status_info = $event_status_labels[$registration['event_status']] ?? [__('unknown'), 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $event_status_info[1] ?>"><?= $event_status_info[0] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($registration['registered_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($registration['registered_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_registration.php?id=<?= $registration['id'] ?>"
                                                    class="btn btn-sm btn-outline"
                                                    title="<?= __('edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <a href="delete_registration.php?id=<?= $registration['id'] ?>"
                                                    class="btn btn-sm btn-danger"
                                                    title="<?= __('delete') ?>"
                                                    onclick="return confirm('<?= __('confirm_delete_registration') ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&event_id=<?= htmlspecialchars($_GET['event_id'] ?? '') ?>"
                                class="btn btn-outline">
                                <i class="fas fa-chevron-left"></i> <?= __('previous') ?>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&event_id=<?= htmlspecialchars($_GET['event_id'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary' : 'btn-outline' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&event_id=<?= htmlspecialchars($_GET['event_id'] ?? '') ?>"
                                class="btn btn-outline">
                                <?= __('next') ?> <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Hamburger menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                hamburgerMenu.classList.toggle('active');
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');

                // Prevent body scroll when sidebar is open
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }

            function closeSidebar() {
                hamburgerMenu.classList.remove('active');
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }

            // Event listeners for hamburger menu
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', toggleSidebar);
            }

            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            // Close sidebar when clicking on nav links (mobile)
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });

            // Close sidebar on window resize if screen becomes larger
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });

            // Keyboard navigation for hamburger menu
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSidebar();
                    }
                });
            }
        });

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close admin language dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const switcher = document.querySelector('.admin-language-switcher');

            if (switcher && !switcher.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>

</html>