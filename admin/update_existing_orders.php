<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

$success_count = 0;
$error_count = 0;
$errors = [];

// Get commission rates
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM commission_settings WHERE setting_key IN ('instructor_commission_rate', 'vendor_commission_rate')");
$stmt->execute();
$rates = [];
while ($row = $stmt->fetch()) {
    $rates[$row['setting_key']] = $row['setting_value'];
}

$instructor_rate = $rates['instructor_commission_rate'] ?? 20.00;
$vendor_rate = $rates['vendor_commission_rate'] ?? 15.00;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_orders'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get all order_items that don't have commission data yet
        $stmt = $pdo->prepare("
            SELECT oi.*, c.instructor_id, p.vendor_id 
            FROM order_items oi
            LEFT JOIN courses c ON oi.course_id = c.id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE (oi.instructor_id IS NULL AND oi.course_id IS NOT NULL) 
               OR (oi.vendor_id IS NULL AND oi.product_id IS NOT NULL)
        ");
        $stmt->execute();
        $items_to_update = $stmt->fetchAll();
        
        foreach ($items_to_update as $item) {
            try {
                $commission_rate = null;
                $user_id = null;
                $transaction_type = null;
                
                // Determine if it's a course or product
                if ($item['course_id'] && $item['instructor_id']) {
                    $commission_rate = $instructor_rate;
                    $user_id = $item['instructor_id'];
                    $transaction_type = 'course';
                } elseif ($item['product_id'] && $item['vendor_id']) {
                    $commission_rate = $vendor_rate;
                    $user_id = $item['vendor_id'];
                    $transaction_type = 'product';
                }
                
                if ($commission_rate && $user_id) {
                    // Update order_item with user_id and commission rate
                    if ($transaction_type === 'course') {
                        $stmt = $pdo->prepare("
                            UPDATE order_items 
                            SET instructor_id = ?, platform_commission_rate = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$user_id, $commission_rate, $item['id']]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE order_items 
                            SET vendor_id = ?, platform_commission_rate = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$user_id, $commission_rate, $item['id']]);
                    }
                    
                    // Create commission transaction
                    $stmt = $pdo->prepare("
                        INSERT INTO commission_transactions (
                            order_item_id, 
                            instructor_id, 
                            vendor_id,
                            gross_revenue, 
                            platform_commission, 
                            vendor_revenue, 
                            commission_rate,
                            transaction_type,
                            status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    
                    $gross_revenue = $item['unit_price'] * $item['quantity'];
                    $platform_commission = $gross_revenue * $commission_rate / 100;
                    $vendor_revenue = $gross_revenue - $platform_commission;
                    
                    $instructor_id = ($transaction_type === 'course') ? $user_id : null;
                    $vendor_id = ($transaction_type === 'product') ? $user_id : null;
                    
                    $stmt->execute([
                        $item['id'],
                        $instructor_id,
                        $vendor_id,
                        $gross_revenue,
                        $platform_commission,
                        $vendor_revenue,
                        $commission_rate,
                        $transaction_type
                    ]);
                    
                    $success_count++;
                }
                
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "Erreur pour l'article ID {$item['id']}: " . $e->getMessage();
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = "Mise à jour terminée. $success_count articles mis à jour avec succès.";
        if ($error_count > 0) {
            $success_message .= " $error_count erreurs rencontrées.";
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Get statistics about existing data
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_order_items,
        COUNT(CASE WHEN instructor_id IS NOT NULL OR vendor_id IS NOT NULL THEN 1 END) as with_commission,
        COUNT(CASE WHEN instructor_id IS NULL AND vendor_id IS NULL AND course_id IS NOT NULL THEN 1 END) as courses_need_update,
        COUNT(CASE WHEN instructor_id IS NULL AND vendor_id IS NULL AND product_id IS NOT NULL THEN 1 END) as products_need_update,
        COUNT(CASE WHEN course_id IS NULL AND product_id IS NULL THEN 1 END) as other_items
    FROM order_items
");
$stmt->execute();
$stats = $stmt->fetch();

// Get commission transactions count
$stmt = $pdo->prepare("SELECT COUNT(*) FROM commission_transactions");
$stmt->execute();
$commission_transactions_count = $stmt->fetchColumn();

// Get commission transactions by type
$stmt = $pdo->prepare("
    SELECT 
        transaction_type,
        COUNT(*) as count,
        SUM(vendor_revenue) as total_revenue
    FROM commission_transactions 
    GROUP BY transaction_type
");
$stmt->execute();
$transactions_by_type = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mise à jour des Commandes | Admin | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                <span>Tableau de bord</span>
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>
                <span>Utilisateurs</span>
            </a>
            <a href="courses.php" class="nav-link">
                <i class="fas fa-graduation-cap"></i>
                <span>Cours</span>
            </a>
            <a href="orders.php" class="nav-link">
                <i class="fas fa-shopping-cart"></i>
                <span>Commandes</span>
            </a>
            <a href="commission_management.php" class="nav-link">
                <i class="fas fa-percentage"></i>
                <span>Commissions</span>
            </a>
            <a href="update_existing_orders.php" class="nav-link active">
                <i class="fas fa-sync-alt"></i>
                <span>Mise à jour</span>
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>
                <span>Paramètres</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="content-header">
            <h1>Mise à jour des Commandes Existantes</h1>
            <p>Appliquez le nouveau système de commissions aux commandes existantes</p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Current Status -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">État Actuel des Données</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $stats['total_order_items'] ?></div>
                            <div class="stat-label">Articles de Commande Totaux</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $stats['with_commission'] ?></div>
                            <div class="stat-label">Avec Commissions</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $stats['courses_need_update'] ?></div>
                            <div class="stat-label">Cours à Mettre à Jour</div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-value"><?= $stats['products_need_update'] ?></div>
                            <div class="stat-label">Produits à Mettre à Jour</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Commission Transactions Status -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Transactions de Commission</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong><?= $commission_transactions_count ?></strong> transactions de commission existantes dans la base de données.
                </div>
                
                <?php if (!empty($transactions_by_type)): ?>
                    <div class="transactions-breakdown">
                        <?php foreach ($transactions_by_type as $type): ?>
                            <div class="transaction-type">
                                <div class="type-icon">
                                    <?php if ($type['transaction_type'] === 'course'): ?>
                                        <i class="fas fa-graduation-cap"></i>
                                    <?php else: ?>
                                        <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="type-info">
                                    <div class="type-name">
                                        <?= ucfirst($type['transaction_type']) ?>s
                                    </div>
                                    <div class="type-stats">
                                        <?= $type['count'] ?> transactions | 
                                        <?= number_format($type['total_revenue'], 2) ?> GHS
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Update Form -->
        <?php if ($stats['courses_need_update'] > 0 || $stats['products_need_update'] > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mettre à Jour les Commandes</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Attention :</strong> Cette opération va mettre à jour 
                        <?= $stats['courses_need_update'] + $stats['products_need_update'] ?> articles de commande 
                        avec le nouveau système de commissions. Cette action ne peut pas être annulée.
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('Êtes-vous sûr de vouloir mettre à jour les commandes existantes ?');">
                        <div class="commission-rates">
                            <div class="rate-item">
                                <label>Taux Commission Instructeurs</label>
                                <input type="text" value="<?= $instructor_rate ?>%" class="form-control" readonly>
                                <small>Appliqué aux cours</small>
                            </div>
                            <div class="rate-item">
                                <label>Taux Commission Vendeurs</label>
                                <input type="text" value="<?= $vendor_rate ?>%" class="form-control" readonly>
                                <small>Appliqué aux produits</small>
                            </div>
                        </div>
                        
                        <button type="submit" name="update_orders" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i>
                            Mettre à Jour <?= $stats['courses_need_update'] + $stats['products_need_update'] ?> Articles
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Aucune Mise à Jour Nécessaire</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Tous les articles de commande ont déjà été mis à jour avec le système de commissions.
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Error Details -->
        <?php if (!empty($errors)): ?>
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Détails des Erreurs</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h4>Erreurs rencontrées :</h4>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Instructions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Instructions</h3>
            </div>
            <div class="card-body">
                <h4>Ce que fait cette mise à jour :</h4>
                <ul>
                    <li>Identifie tous les articles de commande (cours et produits) qui n'ont pas encore de données de commission</li>
                    <li>Assigne automatiquement l'instructeur ou le vendeur à chaque article</li>
                    <li>Applique les taux de commission appropriés :
                        <ul>
                            <li><strong>Cours :</strong> <?= $instructor_rate ?>% commission plateforme</li>
                            <li><strong>Produits :</strong> <?= $vendor_rate ?>% commission plateforme</li>
                        </ul>
                    </li>
                    <li>Calcule automatiquement :
                        <ul>
                            <li><strong>Revenu brut</strong> = prix unitaire × quantité</li>
                            <li><strong>Commission plateforme</strong> = revenu brut × taux approprié</li>
                            <li><strong>Revenu créateur</strong> = revenu brut - commission plateforme</li>
                        </ul>
                    </li>
                    <li>Crée une transaction de commission pour chaque article mis à jour</li>
                </ul>
                
                <h4>Notes importantes :</h4>
                <ul>
                    <li>Cette opération traite à la fois les cours et les produits</li>
                    <li>Les transactions de commission sont créées avec le statut "pending"</li>
                    <li>Cette action ne peut pas être annulée une fois exécutée</li>
                    <li>Il est recommandé de faire une sauvegarde de la base de données avant l'exécution</li>
                </ul>
            </div>
        </div>
    </div>

    <style>
        .commission-rates {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .rate-item {
            flex: 1;
        }
        .rate-item label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .rate-item small {
            color: var(--gray-600);
            font-size: 0.9em;
        }
        .transactions-breakdown {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        .transaction-type {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background: var(--gray-100);
            border-radius: var(--radius-sm);
        }
        .type-icon {
            font-size: 1.2em;
            color: var(--primary-color);
        }
        .type-name {
            font-weight: 600;
        }
        .type-stats {
            font-size: 0.9em;
            color: var(--gray-600);
        }
    </style>

    <script>
        // Auto-hide alerts after 10 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-danger')) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 10000);
    </script>
</body>
</html>
