<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

// Initialize variables
$courses = [];
$total_courses = 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query with search and filters
$query = "SELECT c.id, c.title, c.description, c.price, c.status, c.created_at, u.full_name AS instructor 
          FROM courses c 
          LEFT JOIN users u ON c.instructor_id = u.id 
          WHERE 1";
$params = [];

if (!empty($_GET['search'])) {
    $query .= " AND (c.title LIKE ? OR c.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

if (!empty($_GET['status'])) {
    $query .= " AND c.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['instructor'])) {
    $query .= " AND c.instructor_id = ?";
    $params[] = $_GET['instructor'];
}

// Get total count for pagination
$count_query = str_replace("SELECT c.id, c.title, c.description, c.price, c.status, c.created_at, u.full_name AS instructor", "SELECT COUNT(*)", $query);
try {
    $count_stmt = $pdo->prepare($count_query);
    if ($count_stmt->execute($params)) {
        $total_courses = $count_stmt->fetchColumn();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/courses.php count: " . $e->getMessage());
}

$total_pages = ceil($total_courses / $limit);

// Get courses with pagination
$query .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

try {
    $stmt = $pdo->prepare($query);
    if ($stmt->execute($params)) {
        $courses = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/courses.php: " . $e->getMessage());
}

// Get instructors for filter
$instructors = [];
try {
    $instructor_stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'instructor' ORDER BY full_name");
    if ($instructor_stmt->execute()) {
        $instructors = $instructor_stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Database error in admin/courses.php instructors: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formations | Admin | TaaBia</title>
    
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
                <a href="courses.php" class="nav-link active">
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
                <a href="events.php" class="nav-link">
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
                <h1 class="page-title">Gestion des Formations</h1>
                
                <div class="header-actions">
                    <a href="add_course.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Ajouter une formation
                    </a>
                    
                    <div class="user-menu">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                                 <div style="font-weight: 600; font-size: 0.875rem;">
                                     <?= htmlspecialchars($current_user['full_name'] ?? 'Admin') ?>
                                 </div>
                                 <div style="font-size: 0.75rem; opacity: 0.7;">
                                     <?= htmlspecialchars($current_user['email'] ?? 'admin@taabia.com') ?>
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
                        <label class="form-label">Rechercher</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Titre, description ou instructeur..." 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous les statuts</option>
                            <option value="published" <?= ($_GET['status'] ?? '') === 'published' ? 'selected' : '' ?>>Publié</option>
                            <option value="draft" <?= ($_GET['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                            <option value="archived" <?= ($_GET['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archivé</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">Instructeur</label>
                        <select name="instructor" class="form-control">
                            <option value="">Tous les instructeurs</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= $instructor['id'] ?>" 
                                        <?= ($_GET['instructor'] ?? '') == $instructor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($instructor['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filtrer
                        </button>
                        <a href="courses.php" class="btn btn-secondary">
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
                        <div class="stat-icon courses">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format($total_courses) ?></div>
                            <div class="stat-label">Total Formations</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon instructors">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(count($instructors)) ?></div>
                            <div class="stat-label">Instructeurs</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value"><?= number_format(array_count_values(array_column($courses, 'status'))['published'] ?? 0) ?></div>
                            <div class="stat-label">Formations Publiées</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon revenue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">GHS<?= number_format(array_sum(array_column($courses, 'price')), 2) ?></div>
                            <div class="stat-label">Valeur Totale</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Courses Table -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Liste des Formations</h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= $total_courses ?> formations</span>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Formation</th>
                                <th>Instructeur</th>
                                <th>Prix</th>
                                <th>Statut</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        <i class="fas fa-book" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem; display: block;"></i>
                                        <p>Aucune formation trouvée</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-center">
                                                <div class="user-avatar" style="width: 40px; height: 40px; margin-right: 1rem; background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                                    <i class="fas fa-book"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 600; color: var(--text-primary);">
                                                        <?= htmlspecialchars($course['title']) ?>
                                                    </div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary); max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                        <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>...
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($course['instructor'] ?? 'Non assigné') ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 600; color: var(--success-color);">
                                                GHS<?= number_format($course['price'], 2) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($course['status'] === 'published'): ?>
                                                <span class="badge badge-success">Publié</span>
                                            <?php elseif ($course['status'] === 'draft'): ?>
                                                <span class="badge badge-warning">Brouillon</span>
                                            <?php elseif ($course['status'] === 'archived'): ?>
                                                <span class="badge badge-danger">Archivé</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary"><?= htmlspecialchars($course['status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                                <?= date('d/m/Y', strtotime($course['created_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-light);">
                                                <?= date('H:i', strtotime($course['created_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="view_course.php?id=<?= $course['id'] ?>" 
                                                   class="btn btn-sm btn-secondary" 
                                                   title="Voir">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <a href="course_edit.php?id=<?= $course['id'] ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Modifier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <a href="course_toggle.php?id=<?= $course['id'] ?>&action=<?= $course['status'] === 'published' ? 'archive' : 'publish' ?>" 
                                                   class="btn btn-sm <?= $course['status'] === 'published' ? 'btn-warning' : 'btn-success' ?>"
                                                   title="<?= $course['status'] === 'published' ? 'Archiver' : 'Publier' ?>"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir <?= $course['status'] === 'published' ? 'archiver' : 'publier' ?> cette formation ?')">
                                                    <i class="fas <?= $course['status'] === 'published' ? 'fa-archive' : 'fa-check' ?>"></i>
                                                </a>
                                                
                                                <a href="course_delete.php?id=<?= $course['id'] ?>" 
                                                   class="btn btn-sm btn-danger"
                                                   title="Supprimer"
                                                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette formation ? Cette action est irréversible.')">
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
                            <a href="?page=<?= $current_page - 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>" 
                               class="btn btn-secondary">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                            <a href="?page=<?= $i ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>" 
                               class="btn <?= $i === $current_page ? 'btn-primary active' : 'btn-secondary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?= $current_page + 1 ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>&status=<?= htmlspecialchars($_GET['status'] ?? '') ?>&instructor=<?= htmlspecialchars($_GET['instructor'] ?? '') ?>" 
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
