<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

if (!isset($_GET['id'])) redirect('products.php');
$id = (int) $_GET['id'];

try {
    $product = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $product->execute([$id]);
    $product = $product->fetch();
    if (!$product) redirect('products.php');

    // Get vendors for dropdown
    $vendors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'vendor' AND status = 'active' ORDER BY full_name")->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in admin/product_edit.php: " . $e->getMessage());
    redirect('products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $price = (float) $_POST['price'];
    $description = sanitize($_POST['description']);
    $status = sanitize($_POST['status']);
    $vendor_id = (int) $_POST['vendor_id'];
    $image = $product['image_url'];

    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = 'Le nom du produit est requis';
    }
    if ($price <= 0) {
        $errors[] = 'Le prix doit être supérieur à 0';
    }
    if (empty($description)) {
        $errors[] = 'La description est requise';
    }

    if (empty($errors)) {
        try {
            if (!empty($_FILES['image']['name'])) {
                $filename = time() . '_' . basename($_FILES['image']['name']);
                $target = '../uploads/' . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    $image = $filename;
                }
            }

            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, description = ?, status = ?, vendor_id = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$name, $price, $description, $status, $vendor_id, $image, $id]);

            redirect('products.php');
        } catch (PDOException $e) {
            error_log("Database error in admin/product_edit.php update: " . $e->getMessage());
            $errors[] = 'Erreur lors de la mise à jour du produit';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le produit | Admin | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
            <p>Plateforme de gestion</p>
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
                <a href="events.php" class="nav-link">
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
                <h1 class="page-title">📝 Modifier le produit</h1>
                
                <div class="header-actions">
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
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Modifier le produit</h3>
                </div>
                
                <div style="padding: var(--spacing-lg);">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul style="margin: 0; padding-left: var(--spacing-md);">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="name" class="form-label">Nom du produit *</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($product['name']) ?>" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                            <div class="form-group">
                                <label for="price" class="form-label">Prix (GHS) *</label>
                                <input type="number" id="price" name="price" class="form-control" 
                                       step="0.01" min="0" 
                                       value="<?= htmlspecialchars($product['price']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="status" class="form-label">Statut *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                                    <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                                    <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="vendor_id" class="form-label">Vendeur *</label>
                            <select id="vendor_id" name="vendor_id" class="form-control" required>
                                <option value="">-- Sélectionner un vendeur --</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?= $vendor['id'] ?>" <?= $product['vendor_id'] == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description *</label>
                            <textarea id="description" name="description" class="form-control" 
                                      rows="4" required><?= htmlspecialchars($product['description']) ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Image actuelle</label>
                            <?php if ($product['image_url']): ?>
                                <div style="margin-top: var(--spacing-sm);">
                                    <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>"
                                         style="max-width: 200px; height: auto; border-radius: var(--radius-sm);">
                                </div>
                            <?php else: ?>
                                <div style="color: var(--gray-500); font-style: italic;">
                                    Aucune image
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="image" class="form-label">Changer l'image</label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/*">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-600); margin-top: var(--spacing-xs);">
                                Formats acceptés: JPG, PNG, GIF. Taille max: 5MB
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Mettre à jour
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
