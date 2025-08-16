<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

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
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques de Paiement | Admin | TaaBia</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
            <p><?php
                $current_user = null;
                try {
                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([current_user_id()]);
                    $current_user = $stmt->fetch();
                } catch (PDOException $e) {
                    error_log("Error fetching current user: " . $e->getMessage());
                }
                echo htmlspecialchars($current_user['full_name'] ?? 'Administrateur');
            ?></p>
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
                <a href="products.php" class="nav-link">
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
                <a href="payment_stats.php" class="nav-link active">
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
                <h1 class="page-title">Statistiques de Paiement</h1>
                
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
            <!-- Filter Form -->
            <div class="search-filters">
                <form method="GET" class="filters-row">
                    <div class="filter-group">
                        <label class="form-label">Filtrer par mois</label>
                        <input type="month" name="month" class="form-control" 
                               value="<?= htmlspecialchars($monthFilter) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i>
                            Appliquer
                        </button>
                        <a href="payment_stats.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Réinitialiser
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
                            <div class="stat-label">Chiffre d'affaires</div>
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
                            <div class="stat-label">Payé aux instructeurs</div>
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
                            <div class="stat-label">Solde en attente</div>
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
                            <div class="stat-label">Total Transactions</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Évolution du Chiffre d'Affaires</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= count($monthlyData) ?> mois</span>
                        <span class="badge badge-success">GHS<?= number_format($averageTransaction, 2) ?> moyenne</span>
                    </div>
                </div>
                
                <div style="padding: var(--spacing-lg);">
                    <canvas id="revenueChart" width="600" height="300"></canvas>
                </div>
            </div>

            <!-- Top Instructors -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏆 Top Instructeurs du Mois</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= count($topInstructors) ?> instructeurs</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <?php if (empty($topInstructors)): ?>
                        <div class="text-center" style="padding: 3rem;">
                            <i class="fas fa-trophy" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                            <p>Aucun instructeur trouvé ce mois.</p>
                        </div>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rang</th>
                                    <th>Nom</th>
                                    <th>Montant gagné</th>
                                    <th>Pourcentage</th>
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
    </script>
</body>
</html>