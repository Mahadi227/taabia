<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Initialize variables with default values
$total_products = 0;
$total_pages = 1;
$products = [];

try {
    // Check if products table exists
    $table_exists = $pdo->query("SHOW TABLES LIKE 'products'")->rowCount() > 0;
    
    if ($table_exists) {
        // Get total products count
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
        if ($count_stmt->execute([$vendor_id])) {
            $total_products = $count_stmt->fetchColumn();
        }
        $total_pages = ceil($total_products / $limit);

        // Get products with pagination
        $stmt = $pdo->prepare("
            SELECT * FROM products 
            WHERE vendor_id = ? 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ");
        if ($stmt->execute([$vendor_id, $limit, $offset])) {
            $products = $stmt->fetchAll();
        }
    }
} catch (PDOException $e) {
    // Handle database errors gracefully
    error_log("Database error in vendor/products.php: " . $e->getMessage());
    $total_products = 0;
    $total_pages = 1;
    $products = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Produits | TaaBia</title>
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

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #00796b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: background-color 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #00695c;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-success:hover {
            background: #218838;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .product-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-3px);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .product-price {
            font-size: 1.1rem;
            font-weight: bold;
            color: #00796b;
            margin-bottom: 0.5rem;
        }

        .product-category {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .product-status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .status-out_of_stock {
            background: #fff3cd;
            color: #856404;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
            .products-grid {
                grid-template-columns: 1fr;
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
            <h1>Mes Produits</h1>
            <a href="add_product.php" class="btn"><i class="fas fa-plus"></i> Ajouter un produit</a>
        </div>

        <?php if (empty($products)): ?>
        <div class="empty-state">
            <i class="fas fa-box-open"></i>
            <h3>Aucun produit trouvé</h3>
            <p>Vous n'avez pas encore ajouté de produits à votre boutique.</p>
            <a href="add_product.php" class="btn">Ajouter votre premier produit</a>
        </div>
        <?php else: ?>
        <div class="products-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <?php if ($product['image_url']): ?>
                    <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image">
                <?php else: ?>
                    <div style="width: 100%; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-image" style="font-size: 3rem; color: #ccc;"></i>
                    </div>
                <?php endif; ?>
                
                <div class="product-info">
                    <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                    <div class="product-price"><?= number_format($product['price'], 2) ?> GHS</div>
                    <div class="product-category"><?= htmlspecialchars($product['category'] ?? 'Non catégorisé') ?></div>
                    
                    <div class="product-status status-<?= $product['status'] ?>">
                        <?= ucfirst($product['status']) ?>
                    </div>
                    
                    <div class="product-actions">
                        <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-success">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        
                        <?php if ($product['status'] === 'active'): ?>
                            <a href="toggle_product.php?id=<?= $product['id'] ?>&action=deactivate" class="btn btn-danger">
                                <i class="fas fa-pause"></i> Désactiver
                            </a>
                        <?php else: ?>
                            <a href="toggle_product.php?id=<?= $product['id'] ?>&action=activate" class="btn btn-success">
                                <i class="fas fa-play"></i> Activer
                            </a>
                        <?php endif; ?>
                        
                        <a href="delete_product.php?id=<?= $product['id'] ?>" class="btn btn-danger" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

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
</body>
</html>