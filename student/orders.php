<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    
    try {
        // Check if order belongs to student and can be cancelled
        $stmt = $pdo->prepare("
            SELECT o.*, p.name as product_name 
            FROM orders o 
            LEFT JOIN products p ON o.product_id = p.id 
            WHERE o.id = ? AND o.buyer_id = ? AND o.status = 'pending'
        ");
        $stmt->execute([$order_id, $student_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Update order status to cancelled
            $stmtUpdate = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
            $stmtUpdate->execute([$order_id]);
            
            flash_message("Commande #$order_id annulée avec succès.", 'success');
        } else {
            flash_message("Impossible d'annuler cette commande.", 'error');
        }
    } catch (PDOException $e) {
        error_log("Database error cancelling order: " . $e->getMessage());
        flash_message("Une erreur est survenue lors de l'annulation.", 'error');
    }
    
    header('Location: orders.php');
    exit;
}

// Search and filter functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Build the query with filters
$where_conditions = ["o.buyer_id = ?"];
$params = [$student_id];

if (!empty($search)) {
    $where_conditions[] = "(o.id LIKE ? OR p.name LIKE ? OR o.total_amount LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Order by clause
$order_clause = match($sort_by) {
    'recent' => 'o.ordered_at DESC',
    'oldest' => 'o.ordered_at ASC',
    'amount_high' => 'o.total_amount DESC',
    'amount_low' => 'o.total_amount ASC',
    'status' => 'o.status ASC',
    default => 'o.ordered_at DESC'
};

try {
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name, p.description as product_description,
               CASE 
                   WHEN o.status = 'completed' THEN 'success'
                   WHEN o.status = 'pending' THEN 'warning'
                   WHEN o.status = 'cancelled' THEN 'danger'
                   ELSE 'info'
               END as status_class,
               CASE 
                   WHEN o.status = 'completed' THEN 'Terminée'
                   WHEN o.status = 'pending' THEN 'En attente'
                   WHEN o.status = 'cancelled' THEN 'Annulée'
                   ELSE 'Inconnue'
               END as status_text
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE $where_clause
        ORDER BY $order_clause
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    // Get statistics
    $total_orders = count($orders);
    $completed_orders = array_filter($orders, fn($o) => $o['status'] == 'completed');
    $pending_orders = array_filter($orders, fn($o) => $o['status'] == 'pending');
    $cancelled_orders = array_filter($orders, fn($o) => $o['status'] == 'cancelled');
    
    $total_spent = array_sum(array_column($completed_orders, 'total_amount'));
    $pending_amount = array_sum(array_column($pending_orders, 'total_amount'));

} catch (PDOException $e) {
    error_log("Database error in orders: " . $e->getMessage());
    $orders = [];
    $total_orders = 0;
    $total_spent = 0;
    $pending_amount = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Achats | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p>Espace Apprenant</p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    Découvrir les cours
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
                <a href="course_lessons.php" class="student-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Mes leçons
                </a>
                <a href="orders.php" class="student-nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    Mes achats
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    Mon profil
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>Mes Achats</h1>
                        <p>Gérez vos commandes et suivez vos achats</p>
                    </div>
                    <div>
                        <a href="../public/main_site/shop.php" class="student-btn student-btn-primary">
                            <i class="fas fa-shopping-bag"></i>
                            Visiter la boutique
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="student-cards" style="margin-bottom: var(--spacing-6);">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Total des commandes</div>
                    <div class="student-card-value"><?= $total_orders ?></div>
                    <div class="student-card-description">Toutes vos commandes</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Commandes terminées</div>
                    <div class="student-card-value"><?= count($completed_orders) ?></div>
                    <div class="student-card-description">Commandes livrées</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="student-card-title">En attente</div>
                    <div class="student-card-value"><?= count($pending_orders) ?></div>
                    <div class="student-card-description">Commandes en cours</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Total dépensé</div>
                    <div class="student-card-value"><?= number_format($total_spent, 2) ?> GHS</div>
                    <div class="student-card-description">Montant total des achats</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="student-card" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6);">
                    <form method="GET" style="display: flex; gap: var(--spacing-4); align-items: end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                                Rechercher
                            </label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Rechercher par ID, produit..." 
                                   style="width: 100%; padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                                Statut
                            </label>
                            <select name="status" style="padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px; min-width: 150px;">
                                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Terminées</option>
                                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Annulées</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                                Trier par
                            </label>
                            <select name="sort" style="padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px; min-width: 150px;">
                                <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Plus anciennes</option>
                                <option value="amount_high" <?= $sort_by === 'amount_high' ? 'selected' : '' ?>>Montant élevé</option>
                                <option value="amount_low" <?= $sort_by === 'amount_low' ? 'selected' : '' ?>>Montant faible</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="student-btn student-btn-primary" style="padding: 0.75rem 1.5rem;">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Orders List -->
            <div class="student-card">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-list"></i> Liste des commandes (<?= $total_orders ?>)
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-4);">
                    <?php if (count($orders) > 0): ?>
                        <?php foreach ($orders as $order): ?>
                            <div style="display: flex; align-items: center; padding: var(--spacing-4); border: 1px solid var(--gray-200); border-radius: 8px; margin-bottom: var(--spacing-3); transition: all 0.3s ease;">
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                        <div>
                                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($order['product_name'] ?? 'Commande #' . $order['id']) ?>
                                            </div>
                                            <div style="font-size: 0.875rem; color: var(--gray-600);">
                                                Commande #<?= $order['id'] ?> • <?= date('d/m/Y H:i', strtotime($order['ordered_at'])) ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                                                <?= number_format($order['total_amount'], 2) ?> GHS
                                            </div>
                                            <span class="student-badge student-badge-<?= $order['status_class'] ?>">
                                                <?= $order['status_text'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($order['product_description'])): ?>
                                        <div style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                                            <?= htmlspecialchars(substr($order['product_description'], 0, 100)) ?>...
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; gap: var(--spacing-3);">
                                        <a href="view_order.php?id=<?= $order['id'] ?>" class="student-btn student-btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                            <i class="fas fa-eye"></i> Voir les détails
                                        </a>
                                        
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette commande ?');">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <button type="submit" name="cancel_order" class="student-btn student-btn-danger" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                    <i class="fas fa-times"></i> Annuler
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-8); color: var(--gray-500);">
                            <i class="fas fa-shopping-bag" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Aucune commande trouvée.</p>
                            <a href="../public/main_site/shop.php" class="student-btn student-btn-primary">
                                <i class="fas fa-shopping-bag"></i> Visiter la boutique
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to order cards
            const orderCards = document.querySelectorAll('.student-card > div > div > div');
            orderCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });

            // Auto-submit form when filters change
            const filterForm = document.querySelector('form');
            const filterInputs = filterForm.querySelectorAll('select');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
</body>
</html>