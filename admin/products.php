<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$products = [];
$total_products = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT p.id, p.name, p.description, p.price, p.status, p.created_at, u.full_name AS vendor 
          FROM products p 
          LEFT JOIN users u ON p.vendor_id = u.id 
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $query .= " AND p.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['vendor'])) {
    $query .= " AND p.vendor_id = ?";
    $params[] = $_GET['vendor'];
}

// Get total count for pagination
$count_query = str_replace("SELECT p.id, p.name, p.description, p.price, p.status, p.created_at, u.full_name AS vendor", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_products = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/products.php count: " . $e->getMessage());
}

$total_pages = ceil($total_products / $limit);

// Get products with pagination
$query .= " ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $products = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/products.php: " . $e->getMessage());
}

// Get vendors for filter
$vendors = [];
try {
    $vendor_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'vendor' ORDER BY full_name");
    if ($vendor_stmt->execute()) {
        $vendors = $vendor_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/products.php vendors: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits | Admin | TaaBia</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
            <p><?php
                $current_user = null;
                try {
                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([current_user_id()]);
                    $current_user = $stmt->fetch();
                } catch (PDOException $e) {
                    error_log("Error fetching current user: " . $e->getMessage());
                }
                echo htmlspecialchars($current_user['full_name'] ?? 'Administrateur');
            ?></p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Tableau de bord</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span>Formations</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="products.php" class="nav-link active">
                    <i class="fas fa-box"></i>
                    <span>Produits</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Commandes</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Événements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="blog_posts.php" class="nav-link">
                    <i class="fas fa-newspaper"></i>
                    <span>Articles de Blog</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="transactions.php" class="nav-link">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transactions</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="payout_requests.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Demandes de paiement</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="earnings.php" class="nav-link">
                    <i class="fas fa-wallet"></i>
                    <span>Revenus</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="payments.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Paiements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="payment_stats.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    <span>Statistiques</span>
                </a>
            </div>
            
            <div class="nav-item" style="margin-top: 2rem;">
                <a href="../auth/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <h1 class="page-title">Gestion des Produits</h1>
                
                <div class="header-actions">
                    <a href="add_product.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Ajouter un produit
                    </a>
                    
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 0.875rem;">Administrateur</div>
                            <div style="font-size: 0.75rem; opacity: 0.7;">Admin Panel</div>
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
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Nom, description ou vendeur..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Vendeur</label>
                        <select name="vendor" class="form-control">
                            <option value="">Tous les vendeurs</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" 
                                        <?= ($_GET['vendor'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vendor['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filtrer
                        </button>
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Réinitialiser
                        </a>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon products">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_products) ?></div>
                            <div class="stat-label">Total Produits</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon vendors">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count($vendors)) ?></div>
                            <div class="stat-label">Vendeurs</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($products, 'status'))['active'] ?? 0) ?></div>
                            <div class="stat-label">Produits Actifs</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format(array_sum(array_column($products, 'price')), 2) ?></div>
                            <div class="stat-label">Valeur Totale</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Produits</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_products ?> produits</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Vendeur</th>
                                <th>Prix</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-box" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucun produit trouvé</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($product['name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <?= htmlspecialchars(substr($product['description'], 0, 100)) ?>...
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($product['vendor'] ?? 'Non assigné') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                GHS<?= number_format($product['price'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($product['status'] === 'active'): ?>
                                                <span class="badge badge-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($product['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($product['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="view_product.php?id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-secondary" 
                                                   title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="product_edit.php?id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <a href="product_toggle.php?id=<?= $product['id'] ?>&action=<?= $product['status'] === 'active' ? 'deactivate' : 'activate' ?>" 
                                                   class="btn btn-sm <?= $product['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>"
                                                   title="<?= $product['status'] === 'active' ? 'Désactiver' : 'Activer' ?>"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir <?= $product['status'] === 'active' ? 'désactiver' : 'activer' ?> ce produit ?')">
                                                    <i class="fas <?= $product['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                                                </a>
                                                
                                                <a href="product_delete.php?id=<?= $product['id'] ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')">
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&vendor=<?= htmlspecialchars($_GET['vendor'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&vendor=<?= htmlspecialchars($_GET['vendor'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&vendor=<?= htmlspecialchars($_GET['vendor'] ?? '') ?>" 
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
    </script>
</body>
</html>