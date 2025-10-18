<?php

/**
 * Professional LMS Earnings Dashboard
 * Modern, responsive earnings management interface for instructors
 */

// Start session first to ensure proper language handling
require_once '../includes/session.php';

// Handle language switching
require_once '../includes/language_handler.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Debug: Check available tables and columns
$debug_info = [];
try {
    // Check if order_items table exists and what columns it has
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    $order_items_exists = $stmt->fetch() !== false;

    if ($order_items_exists) {
        $stmt = $pdo->query("DESCRIBE order_items");
        $order_items_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $debug_info['order_items_columns'] = $order_items_columns;
    } else {
        $debug_info['order_items_columns'] = 'Table does not exist';
    }

    // Check if commission_transactions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'commission_transactions'");
    $commission_transactions_exists = $stmt->fetch() !== false;
    $debug_info['commission_transactions_exists'] = $commission_transactions_exists;

    // Check if instructor_payout_accounts table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'instructor_payout_accounts'");
    $payout_accounts_exists = $stmt->fetch() !== false;
    $debug_info['payout_accounts_exists'] = $payout_accounts_exists;
} catch (PDOException $e) {
    $debug_info['error'] = $e->getMessage();
}

// Get instructor's earnings statistics with fallback
$stats = [
    'total_sales' => 0,
    'total_gross_revenue' => 0,
    'total_platform_commission' => 0,
    'total_instructor_revenue' => 0,
    'pending_revenue' => 0,
    'paid_revenue' => 0,
    'avg_commission_rate' => 0
];
try {
$stmt = $pdo->prepare("
    SELECT 
        COUNT(ct.id) as total_sales,
        SUM(ct.gross_revenue) as total_gross_revenue,
        SUM(ct.platform_commission) as total_platform_commission,
            SUM(ct.vendor_revenue) as total_instructor_revenue,
            SUM(CASE WHEN ct.status = 'pending' THEN ct.vendor_revenue ELSE 0 END) as pending_revenue,
            SUM(CASE WHEN ct.status = 'paid' THEN ct.vendor_revenue ELSE 0 END) as paid_revenue,
        AVG(ct.commission_rate) as avg_commission_rate
    FROM commission_transactions ct
    WHERE ct.instructor_id = ?
");
$stmt->execute([$instructor_id]);
    $result = $stmt->fetch();
    $stats = $result ?: $stats;
} catch (PDOException $e) {
    error_log("Error fetching earnings statistics: " . $e->getMessage());
    // Keep default values
}

// Get recent sales with fallback queries
$recent_sales = [];
try {
    // Try the full query first
$stmt = $pdo->prepare("
    SELECT 
            ct.vendor_revenue as instructor_revenue,
        ct.*,
        oi.unit_price,
        oi.quantity,
        c.title as course_title,
        p.name as product_name,
            u.fullname as buyer_name,
        u.email as buyer_email
    FROM commission_transactions ct
    JOIN order_items oi ON ct.order_item_id = oi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN users u ON o.buyer_id = u.id
    LEFT JOIN courses c ON oi.course_id = c.id
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE ct.instructor_id = ?
    ORDER BY ct.created_at DESC
    LIMIT 20
");
$stmt->execute([$instructor_id]);
$recent_sales = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback: Try without course_id join
    try {
        $stmt = $pdo->prepare("
            SELECT 
                ct.vendor_revenue as instructor_revenue,
                ct.*,
                oi.unit_price,
                oi.quantity,
                p.name as product_name,
                u.fullname as buyer_name,
                u.email as buyer_email,
                'Course/Product' as course_title
            FROM commission_transactions ct
            JOIN order_items oi ON ct.order_item_id = oi.id
            JOIN orders o ON oi.order_id = o.id
            JOIN users u ON o.buyer_id = u.id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE ct.instructor_id = ?
            ORDER BY ct.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$instructor_id]);
        $recent_sales = $stmt->fetchAll();
    } catch (PDOException $e2) {
        // Final fallback: Basic query without joins
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    ct.vendor_revenue as instructor_revenue,
                    ct.*,
                    'N/A' as unit_price,
                    1 as quantity,
                    'Course/Product' as course_title,
                    'N/A' as product_name,
                    'Unknown' as buyer_name,
                    'unknown@example.com' as buyer_email
                FROM commission_transactions ct
                WHERE ct.instructor_id = ?
                ORDER BY ct.created_at DESC
                LIMIT 20
            ");
            $stmt->execute([$instructor_id]);
            $recent_sales = $stmt->fetchAll();
        } catch (PDOException $e3) {
            error_log("Error fetching recent sales: " . $e3->getMessage());
            $recent_sales = [];
        }
    }
}

// Get monthly earnings for chart with fallback
$monthly_earnings = [];
try {
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(ct.created_at, '%Y-%m') as month,
            SUM(ct.vendor_revenue) as monthly_revenue,
        COUNT(ct.id) as monthly_sales
    FROM commission_transactions ct
    WHERE ct.instructor_id = ?
    GROUP BY DATE_FORMAT(ct.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$instructor_id]);
$monthly_earnings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching monthly earnings: " . $e->getMessage());
    $monthly_earnings = [];
}

// Get pending payouts with fallback
$pending_payouts = ['pending_amount' => 0, 'pending_transactions' => 0];
try {
$stmt = $pdo->prepare("
    SELECT 
            SUM(ct.vendor_revenue) as pending_amount,
        COUNT(ct.id) as pending_transactions
    FROM commission_transactions ct
    WHERE ct.instructor_id = ? AND ct.status = 'pending'
");
$stmt->execute([$instructor_id]);
    $result = $stmt->fetch();
    $pending_payouts = $result ?: $pending_payouts;
} catch (PDOException $e) {
    error_log("Error fetching pending payouts: " . $e->getMessage());
    $pending_payouts = ['pending_amount' => 0, 'pending_transactions' => 0];
}

// Get instructor's payout accounts with fallback
$payout_accounts = [];
try {
$stmt = $pdo->prepare("
    SELECT * FROM instructor_payout_accounts 
    WHERE instructor_id = ? 
    ORDER BY is_default DESC, created_at DESC
");
$stmt->execute([$instructor_id]);
$payout_accounts = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback: Create empty array if table doesn't exist
    error_log("Error fetching payout accounts: " . $e->getMessage());
    $payout_accounts = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_earnings') ?> | <?= __('instructor_space') ?> | TaaBia</title>

    <!-- Modern Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/earnings.css">

    <!-- Sidebar CSS -->
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <!-- Include Sidebar -->
    <?php include '../includes/instructor_sidebar.php'; ?>
</head>

<body>
    <!-- Debug Information (remove in production) -->
    <div class="debug-info">
        <h4>🐛 Database Debug Information</h4>
        <div class="debug-grid">
            <div>
                <strong>Order Items Table Exists:</strong> <?= isset($debug_info['order_items_columns']) && $debug_info['order_items_columns'] !== 'Table does not exist' ? 'Yes' : 'No' ?><br>
                <strong>Commission Transactions Exists:</strong> <?= $debug_info['commission_transactions_exists'] ? 'Yes' : 'No' ?><br>
                <strong>Payout Accounts Table Exists:</strong> <?= $debug_info['payout_accounts_exists'] ? 'Yes' : 'No' ?><br>
                <strong>Instructor ID:</strong> <?= $instructor_id ?><br>
            </div>
            <div>
                <strong>Order Items Columns:</strong><br>
                <?php if (isset($debug_info['order_items_columns']) && is_array($debug_info['order_items_columns'])): ?>
                    <?= implode(', ', $debug_info['order_items_columns']) ?>
                <?php else: ?>
                    <?= $debug_info['order_items_columns'] ?? 'Unknown' ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if (isset($debug_info['error'])): ?>
            <div style="margin-top: 10px; color: #dc3545;">
                <strong>Error:</strong> <?= htmlspecialchars($debug_info['error']) ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1 class="page-title">
                    <i class="fas fa-chart-line"></i>
                <?= __('my_earnings') ?>
            </h1>
            <p class="page-subtitle"><?= __('track_your_earnings_and_analytics') ?></p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card primary">
                <div class="stat-header">
                    <div class="stat-title"><?= __('total_revenue') ?></div>
                    <div class="stat-icon primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_instructor_revenue'], 2) ?> <?= __('currency') ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?= __('total_earnings') ?>
                </div>
            </div>

            <div class="stat-card success">
                <div class="stat-header">
                    <div class="stat-title"><?= __('total_sales') ?></div>
                    <div class="stat-icon success">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($stats['total_sales']) ?></div>
                <div class="stat-change positive">
                    <i class="fas fa-arrow-up"></i>
                    <?= __('completed_transactions') ?>
                </div>
            </div>

            <div class="stat-card warning">
                <div class="stat-header">
                    <div class="stat-title"><?= __('pending_payouts') ?></div>
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-value"><?= number_format($pending_payouts['pending_amount'], 2) ?> <?= __('currency') ?></div>
                <div class="stat-change neutral">
                    <i class="fas fa-info-circle"></i>
                    <?= $pending_payouts['pending_transactions'] ?> <?= __('transactions') ?>
                </div>
            </div>

            <div class="stat-card info">
                <div class="stat-header">
                    <div class="stat-title"><?= __('commission_rate') ?></div>
                    <div class="stat-icon info">
                    <i class="fas fa-percentage"></i>
                </div>
                </div>
                <div class="stat-value"><?= number_format($stats['avg_commission_rate'], 1) ?>%</div>
                <div class="stat-change neutral">
                    <i class="fas fa-chart-bar"></i>
                    <?= __('average_rate') ?>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section fade-in">
            <!-- Monthly Earnings Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title"><?= __('monthly_earnings') ?></h3>
                    <div class="chart-controls">
                        <button class="chart-btn active" data-period="6m">6M</button>
                        <button class="chart-btn" data-period="1y">1Y</button>
                        <button class="chart-btn" data-period="all"><?= __('all_time') ?></button>
                        </div>
                    </div>
                <div class="chart-container">
                    <canvas id="earningsChart"></canvas>
            </div>
        </div>

            <!-- Revenue Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title"><?= __('revenue_breakdown') ?></h3>
                </div>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
            </div>
            </div>
        </div>

        <!-- Recent Sales -->
        <div class="recent-sales-card fade-in">
            <div class="recent-sales-header">
                <h3 class="recent-sales-title"><?= __('recent_sales') ?></h3>
                <a href="transactions.php" class="view-all-btn">
                    <i class="fas fa-external-link-alt"></i>
                    <?= __('view_all') ?>
                </a>
            </div>
            <div class="table-responsive">
                <table class="sales-table">
                    <thead>
                        <tr>
                            <th><?= __('date') ?></th>
                            <th><?= __('course_product') ?></th>
                            <th><?= __('buyer') ?></th>
                            <th><?= __('amount') ?></th>
                            <th><?= __('status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_sales)): ?>
                        <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: var(--gray-500);">
                                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    <?= __('no_sales_yet') ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach (array_slice($recent_sales, 0, 10) as $sale): ?>
                                <tr class="slide-in">
                                    <td><?= date('M j, Y', strtotime($sale['created_at'])) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($sale['course_title'] ?? $sale['product_name'] ?? 'N/A') ?></strong>
                            </td>
                                    <td><?= htmlspecialchars($sale['buyer_name'] ?? 'Unknown') ?></td>
                                    <td><strong><?= number_format($sale['instructor_revenue'], 2) ?> <?= __('currency') ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= $sale['status'] ?>">
                                            <?= ucfirst($sale['status']) ?>
                                        </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payout Accounts -->
        <div class="payout-accounts-card fade-in">
            <div class="payout-accounts-header">
                <h3 class="payout-accounts-title"><?= __('payout_accounts') ?></h3>
                <a href="payouts.php" class="add-account-btn">
                    <i class="fas fa-plus"></i>
                    <?= __('add_account') ?>
                </a>
                </div>
                <?php if (empty($payout_accounts)): ?>
                <div style="text-align: center; padding: 2rem; color: var(--gray-500);">
                    <i class="fas fa-credit-card" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                    <p><?= __('no_payout_accounts') ?></p>
                    <a href="payouts.php" class="add-account-btn" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i>
                        <?= __('setup_payout_account') ?>
                    </a>
                </div>
                <?php else: ?>
                    <?php foreach ($payout_accounts as $account): ?>
                    <div class="account-item slide-in">
                        <div class="account-info">
                            <div class="account-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="account-details">
                                <h4><?= htmlspecialchars($account['account_type'] ?? 'Bank Account') ?></h4>
                                <p><?= htmlspecialchars($account['account_number'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <div class="account-actions">
                            <a href="payouts.php?edit=<?= $account['id'] ?>" class="action-btn">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="payouts.php?delete=<?= $account['id'] ?>" class="action-btn" onclick="return confirm('<?= __('confirm_delete') ?>')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript Variables -->
    <script>
        // Make data available to JavaScript
        window.earningsData = <?= json_encode(array_reverse($monthly_earnings)) ?>;
        window.stats = <?= json_encode($stats) ?>;
        window.recentSales = <?= json_encode($recent_sales) ?>;
        window.currency = '<?= __('currency') ?>';
        window.language = '<?= $_SESSION['user_language'] ?? 'fr' ?>';
    </script>

    <!-- Custom JavaScript -->
    <script src="assets/js/earnings.js"></script>
</body>

</html>