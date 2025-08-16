<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$event = null;
$registrations = [];
$registration_count = 0;

if ($event_id > 0) {
    try {
        // Get event details with organizer info
        $stmt = $pdo->prepare("
            SELECT e.*, u.full_name as organizer_name, u.email as organizer_email
            FROM events e 
            LEFT JOIN users u ON e.organizer_id = u.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if ($event) {
            // Get registration count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as registration_count
                FROM event_registrations 
                WHERE event_id = ?
            ");
            $stmt->execute([$event_id]);
            $registration_count = $stmt->fetchColumn();
            
            // Get recent registrations
            $stmt = $pdo->prepare("
                SELECT er.*, u.full_name, u.email
                FROM event_registrations er
                LEFT JOIN users u ON er.participant_id = u.id
                WHERE er.event_id = ?
                ORDER BY er.registered_at DESC
                LIMIT 10
            ");
            $stmt->execute([$event_id]);
            $registrations = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Database error in admin/view_event.php: " . $e->getMessage());
    }
}

if (!$event) {
    redirect('events.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voir Événement | Admin | TaaBia</title>
    
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
                    <h1><i class="fas fa-eye"></i> Détails de l'Événement</h1>
                    <p>Informations complètes sur l'événement</p>
                </div>
                
                <div class="header-actions">
                    <div class="d-flex gap-2">
                        <a href="edit_event.php?id=<?= $event['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="event_registrations.php?event_id=<?= $event['id'] ?>" class="btn btn-info">
                            <i class="fas fa-users"></i> Inscriptions
                        </a>
                        <a href="delete_event.php?id=<?= $event['id'] ?>" class="btn btn-danger"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ? Cette action est irréversible.')">
                            <i class="fas fa-trash"></i> Supprimer
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
            <!-- Event Information -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-alt"></i> Informations de l'Événement</h3>
                        </div>
                        <div class="card-body">
                            <div class="event-header">
                                <h2><?= htmlspecialchars($event['title']) ?></h2>
                                <div class="event-meta">
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
                                    <span class="price">GHS <?= number_format($event['price'], 2) ?></span>
                                </div>
                            </div>
                            
                            <div class="event-description">
                                <h4>Description</h4>
                                <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>
                            </div>
                            
                            <div class="event-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <strong>Organisateur:</strong>
                                            <span><?= htmlspecialchars($event['organizer_name'] ?? 'Non assigné') ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Email de l'organisateur:</strong>
                                            <span><?= htmlspecialchars($event['organizer_email'] ?? 'Non disponible') ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Type d'événement:</strong>
                                            <span><?= htmlspecialchars(ucfirst($event['event_type'])) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <strong>Date de l'événement:</strong>
                                            <span><?= date('d/m/Y H:i', strtotime($event['event_date'])) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Durée:</strong>
                                            <span><?= $event['duration'] ?> minutes</span>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Participants max:</strong>
                                            <span><?= $event['max_participants'] ? $event['max_participants'] : 'Illimité' ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($event['meeting_url']): ?>
                                <div class="detail-item">
                                    <strong>URL de réunion:</strong>
                                    <span><a href="<?= htmlspecialchars($event['meeting_url']) ?>" target="_blank"><?= htmlspecialchars($event['meeting_url']) ?></a></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> Statistiques</h3>
                        </div>
                        <div class="card-body">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($registration_count) ?></div>
                                    <div class="stat-label">Inscriptions</div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">
                                        <?php 
                                        if ($event['max_participants'] > 0) {
                                            echo round(($registration_count / $event['max_participants']) * 100);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>%
                                    </div>
                                    <div class="stat-label">Taux de remplissage</div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">GHS <?= number_format($event['price'] * $registration_count, 2) ?></div>
                                    <div class="stat-label">Revenus totaux</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Registrations -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Inscriptions Récentes (<?= count($registrations) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($registrations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-2">Aucune inscription pour cet événement.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Participant</th>
                                        <th>Email</th>
                                        <th>Date d'inscription</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registrations as $registration): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($registration['full_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($registration['email']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($registration['registered_at'])) ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    switch($registration['status']) {
                                                        case 'registered': echo 'badge-info'; break;
                                                        case 'attended': echo 'badge-success'; break;
                                                        case 'no_show': echo 'badge-warning'; break;
                                                        case 'cancelled': echo 'badge-danger'; break;
                                                        default: echo 'badge-secondary'; break;
                                                    }
                                                ?>">
                                                    <?php 
                                                    switch($registration['status']) {
                                                        case 'registered': echo 'Inscrit'; break;
                                                        case 'attended': echo 'Présent'; break;
                                                        case 'no_show': echo 'Absent'; break;
                                                        case 'cancelled': echo 'Annulé'; break;
                                                        default: echo htmlspecialchars($registration['status']); break;
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="event_registrations.php?event_id=<?= $event['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-list"></i> Voir toutes les inscriptions
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .event-header {
            margin-bottom: 2rem;
        }
        
        .event-header h2 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .event-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .event-meta .price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--success-color);
        }
        
        .event-description {
            margin-bottom: 2rem;
        }
        
        .event-description h4 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .event-description p {
            line-height: 1.6;
            color: var(--text-secondary);
        }
        
        .event-details {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
        }
        
        .detail-item {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .detail-item strong {
            color: var(--text-primary);
        }
        
        .detail-item span {
            color: var(--text-secondary);
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
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