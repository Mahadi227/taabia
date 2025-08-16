<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';

require_role('admin');

$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$registration = null;
$error_message = '';
$success_message = '';

// Get current user info for dynamic header
$current_user = null;
try {
    $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $current_user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching current user: " . $e->getMessage());
}

try {
    // Get registration data
    $stmt = $pdo->prepare("
        SELECT er.*, e.title as event_title 
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        WHERE er.id = ?
    ");
    $stmt->execute([$registration_id]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        header('Location: event_registrations.php');
        exit();
    }
    
} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement de l'inscription.";
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM event_registrations WHERE id = ?");
        $stmt->execute([$registration_id]);
        
        $success_message = "Inscription supprimée avec succès.";
        
        // Redirect after a short delay
        header("Refresh: 2; URL=event_registrations.php");
        
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression de l'inscription.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer l'inscription | Admin TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <h1><i class="fas fa-trash"></i> Supprimer l'inscription</h1>
                <a href="event_registrations.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour aux inscriptions
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                    <p>Redirection en cours...</p>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Confirmation Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
                        Confirmer la suppression
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <h4><i class="fas fa-warning"></i> Attention !</h4>
                        <p>Vous êtes sur le point de supprimer définitivement cette inscription. Cette action ne peut pas être annulée.</p>
                    </div>

                    <div class="registration-details">
                        <h4>Détails de l'inscription :</h4>
                        <div class="detail-row">
                            <strong>Participant :</strong> <?= htmlspecialchars($registration['name']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Email :</strong> <?= htmlspecialchars($registration['email']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Événement :</strong> <?= htmlspecialchars($registration['event_title']) ?>
                        </div>
                        <div class="detail-row">
                            <strong>Date d'inscription :</strong> <?= date('d/m/Y H:i', strtotime($registration['registered_at'])) ?>
                        </div>
                    </div>

                    <form method="POST" class="form">
                        <div class="form-actions">
                            <button type="submit" name="confirm_delete" class="btn btn-danger" 
                                    onclick="return confirm('Êtes-vous absolument sûr de vouloir supprimer cette inscription ?')">
                                <i class="fas fa-trash"></i> Confirmer la suppression
                            </button>
                            <a href="event_registrations.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .registration-details {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
        }
        
        .detail-row {
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .detail-row strong {
            display: inline-block;
            width: 150px;
            color: var(--text-primary);
        }
    </style>
</body>
</html> 