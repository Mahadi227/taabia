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
        
        // Get orders with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        
        $stmt = $pdo->prepare("
            SELECT DISTINCT o.*, u.fullname as buyer_name, u.email as buyer_email
            FROM orders o 
            JOIN order_items oi ON o.id = oi.order_id 
            JOIN users u ON o.buyer_id = u.id
            WHERE oi.product_id IN (SELECT id FROM products WHERE vendor_id = ?)
            ORDER BY o.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        if ($stmt->execute([$vendor_id, $limit, $offset])) {
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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Commandes | TaaBia</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .orders-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .orders-section h3 {
            margin-bottom: 1rem;
            color: #00796b;
        }

        .orders-table {
            width: 100%;
            border-collapse: collapse;
        }

        .orders-table th,
        .orders-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .orders-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .orders-table tr:hover {
            background: #f8f9fa;
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

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
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
        <h2>🏪 Vendeur</h2>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Mes Produits</a>
        <a href="add_product.php"><i class="fas fa-plus"></i> Ajouter Produit</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> Mes Gains</a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> Paiements</a>
        <a href="profile.php"><i class="fas fa-user"></i> Mon Profil</a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Mes Commandes</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-shopping-cart"></i> Total Commandes</h3>
                <div class="value"><?= $total_orders ?></div>
                <p>Toutes les commandes</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> En Attente</h3>
                <div class="value"><?= $pending_orders ?></div>
                <p>Commandes en attente</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Livrées</h3>
                <div class="value"><?= $completed_orders ?></div>
                <p>Commandes livrées</p>
            </div>
        </div>

        <div class="orders-section">
            <h3><i class="fas fa-list"></i> Liste des Commandes</h3>
            
            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-cart"></i>
                <h4>Aucune commande trouvée</h4>
                <p>Vous n'avez pas encore reçu de commandes pour vos produits.</p>
                <a href="products.php" class="btn">Gérer mes produits</a>
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
                            <strong><?= htmlspecialchars($order['order_number']) ?></strong>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($order['buyer_name']) ?></div>
                            <small><?= htmlspecialchars($order['buyer_email']) ?></small>
                        </td>
                        <td>
                            <strong><?= number_format($order['total_amount'], 2) ?> GHS</strong>
                        </td>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $order['status'] ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-secondary">
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
                    <a href="?page=<?= $page - 1 ?>">&laquo; Précédent</a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?= $i ?>" <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Suivant &raquo;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>