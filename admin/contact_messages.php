<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$messages = [];
$total_messages = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT * FROM contact_messages WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR message LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(created_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_messages = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/contact_messages.php count: " . $e->getMessage());
}

$total_pages = ceil($total_messages / $limit);

// Get messages with pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $messages = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/contact_messages.php: " . $e->getMessage());
}

// Calculate statistics
$today_messages = 0;
$week_messages = 0;
$month_messages = 0;
$total_messages_count = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        COUNT(*) as total_count,
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_count
        FROM contact_messages");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_messages_count = $stats['total_count'] ?? 0;
        $today_messages = $stats['today_count'] ?? 0;
        $week_messages = $stats['week_count'] ?? 0;
        $month_messages = $stats['month_count'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/contact_messages.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages de Contact | Admin | TaaBia</title>
    
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
                <a href="contact_messages.php" class="nav-link active">
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
                <h1 class="page-title">Messages de Contact</h1>
                
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
                               placeholder="Nom, email ou message..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
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
                        <a href="contact_messages.php" class="btn btn-secondary">
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
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_messages_count) ?></div>
                            <div class="stat-label">Total Messages</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($today_messages) ?></div>
                            <div class="stat-label">Aujourd'hui</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($week_messages) ?></div>
                            <div class="stat-label">Cette semaine</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon courses">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($month_messages) ?></div>
                            <div class="stat-label">Ce mois</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Messages Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Messages de Contact</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_messages ?> messages</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Email</th>
                                <th>Message</th>
                                <th>Reçu le</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($messages)): ?>
                                <tr>
                                    <td colspan="5" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-envelope" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucun message trouvé</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($msg['name']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= htmlspecialchars($msg['email']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary); max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                <?= htmlspecialchars(substr($msg['message'], 0, 100)) ?>...
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light); margin-top: 0.25rem;">
                                                <button type="button" class="btn btn-sm btn-secondary" 
                                                        onclick="showMessage('<?= htmlspecialchars($msg['name']) ?>', '<?= htmlspecialchars($msg['email']) ?>', '<?= htmlspecialchars(str_replace("'", "\\'", $msg['message'])) ?>')">
                                                    Voir le message complet
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($msg['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="mailto:<?= htmlspecialchars($msg['email']) ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Répondre par email">
                                                    <i class="fas fa-reply"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-info" 
                                                        title="Voir le message complet"
                                                        onclick="showMessage('<?= htmlspecialchars($msg['name']) ?>', '<?= htmlspecialchars($msg['email']) ?>', '<?= htmlspecialchars(str_replace("'", "\\'", $msg['message'])) ?>')">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Message Modal -->
    <div id="messageModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
        <div class="modal-content" style="background-color: var(--bg-primary); margin: 5% auto; padding: var(--spacing-xl); border-radius: var(--border-radius); width: 80%; max-width: 600px; box-shadow: var(--shadow-heavy);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg);">
                <h3 id="modalTitle" style="margin: 0; color: var(--text-primary);">Message de Contact</h3>
                <span class="close" onclick="closeModal()" style="color: var(--text-secondary); font-size: var(--font-size-2xl); font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: var(--spacing-md);">
                    <strong>De:</strong> <span id="modalName"></span>
                </div>
                <div style="margin-bottom: var(--spacing-md);">
                    <strong>Email:</strong> <span id="modalEmail"></span>
                </div>
                <div style="margin-bottom: var(--spacing-md);">
                    <strong>Message:</strong>
                </div>
                <div id="modalMessage" style="background: var(--bg-secondary); padding: var(--spacing-md); border-radius: var(--border-radius-sm); white-space: pre-wrap; line-height: 1.6;"></div>
            </div>
            <div class="modal-footer" style="margin-top: var(--spacing-lg); text-align: right;">
                <a id="modalReply" href="#" class="btn btn-primary">
                    <i class="fas fa-reply"></i>
                    Répondre
                </a>
                <button onclick="closeModal()" class="btn btn-secondary">
                    Fermer
                </button>
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

        // Modal functions
        function showMessage(name, email, message) {
            document.getElementById('modalTitle').textContent = 'Message de ' + name;
            document.getElementById('modalName').textContent = name;
            document.getElementById('modalEmail').textContent = email;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('modalReply').href = 'mailto:' + email;
            document.getElementById('messageModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('messageModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('messageModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
