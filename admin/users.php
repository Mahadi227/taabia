<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$users = [];
$total_users = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT id, full_name, email, role, is_active, created_at FROM users WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['role'])) {
    $query .= " AND role = ?";
    $params[] = $_GET['role'];
}

if (!empty($_GET['status'])) {
    $query .= " AND is_active = ?";
    $params[] = ($_GET['status'] === 'active') ? 1 : 0;
}

// Get total count for pagination
$count_query = str_replace("SELECT id, full_name, email, role, is_active, created_at", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_users = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/users.php count: " . $e->getMessage());
}

$total_pages = ceil($total_users / $limit);

// Get users with pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $users = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/users.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Utilisateurs | Admin | TaaBia</title>
    
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
                <a href="users.php" class="nav-link active">
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
                <h1 class="page-title">Gestion des Utilisateurs</h1>
                
                <div class="header-actions">
                    <a href="add_user.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Ajouter un utilisateur
                    </a>
                    <div>
                                 <div style="font-weight: 600; font-size: 0.875rem;">
                                     <?= htmlspecialchars($current_user['full_name'] ?? 'Admin') ?>
                                 </div>
                                 <div style="font-size: 0.75rem; opacity: 0.7;">
                                     <?= htmlspecialchars($current_user['email'] ?? 'admin@taabia.com') ?>
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
                               placeholder="Nom ou email..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Rôle</label>
                        <select name="role" class="form-control">
                            <option value="">Tous les rôles</option>
                            <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="instructor" <?= ($_GET['role'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructeur</option>
                            <option value="student" <?= ($_GET['role'] ?? '') === 'student' ? 'selected' : '' ?>>Étudiant</option>
                            <option value="vendor" <?= ($_GET['role'] ?? '') === 'vendor' ? 'selected' : '' ?>>Vendeur</option>
                        </select>
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
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filtrer
                        </button>
                        <a href="users.php" class="btn btn-secondary">
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
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_users) ?></div>
                            <div class="stat-label">Total Utilisateurs</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($users, 'role'))['student'] ?? 0) ?></div>
                            <div class="stat-label">Étudiants</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($users, 'role'))['instructor'] ?? 0) ?></div>
                            <div class="stat-label">Instructeurs</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon vendors">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($users, 'role'))['vendor'] ?? 0) ?></div>
                            <div class="stat-label">Vendeurs</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Utilisateurs</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_users ?> utilisateurs</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Email</th>
                                <th>Rôle</th>
                                <th>Statut</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-users" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucun utilisateur trouvé</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($user['full_name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        ID: <?= $user['id'] ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($user['email']) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $role_labels = [
                                                'admin' => ['Admin', 'badge-danger'],
                                                'instructor' => ['Instructeur', 'badge-warning'],
                                                'student' => ['Étudiant', 'badge-success'],
                                                'vendor' => ['Vendeur', 'badge-info']
                                            ];
                                            $role_info = $role_labels[$user['role']] ?? ['Inconnu', 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $role_info[1] ?>"><?= $role_info[0] ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['is_active'] == 1): ?>
                                                <span class="badge badge-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($user['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="user_edit.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-secondary" 
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <a href="user_toggle.php?id=<?= $user['id'] ?>&action=<?= $user['is_active'] == 1 ? 'deactivate' : 'activate' ?>" 
                                                   class="btn btn-sm <?= $user['is_active'] == 1 ? 'btn-warning' : 'btn-success' ?>"
                                                   title="<?= $user['is_active'] == 1 ? 'Désactiver' : 'Activer' ?>"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir <?= $user['is_active'] == 1 ? 'désactiver' : 'activer' ?> cet utilisateur ?')">
                                                    <i class="fas <?= $user['is_active'] == 1 ? 'fa-ban' : 'fa-check' ?>"></i>
                                                </a>
                                                
                                                <a href="user_delete.php?id=<?= $user['id'] ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')">
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&role=<?= htmlspecialchars($_GET['role'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>" 
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