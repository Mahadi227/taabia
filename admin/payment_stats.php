<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

// Filter by month (e.g., 2025-07)
$monthFilter = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$startDate = $monthFilter . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Initialize variables
$totalRevenue = 0;
$totalPaid = 0;
$pendingBalance = 0;
$topInstructors = [];
$monthlyData = [];
$chartLabels = [];
$chartValues = [];

// Global revenue
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total_revenue FROM transactions WHERE created_at BETWEEN ? AND ?");
    if ($stmt->execute([$startDate, $endDate])) {
        $totalRevenue = $stmt->fetchColumn() ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payment_stats.php revenue: " . $e->getMessage());
}

// Total paid to instructors
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM payouts WHERE created_at BETWEEN ? AND ?");
    if ($stmt->execute([$startDate, $endDate])) {
        $totalPaid = $stmt->fetchColumn() ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payment_stats.php payouts: " . $e->getMessage());
}

// Pending balance
$pendingBalance = $totalRevenue - $totalPaid;

// Top instructors (top 5)
try {
    $stmt = $pdo->prepare("
        SELECT u.full_name, SUM(e.amount) as total_earned 
        FROM earnings e
        JOIN users u ON e.instructor_id = u.id
        WHERE u.role = 'instructor' AND e.created_at BETWEEN ? AND ?
        GROUP BY e.instructor_id
        ORDER BY total_earned DESC
        LIMIT 5
    ");
    if ($stmt->execute([$startDate, $endDate])) {
        $topInstructors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payment_stats.php top instructors: " . $e->getMessage());
}

// Prepare chart data
try {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total
        FROM transactions
        GROUP BY month
        ORDER BY month ASC
    ");
    if ($stmt->execute()) {
        $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $chartLabels = array_column($monthlyData, 'month');
        $chartValues = array_column($monthlyData, 'total');
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payment_stats.php chart data: " . $e->getMessage());
}

// Calculate additional statistics
$totalTransactions = 0;
$successfulTransactions = 0;
$averageTransaction = 0;

try {
    $stats_stmt = $pdo->query("SELECT 
        COUNT(*) as total_transactions,
        COUNT(CASE WHEN status = 'success' THEN 1 END) as successful_transactions,
        AVG(amount) as average_transaction
        FROM transactions");
    if ($stats_stmt->execute()) {
        $stats = $stats_stmt->fetch();
        $totalTransactions = $stats['total_transactions'] ?? 0;
        $successfulTransactions = $stats['successful_transactions'] ?? 0;
        $averageTransaction = $stats['average_transaction'] ?? 0;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/payment_stats.php stats: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payment_statistics') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
        /* Admin Language Switcher Styles */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .admin-language-btn:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: #f8f9fa;
        }

        .admin-language-item.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .language-flag {
            font-size: 1rem;
        }

        .language-name {
            flex: 1;
        }

        .admin-language-item i {
            color: var(--success-color);
            font-size: 0.75rem;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-chart-bar"></i> <?= __('payment_statistics') ?></h1>
                        <p><?= __('payment_analytics_dashboard') ?></p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 20px;">
                        <!-- Language Switcher -->
                        <div class="admin-language-switcher">
                            <div class="admin-language-dropdown">
                                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                                    <i class="fas fa-globe"></i>
                                    <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="admin-language-menu" id="adminLanguageDropdown">
                                    <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                        <span class="language-flag">🇫🇷</span>
                                        <span class="language-name">Français</span>
                                        <?php if (getCurrentLanguage() == 'fr'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                        <span class="language-flag">🇬🇧</span>
                                        <span class="language-name">English</span>
                                        <?php if (getCurrentLanguage() == 'en'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- User Menu -->
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Filter Form -->
            <div class="search-filters">
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="form-label"><?= __('filter_by_month') ?></label>
                        <input type="month" name="month" class="form-control"
                            value="<?= htmlspecialchars($monthFilter) ?>">
                    </div>

                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            <?= __('apply') ?>
                        </button>
                        <a href="payment_stats.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            <?= __('reset') ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format($totalRevenue, 2) ?></div>
                            <div class="stat-label"><?= __('total_revenue') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon success">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format($totalPaid, 2) ?></div>
                            <div class="stat-label"><?= __('paid_to_instructors') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format($pendingBalance, 2) ?></div>
                            <div class="stat-label"><?= __('pending_balance') ?></div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($totalTransactions) ?></div>
                            <div class="stat-label"><?= __('total_transactions') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('revenue_evolution') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= count($monthlyData) ?> <?= __('months') ?></span>
                        <span class="badge badge-success">GHS<?= number_format($averageTransaction, 2) ?> <?= __('average') ?></span>
                    </div>
                </div>

                <div style="padding: var(--spacing-lg);">
                    <canvas id="revenueChart" width="600" height="300"></canvas>
                </div>
            </div>

            <!-- Top Instructors -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏆 <?= __('top_instructors_month') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= count($topInstructors) ?> <?= __('instructors') ?></span>
                    </div>
                </div>

                <div class="table-container">
                    <?php if (empty($topInstructors)): ?>
                        <div class="text-center" style="padding: 3rem;">
                            <i class="fas fa-trophy" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                            <p><?= __('no_instructors_found_month') ?></p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><?= __('rank') ?></th>
                                    <th><?= __('name') ?></th>
                                    <th><?= __('amount_earned') ?></th>
                                    <th><?= __('percentage') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalEarned = array_sum(array_column($topInstructors, 'total_earned'));
                                foreach ($topInstructors as $index => $ins):
                                    $percentage = $totalEarned > 0 ? ($ins['total_earned'] / $totalEarned) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                #<?= $index + 1 ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($ins['full_name']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                GHS<?= number_format($ins['total_earned'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= number_format($percentage, 1) ?>%
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Export Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📤 Exporter les Statistiques</h3>
                </div>

                <div style="padding: var(--spacing-lg);">
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="export_payment_stats_pdf.php?month=<?= urlencode($monthFilter) ?>"
                            class="btn btn-primary" target="_blank">
                            <i class="fas fa-file-pdf"></i>
                            Exporter en PDF
                        </a>
                        <a href="export_payment_stats_excel.php?month=<?= urlencode($monthFilter) ?>"
                            class="btn btn-success" target="_blank">
                            <i class="fas fa-file-excel"></i>
                            Exporter en Excel
                        </a>
                        <a href="export_stats_pdf.php?month=<?= urlencode($monthFilter) ?>"
                            class="btn btn-info" target="_blank">
                            <i class="fas fa-chart-bar"></i>
                            Rapport Complet
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize Chart.js
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chartLabels) ?>,
                datasets: [{
                    label: 'Chiffre d\'affaires mensuel',
                    data: <?= json_encode($chartValues) ?>,
                    borderColor: 'var(--primary-color)',
                    backgroundColor: 'rgba(0, 121, 96, 0.1)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: 'var(--primary-color)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                family: 'Inter',
                                size: 14
                            },
                            color: 'var(--text-primary)'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'var(--border-color)'
                        },
                        ticks: {
                            color: 'var(--text-secondary)',
                            font: {
                                family: 'Inter',
                                size: 12
                            }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'var(--border-color)'
                        },
                        ticks: {
                            color: 'var(--text-secondary)',
                            font: {
                                family: 'Inter',
                                size: 12
                            },
                            callback: function(value) {
                                return 'GHS ' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                elements: {
                    point: {
                        hoverBackgroundColor: 'var(--primary-color)'
                    }
                }
            }
        });

        // Add smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transform = 'scale(1.01)';
                    this.style.boxShadow = 'var(--shadow-light)';
                });

                row.addEventListener('mouseleave', function() {
                    this.style.transform = 'scale(1)';
                    this.style.boxShadow = 'none';
                });
            });

            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>

</html>