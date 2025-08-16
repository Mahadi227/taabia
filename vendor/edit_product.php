<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    redirect('products.php');
}

// Get product details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
    $stmt->execute([$product_id, $vendor_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirect('products.php');
    }
} catch (PDOException $e) {
    redirect('products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = (float)$_POST['price'];
    $category = sanitize($_POST['category']);
    $stock_quantity = (int)$_POST['stock_quantity'];
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($name)) {
        $error = 'Le nom du produit est requis';
    } elseif ($price < 0) {
        $error = 'Le prix doit être positif';
    } elseif ($stock_quantity < 0) {
        $error = 'La quantité en stock doit être positive';
    } else {
        // Handle image upload
        $image_url = $product['image_url']; // Keep existing image by default
        
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $filename = time() . '_' . $_FILES['image']['name'];
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Delete old image if exists
                    if ($product['image_url'] && file_exists($upload_dir . $product['image_url'])) {
                        unlink($upload_dir . $product['image_url']);
                    }
                    $image_url = $filename;
                } else {
                    $error = 'Erreur lors du téléchargement de l\'image';
                }
            } else {
                $error = 'Format d\'image non supporté. Utilisez JPG, PNG, GIF ou WEBP';
            }
        }
        
        if (empty($error)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET name = ?, description = ?, price = ?, category = ?, stock_quantity = ?, image_url = ?, status = ?
                    WHERE id = ? AND vendor_id = ?
                ");
                $stmt->execute([$name, $description, $price, $category, $stock_quantity, $image_url, $status, $product_id, $vendor_id]);
                
                $message = 'Produit mis à jour avec succès !';
                
                // Refresh product data
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND vendor_id = ?");
                $stmt->execute([$product_id, $vendor_id]);
                $product = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour du produit: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier le Produit | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: #00796b;
            color: white;
            position: fixed;
            padding: 2rem 1rem;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.4rem;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            color: #00796b;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #00796b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background: #00695c;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .image-preview {
            margin-top: 0.5rem;
            max-width: 200px;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }

        .current-image {
            margin-bottom: 1rem;
        }

        .current-image img {
            max-width: 200px;
            border-radius: 5px;
            border: 2px solid #eee;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 Vendeur</h2>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Mes Produits</a>
        <a href="add_product.php"><i class="fas fa-plus"></i> Ajouter Produit</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> Mes Gains</a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> Paiements</a>
        <a href="profile.php"><i class="fas fa-user"></i> Mon Profil</a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-edit"></i> Modifier le Produit</h1>
                <p>Modifiez les informations de votre produit</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">Nom du produit *</label>
                    <input type="text" id="name" name="name" value="<?= htmlspecialchars($product['name']) ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Décrivez votre produit..."><?= htmlspecialchars($product['description']) ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">Prix (GHS) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="stock_quantity">Quantité en stock</label>
                        <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?= htmlspecialchars($product['stock_quantity']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Catégorie</label>
                        <select id="category" name="category">
                            <option value="">Sélectionner une catégorie</option>
                            <option value="Électronique" <?= $product['category'] === 'Électronique' ? 'selected' : '' ?>>Électronique</option>
                            <option value="Vêtements" <?= $product['category'] === 'Vêtements' ? 'selected' : '' ?>>Vêtements</option>
                            <option value="Livres" <?= $product['category'] === 'Livres' ? 'selected' : '' ?>>Livres</option>
                            <option value="Sport" <?= $product['category'] === 'Sport' ? 'selected' : '' ?>>Sport</option>
                            <option value="Maison" <?= $product['category'] === 'Maison' ? 'selected' : '' ?>>Maison</option>
                            <option value="Beauté" <?= $product['category'] === 'Beauté' ? 'selected' : '' ?>>Beauté</option>
                            <option value="Alimentation" <?= $product['category'] === 'Alimentation' ? 'selected' : '' ?>>Alimentation</option>
                            <option value="Autre" <?= $product['category'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select id="status" name="status">
                            <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                            <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                            <option value="out_of_stock" <?= $product['status'] === 'out_of_stock' ? 'selected' : '' ?>>Rupture de stock</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="image">Image du produit</label>
                    <?php if ($product['image_url']): ?>
                        <div class="current-image">
                            <p>Image actuelle:</p>
                            <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                    <div class="image-preview" id="imagePreview"></div>
                </div>

                <div class="form-actions">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>