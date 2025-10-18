<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];

// Initialize variables
$error_messages = [];
$success_messages = [];

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2) . ' GHS';
}

// Function to get time ago
function getTimeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return "À l'instant";
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Il y a $minutes minute" . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Il y a $hours heure" . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "Il y a $days jour" . ($days > 1 ? 's' : '');
    } else {
        return date('d/m/Y', strtotime($datetime));
    }
}

// Function to get percentage change
function getPercentageChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? 100 : 0;
    return round((($current - $previous) / $previous) * 100, 1);
}

// Function to get chart data for different periods
function getChartData($pdo, $vendor_id, $period = 'monthly', $limit = 12) {
    $dateFormat = $period === 'monthly' ? '%Y-%m' : ($period === 'weekly' ? '%Y-%u' : '%Y-%m-%d');
    $groupBy = $period === 'monthly' ? 'DATE_FORMAT(ct.created_at, "%Y-%m")' : 
               ($period === 'weekly' ? 'DATE_FORMAT(ct.created_at, "%Y-%u")' : 'DATE(ct.created_at)');
    
    $stmt = $pdo->prepare("
        SELECT 
            $groupBy as period,
            SUM(ct.vendor_revenue) as revenue,
            COUNT(ct.id) as sales_count,
            SUM(ct.platform_commission) as commission
        FROM commission_transactions ct
        WHERE ct.vendor_id = ?
        GROUP BY $groupBy
        ORDER BY period DESC
        LIMIT ?
    ");
    $stmt->execute([$vendor_id, $limit]);
    return $stmt->fetchAll();
}

// Check if commission_transactions table exists
$commissionTableExists = $pdo->query("SHOW TABLES LIKE 'commission_transactions'")->rowCount() > 0;

// Initialize default values
$stats = [
    'total_sales' => 0,
    'total_gross_revenue' => 0,
    'total_platform_commission' => 0,
    'total_vendor_revenue' => 0,
    'pending_revenue' => 0,
    'paid_revenue' => 0,
    'avg_commission_rate' => 15
];

$current_month = [
    'current_month_revenue' => 0,
    'current_month_sales' => 0
];

$last_month = [
    'last_month_revenue' => 0,
    'last_month_sales' => 0
];

$this_week = [
    'this_week_revenue' => 0,
    'this_week_sales' => 0
];

$today = [
    'today_revenue' => 0,
    'today_sales' => 0
];

$pending_payouts = [
    'pending_amount' => 0,
    'pending_transactions' => 0
];

$recent_sales = [];
$monthly_earnings = [];
$weekly_earnings = [];
$daily_earnings = [];
$earnings_by_category = [];
$best_days = [];
$top_products = [];

if ($commissionTableExists) {
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

    // Get current month earnings
    $stmt = $pdo->prepare("
        SELECT 
            SUM(ct.vendor_revenue) as current_month_revenue,
            COUNT(ct.id) as current_month_sales
        FROM commission_transactions ct
        WHERE ct.vendor_id = ? 
        AND MONTH(ct.created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(ct.created_at) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$vendor_id]);
    $current_month = $stmt->fetch();

    // Get last month earnings for comparison
    $stmt = $pdo->prepare("
        SELECT 
            SUM(ct.vendor_revenue) as last_month_revenue,
            COUNT(ct.id) as last_month_sales
        FROM commission_transactions ct
        WHERE ct.vendor_id = ? 
        AND MONTH(ct.created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) 
        AND YEAR(ct.created_at) = YEAR(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH))
    ");
    $stmt->execute([$vendor_id]);
    $last_month = $stmt->fetch();

    // Get this week earnings
    $stmt = $pdo->prepare("
        SELECT 
            SUM(ct.vendor_revenue) as this_week_revenue,
            COUNT(ct.id) as this_week_sales
        FROM commission_transactions ct
        WHERE ct.vendor_id = ? 
        AND ct.created_at >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)
    ");
    $stmt->execute([$vendor_id]);
    $this_week = $stmt->fetch();

    // Get today's earnings
    $stmt = $pdo->prepare("
        SELECT 
            SUM(ct.vendor_revenue) as today_revenue,
            COUNT(ct.id) as today_sales
        FROM commission_transactions ct
        WHERE ct.vendor_id = ? 
        AND DATE(ct.created_at) = CURDATE()
    ");
    $stmt->execute([$vendor_id]);
    $today = $stmt->fetch();

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

    // Get chart data for different periods
    $monthly_earnings = getChartData($pdo, $vendor_id, 'monthly', 12);
    $weekly_earnings = getChartData($pdo, $vendor_id, 'weekly', 8);
    $daily_earnings = getChartData($pdo, $vendor_id, 'daily', 30);

    // Get earnings by category
$stmt = $pdo->prepare("
    SELECT 
            p.category,
            SUM(ct.vendor_revenue) as category_revenue,
            COUNT(ct.id) as category_sales
    FROM commission_transactions ct
        JOIN order_items oi ON ct.order_item_id = oi.id
        JOIN products p ON oi.product_id = p.id
    WHERE ct.vendor_id = ?
        GROUP BY p.category
        ORDER BY category_revenue DESC
        LIMIT 5
");
$stmt->execute([$vendor_id]);
    $earnings_by_category = $stmt->fetchAll();

    // Get best performing days
    $stmt = $pdo->prepare("
        SELECT 
            DAYNAME(ct.created_at) as day_name,
            SUM(ct.vendor_revenue) as day_revenue,
            COUNT(ct.id) as day_sales
        FROM commission_transactions ct
        WHERE ct.vendor_id = ?
        GROUP BY DAYNAME(ct.created_at)
        ORDER BY day_revenue DESC
    ");
    $stmt->execute([$vendor_id]);
    $best_days = $stmt->fetchAll();

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

// Get vendor's payout accounts (check if table exists first)
$payout_accounts = [];
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'vendor_payout_accounts'")->rowCount() > 0;
    if ($tableExists) {
$stmt = $pdo->prepare("
    SELECT * FROM vendor_payout_accounts 
    WHERE vendor_id = ? 
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$vendor_id]);
$payout_accounts = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // Table doesn't exist, continue with empty array
    $payout_accounts = [];
}

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
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_earnings') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .sidebar {
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
            color: white;
            position: fixed;
            padding: 2rem 1.5rem;
            overflow-y: auto;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 12px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 1rem 1.2rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 700;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.8rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #00796b;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #64748b;
            margin-bottom: 0.8rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-card .trend {
            font-size: 0.9rem;
            color: #10b981;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-card .trend.negative {
            color: #ef4444;
        }

        .stat-card .trend i {
            font-size: 0.8rem;
        }

        .stat-card .percentage {
            font-size: 0.8rem;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
        }

        .stat-card .percentage.positive {
            background: #dcfce7;
            color: #166534;
        }

        .stat-card .percentage.negative {
            background: #fef2f2;
            color: #991b1b;
        }

        .card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            color: #1e293b;
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .card-title i {
            margin-right: 0.8rem;
            color: #00796b;
        }

        .chart-controls {
            display: flex;
            gap: 0.5rem;
        }

        .chart-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e2e8f0;
            background: white;
            color: #64748b;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .chart-btn.active {
            background: #00796b;
            color: white;
            border-color: #00796b;
        }

        .chart-btn:hover {
            border-color: #00796b;
            color: #00796b;
        }

        .chart-btn.active:hover {
            color: white;
        }

        .commission-breakdown {
            display: grid;
            gap: 1rem;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 4px solid #e2e8f0;
        }

        .breakdown-item.breakdown-total {
            background: #dcfce7;
            border-left-color: #10b981;
            font-weight: 600;
        }

        .breakdown-label {
            color: #374151;
            font-weight: 500;
        }

        .breakdown-value {
            font-weight: 600;
            color: #1e293b;
        }

        .text-success {
            color: #10b981 !important;
        }

        .text-danger {
            color: #ef4444 !important;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }

        .product-card {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .product-info h4 {
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .product-stats {
            display: grid;
            gap: 0.5rem;
        }

        .stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
        }

        .stat-value {
            font-weight: 600;
            color: #1e293b;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .table tr:hover {
            background: #f8fafc;
        }

        .badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-primary {
            background: #d1ecf1;
            color: #0c5460;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert-link {
            color: inherit;
            text-decoration: underline;
            font-weight: 600;
        }

        .payout-accounts {
            display: grid;
            gap: 1rem;
        }

        .payout-account {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .account-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .account-name {
            font-weight: 600;
            color: #1e293b;
        }

        .account-method {
            color: #64748b;
            font-size: 0.9rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: #00796b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #00695c;
            transform: translateY(-1px);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #64748b;
        }

        .text-success {
            color: #10b981;
        }

        .text-danger {
            color: #ef4444;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .products-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 <?= __('vendor_space') ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="products.php"><i class="fas fa-box"></i> <?= __('my_products') ?></a>
        <a href="add_product.php"><i class="fas fa-plus"></i> <?= __('add_product') ?></a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></a>
        <a href="earnings.php" class="active"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> <?= __('payments') ?></a>
        <a href="profile.php"><i class="fas fa-user"></i> <?= __('my_profile') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
            </div>

    <div class="main-content">
        <?php if (!empty($error_messages)): ?>
            <?php foreach ($error_messages as $error): ?>
                <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($success_messages)): ?>
            <?php foreach ($success_messages as $success): ?>
                <div class="alert alert-success">✅ <?= htmlspecialchars($success) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!$commissionTableExists): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Note:</strong> Le système de commissions n'est pas encore configuré. Les données d'earnings affichées sont des valeurs par défaut.
            </div>
        <?php endif; ?>

        <div class="header">
            <div>
                <h1><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></h1>
                <p>Suivez vos gains, commissions et performances</p>
        </div>
            <div>
                <?php include '../includes/language_switcher.php'; ?>
            </div>
        </div>

        <!-- Enhanced Earnings Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-chart-line"></i> Revenus Totaux</h3>
                <div class="value"><?= formatCurrency($stats['total_vendor_revenue'] ?? 0) ?></div>
                <div class="trend">
                    <i class="fas fa-arrow-up"></i>
                    <?= $stats['total_sales'] ?? 0 ?> ventes totales
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar-month"></i> Ce Mois</h3>
                <div class="value"><?= formatCurrency($current_month['current_month_revenue'] ?? 0) ?></div>
                <div class="trend">
                    <i class="fas fa-chart-bar"></i>
                    <?= $current_month['current_month_sales'] ?? 0 ?> ventes
                    <?php if (isset($current_month['current_month_revenue']) && isset($last_month['last_month_revenue'])): ?>
                        <span class="percentage <?= getPercentageChange($current_month['current_month_revenue'], $last_month['last_month_revenue']) >= 0 ? 'positive' : 'negative' ?>">
                            <?= getPercentageChange($current_month['current_month_revenue'], $last_month['last_month_revenue']) >= 0 ? '+' : '' ?><?= getPercentageChange($current_month['current_month_revenue'], $last_month['last_month_revenue']) ?>%
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> En Attente</h3>
                <div class="value"><?= formatCurrency($pending_payouts['pending_amount'] ?? 0) ?></div>
                <div class="trend">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $pending_payouts['pending_transactions'] ?? 0 ?> transactions
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-percentage"></i> Votre Part</h3>
                <div class="value"><?= number_format(100 - ($stats['avg_commission_rate'] ?? 15), 1) ?>%</div>
                <div class="trend">
                    <i class="fas fa-hand-holding-usd"></i>
                    Commission moyenne
                </div>
            </div>
        </div>

        <!-- Enhanced Commission Breakdown -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-calculator"></i> Répartition des Commissions</h3>
            </div>
            <div class="card-body">
                <div class="commission-breakdown">
                    <div class="breakdown-item">
                        <div class="breakdown-label">Revenu Brut Total</div>
                        <div class="breakdown-value"><?= formatCurrency($stats['total_gross_revenue'] ?? 0) ?></div>
                    </div>
                    <div class="breakdown-item">
                        <div class="breakdown-label">Commission Plateforme (<?= number_format($stats['avg_commission_rate'] ?? 15, 1) ?>%)</div>
                        <div class="breakdown-value text-danger">-<?= formatCurrency($stats['total_platform_commission'] ?? 0) ?></div>
                    </div>
                    <div class="breakdown-item breakdown-total">
                        <div class="breakdown-label">Vos Revenus</div>
                        <div class="breakdown-value text-success"><?= formatCurrency($stats['total_vendor_revenue'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-calendar-week"></i> Cette Semaine</h3>
                <div class="value"><?= formatCurrency($this_week['this_week_revenue'] ?? 0) ?></div>
                <div class="trend">
                    <i class="fas fa-chart-line"></i>
                    <?= $this_week['this_week_sales'] ?? 0 ?> ventes
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-calendar-day"></i> Aujourd'hui</h3>
                <div class="value"><?= formatCurrency($today['today_revenue'] ?? 0) ?></div>
                <div class="trend">
                    <i class="fas fa-sun"></i>
                    <?= $today['today_sales'] ?? 0 ?> ventes
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-chart-pie"></i> Revenus Payés</h3>
                <div class="value"><?= formatCurrency($stats['paid_revenue'] ?? 0) ?></div>
                <div class="trend">
                    <i class="fas fa-check-circle"></i>
                    Transactions payées
                </div>
            </div>
            
            <div class="stat-card">
                <h3><i class="fas fa-trophy"></i> Meilleur Jour</h3>
                <div class="value"><?= !empty($best_days) ? $best_days[0]['day_name'] : 'N/A' ?></div>
                <div class="trend">
                    <i class="fas fa-star"></i>
                    <?= !empty($best_days) ? formatCurrency($best_days[0]['day_revenue']) : '0 GHS' ?>
                </div>
            </div>
        </div>

        <!-- Enhanced Top Products -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-trophy"></i> Produits les Plus Vendus</h3>
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
                                            <span class="stat-value"><?= formatCurrency($product['total_revenue']) ?></span>
                                        </div>
                                        <div class="stat">
                                            <span class="stat-label">Moyenne</span>
                                            <span class="stat-value"><?= formatCurrency($product['avg_revenue']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Earnings by Category -->
        <?php if (!empty($earnings_by_category)): ?>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-tags"></i> Revenus par Catégorie</h3>
            </div>
            <div class="card-body">
                <div class="products-grid">
                    <?php foreach ($earnings_by_category as $category): ?>
                        <div class="product-card">
                            <div class="product-info">
                                <h4><?= htmlspecialchars($category['category'] ?? 'Sans catégorie') ?></h4>
                                <div class="product-stats">
                                    <div class="stat">
                                        <span class="stat-label">Ventes</span>
                                        <span class="stat-value"><?= $category['category_sales'] ?></span>
                                    </div>
                                    <div class="stat">
                                        <span class="stat-label">Revenus</span>
                                        <span class="stat-value"><?= formatCurrency($category['category_revenue']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Enhanced Earnings Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-chart-area"></i> Évolution des Revenus</h3>
                <div class="chart-controls">
                    <button class="chart-btn active" data-period="monthly">Mensuel</button>
                    <button class="chart-btn" data-period="weekly">Hebdomadaire</button>
                    <button class="chart-btn" data-period="daily">Quotidien</button>
                </div>
            </div>
            <div class="card-body">
                <canvas id="earningsChart" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Enhanced Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-history"></i> Ventes Récentes</h3>
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
                                    <td>
                                        <div style="font-weight: 500;"><?= date('d/m/Y', strtotime($sale['created_at'])) ?></div>
                                        <div style="font-size: 0.9em; color: #64748b;"><?= getTimeAgo($sale['created_at']) ?></div>
                                    </td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 500;">
                                                <i class="fas fa-box"></i>
                                                <?= htmlspecialchars($sale['product_name']) ?>
                                            </div>
                                            <div style="font-size: 0.9em; color: #64748b;">
                                                <?= htmlspecialchars(substr($sale['product_description'], 0, 50)) ?>...
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($sale['buyer_name']) ?></div>
                                            <div style="font-size: 0.9em; color: #64748b;"><?= htmlspecialchars($sale['buyer_email']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= formatCurrency($sale['unit_price'] * $sale['quantity']) ?></td>
                                    <td>
                                        <strong class="text-success"><?= formatCurrency($sale['vendor_revenue']) ?></strong>
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

        <!-- Enhanced Payout Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-hand-holding-usd"></i> Informations de Paiement</h3>
            </div>
            <div class="card-body">
                <?php if ($pending_payouts['pending_amount'] > 0): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Vous avez <strong><?= formatCurrency($pending_payouts['pending_amount']) ?></strong> 
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
        // Enhanced chart functionality with multiple periods
        const ctx = document.getElementById('earningsChart').getContext('2d');
        let currentChart = null;
        
        const chartData = {
            monthly: <?= json_encode(array_reverse($monthly_earnings)) ?>,
            weekly: <?= json_encode(array_reverse($weekly_earnings)) ?>,
            daily: <?= json_encode(array_reverse($daily_earnings)) ?>
        };

        function createChart(period) {
            const data = chartData[period];
            const labels = data.map(item => {
                if (period === 'monthly') {
                    const date = new Date(item.period + '-01');
                    return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
                } else if (period === 'weekly') {
                    return `Semaine ${item.period.split('-')[1]}`;
                } else {
                    const date = new Date(item.period);
                    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
                }
            });

            const revenueData = data.map(item => parseFloat(item.revenue || 0));
            const commissionData = data.map(item => parseFloat(item.commission || 0));

            if (currentChart) {
                currentChart.destroy();
            }

            currentChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenus (GHS)',
                        data: revenueData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    }, {
                        label: 'Commissions (GHS)',
                        data: commissionData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toLocaleString('fr-FR') + ' GHS';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
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
        }

        // Initialize with monthly data
        createChart('monthly');

        // Chart period switching
        document.querySelectorAll('.chart-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const period = this.dataset.period;
                
                // Update active button
                document.querySelectorAll('.chart-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update chart
                createChart(period);
            });
        });

        // Enhanced interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.metaKey) {
                    switch(e.key) {
                        case 'r':
                            e.preventDefault();
                            location.reload();
                            break;
                        case '1':
                            e.preventDefault();
                            document.querySelector('[data-period="monthly"]').click();
                            break;
                        case '2':
                            e.preventDefault();
                            document.querySelector('[data-period="weekly"]').click();
                            break;
                        case '3':
                            e.preventDefault();
                            document.querySelector('[data-period="daily"]').click();
                            break;
                    }
                }
            });

            // Auto-refresh data every 5 minutes
            setInterval(function() {
                console.log('Refreshing earnings data...');
                // You can add AJAX call here to refresh data
            }, 300000); // 5 minutes

            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Add responsive behavior
            function handleResize() {
                const sidebar = document.querySelector('.sidebar');
                const mainContent = document.querySelector('.main-content');
                
                if (window.innerWidth <= 1024) {
                    sidebar.style.position = 'relative';
                    sidebar.style.width = '100%';
                    mainContent.style.marginLeft = '0';
                } else {
                    sidebar.style.position = 'fixed';
                    sidebar.style.width = '280px';
                    mainContent.style.marginLeft = '280px';
                }
            }

            window.addEventListener('resize', handleResize);
            handleResize(); // Initial call
        });
    </script>
</body>
</html>

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