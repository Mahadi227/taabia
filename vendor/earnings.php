<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

// Get vendor's earnings statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(ct.id) as total_sales,
        SUM(ct.gross_revenue) as total_gross_revenue,
        SUM(ct.platform_commission) as total_platform_commission,
        SUM(ct.vendor_revenue) as total_vendor_revenue,
        SUM(CASE WHEN ct.status = 'pending' THEN ct.vendor_revenue ELSE 0 END) as pending_revenue,
        SUM(CASE WHEN ct.status = 'paid' THEN ct.vendor_revenue ELSE 0 END) as paid_revenue,
        AVG(ct.commission_rate) as avg_commission_rate
    FROM commission_transactions ct
    WHERE ct.vendor_id = ?
");
$stmt->execute([$vendor_id]);
$stats = $stmt->fetch();

// Get recent sales
$stmt = $pdo->prepare("
    SELECT 
        ct.*,
        oi.unit_price,
        oi.quantity,
        p.name as product_name,
        p.description as product_description,
        u.full_name as buyer_name,
        u.email as buyer_email
    FROM commission_transactions ct
    JOIN order_items oi ON ct.order_item_id = oi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN users u ON o.buyer_id = u.id
    JOIN products p ON oi.product_id = p.id
    WHERE ct.vendor_id = ?
    ORDER BY ct.created_at DESC
    LIMIT 20
");
$stmt->execute([$vendor_id]);
$recent_sales = $stmt->fetchAll();

// Get monthly earnings for chart
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(ct.created_at, '%Y-%m') as month,
        SUM(ct.vendor_revenue) as monthly_revenue,
        COUNT(ct.id) as monthly_sales
    FROM commission_transactions ct
    WHERE ct.vendor_id = ?
    GROUP BY DATE_FORMAT(ct.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$vendor_id]);
$monthly_earnings = $stmt->fetchAll();

// Get pending payouts
$stmt = $pdo->prepare("
    SELECT 
        SUM(ct.vendor_revenue) as pending_amount,
        COUNT(ct.id) as pending_transactions
    FROM commission_transactions ct
    WHERE ct.vendor_id = ? AND ct.status = 'pending'
");
$stmt->execute([$vendor_id]);
$pending_payouts = $stmt->fetch();

// Get vendor's payout accounts
$stmt = $pdo->prepare("
    SELECT * FROM vendor_payout_accounts 
    WHERE vendor_id = ? 
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$vendor_id]);
$payout_accounts = $stmt->fetchAll();

// Get top selling products
$stmt = $pdo->prepare("
    SELECT 
        p.name as product_name,
        COUNT(ct.id) as sales_count,
        SUM(ct.vendor_revenue) as total_revenue,
        AVG(ct.vendor_revenue) as avg_revenue
    FROM commission_transactions ct
    JOIN order_items oi ON ct.order_item_id = oi.id
    JOIN products p ON oi.product_id = p.id
    WHERE ct.vendor_id = ?
    GROUP BY p.id, p.name
    ORDER BY total_revenue DESC
    LIMIT 5
");
$stmt->execute([$vendor_id]);
$top_products = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Revenus | Vendeur | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/vendor-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <h1>Mes Revenus</h1>
                <p>Suivez vos gains et commissions</p>
            </div>
            <div class="header-right">
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour au tableau de bord
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Earnings Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($stats['total_vendor_revenue'] ?? 0, 2) ?> GHS</div>
                    <div class="stat-label">Revenus Totaux</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($pending_payouts['pending_amount'] ?? 0, 2) ?> GHS</div>
                    <div class="stat-label">En Attente de Paiement</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $stats['total_sales'] ?? 0 ?></div>
                    <div class="stat-label">Ventes Totales</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format(100 - ($stats['avg_commission_rate'] ?? 15), 1) ?>%</div>
                    <div class="stat-label">Votre Part (Moyenne)</div>
                </div>
            </div>
        </div>

        <!-- Commission Breakdown -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Répartition des Commissions</h3>
            </div>
            <div class="card-body">
                <div class="commission-breakdown">
                    <div class="breakdown-item">
                        <div class="breakdown-label">Revenu Brut Total</div>
                        <div class="breakdown-value"><?= number_format($stats['total_gross_revenue'] ?? 0, 2) ?> GHS</div>
                    </div>
                    <div class="breakdown-item">
                        <div class="breakdown-label">Commission Plateforme (<?= number_format($stats['avg_commission_rate'] ?? 15, 1) ?>%)</div>
                        <div class="breakdown-value text-danger">-<?= number_format($stats['total_platform_commission'] ?? 0, 2) ?> GHS</div>
                    </div>
                    <div class="breakdown-item breakdown-total">
                        <div class="breakdown-label">Vos Revenus</div>
                        <div class="breakdown-value text-success"><?= number_format($stats['total_vendor_revenue'] ?? 0, 2) ?> GHS</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Products -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Produits les Plus Vendus</h3>
            </div>
            <div class="card-body">
                <?php if (empty($top_products)): ?>
                    <div class="text-center text-muted">
                        <i class="fas fa-box" style="font-size: 3rem; opacity: 0.5;"></i>
                        <p>Aucune vente pour le moment</p>
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($top_products as $product): ?>
                            <div class="product-card">
                                <div class="product-info">
                                    <h4><?= htmlspecialchars($product['product_name']) ?></h4>
                                    <div class="product-stats">
                                        <div class="stat">
                                            <span class="stat-label">Ventes</span>
                                            <span class="stat-value"><?= $product['sales_count'] ?></span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-label">Revenus</span>
                                            <span class="stat-value"><?= number_format($product['total_revenue'], 2) ?> GHS</span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-label">Moyenne</span>
                                            <span class="stat-value"><?= number_format($product['avg_revenue'], 2) ?> GHS</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Monthly Earnings Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Évolution des Revenus Mensuels</h3>
            </div>
            <div class="card-body">
                <canvas id="earningsChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Ventes Récentes</h3>
            </div>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Produit</th>
                            <th>Acheteur</th>
                            <th>Prix de Vente</th>
                            <th>Votre Revenu</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_sales)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    <i class="fas fa-inbox"></i>
                                    Aucune vente pour le moment
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_sales as $sale): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <i class="fas fa-box"></i>
                                                <?= htmlspecialchars($sale['product_name']) ?>
                                            </div>
                                            <div style="font-size: 0.9em; color: var(--gray-600);">
                                                <?= htmlspecialchars(substr($sale['product_description'], 0, 50)) ?>...
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($sale['buyer_name']) ?></div>
                                            <div style="font-size: 0.9em; color: var(--gray-600);"><?= htmlspecialchars($sale['buyer_email']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= number_format($sale['unit_price'] * $sale['quantity'], 2) ?> GHS</td>
                                    <td>
                                        <strong class="text-success"><?= number_format($sale['vendor_revenue'], 2) ?> GHS</strong>
                                    </td>
                                    <td>
                                        <?php if ($sale['status'] === 'pending'): ?>
                                            <span class="badge badge-warning">En attente</span>
                                        <?php elseif ($sale['status'] === 'paid'): ?>
                                            <span class="badge badge-success">Payé</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Annulé</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payout Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Informations de Paiement</h3>
            </div>
            <div class="card-body">
                <?php if ($pending_payouts['pending_amount'] > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Vous avez <strong><?= number_format($pending_payouts['pending_amount'], 2) ?> GHS</strong> 
                        en attente de paiement pour <strong><?= $pending_payouts['pending_transactions'] ?></strong> transactions.
                    </div>
                <?php endif; ?>

                <?php if (empty($payout_accounts)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        Vous n'avez pas encore configuré de compte de paiement. 
                        <a href="payout_settings.php" class="alert-link">Configurer maintenant</a>
                    </div>
                <?php else: ?>
                    <h4>Comptes de Paiement Configurés</h4>
                    <div class="payout-accounts">
                        <?php foreach ($payout_accounts as $account): ?>
                            <div class="payout-account">
                                <div class="account-info">
                                    <div class="account-name"><?= htmlspecialchars($account['account_name']) ?></div>
                                    <div class="account-method"><?= htmlspecialchars($account['payout_method']) ?></div>
                                    <?php if ($account['is_default']): ?>
                                        <span class="badge badge-primary">Compte par défaut</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Monthly earnings chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const earningsData = <?= json_encode(array_reverse($monthly_earnings)) ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: earningsData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Revenus Mensuels (GHS)',
                    data: earningsData.map(item => parseFloat(item.monthly_revenue)),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString('fr-FR') + ' GHS';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>