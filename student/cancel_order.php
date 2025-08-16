<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$order_id = $_GET['id'] ?? null;

try {
    if (!$order_id) {
        header('Location: orders.php?error=invalid_id');
        exit;
    }

    // Vérifier que la commande appartient à l'étudiant et qu'elle est encore en attente
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.id = ? AND o.buyer_id = ? AND o.status = 'pending'
    ");
    $stmt->execute([$order_id, $student_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: orders.php?error=not_allowed');
        exit;
    }

    // Process cancellation
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Récupérer tous les produits de la commande
        $stmtItems = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $stmtItems->execute([$order_id]);
        $order_items = $stmtItems->fetchAll();

        // Réajuster le stock pour chaque produit
        foreach ($order_items as $item) {
            $product_id = $item['product_id'];
            $qty = $item['quantity'];

            $updateStock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
            $updateStock->execute([$qty, $product_id]);
        }

        // Mettre à jour le statut de la commande
        $update = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?");
        $update->execute([$order_id]);

        header('Location: orders.php?success=cancelled');
        exit;
    }

} catch (PDOException $e) {
    error_log("Database error in cancel_order: " . $e->getMessage());
    header('Location: orders.php?error=database_error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annuler la commande | TaaBia</title>
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
                <h1>Annuler la commande</h1>
                <p>Confirmez l'annulation de votre commande</p>
            </div>

            <!-- Order Information -->
            <div class="student-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-exclamation-triangle"></i> Confirmation d'annulation
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div style="
                        background: var(--warning-color); 
                        color: var(--white); 
                        padding: var(--spacing-4); 
                        border-radius: var(--radius-lg);
                        margin-bottom: var(--spacing-6);
                        display: flex;
                        align-items: center;
                        gap: var(--spacing-3);
                    ">
                        <i class="fas fa-exclamation-triangle" style="font-size: var(--font-size-xl);"></i>
                        <div>
                            <div style="font-weight: 600; margin-bottom: var(--spacing-1);">
                                Attention
                            </div>
                            <div style="font-size: var(--font-size-sm); opacity: 0.9;">
                                Cette action est irréversible. Êtes-vous sûr de vouloir annuler cette commande ?
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-6);">
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-shopping-bag"></i> Détails de la commande
                            </h4>
                            <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                                <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2);">
                                    Commande #<?= $order['id'] ?>
                                </div>
                                <div style="color: var(--gray-600); margin-bottom: var(--spacing-2);">
                                    <?= htmlspecialchars($order['product_name'] ?? 'Produit') ?>
                                </div>
                                <div style="font-weight: 600; color: var(--primary-color);">
                                    <?= number_format($order['total_amount'], 2) ?> GHS
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-calendar"></i> Informations
                            </h4>
                            <div style="background: var(--gray-50); padding: var(--spacing-4); border-radius: var(--radius-lg);">
                                <div style="margin-bottom: var(--spacing-2);">
                                    <span style="font-weight: 600; color: var(--gray-700);">Date de commande:</span>
                                    <div style="color: var(--gray-600);">
                                        <?= date('d/m/Y à H:i', strtotime($order['ordered_at'])) ?>
                                    </div>
                                </div>
                                <div>
                                    <span style="font-weight: 600; color: var(--gray-700);">Statut actuel:</span>
                                    <div style="color: var(--gray-600);">
                                        <span class="student-badge warning">En attente</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cancellation Form -->
            <div class="student-table-container">
                <div style="padding: var(--spacing-6);">
                    <form method="POST" id="cancelForm">
                        <div style="margin-bottom: var(--spacing-6);">
                            <h4 style="margin: 0 0 var(--spacing-3) 0; color: var(--gray-800);">
                                <i class="fas fa-comment"></i> Raison de l'annulation (optionnel)
                            </h4>
                            <textarea name="reason" 
                                      placeholder="Décrivez brièvement la raison de l'annulation..."
                                      class="student-search-input" 
                                      rows="3"
                                      style="resize: vertical;"></textarea>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                            <button type="submit" 
                                    class="student-btn" 
                                    style="background: var(--danger-color); color: var(--white); padding: var(--spacing-3) var(--spacing-6);">
                                <i class="fas fa-times"></i>
                                Confirmer l'annulation
                            </button>
                            
                            <a href="orders.php" 
                               class="student-btn student-btn-secondary" 
                               style="padding: var(--spacing-3) var(--spacing-6);">
                                <i class="fas fa-arrow-left"></i>
                                Retour aux commandes
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="orders.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux commandes
                </a>
                
                <a href="view_order.php?id=<?= $order_id ?>" class="student-btn student-btn-primary">
                    <i class="fas fa-eye"></i>
                    Voir les détails
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('cancelForm');
            
            form.addEventListener('submit', function(e) {
                if (!confirm('Êtes-vous absolument sûr de vouloir annuler cette commande ? Cette action est irréversible.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
