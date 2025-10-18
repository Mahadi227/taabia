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
$query = "SELECT t.*, u.full_name, u.email 
          FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR t.reference LIKE ? OR t.id LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $query .= " AND t.payment_status = ?";
    $params[] = $_GET['status'];
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
$count_query = str_replace("SELECT t.*, u.full_name, u.email", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_transactions = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/transactions.php count: " . $e->getMessage());
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
    error_log("Database error in admin/transactions.php: " . $e->getMessage());
}

// Calculate statistics
$total_amount = 0;
$successful_transactions = 0;
$pending_transactions = 0;
$failed_transactions = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        SUM(amount) as total_amount,
        COUNT(CASE WHEN payment_status = 'success' THEN 1 END) as success_count,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_count
        FROM transactions");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_amount = $stats['total_amount'] ?? 0;
        $successful_transactions = $stats['success_count'] ?? 0;
        $pending_transactions = $stats['pending_count'] ?? 0;
        $failed_transactions = $stats['failed_count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/transactions.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('transactions') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        .transactions-table-container {
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
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-exchange-alt"></i> <?= __('transactions') ?></h1>
                        <p><?= __('manage_transactions') ?></p>
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
                            placeholder="<?= __('search_transaction_placeholder') ?>"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('status') ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="success" <?= ($_GET['status'] ?? '') === 'success' ? 'selected' : '' ?>>
                                <?= __('success') ?></option>
                            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                            <option value="failed" <?= ($_GET['status'] ?? '') === 'failed' ? 'selected' : '' ?>><?= __('failed') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('payment_method') ?></label>
                        <select name="method" class="form-control">
                            <option value=""><?= __('all_methods') ?></option>
                            <option value="card" <?= ($_GET['method'] ?? '') === 'card' ? 'selected' : '' ?>><?= __('card') ?></option>
                            <option value="bank_transfer"
                                <?= ($_GET['method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>><?= __('bank_transfer') ?></option>
                            <option value="mobile_money"
                                <?= ($_GET['method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>><?= __('mobile_money') ?></option>
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
                        <a href="transactions.php" class="btn btn-secondary">
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
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_transactions) ?></div>
                            <div class="stat-label"><?= __('total_transactions') ?></div>
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
                        <div class="stat-icon students">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($successful_transactions) ?></div>
                            <div class="stat-label">Transactions Réussies</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($pending_transactions) ?></div>
                            <div class="stat-label">En Attente</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Transactions</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_transactions ?> transactions</span>
                    </div>
                </div>

                <div class="table-container">
                    <div class="transactions-table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= __('transaction_id') ?></th>
                                    <th><?= __('user') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('payment_method') ?></th>
                                    <th><?= __('status') ?></th>
                                    <th><?= __('reference') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center" style="padding: 3rem;">
                                            <i class="fas fa-exchange-alt"
                                                style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                            <p><?= __('no_transactions_found') ?></p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $tx): ?>
                                        <tr>
                                            <td>
                                                <div style="font-weight: 600; color: var(--primary-color);">
                                                    #<?= $tx['id'] ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500;">
                                                    <?= htmlspecialchars($tx['full_name'] ?? 'Utilisateur #' . $tx['user_id']) ?>
                                                </div>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                    <?= htmlspecialchars($tx['email'] ?? 'N/A') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 600; color: var(--success-color);">
                                                    <?= number_format($tx['amount'], 2) ?>
                                                    <?= htmlspecialchars($tx['currency'] ?? 'USD') ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-weight: 500; text-transform: capitalize;">
                                                    <?= htmlspecialchars($tx['payment_method']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php
                                                $status_labels = [
                                                    'success' => [__('success'), 'badge-success'],
                                                    'pending' => [__('pending'), 'badge-warning'],
                                                    'failed' => [__('failed'), 'badge-danger']
                                                ];
                                                $status_info = $status_labels[$tx['payment_status']] ?? [__('unknown'), 'badge-secondary'];
                                                ?>
                                                <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                                            </td>
                                            <td>
                                                <div
                                                    style="font-size: 0.875rem; color: var(--text-secondary); font-family: monospace;">
                                                    <?= htmlspecialchars($tx['reference']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                    <?= date('d/m/Y', strtotime($tx['created_at'])) ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-light);">
                                                    <?= date('H:i', strtotime($tx['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="edit_transaction.php?id=<?= $tx['id'] ?>"
                                                        class="btn btn-sm btn-warning" title="Modifier">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="delete_transaction.php?id=<?= $tx['id'] ?>"
                                                        class="btn btn-sm btn-danger" title="Supprimer"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?')">
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
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&method=<?= htmlspecialchars($_GET['method'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&method=<?= htmlspecialchars($_GET['method'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&method=<?= htmlspecialchars($_GET['method'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
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