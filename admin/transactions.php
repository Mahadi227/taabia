<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions | Admin | TaaBia</title>
    
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
                <a href="transactions.php" class="nav-link active">
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
                <h1 class="page-title">Gestion des Transactions</h1>
                
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
                               placeholder="Utilisateur, email, référence..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="success" <?= ($_GET['status'] ?? '') === 'success' ? 'selected' : '' ?>>Réussi</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="failed" <?= ($_GET['status'] ?? '') === 'failed' ? 'selected' : '' ?>>Échoué</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Méthode</label>
                        <select name="method" class="form-control">
                            <option value="">Toutes les méthodes</option>
                            <option value="card" <?= ($_GET['method'] ?? '') === 'card' ? 'selected' : '' ?>>Carte</option>
                            <option value="bank_transfer" <?= ($_GET['method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>>Virement</option>
                            <option value="mobile_money" <?= ($_GET['method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>>Mobile Money</option>
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
                        <a href="transactions.php" class="btn btn-secondary">
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
                        <div class="stat-icon revenue">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_transactions) ?></div>
                            <div class="stat-label">Total Transactions</div>
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
                            <div class="stat-label">Montant Total</div>
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
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID Transaction</th>
                                <th>Utilisateur</th>
                                <th>Montant</th>
                                <th>Méthode</th>
                                <th>Statut</th>
                                <th>Référence</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="8" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-exchange-alt" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucune transaction trouvée</p>
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
                                            <div style="font-weight: 500;"><?= htmlspecialchars($tx['full_name'] ?? 'Utilisateur #' . $tx['user_id']) ?></div>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= htmlspecialchars($tx['email'] ?? 'N/A') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                <?= number_format($tx['amount'], 2) ?> <?= htmlspecialchars($tx['currency'] ?? 'USD') ?>
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
                                                'success' => ['Réussi', 'badge-success'],
                                                'pending' => ['En attente', 'badge-warning'],
                                                'failed' => ['Échoué', 'badge-danger']
                                            ];
                                            $status_info = $status_labels[$tx['payment_status']] ?? ['Inconnu', 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); font-family: monospace;">
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
                                                   class="btn btn-sm btn-warning" 
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete_transaction.php?id=<?= $tx['id'] ?>" 
                                                   class="btn btn-sm btn-danger" 
                                                   title="Supprimer"
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
    </script>
</body>
</html>