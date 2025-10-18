<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
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
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= __('events') ?> | <?= __('admin_panel') ?> | TaaBia</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="admin-styles.css">

  <style>
    /* Hamburger Menu Styles */
    .hamburger-menu {
      display: none;
      flex-direction: column;
      justify-content: space-around;
      width: 30px;
      height: 30px;
      background: transparent;
      border: none;
      cursor: pointer;
      padding: 0;
      z-index: 1001;
      transition: all 0.3s ease;
    }

    .hamburger-line {
      width: 100%;
      height: 3px;
      background-color: var(--text-primary);
      border-radius: 2px;
      transition: all 0.3s ease;
      transform-origin: center;
    }

    /* Hamburger menu animation */
    .hamburger-menu.active .hamburger-line:nth-child(1) {
      transform: rotate(45deg) translate(6px, 6px);
    }

    .hamburger-menu.active .hamburger-line:nth-child(2) {
      opacity: 0;
    }

    .hamburger-menu.active .hamburger-line:nth-child(3) {
      transform: rotate(-45deg) translate(6px, -6px);
    }

    /* Sidebar overlay for mobile */
    .sidebar-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 999;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
      opacity: 1;
    }

    @media (max-width: 768px) {
      .hamburger-menu {
        display: flex;
      }

      .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        z-index: 1000;
      }

      .sidebar.active {
        transform: translateX(0);
      }

      .sidebar-overlay {
        display: block;
      }

      .main-content {
        margin-left: 0;
      }

      .header-content {
        padding-left: 20px;
      }
    }

    .events-table-container {
      max-width: 100%;
      max-height: 600px;
      overflow-x: auto;
      overflow-y: auto;
    }

    /* Admin Language Switcher */
    .admin-language-switcher {
      position: relative;
      display: inline-block;
    }

    .admin-language-dropdown {
      position: relative;
    }

    .admin-language-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: var(--light-color);
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius-sm);
      cursor: pointer;
      font-size: 14px;
      color: var(--dark-color);
      transition: var(--transition);
    }

    .admin-language-btn:hover {
      background: white;
      border-color: var(--primary-color);
    }

    .admin-language-menu {
      position: absolute;
      top: 100%;
      right: 0;
      background: white;
      border: 1px solid var(--border-color);
      border-radius: var(--border-radius-sm);
      box-shadow: var(--shadow-lg);
      min-width: 150px;
      z-index: 1000;
      display: none;
      margin-top: 4px;
    }

    .admin-language-menu.show {
      display: block;
    }

    .admin-language-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 12px;
      text-decoration: none;
      color: var(--dark-color);
      transition: var(--transition);
      border-bottom: 1px solid var(--border-color);
    }

    .admin-language-item:last-child {
      border-bottom: none;
    }

    .admin-language-item:hover {
      background: var(--light-color);
    }

    .admin-language-item.active {
      background: var(--primary-color);
      color: white;
    }

    .language-flag {
      font-size: 16px;
    }

    .language-name {
      flex: 1;
      font-size: 14px;
    }

    .admin-language-item i {
      font-size: 12px;
      margin-left: auto;
    }
  </style>
</head>

<body>
  <!-- Sidebar Overlay for Mobile -->
  <div class="sidebar-overlay" id="sidebarOverlay"></div>

  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content">
    <!-- Header -->
    <header class="header">
      <div class="header-content">
        <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
          <div class="page-title">
            <h1><i class="fas fa-calendar-alt"></i> <?= __('events') ?></h1>
            <p><?= __('event_management') ?></p>
          </div>

          <div style="display: flex; align-items: center; gap: 20px;">
            <!-- Language Switcher -->
            <div class="admin-language-switcher">
              <div class="admin-language-dropdown">
                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                  <i class="fas fa-globe"></i>
                  <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                  <i class="fas fa-chevron-down"></i>
                </button>

                <div class="admin-language-menu" id="adminLanguageDropdown">
                  <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                    <span class="language-flag">🇫🇷</span>
                    <span class="language-name">Français</span>
                    <?php if (getCurrentLanguage() == 'fr'): ?>
                      <i class="fas fa-check"></i>
                    <?php endif; ?>
                  </a>
                  <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                    <span class="language-flag">🇬🇧</span>
                    <span class="language-name">English</span>
                    <?php if (getCurrentLanguage() == 'en'): ?>
                      <i class="fas fa-check"></i>
                    <?php endif; ?>
                  </a>
                </div>
              </div>
            </div>

            <a href="add_event.php" class="btn btn-primary">
              <i class="fas fa-plus"></i>
              <?= __('add_event') ?>
            </a>

            <div class="user-menu">
              <?php
              $current_user = null;
              try {
                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([current_user_id()]);
                $current_user = $stmt->fetch();
              } catch (PDOException $e) {
                error_log("Error fetching current user: " . $e->getMessage());
              }
              ?>
              <div class="user-avatar">
                <i class="fas fa-user"></i>
              </div>
              <div>
                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
              </div>
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
            <label class="form-label"><?= __('search') ?></label>
            <input type="text" name="search" class="form-control" placeholder="<?= __('search_event_placeholder') ?>"
              value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
          </div>

          <div class="filter-group">
            <label class="form-label"><?= __('status') ?></label>
            <select name="status" class="form-control">
              <option value=""><?= __('all_statuses') ?></option>
              <option value="upcoming" <?= ($_GET['status'] ?? '') === 'upcoming' ? 'selected' : '' ?>><?= __('upcoming') ?></option>
              <option value="ongoing" <?= ($_GET['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>><?= __('ongoing') ?></option>
              <option value="completed" <?= ($_GET['status'] ?? '') === 'completed' ? 'selected' : '' ?>><?= __('completed') ?></option>
              <option value="cancelled" <?= ($_GET['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>><?= __('cancelled') ?></option>
            </select>
          </div>

          <div class="filter-group">
            <label class="form-label">&nbsp;</label>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-search"></i>
              <?= __('filter') ?>
            </button>
            <a href="events.php" class="btn btn-secondary">
              <i class="fas fa-times"></i>
              <?= __('reset') ?>
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
              <div class="stat-label"><?= __('total_events') ?></div>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon upcoming">
              <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                <?= number_format(array_count_values(array_column($events, 'status'))['upcoming'] ?? 0) ?>
              </div>
              <div class="stat-label"><?= __('upcoming_events') ?></div>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon ongoing">
              <i class="fas fa-play-circle"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                <?= number_format(array_count_values(array_column($events, 'status'))['ongoing'] ?? 0) ?>
              </div>
              <div class="stat-label"><?= __('ongoing_events') ?></div>
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-header">
            <div class="stat-icon completed">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
              <div class="stat-value">
                <?= number_format(array_count_values(array_column($events, 'status'))['completed'] ?? 0) ?>
              </div>
              <div class="stat-label"><?= __('completed_events') ?></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Events Table -->
      <div class="card">
        <div class="card-header">
          <h3 class="card-title"><?= __('events_list') ?></h3>
          <div class="d-flex gap-2">
            <span class="badge badge-primary"><?= $total_events ?> <?= __('events_count') ?></span>
          </div>
        </div>

        <div class="table-container">
          <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?= $error_message ?></div>
          <?php endif; ?>

          <div class="events-table-container">
            <table class="table">
              <thead>
                <tr>
                  <th><?= __('event') ?></th>
                  <th><?= __('date_time') ?></th>
                  <th><?= __('instructor') ?></th>
                  <th><?= __('status') ?></th>
                  <th><?= __('price') ?></th>
                  <th><?= __('created_date') ?></th>
                  <th><?= __('actions') ?></th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($events)): ?>
                  <tr>
                    <td colspan="7" class="text-center" style="padding: 3rem;">
                      <i class="fas fa-calendar-alt"
                        style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                      <p><?= __('no_events_found') ?></p>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($events as $event): ?>
                    <tr>
                      <td>
                        <div class="d-flex align-center">
                          <div class="event-icon"
                            style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #f44336, #ef5350); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white;">
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
                        <div style="font-weight: 500;">
                          <?= date('d/m/Y', strtotime($event['event_date'])) ?></div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">
                          <?= date('H:i', strtotime($event['event_date'])) ?>
                        </div>
                      </td>
                      <td>
                        <div style="font-weight: 500;">
                          <?= htmlspecialchars($event['organizer_name'] ?? 'N/A') ?></div>
                      </td>
                      <td>
                        <?php
                        $status_labels = [
                          'upcoming' => [__('upcoming'), 'badge-info'],
                          'ongoing' => [__('ongoing'), 'badge-warning'],
                          'completed' => [__('completed'), 'badge-success'],
                          'cancelled' => [__('cancelled'), 'badge-danger']
                        ];
                        $status_info = $status_labels[$event['status']] ?? [__('unknown'), 'badge-secondary'];
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
                            class="btn btn-sm btn-secondary" title="<?= __('edit') ?>">
                            <i class="fas fa-edit"></i>
                          </a>

                          <a href="event_registrations.php?event_id=<?= $event['id'] ?>"
                            class="btn btn-sm btn-info" title="<?= __('view_registrations') ?>">
                            <i class="fas fa-users"></i>
                          </a>

                          <a href="delete_event.php?id=<?= $event['id'] ?>"
                            class="btn btn-sm btn-danger" title="<?= __('delete') ?>"
                            onclick="return confirm('<?= __('confirm_delete_event') ?>')">
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

    // Hamburger menu functionality
    document.addEventListener('DOMContentLoaded', function() {
      const hamburgerMenu = document.getElementById('hamburgerMenu');
      const sidebar = document.getElementById('sidebar');
      const sidebarOverlay = document.getElementById('sidebarOverlay');

      function toggleSidebar() {
        hamburgerMenu.classList.toggle('active');
        sidebar.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');

        // Prevent body scroll when sidebar is open
        if (sidebar.classList.contains('active')) {
          document.body.style.overflow = 'hidden';
        } else {
          document.body.style.overflow = '';
        }
      }

      function closeSidebar() {
        hamburgerMenu.classList.remove('active');
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
      }

      // Event listeners for hamburger menu
      if (hamburgerMenu) {
        hamburgerMenu.addEventListener('click', toggleSidebar);
      }

      // Close sidebar when clicking overlay
      if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
      }

      // Close sidebar when clicking on nav links (mobile)
      const navLinks = document.querySelectorAll('.nav-link');
      navLinks.forEach(link => {
        link.addEventListener('click', function() {
          if (window.innerWidth <= 768) {
            closeSidebar();
          }
        });
      });

      // Close sidebar on window resize if screen becomes larger
      window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
          closeSidebar();
        }
      });

      // Keyboard navigation for hamburger menu
      if (hamburgerMenu) {
        hamburgerMenu.addEventListener('keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleSidebar();
          }
        });
      }
    });

    // Admin Language Switcher
    function toggleAdminLanguageDropdown() {
      const dropdown = document.getElementById('adminLanguageDropdown');
      dropdown.classList.toggle('show');
    }

    // Close admin language dropdown when clicking outside
    document.addEventListener('click', function(event) {
      const dropdown = document.getElementById('adminLanguageDropdown');
      const switcher = document.querySelector('.admin-language-switcher');

      if (switcher && !switcher.contains(event.target)) {
        dropdown.classList.remove('show');
      }
    });
  </script>
</body>

</html>