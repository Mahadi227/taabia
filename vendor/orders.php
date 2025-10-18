<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

// Initialize variables
$orders = [];
$total_orders = 0;
$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;
$total_revenue = 0;
$monthly_revenue = 0;
$error_messages = [];
$success_messages = [];

// Function to format currency
function formatCurrency($amount)
{
    return number_format($amount, 2) . ' GHS';
}

// Function to get time ago
function getTimeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) {
        return "À l'instant";
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Il y a $minutes minute" . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Il y a $hours heure" . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "Il y a $days jour" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y', strtotime($datetime));
    }
}

// Function to get percentage change
function getPercentageChange($current, $previous)
{
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

try {
    // Check if required tables exist
    $ordersExists = $pdo->query("SHOW TABLES LIKE 'orders'")->rowCount() > 0;
    $orderItemsExists = $pdo->query("SHOW TABLES LIKE 'order_items'")->rowCount() > 0;
    $usersExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;

    if ($ordersExists && $orderItemsExists && $usersExists) {
        // Get total orders count
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id) 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?)
        ");
        if ($stmt->execute([$vendor_id])) {
            $total_orders = $stmt->fetchColumn();
        }

        // Get pending orders count
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id) 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) 
            AND o.status = 'pending'
        ");
        if ($stmt->execute([$vendor_id])) {
            $pending_orders = $stmt->fetchColumn();
        }

        // Get completed orders count
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id) 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) 
            AND o.status = 'delivered'
        ");
        if ($stmt->execute([$vendor_id])) {
            $completed_orders = $stmt->fetchColumn();
        }

        // Get cancelled orders count
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT o.id) 
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) 
            AND o.status = 'cancelled'
        ");
        if ($stmt->execute([$vendor_id])) {
            $cancelled_orders = $stmt->fetchColumn();
        }

        // Get total revenue
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(o.total_amount), 0)
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) 
            AND o.status IN ('paid', 'shipped', 'delivered')
        ");
        if ($stmt->execute([$vendor_id])) {
            $total_revenue = $stmt->fetchColumn();
        }

        // Get monthly revenue
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(o.total_amount), 0)
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) 
            AND o.status IN ('paid', 'shipped', 'delivered')
            AND MONTH(o.created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
        ");
        if ($stmt->execute([$vendor_id])) {
            $monthly_revenue = $stmt->fetchColumn();
        }

        // Get last month revenue for comparison
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(o.total_amount), 0)
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) 
            AND o.status IN ('paid', 'shipped', 'delivered')
            AND MONTH(o.created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
            AND YEAR(o.created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
        ");
        if ($stmt->execute([$vendor_id])) {
            $last_month_revenue = $stmt->fetchColumn();
        }

        // Get orders with pagination and filtering
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 15;
        $offset = ($page - 1) * $limit;

        // Filter parameters
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        $date_filter = isset($_GET['date']) ? $_GET['date'] : '';
        $search = isset($_GET['search']) ? $_GET['search'] : '';

        // Build WHERE clause
        $where_conditions = ["oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?)"];
        $params = [$vendor_id];

        if ($status_filter) {
            $where_conditions[] = "o.status = ?";
            $params[] = $status_filter;
        }

        if ($date_filter) {
            switch ($date_filter) {
                case 'today':
                    $where_conditions[] = "DATE(o.created_at) = CURDATE()";
                    break;
                case 'week':
                    $where_conditions[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $where_conditions[] = "o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
            }
        }

        if ($search) {
            $where_conditions[] = "(u.fullname LIKE ? OR u.email LIKE ? OR o.order_number LIKE ?)";
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        $where_clause = implode(" AND ", $where_conditions);

        $stmt = $pdo->prepare("
            SELECT DISTINCT o.*, u.fullname as buyer_name, u.email as buyer_email,
                   (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.id AND oi2.product_id IN (SELECT id FROM products WHERE vendor_id = ?)) as vendor_items_count,
                   (SELECT SUM(oi3.price) FROM order_items oi3 WHERE oi3.order_id = o.id AND oi3.product_id IN (SELECT id FROM products WHERE vendor_id = ?)) as vendor_amount
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            JOIN users u ON o.buyer_id = u.id
            WHERE $where_clause
            ORDER BY o.created_at DESC 
            LIMIT ? OFFSET ?
        ");

        $params[] = $vendor_id; // for vendor_items_count
        $params[] = $vendor_id; // for vendor_amount
        $params[] = $limit;
        $params[] = $offset;

        if ($stmt->execute($params)) {
            $orders = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    error_log("Database error in vendor orders: " . $e->getMessage());
}

// Calculate total pages
$total_pages = ceil($total_orders / $limit);
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('orders') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: #f8fafc;
        color: #1e293b;
        line-height: 1.6;
    }

    .sidebar {
        width: 280px;
        height: 100vh;
        background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
        color: white;
        position: fixed;
        padding: 2rem 1.5rem;
        overflow-y: auto;
    }

    .sidebar h2 {
        margin-bottom: 2rem;
        text-align: center;
        font-size: 1.5rem;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.1);
        padding: 1rem;
        border-radius: 12px;
    }

    .sidebar a {
        display: flex;
        align-items: center;
        color: white;
        text-decoration: none;
        padding: 1rem 1.2rem;
        margin-bottom: 0.5rem;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .sidebar a:hover {
        background: rgba(255, 255, 255, 0.15);
        transform: translateX(5px);
    }

    .sidebar a i {
        margin-right: 12px;
        width: 20px;
        text-align: center;
    }

    .main-content {
        margin-left: 280px;
        padding: 2rem;
        min-height: 100vh;
    }

    .header {
        background: white;
        padding: 1.5rem 2rem;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header h1 {
        color: #1e293b;
        font-size: 2rem;
        font-weight: 700;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.8rem;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        border-left: 4px solid #00796b;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
    }

    .stat-card h3 {
        color: #64748b;
        margin-bottom: 0.8rem;
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-card .value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 0.5rem;
    }

    .stat-card .trend {
        font-size: 0.9rem;
        color: #10b981;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .stat-card .trend.negative {
        color: #ef4444;
    }

    .stat-card .trend i {
        font-size: 0.8rem;
    }

    .stat-card .percentage {
        font-size: 0.8rem;
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        font-weight: 600;
    }

    .stat-card .percentage.positive {
        background: #dcfce7;
        color: #166534;
    }

    .stat-card .percentage.negative {
        background: #fef2f2;
        color: #991b1b;
    }

    .orders-section {
        background: white;
        padding: 2rem;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .orders-section h3 {
        margin-bottom: 1.5rem;
        color: #1e293b;
        font-size: 1.3rem;
        font-weight: 600;
        display: flex;
        align-items: center;
    }

    .orders-section h3 i {
        margin-right: 0.8rem;
        color: #00796b;
    }

    .filters-section {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border: 1px solid #e2e8f0;
    }

    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        align-items: end;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
    }

    .filter-group label {
        font-size: 0.9rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
    }

    .filter-group select,
    .filter-group input {
        padding: 0.75rem;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .filter-group select:focus,
    .filter-group input:focus {
        outline: none;
        border-color: #00796b;
        box-shadow: 0 0 0 3px rgba(0, 119, 107, 0.1);
    }

    .filter-actions {
        display: flex;
        gap: 0.5rem;
    }

    .orders-table {
        width: 100%;
        border-collapse: collapse;
    }

    .orders-table th,
    .orders-table td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }

    .orders-table th {
        background: #f8fafc;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }

    .orders-table tr:hover {
        background: #f8fafc;
    }

    .order-number {
        font-weight: 600;
        color: #00796b;
        font-family: 'Courier New', monospace;
    }

    .customer-info {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .customer-name {
        font-weight: 600;
        color: #1e293b;
    }

    .customer-email {
        font-size: 0.85rem;
        color: #64748b;
    }

    .order-amount {
        font-weight: 700;
        color: #00796b;
        font-size: 1.1rem;
    }

    .order-date {
        font-size: 0.9rem;
        color: #64748b;
    }

    .order-items {
        font-size: 0.85rem;
        color: #64748b;
        margin-top: 0.25rem;
    }

    .status-badge {
        padding: 0.3rem 0.6rem;
        border-radius: 15px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-pending {
        background: #fff3cd;
        color: #856404;
    }

    .status-paid {
        background: #d1ecf1;
        color: #0c5460;
    }

    .status-shipped {
        background: #d4edda;
        color: #155724;
    }

    .status-delivered {
        background: #d4edda;
        color: #155724;
    }

    .status-cancelled {
        background: #f8d7da;
        color: #721c24;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        background: #00796b;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
    }

    .btn:hover {
        background: #00695c;
        transform: translateY(-1px);
    }

    .btn i {
        margin-right: 0.5rem;
    }

    .btn-secondary {
        background: #6b7280;
    }

    .btn-secondary:hover {
        background: #4b5563;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.8rem;
    }

    .btn-outline {
        background: transparent;
        color: #00796b;
        border: 2px solid #00796b;
    }

    .btn-outline:hover {
        background: #00796b;
        color: white;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .pagination a {
        padding: 0.5rem 1rem;
        border: 1px solid #ddd;
        text-decoration: none;
        color: #333;
        border-radius: 5px;
    }

    .pagination a:hover {
        background: #f8f9fa;
    }

    .pagination .active {
        background: #00796b;
        color: white;
        border-color: #00796b;
    }

    .empty-state {
        text-align: center;
        padding: 3rem;
        color: #666;
    }

    .empty-state i {
        font-size: 3rem;
        color: #ccc;
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
        }

        .main-content {
            margin-left: 0;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .orders-table {
            font-size: 0.9rem;
        }
    }
    </style>
</head>

<body>
    <div class="sidebar">
        <h2>🏪 <?= __('vendor_space') ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="products.php"><i class="fas fa-box"></i> <?= __('my_products') ?></a>
        <a href="add_product.php"><i class="fas fa-plus"></i> <?= __('add_product') ?></a>
        <a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> <?= __('payments') ?></a>
        <a href="profile.php"><i class="fas fa-user"></i> <?= __('my_profile') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
    </div>

    <div class="main-content">
        <?php if (!empty($error_messages)): ?>
        <?php foreach ($error_messages as $error): ?>
        <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($success_messages)): ?>
        <?php foreach ($success_messages as $success): ?>
        <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div class="header">
            <div>
                <h1><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></h1>
                <p>Gérez et suivez toutes vos commandes</p>
            </div>
            <div>
                <?php include '../includes/language_switcher.php'; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-shopping-cart"></i> Total Commandes</h3>
                <div class="value"><?= $total_orders ?></div>
                <div class="trend">
                    <i class="fas fa-chart-line"></i>
                    Toutes les commandes
                </div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> En Attente</h3>
                <div class="value"><?= $pending_orders ?></div>
                <div class="trend">
                    <i class="fas fa-exclamation-circle"></i>
                    Commandes en attente
                </div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Livrées</h3>
                <div class="value"><?= $completed_orders ?></div>
                <div class="trend">
                    <i class="fas fa-check-double"></i>
                    Commandes livrées
                </div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-money-bill-wave"></i> Revenus</h3>
                <div class="value"><?= formatCurrency($total_revenue) ?></div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    Ce mois: <?= formatCurrency($monthly_revenue) ?>
                    <?php if (isset($monthly_revenue) && isset($last_month_revenue)): ?>
                    <span
                        class="percentage <?= getPercentageChange($monthly_revenue, $last_month_revenue) >= 0 ? 'positive' : 'negative' ?>">
                        <?= getPercentageChange($monthly_revenue, $last_month_revenue) >= 0 ? '+' : '' ?><?= getPercentageChange($monthly_revenue, $last_month_revenue) ?>%
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="orders-section">
            <h3><i class="fas fa-list"></i> Liste des Commandes</h3>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="" class="filters-grid">
                    <div class="filter-group">
                        <label for="search">Rechercher</label>
                        <input type="text" id="search" name="search"
                            placeholder="Client, email ou numéro de commande..."
                            value="<?= htmlspecialchars($search ?? '') ?>">
                    </div>
                    <div class="filter-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= ($status_filter ?? '') === 'pending' ? 'selected' : '' ?>>En
                                attente</option>
                            <option value="paid" <?= ($status_filter ?? '') === 'paid' ? 'selected' : '' ?>>Payé
                            </option>
                            <option value="shipped" <?= ($status_filter ?? '') === 'shipped' ? 'selected' : '' ?>>
                                Expédié</option>
                            <option value="delivered" <?= ($status_filter ?? '') === 'delivered' ? 'selected' : '' ?>>
                                Livré</option>
                            <option value="cancelled" <?= ($status_filter ?? '') === 'cancelled' ? 'selected' : '' ?>>
                                Annulé</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="date">Période</label>
                        <select id="date" name="date">
                            <option value="">Toutes les dates</option>
                            <option value="today" <?= ($date_filter ?? '') === 'today' ? 'selected' : '' ?>>Aujourd'hui
                            </option>
                            <option value="week" <?= ($date_filter ?? '') === 'week' ? 'selected' : '' ?>>Cette semaine
                            </option>
                            <option value="month" <?= ($date_filter ?? '') === 'month' ? 'selected' : '' ?>>Ce mois
                            </option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <a href="orders.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h4>Aucune commande trouvée</h4>
                <p><?= !empty($search) || !empty($status_filter) || !empty($date_filter) ? 'Aucune commande ne correspond à vos critères de recherche.' : 'Vous n\'avez pas encore reçu de commandes pour vos produits.' ?>
                </p>
                <?php if (!empty($search) || !empty($status_filter) || !empty($date_filter)): ?>
                <a href="orders.php" class="btn">Voir toutes les commandes</a>
                <?php else: ?>
                <a href="products.php" class="btn">Gérer mes produits</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>N° Commande</th>
                        <th>Client</th>
                        <th>Montant</th>
                        <th>Date</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                            <div class="order-items"><?= $order['vendor_items_count'] ?? 0 ?> article(s) de vos produits
                            </div>
                        </td>
                        <td>
                            <div class="customer-info">
                                <div class="customer-name"><?= htmlspecialchars($order['buyer_name']) ?></div>
                                <div class="customer-email"><?= htmlspecialchars($order['buyer_email']) ?></div>
                            </div>
                        </td>
                        <td>
                            <div class="order-amount">
                                <?= formatCurrency($order['vendor_amount'] ?? $order['total_amount']) ?></div>
                            <?php if (($order['vendor_amount'] ?? 0) < $order['total_amount']): ?>
                            <div class="order-items">Sur <?= formatCurrency($order['total_amount']) ?> total</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="order-date"><?= date('d/m/Y', strtotime($order['created_at'])) ?></div>
                            <div class="order-items"><?= getTimeAgo($order['created_at']) ?></div>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a
                    href="?page=<?= $page - 1 ?>&status=<?= $status_filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>">&laquo;
                    Précédent</a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&status=<?= $status_filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>"
                    <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a
                    href="?page=<?= $page + 1 ?>&status=<?= $status_filter ?>&date=<?= $date_filter ?>&search=<?= urlencode($search) ?>">Suivant
                    &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Enhanced interactivity and dynamic features
    document.addEventListener('DOMContentLoaded', function() {
        // Add hover effects to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Add real-time search functionality
        const searchInput = document.getElementById('search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'f':
                        e.preventDefault();
                        document.getElementById('search').focus();
                        break;
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                }
            }
        });

        // Add loading animations
        function addLoadingAnimation() {
            const cards = document.querySelectorAll('.stat-card .value');
            cards.forEach(card => {
                card.style.opacity = '0.7';
                setTimeout(() => {
                    card.style.opacity = '1';
                }, 500);
            });
        }

        // Auto-refresh orders every 2 minutes
        setInterval(function() {
            addLoadingAnimation();
            // You can add AJAX call here to refresh orders
            console.log('Refreshing orders data...');
        }, 120000); // 2 minutes

        // Add smooth scrolling for better UX
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

        // Add responsive behavior
        function handleResize() {
            const sidebar = document.querySelector('.sidebar');
            const mainContent = document.querySelector('.main-content');

            if (window.innerWidth <= 1024) {
                sidebar.style.position = 'relative';
                sidebar.style.width = '100%';
                mainContent.style.marginLeft = '0';
            } else {
                sidebar.style.position = 'fixed';
                sidebar.style.width = '280px';
                mainContent.style.marginLeft = '280px';
            }
        }

        window.addEventListener('resize', handleResize);
        handleResize(); // Initial call

        // Add tooltips for better UX
        const orderRows = document.querySelectorAll('.orders-table tbody tr');
        orderRows.forEach(row => {
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f1f5f9';
            });
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
        });
    });
    </script>
</body>

</html>