<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$message = '';
$error = '';

// Get event information
if ($event_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT e.*, u.full_name as organizer_name, 
                   (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registration_count
            FROM events e 
            LEFT JOIN users u ON e.organizer_id = u.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in admin/delete_event.php: " . $e->getMessage());
        $error = "Erreur de base de données.";
    }
}

if (!$event) {
    redirect('events.php');
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete event registrations first
        $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ?");
        $stmt->execute([$event_id]);
        
        // Delete the event
        $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success_message'] = "L'événement '{$event['title']}' a été supprimé avec succès.";
        redirect('events.php');
        
    } catch (PDOException $e) {
        // Rollback transaction
        $pdo->rollBack();
        error_log("Database error in delete_event: " . $e->getMessage());
        $error = "Erreur lors de la suppression de l'événement.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer Événement | Admin | TaaBia</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
                <a href="events.php" class="nav-link active">
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
                <a href="payment_stats.php" class="nav-link">
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
        <div class="header">
            <div class="header-content">
                <div class="page-title">
                    <h1><i class="fas fa-trash"></i> Supprimer l'Événement</h1>
                    <p>Confirmation de suppression</p>
                </div>
                
                <div class="header-actions">
                    <div class="d-flex gap-2">
                        <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Voir
                        </a>
                        <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="events.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour
                        </a>
                    </div>
                    
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? 'Administrateur') ?></div>
                            <div style="font-size: 0.75rem; opacity: 0.7;">Admin Panel</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if ($error): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Warning Card -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Attention - Suppression Irréversible</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger">
                        <h4><i class="fas fa-warning"></i> Cette action est irréversible !</h4>
                        <p>Vous êtes sur le point de supprimer définitivement l'événement <strong>"<?= htmlspecialchars($event['title']) ?>"</strong>.</p>
                        <p>Cette action supprimera également :</p>
                        <ul>
                            <li>Toutes les inscriptions (<?= $event['registration_count'] ?> inscription(s))</li>
                            <li>Toutes les données liées à cet événement</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Event Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Informations de l'Événement</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Titre:</strong>
                                <span><?= htmlspecialchars($event['title']) ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Organisateur:</strong>
                                <span><?= htmlspecialchars($event['organizer_name'] ?? 'Non assigné') ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Prix:</strong>
                                <span>GHS <?= number_format($event['price'], 2) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Statut:</strong>
                                <span class="badge <?php 
                                    switch($event['status']) {
                                        case 'upcoming': echo 'badge-info'; break;
                                        case 'ongoing': echo 'badge-success'; break;
                                        case 'completed': echo 'badge-secondary'; break;
                                        case 'cancelled': echo 'badge-danger'; break;
                                        default: echo 'badge-secondary'; break;
                                    }
                                ?>">
                                    <?php 
                                    switch($event['status']) {
                                        case 'upcoming': echo 'À venir'; break;
                                        case 'ongoing': echo 'En cours'; break;
                                        case 'completed': echo 'Terminé'; break;
                                        case 'cancelled': echo 'Annulé'; break;
                                        default: echo htmlspecialchars($event['status']); break;
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <strong>Date de l'événement:</strong>
                                <span><?= date('d/m/Y H:i', strtotime($event['event_date'])) ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Description:</strong>
                                <span><?= htmlspecialchars(substr($event['description'], 0, 100)) ?>...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Impact de la Suppression</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-card danger">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #f44336, #e57373);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($event['registration_count']) ?></div>
                                    <div class="stat-label">Inscriptions supprimées</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="stat-card danger">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #f44336, #e57373);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">GHS <?= number_format($event['price'] * $event['registration_count'], 2) ?></div>
                                    <div class="stat-label">Revenus potentiels perdus</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Confirmation Form -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-check-circle"></i> Confirmation</h3>
                </div>
                <div class="card-body">
                    <form method="POST" onsubmit="return confirm('Êtes-vous ABSOLUMENT sûr de vouloir supprimer cet événement ? Cette action est irréversible !');">
                        <div class="form-group">
                            <label for="confirm_text" class="form-label">
                                <i class="fas fa-keyboard"></i> Tapez "SUPPRIMER" pour confirmer *
                            </label>
                            <input type="text" name="confirm_text" id="confirm_text" class="form-control" 
                                   placeholder="SUPPRIMER" required>
                            <small class="text-muted">Cette action est irréversible et supprimera définitivement l'événement et toutes ses données associées.</small>
                        </div>
                        
                        <div class="d-flex gap-3 mt-4">
                            <button type="submit" name="confirm_delete" class="btn btn-danger" disabled>
                                <i class="fas fa-trash"></i> Supprimer Définitivement
                            </button>
                            
                            <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> Voir l'Événement
                            </a>
                            
                            <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> Modifier à la Place
                            </a>
                            
                            <a href="events.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const confirmInput = document.getElementById('confirm_text');
            const deleteButton = document.querySelector('button[name="confirm_delete"]');
            
            confirmInput.addEventListener('input', function() {
                if (this.value.toUpperCase() === 'SUPPRIMER') {
                    deleteButton.disabled = false;
                    deleteButton.classList.remove('btn-secondary');
                    deleteButton.classList.add('btn-danger');
                } else {
                    deleteButton.disabled = true;
                    deleteButton.classList.remove('btn-danger');
                    deleteButton.classList.add('btn-secondary');
                }
            });
        });
    </script>

    <style>
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item strong {
            color: var(--text-primary);
        }
        
        .info-item span {
            color: var(--text-secondary);
        }
        
        .stat-card.danger {
            border: 2px solid #f44336;
            background: rgba(244, 67, 54, 0.05);
        }
        
        .stat-card {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .stat-content .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .stat-content .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
    </style>
</body>
</html>
