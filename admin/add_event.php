<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $organizer_id = (int)($_POST['organizer_id'] ?? 0);
    $event_type = trim($_POST['event_type'] ?? 'webinar');
    $duration = (int)($_POST['duration'] ?? 60);
    $max_participants = (int)($_POST['max_participants'] ?? 0);
    $price = (float)($_POST['price'] ?? 0);
    $meeting_url = trim($_POST['meeting_url'] ?? '');

    if ($title && $description && $event_date && $organizer_id) {
        try {
            $stmt = $pdo->prepare("INSERT INTO events (title, description, event_date, organizer_id, event_type, duration, max_participants, price, meeting_url, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming')");
            if ($stmt->execute([$title, $description, $event_date, $organizer_id, $event_type, $duration, $max_participants, $price, $meeting_url])) {
                $success = "✅ Événement ajouté avec succès.";
                $_POST = array();
            } else {
                $error = "❌ Une erreur est survenue lors de l'enregistrement.";
            }
        } catch (PDOException $e) {
            error_log("Database error in admin/add_event.php: " . $e->getMessage());
            $error = "❌ Une erreur est survenue lors de l'enregistrement.";
        }
    } else {
        $error = "❌ Tous les champs obligatoires doivent être remplis.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Événement | Admin | TaaBia</title>
    
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
        <header class="header">
            <div class="header-content">
                <h1 class="page-title">Créer un Nouvel Événement</h1>
                
                <div class="header-actions">
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Retour aux événements
                    </a>
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; font-size: 0.875rem;">Administrateur</div>
                            <div style="font-size: 0.75rem; opacity: 0.7;">Admin Panel</div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Flash Messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Event Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-calendar-plus"></i>
                        Informations de l'Événement
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-xl);">
                    <form method="post" action="" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-heading"></i>
                                    Titre de l'événement <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="title" 
                                       name="title" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                                       placeholder="Ex: Formation en Marketing Digital"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-user-tie"></i>
                                    Organisateur <span class="required">*</span>
                                </label>
                                <select id="organizer_id" 
                                        name="organizer_id" 
                                        class="form-control" 
                                        required>
                                    <option value="">-- Choisir un organisateur --</option>
                                    <?php
                                    try {
                                        $organizers = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('admin', 'instructor') AND is_active = 1 ORDER BY full_name")->fetchAll();
                                        foreach ($organizers as $organizer) {
                                            $selected = ($_POST['organizer_id'] ?? '') == $organizer['id'] ? 'selected' : '';
                                            echo "<option value='{$organizer['id']}' {$selected}>{$organizer['full_name']}</option>";
                                        }
                                    } catch (PDOException $e) {
                                        error_log("Error fetching organizers: " . $e->getMessage());
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i>
                                    Date de l'événement <span class="required">*</span>
                                </label>
                                <input type="date" 
                                       id="event_date" 
                                       name="event_date" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['event_date'] ?? '') ?>"
                                       min="<?= date('Y-m-d') ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-tag"></i>
                                    Type d'événement
                                </label>
                                <select id="event_type" 
                                        name="event_type" 
                                        class="form-control">
                                    <option value="webinar" <?= ($_POST['event_type'] ?? '') === 'webinar' ? 'selected' : '' ?>>Webinaire</option>
                                    <option value="workshop" <?= ($_POST['event_type'] ?? '') === 'workshop' ? 'selected' : '' ?>>Atelier</option>
                                    <option value="meetup" <?= ($_POST['event_type'] ?? '') === 'meetup' ? 'selected' : '' ?>>Meetup</option>
                                    <option value="conference" <?= ($_POST['event_type'] ?? '') === 'conference' ? 'selected' : '' ?>>Conférence</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-clock"></i>
                                    Durée (minutes)
                                </label>
                                <input type="number" 
                                       id="duration" 
                                       name="duration" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['duration'] ?? '60') ?>"
                                       min="15"
                                       max="480"
                                       placeholder="60">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-link"></i>
                                    URL de réunion
                                </label>
                                <input type="url" 
                                       id="meeting_url" 
                                       name="meeting_url" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['meeting_url'] ?? '') ?>"
                                       placeholder="https://meet.google.com/...">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">
                                <i class="fas fa-align-left"></i>
                                Description <span class="required">*</span>
                            </label>
                            <textarea id="description" 
                                      name="description" 
                                      class="form-control" 
                                      rows="6" 
                                      placeholder="Décrivez l'événement, ses objectifs, le programme, etc."
                                      required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Créer l'événement
                            </button>
                            <a href="events.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-lg);
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
        }
        
        .form-label {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-label i {
            color: var(--primary-color);
            width: 16px;
        }
        
        .required {
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-base);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 121, 96, 0.1);
        }
        
        .form-control::placeholder {
            color: var(--text-light);
        }
        
        .form-actions {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>

    <script>
        // Add smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add focus effects to form controls
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                control.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>
