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

try {
    // Check if products table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'products'")->rowCount() > 0;
    
    if ($tableExists) {
        // Statistiques du vendeur
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
        if ($stmt->execute([$vendor_id])) {
            $total_products = $stmt->fetchColumn();
        }
        
        // Check if transactions table exists
        $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
        
        if ($transactionsExists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE vendor_id = ? AND type = 'product_purchase'");
            if ($stmt->execute([$vendor_id])) {
                $total_sales = $stmt->fetchColumn();
            }
            
            $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE vendor_id = ? AND type = 'product_purchase' AND status = 'completed'");
            if ($stmt->execute([$vendor_id])) {
                $total_earnings = $stmt->fetchColumn();
            }
        }
        
        // Check if orders table exists
        $ordersExists = $pdo->query("SHOW TABLES LIKE 'orders'")->rowCount() > 0;
        
        if ($ordersExists) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o JOIN order_items oi ON o.id = oi.order_id WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?) AND o.status = 'pending'");
            if ($stmt->execute([$vendor_id])) {
                $pending_orders = $stmt->fetchColumn();
            }
        }
        
        // Produits récents
        $stmt = $pdo->prepare("SELECT * FROM products WHERE vendor_id = ? ORDER BY created_at DESC LIMIT 5");
        if ($stmt->execute([$vendor_id])) {
            $recent_products = $stmt->fetchAll();
        }
        
        // Ventes récentes - only if all required tables exist
        $usersExists = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount() > 0;
        $orderItemsExists = $pdo->query("SHOW TABLES LIKE 'order_items'")->rowCount() > 0;
        
        if ($transactionsExists && $usersExists && $ordersExists && $orderItemsExists) {
            $stmt = $pdo->prepare("
                SELECT t.*, u.fullname as buyer_name, p.name as product_name
                FROM transactions t
                JOIN users u ON t.student_id = u.id
                JOIN order_items oi ON t.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE t.vendor_id = ? AND t.type = 'product_purchase'
                ORDER BY t.created_at DESC LIMIT 10
            ");
            if ($stmt->execute([$vendor_id])) {
                $recent_sales = $stmt->fetchAll();
            }
        }
    }
} catch (PDOException $e) {
    // Log error for debugging
    error_log("Database error in vendor dashboard: " . $e->getMessage());
    // Continue with default values
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('dashboard') ?> <?= __('vendor_space') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: #00796b;
            color: white;
            position: fixed;
            padding: 2rem 1rem;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.4rem;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card h3 {
            color: #00796b;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section h3 {
            margin-bottom: 1rem;
            color: #00796b;
        }

        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .product-card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }

        .product-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 0.5rem;
        }

        .product-card h4 {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .product-card .price {
            color: #00796b;
            font-weight: bold;
        }

        .sales-table {
            width: 100%;
            border-collapse: collapse;
        }

        .sales-table th,
        .sales-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .sales-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #00796b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background: #00695c;
        }

        .status-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
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
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 <?= __('vendor_space') ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="products.php"><i class="fas fa-box"></i> <?= __('my_products') ?></a>
        <a href="add_product.php"><i class="fas fa-plus"></i> <?= __('add_product') ?></a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> <?= __('payments') ?></a>
        <a href="profile.php"><i class="fas fa-user"></i> <?= __('my_profile') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
    </div>

    <div class="main-content">
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1><?= __('dashboard') ?> <?= __('vendor_space') ?></h1>
                    <p><?= __('welcome') ?>, <?= htmlspecialchars($_SESSION['full_name'] ?? __('vendor_space')) ?> !</p>
                </div>
                <div>
                    <?php include '../includes/language_switcher.php'; ?>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-box"></i> <?= __('products') ?></h3>
                <div class="value"><?= $total_products ?></div>
                <p><?= __('total_in_shop') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-shopping-cart"></i> <?= __('sales') ?></h3>
                <div class="value"><?= $total_sales ?></div>
                <p><?= __('completed_transactions') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-money-bill-wave"></i> <?= __('earnings') ?></h3>
                <div class="value"><?= number_format($total_earnings, 2) ?> <?= __('currency') ?></div>
                <p><?= __('total_revenue') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> <?= __('pending') ?></h3>
                <div class="value"><?= $pending_orders ?></div>
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
                <div class="product-card">
                    <?php if ($product['image_url']): ?>
                        <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <div style="width: 100%; height: 120px; background: #f0f0f0; border-radius: 5px; display: flex; align-items: center; justify-content: center; margin-bottom: 0.5rem;">
                            <i class="fas fa-image" style="font-size: 2rem; color: #ccc;"></i>
                        </div>
                    <?php endif; ?>
                    <h4><?= htmlspecialchars($product['name']) ?></h4>
                    <div class="price"><?= number_format($product['price'], 2) ?> GHS</div>
                    <div class="status-badge <?= $product['status'] === 'active' ? 'status-completed' : 'status-pending' ?>">
                        <?= ucfirst($product['status']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="margin-top: 1rem;">
                <a href="products.php" class="btn"><?= __('view_all_products') ?></a>
            </div>
            <?php endif; ?>
        </div>

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
                        <td><?= htmlspecialchars($sale['product_name']) ?></td>
                        <td><?= htmlspecialchars($sale['buyer_name']) ?></td>
                        <td><?= number_format($sale['amount'], 2) ?> GHS</td>
                        <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                        <td>
                            <span class="status-badge <?= $sale['status'] === 'completed' ? 'status-completed' : 'status-pending' ?>">
                                <?= ucfirst($sale['status']) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 1rem;">
                <a href="earnings.php" class="btn">Voir tous les gains</a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
