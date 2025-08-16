<?php
// admin/events.php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$events = [];
$total_events = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

try {
    // Build query with search and filters
    $query = "
        SELECT e.*, u.full_name as organizer_name 
        FROM events e 
        LEFT JOIN users u ON e.organizer_id = u.id 
        WHERE 1
    ";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " AND (e.title LIKE ? OR e.description LIKE ?)";
        $params[] = '%' . $_GET['search'] . '%';
        $params[] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['status'])) {
        $query .= " AND e.status = ?";
        $params[] = $_GET['status'];
    }

    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM events e WHERE 1";
    $count_params = [];
    
    if (!empty($_GET['search'])) {
        $count_query .= " AND (e.title LIKE ? OR e.description LIKE ?)";
        $count_params[] = '%' . $_GET['search'] . '%';
        $count_params[] = '%' . $_GET['search'] . '%';
    }

    if (!empty($_GET['status'])) {
        $count_query .= " AND e.status = ?";
        $count_params[] = $_GET['status'];
    }

    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($count_params);
    $total_events = $count_stmt->fetchColumn();

    $total_pages = ceil($total_events / $limit);

    // Get events with pagination
    $query .= " ORDER BY e.event_date ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $events = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in admin/events.php: " . $e->getMessage());
    $error_message = "Une erreur est survenue lors du chargement des événements.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestion des événements | Admin | TaaBia</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <a href="event_registrations.php" class="nav-link">
          <i class="fas fa-user-check"></i>
          <span>Inscriptions</span>
        </a>
      </div>
      
                  <div class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </div>

            <div class="nav-item">
                <a href="blog_posts.php" class="nav-link">
                    <i class="fas fa-newspaper"></i>
                    <span>Articles de Blog</span>
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
        <h1 class="page-title">📆 Gestion des événements</h1>
        
        <div class="header-actions">
          <a href="add_event.php" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            Créer un nouvel événement
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
      <!-- Search and Filters -->
      <div class="search-filters">
        <form method="GET" class="filters-row">
          <div class="filter-group">
            <label class="form-label">Rechercher</label>
            <input type="text" name="search" class="form-control" 
                   placeholder="Titre ou description..." 
                   value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
          </div>
          
          <div class="filter-group">
            <label class="form-label">Statut</label>
            <select name="status" class="form-control">
              <option value="">Tous les statuts</option>
              <option value="upcoming" <?= ($_GET['status'] ?? '') === 'upcoming' ? 'selected' : '' ?>>À venir</option>
              <option value="ongoing" <?= ($_GET['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>En cours</option>
              <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Terminé</option>
              <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="form-label">&nbsp;</label>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-search"></i>
              Filtrer
            </button>
            <a href="events.php" class="btn btn-secondary">
              <i class="fas fa-times"></i>
              Réinitialiser
            </a>
          </div>
        </form>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon events">
              <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value"><?= number_format($total_events) ?></div>
              <div class="stat-label">Total Événements</div>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon upcoming">
              <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value"><?= number_format(array_count_values(array_column($events, 'status'))['upcoming'] ?? 0) ?></div>
              <div class="stat-label">À venir</div>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon ongoing">
              <i class="fas fa-play-circle"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value"><?= number_format(array_count_values(array_column($events, 'status'))['ongoing'] ?? 0) ?></div>
              <div class="stat-label">En cours</div>
            </div>
          </div>
        </div>
        
        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon completed">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value"><?= number_format(array_count_values(array_column($events, 'status'))['completed'] ?? 0) ?></div>
              <div class="stat-label">Terminés</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Events Table -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title">Liste des Événements</h3>
          <div class="d-flex gap-2">
            <span class="badge badge-primary"><?= $total_events ?> événements</span>
          </div>
        </div>
        
        <div class="table-container">
          <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
          <?php endif; ?>
          
          <table class="table">
            <thead>
              <tr>
                <th>Événement</th>
                <th>Date & Heure</th>
                <th>Formateur</th>
                <th>Statut</th>
                <th>Prix</th>
                <th>Créé le</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($events)): ?>
                <tr>
                  <td colspan="7" class="text-center" style="padding: 3rem;">
                    <i class="fas fa-calendar-alt" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                    <p>Aucun événement trouvé</p>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($events as $event): ?>
                  <tr>
                    <td>
                      <div class="d-flex align-center">
                        <div class="event-icon" style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #f44336, #ef5350); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
                          <i class="fas fa-calendar"></i>
                        </div>
                        <div>
                          <div style="font-weight: 600; color: var(--text-primary);">
                            <?= htmlspecialchars($event['title']) ?>
                          </div>
                          <div style="font-size: 0.875rem; color: var(--text-secondary);">
                            <?= htmlspecialchars(substr($event['description'], 0, 50)) ?>...
                          </div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div style="font-weight: 500;"><?= date('d/m/Y', strtotime($event['event_date'])) ?></div>
                      <div style="font-size: 0.875rem; color: var(--text-secondary);">
                        <?= date('H:i', strtotime($event['event_date'])) ?>
                      </div>
                    </td>
                    <td>
                                                      <div style="font-weight: 500;"><?= htmlspecialchars($event['organizer_name'] ?? 'N/A') ?></div>
                    </td>
                    <td>
                      <?php
                      $status_labels = [
                        'upcoming' => ['À venir', 'badge-info'],
                        'ongoing' => ['En cours', 'badge-warning'],
                        'completed' => ['Terminé', 'badge-success'],
                        'cancelled' => ['Annulé', 'badge-danger']
                      ];
                      $status_info = $status_labels[$event['status']] ?? ['Inconnu', 'badge-secondary'];
                      ?>
                      <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                    </td>
                    <td>
                      <div style="font-weight: 600; color: var(--success-color);">
                        <?= number_format($event['price'], 2) ?> GHS
                      </div>
                    </td>
                    <td>
                      <div style="font-size: 0.875rem; color: var(--text-secondary);">
                        <?= date('d/m/Y', strtotime($event['created_at'])) ?>
                      </div>
                      <div style="font-size: 0.75rem; color: var(--text-light);">
                        <?= date('H:i', strtotime($event['created_at'])) ?>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex gap-1">
                        <a href="edit_event.php?id=<?= $event['id'] ?>" 
                           class="btn btn-sm btn-secondary" 
                           title="Modifier">
                          <i class="fas fa-edit"></i>
                        </a>
                        
                        <a href="event_registrations.php?event_id=<?= $event['id'] ?>" 
                           class="btn btn-sm btn-info"
                           title="Voir les inscriptions">
                          <i class="fas fa-users"></i>
                        </a>
                        
                        <a href="delete_event.php?id=<?= $event['id'] ?>" 
                           class="btn btn-sm btn-danger"
                           title="Supprimer"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet événement ? Cette action est irréversible.')">
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
              <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>" 
                 class="btn btn-secondary">
                <i class="fas fa-chevron-left"></i>
              </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
              <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>" 
                 class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                <?= $i ?>
              </a>
            <?php endfor; ?>
            
            <?php if ($current_page < $total_pages): ?>
              <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>" 
                 class="btn btn-secondary">
                <i class="fas fa-chevron-right"></i>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Add smooth interactions
    document.addEventListener('DOMContentLoaded', function() {
      // Add hover effects to table rows
      const tableRows = document.querySelectorAll('.table tbody tr');
      tableRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
          this.style.transform = 'scale(1.01)';
          this.style.boxShadow = 'var(--shadow-light)';
        });
        
        row.addEventListener('mouseleave', function() {
          this.style.transform = 'scale(1)';
          this.style.boxShadow = 'none';
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
