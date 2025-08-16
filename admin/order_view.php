<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

if (!isset($_GET['id'])) redirect('orders.php');
$order_id = (int) $_GET['id'];

try {
    // Récupération infos de la commande
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.buyer_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        redirect('orders.php');
    }

    // Récupération des articles de la commande
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in admin/order_view.php: " . $e->getMessage());
    redirect('orders.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détail commande #<?= $order_id ?> | Admin | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
            <p>Plateforme de gestion</p>
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
                <a href="event_registrations.php" class="nav-link">
                    <i class="fas fa-user-check"></i>
                    <span>Inscriptions</span>
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
                <h1 class="page-title">📦 Détail commande #<?= $order_id ?></h1>
                
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
            <!-- Order Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informations de la commande</h3>
                </div>
                
                <div style="padding: var(--spacing-lg);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg);">
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-md); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-user"></i> Client
                            </div>
                            <div style="color: var(--gray-700);">
                                <?= htmlspecialchars($order['full_name']) ?>
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                <?= htmlspecialchars($order['email']) ?>
                            </div>
                            <?php if ($order['phone']): ?>
                                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                    <?= htmlspecialchars($order['phone']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-md); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-calendar"></i> Date de commande
                            </div>
                            <div style="color: var(--gray-700);">
                                <?= date('d/m/Y', strtotime($order['ordered_at'])) ?>
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                <?= date('H:i', strtotime($order['ordered_at'])) ?>
                            </div>
                        </div>
                        
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-md); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-info-circle"></i> Statut
                            </div>
                            <div>
                                <?php
                                $status_labels = [
                                    'pending' => ['En attente', 'badge-warning'],
                                    'processing' => ['En traitement', 'badge-info'],
                                    'shipped' => ['Expédiée', 'badge-primary'],
                                    'delivered' => ['Livrée', 'badge-success'],
                                    'cancelled' => ['Annulée', 'badge-danger']
                                ];
                                $status_info = $status_labels[$order['status']] ?? ['Inconnu', 'badge-secondary'];
                                ?>
                                <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                            </div>
                        </div>
                        
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-md); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-money-bill-wave"></i> Total
                            </div>
                            <div style="color: var(--success-color); font-weight: 600; font-size: var(--font-size-lg);">
                                <?= number_format($order['total_amount'], 2) ?> GHS
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Articles de la commande</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= count($items) ?> articles</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Sous-total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-center">
                                            <?php if ($item['image_url']): ?>
                                                <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                     alt="<?= htmlspecialchars($item['name']) ?>"
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm); margin-right: var(--spacing-sm);">
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; background: var(--gray-200); border-radius: var(--radius-sm); margin-right: var(--spacing-sm); display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-box" style="color: var(--gray-500);"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?= number_format($item['unit_price'], 2) ?> GHS</div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?= $item['quantity'] ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--success-color);">
                                            <?= number_format($item['quantity'] * $item['unit_price'], 2) ?> GHS
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Actions -->
            <div style="margin-top: var(--spacing-lg); display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                <a href="orders.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux commandes
                </a>
                
                <a href="edit_order.php?id=<?= $order_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Modifier la commande
                </a>
                
                <a href="print_order.php?id=<?= $order_id ?>" class="btn btn-info" target="_blank">
                    <i class="fas fa-print"></i>
                    Imprimer
                </a>
            </div>
        </div>
    </div>
</body>
</html>
