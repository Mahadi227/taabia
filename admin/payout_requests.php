<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

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
            $_SESSION['success_message'] = "Demande de paiement mise à jour avec succès.";
        } catch (PDOException $e) {
            error_log("Database error in admin/payout_requests.php update: " . $e->getMessage());
            $_SESSION['error_message'] = "Erreur lors de la mise à jour de la demande.";
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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demandes de Paiement | Admin | TaaBia</title>
    
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
                <a href="transactions.php" class="nav-link">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transactions</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="payout_requests.php" class="nav-link active">
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
                <h1 class="page-title">Demandes de Paiement</h1>
                
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
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Utilisateur, ID ou notes..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= ($_GET['status'] ?? '') === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="approved" <?= ($_GET['status'] ?? '') === 'approved' ? 'selected' : '' ?>>Approuvé</option>
                            <option value="rejected" <?= ($_GET['status'] ?? '') === 'rejected' ? 'selected' : '' ?>>Rejeté</option>
                            <option value="paid" <?= ($_GET['status'] ?? '') === 'paid' ? 'selected' : '' ?>>Payé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Rôle</label>
                        <select name="role" class="form-control">
                            <option value="">Tous les rôles</option>
                            <option value="instructor" <?= ($_GET['role'] ?? '') === 'instructor' ? 'selected' : '' ?>>Formateur</option>
                            <option value="vendor" <?= ($_GET['role'] ?? '') === 'vendor' ? 'selected' : '' ?>>Vendeur</option>
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
                        <a href="payout_requests.php" class="btn btn-secondary">
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
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_requests) ?></div>
                            <div class="stat-label">Total Demandes</div>
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
                                <th>ID</th>
                                <th>Utilisateur</th>
                                <th>Rôle</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Demandé le</th>
                                <th>Payé le</th>
                                <th>Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($requests)): ?>
                                <tr>
                                    <td colspan="9" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-hand-holding-usd" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucune demande trouvée</p>
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
                                                'pending' => ['En attente', 'badge-warning'],
                                                'approved' => ['Approuvé', 'badge-info'],
                                                'rejected' => ['Rejeté', 'badge-danger'],
                                                'paid' => ['Payé', 'badge-success']
                                            ];
                                            $status_info = $status_labels[$req['status']] ?? ['Inconnu', 'badge-secondary'];
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
                                                                title="Approuver"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir approuver cette demande ?')">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                                        <button type="submit" name="action" value="rejected" 
                                                                class="btn btn-sm btn-danger" 
                                                                title="Rejeter"
                                                                onclick="return confirm('Êtes-vous sûr de vouloir rejeter cette demande ?')">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($req['status'] === 'approved'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                                    <button type="submit" name="action" value="paid" 
                                                            class="btn btn-sm btn-primary" 
                                                            title="Marquer comme payé"
                                                            onclick="return confirm('Êtes-vous sûr de vouloir marquer cette demande comme payée ?')">
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
    </script>
</body>
</html>