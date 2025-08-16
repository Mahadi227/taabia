<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$orders = [];
$total_orders = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT o.id, o.ordered_at AS order_date, o.total_amount, o.status, u.full_name, u.email 
          FROM orders o 
          JOIN users u ON o.buyer_id = u.id 
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR o.id LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $query .= " AND o.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(o.ordered_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(o.ordered_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = str_replace("SELECT o.id, o.ordered_at AS order_date, o.total_amount, o.status, u.full_name, u.email", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_orders = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/orders.php count: " . $e->getMessage());
}

$total_pages = ceil($total_orders / $limit);

// Get orders with pagination
$query .= " ORDER BY o.ordered_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $orders = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/orders.php: " . $e->getMessage());
}

// Calculate statistics
$total_revenue = 0;
$pending_orders = 0;
$completed_orders = 0;
$cancelled_orders = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        SUM(total_amount) as total_revenue,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
        FROM orders");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_revenue = $stats['total_revenue'] ?? 0;
        $pending_orders = $stats['pending_count'] ?? 0;
        $completed_orders = $stats['completed_count'] ?? 0;
        $cancelled_orders = $stats['cancelled_count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/orders.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes | Admin | TaaBia</title>
    
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
                <a href="products.php" class="nav-link">
                    <i class="fas fa-box"></i>
                    <span>Produits</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="orders.php" class="nav-link active">
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
                <h1 class="page-title">Gestion des Commandes</h1>
                
                <div class="header-actions">
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
                               placeholder="Client, email ou ID commande..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminée</option>
                            <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Date de début</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Date de fin</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filtrer
                        </button>
                        <a href="orders.php" class="btn btn-secondary">
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
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_orders) ?></div>
                            <div class="stat-label">Total Commandes</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format($total_revenue, 2) ?></div>
                            <div class="stat-label">Revenus Totaux</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($pending_orders) ?></div>
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
                            <div class="stat-value"><?= number_format($completed_orders) ?></div>
                            <div class="stat-label">Terminées</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Commandes</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_orders ?> commandes</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID Commande</th>
                                <th>Client</th>
                                <th>Email</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-shopping-cart" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucune commande trouvée</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                #<?= $order['id'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($order['full_name']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= htmlspecialchars($order['email']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($order['order_date'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($order['order_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                GHS<?= number_format($order['total_amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status_labels = [
                                                'pending' => ['En attente', 'badge-warning'],
                                                'completed' => ['Terminée', 'badge-success'],
                                                'cancelled' => ['Annulée', 'badge-danger']
                                            ];
                                            $status_info = $status_labels[$order['status']] ?? ['Inconnu', 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="order_view.php?id=<?= $order['id'] ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Voir détails">
                                                    <i class="fas fa-eye"></i>
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
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
