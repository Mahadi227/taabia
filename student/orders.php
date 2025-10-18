<?php

/**
 * Student Orders Page - Modern LMS
 * Manage and track all purchase orders
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Search and filter
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Initialize
$orders = [];
$total_orders = 0;
$completed_count = 0;
$pending_count = 0;
$cancelled_count = 0;
$total_spent = 0;
$pending_amount = 0;

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];

    try {
        // Check if order can be cancelled
        $stmt_check = $pdo->prepare("
            SELECT * FROM orders 
            WHERE id = ? AND buyer_id = ? AND status = 'pending'
        ");
        $stmt_check->execute([$order_id, $student_id]);
        $order = $stmt_check->fetch();

        if ($order) {
            $stmt_cancel = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $stmt_cancel->execute([$order_id]);

            $_SESSION['success_message'] = __('order_cancelled_success') ?? 'Commande annulée avec succès';
        } else {
            $_SESSION['error_message'] = __('cannot_cancel_order') ?? 'Impossible d\'annuler cette commande';
        }
    } catch (PDOException $e) {
        error_log("Error cancelling order: " . $e->getMessage());
        $_SESSION['error_message'] = __('error_occurred') ?? 'Une erreur est survenue';
    }

    header('Location: orders.php');
    exit;
}

try {
    error_log("=== ORDERS PAGE DEBUG ===");
    error_log("Student ID: $student_id");

    // Build query conditions
    $where_conditions = ["o.buyer_id = ?"];
    $params = [$student_id];

    if (!empty($search)) {
        $where_conditions[] = "(o.id LIKE ? OR p.name LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
    }

    if ($status_filter !== 'all') {
        $where_conditions[] = "o.status = ?";
        $params[] = $status_filter;
    }

    $where_clause = implode(' AND ', $where_conditions);

    // Get orders - try different date columns for sorting
    $orders = [];

    // Try different sorting columns
    $order_variations = [
        'recent' => ['o.ordered_at DESC', 'o.created_at DESC', 'o.id DESC'],
        'oldest' => ['o.ordered_at ASC', 'o.created_at ASC', 'o.id ASC'],
        'amount_high' => ['o.total_amount DESC'],
        'amount_low' => ['o.total_amount ASC']
    ];

    $sort_options = $order_variations[$sort_by] ?? ['o.id DESC'];

    foreach ($sort_options as $order_clause) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    o.*,
                    p.name as product_name,
                    p.description as product_description,
                    p.image_url as product_image
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE $where_clause
                ORDER BY $order_clause
            ");
            $stmt->execute($params);
            $orders = $stmt->fetchAll();
            error_log("Orders loaded successfully with: $order_clause");
            break;
        } catch (PDOException $e) {
            error_log("Order query failed with $order_clause: " . $e->getMessage());
            continue;
        }
    }

    // If still no orders, try minimal query
    if (empty($orders) && empty($search) && $status_filter === 'all') {
        try {
            $stmt = $pdo->prepare("
                SELECT o.*, p.name as product_name
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                WHERE o.buyer_id = ?
                ORDER BY o.id DESC
            ");
            $stmt->execute([$student_id]);
            $orders = $stmt->fetchAll();
            error_log("Orders loaded with minimal query: " . count($orders));
        } catch (PDOException $e) {
            error_log("Even minimal query failed: " . $e->getMessage());
        }
    }

    error_log("Found " . count($orders) . " orders");

    // Calculate statistics
    $total_orders = count($orders);
    $completed_count = count(array_filter($orders, fn($o) => $o['status'] === 'completed'));
    $pending_count = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
    $cancelled_count = count(array_filter($orders, fn($o) => $o['status'] === 'cancelled'));

    // Calculate amounts
    $completed_orders = array_filter($orders, fn($o) => $o['status'] === 'completed');
    $pending_orders = array_filter($orders, fn($o) => $o['status'] === 'pending');

    $total_spent = array_sum(array_map(fn($o) => $o['total_amount'], $completed_orders));
    $pending_amount = array_sum(array_map(fn($o) => $o['total_amount'], $pending_orders));

    error_log("Stats - Total: $total_orders, Completed: $completed_count, Pending: $pending_count, Spent: $total_spent");
} catch (PDOException $e) {
    error_log("Error in orders.php: " . $e->getMessage());
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_purchases') ?? 'Mes Achats' ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004080;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            margin-right: 0.75rem;
        }

        .nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), transparent);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .stat-icon.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
        }

        .form-input,
        .form-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Orders List */
        .orders-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .order-card {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            transition: background 0.3s ease;
        }

        .order-card:hover {
            background: var(--gray-50);
        }

        .order-card:last-child {
            border-bottom: none;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .order-info h4 {
            color: var(--gray-900);
            font-size: 1.125rem;
            margin-bottom: 0.5rem;
        }

        .order-meta {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .order-amount {
            text-align: right;
        }

        .amount {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .order-product {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .product-image {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        .product-details {
            flex: 1;
        }

        .product-details p {
            font-size: 0.875rem;
            color: var(--gray-700);
            line-height: 1.6;
        }

        .order-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .order-header {
                flex-direction: column;
                gap: 1rem;
            }

            .order-amount {
                text-align: left;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
            </div>

            <nav class="nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item">
                    <i class="fas fa-compass"></i>
                    <?= __('discover_courses') ?? 'Découvrir' ?>
                </a>
                <a href="course_lessons.php" class="nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?? 'Mes Leçons' ?>
                </a>
                <a href="orders.php" class="nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><?= __('my_purchases') ?? 'Mes Achats' ?></h1>
                    <p><?= __('manage_orders') ?? 'Gérez vos commandes et suivez vos achats' ?></p>

                    <!-- Debug Info -->
                    <div style="margin-top: 1rem; padding: 1rem; background: #fef3c7; border-radius: 8px; font-size: 0.875rem;">
                        <strong>🔍 Debug:</strong>
                        Orders: <?= count($orders) ?> |
                        Total: <?= $total_orders ?> |
                        Completed: <?= $completed_count ?> |
                        Pending: <?= $pending_count ?> |
                        Cancelled: <?= $cancelled_count ?> |
                        Spent: <?= number_format($total_spent, 2) ?> GHS
                    </div>
                </div>
                <a href="../public/main_site/shop.php" class="btn btn-primary">
                    <i class="fas fa-shopping-bag"></i>
                    <?= __('visit_shop') ?? 'Visiter la boutique' ?>
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($_SESSION['error_message']) ?></div>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-label"><?= __('total_orders') ?? 'Total Commandes' ?></div>
                    <div class="stat-value"><?= $total_orders ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-label"><?= __('completed') ?? 'Terminées' ?></div>
                    <div class="stat-value"><?= $completed_count ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-label"><?= __('pending') ?? 'En Attente' ?></div>
                    <div class="stat-value"><?= $pending_count ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stat-label"><?= __('cancelled') ?? 'Annulées' ?></div>
                    <div class="stat-value"><?= $cancelled_count ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-label"><?= __('total_spent') ?? 'Total Dépensé' ?></div>
                    <div class="stat-value"><?= number_format($total_spent, 0) ?> GHS</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filters-form">
                    <input type="text" name="search" class="form-input"
                        placeholder="<?= __('search_orders') ?? 'Rechercher par ID ou produit...' ?>"
                        value="<?= htmlspecialchars($search) ?>">

                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>
                            <?= __('all_status') ?? 'Tous les statuts' ?>
                        </option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>
                            <?= __('pending') ?? 'En attente' ?>
                        </option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>
                            <?= __('completed') ?? 'Terminées' ?>
                        </option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>
                            <?= __('cancelled') ?? 'Annulées' ?>
                        </option>
                    </select>

                    <select name="sort" class="form-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>
                            <?= __('most_recent') ?? 'Plus récentes' ?>
                        </option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>
                            <?= __('oldest') ?? 'Plus anciennes' ?>
                        </option>
                        <option value="amount_high" <?= $sort_by === 'amount_high' ? 'selected' : '' ?>>
                            <?= __('amount_high') ?? 'Montant élevé' ?>
                        </option>
                        <option value="amount_low" <?= $sort_by === 'amount_low' ? 'selected' : '' ?>>
                            <?= __('amount_low') ?? 'Montant faible' ?>
                        </option>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?= __('search') ?? 'Rechercher' ?>
                    </button>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-container">
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <div class="order-card">
                            <div class="order-header">
                                <div class="order-info">
                                    <h4>
                                        <?= __('order') ?? 'Commande' ?> #<?= $order['id'] ?>
                                    </h4>
                                    <div class="order-meta">
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y H:i', strtotime($order['created_at'] ?? $order['ordered_at'] ?? 'now')) ?>
                                    </div>
                                </div>
                                <div class="order-amount">
                                    <div class="amount"><?= number_format($order['total_amount'], 2) ?> GHS</div>
                                    <span class="badge badge-<?= match ($order['status']) {
                                                                    'completed' => 'success',
                                                                    'pending' => 'warning',
                                                                    'cancelled' => 'danger',
                                                                    default => 'info'
                                                                } ?>">
                                        <?= match ($order['status']) {
                                            'completed' => __('completed') ?? 'Terminée',
                                            'pending' => __('pending') ?? 'En attente',
                                            'cancelled' => __('cancelled') ?? 'Annulée',
                                            default => ucfirst($order['status'])
                                        } ?>
                                    </span>
                                </div>
                            </div>

                            <?php if ($order['product_id']): ?>
                                <div class="order-product">
                                    <div class="product-image">
                                        <?php if (!empty($order['product_image'])): ?>
                                            <img src="../uploads/<?= htmlspecialchars($order['product_image']) ?>"
                                                alt="<?= htmlspecialchars($order['product_name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-box"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-details">
                                        <h5 style="color: var(--gray-900); margin-bottom: 0.5rem;">
                                            <?= htmlspecialchars($order['product_name'] ?? 'Produit') ?>
                                        </h5>
                                        <?php if (!empty($order['product_description'])): ?>
                                            <p><?= htmlspecialchars(substr($order['product_description'], 0, 150)) ?>...</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="order-actions">
                                <a href="view_order.php?id=<?= $order['id'] ?>" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-eye"></i> <?= __('view_details') ?? 'Voir détails' ?>
                                </a>

                                <?php if ($order['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" name="cancel_order" class="btn btn-danger btn-sm"
                                            onclick="return confirm('<?= __('confirm_cancel_order') ?? 'Êtes-vous sûr de vouloir annuler cette commande ?' ?>')">
                                            <i class="fas fa-times"></i> <?= __('cancel') ?? 'Annuler' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'completed'): ?>
                                    <a href="view_order.php?id=<?= $order['id'] ?>&download=invoice" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-download"></i> <?= __('download_invoice') ?? 'Télécharger facture' ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3><?= __('no_orders') ?? 'Aucune commande' ?></h3>
                        <p>
                            <?= (!empty($search) || $status_filter !== 'all')
                                ? (__('no_orders_filters') ?? 'Aucune commande trouvée avec ces critères')
                                : (__('no_orders_yet') ?? 'Vous n\'avez pas encore passé de commande') ?>
                        </p>
                        <a href="../public/main_site/shop.php" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-shopping-bag"></i> <?= __('start_shopping') ?? 'Commencer vos achats' ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-submit on filter change
            const filterSelects = document.querySelectorAll('.filters-form select');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });

            // Animate cards
            const cards = document.querySelectorAll('.order-card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>

</html>