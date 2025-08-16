<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';

require_role('admin');

$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$registration = null;
$events = [];
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
    
    // Get events for dropdown
    $events = $pdo->query("SELECT id, title FROM events ORDER BY title")->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement de l'inscription.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $event_id = (int)$_POST['event_id'];
    
    // Validation
    $errors = [];
    if (empty($name)) $errors[] = "Le nom est requis.";
    if (empty($email)) $errors[] = "L'email est requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "L'email n'est pas valide.";
    if ($event_id <= 0) $errors[] = "Veuillez sélectionner un événement.";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE event_registrations 
                SET name = ?, email = ?, event_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $email, $event_id, $registration_id]);
            
            $success_message = "Inscription mise à jour avec succès.";
            
            // Update registration data for display
            $registration['name'] = $name;
            $registration['email'] = $email;
            $registration['event_id'] = $event_id;
            
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la mise à jour de l'inscription.";
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'inscription | Admin TaaBia</title>
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
                <h1><i class="fas fa-edit"></i> Modifier l'inscription</h1>
                <a href="event_registrations.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour aux inscriptions
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Détails de l'inscription</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name" class="form-label">Nom du participant *</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?= htmlspecialchars($registration['name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($registration['email']) ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="event_id" class="form-label">Événement *</label>
                                <select id="event_id" name="event_id" class="form-control" required>
                                    <option value="">Sélectionner un événement</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>" 
                                                <?= $registration['event_id'] == $event['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($event['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Date d'inscription</label>
                                <input type="text" class="form-control" 
                                       value="<?= date('d/m/Y H:i', strtotime($registration['registered_at'])) ?>" 
                                       readonly>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Mettre à jour
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
</body>
</html> 