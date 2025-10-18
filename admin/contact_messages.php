<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

// Initialize variables
$messages = [];
$total_messages = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT * FROM contact_messages WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_messages = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/contact_messages.php count: " . $e->getMessage());
}

$total_pages = ceil($total_messages / $limit);

// Get messages with pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database error in admin/contact_messages.php: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des messages.";
}

// Get statistics
$stats = [
    'total' => 0,
    'today' => 0,
    'this_week' => 0,
    'this_month' => 0
];

try {
    // Total messages
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages");
    $stats['total'] = $stmt->fetchColumn();

    // Today's messages
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE DATE(created_at) = CURDATE()");
    $stats['today'] = $stmt->fetchColumn();

    // This week's messages
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['this_week'] = $stmt->fetchColumn();

    // This month's messages
    $stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stats['this_month'] = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error in admin/contact_messages.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('contact_messages') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
        }

        .stat-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.total {
            background: linear-gradient(45deg, #667eea, #764ba2);
        }

        .stat-icon.today {
            background: linear-gradient(45deg, #f093fb, #f5576c);
        }

        .stat-icon.week {
            background: linear-gradient(45deg, #4facfe, #00f2fe);
        }

        .stat-icon.month {
            background: linear-gradient(45deg, #43e97b, #38f9d7);
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
        }

        .message-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .message-card:hover {
            box-shadow: var(--shadow-medium);
        }

        .message-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .message-info {
            flex: 1;
        }

        .message-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }

        .message-email {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .message-email:hover {
            text-decoration: underline;
        }

        .message-date {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .message-content {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .message-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: var(--light-color);
            color: var(--dark-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--gray-100);
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: var(--danger-dark);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            color: var(--dark-color);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--light-color);
        }

        .pagination a.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .message-header {
                flex-direction: column;
                gap: 1rem;
            }

            .message-actions {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-envelope"></i> <?= __('contact_messages') ?></h1>
                        <p><?= __('manage_contact_messages') ?></p>
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
                                    placeholder="<?= __('search_messages_placeholder') ?>"
                                    value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="date_from" class="form-label"><?= __('date_from') ?></label>
                                <input type="date" id="date_from" name="date_from" class="form-control"
                                    value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="date_to" class="form-label"><?= __('date_to') ?></label>
                                <input type="date" id="date_to" name="date_to" class="form-control"
                                    value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> <?= __('filter') ?>
                                    </button>
                                    <a href="contact_messages.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> <?= __('reset') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon total">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($stats['total']) ?></div>
                            <div class="stat-label"><?= __('total_messages') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon today">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($stats['today']) ?></div>
                            <div class="stat-label"><?= __('today') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon week">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($stats['this_week']) ?></div>
                            <div class="stat-label"><?= __('this_week') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon month">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($stats['this_month']) ?></div>
                            <div class="stat-label"><?= __('this_month') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('messages_list') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_messages ?> <?= __('messages_count') ?></span>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (empty($messages)): ?>
                        <div class="text-center" style="padding: 3rem;">
                            <i class="fas fa-envelope" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                            <p><?= __('no_messages_found') ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $message): ?>
                            <div class="message-card">
                                <div class="message-header">
                                    <div class="message-info">
                                        <div class="message-name"><?= htmlspecialchars($message['name']) ?></div>
                                        <a href="mailto:<?= htmlspecialchars($message['email']) ?>" class="message-email">
                                            <i class="fas fa-envelope"></i> <?= htmlspecialchars($message['email']) ?>
                                        </a>
                                        <div class="message-date">
                                            <i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($message['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="message-content">
                                    <?= nl2br(htmlspecialchars($message['message'])) ?>
                                </div>

                                <div class="message-actions">
                                    <a href="mailto:<?= htmlspecialchars($message['email']) ?>" class="btn btn-primary">
                                        <i class="fas fa-reply"></i> <?= __('reply') ?>
                                    </a>
                                    <button class="btn btn-secondary" onclick="markAsRead(<?= $message['id'] ?>)">
                                        <i class="fas fa-check"></i> <?= __('mark_as_read') ?>
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteMessage(<?= $message['id'] ?>)">
                                        <i class="fas fa-trash"></i> <?= __('delete') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                            <i class="fas fa-chevron-left"></i> <?= __('previous') ?>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                            class="<?= $i === $current_page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                            <?= __('next') ?> <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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

        // Message actions
        function markAsRead(messageId) {
            if (confirm('<?= __('confirm_mark_as_read') ?>')) {
                // AJAX call to mark message as read
                fetch('mark_message_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: messageId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('<?= __('error_processing_request') ?>');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('<?= __('error_processing_request') ?>');
                    });
            }
        }

        function deleteMessage(messageId) {
            if (confirm('<?= __('confirm_delete_message') ?>')) {
                // AJAX call to delete message
                fetch('delete_message.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            id: messageId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('<?= __('error_processing_request') ?>');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('<?= __('error_processing_request') ?>');
                    });
            }
        }
    </script>
</body>

</html>