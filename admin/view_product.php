<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

if (!isset($_GET['id'])) redirect('products.php');
$product_id = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        redirect('products.php');
    }

} catch (PDOException $e) {
    error_log("Database error in admin/view_product.php: " . $e->getMessage());
    redirect('products.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | Admin | TaaBia</title>
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
                <h1 class="page-title">📦 <?= htmlspecialchars($product['name']) ?></h1>
                
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
            <!-- Product Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Informations du produit</h3>
                </div>
                
                <div style="padding: var(--spacing-lg);">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-xl);">
                        <!-- Product Details -->
                        <div>
                            <div style="margin-bottom: var(--spacing-lg);">
                                <h4 style="margin-bottom: var(--spacing-sm); color: var(--text-primary);">
                                    <?= htmlspecialchars($product['name']) ?>
                                </h4>
                                <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--success-color); margin-bottom: var(--spacing-md);">
                                    <?= number_format($product['price'], 2) ?> GHS
                                </div>
                                <div style="color: var(--text-secondary); line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-lg);">
                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-md); 
                                    border-radius: var(--radius-lg);
                                ">
                                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                        <i class="fas fa-store"></i> Vendeur
                                    </div>
                                    <div style="color: var(--gray-700);">
                                        <?= htmlspecialchars($product['vendor_name'] ?? 'N/A') ?>
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
                                            'active' => ['Actif', 'badge-success'],
                                            'inactive' => ['Inactif', 'badge-warning'],
                                            'draft' => ['Brouillon', 'badge-info']
                                        ];
                                        $status_info = $status_labels[$product['status']] ?? ['Inconnu', 'badge-secondary'];
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
                                        <i class="fas fa-calendar"></i> Créé le
                                    </div>
                                    <div style="color: var(--gray-700);">
                                        <?= date('d/m/Y', strtotime($product['created_at'])) ?>
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                        <?= date('H:i', strtotime($product['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Product Image -->
                        <div>
                            <?php if ($product['image_url']): ?>
                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-md); 
                                    border-radius: var(--radius-lg);
                                    text-align: center;
                                ">
                                    <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         style="max-width: 100%; height: auto; border-radius: var(--radius-sm);">
                                </div>
                            <?php else: ?>
                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-xl); 
                                    border-radius: var(--radius-lg);
                                    text-align: center;
                                    color: var(--gray-500);
                                ">
                                    <i class="fas fa-image" style="font-size: 3rem; margin-bottom: var(--spacing-md);"></i>
                                    <div>Aucune image</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div style="margin-top: var(--spacing-lg); display: flex; gap: var(--spacing-md); flex-wrap: wrap;">
                <a href="products.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux produits
                </a>
                
                <a href="product_edit.php?id=<?= $product_id ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i>
                    Modifier le produit
                </a>
                
                <a href="product_toggle.php?id=<?= $product_id ?>&action=<?= $product['status'] === 'active' ? 'deactivate' : 'activate' ?>" 
                   class="btn <?= $product['status'] === 'active' ? 'btn-warning' : 'btn-success' ?>"
                   onclick="return confirm('Êtes-vous sûr de vouloir <?= $product['status'] === 'active' ? 'désactiver' : 'activer' ?> ce produit ?')">
                    <i class="fas <?= $product['status'] === 'active' ? 'fa-ban' : 'fa-check' ?>"></i>
                    <?= $product['status'] === 'active' ? 'Désactiver' : 'Activer' ?>
                </a>
                
                <a href="product_delete.php?id=<?= $product_id ?>" 
                   class="btn btn-danger"
                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ? Cette action est irréversible.')">
                    <i class="fas fa-trash"></i>
                    Supprimer
                </a>
            </div>
        </div>
    </div>
</body>
</html> 