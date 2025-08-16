<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

$order_id = (int)$_GET['id'];
$student_id = $_SESSION['user_id'];

try {
    // Vérifie que la commande appartient à l'utilisateur
    $stmt = $pdo->prepare("
        SELECT o.*, p.name AS product_name, p.description AS product_description,
               CASE 
                   WHEN o.status = 'completed' THEN 'success'
                   WHEN o.status = 'pending' THEN 'warning'
                   WHEN o.status = 'cancelled' THEN 'danger'
                   ELSE 'info'
               END as status_class
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$order_id, $student_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: orders.php');
        exit;
    }

    // Récupère les articles de la commande
    $stmt = $pdo->prepare("
        SELECT p.name AS product_name, p.description AS product_description,
               oi.quantity, oi.price, (oi.price * oi.quantity) as subtotal
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    // Calculate totals
    $subtotal = array_sum(array_column($items, 'subtotal'));
    $tax = $subtotal * 0.05; // 5% tax
    $total = $subtotal + $tax;

} catch (PDOException $e) {
    error_log("Database error in view_order: " . $e->getMessage());
    header('Location: orders.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande #<?= $order['id'] ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p>Espace Apprenant</p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    Découvrir les cours
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
                <a href="course_lessons.php" class="student-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Mes leçons
                </a>
                <a href="orders.php" class="student-nav-item active">
                    <i class="fas fa-shopping-cart"></i>
                    Mes achats
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    Mon profil
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <h1>Commande #<?= $order['id'] ?></h1>
                <p>Détails de votre commande</p>
            </div>

            <!-- Order Summary -->
            <div class="student-cards" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Numéro de commande</div>
                    <div class="student-card-value">#<?= $order['id'] ?></div>
                    <div class="student-card-description">Identifiant unique</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Montant total</div>
                    <div class="student-card-value"><?= number_format($order['total_amount'], 2) ?> GHS</div>
                    <div class="student-card-description">Montant payé</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon <?= $order['status_class'] ?>">
                            <i class="fas fa-<?= $order['status'] == 'completed' ? 'check' : ($order['status'] == 'pending' ? 'clock' : 'times') ?>"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Statut</div>
                    <div class="student-card-value"><?= ucfirst($order['status']) ?></div>
                    <div class="student-card-description">État de la commande</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-calendar"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Date de commande</div>
                    <div class="student-card-value"><?= date('d/m/Y', strtotime($order['ordered_at'])) ?></div>
                    <div class="student-card-description"><?= date('H:i', strtotime($order['ordered_at'])) ?></div>
                </div>
            </div>

            <!-- Order Details -->
            <div class="student-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-info-circle"></i> Informations de la commande
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-6);">
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-shopping-cart"></i> Produit commandé
                            </h4>
                            <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                                <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2);">
                                    <?= htmlspecialchars($order['product_name'] ?? 'Cours') ?>
                                </div>
                                <?php if ($order['product_description']): ?>
                                    <div style="color: var(--gray-600); font-size: var(--font-size-sm);">
                                        <?= htmlspecialchars(substr($order['product_description'], 0, 200)) ?>...
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-credit-card"></i> Détails du paiement
                            </h4>
                            <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                                <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-2);">
                                    <span style="color: var(--gray-600);">Sous-total:</span>
                                    <span style="font-weight: 600;"><?= number_format($subtotal, 2) ?> GHS</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: var(--spacing-2);">
                                    <span style="color: var(--gray-600);">Taxes (5%):</span>
                                    <span style="font-weight: 600;"><?= number_format($tax, 2) ?> GHS</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding-top: var(--spacing-2); border-top: 1px solid var(--gray-200);">
                                    <span style="color: var(--gray-800); font-weight: 600;">Total:</span>
                                    <span style="color: var(--primary-color); font-weight: 700; font-size: var(--font-size-lg);">
                                        <?= number_format($total, 2) ?> GHS
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Items -->
            <?php if (count($items) > 0): ?>
                <div class="student-table-container">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-list"></i> Articles de la commande
                        </h3>
                    </div>
                    
                    <div class="student-table-container" style="margin: 0; box-shadow: none;">
                        <table class="student-table">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Description</th>
                                    <th>Quantité</th>
                                    <th>Prix unitaire</th>
                                    <th>Sous-total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--gray-900);">
                                                <?= htmlspecialchars($item['product_name']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="color: var(--gray-600); font-size: var(--font-size-sm);">
                                                <?= htmlspecialchars(substr($item['product_description'] ?? '', 0, 100)) ?>...
                                            </div>
                                        </td>
                                        <td>
                                            <span class="student-badge primary">
                                                <?= (int)$item['quantity'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--primary-color);">
                                                <?= number_format($item['price'], 2) ?> GHS
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                <?= number_format($item['subtotal'], 2) ?> GHS
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Order Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="orders.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux commandes
                </a>
                
                <?php if ($order['status'] == 'pending'): ?>
                    <a href="cancel_order.php?id=<?= $order['id'] ?>" 
                       class="student-btn" 
                       style="background: var(--danger-color); color: var(--white);"
                       onclick="return confirm('Êtes-vous sûr de vouloir annuler cette commande ?')">
                        <i class="fas fa-times"></i>
                        Annuler la commande
                    </a>
                <?php endif; ?>
                
                <a href="messages.php?order_id=<?= $order['id'] ?>" class="student-btn student-btn-success">
                    <i class="fas fa-envelope"></i>
                    Contacter le support
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.student-table tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = 'var(--gray-50)';
                });
                
                row.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = '';
                });
            });
        });
    </script>
</body>
</html>
