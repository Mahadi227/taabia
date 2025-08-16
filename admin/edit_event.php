<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$success_message = '';
$error_message = '';

// Get event data
if ($event_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error in admin/edit_event.php: " . $e->getMessage());
    }
}

if (!$event) {
    redirect('events.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $event_date = $_POST['event_date'];
        $organizer_id = (int)($_POST['organizer_id'] ?? 0);
        $event_type = trim($_POST['event_type'] ?? 'webinar');
        $duration = (int)($_POST['duration'] ?? 60);
        $max_participants = (int)($_POST['max_participants'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $meeting_url = trim($_POST['meeting_url'] ?? '');
        $status = trim($_POST['status'] ?? 'upcoming');

        // Validation
        if (empty($title)) {
            $error_message = "Le titre de l'événement est obligatoire.";
        } elseif (empty($description)) {
            $error_message = "La description est obligatoire.";
        } elseif (empty($event_date)) {
            $error_message = "La date de l'événement est obligatoire.";
        } elseif (empty($organizer_id)) {
            $error_message = "L'organisateur est obligatoire.";
        } else {
            // Update event
            $stmt = $pdo->prepare("
                UPDATE events 
                SET title = ?, description = ?, event_date = ?, organizer_id = ?, event_type = ?, 
                    duration = ?, max_participants = ?, price = ?, meeting_url = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");

            if ($stmt->execute([$title, $description, $event_date, $organizer_id, $event_type, 
                              $duration, $max_participants, $price, $meeting_url, $status, $event_id])) {
                $success_message = "Événement mis à jour avec succès !";
                
                // Refresh event data
                $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
                $stmt->execute([$event_id]);
                $event = $stmt->fetch();
            } else {
                $error_message = "Erreur lors de la mise à jour de l'événement.";
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in edit_event: " . $e->getMessage());
        $error_message = "Erreur de base de données.";
    }
}

// Get organizers for dropdown
try {
    $organizers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'instructor') AND is_active = 1 ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in admin/edit_event.php: " . $e->getMessage());
    $organizers = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier Événement | Admin | TaaBia</title>
    
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
                    <h1><i class="fas fa-edit"></i> Modifier l'Événement</h1>
                    <p>Modifier les informations de l'événement</p>
                </div>
                
                <div class="header-actions">
                    <div class="d-flex gap-2">
                        <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-info">
                            <i class="fas fa-eye"></i> Voir
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
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Event Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Informations de l'Événement</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="eventForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title" class="form-label">
                                    <i class="fas fa-heading"></i> Titre de l'Événement *
                                </label>
                                <input type="text" name="title" id="title" class="form-control" 
                                       value="<?= htmlspecialchars($event['title']) ?>" required
                                       placeholder="Entrez le titre de l'événement">
                            </div>
                            
                            <div class="form-group">
                                <label for="organizer_id" class="form-label">
                                    <i class="fas fa-user-tie"></i> Organisateur *
                                </label>
                                <select name="organizer_id" id="organizer_id" class="form-control" required>
                                    <option value="">-- Choisir un organisateur --</option>
                                    <?php foreach ($organizers as $organizer): ?>
                                        <option value="<?= $organizer['id'] ?>" 
                                                <?= $event['organizer_id'] == $organizer['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($organizer['full_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="event_date" class="form-label">
                                    <i class="fas fa-calendar"></i> Date de l'Événement *
                                </label>
                                <input type="datetime-local" name="event_date" id="event_date" class="form-control" 
                                       value="<?= date('Y-m-d\TH:i', strtotime($event['event_date'])) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="event_type" class="form-label">
                                    <i class="fas fa-tag"></i> Type d'Événement
                                </label>
                                <select name="event_type" id="event_type" class="form-control">
                                    <option value="webinar" <?= $event['event_type'] === 'webinar' ? 'selected' : '' ?>>Webinaire</option>
                                    <option value="workshop" <?= $event['event_type'] === 'workshop' ? 'selected' : '' ?>>Atelier</option>
                                    <option value="meetup" <?= $event['event_type'] === 'meetup' ? 'selected' : '' ?>>Meetup</option>
                                    <option value="conference" <?= $event['event_type'] === 'conference' ? 'selected' : '' ?>>Conférence</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="duration" class="form-label">
                                    <i class="fas fa-clock"></i> Durée (minutes)
                                </label>
                                <input type="number" name="duration" id="duration" class="form-control" 
                                       value="<?= htmlspecialchars($event['duration']) ?>" min="15" max="480">
                            </div>
                            
                            <div class="form-group">
                                <label for="max_participants" class="form-label">
                                    <i class="fas fa-users"></i> Participants Max
                                </label>
                                <input type="number" name="max_participants" id="max_participants" class="form-control" 
                                       value="<?= htmlspecialchars($event['max_participants']) ?>" min="1">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price" class="form-label">
                                    <i class="fas fa-money-bill-wave"></i> Prix (GHS)
                                </label>
                                <input type="number" name="price" id="price" class="form-control" 
                                       value="<?= htmlspecialchars($event['price']) ?>" step="0.01" min="0">
                            </div>
                            
                            <div class="form-group">
                                <label for="status" class="form-label">
                                    <i class="fas fa-toggle-on"></i> Statut
                                </label>
                                <select name="status" id="status" class="form-control">
                                    <option value="upcoming" <?= $event['status'] === 'upcoming' ? 'selected' : '' ?>>À venir</option>
                                    <option value="ongoing" <?= $event['status'] === 'ongoing' ? 'selected' : '' ?>>En cours</option>
                                    <option value="completed" <?= $event['status'] === 'completed' ? 'selected' : '' ?>>Terminé</option>
                                    <option value="cancelled" <?= $event['status'] === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="meeting_url" class="form-label">
                                <i class="fas fa-link"></i> URL de Réunion
                            </label>
                            <input type="url" name="meeting_url" id="meeting_url" class="form-control" 
                                   value="<?= htmlspecialchars($event['meeting_url']) ?>"
                                   placeholder="https://meet.google.com/...">
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">
                                <i class="fas fa-align-left"></i> Description *
                            </label>
                            <textarea name="description" id="description" class="form-control" rows="6"
                                      placeholder="Décrivez l'événement en détail..."><?= htmlspecialchars($event['description']) ?></textarea>
                        </div>
                        
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer les Modifications
                            </button>
                            
                            <a href="view_event.php?id=<?= $event['id'] ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> Voir l'Événement
                            </a>
                            
                            <a href="events.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour
                            </a>
                            
                            <button type="reset" class="btn btn-warning">
                                <i class="fas fa-undo"></i> Réinitialiser
                            </button>
                        </div>
                    </form>
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
                                <strong>ID de l'événement:</strong>
                                <span>#<?= $event['id'] ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Date de création:</strong>
                                <span><?= date('d/m/Y H:i', strtotime($event['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong>Dernière modification:</strong>
                                <span><?= isset($event['updated_at']) ? date('d/m/Y H:i', strtotime($event['updated_at'])) : 'Non modifié' ?></span>
                            </div>
                            <div class="info-item">
                                <strong>Statut actuel:</strong>
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
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('eventForm');
            const priceInput = document.getElementById('price');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            
            // Price validation
            priceInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value < 0) {
                    this.value = 0;
                }
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const description = descriptionInput.value.trim();
                const price = parseFloat(priceInput.value);
                const organizer = document.getElementById('organizer_id').value;
                const eventDate = document.getElementById('event_date').value;
                
                if (!title) {
                    e.preventDefault();
                    alert('Veuillez saisir le titre de l\'événement.');
                    titleInput.focus();
                    return;
                }
                
                if (!description) {
                    e.preventDefault();
                    alert('Veuillez saisir la description de l\'événement.');
                    descriptionInput.focus();
                    return;
                }
                
                if (!organizer) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un organisateur.');
                    document.getElementById('organizer_id').focus();
                    return;
                }
                
                if (!eventDate) {
                    e.preventDefault();
                    alert('Veuillez sélectionner une date pour l\'événement.');
                    document.getElementById('event_date').focus();
                    return;
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
    </style>
</body>
</html>
