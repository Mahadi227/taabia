<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

// Handle verification actions
if ($_POST['action'] ?? false) {
    $action = $_POST['action'];
    $verification_id = $_POST['verification_id'] ?? null;

    switch ($action) {
        case 'delete_verification':
            if ($verification_id) {
                $delete_query = "DELETE FROM certificate_verifications WHERE id = ?";
                $delete_stmt = $pdo->prepare($delete_query);
                $delete_stmt->execute([$verification_id]);
                $_SESSION['success_message'] = 'Verification record deleted successfully.';
            }
            break;

        case 'mark_suspicious':
            if ($verification_id) {
                $update_query = "UPDATE certificate_verifications SET verification_status = 'suspicious' WHERE id = ?";
                $update_stmt = $pdo->prepare($update_query);
                $update_stmt->execute([$verification_id]);
                $_SESSION['success_message'] = 'Verification marked as suspicious.';
            }
            break;
    }

    header('Location: certificate_verification.php');
    exit();
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$sort_by = $_GET['sort'] ?? 'verified_at';
$sort_order = $_GET['order'] ?? 'DESC';
$page = (int)($_GET['page'] ?? 1);
$per_page = 20;

// Validate sort parameters
$allowed_sorts = ['verified_at', 'certificate_number', 'student_name', 'course_title', 'verification_status'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'verified_at';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'DESC';
}

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(cc.certificate_number LIKE ? OR cc.student_name LIKE ? OR cc.course_title LIKE ? OR cv.verification_code LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "cv.verification_status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(cv.verified_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(cv.verified_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM certificate_verifications cv
    INNER JOIN course_certificates cc ON cv.certificate_id = cc.id
    $where_clause
";
$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_verifications = $count_stmt->fetch()['total'];
$total_pages = ceil($total_verifications / $per_page);

// Get verifications with pagination
$offset = ($page - 1) * $per_page;
$verifications_query = "
    SELECT cv.*, 
           cc.certificate_number,
           cc.student_name,
           cc.course_title,
           cc.instructor_name,
           cc.issue_date,
           cc.final_grade
    FROM certificate_verifications cv
    INNER JOIN course_certificates cc ON cv.certificate_id = cc.id
    $where_clause
    ORDER BY cv.$sort_by $sort_order
    LIMIT $per_page OFFSET $offset
";

$verifications_stmt = $pdo->prepare($verifications_query);
$verifications_stmt->execute($params);
$verifications = $verifications_stmt->fetchAll();

// Get statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_verifications,
        COUNT(DISTINCT certificate_id) as unique_certificates,
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(CASE WHEN verification_status = 'success' THEN 1 END) as successful_verifications,
        COUNT(CASE WHEN verification_status = 'failed' THEN 1 END) as failed_verifications,
        COUNT(CASE WHEN verification_status = 'suspicious' THEN 1 END) as suspicious_verifications,
        COUNT(CASE WHEN DATE(verified_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
        COUNT(CASE WHEN DATE(verified_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
        COUNT(CASE WHEN DATE(verified_at) = CURDATE() THEN 1 END) as today
    FROM certificate_verifications
";
$stats_stmt = $pdo->query($stats_query);
$stats = $stats_stmt->fetch();

// Get verification trends (last 30 days)
$trends_query = "
    SELECT 
        DATE(verified_at) as date,
        COUNT(*) as verification_count,
        COUNT(CASE WHEN verification_status = 'success' THEN 1 END) as successful_count,
        COUNT(CASE WHEN verification_status = 'failed' THEN 1 END) as failed_count,
        COUNT(CASE WHEN verification_status = 'suspicious' THEN 1 END) as suspicious_count
    FROM certificate_verifications
    WHERE verified_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(verified_at)
    ORDER BY date DESC
    LIMIT 30
";
$trends_stmt = $pdo->query($trends_query);
$trends = $trends_stmt->fetchAll();

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('certificate_verification') ?> - <?= __('admin_panel') ?></title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .verification-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-number.primary {
            color: #667eea;
        }

        .stat-number.success {
            color: #38a169;
        }

        .stat-number.warning {
            color: #ed8936;
        }

        .stat-number.danger {
            color: #e53e3e;
        }

        .stat-number.info {
            color: #3182ce;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .filters-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .verifications-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .table-title {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .table-content {
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
            border-bottom: 1px solid #f0f0f0;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-suspicious {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
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
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ddd;
        }

        .sortable {
            cursor: pointer;
            user-select: none;
        }

        .sortable:hover {
            background: #e9ecef;
        }

        .sort-indicator {
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            margin: 0 0 1.5rem 0;
            color: #333;
            font-size: 1.2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }

        .ip-address {
            font-family: monospace;
            font-size: 0.9rem;
            color: #666;
        }

        .user-agent {
            font-size: 0.8rem;
            color: #999;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1><?= __('certificate_verification') ?></h1>
                <p><?= __('manage_certificate_verifications_and_security') ?></p>
            </div>

            <div class="verification-container">
                <!-- Success/Error Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="success-message">
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="error-message">
                        <?= htmlspecialchars($_SESSION['error_message']) ?>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number primary"><?= number_format($stats['total_verifications']) ?></div>
                        <div class="stat-label"><?= __('total_verifications') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number success"><?= number_format($stats['successful_verifications']) ?></div>
                        <div class="stat-label"><?= __('successful_verifications') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number warning"><?= number_format($stats['failed_verifications']) ?></div>
                        <div class="stat-label"><?= __('failed_verifications') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number danger"><?= number_format($stats['suspicious_verifications']) ?></div>
                        <div class="stat-label"><?= __('suspicious_verifications') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number info"><?= number_format($stats['unique_certificates']) ?></div>
                        <div class="stat-label"><?= __('unique_certificates') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number primary"><?= number_format($stats['unique_ips']) ?></div>
                        <div class="stat-label"><?= __('unique_ip_addresses') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number success"><?= number_format($stats['last_30_days']) ?></div>
                        <div class="stat-label"><?= __('last_30_days') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number info"><?= number_format($stats['last_7_days']) ?></div>
                        <div class="stat-label"><?= __('last_7_days') ?></div>
                    </div>
                </div>

                <!-- Verification Trends Chart -->
                <?php if (!empty($trends)): ?>
                    <div class="chart-container">
                        <h3 class="chart-title"><?= __('verification_trends') ?> (<?= __('last_30_days') ?>)</h3>
                        <div class="chart-wrapper">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="filters-section">
                    <h3><?= __('filters') ?></h3>
                    <form method="GET" class="filters-form">
                        <div class="filters-grid">
                            <div class="filter-group">
                                <label for="search"><?= __('search') ?></label>
                                <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
                                    placeholder="<?= __('search_verifications') ?>">
                            </div>

                            <div class="filter-group">
                                <label for="status"><?= __('status') ?></label>
                                <select id="status" name="status">
                                    <option value=""><?= __('all_statuses') ?></option>
                                    <option value="success" <?= $status_filter === 'success' ? 'selected' : '' ?>><?= __('successful') ?></option>
                                    <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>><?= __('failed') ?></option>
                                    <option value="suspicious" <?= $status_filter === 'suspicious' ? 'selected' : '' ?>><?= __('suspicious') ?></option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="date_from"><?= __('from_date') ?></label>
                                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
                            </div>

                            <div class="filter-group">
                                <label for="date_to"><?= __('to_date') ?></label>
                                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> <?= __('apply_filters') ?>
                            </button>
                            <a href="certificate_verification.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?= __('clear_filters') ?>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Verifications Table -->
                <div class="verifications-table">
                    <div class="table-header">
                        <h3 class="table-title"><?= __('verification_records') ?> (<?= number_format($total_verifications) ?>)</h3>
                    </div>

                    <div class="table-content">
                        <?php if (empty($verifications)): ?>
                            <div class="empty-state">
                                <i class="fas fa-search"></i>
                                <p><?= __('no_verifications_found') ?></p>
                            </div>
                        <?php else: ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="sortable" data-sort="verified_at">
                                            <?= __('verification_date') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'verified_at' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="certificate_number">
                                            <?= __('certificate_number') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'certificate_number' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="student_name">
                                            <?= __('student') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'student_name' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="course_title">
                                            <?= __('course') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'course_title' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th class="sortable" data-sort="verification_status">
                                            <?= __('status') ?>
                                            <span class="sort-indicator">
                                                <?= $sort_by === 'verification_status' ? ($sort_order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </span>
                                        </th>
                                        <th><?= __('ip_address') ?></th>
                                        <th><?= __('user_agent') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($verifications as $verification): ?>
                                        <tr>
                                            <td>
                                                <div><?= date('M j, Y', strtotime($verification['verified_at'])) ?></div>
                                                <small class="text-muted"><?= date('g:i A', strtotime($verification['verified_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="certificate-number"><?= htmlspecialchars($verification['certificate_number']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($verification['student_name']) ?></div>
                                            </td>
                                            <td>
                                                <div><?= htmlspecialchars($verification['course_title']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($verification['instructor_name']) ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= $verification['verification_status'] ?>">
                                                    <?= ucfirst($verification['verification_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="ip-address"><?= htmlspecialchars($verification['ip_address']) ?></div>
                                            </td>
                                            <td>
                                                <div class="user-agent" title="<?= htmlspecialchars($verification['user_agent']) ?>">
                                                    <?= htmlspecialchars($verification['user_agent']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="../public/verify_certificate.php?code=<?= urlencode($verification['verification_code']) ?>"
                                                        class="btn btn-primary btn-sm" target="_blank" title="<?= __('verify_certificate') ?>">
                                                        <i class="fas fa-check-circle"></i>
                                                    </a>

                                                    <?php if ($verification['verification_status'] !== 'suspicious'): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="mark_suspicious">
                                                            <input type="hidden" name="verification_id" value="<?= $verification['id'] ?>">
                                                            <button type="submit" class="btn btn-warning btn-sm"
                                                                title="<?= __('mark_as_suspicious') ?>"
                                                                onclick="return confirm('<?= __('confirm_mark_suspicious') ?>')">
                                                                <i class="fas fa-exclamation-triangle"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_verification">
                                                        <input type="hidden" name="verification_id" value="<?= $verification['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                            title="<?= __('delete_verification') ?>"
                                                            onclick="return confirm('<?= __('confirm_delete_verification') ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-left"></i></span>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        ?>

                        <?php if ($start_page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                            <?php if ($start_page > 2): ?>
                                <span>...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span>...</span>
                            <?php endif; ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-chevron-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Sorting functionality
        document.querySelectorAll('.sortable').forEach(header => {
            header.addEventListener('click', function() {
                const sortBy = this.dataset.sort;
                const currentSort = '<?= $sort_by ?>';
                const currentOrder = '<?= $sort_order ?>';

                let newOrder = 'ASC';
                if (sortBy === currentSort && currentOrder === 'ASC') {
                    newOrder = 'DESC';
                }

                const url = new URL(window.location);
                url.searchParams.set('sort', sortBy);
                url.searchParams.set('order', newOrder);
                url.searchParams.delete('page'); // Reset to first page

                window.location.href = url.toString();
            });
        });

        // Trends Chart
        <?php if (!empty($trends)): ?>
            const trendsCtx = document.getElementById('trendsChart').getContext('2d');
            new Chart(trendsCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_reverse(array_column($trends, 'date'))) ?>,
                    datasets: [{
                        label: '<?= __('total_verifications') ?>',
                        data: <?= json_encode(array_reverse(array_column($trends, 'verification_count'))) ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: '<?= __('successful_verifications') ?>',
                        data: <?= json_encode(array_reverse(array_column($trends, 'successful_count'))) ?>,
                        borderColor: '#38a169',
                        backgroundColor: 'rgba(56, 161, 105, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }, {
                        label: '<?= __('failed_verifications') ?>',
                        data: <?= json_encode(array_reverse(array_column($trends, 'failed_count'))) ?>,
                        borderColor: '#e53e3e',
                        backgroundColor: 'rgba(229, 62, 62, 0.1)',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>






