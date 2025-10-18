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
$requests = [];
$total_requests = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Process action (approve / reject / paid)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'];
    $now = date('Y-m-d H:i:s');

    if (in_array($action, ['approved', 'rejected', 'paid'])) {
        try {
            $sql = "UPDATE payout_requests SET status = :status";
            if ($action === 'paid') {
                $sql .= ", paid_at = :paid_at";
            }
            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':status', $action);
            $stmt->bindValue(':id', $id);
            if ($action === 'paid') {
                $stmt->bindValue(':paid_at', $now);
            }
            $stmt->execute();

            // Set success message
            $_SESSION['success_message'] = __('payout_request_updated_successfully');
        } catch (PDOException $e) {
            error_log("Database error in admin/payout_requests.php update: " . $e->getMessage());
            $_SESSION['error_message'] = __('error_updating_payout_request');
        }
    }

    header("Location: payout_requests.php");
    exit();
}

// Build query with search and filters
$query = "SELECT pr.*, u.full_name, u.role 
          FROM payout_requests pr 
          JOIN users u ON pr.user_id = u.id 
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (u.full_name LIKE ? OR pr.id LIKE ? OR pr.notes LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $query .= " AND pr.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['role'])) {
    $query .= " AND u.role = ?";
    $params[] = $_GET['role'];
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(pr.requested_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(pr.requested_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = str_replace("SELECT pr.*, u.full_name, u.role", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_requests = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payout_requests.php count: " . $e->getMessage());
}

$total_pages = ceil($total_requests / $limit);

// Get requests with pagination
$query .= " ORDER BY pr.requested_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $requests = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payout_requests.php: " . $e->getMessage());
}

// Calculate statistics
$total_amount = 0;
$pending_requests = 0;
$approved_requests = 0;
$paid_requests = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        SUM(amount) as total_amount,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_count
        FROM payout_requests");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_amount = $stats['total_amount'] ?? 0;
        $pending_requests = $stats['pending_count'] ?? 0;
        $approved_requests = $stats['approved_count'] ?? 0;
        $paid_requests = $stats['paid_count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payout_requests.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payout_requests') ?> | <?= __('admin_panel') ?> | TaaBia</title>

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

        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }

        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

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

            .header-content {
                padding-left: 20px;
            }
        }

        /* Enhanced Payout Management Styles */
        .payout-status {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payout-status.pending {
            background: linear-gradient(45deg, #ff9800, #ffb74d);
            color: white;
        }

        .payout-status.approved {
            background: linear-gradient(45deg, #4caf50, #66bb6a);
            color: white;
        }

        .payout-status.rejected {
            background: linear-gradient(45deg, #f44336, #ef5350);
            color: white;
        }

        .payout-status.paid {
            background: linear-gradient(45deg, #2196f3, #42a5f5);
            color: white;
        }

        .payout-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .payout-actions .btn {
            padding: 0.375rem 0.75rem;
            font-size: 0.875rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .payout-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .payout-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .payout-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .payout-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .payout-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payout-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(45deg, #00796b, #26a69a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.25rem;
        }

        .payout-info h4 {
            margin: 0;
            color: #2c3e50;
            font-weight: 600;
        }

        .payout-info p {
            margin: 0.25rem 0 0 0;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .payout-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: #00796b;
        }

        .payout-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .payout-detail {
            display: flex;
            flex-direction: column;
        }

        .payout-detail-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }

        .payout-detail-value {
            font-weight: 600;
            color: #2c3e50;
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
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-hand-holding-usd"></i> <?= __('payout_requests') ?></h1>
                        <p><?= __('manage_payout_requests') ?></p>
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
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message']) ?>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="search-filters">
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="form-label"><?= __('search') ?></label>
                        <input type="text" name="search" class="form-control"
                            placeholder="<?= __('search_payout_placeholder') ?>"
                            value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('status') ?></label>
                        <select name="status" class="form-control">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                            <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>><?= __('approved') ?></option>
                            <option value="rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>><?= __('rejected') ?></option>
                            <option value="paid" <?= ($_GET['status'] ?? '') === 'paid' ? 'selected' : '' ?>><?= __('paid') ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="form-label"><?= __('role') ?></label>
                        <select name="role" class="form-control">
                            <option value=""><?= __('all_roles') ?></option>
                            <option value="instructor" <?= ($_GET['role'] ?? '') === 'instructor' ? 'selected' : '' ?>><?= __('instructor') ?></option>
                            <option value="vendor" <?= ($_GET['role'] ?? '') === 'vendor' ? 'selected' : '' ?>><?= __('vendor') ?></option>
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
                        <a href="payout_requests.php" class="btn btn-secondary">
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
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_requests) ?></div>
                            <div class="stat-label"><?= __('total_requests') ?></div>
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
                        <div class="stat-icon users">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($pending_requests) ?></div>
                            <div class="stat-label">En Attente</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($paid_requests) ?></div>
                            <div class="stat-label">Payées</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payout Requests Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Demandes de Paiement</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_requests ?> demandes</span>
                        <span class="badge badge-success">GHS<?= number_format($total_amount, 2) ?> total</span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?= __('id') ?></th>
                                <th><?= __('user') ?></th>
                                <th><?= __('role') ?></th>
                                <th><?= __('amount') ?></th>
                                <th><?= __('status') ?></th>
                                <th><?= __('requested_date') ?></th>
                                <th><?= __('paid_date') ?></th>
                                <th><?= __('notes') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-hand-holding-usd" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p><?= __('no_requests_found') ?></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                #<?= $req['id'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($req['full_name']) ?></div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $req['role'] === 'instructor' ? 'badge-info' : 'badge-warning' ?>">
                                                <?= ucfirst($req['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                $<?= number_format($req['amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_labels = [
                                                'pending' => [__('pending'), 'badge-warning'],
                                                'approved' => [__('approved'), 'badge-info'],
                                                'rejected' => [__('rejected'), 'badge-danger'],
                                                'paid' => [__('paid'), 'badge-success']
                                            ];
                                            $status_info = $status_labels[$req['status']] ?? [__('unknown'), 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($req['requested_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($req['requested_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($req['paid_at']): ?>
                                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                    <?= date('d/m/Y', strtotime($req['paid_at'])) ?>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-light);">
                                                    <?= date('H:i', strtotime($req['paid_at'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-light);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                                <?= htmlspecialchars($req['notes'] ?? '') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <div class="d-flex gap-1">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                                        <button type="submit" name="action" value="approved"
                                                            class="btn btn-sm btn-success"
                                                            title="<?= __('approve') ?>"
                                                            onclick="return confirm('<?= __('confirm_approve_request') ?>')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                                        <button type="submit" name="action" value="rejected"
                                                            class="btn btn-sm btn-danger"
                                                            title="<?= __('reject') ?>"
                                                            onclick="return confirm('<?= __('confirm_reject_request') ?>')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($req['status'] === 'approved'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                                    <button type="submit" name="action" value="paid"
                                                        class="btn btn-sm btn-primary"
                                                        title="<?= __('mark_as_paid') ?>"
                                                        onclick="return confirm('<?= __('confirm_mark_paid') ?>')">
                                                        <i class="fas fa-money-bill-wave"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span style="color: var(--text-light);">-</span>
                                            <?php endif; ?>
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
                                class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>"
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

        // Hamburger menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');

            function toggleSidebar() {
                hamburgerMenu.classList.toggle('active');
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');

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

            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', toggleSidebar);
            }

            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }

            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });

            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSidebar();
                    }
                });
            }

            // Enhanced payout management interactions
            const payoutCards = document.querySelectorAll('.payout-card');
            payoutCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Status badge animations
            const statusBadges = document.querySelectorAll('.payout-status');
            statusBadges.forEach(badge => {
                badge.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.05)';
                });

                badge.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
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