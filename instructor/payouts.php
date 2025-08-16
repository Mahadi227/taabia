<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$filter_status = $_GET['status'] ?? '';
$filter_year = $_GET['year'] ?? date('Y');
$sort_by = $_GET['sort'] ?? 'recent';

try {
    // Build query with filters
    $where_conditions = ["instructor_id = ?"];
    $params = [$instructor_id];

    if ($filter_status) {
        $where_conditions[] = "status = ?";
        $params[] = $filter_status;
    }

    if ($filter_year) {
        $where_conditions[] = "YEAR(created_at) = ?";
        $params[] = $filter_year;
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Determine sort order
    $order_by = match($sort_by) {
        'recent' => 'created_at DESC',
        'oldest' => 'created_at ASC',
        'amount' => 'amount DESC',
        'status' => 'status ASC',
        default => 'created_at DESC'
    };

    // Get payouts with comprehensive data
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

    // Get monthly payout data for chart
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
    $stmt->execute([$instructor_id, $filter_year]);
    $monthly_data = $stmt->fetchAll();

    // Get available balance (earnings - payouts)
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
    error_log("Database error in payouts: " . $e->getMessage());
    $payouts = [];
    $stats = ['total_payouts' => 0, 'total_amount' => 0, 'approved_payouts' => 0, 'pending_payouts' => 0, 'rejected_payouts' => 0, 'avg_payout' => 0];
    $monthly_data = [];
    $available_balance = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes paiements | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p>Espace Formateur</p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    Nouveau cours
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    Mes étudiants
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    Devoirs à valider
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    Mes gains
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Transactions
                </a>
                <a href="payouts.php" class="instructor-nav-item active">
                    <i class="fas fa-money-bill-wave"></i>
                    Paiements
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <h1>Mes paiements</h1>
                <p>Gérez vos paiements et demandes de retrait</p>
            </div>

            <!-- Balance and Statistics Cards -->
            <div class="instructor-cards" style="margin-bottom: var(--spacing-6);">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-wallet"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Solde disponible</div>
                    <div class="instructor-card-value"><?= number_format($available_balance, 2) ?> GHS</div>
                    <div class="instructor-card-description">Montant disponible</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Total payouts</div>
                    <div class="instructor-card-value"><?= number_format($stats['total_amount'], 2) ?> GHS</div>
                    <div class="instructor-card-description">Tous paiements</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Payouts approuvés</div>
                    <div class="instructor-card-value"><?= $stats['approved_payouts'] ?></div>
                    <div class="instructor-card-description">Paiements traités</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">En attente</div>
                    <div class="instructor-card-value"><?= $stats['pending_payouts'] ?></div>
                    <div class="instructor-card-description">Paiements en cours</div>
                </div>
            </div>

            <!-- Request Payout Button -->
            <?php if ($available_balance > 0): ?>
                <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                    <div style="padding: var(--spacing-6);">
                        <div style="
                            background: var(--success-color); 
                            color: var(--white); 
                            padding: var(--spacing-6); 
                            border-radius: var(--radius-lg);
                            text-align: center;
                        ">
                            <h3 style="margin: 0 0 var(--spacing-3) 0; color: var(--white);">
                                <i class="fas fa-gift"></i> Solde disponible pour retrait
                            </h3>
                            <p style="margin: 0 0 var(--spacing-4) 0; opacity: 0.9;">
                                Vous avez <?= number_format($available_balance, 2) ?> GHS disponibles pour retrait.
                            </p>
                            <a href="request_payout.php" class="instructor-btn" style="
                                background: var(--white); 
                                color: var(--success-color); 
                                padding: var(--spacing-3) var(--spacing-6);
                                font-weight: 600;
                            ">
                                <i class="fas fa-plus"></i>
                                Demander un paiement
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-search"></i> Recherche et filtres
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-4);">
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-filter"></i> Statut
                            </label>
                            <select name="status" class="instructor-form-input instructor-form-select">
                                <option value="">Tous les statuts</option>
                                <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="approved" <?= $filter_status == 'approved' ? 'selected' : '' ?>>Approuvés</option>
                                <option value="rejected" <?= $filter_status == 'rejected' ? 'selected' : '' ?>>Rejetés</option>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-calendar"></i> Année
                            </label>
                            <select name="year" class="instructor-form-input instructor-form-select">
                                <?php for ($year = date('Y'); $year >= date('Y') - 3; $year--): ?>
                                    <option value="<?= $year ?>" <?= $filter_year == $year ? 'selected' : '' ?>>
                                        <?= $year ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-sort"></i> Trier par
                            </label>
                            <select name="sort" class="instructor-form-input instructor-form-select">
                                <option value="recent" <?= $sort_by == 'recent' ? 'selected' : '' ?>>Plus récents</option>
                                <option value="oldest" <?= $sort_by == 'oldest' ? 'selected' : '' ?>>Plus anciens</option>
                                <option value="amount" <?= $sort_by == 'amount' ? 'selected' : '' ?>>Montant</option>
                                <option value="status" <?= $sort_by == 'status' ? 'selected' : '' ?>>Statut</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-2); align-items: end;">
                            <button type="submit" class="instructor-btn instructor-btn-primary">
                                <i class="fas fa-search"></i>
                                Filtrer
                            </button>
                            
                            <a href="payouts.php" class="instructor-btn instructor-btn-secondary">
                                <i class="fas fa-times"></i>
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payouts Chart -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-chart-bar"></i> Évolution des paiements (<?= $filter_year ?>)
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <canvas id="payoutsChart" style="width: 100%; height: 300px;"></canvas>
                </div>
            </div>

            <!-- Payouts List -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-list"></i> Historique des paiements (<?= count($payouts) ?>)
                    </h3>
                </div>
                
                <?php if (count($payouts) === 0): ?>
                    <div style="padding: var(--spacing-8); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-money-bill-wave" style="font-size: 3rem; margin-bottom: var(--spacing-4); opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                            Aucun paiement trouvé
                        </h3>
                        <p style="margin: 0; color: var(--gray-500);">
                            <?= $filter_status || $filter_year != date('Y') ? 'Aucun paiement ne correspond à vos critères de recherche.' : 'Aucun paiement effectué pour l\'instant.' ?>
                        </p>
                        <?php if ($filter_status || $filter_year != date('Y')): ?>
                            <a href="payouts.php" class="instructor-btn instructor-btn-primary" style="margin-top: var(--spacing-4);">
                                <i class="fas fa-times"></i>
                                Réinitialiser les filtres
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Montant</th>
                                        <th>Méthode</th>
                                        <th>Référence</th>
                                        <th>Statut</th>
                                        <th>Date de demande</th>
                                        <th>Date de traitement</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payouts as $payout): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?= $payout['id'] ?></strong>
                                            </td>
                                            <td>
                                                <strong style="color: var(--success-color);">
                                                    <?= number_format($payout['amount'], 2) ?> GHS
                                                </strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: var(--spacing-2);">
                                                    <i class="fas fa-<?= $payout['method'] == 'bank' ? 'university' : ($payout['method'] == 'mobile' ? 'mobile-alt' : 'credit-card') ?>"></i>
                                                    <?= ucfirst($payout['method']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= $payout['transaction_ref'] ? htmlspecialchars($payout['transaction_ref']) : '-' ?>
                                            </td>
                                            <td>
                                                <span class="instructor-badge <?= $payout['status'] == 'approved' ? 'success' : ($payout['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($payout['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y à H:i', strtotime($payout['created_at'])) ?></td>
                                            <td>
                                                <?= $payout['processed_at'] ? date('d/m/Y à H:i', strtotime($payout['processed_at'])) : '-' ?>
                                            </td>
                                            <td>
                                                <?= $payout['notes'] ? htmlspecialchars($payout['notes']) : '-' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="earnings.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux gains
                </a>
                
                <?php if ($available_balance > 0): ?>
                    <a href="request_payout.php" class="instructor-btn instructor-btn-success">
                        <i class="fas fa-plus"></i>
                        Demander un paiement
                    </a>
                <?php endif; ?>
                
                <a href="transactions.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-shopping-cart"></i>
                    Voir les transactions
                </a>
            </div>
        </div>
    </div>

    <script>
        // Payouts Chart
        const ctx = document.getElementById('payoutsChart').getContext('2d');
        const payoutsData = <?= json_encode($monthly_data) ?>;
        
        const labels = payoutsData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
        });
        
        const data = payoutsData.map(item => item.total_amount);
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Paiements mensuels (GHS)',
                    data: data,
                    backgroundColor: 'var(--primary-color)',
                    borderColor: 'var(--primary-color)',
                    borderWidth: 1
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
                                return value.toLocaleString() + ' GHS';
                            }
                        }
                    }
                }
            }
        });

        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('select[name="status"], select[name="year"], select[name="sort"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });
        });
    </script>
</body>
</html>