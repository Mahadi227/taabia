<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$earnings = [];
$total_earnings = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT e.*, u.full_name AS instructor_name, c.title AS course_title 
          FROM earnings e
          JOIN users u ON e.instructor_id = u.id
          JOIN courses c ON e.course_id = c.id
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (u.full_name LIKE ? OR c.title LIKE ? OR e.id LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['instructor'])) {
    $query .= " AND e.instructor_id = ?";
    $params[] = $_GET['instructor'];
}

if (!empty($_GET['date_from'])) {
    $query .= " AND DATE(e.created_at) >= ?";
    $params[] = $_GET['date_from'];
}

if (!empty($_GET['date_to'])) {
    $query .= " AND DATE(e.created_at) <= ?";
    $params[] = $_GET['date_to'];
}

// Get total count for pagination
$count_query = str_replace("SELECT e.*, u.full_name AS instructor_name, c.title AS course_title", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_earnings = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php count: " . $e->getMessage());
}

$total_pages = ceil($total_earnings / $limit);

// Get earnings with pagination
$query .= " ORDER BY e.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $earnings = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php: " . $e->getMessage());
}

// Calculate statistics
$total_amount = 0;
$total_instructors = 0;
$total_courses = 0;
$average_earning = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        SUM(amount) as total_amount,
        COUNT(DISTINCT instructor_id) as total_instructors,
        COUNT(DISTINCT course_id) as total_courses,
        AVG(amount) as average_earning
        FROM earnings");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $total_amount = $stats['total_amount'] ?? 0;
        $total_instructors = $stats['total_instructors'] ?? 0;
        $total_courses = $stats['total_courses'] ?? 0;
        $average_earning = $stats['average_earning'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php stats: " . $e->getMessage());
}

// Get instructors for filter dropdown
$instructors = [];
try {
    $instructors_stmt = $pdo->query("SELECT DISTINCT u.id, u.full_name 
                                     FROM users u 
                                     JOIN earnings e ON u.id = e.instructor_id 
                                     WHERE u.role = 'instructor' 
                                     ORDER BY u.full_name");
    if ($instructors_stmt->execute()) {
        $instructors = $instructors_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/earnings.php instructors: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revenus | Admin | TaaBia</title>
    
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
                <a href="payout_requests.php" class="nav-link">
                    <i class="fas fa-hand-holding-usd"></i>
                    <span>Demandes de paiement</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="earnings.php" class="nav-link active">
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
                <h1 class="page-title">Gestion des Revenus</h1>
                
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
                               placeholder="Formateur, cours ou ID..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Formateur</label>
                        <select name="instructor" class="form-control">
                            <option value="">Tous les formateurs</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= $instructor['id'] ?>" 
                                        <?= ($_GET['instructor'] ?? '') == $instructor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($instructor['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
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
                        <a href="earnings.php" class="btn btn-secondary">
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
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_earnings) ?></div>
                            <div class="stat-label">Total Revenus</div>
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
                        <div class="stat-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_instructors) ?></div>
                            <div class="stat-label">Formateurs</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_courses) ?></div>
                            <div class="stat-label">Cours</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Earnings Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Revenus par Formateur</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_earnings ?> revenus</span>
                        <span class="badge badge-success">GHS<?= number_format($average_earning, 2) ?> moyenne</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Formateur</th>
                                <th>Cours</th>
                                <th>Montant</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($earnings)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-wallet" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucun revenu trouvé</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($earnings as $index => $earning): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                #<?= $earning['id'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($earning['instructor_name']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($earning['course_title']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                $<?= number_format($earning['amount'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($earning['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($earning['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="view_course.php?id=<?= $earning['course_id'] ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Voir détail du cours">
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>&date_from=<?= htmlspecialchars($_GET['date_from'] ?? '') ?>&date_to=<?= htmlspecialchars($_GET['date_to'] ?? '') ?>" 
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