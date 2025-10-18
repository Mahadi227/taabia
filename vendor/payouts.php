<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Initialize variables
$available_balance = 0;
$pending_payouts = 0;
$total_payouts = 0;
$payout_requests = [];
$payout_history = [];

// Pagination
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($current_page - 1) * $limit;

// Search and filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

try {
    // Check if required tables exist
    $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    $payoutRequestsExists = $pdo->query("SHOW TABLES LIKE 'payout_requests'")->rowCount() > 0;
    
    // Debug information
    if (!$transactionsExists) {
        error_log("Transactions table does not exist");
    }
    if (!$payoutRequestsExists) {
        error_log("Payout_requests table does not exist");
    }
    
    if ($transactionsExists) {
        // Calculate available balance (completed transactions - paid out)
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(amount), 0) 
            FROM transactions 
            WHERE vendor_id = ? AND type = 'product_purchase' AND status = 'completed'
        ");
        if ($stmt->execute([$vendor_id])) {
            $total_earnings = $stmt->fetchColumn();
        }
        
        if ($payoutRequestsExists) {
            // Get total paid out
            $stmt = $pdo->prepare("
                SELECT IFNULL(SUM(amount), 0) 
                FROM payout_requests 
                WHERE user_id = ? AND status = 'processed'
            ");
            if ($stmt->execute([$vendor_id])) {
                $total_paid_out = $stmt->fetchColumn();
            }
            
            // Get pending payouts
            $stmt = $pdo->prepare("
                SELECT IFNULL(SUM(amount), 0) 
                FROM payout_requests 
                WHERE user_id = ? AND status = 'pending'
            ");
            if ($stmt->execute([$vendor_id])) {
                $pending_payouts = $stmt->fetchColumn();
            }
            
            $available_balance = $total_earnings - $total_paid_out - $pending_payouts;
            $total_payouts = $total_paid_out;
            
            // Build query for payout requests with filters
            $query = "SELECT * FROM payout_requests WHERE user_id = ?";
            $params = [$vendor_id];
            
            if (!empty($search)) {
                $query .= " AND (payment_method LIKE ? OR account_details LIKE ? OR id LIKE ?)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
            
            if (!empty($status_filter)) {
                $query .= " AND status = ?";
                $params[] = $status_filter;
            }
            
            if (!empty($date_from)) {
                $query .= " AND DATE(created_at) >= ?";
                $params[] = $date_from;
            }
            
            if (!empty($date_to)) {
                $query .= " AND DATE(created_at) <= ?";
                $params[] = $date_to;
            }
            
            // Get total count for pagination
            $count_query = str_replace("SELECT *", "SELECT COUNT(*)", $query);
            $count_stmt = $pdo->prepare($count_query);
            $count_stmt->execute($params);
            $total_records = $count_stmt->fetchColumn();
            $total_pages = ceil($total_records / $limit);
            
            // Get paginated results
            $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $pdo->prepare($query);
            if ($stmt->execute($params)) {
                $payout_requests = $stmt->fetchAll();
            }
        } else {
            $available_balance = $total_earnings;
        }
    }
} catch (PDOException $e) {
    error_log("Database error in vendor payouts: " . $e->getMessage());
    $error = __('database_error') . ': ' . $e->getMessage();
} catch (Exception $e) {
    error_log("General error in vendor payouts: " . $e->getMessage());
    $error = 'An error occurred: ' . $e->getMessage();
}

// Handle payout request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = __('csrf_error');
    } else {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = sanitize($_POST['payment_method']);
        $bank_details = sanitize($_POST['bank_details']);
        
        if ($amount === false || $amount <= 0) {
            $error = __('invalid_amount');
        } elseif ($amount > $available_balance) {
            $error = __('amount_exceeds_balance');
        } elseif ($amount < 50) {
            $error = __('minimum_payout_amount');
        } elseif (empty($payment_method)) {
            $error = __('payment_method_required');
        } elseif (empty($bank_details)) {
            $error = __('bank_details_required');
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO payout_requests (user_id, amount, status, payment_method, account_details, created_at)
                    VALUES (?, ?, 'pending', ?, ?, NOW())
                ");
                
                if ($stmt->execute([$vendor_id, $amount, $payment_method, $bank_details])) {
                    $message = __('payout_request_submitted');
                    $available_balance -= $amount;
                    $pending_payouts += $amount;
                    
                    // Refresh payout requests
                    $stmt = $pdo->prepare("
                        SELECT * FROM payout_requests 
                        WHERE user_id = ? 
                        ORDER BY created_at DESC 
                        LIMIT 10
                    ");
                    if ($stmt->execute([$vendor_id])) {
                        $payout_requests = $stmt->fetchAll();
                    }
                } else {
                    $error = __('payout_request_error');
                }
            } catch (PDOException $e) {
                error_log("Payout request error: " . $e->getMessage());
                $error = __('payout_request_error');
            }
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Export functionality
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="payout_requests_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Date', 'Amount', 'Payment Method', 'Status', 'Details']);
    
    $export_query = "SELECT * FROM payout_requests WHERE user_id = ? ORDER BY created_at DESC";
    $export_stmt = $pdo->prepare($export_query);
    $export_stmt->execute([$vendor_id]);
    
    while ($row = $export_stmt->fetch()) {
        fputcsv($output, [
            $row['id'],
            date('Y-m-d H:i', strtotime($row['created_at'])),
            number_format($row['amount'], 2) . ' GHS',
            $row['payment_method'],
            ucfirst($row['status']),
            $row['account_details']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= get_current_language() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_payments') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #00796b;
            --primary-dark: #00695c;
            --secondary-color: #f0f2f5;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --text-primary: #333;
            --text-secondary: #666;
            --border-color: #ddd;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --border-radius: 10px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--secondary-color);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .sidebar {
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            position: fixed;
            padding: 2rem 1.5rem;
            overflow-y: auto;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 1rem 1.2rem;
            margin-bottom: 0.5rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
            font-weight: 500;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar a i {
            margin-right: 0.8rem;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card h3 {
            color: var(--primary-color);
            margin-bottom: 0.8rem;
            font-size: 1.1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .stat-card h3 i {
            margin-right: 0.5rem;
        }

        .stat-card .value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .payout-form {
            background: white;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .payout-form h3 {
            margin-bottom: 2rem;
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .payout-form h3 i {
            margin-right: 0.8rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-family: inherit;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 107, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group small {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 0.3rem;
            display: block;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 2rem;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            gap: 0.5rem;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: var(--success-color);
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-warning {
            background: var(--warning-color);
        }

        .btn-warning:hover {
            background: #e68900;
        }

        .payouts-section {
            background: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .payouts-section h3 {
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            font-size: 1.3rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .payouts-section h3 i {
            margin-right: 0.8rem;
        }

        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        .filter-group input,
        .filter-group select {
            padding: 0.6rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .payouts-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .payouts-table th,
        .payouts-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .payouts-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payouts-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-approved {
            background: #cce5ff;
            color: #004085;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: var(--success-color);
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left-color: var(--danger-color);
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left-color: var(--warning-color);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1.5rem;
        }

        .empty-state h4 {
            font-size: 1.3rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
        }

        .pagination a:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination .current {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination .disabled {
            color: #ccc;
            cursor: not-allowed;
        }

        .export-btn {
            background: var(--success-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .export-btn:hover {
            background: #45a049;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 1rem;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .filters {
                flex-direction: column;
                align-items: stretch;
            }
            
            .payouts-table {
                font-size: 0.9rem;
            }
            
            .payouts-table th,
            .payouts-table td {
                padding: 0.5rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .payouts-table {
                display: block;
                overflow-x: auto;
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
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php" class="active"><i class="fas fa-hand-holding-usd"></i> <?= __('payments') ?></a>
        <a href="profile.php"><i class="fas fa-user"></i> <?= __('profile') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1><?= __('my_payments') ?></h1>
            <div class="header-actions">
                <a href="?export=csv" class="export-btn">
                    <i class="fas fa-download"></i> <?= __('export_csv') ?>
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-wallet"></i> <?= __('available_balance') ?></h3>
                <div class="value"><?= number_format($available_balance, 2) ?> GHS</div>
                <p><?= __('available_balance_desc') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> <?= __('pending_payouts') ?></h3>
                <div class="value"><?= number_format($pending_payouts, 2) ?> GHS</div>
                <p><?= __('pending_payouts_desc') ?></p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> <?= __('total_paid') ?></h3>
                <div class="value"><?= number_format($total_payouts, 2) ?> GHS</div>
                <p><?= __('total_paid_desc') ?></p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $message ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
            </div>
        <?php endif; ?>

        <div class="payout-form">
            <h3><i class="fas fa-hand-holding-usd"></i> <?= __('request_payout') ?></h3>
            
            <?php if ($available_balance >= 50): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount"><?= __('amount') ?> (GHS) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="50" max="<?= $available_balance ?>" required>
                        <small><?= __('minimum_payout_amount') ?>: 50 GHS</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method"><?= __('payment_method') ?> *</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value=""><?= __('select_payment_method') ?></option>
                            <option value="Mobile Money"><?= __('mobile_money') ?></option>
                            <option value="Bank Transfer"><?= __('bank_transfer') ?></option>
                            <option value="PayPal"><?= __('paypal') ?></option>
                            <option value="Cash"><?= __('cash') ?></option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bank_details"><?= __('payment_details') ?> *</label>
                    <textarea id="bank_details" name="bank_details" placeholder="<?= __('payment_details_placeholder') ?>" required></textarea>
                </div>
                
                <button type="submit" name="request_payout" class="btn">
                    <i class="fas fa-paper-plane"></i> <?= __('submit_request') ?>
                </button>
            </form>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-wallet"></i>
                <h4><?= __('insufficient_balance') ?></h4>
                <p><?= __('insufficient_balance_desc') ?></p>
                <a href="earnings.php" class="btn"><?= __('view_earnings') ?></a>
            </div>
            <?php endif; ?>
        </div>

        <div class="payouts-section">
            <h3>
                <span><i class="fas fa-list"></i> <?= __('payout_history') ?></span>
            </h3>
            
            <?php if (!empty($payout_requests) || !empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
            <div class="filters">
                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label for="search"><?= __('search') ?></label>
                        <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="<?= __('search_placeholder') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="status"><?= __('status') ?></label>
                        <select id="status" name="status">
                            <option value=""><?= __('all_statuses') ?></option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                            <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>><?= __('approved') ?></option>
                            <option value="processed" <?= $status_filter === 'processed' ? 'selected' : '' ?>><?= __('processed') ?></option>
                            <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>><?= __('rejected') ?></option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from"><?= __('date_from') ?></label>
                        <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to"><?= __('date_to') ?></label>
                        <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i> <?= __('filter') ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if (empty($payout_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding-usd"></i>
                <h4><?= __('no_payout_requests') ?></h4>
                <p><?= __('no_payout_requests_desc') ?></p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="payouts-table">
                    <thead>
                        <tr>
                            <th><?= __('date') ?></th>
                            <th><?= __('amount') ?></th>
                            <th><?= __('payment_method') ?></th>
                            <th><?= __('status') ?></th>
                            <th><?= __('details') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payout_requests as $payout): ?>
                        <tr>
                            <td>
                                <strong><?= date('d/m/Y', strtotime($payout['created_at'])) ?></strong><br>
                                <small><?= date('H:i', strtotime($payout['created_at'])) ?></small>
                            </td>
                            <td>
                                <strong><?= number_format($payout['amount'], 2) ?> GHS</strong>
                            </td>
                            <td>
                                <i class="fas fa-credit-card"></i> <?= htmlspecialchars($payout['payment_method']) ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $payout['status'] ?>">
                                    <?= ucfirst($payout['status']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= htmlspecialchars($payout['account_details']) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                    <a href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">
                        <i class="fas fa-chevron-left"></i> <?= __('previous') ?>
                    </a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($status_filter) ?>&date_from=<?= urlencode($date_from) ?>&date_to=<?= urlencode($date_to) ?>">
                        <?= __('next') ?> <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const amountInput = document.getElementById('amount');
            const availableBalance = <?= $available_balance ?>;

            if (form && amountInput) {
                form.addEventListener('submit', function(e) {
                    const amount = parseFloat(amountInput.value);
                    
                    if (amount > availableBalance) {
                        e.preventDefault();
                        alert('<?= __('amount_exceeds_balance') ?>');
                        return false;
                    }
                    
                    if (amount < 50) {
                        e.preventDefault();
                        alert('<?= __('minimum_payout_amount') ?>');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>