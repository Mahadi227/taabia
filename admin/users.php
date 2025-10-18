<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$users = [];
$total_users = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT id, full_name, email, role, is_active, created_at FROM users WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['role'])) {
    $query .= " AND role = ?";
    $params[] = $_GET['role'];
}

if (!empty($_GET['status'])) {
    $query .= " AND is_active = ?";
    $params[] = ($_GET['status'] === 'active') ? 1 : 0;
}

// Get total count for pagination
$count_query = str_replace("SELECT id, full_name, email, role, is_active, created_at", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_users = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/users.php count: " . $e->getMessage());
}

$total_pages = ceil($total_users / $limit);

// Get users with pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $users = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/users.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('user_management') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
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

        /* User */
        .table th:nth-child(2),
        .table td:nth-child(2) {
            min-width: 180px;
        }

        /* Email */
        .table th:nth-child(3),
        .table td:nth-child(3) {
            min-width: 120px;
        }

        /* Role */
        .table th:nth-child(4),
        .table td:nth-child(4) {
            min-width: 100px;
        }

        /* Status */
        .table th:nth-child(5),
        .table td:nth-child(5) {
            min-width: 140px;
        }

        /* Registration Date */
        .table th:nth-child(6),
        .table td:nth-child(6) {
            min-width: 150px;
        }

        /* Actions */

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
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: var(--bg-secondary);
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-medium);
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
            color: var(--text-primary);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--bg-secondary);
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

        /* Enhanced user avatar styling */
        .user-avatar {
            background: linear-gradient(45deg, #9c27b0, #ba68c8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
        }

        /* Enhanced table row hover effects */
        .table tbody tr {
            transition: all 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: rgba(0, 150, 136, 0.05);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        /* Enhanced button styling */
        .btn {
            transition: all 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

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

        /* Mobile sidebar styles */
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

            .header-content {
                padding-left: 20px;
            }

            .table-container {
                font-size: 0.875rem;
            }

            .table td,
            .table th {
                min-width: 100px;
                padding: 8px 12px;
            }

            .user-avatar {
                width: 32px !important;
                height: 32px !important;
                font-size: 0.875rem;
            }

            /* Hide header actions on very small screens */
            @media (max-width: 480px) {
                .header-actions {
                    display: none;
                }

                .page-title {
                    font-size: 1.2rem;
                }
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <!-- Hamburger Menu Button -->
                <button class="hamburger-menu" id="hamburgerMenu" aria-label="Toggle navigation menu">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>

                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <h1 class="page-title"><?= __('user_management') ?></h1>

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

                        <a href="add_user.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            <?= __('add_user') ?>
                        </a>

                        <div class="user-menu">
                            <div class="user-avatar">
                                <?php
                                $current_user = null;
                                try {
                                    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
                                    $stmt->execute([current_user_id()]);
                                    $current_user = $stmt->fetch();
                                } catch (PDOException $e) {
                                    error_log("Error fetching current user: " . $e->getMessage());
                                }
                                ?>
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;">
                                    <?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?>
                                </div>
                                <div style="font-size: 0.75rem; opacity: 0.7;">
                                    <?= htmlspecialchars($current_user['email'] ?? 'admin@taabia.com') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="form-label"><?= __('search') ?></label>
                        <input type="text" name="search" class="form-control"
                            placeholder="<?= __('search_placeholder') ?>"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('role') ?></label>
                        <select name="role" class="form-control">
                            <option value=""><?= __('all_roles') ?></option>
                            <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>><?= __('admin') ?></option>
                            <option value="instructor" <?= ($_GET['role'] ?? '') === 'instructor' ? 'selected' : '' ?>><?= __('instructor') ?></option>
                            <option value="student" <?= ($_GET['role'] ?? '') === 'student' ? 'selected' : '' ?>><?= __('student') ?></option>
                            <option value="vendor" <?= ($_GET['role'] ?? '') === 'vendor' ? 'selected' : '' ?>><?= __('vendor') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('status') ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>><?= __('active') ?></option>
                            <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            <?= __('filter') ?>
                        </button>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            <?= __('reset') ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_users) ?></div>
                            <div class="stat-label"><?= __('total_users') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($users, 'role'))['student'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('students') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($users, 'role'))['instructor'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('instructors') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon vendors">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($users, 'role'))['vendor'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('vendors') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('user_list') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_users ?> <?= __('users_count') ?></span>
                    </div>
                </div>

                <div class="table-container" style="max-height: 600px; overflow: auto; border: 1px solid var(--border-color); border-radius: var(--border-radius);">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('user') ?></th>
                                <th><?= __('email') ?></th>
                                <th><?= __('role') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('registration_date') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-users" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p><?= __('no_users_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($user['full_name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        ID: <?= $user['id'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($user['email']) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $role_labels = [
                                                'admin' => [__('admin'), 'badge-danger'],
                                                'instructor' => [__('instructor'), 'badge-warning'],
                                                'student' => [__('student'), 'badge-success'],
                                                'vendor' => [__('vendor'), 'badge-info']
                                            ];
                                            $role_info = $role_labels[$user['role']] ?? [__('unknown'), 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $role_info[1] ?>"><?= $role_info[0] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active'] == 1): ?>
                                                <span class="badge badge-success"><?= __('active') ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-danger"><?= __('inactive') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($user['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="user_profile.php?id=<?= $user['id'] ?>"
                                                    class="btn btn-sm btn-primary"
                                                    title="<?= __('view_profile') ?>">
                                                    <i class="fas fa-user"></i>
                                                </a>

                                                <a href="user_edit.php?id=<?= $user['id'] ?>"
                                                    class="btn btn-sm btn-secondary"
                                                    title="<?= __('edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <a href="user_toggle.php?id=<?= $user['id'] ?>&action=<?= $user['is_active'] == 1 ? 'deactivate' : 'activate' ?>"
                                                    class="btn btn-sm <?= $user['is_active'] == 1 ? 'btn-warning' : 'btn-success' ?>"
                                                    title="<?= $user['is_active'] == 1 ? __('deactivate') : __('activate') ?>"
                                                    onclick="return confirm('<?= $user['is_active'] == 1 ? __('confirm_deactivate') : __('confirm_activate') ?>')">
                                                    <i class="fas <?= $user['is_active'] == 1 ? 'fa-ban' : 'fa-check' ?>"></i>
                                                </a>

                                                <a href="user_delete.php?id=<?= $user['id'] ?>"
                                                    class="btn btn-sm btn-danger"
                                                    title="<?= __('delete') ?>"
                                                    onclick="return confirm('<?= __('confirm_delete') ?>')">
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
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

        // Enhanced interactions and accessibility
        document.addEventListener('DOMContentLoaded', function() {
            // Hamburger menu functionality
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
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

            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });

            // Add keyboard navigation for table
            const table = document.querySelector('.table');
            if (table) {
                table.setAttribute('role', 'table');
                table.setAttribute('aria-label', 'Liste des utilisateurs');
            }

            // Add focus management for better accessibility
            const focusableElements = document.querySelectorAll('a, button, input, select');
            focusableElements.forEach(element => {
                element.addEventListener('focus', function() {
                    this.style.outline = '2px solid #009688';
                    this.style.outlineOffset = '2px';
                });

                element.addEventListener('blur', function() {
                    this.style.outline = 'none';
                });
            });

            // Add smooth scrolling to pagination
            const paginationLinks = document.querySelectorAll('.pagination a');
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Smooth scroll to top of table
                    const tableContainer = document.querySelector('.table-container');
                    if (tableContainer) {
                        tableContainer.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
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
    </script>
</body>

</html>