<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$course = null;
$instructor = null;
$lessons = [];
$enrollments = [];
$enrollment_count = 0;

if ($course_id > 0) {
    try {
        // Get course details with instructor info
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as instructor_name, u.email as instructor_email
            FROM courses c 
            LEFT JOIN users u ON c.instructor_id = u.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if ($course) {
            // Get lessons for this course
            $stmt = $pdo->prepare("
                SELECT id, title, content as description, content_type, order_index, created_at
                FROM course_contents 
                WHERE course_id = ? 
                ORDER BY order_index ASC
            ");
            $stmt->execute([$course_id]);
            $lessons = $stmt->fetchAll();
            
            // Get enrollment count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as enrollment_count
                FROM student_courses 
                WHERE course_id = ?
            ");
            $stmt->execute([$course_id]);
            $enrollment_count = $stmt->fetchColumn();
            
            // Get recent enrollments
            $stmt = $pdo->prepare("
                SELECT sc.*, u.full_name, u.email
                FROM student_courses sc
                LEFT JOIN users u ON sc.student_id = u.id
                WHERE sc.course_id = ?
                ORDER BY sc.enrolled_at DESC
                LIMIT 5
            ");
            $stmt->execute([$course_id]);
            $enrollments = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Database error in admin/view_course.php: " . $e->getMessage());
    }
}

if (!$course) {
    redirect('courses.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voir Formation | Admin | TaaBia</title>
    
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
                    <h1><i class="fas fa-eye"></i> Détails de la Formation</h1>
                    <p>Informations complètes sur la formation</p>
                </div>
                
                <div class="header-actions">
                    <div class="d-flex gap-2">
                        <a href="course_edit.php?id=<?= $course['id'] ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Modifier
                        </a>
                        <a href="course_toggle.php?id=<?= $course['id'] ?>&action=<?= $course['status'] === 'published' ? 'archive' : 'publish' ?>" 
                           class="btn <?= $course['status'] === 'published' ? 'btn-warning' : 'btn-success' ?>"
                           onclick="return confirm('Êtes-vous sûr de vouloir <?= $course['status'] === 'published' ? 'archiver' : 'publier' ?> cette formation ?')">
                            <i class="fas <?= $course['status'] === 'published' ? 'fa-archive' : 'fa-check' ?>"></i>
                            <?= $course['status'] === 'published' ? 'Archiver' : 'Publier' ?>
                        </a>
                        <a href="course_delete.php?id=<?= $course['id'] ?>" class="btn btn-danger"
                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette formation ? Cette action est irréversible.')">
                            <i class="fas fa-trash"></i> Supprimer
                        </a>
                        <a href="courses.php" class="btn btn-secondary">
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
            <!-- Course Information -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-book"></i> Informations de la Formation</h3>
                        </div>
                        <div class="card-body">
                            <div class="course-header">
                                <h2><?= htmlspecialchars($course['title']) ?></h2>
                                <div class="course-meta">
                                    <span class="badge <?php 
                                        switch($course['status']) {
                                            case 'published': echo 'badge-success'; break;
                                            case 'draft': echo 'badge-warning'; break;
                                            case 'archived': echo 'badge-danger'; break;
                                            default: echo 'badge-secondary'; break;
                                        }
                                    ?>">
                                        <?php 
                                        switch($course['status']) {
                                            case 'published': echo 'Publié'; break;
                                            case 'draft': echo 'Brouillon'; break;
                                            case 'archived': echo 'Archivé'; break;
                                            default: echo htmlspecialchars($course['status']); break;
                                        }
                                        ?>
                                    </span>
                                    <span class="price">GHS <?= number_format($course['price'], 2) ?></span>
                                </div>
                            </div>
                            
                            <div class="course-description">
                                <h4>Description</h4>
                                <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                            </div>
                            
                            <div class="course-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <strong>Formateur:</strong>
                                            <span><?= htmlspecialchars($course['instructor_name'] ?? 'Non assigné') ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Email du formateur:</strong>
                                            <span><?= htmlspecialchars($course['instructor_email'] ?? 'Non disponible') ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <strong>Date de création:</strong>
                                            <span><?= date('d/m/Y H:i', strtotime($course['created_at'])) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong>Dernière modification:</strong>
                                            <span><?= isset($course['updated_at']) ? date('d/m/Y H:i', strtotime($course['updated_at'])) : 'Non modifié' ?></span>
                                        </div>
                                    </div>
                                </div>
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
                                    <div class="stat-number"><?= number_format($enrollment_count) ?></div>
                                    <div class="stat-label">Inscriptions</div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format(count($lessons)) ?></div>
                                    <div class="stat-label">Leçons</div>
                                </div>
                            </div>
                            
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">GHS <?= number_format($course['price'] * $enrollment_count, 2) ?></div>
                                    <div class="stat-label">Revenus totaux</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Lessons -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-play-circle"></i> Leçons (<?= count($lessons) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($lessons)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-2">Aucune leçon disponible pour cette formation.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ordre</th>
                                        <th>Titre</th>
                                        <th>Description</th>
                                        <th>Type</th>
                                        <th>Date de création</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td><?= $lesson['order_index'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars(substr($lesson['description'], 0, 100)) ?>...</td>
                                            <td>
                                                <span class="badge badge-info"><?= htmlspecialchars($lesson['content_type'] ?? 'text') ?></span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($lesson['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Enrollments -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Inscriptions Récentes</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($enrollments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-2">Aucune inscription pour cette formation.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Étudiant</th>
                                        <th>Email</th>
                                        <th>Date d'inscription</th>
                                        <th>Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($enrollment['full_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($enrollment['email']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($enrollment['enrolled_at'])) ?></td>
                                            <td>
                                                <span class="badge badge-success">Inscrit</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .course-header {
            margin-bottom: 2rem;
        }
        
        .course-header h2 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .course-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .course-meta .price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--success-color);
        }
        
        .course-description {
            margin-bottom: 2rem;
        }
        
        .course-description h4 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .course-description p {
            line-height: 1.6;
            color: var(--text-secondary);
        }
        
        .course-details {
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