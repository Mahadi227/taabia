<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_year = $_GET['year'] ?? date('Y');
$filter_status = $_GET['status'] ?? '';
$filter_type = $_GET['type'] ?? '';
$sort_by = $_GET['sort'] ?? 'recent';

try {
    // Get instructor's courses for filter
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE instructor_id = ? ORDER BY title");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    // Build query with filters
    $where_conditions = ["c.instructor_id = ?"];
    $params = [$instructor_id];

    if ($search) {
        $where_conditions[] = "(s.full_name LIKE ? OR c.title LIKE ? OR o.id LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($filter_month && $filter_month !== 'all') {
        $where_conditions[] = "DATE_FORMAT(o.ordered_at, '%Y-%m') = ?";
        $params[] = $filter_month;
    }

    if ($filter_status) {
        $where_conditions[] = "o.status = ?";
        $params[] = $filter_status;
    }

    if ($filter_type) {
        $where_conditions[] = "oi.type = ?";
        $params[] = $filter_type;
    }

    $where_clause = implode(" AND ", $where_conditions);

    // Determine sort order
    $order_by = match($sort_by) {
        'recent' => 'o.ordered_at DESC',
        'oldest' => 'o.ordered_at ASC',
        'amount' => 'oi.price * oi.quantity DESC',
        'student' => 's.full_name ASC',
        'course' => 'c.title ASC',
        'status' => 'o.status ASC',
        default => 'o.ordered_at DESC'
    };

    // Get transactions with comprehensive data
    $stmt = $pdo->prepare("
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
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();

    // Get statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            SUM(oi.price * oi.quantity) as total_revenue,
            COUNT(DISTINCT o.buyer_id) as unique_customers,
            AVG(oi.price * oi.quantity) as avg_order_value,
            COUNT(CASE WHEN o.status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN o.status = 'pending' THEN 1 END) as pending_orders
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN courses c ON oi.course_id = c.id
        WHERE c.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $stats = $stmt->fetch();

    // Get monthly revenue data for chart
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(o.ordered_at, '%Y-%m') as month,
            SUM(oi.price * oi.quantity) as revenue,
            COUNT(DISTINCT o.id) as orders,
            COUNT(DISTINCT o.buyer_id) as customers
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        LEFT JOIN courses c ON oi.course_id = c.id
        WHERE c.instructor_id = ? 
          AND o.status = 'completed'
          AND YEAR(o.ordered_at) = ?
        GROUP BY DATE_FORMAT(o.ordered_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$instructor_id, $filter_year]);
    $monthly_data = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in transactions: " . $e->getMessage());
    $transactions = [];
    $courses = [];
    $stats = ['total_orders' => 0, 'total_revenue' => 0, 'unique_customers' => 0, 'avg_order_value' => 0, 'completed_orders' => 0, 'pending_orders' => 0];
    $monthly_data = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions | TaaBia</title>
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
                <a href="transactions.php" class="instructor-nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    Transactions
                </a>
                <a href="payouts.php" class="instructor-nav-item">
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
                <h1>Transactions</h1>
                <p>Gérez et suivez toutes vos transactions</p>
            </div>

            <!-- Statistics Cards -->
            <div class="instructor-cards" style="margin-bottom: var(--spacing-6);">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Total commandes</div>
                    <div class="instructor-card-value"><?= $stats['total_orders'] ?></div>
                    <div class="instructor-card-description">Toutes les commandes</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Revenus totaux</div>
                    <div class="instructor-card-value"><?= number_format($stats['total_revenue'], 2) ?> GHS</div>
                    <div class="instructor-card-description">Tous temps confondus</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Clients uniques</div>
                    <div class="instructor-card-value"><?= $stats['unique_customers'] ?></div>
                    <div class="instructor-card-description">Étudiants différents</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Panier moyen</div>
                    <div class="instructor-card-value"><?= number_format($stats['avg_order_value'], 2) ?> GHS</div>
                    <div class="instructor-card-description">Par commande</div>
                </div>
            </div>

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
                                <i class="fas fa-search"></i> Rechercher
                            </label>
                            <input type="text" name="search" 
                                   class="instructor-form-input" 
                                   placeholder="Étudiant, cours ou ID commande"
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-calendar"></i> Mois
                            </label>
                            <input type="month" name="month" 
                                   class="instructor-form-input" 
                                   value="<?= htmlspecialchars($filter_month) ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-filter"></i> Statut
                            </label>
                            <select name="status" class="instructor-form-input instructor-form-select">
                                <option value="">Tous les statuts</option>
                                <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>En attente</option>
                                <option value="completed" <?= $filter_status == 'completed' ? 'selected' : '' ?>>Complétées</option>
                                <option value="cancelled" <?= $filter_status == 'cancelled' ? 'selected' : '' ?>>Annulées</option>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-tag"></i> Type
                            </label>
                            <select name="type" class="instructor-form-input instructor-form-select">
                                <option value="">Tous les types</option>
                                <option value="course" <?= $filter_type == 'course' ? 'selected' : '' ?>>Cours</option>
                                <option value="product" <?= $filter_type == 'product' ? 'selected' : '' ?>>Produits</option>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label class="instructor-form-label">
                                <i class="fas fa-sort"></i> Trier par
                            </label>
                            <select name="sort" class="instructor-form-input instructor-form-select">
                                <option value="recent" <?= $sort_by == 'recent' ? 'selected' : '' ?>>Plus récentes</option>
                                <option value="oldest" <?= $sort_by == 'oldest' ? 'selected' : '' ?>>Plus anciennes</option>
                                <option value="amount" <?= $sort_by == 'amount' ? 'selected' : '' ?>>Montant</option>
                                <option value="student" <?= $sort_by == 'student' ? 'selected' : '' ?>>Étudiant</option>
                                <option value="course" <?= $sort_by == 'course' ? 'selected' : '' ?>>Cours</option>
                                <option value="status" <?= $sort_by == 'status' ? 'selected' : '' ?>>Statut</option>
                            </select>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-2); align-items: end;">
                            <button type="submit" class="instructor-btn instructor-btn-primary">
                                <i class="fas fa-search"></i>
                                Filtrer
                            </button>
                            
                            <a href="transactions.php" class="instructor-btn instructor-btn-secondary">
                                <i class="fas fa-times"></i>
                                Réinitialiser
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Revenue Chart -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-chart-area"></i> Évolution des revenus (<?= $filter_year ?>)
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <canvas id="revenueChart" style="width: 100%; height: 300px;"></canvas>
                </div>
            </div>

            <!-- Transactions List -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-list"></i> Liste des transactions (<?= count($transactions) ?>)
                    </h3>
                </div>
                
                <?php if (count($transactions) === 0): ?>
                    <div style="padding: var(--spacing-8); text-align: center; color: var(--gray-500);">
                        <i class="fas fa-shopping-cart" style="font-size: 3rem; margin-bottom: var(--spacing-4); opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 var(--spacing-2) 0; color: var(--gray-600);">
                            Aucune transaction trouvée
                        </h3>
                        <p style="margin: 0; color: var(--gray-500);">
                            <?= $search || $filter_month || $filter_status || $filter_type ? 'Aucune transaction ne correspond à vos critères de recherche.' : 'Aucune transaction enregistrée pour l\'instant.' ?>
                        </p>
                        <?php if ($search || $filter_month || $filter_status || $filter_type): ?>
                            <a href="transactions.php" class="instructor-btn instructor-btn-primary" style="margin-top: var(--spacing-4);">
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
                                        <th>Commande</th>
                                        <th>Étudiant</th>
                                        <th>Produit</th>
                                        <th>Montant</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?= $transaction['order_id'] ?></strong>
                                            </td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: var(--spacing-2);">
                                                    <div style="
                                                        width: 32px; 
                                                        height: 32px; 
                                                        background: var(--primary-color); 
                                                        border-radius: 50%;
                                                        display: flex;
                                                        align-items: center;
                                                        justify-content: center;
                                                        color: var(--white);
                                                        font-size: var(--font-size-xs);
                                                    ">
                                                        <i class="fas fa-user"></i>
                                                    </div>
                                                    <div>
                                                        <div style="font-weight: 600; color: var(--gray-900);">
                                                            <?= htmlspecialchars($transaction['student_name']) ?>
                                                        </div>
                                                        <div style="font-size: var(--font-size-xs); color: var(--gray-500);">
                                                            <?= htmlspecialchars($transaction['student_email']) ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--gray-900);">
                                                        <?= htmlspecialchars($transaction['course_title'] ?? $transaction['product_name'] ?? 'Produit') ?>
                                                    </div>
                                                    <div style="font-size: var(--font-size-xs); color: var(--gray-500);">
                                                        <?= ucfirst($transaction['type']) ?> • Qty: <?= $transaction['quantity'] ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong style="color: var(--success-color);">
                                                    <?= number_format($transaction['amount'], 2) ?> GHS
                                                </strong>
                                            </td>
                                            <td><?= date('d/m/Y à H:i', strtotime($transaction['ordered_at'])) ?></td>
                                            <td>
                                                <span class="instructor-badge <?= $transaction['status'] == 'completed' ? 'success' : ($transaction['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($transaction['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: var(--spacing-1);">
                                                    <a href="message_student.php?id=<?= $transaction['student_id'] ?>" 
                                                       class="instructor-btn instructor-btn-primary"
                                                       style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                        <i class="fas fa-envelope"></i>
                                                    </a>
                                                    
                                                    <?php if ($transaction['course_id']): ?>
                                                        <a href="course_students.php?course_id=<?= $transaction['course_id'] ?>" 
                                                           class="instructor-btn instructor-btn-info"
                                                           style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                            <i class="fas fa-users"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
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
                
                <button onclick="exportTransactions()" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-download"></i>
                    Exporter (CSV)
                </button>
                
                <button onclick="window.print()" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-print"></i>
                    Imprimer
                </button>
            </div>
        </div>
    </div>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const revenueData = <?= json_encode($monthly_data) ?>;
        
        const labels = revenueData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
        });
        
        const data = revenueData.map(item => item.revenue);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenus mensuels (GHS)',
                    data: data,
                    borderColor: 'var(--success-color)',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
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
                },
                elements: {
                    point: {
                        backgroundColor: 'var(--success-color)',
                        borderColor: 'var(--white)',
                        borderWidth: 2,
                        radius: 6
                    }
                }
            }
        });

        // Export function
        function exportTransactions() {
            const table = document.querySelector('table');
            let csv = [];
            
            // Add headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Add data
            table.querySelectorAll('tbody tr').forEach(row => {
                const rowData = [];
                row.querySelectorAll('td').forEach(cell => {
                    rowData.push(cell.textContent.trim().replace(/,/g, ';'));
                });
                csv.push(rowData.join(','));
            });
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'transactions_<?= date('Y-m-d') ?>.csv';
            a.click();
        }

        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('select[name="status"], select[name="type"], select[name="sort"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });
            
            // Search with debounce
            const searchInput = document.querySelector('input[name="search"]');
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.closest('form').submit();
                }, 500);
            });
        });
    </script>
</body>
</html>