<?php

/**
 * Transactions Management Page
 * Professional LMS Interface - Consistent Design
 */

// ============================================================================
// INITIALIZATION & SECURITY
// ============================================================================

ob_start();
require_once '../includes/language_handler.php';
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

require_role('instructor');
$instructor_id = $_SESSION['user_id'];

// ============================================================================
// DATA PROCESSING
// ============================================================================

/**
 * Transaction Analytics Engine
 */
class TransactionAnalytics
{
    private $pdo;
    private $instructor_id;

    public function __construct($pdo, $instructor_id)
    {
        $this->pdo = $pdo;
        $this->instructor_id = $instructor_id;
    }

    public function getOverviewStats()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(DISTINCT o.id) as total_orders,
                    SUM(oi.price * oi.quantity) as total_revenue,
                    COUNT(DISTINCT o.buyer_id) as unique_customers,
                    AVG(oi.price * oi.quantity) as avg_order_value,
                    COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_orders,
                    COUNT(CASE WHEN o.status = 'cancelled' THEN 1 END) as cancelled_orders,
                    COUNT(CASE WHEN o.status = 'refunded' THEN 1 END) as refunded_orders
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                LEFT JOIN courses c ON oi.course_id = c.id
                WHERE c.instructor_id = ? OR oi.course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
            ");
            $stmt->execute([$this->instructor_id, $this->instructor_id]);
            return $stmt->fetch() ?: [];
        } catch (PDOException $e) {
            error_log("Analytics Error: " . $e->getMessage());
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'unique_customers' => 0,
                'avg_order_value' => 0,
                'completed_orders' => 0,
                'pending_orders' => 0,
                'cancelled_orders' => 0,
                'refunded_orders' => 0
            ];
        }
    }

    public function getRevenueData($months = 12)
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    DATE_FORMAT(o.ordered_at, '%Y-%m') as month,
                    SUM(oi.price * oi.quantity) as revenue,
                    COUNT(DISTINCT o.id) as orders
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                LEFT JOIN courses c ON oi.course_id = c.id
                WHERE (c.instructor_id = ? OR oi.course_id IN (SELECT id FROM courses WHERE instructor_id = ?))
                AND o.status = 'completed'
                AND o.ordered_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                GROUP BY DATE_FORMAT(o.ordered_at, '%Y-%m')
                ORDER BY month ASC
                LIMIT ?
            ");
            $stmt->execute([$this->instructor_id, $this->instructor_id, $months, $months]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
}

/**
 * Transaction Manager
 */
class TransactionManager
{
    private $pdo;
    private $instructor_id;

    public function __construct($pdo, $instructor_id)
    {
        $this->pdo = $pdo;
        $this->instructor_id = $instructor_id;
    }

    public function getTransactions($filters = [])
    {
        $where_conditions = ["(c.instructor_id = ? OR oi.course_id IN (SELECT id FROM courses WHERE instructor_id = ?))"];
        $params = [$this->instructor_id, $this->instructor_id];

        if (!empty($filters['search'])) {
            $where_conditions[] = "(s.full_name LIKE ? OR c.title LIKE ? OR o.id LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if (!empty($filters['status'])) {
            $where_conditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['type'])) {
            $where_conditions[] = "oi.type = ?";
            $params[] = $filters['type'];
        }

        $where_clause = implode(" AND ", $where_conditions);
        $order_by = $this->getOrderBy($filters['sort'] ?? 'recent');

        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    o.id as order_id,
                    o.ordered_at,
                    o.status,
                    oi.price * oi.quantity as amount,
                    oi.quantity,
                    oi.type,
                    s.id as student_id,
                    s.full_name as student_name,
                    s.email as student_email,
                    c.id as course_id,
                    c.title as course_title,
                    p.name as product_name
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN students s ON o.buyer_id = s.id
                LEFT JOIN courses c ON oi.course_id = c.id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE $where_clause
                ORDER BY $order_by
                LIMIT 100
            ");
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Transaction Query Error: " . $e->getMessage());
            return [];
        }
    }

    private function getOrderBy($sort)
    {
        return match ($sort) {
            'recent' => 'o.ordered_at DESC',
            'oldest' => 'o.ordered_at ASC',
            'amount_high' => 'oi.price * oi.quantity DESC',
            'amount_low' => 'oi.price * oi.quantity ASC',
            'student' => 's.full_name ASC',
            default => 'o.ordered_at DESC'
        };
    }
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

$analytics = new TransactionAnalytics($pdo, $instructor_id);
$transactionManager = new TransactionManager($pdo, $instructor_id);

$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',
    'sort' => $_GET['sort'] ?? 'recent',
    'period' => (int)($_GET['period'] ?? 12)
];

$stats = $analytics->getOverviewStats();
$revenueData = $analytics->getRevenueData($filters['period']);
$transactions = $transactionManager->getTransactions($filters);

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('transactions') ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <style>
        /* Consistent Professional LMS Design */
        .instructor-main {
            margin-left: 280px;
            padding: var(--spacing-8);
            background-color: var(--gray-50);
            min-height: 100vh;
        }

        @media (max-width: 1024px) {
            .instructor-main {
                margin-left: 0;
                padding: var(--spacing-4);
            }
        }

        /* Page Header */
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary-color);
        }

        .page-header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title i {
            color: var(--primary-color);
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1rem;
            margin: 0;
        }

        .page-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-color);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.revenue {
            border-left-color: var(--success-color);
        }

        .stat-card.orders {
            border-left-color: var(--primary-color);
        }

        .stat-card.customers {
            border-left-color: var(--info-color);
        }

        .stat-card.performance {
            border-left-color: var(--warning-color);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .stat-icon.revenue {
            background: linear-gradient(135deg, var(--success-color), var(--success-dark));
        }

        .stat-icon.orders {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
        }

        .stat-icon.customers {
            background: linear-gradient(135deg, var(--info-color), #0284c7);
        }

        .stat-icon.performance {
            background: linear-gradient(135deg, var(--warning-color), var(--warning-dark));
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-change {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: var(--success-color);
        }

        .stat-change.neutral {
            color: var(--gray-500);
        }

        /* Chart Section */
        .chart-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .chart-controls {
            display: flex;
            gap: 0.5rem;
        }

        .chart-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--gray-300);
            background: white;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--gray-600);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .chart-btn:hover {
            background: var(--gray-50);
            border-color: var(--gray-400);
        }

        .chart-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .filter-input {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .filter-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.3);
        }

        .btn-outline {
            background: white;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Transactions Table */
        .transactions-table-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table th {
            background: var(--gray-50);
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--gray-200);
        }

        .transactions-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-900);
            vertical-align: middle;
        }

        .transactions-table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .status-refunded {
            background: rgba(107, 114, 128, 0.1);
            color: var(--gray-600);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            color: var(--gray-700);
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .chart-section {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .transactions-table {
                font-size: 0.85rem;
            }

            .transactions-table th,
            .transactions-table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <?php include '../includes/instructor_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="instructor-main">
            <!-- Page Header -->
            <header class="page-header">
                <div class="page-header-content">
                    <div>
                        <h1 class="page-title">
                            <i class="fas fa-shopping-cart"></i>
                            <?= __('transactions') ?>
                        </h1>
                        <p class="page-subtitle"><?= __('manage_your_sales_and_orders') ?></p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> <?= __('refresh_data') ?>
                        </button>
                        <button class="btn btn-primary" onclick="exportTransactions()">
                            <i class="fas fa-download"></i> <?= __('export_transactions') ?>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card revenue">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                    <div class="stat-value">$<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
                    <div class="stat-label"><?= __('total_revenue') ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +12.5% <?= __('this_month') ?>
                    </div>
                </div>

                <div class="stat-card orders">
                    <div class="stat-header">
                        <div class="stat-icon orders">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['total_orders'] ?? 0) ?></div>
                    <div class="stat-label"><?= __('total_orders') ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-check"></i> <?= $stats['completed_orders'] ?? 0 ?> <?= __('completed') ?>
                    </div>
                </div>

                <div class="stat-card customers">
                    <div class="stat-header">
                        <div class="stat-icon customers">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['unique_customers'] ?? 0) ?></div>
                    <div class="stat-label"><?= __('unique_customers') ?></div>
                    <div class="stat-change neutral">
                        <i class="fas fa-user"></i> <?= __('customers') ?>
                    </div>
                </div>

                <div class="stat-card performance">
                    <div class="stat-header">
                        <div class="stat-icon performance">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                    </div>
                    <div class="stat-value">$<?= number_format($stats['avg_order_value'] ?? 0, 2) ?></div>
                    <div class="stat-label"><?= __('avg_order_value') ?></div>
                    <div class="stat-change neutral">
                        <i class="fas fa-percentage"></i> <?= __('average') ?>
                    </div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="chart-section">
                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title"><?= __('revenue_breakdown') ?></h3>
                        <div class="chart-controls">
                            <button class="chart-btn <?= $filters['period'] == 12 ? 'active' : '' ?>" data-period="12">12M</button>
                            <button class="chart-btn <?= $filters['period'] == 6 ? 'active' : '' ?>" data-period="6">6M</button>
                            <button class="chart-btn <?= $filters['period'] == 3 ? 'active' : '' ?>" data-period="3">3M</button>
                            <button class="chart-btn <?= $filters['period'] == 1 ? 'active' : '' ?>" data-period="1">1M</button>
                        </div>
                    </div>
                    <div style="position: relative; height: 300px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <div class="chart-header">
                        <h3 class="chart-title"><?= __('performance_metrics') ?></h3>
                    </div>
                    <div style="position: relative; height: 300px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label"><?= __('search_transactions') ?></label>
                        <input type="text" name="search" class="filter-input"
                            value="<?= htmlspecialchars($filters['search']) ?>"
                            placeholder="<?= __('search_transactions') ?>">
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('filter_by_status') ?></label>
                        <select name="status" class="filter-input">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="completed" <?= $filters['status'] == 'completed' ? 'selected' : '' ?>>
                                <?= __('completed') ?>
                            </option>
                            <option value="pending" <?= $filters['status'] == 'pending' ? 'selected' : '' ?>>
                                <?= __('pending') ?>
                            </option>
                            <option value="cancelled" <?= $filters['status'] == 'cancelled' ? 'selected' : '' ?>>
                                <?= __('cancelled') ?>
                            </option>
                            <option value="refunded" <?= $filters['status'] == 'refunded' ? 'selected' : '' ?>>
                                <?= __('refunded') ?>
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('filter_by_type') ?></label>
                        <select name="type" class="filter-input">
                            <option value=""><?= __('all_types') ?></option>
                            <option value="course" <?= $filters['type'] == 'course' ? 'selected' : '' ?>>
                                <?= __('course') ?>
                            </option>
                            <option value="product" <?= $filters['type'] == 'product' ? 'selected' : '' ?>>
                                <?= __('product') ?>
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('sort_by') ?></label>
                        <select name="sort" class="filter-input">
                            <option value="recent" <?= $filters['sort'] == 'recent' ? 'selected' : '' ?>>
                                <?= __('most_recent') ?>
                            </option>
                            <option value="oldest" <?= $filters['sort'] == 'oldest' ? 'selected' : '' ?>>
                                <?= __('oldest_first') ?>
                            </option>
                            <option value="amount_high" <?= $filters['sort'] == 'amount_high' ? 'selected' : '' ?>>
                                <?= __('highest_amount') ?>
                            </option>
                            <option value="student" <?= $filters['sort'] == 'student' ? 'selected' : '' ?>>
                                <?= __('student_name') ?>
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> <?= __('apply_filters') ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Transactions Table -->
            <div class="transactions-table-container">
                <div class="table-header">
                    <h3 class="table-title"><?= __('transaction_history') ?></h3>
                    <div>
                        <span style="color: var(--gray-600); font-size: 0.9rem;">
                            <?= count($transactions) ?> <?= __('transactions_found') ?>
                        </span>
                    </div>
                </div>

                <?php if (empty($transactions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3><?= __('no_transactions_found') ?></h3>
                        <p><?= __('try_adjusting_your_filters') ?></p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="transactions-table">
                            <thead>
                                <tr>
                                    <th><?= __('order_id') ?></th>
                                    <th><?= __('student') ?></th>
                                    <th><?= __('course_product') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('quantity') ?></th>
                                    <th><?= __('date') ?></th>
                                    <th><?= __('status') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= htmlspecialchars($transaction['order_id']) ?></strong>
                                        </td>
                                        <td>
                                            <div>
                                                <div style="font-weight: 600;">
                                                    <?= htmlspecialchars($transaction['student_name']) ?>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--gray-500);">
                                                    <?= htmlspecialchars($transaction['student_email']) ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($transaction['course_title']): ?>
                                                <div style="font-weight: 600;">
                                                    <?= htmlspecialchars($transaction['course_title']) ?>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--gray-500);">
                                                    <?= __('course') ?>
                                                </div>
                                            <?php elseif ($transaction['product_name']): ?>
                                                <div style="font-weight: 600;">
                                                    <?= htmlspecialchars($transaction['product_name']) ?>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--gray-500);">
                                                    <?= __('product') ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--gray-500);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong>$<?= number_format($transaction['amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <span style="color: var(--gray-600);"><?= $transaction['quantity'] ?></span>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($transaction['ordered_at'])) ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $transaction['status'] ?>">
                                                <?= __($transaction['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem;"
                                                onclick="viewDetails(<?= $transaction['order_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        const revenueData = <?= json_encode($revenueData) ?>;
        const stats = <?= json_encode($stats) ?>;

        let revenueChart = null;
        let performanceChart = null;

        function initializeCharts() {
            // Destroy existing charts if they exist
            if (revenueChart) {
                revenueChart.destroy();
            }
            if (performanceChart) {
                performanceChart.destroy();
            }

            // Revenue Chart
            const ctx1 = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: revenueData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('<?= $_SESSION['user_language'] ?? 'fr' ?>', {
                            month: 'short',
                            year: 'numeric'
                        });
                    }),
                    datasets: [{
                        label: '<?= __('monthly_revenue') ?>',
                        data: revenueData.map(item => item.revenue),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2.5,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: 'white',
                            bodyColor: 'white',
                            cornerRadius: 8
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });

            // Performance Chart
            const ctx2 = document.getElementById('performanceChart').getContext('2d');
            performanceChart = new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['<?= __('completed') ?>', '<?= __('pending') ?>', '<?= __('cancelled') ?>', '<?= __('refunded') ?>'],
                    datasets: [{
                        data: [
                            stats.completed_orders,
                            stats.pending_orders,
                            stats.cancelled_orders,
                            stats.refunded_orders
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
        }

        function refreshData() {
            window.location.reload();
        }

        function exportTransactions() {
            alert('<?= __('exporting_transactions') ?>...');
        }

        function viewDetails(orderId) {
            alert('<?= __('view_details') ?> #' + orderId);
        }

        // Chart Period Controls
        function updateChartPeriod(period) {
            // Get current URL parameters
            const urlParams = new URLSearchParams(window.location.search);

            // Update or add period parameter
            urlParams.set('period', period);

            // Reload page with new period
            window.location.search = urlParams.toString();
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts();

            // Add event listeners to chart buttons
            document.querySelectorAll('.chart-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const period = this.getAttribute('data-period');
                    updateChartPeriod(period);
                });
            });
        });
    </script>
</body>

</html>