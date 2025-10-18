<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

// Initialize variables with default values
$total_products = 0;
$total_sales = 0;
$total_earnings = 0;
$pending_orders = 0;
$recent_products = [];
$recent_sales = [];
$error_messages = [];
$success_messages = [];

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2) . ' GHS';
}

// Function to get time ago
function getTimeAgo($datetime) {
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
function getPercentageChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Function to get chart data
function getChartData($pdo, $vendor_id, $days = 30) {
    $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $data[$date] = 0;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT DATE(created_at) as date, COUNT(*) as count, SUM(amount) as revenue
            FROM transactions 
            WHERE vendor_id = ? AND type = 'product' AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
        ");
        $stmt->execute([$vendor_id, $days]);
        
        while ($row = $stmt->fetch()) {
            $data[$row['date']] = [
                'sales' => $row['count'],
                'revenue' => $row['revenue']
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting chart data: " . $e->getMessage());
    }
    
    return $data;
}

try {
    // Check if products table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'products'")->rowCount() > 0;
    
    if ($tableExists) {
        // Get vendor statistics
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
        if ($stmt->execute([$vendor_id])) {
            $total_products = $stmt->fetchColumn();
        }
        
        // Get active products count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ? AND status = 'active'");
        if ($stmt->execute([$vendor_id])) {
            $active_products = $stmt->fetchColumn();
        }
        
        // Check if transactions table exists
        $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
        
        if ($transactionsExists) {
            // Get total sales count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE vendor_id = ? AND type = 'product'");
            if ($stmt->execute([$vendor_id])) {
                $total_sales = $stmt->fetchColumn();
            }
            
            // Get total earnings
            $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE vendor_id = ? AND type = 'product' AND status = 'completed'");
            if ($stmt->execute([$vendor_id])) {
                $total_earnings = $stmt->fetchColumn();
            }
            
                    // Get this month's earnings
        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE vendor_id = ? AND type = 'product' AND status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        if ($stmt->execute([$vendor_id])) {
            $monthly_earnings = $stmt->fetchColumn();
        }
        
        // Get last month's earnings for comparison
        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE vendor_id = ? AND type = 'product' AND status = 'completed' AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))");
        if ($stmt->execute([$vendor_id])) {
            $last_month_earnings = $stmt->fetchColumn();
        }
        
        // Get this month's sales count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE vendor_id = ? AND type = 'product' AND status = 'completed' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
        if ($stmt->execute([$vendor_id])) {
            $monthly_sales = $stmt->fetchColumn();
        }
        
        // Get last month's sales count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE vendor_id = ? AND type = 'product' AND status = 'completed' AND MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) AND YEAR(created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))");
        if ($stmt->execute([$vendor_id])) {
            $last_month_sales = $stmt->fetchColumn();
            }
        }
        
        // Check if orders table exists
        $ordersExists = $pdo->query("SHOW TABLES LIKE 'orders'")->rowCount() > 0;
        
        if ($ordersExists) {
            // Get pending orders count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) AND o.status = 'pending'");
            if ($stmt->execute([$vendor_id])) {
                $pending_orders = $stmt->fetchColumn();
            }
        }
        
        // Get recent products with more details
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, status, stock_quantity, created_at, 
                   (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = products.id) as sales_count
            FROM products 
            WHERE vendor_id = ? 
            ORDER BY created_at DESC 
            LIMIT 6
        ");
        if ($stmt->execute([$vendor_id])) {
            $recent_products = $stmt->fetchAll();
        }
        
        // Get recent sales with more details
        $usersExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
        $orderItemsExists = $pdo->query("SHOW TABLES LIKE 'order_items'")->rowCount() > 0;
        
        if ($transactionsExists && $usersExists && $ordersExists && $orderItemsExists) {
            $stmt = $pdo->prepare("
                SELECT t.*, u.full_name as buyer_name, p.name as product_name, p.image_url as product_image
                FROM transactions t
                JOIN users u ON t.student_id = u.id
                JOIN order_items oi ON t.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE t.vendor_id = ? AND t.type = 'product'
                ORDER BY t.created_at DESC 
                LIMIT 8
            ");
            if ($stmt->execute([$vendor_id])) {
                $recent_sales = $stmt->fetchAll();
            }
        }
        
        // Get top performing products
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.image_url, p.status, p.stock_quantity,
                   COUNT(oi.id) as sales_count,
                   SUM(oi.price) as total_revenue
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            WHERE p.vendor_id = ?
            GROUP BY p.id
            ORDER BY sales_count DESC, total_revenue DESC
            LIMIT 5
        ");
        if ($stmt->execute([$vendor_id])) {
            $top_products = $stmt->fetchAll();
        }
        
        // Get low stock products
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, status, stock_quantity, created_at
            FROM products 
            WHERE vendor_id = ? AND stock_quantity <= 5 AND stock_quantity > 0
            ORDER BY stock_quantity ASC
            LIMIT 5
        ");
        if ($stmt->execute([$vendor_id])) {
            $low_stock_products = $stmt->fetchAll();
        }
        
        // Get out of stock products
        $stmt = $pdo->prepare("
            SELECT id, name, price, image_url, status, stock_quantity, created_at
            FROM products 
            WHERE vendor_id = ? AND stock_quantity = 0
            ORDER BY created_at DESC
            LIMIT 5
        ");
        if ($stmt->execute([$vendor_id])) {
            $out_of_stock_products = $stmt->fetchAll();
        }
        
        // Get chart data for analytics
        $chart_data = getChartData($pdo, $vendor_id, 30);
        
    } else {
        $error_messages[] = "La table des produits n'existe pas dans la base de données.";
    }
} catch (PDOException $e) {
    error_log("Database error in vendor dashboard: " . $e->getMessage());
    $error_messages[] = "Une erreur de base de données s'est produite. Veuillez réessayer.";
}

// Get current month and year for display
$current_month = date('F Y');
$current_year = date('Y');
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?> <?= __('vendor_space') ?> | TaaBia</title>
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

        .header p {
            color: #64748b;
            margin-top: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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

        .section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section h3 {
            margin-bottom: 1.5rem;
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .section h3 i {
            margin-right: 0.8rem;
            color: #00796b;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .product-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .product-card:hover {
            transform: translateY(-3px);
            border-color: #00796b;
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
        }

        .product-card img {
            width: 100%;
            height: 140px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .product-card .placeholder {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .product-card h4 {
            margin-bottom: 0.8rem;
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .product-card .price {
            color: #00796b;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .product-card .meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fef2f2;
            color: #991b1b;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .sales-table th,
        .sales-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .sales-table th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .sales-table tr:hover {
            background: #f8fafc;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.8rem 1.5rem;
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
            transform: translateY(-2px);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .empty-state h4 {
            font-size: 1.2rem;
            margin-bottom: 0.8rem;
            color: #475569;
        }

        .empty-state p {
            margin-bottom: 1.5rem;
            color: #64748b;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .top-products {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .top-product-card {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s ease;
        }

        .top-product-card:hover {
            border-color: #00796b;
            transform: translateY(-2px);
        }

        .top-product-card .rank {
            background: #00796b;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.8rem;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-action-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .quick-action-card:hover {
            transform: translateY(-3px);
            border-color: #00796b;
            box-shadow: 0 8px 25px -3px rgba(0, 0, 0, 0.1);
        }

        .quick-action-card i {
            font-size: 2rem;
            color: #00796b;
            margin-bottom: 1rem;
        }

        .quick-action-card h4 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .quick-action-card p {
            font-size: 0.9rem;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .stock-alert {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            color: #92400e;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stock-alert.critical {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 1rem;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
            .product-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .product-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 <?= __('vendor_space') ?></h2>
        <a href="index.php" class="active"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="products.php"><i class="fas fa-box"></i> <?= __('my_products') ?></a>
        <a href="add_product.php"><i class="fas fa-plus"></i> <?= __('add_product') ?></a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></a>
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
                    <h1><?= __('dashboard') ?> <?= __('vendor_space') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($_SESSION['full_name'] ?? __('vendor_space')) ?> !</p>
                </div>
                <div>
                    <?php include '../includes/language_switcher.php'; ?>
                </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action-card" onclick="window.location.href='add_product.php'">
                <i class="fas fa-plus"></i>
                <h4>Ajouter un Produit</h4>
                <p>Créez un nouveau produit pour votre boutique</p>
                <div class="btn">Commencer</div>
            </div>
            <div class="quick-action-card" onclick="window.location.href='products.php'">
                <i class="fas fa-box"></i>
                <h4>Gérer les Produits</h4>
                <p>Voir et modifier tous vos produits</p>
                <div class="btn">Voir tout</div>
            </div>
            <div class="quick-action-card" onclick="window.location.href='orders.php'">
                <i class="fas fa-shopping-cart"></i>
                <h4>Commandes</h4>
                <p>Gérez les commandes en cours</p>
                <div class="btn">Voir les commandes</div>
            </div>
            <div class="quick-action-card" onclick="window.location.href='earnings.php'">
                <i class="fas fa-chart-line"></i>
                <h4>Analytics</h4>
                <p>Analysez vos performances</p>
                <div class="btn">Voir les stats</div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-box"></i> <?= __('products') ?></h3>
                <div class="value"><?= $total_products ?></div>
                <div class="trend">
                    <i class="fas fa-check-circle"></i>
                    <?= $active_products ?? 0 ?> actifs
                </div>
                <p><?= __('total_in_shop') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-shopping-cart"></i> <?= __('sales') ?></h3>
                <div class="value"><?= $total_sales ?></div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    Ce mois: <?= $monthly_sales ?? 0 ?> ventes
                    <?php if (isset($monthly_sales) && isset($last_month_sales)): ?>
                        <span class="percentage <?= getPercentageChange($monthly_sales, $last_month_sales) >= 0 ? 'positive' : 'negative' ?>">
                            <?= getPercentageChange($monthly_sales, $last_month_sales) >= 0 ? '+' : '' ?><?= getPercentageChange($monthly_sales, $last_month_sales) ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <p><?= __('completed_transactions') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-money-bill-wave"></i> <?= __('earnings') ?></h3>
                <div class="value"><?= formatCurrency($total_earnings) ?></div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    Ce mois: <?= formatCurrency($monthly_earnings ?? 0) ?>
                    <?php if (isset($monthly_earnings) && isset($last_month_earnings)): ?>
                        <span class="percentage <?= getPercentageChange($monthly_earnings, $last_month_earnings) >= 0 ? 'positive' : 'negative' ?>">
                            <?= getPercentageChange($monthly_earnings, $last_month_earnings) >= 0 ? '+' : '' ?><?= getPercentageChange($monthly_earnings, $last_month_earnings) ?>%
                        </span>
                    <?php endif; ?>
                </div>
                <p><?= __('total_revenue') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> <?= __('pending') ?></h3>
                <div class="value"><?= $pending_orders ?></div>
                <div class="trend">
                    <i class="fas fa-exclamation-circle"></i>
                    Commandes en attente
                </div>
                <p><?= __('pending_orders') ?></p>
            </div>
        </div>

        <div class="section">
            <h3><i class="fas fa-box"></i> <?= __('recent_products') ?></h3>
            <?php if (empty($recent_products)): ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h4><?= __('no_products_found') ?></h4>
                <p><?= __('no_products_desc') ?></p>
                <a href="add_product.php" class="btn"><?= __('add_first_product') ?></a>
            </div>
            <?php else: ?>
            <div class="product-grid">
                <?php foreach ($recent_products as $product): ?>
                <div class="product-card" data-product-id="<?= $product['id'] ?>">
                    <?php if ($product['image_url']): ?>
                        <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <div class="placeholder">
                            <i class="fas fa-image" style="font-size: 2rem; color: #94a3b8;"></i>
                        </div>
                    <?php endif; ?>
                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                    <div class="price"><?= formatCurrency($product['price']) ?></div>
                    <div class="meta">
                        <span>Stock: <?= $product['stock_quantity'] ?></span>
                        <span>Ventes: <?= $product['sales_count'] ?? 0 ?></span>
                    </div>
                    <div class="status-badge <?= $product['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                        <?= ucfirst($product['status']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="products.php" class="btn"><?= __('view_all_products') ?></a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Stock Alerts -->
        <?php if (!empty($low_stock_products) || !empty($out_of_stock_products)): ?>
        <div class="section">
            <h3><i class="fas fa-exclamation-triangle"></i> Alertes de Stock</h3>
            
            <?php if (!empty($low_stock_products)): ?>
            <div style="margin-bottom: 1.5rem;">
                <h4 style="color: #92400e; margin-bottom: 1rem; font-size: 1rem;">Produits en Stock Faible (≤ 5 unités)</h4>
                <div class="product-grid">
                    <?php foreach ($low_stock_products as $product): ?>
                    <div class="product-card" data-product-id="<?= $product['id'] ?>">
                        <div class="stock-alert">
                            <i class="fas fa-exclamation-triangle"></i> Stock faible: <?= $product['stock_quantity'] ?> unités
                        </div>
                        <?php if ($product['image_url']): ?>
                            <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <div class="placeholder">
                                <i class="fas fa-image" style="font-size: 2rem; color: #94a3b8;"></i>
                            </div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($product['name']) ?></h4>
                        <div class="price"><?= formatCurrency($product['price']) ?></div>
                        <div class="meta">
                            <span>Stock: <?= $product['stock_quantity'] ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($out_of_stock_products)): ?>
            <div>
                <h4 style="color: #991b1b; margin-bottom: 1rem; font-size: 1rem;">Produits en Rupture de Stock</h4>
                <div class="product-grid">
                    <?php foreach ($out_of_stock_products as $product): ?>
                    <div class="product-card" data-product-id="<?= $product['id'] ?>">
                        <div class="stock-alert critical">
                            <i class="fas fa-times-circle"></i> Rupture de stock
                        </div>
                        <?php if ($product['image_url']): ?>
                            <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <div class="placeholder">
                                <i class="fas fa-image" style="font-size: 2rem; color: #94a3b8;"></i>
                            </div>
                        <?php endif; ?>
                        <h4><?= htmlspecialchars($product['name']) ?></h4>
                        <div class="price"><?= formatCurrency($product['price']) ?></div>
                        <div class="meta">
                            <span>Stock: 0</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($top_products)): ?>
        <div class="section">
            <h3><i class="fas fa-trophy"></i> Produits les Plus Performants</h3>
            <div class="top-products">
                <?php foreach ($top_products as $index => $product): ?>
                <div class="top-product-card">
                    <div class="rank">#<?= $index + 1 ?></div>
                    <?php if ($product['image_url']): ?>
                        <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-bottom: 0.8rem;">
                    <?php else: ?>
                        <div style="width: 60px; height: 60px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                            <i class="fas fa-image" style="color: #94a3b8;"></i>
                        </div>
                    <?php endif; ?>
                    <h4 style="font-size: 0.9rem; margin-bottom: 0.5rem;"><?= htmlspecialchars($product['name']) ?></h4>
                    <div style="font-size: 0.8rem; color: #64748b;">
                        <div>Ventes: <?= $product['sales_count'] ?></div>
                        <div>Revenus: <?= formatCurrency($product['total_revenue'] ?? 0) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h3><i class="fas fa-chart-line"></i> <?= __('recent_sales') ?></h3>
            <?php if (empty($recent_sales)): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h4><?= __('no_recent_sales') ?></h4>
                <p><?= __('no_sales_desc') ?></p>
            </div>
            <?php else: ?>
            <table class="sales-table">
                <thead>
                    <tr>
                        <th><?= __('product') ?></th>
                        <th><?= __('buyer') ?></th>
                        <th><?= __('amount') ?></th>
                        <th><?= __('date') ?></th>
                        <th><?= __('status') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sales as $sale): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.8rem;">
                                <?php if ($sale['product_image']): ?>
                                    <img src="../uploads/<?= htmlspecialchars($sale['product_image']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 6px;">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($sale['product_name']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($sale['buyer_name']) ?></td>
                        <td><?= formatCurrency($sale['amount']) ?></td>
                        <td><?= getTimeAgo($sale['created_at']) ?></td>
                        <td>
                            <span class="status-badge <?= $sale['status'] === 'completed' ? 'status-active' : 'status-inactive' ?>">
                                <?= ucfirst($sale['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 1.5rem; text-align: center;">
                <a href="earnings.php" class="btn">Voir tous les gains</a>
            </div>
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

            // Add click handlers for product cards
            const productCards = document.querySelectorAll('.product-card');
            productCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Add navigation to product details
                    const productId = this.dataset.productId;
                    if (productId) {
                        window.location.href = `edit_product.php?id=${productId}`;
                    }
                });
                card.style.cursor = 'pointer';
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

            // Auto-refresh dashboard data every 5 minutes
            setInterval(function() {
                addLoadingAnimation();
                // You can add AJAX call here to refresh data
                console.log('Refreshing dashboard data...');
            }, 300000); // 5 minutes

            // Add real-time notifications
            function checkForNewOrders() {
                // This could be an AJAX call to check for new orders
                console.log('Checking for new orders...');
            }

            // Check for new orders every 30 seconds
            setInterval(checkForNewOrders, 30000);

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'p':
                            e.preventDefault();
                            window.location.href = 'add_product.php';
                            break;
                        case 's':
                            e.preventDefault();
                            window.location.href = 'products.php';
                            break;
                        case 'e':
                            e.preventDefault();
                            window.location.href = 'earnings.php';
                            break;
                        case 'o':
                            e.preventDefault();
                            window.location.href = 'orders.php';
                            break;
                        case 'r':
                            e.preventDefault();
                            location.reload();
                            break;
                    }
                }
            });

            // Add tooltips for keyboard shortcuts
            const addProductBtn = document.querySelector('a[href="add_product.php"]');
            if (addProductBtn) {
                addProductBtn.title = 'Ajouter un produit (Ctrl+P)';
            }

            // Add real-time notifications
            function showNotification(message, type = 'info') {
                const notification = document.createElement('div');
                notification.className = `alert alert-${type}`;
                notification.style.position = 'fixed';
                notification.style.top = '20px';
                notification.style.right = '20px';
                notification.style.zIndex = '1000';
                notification.style.animation = 'slideIn 0.3s ease';
                notification.textContent = message;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'slideOut 0.3s ease';
                    setTimeout(() => {
                        document.body.removeChild(notification);
                    }, 300);
                }, 3000);
            }

            // Add CSS animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOut {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                .stat-card:hover .value {
                    animation: pulse 0.6s ease;
                }
            `;
            document.head.appendChild(style);

            // Add search functionality
            function addSearchFunctionality() {
                const searchInput = document.createElement('input');
                searchInput.type = 'text';
                searchInput.placeholder = 'Rechercher des produits...';
                searchInput.style.cssText = `
                    width: 100%;
                    padding: 0.75rem 1rem;
                    border: 2px solid #e2e8f0;
                    border-radius: 8px;
                    font-size: 0.95rem;
                    margin-bottom: 1rem;
                `;
                
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const productCards = document.querySelectorAll('.product-card');
                    
                    productCards.forEach(card => {
                        const productName = card.querySelector('h4').textContent.toLowerCase();
                        if (productName.includes(searchTerm)) {
                            card.style.display = 'block';
                        } else {
                            card.style.display = 'none';
                        }
                    });
                });
                
                // Insert search input before the first section
                const firstSection = document.querySelector('.section');
                if (firstSection) {
                    firstSection.parentNode.insertBefore(searchInput, firstSection);
                }
            }

            // Initialize search functionality
            addSearchFunctionality();

            // Add smooth scrolling for better UX
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
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
        });
    </script>
</body>
</html>
