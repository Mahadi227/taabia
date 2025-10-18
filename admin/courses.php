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
$courses = [];
$total_courses = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT c.id, c.title, c.description, c.price, c.status, c.created_at, u.full_name AS instructor 
          FROM courses c 
          LEFT JOIN users u ON c.instructor_id = u.id 
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $query .= " AND c.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['instructor'])) {
    $query .= " AND c.instructor_id = ?";
    $params[] = $_GET['instructor'];
}

// Get total count for pagination
$count_query = str_replace("SELECT c.id, c.title, c.description, c.price, c.status, c.created_at, u.full_name AS instructor", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_courses = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/courses.php count: " . $e->getMessage());
}

$total_pages = ceil($total_courses / $limit);

// Get courses with pagination
$query .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $courses = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/courses.php: " . $e->getMessage());
}

// Get instructors for filter
$instructors = [];
try {
    $instructor_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'instructor' ORDER BY full_name");
    if ($instructor_stmt->execute()) {
        $instructors = $instructor_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/courses.php instructors: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('course_management') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
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
                    <h1 class="page-title"><?= __('course_management') ?></h1>

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

                        <a href="add_course.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            <?= __('add_course') ?>
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
            <div style="height: 2px; background: linear-gradient(90deg, rgba(0,150,136,0.2), rgba(0,150,136,0.6), rgba(0,150,136,0.2)); border-radius: 2px; margin: 0.75rem 0 1.25rem 0;"></div>
            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="form-label"><?= __('search') ?></label>
                        <input type="text" name="search" class="form-control"
                            placeholder="<?= __('search_course_placeholder') ?>"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('status') ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="published" <?= ($_GET['status'] ?? '') === 'published' ? 'selected' : '' ?>><?= __('published') ?></option>
                            <option value="draft" <?= ($_GET['status'] ?? '') === 'draft' ? 'selected' : '' ?>><?= __('draft') ?></option>
                            <option value="archived" <?= ($_GET['status'] ?? '') === 'archived' ? 'selected' : '' ?>><?= __('archived') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('instructor') ?></label>
                        <select name="instructor" class="form-control">
                            <option value=""><?= __('all_instructors') ?></option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= $instructor['id'] ?>"
                                    <?= ($_GET['instructor'] ?? '') == $instructor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($instructor['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            <?= __('filter') ?>
                        </button>
                        <a href="courses.php" class="btn btn-secondary">
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
                        <div class="stat-icon courses">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_courses) ?></div>
                            <div class="stat-label"><?= __('total_courses_label') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count($instructors)) ?></div>
                            <div class="stat-label"><?= __('instructors') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($courses, 'status'))['published'] ?? 0) ?></div>
                            <div class="stat-label"><?= __('published_courses_label') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format(array_sum(array_column($courses, 'price')), 2) ?></div>
                            <div class="stat-label"><?= __('total_value') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Courses Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('course_list') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_courses ?> <?= __('courses_count') ?></span>
                    </div>
                </div>

                <div class="table-container" style="max-height: 500px; overflow: auto; border: 1px solid var(--border-color); border-radius: 8px;">
                    <table class="table" style="min-width: 1000px;">
                        <thead style="position: sticky; top: 0; background: var(--bg-primary); z-index: 1;">
                            <tr>
                                <th><?= __('course') ?></th>
                                <th><?= __('instructor') ?></th>
                                <th><?= __('price') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('creation_date') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-book" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p><?= __('no_courses_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($course['title']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>...
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($course['instructor'] ?? __('not_assigned')) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                <?php if ((float)($course['price'] ?? 0) <= 0): ?>
                                                    <?= __('free') ?>
                                                <?php else: ?>
                                                    GHS<?= number_format($course['price'], 2) ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($course['status'] === 'published'): ?>
                                                <span class="badge badge-success"><?= __('published') ?></span>
                                            <?php elseif ($course['status'] === 'draft'): ?>
                                                <span class="badge badge-warning"><?= __('draft') ?></span>
                                            <?php elseif ($course['status'] === 'archived'): ?>
                                                <span class="badge badge-danger"><?= __('archived') ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><?= htmlspecialchars($course['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($course['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($course['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="view_course.php?id=<?= $course['id'] ?>"
                                                    class="btn btn-sm btn-secondary"
                                                    title="<?= __('view') ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a href="course_edit.php?id=<?= $course['id'] ?>"
                                                    class="btn btn-sm btn-primary"
                                                    title="<?= __('edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <a href="course_toggle.php?id=<?= $course['id'] ?>&action=<?= $course['status'] === 'published' ? 'archive' : 'publish' ?>"
                                                    class="btn btn-sm <?= $course['status'] === 'published' ? 'btn-warning' : 'btn-success' ?>"
                                                    title="<?= $course['status'] === 'published' ? __('archive') : __('publish') ?>"
                                                    onclick="return confirm('<?= $course['status'] === 'published' ? __('confirm_archive') : __('confirm_publish') ?>')">
                                                    <i class="fas <?= $course['status'] === 'published' ? 'fa-archive' : 'fa-check' ?>"></i>
                                                </a>

                                                <a href="course_delete.php?id=<?= $course['id'] ?>"
                                                    class="btn btn-sm btn-danger"
                                                    title="<?= __('delete') ?>"
                                                    onclick="return confirm('<?= __('confirm_delete_course') ?>')">
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>"
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

        // Add smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = 'var(--shadow-light)';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
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
        });

        // Hamburger menu functionality
        document.addEventListener('DOMContentLoaded', function() {
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