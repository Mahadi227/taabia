<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

$payout_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

if (!$payout_id) {
    flash_message("ID de paiement manquant.", 'error');
    redirect('payout_requests.php');
}

try {
    // Get payout details
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as instructor_name, u.email as instructor_email
        FROM payouts p
        JOIN users u ON p.instructor_id = u.id
        WHERE p.id = ?
    ");
    $stmt->execute([$payout_id]);
    $payout = $stmt->fetch();

    if (!$payout) {
        flash_message("Demande de paiement non trouvée.", 'error');
        redirect('payout_requests.php');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $status = $_POST['status'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        
        if (!in_array($status, ['approved', 'rejected'])) {
            flash_message("Statut invalide.", 'error');
            redirect("process_payout.php?id=$payout_id");
        }

        // Update payout status
        $update_stmt = $pdo->prepare("
            UPDATE payouts 
            SET status = ?, processed_at = NOW(), notes = ?
            WHERE id = ?
        ");
        
        if ($update_stmt->execute([$status, $notes, $payout_id])) {
            $status_message = $status === 'approved' ? 'approuvé' : 'rejeté';
            flash_message("Paiement $status_message avec succès.", 'success');
            
            // Send notification to instructor
            // TODO: Implement notification system
            
            redirect('payout_requests.php');
        } else {
            flash_message("Erreur lors du traitement du paiement.", 'error');
        }
    }

} catch (PDOException $e) {
    error_log("Database error in process_payout: " . $e->getMessage());
    flash_message("Erreur de base de données.", 'error');
    redirect('payout_requests.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Traitement Paiement | Admin | TaaBia</title>
    
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
            <p>Plateforme de gestion</p>
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
                    <span>Cours</span>
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
                <a href="payout_requests.php" class="nav-link active">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Paiements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar"></i>
                    <span>Événements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
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
                    <h1><i class="fas fa-money-bill-wave"></i> Traitement Paiement</h1>
                    <p>Gérer les demandes de paiement des formateurs</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-menu">
                        <i class="fas fa-user-circle"></i>
                        <span>Administrateur</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Payout Details Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Détails du Paiement</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Formateur</label>
                                <div class="form-control-static">
                                    <strong><?= htmlspecialchars($payout['instructor_name']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= htmlspecialchars($payout['instructor_email']) ?></small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Montant</label>
                                <div class="form-control-static">
                                    <strong class="text-success"><?= number_format($payout['amount'], 2) ?> GHS</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Méthode de Paiement</label>
                                <div class="form-control-static">
                                    <span class="badge badge-info"><?= htmlspecialchars($payout['method']) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Statut Actuel</label>
                                <div class="form-control-static">
                                    <?php
                                    $status_badge = match($payout['status']) {
                                        'pending' => 'badge-warning',
                                        'approved' => 'badge-success',
                                        'rejected' => 'badge-danger',
                                        default => 'badge-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $status_badge ?>">
                                        <?= ucfirst($payout['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Date de Demande</label>
                                <div class="form-control-static">
                                    <?= date('d/m/Y à H:i', strtotime($payout['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">Référence Transaction</label>
                                <div class="form-control-static">
                                    <code><?= htmlspecialchars($payout['transaction_ref'] ?? 'N/A') ?></code>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($payout['notes']): ?>
                    <div class="row">
                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Notes</label>
                                <div class="form-control-static">
                                    <em><?= nl2br(htmlspecialchars($payout['notes'])) ?></em>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Processing Form -->
            <?php if ($payout['status'] === 'pending'): ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cogs"></i> Traitement du Paiement</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="processForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status" class="form-label">Action</label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="">-- Choisir une action --</option>
                                        <option value="approved">Approuver le paiement</option>
                                        <option value="rejected">Rejeter le paiement</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="notes" class="form-label">Notes (optionnel)</label>
                                    <textarea name="notes" id="notes" class="form-control" rows="4" 
                                              placeholder="Ajoutez des notes sur le traitement de ce paiement..."></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-check"></i> Traiter le Paiement
                                    </button>
                                    
                                    <a href="payout_requests.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Retour
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> Statut du Paiement</h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Ce paiement a déjà été traité le 
                        <strong><?= date('d/m/Y à H:i', strtotime($payout['processed_at'])) ?></strong>
                    </div>
                    
                    <div class="d-flex gap-3">
                        <a href="payout_requests.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Retour aux Demandes
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('processForm');
            const statusSelect = document.getElementById('status');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const status = statusSelect.value;
                    
                    if (!status) {
                        e.preventDefault();
                        alert('Veuillez sélectionner une action.');
                        return;
                    }
                    
                    const action = status === 'approved' ? 'approuver' : 'rejeter';
                    if (!confirm(`Êtes-vous sûr de vouloir ${action} ce paiement ?`)) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>