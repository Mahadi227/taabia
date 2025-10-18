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
$transactions = [];
$total_transactions = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT t.*, 
          u.full_name AS student_name,
          CASE 
              WHEN t.type = 'course' THEN (SELECT title FROM courses WHERE courses.id = t.order_id)
              WHEN t.type = 'product' THEN (SELECT name FROM products WHERE products.id = t.order_id)
              ELSE '—'
          END AS item_title
          FROM transactions t
          LEFT JOIN users u ON t.user_id = u.id
          WHERE t.status = 'success'";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (u.full_name LIKE ? OR t.reference LIKE ? OR t.id LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['type'])) {
    $query .= " AND t.type = ?";
    $params[] = $_GET['type'];
}

if (!empty($_GET['method'])) {
    $query .= " AND t.payment_method = ?";
    $params[] = $_GET['method'];
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(t.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(t.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = str_replace("SELECT t.*, 
          u.full_name AS student_name,
          CASE 
              WHEN t.type = 'course' THEN (SELECT title FROM courses WHERE courses.id = t.order_id)
              WHEN t.type = 'product' THEN (SELECT name FROM products WHERE products.id = t.order_id)
              ELSE '—'
          END AS item_title", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_transactions = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payments.php count: " . $e->getMessage());
}

$total_pages = ceil($total_transactions / $limit);

// Get transactions with pagination
$query .= " ORDER BY t.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $transactions = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payments.php: " . $e->getMessage());
}

// Calculate statistics
$total_amount = 0;
$course_payments = 0;
$product_payments = 0;
$card_payments = 0;
$mobile_payments = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        SUM(amount) as total_amount,
        COUNT(CASE WHEN type = 'course' THEN 1 END) as course_count,
        COUNT(CASE WHEN type = 'product' THEN 1 END) as product_count,
        COUNT(CASE WHEN payment_method = 'card' THEN 1 END) as card_count,
        COUNT(CASE WHEN payment_method = 'mobile_money' THEN 1 END) as mobile_count
        FROM transactions 
        WHERE status = 'success'");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_amount = $stats['total_amount'] ?? 0;
        $course_payments = $stats['course_count'] ?? 0;
        $product_payments = $stats['product_count'] ?? 0;
        $card_payments = $stats['card_count'] ?? 0;
        $mobile_payments = $stats['mobile_count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payments.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payments') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
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
            background: #f8f9fa;
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
            background: #f8f9fa;
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
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-money-bill-wave"></i> <?= __('payments') ?></h1>
                        <p><?= __('payment_history') ?></p>
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
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
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
                            placeholder="<?= __('search_payment_placeholder') ?>"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('type') ?></label>
                        <select name="type" class="form-control">
                            <option value=""><?= __('all_types') ?></option>
                            <option value="course" <?= ($_GET['type'] ?? '') === 'course' ? 'selected' : '' ?>><?= __('course') ?></option>
                            <option value="product" <?= ($_GET['type'] ?? '') === 'product' ? 'selected' : '' ?>><?= __('product') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('method') ?></label>
                        <select name="method" class="form-control">
                            <option value=""><?= __('all_methods') ?></option>
                            <option value="card" <?= ($_GET['method'] ?? '') === 'card' ? 'selected' : '' ?>><?= __('card') ?></option>
                            <option value="mobile_money" <?= ($_GET['method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>><?= __('mobile_money') ?></option>
                            <option value="bank_transfer" <?= ($_GET['method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>><?= __('bank_transfer') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('date_from') ?></label>
                        <input type="date" name="date_from" class="form-control"
                            value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('date_to') ?></label>
                        <input type="date" name="date_to" class="form-control"
                            value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            <?= __('filter') ?>
                        </button>
                        <a href="payments.php" class="btn btn-secondary">
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
                        <div class="stat-icon revenue">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_transactions) ?></div>
                            <div class="stat-label"><?= __('total_payments') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format($total_amount, 2) ?></div>
                            <div class="stat-label"><?= __('total_amount') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($course_payments) ?></div>
                            <div class="stat-label"><?= __('course_payments') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($product_payments) ?></div>
                            <div class="stat-label"><?= __('product_payments') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payments Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('payment_history') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_transactions ?> <?= __('payments_count') ?></span>
                        <span class="badge badge-success">GHS<?= number_format($total_amount, 2) ?> <?= __('total') ?></span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('transaction_id') ?></th>
                                <th><?= __('student') ?></th>
                                <th><?= __('type') ?></th>
                                <th><?= __('reference') ?></th>
                                <th><?= __('course_product') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('method') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('date') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-money-bill-wave" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p><?= __('no_payments_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                #<?= $txn['id'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($txn['student_name'] ?? 'Utilisateur #' . $txn['user_id']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $txn['type'] === 'course' ? 'badge-info' : 'badge-warning' ?>">
                                                <?= ucfirst($txn['type']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); font-family: monospace;">
                                                <?= htmlspecialchars($txn['reference']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($txn['item_title']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                $<?= number_format($txn['amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; text-transform: capitalize;">
                                                <?= htmlspecialchars($txn['payment_method']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-success"><?= __('success') ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($txn['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($txn['created_at'])) ?>
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&type=<?= htmlspecialchars($_GET['type'] ?? '') ?>&method=<?= htmlspecialchars($_GET['method'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&type=<?= htmlspecialchars($_GET['type'] ?? '') ?>&method=<?= htmlspecialchars($_GET['method'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&type=<?= htmlspecialchars($_GET['type'] ?? '') ?>&method=<?= htmlspecialchars($_GET['method'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
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

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>

</html>