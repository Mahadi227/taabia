<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

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

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected_products = $_POST['selected_products'] ?? [];
    $action = $_POST['bulk_action'];
    
    if (!empty($selected_products)) {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE products SET status = 'active' WHERE id IN (" . str_repeat('?,', count($selected_products) - 1) . "?) AND vendor_id = ?");
                    $params = array_merge($selected_products, [$vendor_id]);
                    $stmt->execute($params);
                    $success_message = count($selected_products) . " produit(s) activé(s) avec succès.";
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE products SET status = 'inactive' WHERE id IN (" . str_repeat('?,', count($selected_products) - 1) . "?) AND vendor_id = ?");
                    $params = array_merge($selected_products, [$vendor_id]);
                    $stmt->execute($params);
                    $success_message = count($selected_products) . " produit(s) désactivé(s) avec succès.";
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM products WHERE id IN (" . str_repeat('?,', count($selected_products) - 1) . "?) AND vendor_id = ?");
                    $params = array_merge($selected_products, [$vendor_id]);
                    $stmt->execute($params);
                    $success_message = count($selected_products) . " produit(s) supprimé(s) avec succès.";
                    break;
            }
        } catch (PDOException $e) {
            $error_message = "Erreur lors de l'action en masse: " . $e->getMessage();
        }
    }
}

// Search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$sort_by = $_GET['sort'] ?? 'created_at';
$sort_order = $_GET['order'] ?? 'DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 12;
$offset = ($page - 1) * $limit;

// Initialize variables
$total_products = 0;
$total_pages = 1;
$products = [];
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'out_of_stock' => 0,
    'low_stock' => 0
];

try {
    // Build WHERE clause
    $where_conditions = ["vendor_id = ?"];
    $params = [$vendor_id];
    
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN status = 'out_of_stock' THEN 1 ELSE 0 END) as out_of_stock,
            SUM(CASE WHEN stock_quantity <= 5 AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock
        FROM products 
        WHERE vendor_id = ?
    ");
    $stats_stmt->execute([$vendor_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get total count for pagination
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE $where_clause");
    $count_stmt->execute($params);
            $total_products = $count_stmt->fetchColumn();
        $total_pages = ceil($total_products / $limit);

    // Get products with search, filter, and pagination
    $order_clause = "ORDER BY $sort_by $sort_order";
        $stmt = $pdo->prepare("
        SELECT id, name, price, image_url, status, stock_quantity, created_at, 
               (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = products.id) as sales_count
        FROM products 
        WHERE $where_clause 
        $order_clause
            LIMIT ? OFFSET ?
        ");
    
    $query_params = array_merge($params, [$limit, $offset]);
    $stmt->execute($query_params);
            $products = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error in vendor/products.php: " . $e->getMessage());
    $error_message = "Une erreur de base de données s'est produite.";
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_products') ?> | TaaBia</title>
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #00796b;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #00796b;
            margin-bottom: 0.5rem;
        }

        .stat-card .label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .filters-section {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: #00796b;
            box-shadow: 0 0 0 3px rgba(0, 119, 107, 0.1);
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

        .btn-danger {
            background: #dc2626;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-success {
            background: #059669;
        }

        .btn-success:hover {
            background: #047857;
        }

        .btn-warning {
            background: #d97706;
        }

        .btn-warning:hover {
            background: #b45309;
        }

        .bulk-actions {
            background: white;
            padding: 1rem 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #00796b;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
            border-color: #00796b;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-placeholder {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e1 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .product-placeholder i {
            font-size: 3rem;
            color: #94a3b8;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
            line-height: 1.4;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #00796b;
            margin-bottom: 0.5rem;
        }

        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .product-status {
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

        .status-out_of_stock {
            background: #fef3c7;
            color: #92400e;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .product-actions .btn {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            text-decoration: none;
            color: #374151;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #f3f4f6;
            border-color: #00796b;
        }

        .pagination .active {
            background: #00796b;
            color: white;
            border-color: #00796b;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .empty-state p {
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .stock-warning {
            color: #d97706;
            font-weight: 500;
        }

        .stock-danger {
            color: #dc2626;
            font-weight: 500;
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
            .products-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .filters-grid {
                grid-template-columns: 1fr;
            }
            .products-grid {
                grid-template-columns: 1fr;
            }
            .bulk-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 <?= __('vendor_space') ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="products.php" class="active"><i class="fas fa-box"></i> <?= __('my_products') ?></a>
        <a href="add_product.php"><i class="fas fa-plus"></i> <?= __('add_product') ?></a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> <?= __('payments') ?></a>
        <a href="profile.php"><i class="fas fa-user"></i> <?= __('my_profile') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
    </div>

    <div class="main-content">
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="header">
            <div>
                <h1><?= __('my_products') ?></h1>
                <p>Gérez vos produits et suivez leurs performances</p>
            </div>
            <a href="add_product.php" class="btn">
                <i class="fas fa-plus"></i> <?= __('add_product') ?>
            </a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="value"><?= $stats['total'] ?></div>
                <div class="label">Total Produits</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['active'] ?></div>
                <div class="label">Actifs</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['inactive'] ?></div>
                <div class="label">Inactifs</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $stats['low_stock'] ?></div>
                <div class="label">Stock Faible</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" class="filters-grid">
                <div class="form-group">
                    <label for="search">Rechercher</label>
                    <input type="text" id="search" name="search" class="form-control" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Nom du produit...">
                </div>
                
                <div class="form-group">
                    <label for="status">Statut</label>
                    <select id="status" name="status" class="form-control">
                        <option value="">Tous les statuts</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Actif</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                        <option value="out_of_stock" <?= $status_filter === 'out_of_stock' ? 'selected' : '' ?>>Rupture de stock</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sort">Trier par</label>
                    <select id="sort" name="sort" class="form-control">
                        <option value="created_at" <?= $sort_by === 'created_at' ? 'selected' : '' ?>>Date de création</option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Nom</option>
                        <option value="price" <?= $sort_by === 'price' ? 'selected' : '' ?>>Prix</option>
                        <option value="stock" <?= $sort_by === 'stock' ? 'selected' : '' ?>>Stock</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="order">Ordre</label>
                    <select id="order" name="order" class="form-control">
                        <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>Décroissant</option>
                        <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>Croissant</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>

        <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3><?= __('no_products_found') ?></h3>
            <p><?= __('no_products_desc') ?></p>
            <a href="add_product.php" class="btn"><?= __('add_first_product') ?></a>
        </div>
        <?php else: ?>
        
        <!-- Bulk Actions -->
        <form method="POST" id="bulk-form">
            <div class="bulk-actions">
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="select-all">
                    <label for="select-all">Sélectionner tout</label>
                </div>
                
                <select name="bulk_action" class="form-control" style="max-width: 200px;">
                    <option value="">Actions en masse</option>
                    <option value="activate">Activer</option>
                    <option value="deactivate">Désactiver</option>
                    <option value="delete">Supprimer</option>
                </select>
                
                <button type="submit" class="btn btn-secondary" onclick="return confirm('Êtes-vous sûr de vouloir effectuer cette action ?')">
                    <i class="fas fa-play"></i> Appliquer
                </button>
            </div>

        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                    <div class="checkbox-wrapper" style="position: absolute; top: 1rem; left: 1rem; z-index: 10;">
                        <input type="checkbox" name="selected_products[]" value="<?= $product['id'] ?>" class="product-checkbox">
                    </div>
                    
                <?php if ($product['image_url']): ?>
                        <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             class="product-image">
                <?php else: ?>
                        <div class="product-placeholder">
                            <i class="fas fa-image"></i>
                    </div>
                <?php endif; ?>
                
                <div class="product-info">
                    <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                        <div class="product-price"><?= formatCurrency($product['price']) ?></div>
                        
                        <div class="product-meta">
                            <span>
                                <i class="fas fa-box"></i> 
                                Stock: 
                                <span class="<?= $product['stock_quantity'] <= 5 ? ($product['stock_quantity'] == 0 ? 'stock-danger' : 'stock-warning') : '' ?>">
                                    <?= $product['stock_quantity'] ?>
                                </span>
                            </span>
                            <span>
                                <i class="fas fa-chart-line"></i> 
                                Ventes: <?= $product['sales_count'] ?? 0 ?>
                            </span>
                        </div>
                    
                    <div class="product-status status-<?= $product['status'] ?>">
                        <?= ucfirst($product['status']) ?>
                    </div>
                    
                    <div class="product-actions">
                            <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-success" title="Modifier">
                                <i class="fas fa-edit"></i>
                        </a>
                        
                        <?php if ($product['status'] === 'active'): ?>
                                <a href="toggle_product.php?id=<?= $product['id'] ?>&action=deactivate" 
                                   class="btn btn-warning" title="Désactiver">
                                    <i class="fas fa-pause"></i>
                            </a>
                        <?php else: ?>
                                <a href="toggle_product.php?id=<?= $product['id'] ?>&action=activate" 
                                   class="btn btn-success" title="Activer">
                                    <i class="fas fa-play"></i>
                            </a>
                        <?php endif; ?>
                        
                            <a href="delete_product.php?id=<?= $product['id'] ?>" 
                               class="btn btn-danger" 
                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')"
                               title="Supprimer">
                                <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        </form>

        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                    <i class="fas fa-chevron-left"></i> Précédent
                </a>
            <?php endif; ?>
            
            <?php
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            
            if ($start_page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                <?php if ($start_page > 2): ?>
                    <span>...</span>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                   <?= $i === $page ? 'class="active"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
            
            <?php if ($end_page < $total_pages): ?>
                <?php if ($end_page < $total_pages - 1): ?>
                    <span>...</span>
                <?php endif; ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
            <?php endif; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                    Suivant <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Select all functionality
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Update select all when individual checkboxes change
        document.querySelectorAll('.product-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allCheckboxes = document.querySelectorAll('.product-checkbox');
                const checkedCheckboxes = document.querySelectorAll('.product-checkbox:checked');
                const selectAllCheckbox = document.getElementById('select-all');
                
                selectAllCheckbox.checked = allCheckboxes.length === checkedCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCheckboxes.length > 0 && checkedCheckboxes.length < allCheckboxes.length;
            });
        });

        // Auto-submit form when bulk action is selected
        document.querySelector('select[name="bulk_action"]').addEventListener('change', function() {
            if (this.value) {
                const checkedBoxes = document.querySelectorAll('.product-checkbox:checked');
                if (checkedBoxes.length === 0) {
                    alert('Veuillez sélectionner au moins un produit.');
                    this.value = '';
                    return;
                }
            }
        });

        // Enhanced product card interactions
        document.querySelectorAll('.product-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'a':
                        e.preventDefault();
                        document.getElementById('select-all').click();
                        break;
                    case 'n':
                        e.preventDefault();
                        window.location.href = 'add_product.php';
                        break;
                }
            }
        });

        // Search functionality with debounce
        let searchTimeout;
        document.getElementById('search').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                this.form.submit();
            }, 500);
        });
    </script>
</body>
</html>