<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

// Get commission settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM commission_settings");
$stmt->execute();
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Handle commission rate update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commission_rate'])) {
    $instructor_rate = (float) $_POST['instructor_commission_rate'];
    $vendor_rate = (float) $_POST['vendor_commission_rate'];
    
    if ($instructor_rate >= 0 && $instructor_rate <= 100 && $vendor_rate >= 0 && $vendor_rate <= 100) {
        $stmt = $pdo->prepare("UPDATE commission_settings SET setting_value = ? WHERE setting_key = 'instructor_commission_rate'");
        $stmt->execute([$instructor_rate]);
        
        $stmt = $pdo->prepare("UPDATE commission_settings SET setting_value = ? WHERE setting_key = 'vendor_commission_rate'");
        $stmt->execute([$vendor_rate]);
        
        // Update existing order_items with new rates
        $stmt = $pdo->prepare("UPDATE order_items SET platform_commission_rate = ? WHERE instructor_id IS NOT NULL");
        $stmt->execute([$instructor_rate]);
        
        $stmt = $pdo->prepare("UPDATE order_items SET platform_commission_rate = ? WHERE vendor_id IS NOT NULL");
        $stmt->execute([$vendor_rate]);
        
        $success_message = "Taux de commission mis à jour avec succès.";
    } else {
        $error_message = "Les taux de commission doivent être entre 0 et 100%.";
    }
}

// Get commission statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(gross_revenue) as total_gross_revenue,
        SUM(platform_commission) as total_platform_commission,
        SUM(vendor_revenue) as total_vendor_revenue,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_commissions,
        COUNT(CASE WHEN status = 'paid' THEN 1 END) as paid_commissions,
        COUNT(CASE WHEN transaction_type = 'course' THEN 1 END) as course_transactions,
        COUNT(CASE WHEN transaction_type = 'product' THEN 1 END) as product_transactions
    FROM commission_transactions
");
$stmt->execute();
$stats = $stmt->fetch();

// Get recent commission transactions
$stmt = $pdo->prepare("
    SELECT 
        ct.*,
        u.full_name as user_name,
        u.email as user_email,
        oi.unit_price,
        oi.quantity,
        c.title as course_title,
        p.name as product_name
    FROM commission_transactions ct
    JOIN users u ON (ct.instructor_id = u.id OR ct.vendor_id = u.id)
    JOIN order_items oi ON ct.order_item_id = oi.id
    LEFT JOIN courses c ON oi.course_id = c.id
    LEFT JOIN products p ON oi.product_id = p.id
    ORDER BY ct.created_at DESC
    LIMIT 50
");
$stmt->execute();
$transactions = $stmt->fetchAll();

// Get instructor earnings summary
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        'instructor' as user_type,
        COUNT(ct.id) as total_sales,
        SUM(ct.gross_revenue) as total_gross_revenue,
        SUM(ct.platform_commission) as total_platform_commission,
        SUM(ct.vendor_revenue) as total_vendor_revenue,
        SUM(CASE WHEN ct.status = 'pending' THEN ct.vendor_revenue ELSE 0 END) as pending_revenue,
        SUM(CASE WHEN ct.status = 'paid' THEN ct.vendor_revenue ELSE 0 END) as paid_revenue
    FROM users u
    JOIN commission_transactions ct ON u.id = ct.instructor_id
    WHERE u.role = 'instructor'
    GROUP BY u.id, u.full_name, u.email
    ORDER BY total_vendor_revenue DESC
");
$stmt->execute();
$instructor_earnings = $stmt->fetchAll();

// Get vendor earnings summary
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        u.email,
        'vendor' as user_type,
        COUNT(ct.id) as total_sales,
        SUM(ct.gross_revenue) as total_gross_revenue,
        SUM(ct.platform_commission) as total_platform_commission,
        SUM(ct.vendor_revenue) as total_vendor_revenue,
        SUM(CASE WHEN ct.status = 'pending' THEN ct.vendor_revenue ELSE 0 END) as pending_revenue,
        SUM(CASE WHEN ct.status = 'paid' THEN ct.vendor_revenue ELSE 0 END) as paid_revenue
    FROM users u
    JOIN commission_transactions ct ON u.id = ct.vendor_id
    WHERE u.role = 'vendor'
    GROUP BY u.id, u.full_name, u.email
    ORDER BY total_vendor_revenue DESC
");
$stmt->execute();
$vendor_earnings = $stmt->fetchAll();

// Combine both earnings
$all_earnings = array_merge($instructor_earnings, $vendor_earnings);
usort($all_earnings, function($a, $b) {
    return $b['total_vendor_revenue'] <=> $a['total_vendor_revenue'];
});
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commissions | Admin | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="courses.php" class="nav-link">
                <i class="fas fa-graduation-cap"></i>
                <span>Cours</span>
            </a>
            <a href="orders.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="commission_management.php" class="nav-link active">
                <i class="fas fa-percentage"></i>
                <span>Commissions</span>
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>Gestion des Commissions</h1>
            <p>Gérez les commissions des instructeurs et vendeurs</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Commission Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Paramètres de Commission</h3>
            </div>
            <div class="card-body">
                <form method="POST" class="form-inline">
                    <div class="form-group" style="margin-right: 20px;">
                        <label for="instructor_commission_rate">Commission Instructeurs (%)</label>
                        <input type="number" 
                               id="instructor_commission_rate" 
                               name="instructor_commission_rate" 
                               value="<?= htmlspecialchars($settings['instructor_commission_rate'] ?? '20') ?>" 
                               min="0" 
                               max="100" 
                               step="0.01" 
                               class="form-control"
                               style="width: 150px;">
                    </div>
                    <div class="form-group" style="margin-right: 20px;">
                        <label for="vendor_commission_rate">Commission Vendeurs (%)</label>
                        <input type="number" 
                               id="vendor_commission_rate" 
                               name="vendor_commission_rate" 
                               value="<?= htmlspecialchars($settings['vendor_commission_rate'] ?? '15') ?>" 
                               min="0" 
                               max="100" 
                               step="0.01" 
                               class="form-control"
                               style="width: 150px;">
                    </div>
                    <button type="submit" name="update_commission_rate" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Mettre à jour
                    </button>
                </form>
                
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        <strong>Instructeurs :</strong> <?= htmlspecialchars($settings['instructor_commission_rate'] ?? '20') ?>% commission plateforme, 
                        <?= 100 - (float)($settings['instructor_commission_rate'] ?? '20') ?>% pour l'instructeur. |
                        <strong>Vendeurs :</strong> <?= htmlspecialchars($settings['vendor_commission_rate'] ?? '15') ?>% commission plateforme, 
                        <?= 100 - (float)($settings['vendor_commission_rate'] ?? '15') ?>% pour le vendeur.
                    </small>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_gross_revenue'] ?? 0, 2) ?> GHS</div>
                    <div class="stat-label">Revenu Brut Total</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_platform_commission'] ?? 0, 2) ?> GHS</div>
                    <div class="stat-label">Commission Plateforme</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_vendor_revenue'] ?? 0, 2) ?> GHS</div>
                    <div class="stat-label">Revenus Créateurs</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['pending_commissions'] ?? 0 ?></div>
                    <div class="stat-label">Commissions en Attente</div>
                </div>
            </div>
        </div>

        <!-- Transaction Types Breakdown -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Répartition par Type</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $stats['course_transactions'] ?? 0 ?></div>
                            <div class="stat-label">Transactions Cours</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $stats['product_transactions'] ?? 0 ?></div>
                            <div class="stat-label">Transactions Produits</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Earnings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Revenus par Créateur</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Créateur</th>
                            <th>Type</th>
                            <th>Ventes</th>
                            <th>Revenu Brut</th>
                            <th>Commission Plateforme</th>
                            <th>Revenu Créateur</th>
                            <th>En Attente</th>
                            <th>Payé</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_earnings as $earnings): ?>
                            <tr>
                                <td>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($earnings['full_name']) ?></div>
                                        <div style="font-size: 0.9em; color: var(--gray-600);"><?= htmlspecialchars($earnings['email']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($earnings['user_type'] === 'instructor'): ?>
                                        <span class="badge badge-primary">
                                            <i class="fas fa-graduation-cap"></i> Instructeur
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-store"></i> Vendeur
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-info"><?= $earnings['total_sales'] ?></span>
                                </td>
                                <td><?= number_format($earnings['total_gross_revenue'], 2) ?> GHS</td>
                                <td><?= number_format($earnings['total_platform_commission'], 2) ?> GHS</td>
                                <td>
                                    <strong><?= number_format($earnings['total_vendor_revenue'], 2) ?> GHS</strong>
                                </td>
                                <td>
                                    <span class="badge badge-warning"><?= number_format($earnings['pending_revenue'], 2) ?> GHS</span>
                                </td>
                                <td>
                                    <span class="badge badge-success"><?= number_format($earnings['paid_revenue'], 2) ?> GHS</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transactions Récentes</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Créateur</th>
                            <th>Type</th>
                            <th>Produit/Cours</th>
                            <th>Prix</th>
                            <th>Commission</th>
                            <th>Revenu Créateur</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td>
                                <td><?= htmlspecialchars($transaction['user_name']) ?></td>
                                <td>
                                    <?php if ($transaction['transaction_type'] === 'course'): ?>
                                        <span class="badge badge-primary">
                                            <i class="fas fa-graduation-cap"></i> Cours
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-success">
                                            <i class="fas fa-box"></i> Produit
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($transaction['course_title']): ?>
                                        <i class="fas fa-graduation-cap"></i>
                                        <?= htmlspecialchars($transaction['course_title']) ?>
                                    <?php elseif ($transaction['product_name']): ?>
                                        <i class="fas fa-box"></i>
                                        <?= htmlspecialchars($transaction['product_name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($transaction['unit_price'] * $transaction['quantity'], 2) ?> GHS</td>
                                <td><?= number_format($transaction['platform_commission'], 2) ?> GHS</td>
                                <td>
                                    <strong><?= number_format($transaction['vendor_revenue'], 2) ?> GHS</strong>
                                </td>
                                <td>
                                    <?php if ($transaction['status'] === 'pending'): ?>
                                        <span class="badge badge-warning">En attente</span>
                                    <?php elseif ($transaction['status'] === 'paid'): ?>
                                        <span class="badge badge-success">Payé</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Annulé</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // Add any JavaScript for interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        });
    </script>
</body>
</html>
