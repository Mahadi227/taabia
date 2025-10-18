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

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $post_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $success_message = __("post_deleted_successfully");
    } catch (PDOException $e) {
        $error_message = __("error_deleting_post");
    }
}

// Get blog posts with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $total_posts = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    $total_pages = ceil($total_posts / $limit);

    // Get posts with author and category info
    $posts_query = "
        SELECT bp.*, bc.name as category_name, u.full_name as author_name
        FROM blog_posts bp 
        LEFT JOIN blog_categories bc ON bp.category_id = bc.id 
        LEFT JOIN users u ON bp.author_id = u.id 
        ORDER BY bp.created_at DESC 
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($posts_query);
    $stmt->execute([$limit, $offset]);
    $blog_posts = $stmt->fetchAll();
} catch (PDOException $e) {
    $blog_posts = [];
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('blog_management') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        .blog-posts-table-container {
            max-width: 100%;
            max-height: 600px;
            overflow-x: auto;
            overflow-y: auto;
        }

        /* Admin Language Switcher Styles */
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
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .admin-language-btn:hover {
            background: var(--gray-100);
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--gray-100);
        }

        .admin-language-item.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .language-flag {
            font-size: 1rem;
        }

        .language-name {
            flex: 1;
        }

        .admin-language-item i {
            color: var(--success-color);
            font-size: 0.75rem;
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
                        <h1><i class="fas fa-newspaper"></i> <?= __('blog_management') ?></h1>
                        <p><?= __('manage_blog_posts') ?></p>
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

                        <a href="add_blog_post.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> <?= __('new_post') ?>
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
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Blog Posts Table -->
            <div class="table-container">
                <div class="blog-posts-table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('title') ?></th>
                                <th><?= __('category') ?></th>
                                <th><?= __('author') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('creation_date') ?></th>
                                <th><?= __('views') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($blog_posts)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">
                                        <i class="fas fa-newspaper"
                                            style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>
                                        <p><?= __('no_posts_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($blog_posts as $post): ?>
                                    <tr>
                                        <td>
                                            <div class="post-title">
                                                <strong><?= htmlspecialchars($post['title']) ?></strong>
                                                <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($post['category_name']): ?>
                                                <span
                                                    class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Aucune catégorie</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($post['author_name']) ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($post['status']) {
                                                case 'published':
                                                    $status_class = 'badge-success';
                                                    $status_text = 'Publié';
                                                    break;
                                                case 'draft':
                                                    $status_class = 'badge-warning';
                                                    $status_text = 'Brouillon';
                                                    break;
                                                case 'archived':
                                                    $status_class = 'badge-danger';
                                                    $status_text = 'Archivé';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>
                                        <td><?= $post['view_count'] ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_blog_post.php?id=<?= $post['id'] ?>"
                                                    class="btn btn-sm btn-outline" title="<?= __('edit') ?>">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>"
                                                    target="_blank" class="btn btn-sm btn-outline" title="<?= __('view') ?>">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger"
                                                    title="<?= __('delete') ?>"
                                                    onclick="return confirm('<?= __('confirm_delete_post') ?>')">
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
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>
<th>Date de création</th>

<th>Vues</th>

<th>Actions</th>

</tr>

</thead>

<tbody>

    <?php if (empty($blog_posts)): ?>

        <tr>

            <td colspan="7" class="text-center">

                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>

                <p>Aucun article trouvé</p>

            </td>

        </tr>

    <?php else: ?>

        <?php foreach ($blog_posts as $post): ?>

            <tr>

                <td>

                    <div class="post-title">

                        <strong><?= htmlspecialchars($post['title']) ?></strong>

                        <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>

                    </div>

                </td>

                <td>

                    <?php if ($post['category_name']): ?>

                        <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>

                    <?php else: ?>

                        <span class="badge badge-secondary">Aucune catégorie</span>

                    <?php endif; ?>

                </td>

                <td><?= htmlspecialchars($post['author_name']) ?></td>

                <td>

                    <?php

                    $status_class = '';

                    $status_text = '';

                    switch ($post['status']) {

                        case 'published':

                            $status_class = 'badge-success';

                            $status_text = 'Publié';

                            break;

                        case 'draft':

                            $status_class = 'badge-warning';

                            $status_text = 'Brouillon';

                            break;

                        case 'archived':

                            $status_class = 'badge-danger';

                            $status_text = 'Archivé';

                            break;
                    }

                    ?>

                    <span class="badge <?= $status_class ?>"><?= $status_text ?></span>

                </td>

                <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>

                <td><?= $post['view_count'] ?></td>

                <td>

                    <div class="action-buttons">

                        <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">

                            <i class="fas fa-edit"></i>

                        </a>

                        <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">

                            <i class="fas fa-eye"></i>

                        </a>

                        <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer"

                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">

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

        <?php if ($page > 1): ?>

            <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">

                <i class="fas fa-chevron-left"></i> Précédent

            </a>

        <?php endif; ?>



        <?php for ($i = 1; $i <= $total_pages; $i++): ?>

            <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">

                <?= $i ?>

            </a>

        <?php endfor; ?>



        <?php if ($page < $total_pages): ?>

            <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">

                Suivant <i class="fas fa-chevron-right"></i>

            </a>

        <?php endif; ?>

    </div>

<?php endif; ?>

</div>

</div>

</body>

</html>