<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$registrations = [];
$total_registrations = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

try {
    // Build query with search and filters
    $query = "
        SELECT er.id, er.name, er.email, er.registered_at,
               e.title AS event_title, e.event_date, e.status as event_status
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        WHERE 1
    ";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " AND (er.name LIKE ? OR er.email LIKE ? OR e.title LIKE ?)";
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['event_id'])) {
        $query .= " AND er.event_id = ?";
        $params[] = $_GET['event_id'];
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM event_registrations er JOIN events e ON er.event_id = e.id WHERE 1";
    $count_params = [];
    
    if (!empty($_GET['search'])) {
        $count_query .= " AND (er.name LIKE ? OR er.email LIKE ? OR e.title LIKE ?)";
        $count_params[] = '%' . $_GET['search'] . '%';
        $count_params[] = '%' . $_GET['search'] . '%';
        $count_params[] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['event_id'])) {
        $count_query .= " AND er.event_id = ?";
        $count_params[] = $_GET['event_id'];
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_registrations = $count_stmt->fetchColumn();

    $total_pages = ceil($total_registrations / $limit);

    // Get registrations with pagination
    $query .= " ORDER BY er.registered_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $registrations = $stmt->fetchAll();

    // Get events for filter
    $events = [];
    $event_stmt = $pdo->query("SELECT id, title FROM events ORDER BY title");
    $events = $event_stmt->fetchAll();

    // Get current user info for dynamic header
    $current_user = null;
    try {
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log("Database error in admin/event_registrations.php: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des inscriptions.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscriptions aux événements | Admin | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
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
                <h1><i class="fas fa-user-check"></i> Inscriptions aux événements</h1>
                
                <!-- User Menu -->
                <div class="user-menu">
                    <div class="user-avatar"><i class="fas fa-user"></i></div>
                    <div>
                        <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? 'Admin') ?></div>
                        <div style="font-size: 0.75rem; opacity: 0.7;"><?= htmlspecialchars($current_user['email'] ?? 'admin@taabia.com') ?></div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Filtres et Recherche</h3>
                </div>
                <div class="card-body">
                    <form method="GET" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="search" class="form-label">Rechercher</label>
                                <input type="text" id="search" name="search" class="form-control" 
                                       placeholder="Nom, email ou événement..." 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="event_id" class="form-label">Événement</label>
                                <select id="event_id" name="event_id" class="form-control">
                                    <option value="">Tous les événements</option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>" <?= ($_GET['event_id'] ?? '') == $event['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($event['title']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrer
                                    </button>
                                    <a href="event_registrations.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Réinitialiser
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon registrations">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_registrations) ?></div>
                            <div class="stat-label">Total Inscriptions</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon events">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count(array_unique(array_column($registrations, 'event_id')))) ?></div>
                            <div class="stat-label">Événements avec inscriptions</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon participants">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count(array_unique(array_column($registrations, 'email')))) ?></div>
                            <div class="stat-label">Participants uniques</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon recent">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count(array_filter($registrations, function($r) { return strtotime($r['registered_at']) > strtotime('-7 days'); }))) ?></div>
                            <div class="stat-label">Inscriptions récentes (7j)</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Registrations Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Inscriptions</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_registrations ?> inscriptions</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th>Événement</th>
                                <th>Date de l'événement</th>
                                <th>Statut de l'événement</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($registrations)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-user-check" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucune inscription trouvée</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($registrations as $registration): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #9c27b0, #ba68c8); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($registration['name']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                        <?= htmlspecialchars($registration['email']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($registration['event_title']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= date('d/m/Y', strtotime($registration['event_date'])) ?></div>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('H:i', strtotime($registration['event_date'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $event_status_labels = [
                                                'upcoming' => ['À venir', 'badge-primary'],
                                                'ongoing' => ['En cours', 'badge-success'],
                                                'completed' => ['Terminé', 'badge-secondary'],
                                                'cancelled' => ['Annulé', 'badge-danger']
                                            ];
                                            $event_status_info = $event_status_labels[$registration['event_status']] ?? ['Inconnu', 'badge-secondary'];
                                            ?>
                                            <span class="badge <?= $event_status_info[1] ?>"><?= $event_status_info[0] ?></span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($registration['registered_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($registration['registered_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_registration.php?id=<?= $registration['id'] ?>" 
                                                   class="btn btn-sm btn-outline" 
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <a href="delete_registration.php?id=<?= $registration['id'] ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette inscription ?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&event_id=<?= htmlspecialchars($_GET['event_id'] ?? '') ?>" 
                               class="btn btn-outline">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&event_id=<?= htmlspecialchars($_GET['event_id'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary' : 'btn-outline' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&event_id=<?= htmlspecialchars($_GET['event_id'] ?? '') ?>" 
                               class="btn btn-outline">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>