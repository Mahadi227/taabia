<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $stock = (int) ($_POST['stock'] ?? 0);
        $category = trim($_POST['category'] ?? '');
        $image = null;

        // Validation
        if (empty($name)) {
            $error_message = "Le nom du produit est obligatoire.";
        } elseif (strlen($name) < 3) {
            $error_message = "Le nom du produit doit contenir au moins 3 caractères.";
        } elseif ($price <= 0) {
            $error_message = "Le prix doit être supérieur à 0.";
        } elseif ($stock < 0) {
            $error_message = "Le stock ne peut pas être négatif.";
        } else {
            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['image']['type'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $error_message = "Format d'image non supporté. Utilisez JPG, PNG, GIF ou WebP.";
                } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error_message = "L'image est trop volumineuse. Taille maximale : 5MB.";
                } else {
                    $filename = time() . '_' . basename($_FILES['image']['name']);
                    $target = '../uploads/products/' . $filename;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir('../uploads/products/')) {
                        mkdir('../uploads/products/', 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                        $image = $filename;
                    } else {
                        $error_message = "Erreur lors du téléchargement de l'image.";
                    }
                }
            }

            if (empty($error_message)) {
                $status = 'active';
                $vendor_id = null; // Admin created product

                $stmt = $pdo->prepare("
                    INSERT INTO products (vendor_id, name, price, description, image_url, stock, category, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                if ($stmt->execute([$vendor_id, $name, $price, $description, $image, $stock, $category, $status])) {
                    $success_message = "Produit ajouté avec succès !";
                    
                    // Clear form data
                    $name = $price = $description = $stock = $category = '';
                } else {
                    $error_message = "Erreur lors de l'ajout du produit.";
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in add_product: " . $e->getMessage());
        $error_message = "Erreur de base de données.";
    }
}

// Get categories for dropdown
$categories = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database error getting categories: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit | Admin | TaaBia</title>
    
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
                    <span>Cours</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="products.php" class="nav-link active">
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
                <a href="payout_requests.php" class="nav-link">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Paiements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar"></i>
                    <span>Événements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
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
                    <h1><i class="fas fa-plus-circle"></i> Ajouter un Produit</h1>
                    <p>Créer un nouveau produit pour la plateforme</p>
                </div>
                
                <div class="header-actions">
                    <div class="user-menu">
                        <i class="fas fa-user-circle"></i>
                        <span><?= htmlspecialchars($current_user['full_name'] ?? 'Administrateur') ?></span>
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

            <!-- Product Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-box"></i> Informations du Produit</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-tag"></i> Nom du Produit *
                                    </label>
                                    <input type="text" name="name" id="name" class="form-control" 
                                           value="<?= htmlspecialchars($name ?? '') ?>" required
                                           placeholder="Entrez le nom du produit">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="category" class="form-label">
                                        <i class="fas fa-folder"></i> Catégorie
                                    </label>
                                    <select name="category" id="category" class="form-control">
                                        <option value="">-- Choisir une catégorie --</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?= htmlspecialchars($cat) ?>" 
                                                    <?= ($category ?? '') === $cat ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cat) ?>
                                            </option>
                                        <?php endforeach; ?>
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
                                    <label for="stock" class="form-label">
                                        <i class="fas fa-boxes"></i> Stock Initial
                                    </label>
                                    <input type="number" name="stock" id="stock" class="form-control" 
                                           value="<?= htmlspecialchars($stock ?? 0) ?>" min="0"
                                           placeholder="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="description" class="form-label">
                                        <i class="fas fa-align-left"></i> Description
                                    </label>
                                    <textarea name="description" id="description" class="form-control" rows="4"
                                              placeholder="Décrivez le produit en détail..."><?= htmlspecialchars($description ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="image" class="form-label">
                                        <i class="fas fa-image"></i> Image du Produit
                                    </label>
                                    <input type="file" name="image" id="image" class="form-control" 
                                           accept="image/*">
                                    <small class="text-muted">
                                        Formats acceptés : JPG, PNG, GIF, WebP. Taille maximale : 5MB
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Ajouter le Produit
                                    </button>
                                    
                                    <a href="products.php" class="btn btn-secondary">
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
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> Voir tous les produits
                        </a>
                        
                        <a href="add_course.php" class="btn btn-outline-success">
                            <i class="fas fa-plus"></i> Ajouter un cours
                        </a>
                        
                        <a href="orders.php" class="btn btn-outline-info">
                            <i class="fas fa-shopping-cart"></i> Gérer les commandes
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('productForm');
            const priceInput = document.getElementById('price');
            const stockInput = document.getElementById('stock');
            
            // Price validation
            priceInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value < 0) {
                    this.value = 0;
                }
            });
            
            // Stock validation
            stockInput.addEventListener('input', function() {
                const value = parseInt(this.value);
                if (value < 0) {
                    this.value = 0;
                }
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const name = document.getElementById('name').value.trim();
                const price = parseFloat(document.getElementById('price').value);
                
                if (!name) {
                    e.preventDefault();
                    alert('Veuillez saisir le nom du produit.');
                    return;
                }
                
                if (price <= 0) {
                    e.preventDefault();
                    alert('Le prix doit être supérieur à 0.');
                    return;
                }
            });
            
            // Image preview
            const imageInput = document.getElementById('image');
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert('L\'image est trop volumineuse. Taille maximale : 5MB');
                        this.value = '';
                    }
                }
            });
        });
    </script>
</body>
</html>
