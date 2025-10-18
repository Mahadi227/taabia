<?php

/**
 * Payouts Management Page - Bilingual Version
 * Professional LMS Interface with Language Support
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

$filters = [
    'status' => $_GET['status'] ?? '',
    'year' => $_GET['year'] ?? date('Y'),
    'sort' => $_GET['sort'] ?? 'recent'
];

try {
    // Build query with filters
    $where_conditions = ["instructor_id = ?"];
    $params = [$instructor_id];

    if ($filters['status']) {
        $where_conditions[] = "status = ?";
        $params[] = $filters['status'];
    }

    if ($filters['year']) {
        $where_conditions[] = "YEAR(created_at) = ?";
        $params[] = $filters['year'];
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Determine sort order
    $order_by = match ($filters['sort']) {
        'recent' => 'created_at DESC',
        'oldest' => 'created_at ASC',
        'amount' => 'amount DESC',
        'status' => 'status ASC',
        default => 'created_at DESC'
    };

    // Get payouts
    $stmt = $pdo->prepare("
        SELECT 
            id,
            amount,
            method,
            transaction_ref,
            status,
            created_at,
            processed_at,
            notes
        FROM payouts 
        WHERE $where_clause
        ORDER BY $order_by
    ");
    $stmt->execute($params);
    $payouts = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_payouts,
            SUM(amount) as total_amount,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_payouts,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payouts,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_payouts,
            AVG(amount) as avg_payout
        FROM payouts 
        WHERE instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $stats = $stmt->fetch();

    // Get monthly payout data
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(amount) as total_amount,
            COUNT(*) as payout_count
        FROM payouts 
        WHERE instructor_id = ? 
          AND YEAR(created_at) = ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$instructor_id, $filters['year']]);
    $monthly_data = $stmt->fetchAll();

    // Get available balance
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(oi.price * oi.quantity), 0) as total_earnings,
            COALESCE(SUM(p.amount), 0) as total_payouts
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN courses c ON oi.course_id = c.id
        LEFT JOIN payouts p ON c.instructor_id = p.instructor_id AND p.status = 'approved'
        WHERE c.instructor_id = ? AND o.status = 'completed'
    ");
    $stmt->execute([$instructor_id]);
    $balance_data = $stmt->fetch();

    $available_balance = $balance_data['total_earnings'] - $balance_data['total_payouts'];
} catch (PDOException $e) {
    error_log("Payouts Error: " . $e->getMessage());
    $payouts = [];
    $stats = [
        'total_payouts' => 0,
        'total_amount' => 0,
        'approved_payouts' => 0,
        'pending_payouts' => 0,
        'rejected_payouts' => 0,
        'avg_payout' => 0
    ];
    $monthly_data = [];
    $balance_data = [
        'total_earnings' => 0,
        'total_payouts' => 0
    ];
    $available_balance = 0;
}

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payouts') ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <style>
        /* Professional LMS Payouts Design */
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

        /* Balance Card */
        .balance-card {
            background: linear-gradient(135deg, var(--success-color), var(--success-dark));
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            color: white;
        }

        .balance-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .balance-amount {
            font-size: 3rem;
            font-weight: 800;
            margin: 0.5rem 0;
        }

        .balance-info {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }

        .balance-info-item {
            display: flex;
            flex-direction: column;
        }

        .balance-info-label {
            font-size: 0.85rem;
            opacity: 0.8;
        }

        .balance-info-value {
            font-size: 1.25rem;
            font-weight: 600;
        }

        /* Statistics Grid */
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

        .stat-card.approved {
            border-left-color: var(--success-color);
        }

        .stat-card.pending {
            border-left-color: var(--warning-color);
        }

        .stat-card.rejected {
            border-left-color: var(--danger-color);
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

        .stat-icon.approved {
            background: linear-gradient(135deg, var(--success-color), var(--success-dark));
        }

        .stat-icon.pending {
            background: linear-gradient(135deg, var(--warning-color), var(--warning-dark));
        }

        .stat-icon.rejected {
            background: linear-gradient(135deg, var(--danger-color), #c81e1e);
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

        /* Chart Section */
        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
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

        .btn-success {
            background: var(--success-color);
            color: white;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            background: var(--success-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
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

        /* Payouts Table */
        .payouts-table-container {
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

        .payouts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payouts-table th {
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

        .payouts-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--gray-100);
            color: var(--gray-900);
            vertical-align: middle;
        }

        .payouts-table tbody tr:hover {
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

        .status-approved {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
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

            .balance-info {
                flex-direction: column;
                gap: 1rem;
            }

            .payouts-table {
                font-size: 0.85rem;
            }

            .payouts-table th,
            .payouts-table td {
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
                            <i class="fas fa-money-bill-wave"></i>
                            <?= __('payouts') ?>
                        </h1>
                        <p class="page-subtitle"><?= __('manage_your_payout_requests') ?></p>
                    </div>
                    <div class="page-actions">
                        <button class="btn btn-outline" onclick="refreshData()">
                            <i class="fas fa-sync-alt"></i> <?= __('refresh') ?>
                        </button>
                        <button class="btn btn-success" onclick="requestPayout()">
                            <i class="fas fa-plus"></i> <?= __('request_payout') ?>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Available Balance Card -->
            <div class="balance-card">
                <div class="balance-label"><?= __('available_balance') ?></div>
                <div class="balance-amount">$<?= number_format($available_balance, 2) ?></div>
                <div class="balance-info">
                    <div class="balance-info-item">
                        <span class="balance-info-label"><?= __('total_earnings') ?></span>
                        <span class="balance-info-value">$<?= number_format($balance_data['total_earnings'], 2) ?></span>
                    </div>
                    <div class="balance-info-item">
                        <span class="balance-info-label"><?= __('total_paid_out') ?></span>
                        <span class="balance-info-value">$<?= number_format($balance_data['total_payouts'], 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card approved">
                    <div class="stat-header">
                        <div class="stat-icon approved">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['approved_payouts'] ?? 0) ?></div>
                    <div class="stat-label"><?= __('approved_payouts') ?></div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-header">
                        <div class="stat-icon pending">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['pending_payouts'] ?? 0) ?></div>
                    <div class="stat-label"><?= __('pending_payouts') ?></div>
                </div>

                <div class="stat-card rejected">
                    <div class="stat-header">
                        <div class="stat-icon rejected">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?= number_format($stats['rejected_payouts'] ?? 0) ?></div>
                    <div class="stat-label"><?= __('rejected_payouts') ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="stat-value">$<?= number_format($stats['avg_payout'] ?? 0, 2) ?></div>
                    <div class="stat-label"><?= __('average_payout') ?></div>
                </div>
            </div>

            <!-- Monthly Payouts Chart -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title"><?= __('monthly_payouts') ?></h3>
                </div>
                <div style="position: relative; height: 300px;">
                    <canvas id="payoutsChart"></canvas>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label"><?= __('filter_by_status') ?></label>
                        <select name="status" class="filter-input">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="approved" <?= $filters['status'] == 'approved' ? 'selected' : '' ?>>
                                <?= __('approved') ?>
                            </option>
                            <option value="pending" <?= $filters['status'] == 'pending' ? 'selected' : '' ?>>
                                <?= __('pending') ?>
                            </option>
                            <option value="rejected" <?= $filters['status'] == 'rejected' ? 'selected' : '' ?>>
                                <?= __('rejected') ?>
                            </option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label"><?= __('filter_by_year') ?></label>
                        <select name="year" class="filter-input">
                            <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                <option value="<?= $year ?>" <?= $filters['year'] == $year ? 'selected' : '' ?>>
                                    <?= $year ?>
                                </option>
                            <?php endfor; ?>
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
                            <option value="amount" <?= $filters['sort'] == 'amount' ? 'selected' : '' ?>>
                                <?= __('highest_amount') ?>
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

            <!-- Payouts Table -->
            <div class="payouts-table-container">
                <div class="table-header">
                    <h3 class="table-title"><?= __('payout_history') ?></h3>
                    <div>
                        <span style="color: var(--gray-600); font-size: 0.9rem;">
                            <?= count($payouts) ?> <?= __('payouts_found') ?>
                        </span>
                    </div>
                </div>

                <?php if (empty($payouts)): ?>
                    <div class="empty-state">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3><?= __('no_payouts_found') ?></h3>
                        <p><?= __('request_your_first_payout') ?></p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="payouts-table">
                            <thead>
                                <tr>
                                    <th><?= __('payout_id') ?></th>
                                    <th><?= __('amount') ?></th>
                                    <th><?= __('payment_method') ?></th>
                                    <th><?= __('transaction_ref') ?></th>
                                    <th><?= __('requested_date') ?></th>
                                    <th><?= __('processed_date') ?></th>
                                    <th><?= __('status') ?></th>
                                    <th><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payouts as $payout): ?>
                                    <tr>
                                        <td>
                                            <strong>#<?= htmlspecialchars($payout['id']) ?></strong>
                                        </td>
                                        <td>
                                            <strong>$<?= number_format($payout['amount'], 2) ?></strong>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($payout['method'] ?? __('not_specified')) ?>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($payout['transaction_ref'] ?? '-') ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($payout['created_at'])) ?>
                                        </td>
                                        <td>
                                            <?= $payout['processed_at'] ? date('M j, Y', strtotime($payout['processed_at'])) : '-' ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= $payout['status'] ?>">
                                                <?= __($payout['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem;"
                                                onclick="viewDetails(<?= $payout['id'] ?>)">
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
        const monthlyData = <?= json_encode($monthly_data) ?>;

        function initializeChart() {
            const ctx = document.getElementById('payoutsChart').getContext('2d');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(item => {
                        const date = new Date(item.month + '-01');
                        return date.toLocaleDateString('<?= $_SESSION['user_language'] ?? 'fr' ?>', {
                            month: 'short',
                            year: 'numeric'
                        });
                    }),
                    datasets: [{
                        label: '<?= __('monthly_payouts') ?>',
                        data: monthlyData.map(item => item.total_amount),
                        backgroundColor: '#10b981',
                        borderRadius: 8,
                        borderWidth: 0
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
        }

        function refreshData() {
            window.location.reload();
        }

        function requestPayout() {
            alert('<?= __('request_payout_feature_coming_soon') ?>');
        }

        function viewDetails(payoutId) {
            alert('<?= __('view_details') ?> #' + payoutId);
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeChart();
        });
    </script>
</body>

</html>