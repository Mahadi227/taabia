<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

$success_message = '';
$error_message = '';

// Récupérer les formateurs
try {
    $instructors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'instructor' AND is_active = 1 ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in admin/add_course.php: " . $e->getMessage());
    $instructors = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $instructor_id = (int) ($_POST['instructor_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');

        // Validation
        if (empty($title)) {
            $error_message = "Le titre de la formation est obligatoire.";
        } elseif (strlen($title) < 3) {
            $error_message = "Le titre doit contenir au moins 3 caractères.";
        } elseif (empty($description)) {
            $error_message = "La description est obligatoire.";
        } elseif (strlen($description) < 10) {
            $error_message = "La description doit contenir au moins 10 caractères.";
        } elseif ($price <= 0) {
            $error_message = "Le prix doit être supérieur à 0.";
        } elseif (empty($instructor_id)) {
            $error_message = "Le formateur est obligatoire.";
        } elseif (empty($status)) {
            $error_message = "Le statut est obligatoire.";
        } else {
            // Insert course
            $stmt = $pdo->prepare("
                INSERT INTO courses (title, description, price, instructor_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            if ($stmt->execute([$title, $description, $price, $instructor_id, $status])) {
                $success_message = "Formation créée avec succès !";
                
                // Clear form data
                $title = $description = $price = $instructor_id = $status = '';
            } else {
                $error_message = "Erreur lors de la création de la formation.";
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in add_course: " . $e->getMessage());
        $error_message = "Erreur de base de données.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter une Formation | Admin | TaaBia</title>
    
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
                    <h1><i class="fas fa-plus-circle"></i> Ajouter une Formation</h1>
                    <p>Créer une nouvelle formation pour la plateforme</p>
                </div>
                
                <div class="header-actions">
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

            <!-- Course Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> Informations de la Formation</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="courseForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="title" class="form-label">
                                        <i class="fas fa-heading"></i> Titre de la Formation *
                                    </label>
                                    <input type="text" name="title" id="title" class="form-control" 
                                           value="<?= htmlspecialchars($title ?? '') ?>" required
                                           placeholder="Entrez le titre de la formation">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status" class="form-label">
                                        <i class="fas fa-toggle-on"></i> Statut *
                                    </label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="">-- Choisir un statut --</option>
                                        <option value="draft" <?= ($status ?? '') === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                                        <option value="published" <?= ($status ?? '') === 'published' ? 'selected' : '' ?>>Publié</option>
                                        <option value="archived" <?= ($status ?? '') === 'archived' ? 'selected' : '' ?>>Archivé</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price" class="form-label">
                                        <i class="fas fa-money-bill-wave"></i> Prix (GHS) *
                                    </label>
                                    <input type="number" name="price" id="price" class="form-control" 
                                           value="<?= htmlspecialchars($price ?? '') ?>" step="0.01" min="0" required
                                           placeholder="0.00">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="instructor_id" class="form-label">
                                        <i class="fas fa-user-tie"></i> Formateur *
                                    </label>
                                    <select name="instructor_id" id="instructor_id" class="form-control" required>
                                        <option value="">-- Choisir un formateur --</option>
                                        <?php foreach ($instructors as $instructor): ?>
                                            <option value="<?= $instructor['id'] ?>" 
                                                    <?= ($instructor_id ?? '') == $instructor['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($instructor['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="description" class="form-label">
                                        <i class="fas fa-align-left"></i> Description *
                                    </label>
                                    <textarea name="description" id="description" class="form-control" rows="6"
                                              placeholder="Décrivez la formation en détail..."><?= htmlspecialchars($description ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Créer la Formation
                                    </button>
                                    
                                    <a href="courses.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Retour
                                    </a>
                                    
                                    <button type="reset" class="btn btn-warning">
                                        <i class="fas fa-undo"></i> Réinitialiser
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-lightning-bolt"></i> Actions Rapides</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="courses.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> Voir toutes les formations
                        </a>
                        
                        <a href="add_product.php" class="btn btn-outline-success">
                            <i class="fas fa-plus"></i> Ajouter un produit
                        </a>
                        
                        <a href="add_event.php" class="btn btn-outline-info">
                            <i class="fas fa-calendar-plus"></i> Créer un événement
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Statistiques des Formations</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        try {
                            // Total courses
                            $stmt = $pdo->query("SELECT COUNT(*) FROM courses");
                            $total_courses = $stmt->fetchColumn();
                            
                            // Published courses
                            $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'");
                            $published_courses = $stmt->fetchColumn();
                            
                            // Draft courses
                            $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'draft'");
                            $draft_courses = $stmt->fetchColumn();
                            
                            // Active instructors
                            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND is_active = 1");
                            $active_instructors = $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            error_log("Database error in add_course.php stats: " . $e->getMessage());
                            $total_courses = $published_courses = $draft_courses = $active_instructors = 0;
                        }
                        ?>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($total_courses) ?></div>
                                    <div class="stat-label">Total Formations</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($published_courses) ?></div>
                                    <div class="stat-label">Publiées</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($draft_courses) ?></div>
                                    <div class="stat-label">Brouillons</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #9c27b0, #ba68c8);">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($active_instructors) ?></div>
                                    <div class="stat-label">Formateurs Actifs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('courseForm');
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
                const instructor = document.getElementById('instructor_id').value;
                const status = document.getElementById('status').value;
                
                if (!title) {
                    e.preventDefault();
                    alert('Veuillez saisir le titre de la formation.');
                    titleInput.focus();
                    return;
                }
                
                if (title.length < 3) {
                    e.preventDefault();
                    alert('Le titre doit contenir au moins 3 caractères.');
                    titleInput.focus();
                    return;
                }
                
                if (!description) {
                    e.preventDefault();
                    alert('Veuillez saisir la description de la formation.');
                    descriptionInput.focus();
                    return;
                }
                
                if (description.length < 10) {
                    e.preventDefault();
                    alert('La description doit contenir au moins 10 caractères.');
                    descriptionInput.focus();
                    return;
                }
                
                if (price <= 0) {
                    e.preventDefault();
                    alert('Le prix doit être supérieur à 0.');
                    priceInput.focus();
                    return;
                }
                
                if (!instructor) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un formateur.');
                    document.getElementById('instructor_id').focus();
                    return;
                }
                
                if (!status) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un statut.');
                    document.getElementById('status').focus();
                    return;
                }
            });
            
            // Auto-save draft functionality
            let autoSaveTimer;
            const inputs = [titleInput, descriptionInput, priceInput];
            
            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        // Here you could implement auto-save functionality
                        console.log('Auto-save triggered...');
                    }, 2000);
                });
            });
        });
    </script>
</body>
</html>
