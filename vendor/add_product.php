<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2) . ' GHS';
}

// Function to validate image
function validateImage($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return 'Format d\'image non supporté. Utilisez JPG, PNG, GIF ou WEBP';
    }
    
    if ($file['size'] > $max_size) {
        return 'L\'image est trop volumineuse. Taille maximale: 5MB';
    }
    
    return null;
}

// Function to optimize image
function optimizeImage($source_path, $destination_path, $max_width = 800) {
    $image_info = getimagesize($source_path);
    $width = $image_info[0];
    $height = $image_info[1];
    $type = $image_info[2];
    
    if ($width <= $max_width) {
        return copy($source_path, $destination_path);
    }
    
    $ratio = $max_width / $width;
    $new_width = $max_width;
    $new_height = $height * $ratio;
    
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        case IMAGETYPE_WEBP:
            $source = imagecreatefromwebp($source_path);
            break;
        default:
            return false;
    }
    
    imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($new_image, $destination_path, 85);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($new_image, $destination_path, 8);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($new_image, $destination_path);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($new_image, $destination_path, 85);
            break;
    }
    
    imagedestroy($source);
    imagedestroy($new_image);
    
    return $result;
}

// Get vendor statistics
try {
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_products,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_products,
            SUM(CASE WHEN stock_quantity <= 5 AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock_products,
            SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock_products
        FROM products 
        WHERE vendor_id = ?
    ");
    $stats_stmt->execute([$vendor_id]);
    $vendor_stats = $stats_stmt->fetch();
} catch (PDOException $e) {
    $vendor_stats = ['total_products' => 0, 'active_products' => 0, 'low_stock_products' => 0, 'out_of_stock_products' => 0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = (float)$_POST['price'];
    $category = sanitize($_POST['category']);
    $stock_quantity = (int)$_POST['stock_quantity'];
    $status = sanitize($_POST['status']);
    
    // Enhanced validation
    $validation_errors = [];
    
    if (empty($name)) {
        $validation_errors[] = 'Le nom du produit est requis';
    } elseif (strlen($name) < 3) {
        $validation_errors[] = 'Le nom du produit doit contenir au moins 3 caractères';
    } elseif (strlen($name) > 100) {
        $validation_errors[] = 'Le nom du produit ne peut pas dépasser 100 caractères';
    }
    
    if ($price < 0) {
        $validation_errors[] = 'Le prix doit être positif';
    } elseif ($price > 999999.99) {
        $validation_errors[] = 'Le prix ne peut pas dépasser 999,999.99 GHS';
    }
    
    if ($stock_quantity < 0) {
        $validation_errors[] = 'La quantité en stock doit être positive';
    } elseif ($stock_quantity > 999999) {
        $validation_errors[] = 'La quantité en stock ne peut pas dépasser 999,999';
    }
    
    if (strlen($description) > 1000) {
        $validation_errors[] = 'La description ne peut pas dépasser 1000 caractères';
    }
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_validation = validateImage($_FILES['image']);
        if ($image_validation) {
            $validation_errors[] = $image_validation;
        } else {
            $upload_dir = '../uploads/';
            $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = 'product_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                // Optimize image
                $optimized_path = $upload_dir . 'optimized_' . $filename;
                if (optimizeImage($upload_path, $optimized_path)) {
                    unlink($upload_path); // Remove original
                    $filename = 'optimized_' . $filename;
                }
                $image_url = $filename;
            } else {
                $validation_errors[] = 'Erreur lors du téléchargement de l\'image';
            }
        }
    }
    
    if (empty($validation_errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO products (name, description, price, category, vendor_id, stock_quantity, image_url, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $price, $category, $vendor_id, $stock_quantity, $image_url, $status]);
            
            $new_product_id = $pdo->lastInsertId();
            $message = '✅ Produit ajouté avec succès ! ID: ' . $new_product_id;
            
            // Clear form data
            $_POST = array();
            
            // Refresh vendor stats
            $stats_stmt->execute([$vendor_id]);
            $vendor_stats = $stats_stmt->fetch();
            
        } catch (PDOException $e) {
            $error = '❌ Erreur lors de l\'ajout du produit: ' . $e->getMessage();
        }
    } else {
        $error = '❌ ' . implode('<br>', $validation_errors);
    }
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_product') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.6;
        }

        .sidebar {
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #00796b 0%, #004d40 100%);
            color: white;
            position: fixed;
            padding: 2rem 1.5rem;
            overflow-y: auto;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 12px;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            padding: 1rem 1.2rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(5px);
        }

        .sidebar a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 280px;
            padding: 2rem;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: #1e293b;
            font-size: 2rem;
            font-weight: 700;
        }

        .vendor-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 4px solid #00796b;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #00796b;
            margin-bottom: 0.5rem;
        }

        .stat-card .label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f1f5f9;
        }

        .form-header h2 {
            color: #00796b;
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
        }

        .form-header p {
            color: #64748b;
        }

        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #00796b;
        }

        .form-section h3 {
            color: #1e293b;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #374151;
        }

        .form-group .required {
            color: #dc2626;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00796b;
            box-shadow: 0 0 0 3px rgba(0, 119, 107, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group .help-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .form-group .error-text {
            font-size: 0.85rem;
            color: #dc2626;
            margin-top: 0.25rem;
        }

        .form-group.error input,
        .form-group.error textarea,
        .form-group.error select {
            border-color: #dc2626;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            background: #00796b;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #00695c;
            transform: translateY(-1px);
        }

        .btn i {
            margin-right: 0.5rem;
        }

        .btn-secondary {
            background: #6b7280;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f1f5f9;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .image-section {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 12px;
            border: 2px dashed #cbd5e1;
            text-align: center;
            transition: all 0.3s ease;
        }

        .image-section:hover {
            border-color: #00796b;
            background: #f0fdf4;
        }

        .image-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }

        .image-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .upload-area {
            padding: 2rem;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            background: white;
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            border-color: #00796b;
            background: #f0fdf4;
        }

        .upload-area i {
            font-size: 3rem;
            color: #94a3b8;
            margin-bottom: 1rem;
        }

        .image-preview {
            margin-top: 1rem;
            max-width: 300px;
            margin-left: auto;
            margin-right: auto;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .character-count {
            font-size: 0.8rem;
            color: #64748b;
            text-align: right;
            margin-top: 0.25rem;
        }

        .character-count.warning {
            color: #d97706;
        }

        .character-count.error {
            color: #dc2626;
        }

        .price-input {
            position: relative;
        }

        .price-input::before {
            content: 'GHS';
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-weight: 500;
        }

        .price-input input {
            padding-left: 3.5rem;
        }

        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e2e8f0;
            border-radius: 2px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: #00796b;
            transition: width 0.3s ease;
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 1rem;
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            .vendor-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 <?= __('vendor_space') ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> <?= __('dashboard') ?></a>
        <a href="products.php"><i class="fas fa-box"></i> <?= __('my_products') ?></a>
        <a href="add_product.php"><i class="fas fa-plus"></i> <?= __('add_product') ?></a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> <?= __('orders') ?></a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> <?= __('my_earnings') ?></a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> <?= __('payments') ?></a>
        <a href="profile.php"><i class="fas fa-user"></i> <?= __('my_profile') ?></a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a>
    </div>

    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <h1><i class="fas fa-plus"></i> <?= __('add_product') ?></h1>
                <p>Ajoutez un nouveau produit à votre boutique</p>
            </div>
            <a href="products.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour aux produits
            </a>
        </div>

        <!-- Vendor Statistics -->
        <div class="vendor-stats">
            <div class="stat-card">
                <div class="value"><?= $vendor_stats['total_products'] ?></div>
                <div class="label">Produits Totaux</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $vendor_stats['active_products'] ?></div>
                <div class="label">Produits Actifs</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $vendor_stats['low_stock_products'] ?></div>
                <div class="label">Stock Faible</div>
            </div>
            <div class="stat-card">
                <div class="value"><?= $vendor_stats['out_of_stock_products'] ?></div>
                <div class="label">Rupture de Stock</div>
            </div>
        </div>

        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-plus"></i> Nouveau Produit</h2>
                <p>Remplissez les informations de votre produit</p>
            </div>

            <form method="POST" enctype="multipart/form-data" id="addProductForm">
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Informations de Base</h3>
                    
                    <div class="form-group">
                        <label for="name">Nom du produit <span class="required">*</span></label>
                        <input type="text" id="name" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" 
                               required maxlength="100" oninput="updateCharCount(this, 'nameCount')">
                        <div class="character-count" id="nameCount">0/100</div>
                        <div class="help-text">Le nom de votre produit (3-100 caractères)</div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Décrivez votre produit en détail..." 
                                  maxlength="1000" oninput="updateCharCount(this, 'descCount')"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        <div class="character-count" id="descCount">0/1000</div>
                        <div class="help-text">Description détaillée de votre produit (max 1000 caractères)</div>
                    </div>
                </div>

                <!-- Pricing and Stock -->
                <div class="form-section">
                    <h3><i class="fas fa-tags"></i> Prix et Stock</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="price">Prix <span class="required">*</span></label>
                            <div class="price-input">
                                <input type="number" id="price" name="price" step="0.01" min="0" max="999999.99" 
                                       value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                            </div>
                            <div class="help-text">Prix en GHS (0.01 - 999,999.99)</div>
                        </div>

                        <div class="form-group">
                            <label for="stock_quantity">Quantité en stock</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" min="0" max="999999" 
                                   value="<?= htmlspecialchars($_POST['stock_quantity'] ?? '0') ?>">
                            <div class="help-text">Nombre d'unités disponibles (0 - 999,999)</div>
                        </div>
                    </div>
                </div>

                <!-- Category and Status -->
                <div class="form-section">
                    <h3><i class="fas fa-cog"></i> Catégorie et Statut</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Catégorie</label>
                            <select id="category" name="category">
                                <option value="">Sélectionner une catégorie</option>
                                <option value="Électronique" <?= ($_POST['category'] ?? '') === 'Électronique' ? 'selected' : '' ?>>Électronique</option>
                                <option value="Vêtements" <?= ($_POST['category'] ?? '') === 'Vêtements' ? 'selected' : '' ?>>Vêtements</option>
                                <option value="Livres" <?= ($_POST['category'] ?? '') === 'Livres' ? 'selected' : '' ?>>Livres</option>
                                <option value="Sport" <?= ($_POST['category'] ?? '') === 'Sport' ? 'selected' : '' ?>>Sport</option>
                                <option value="Maison" <?= ($_POST['category'] ?? '') === 'Maison' ? 'selected' : '' ?>>Maison</option>
                                <option value="Beauté" <?= ($_POST['category'] ?? '') === 'Beauté' ? 'selected' : '' ?>>Beauté</option>
                                <option value="Alimentation" <?= ($_POST['category'] ?? '') === 'Alimentation' ? 'selected' : '' ?>>Alimentation</option>
                                <option value="Autre" <?= ($_POST['category'] ?? '') === 'Autre' ? 'selected' : '' ?>>Autre</option>
                            </select>
                            <div class="help-text">Catégorie principale de votre produit</div>
                        </div>

                        <div class="form-group">
                            <label for="status">Statut</label>
                            <select id="status" name="status">
                                <option value="active" <?= ($_POST['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Actif</option>
                                <option value="inactive" <?= ($_POST['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                                <option value="out_of_stock" <?= ($_POST['status'] ?? '') === 'out_of_stock' ? 'selected' : '' ?>>Rupture de stock</option>
                            </select>
                            <div class="help-text">Statut de visibilité du produit</div>
                        </div>
                    </div>
                </div>

                <!-- Image Upload -->
                <div class="form-section">
                    <h3><i class="fas fa-image"></i> Image du Produit</h3>
                    
                    <div class="image-section">
                        <div class="image-upload">
                            <div class="upload-area" onclick="document.getElementById('image').click()">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p><strong>Cliquez pour sélectionner une image</strong></p>
                                <p>ou glissez-déposez une image ici</p>
                                <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.5rem;">
                                    Formats acceptés: JPG, PNG, GIF, WEBP (max 5MB)
                                </p>
                            </div>
                            <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                        </div>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Annuler
                    </a>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Ajouter le produit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Character count functionality
        function updateCharCount(element, countId) {
            const count = element.value.length;
            const maxLength = element.maxLength;
            const countElement = document.getElementById(countId);
            
            countElement.textContent = `${count}/${maxLength}`;
            
            // Update color based on usage
            const percentage = (count / maxLength) * 100;
            countElement.className = 'character-count';
            
            if (percentage >= 90) {
                countElement.classList.add('error');
            } else if (percentage >= 75) {
                countElement.classList.add('warning');
            }
        }

        // Initialize character counts
        document.addEventListener('DOMContentLoaded', function() {
            updateCharCount(document.getElementById('name'), 'nameCount');
            updateCharCount(document.getElementById('description'), 'descCount');
        });

        // Image preview functionality
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                // Validate file size
                if (file.size > 5 * 1024 * 1024) {
                    alert('L\'image est trop volumineuse. Taille maximale: 5MB');
                    input.value = '';
                    return;
                }
                
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format d\'image non supporté. Utilisez JPG, PNG, GIF ou WEBP');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    preview.appendChild(img);
                }
                reader.readAsDataURL(file);
            }
        }

        // Drag and drop functionality
        const uploadArea = document.querySelector('.upload-area');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            uploadArea.style.borderColor = '#00796b';
            uploadArea.style.background = '#f0fdf4';
        }

        function unhighlight(e) {
            uploadArea.style.borderColor = '#cbd5e1';
            uploadArea.style.background = 'white';
        }

        uploadArea.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                document.getElementById('image').files = files;
                previewImage(document.getElementById('image'));
            }
        }

        // Form validation
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock_quantity').value);
            
            let isValid = true;
            
            // Clear previous errors
            document.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('error');
            });
            
            // Validate name
            if (name.length < 3) {
                showError('name', 'Le nom doit contenir au moins 3 caractères');
                isValid = false;
            }
            
            // Validate price
            if (price < 0) {
                showError('price', 'Le prix doit être positif');
                isValid = false;
            }
            
            // Validate stock
            if (stock < 0) {
                showError('stock_quantity', 'La quantité en stock doit être positive');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
            }
        });

        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const formGroup = field.closest('.form-group');
            formGroup.classList.add('error');
            
            let errorElement = formGroup.querySelector('.error-text');
            if (!errorElement) {
                errorElement = document.createElement('div');
                errorElement.className = 'error-text';
                formGroup.appendChild(errorElement);
            }
            errorElement.textContent = message;
        }

        // Auto-save draft functionality
        let autoSaveTimeout;
        const form = document.getElementById('addProductForm');
        
        form.addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(function() {
                saveDraft();
            }, 2000); // Save draft after 2 seconds of inactivity
        });

        function saveDraft() {
            const formData = new FormData(form);
            const draft = {};
            
            for (let [key, value] of formData.entries()) {
                if (key !== 'image') { // Don't save image in draft
                    draft[key] = value;
                }
            }
            
            localStorage.setItem('product_draft_new', JSON.stringify(draft));
        }

        // Load draft on page load
        window.addEventListener('load', function() {
            const draft = localStorage.getItem('product_draft_new');
            if (draft) {
                const draftData = JSON.parse(draft);
                for (let key in draftData) {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field && !field.value) {
                        field.value = draftData[key];
                        if (field.tagName === 'TEXTAREA' || field.tagName === 'INPUT') {
                            updateCharCount(field, key === 'name' ? 'nameCount' : 'descCount');
                        }
                    }
                }
            }
        });

        // Clear draft on successful save
        form.addEventListener('submit', function() {
            localStorage.removeItem('product_draft_new');
        });

        // Real-time price formatting
        document.getElementById('price').addEventListener('input', function(e) {
            const value = parseFloat(e.target.value);
            if (!isNaN(value) && value > 0) {
                const formatted = new Intl.NumberFormat('en-GH', {
                    style: 'currency',
                    currency: 'GHS'
                }).format(value);
                // You can add a preview element to show formatted price
            }
        });

        // Stock level indicators
        document.getElementById('stock_quantity').addEventListener('input', function(e) {
            const value = parseInt(e.target.value);
            const field = e.target;
            
            // Remove previous classes
            field.classList.remove('low-stock', 'out-of-stock', 'good-stock');
            
            if (value === 0) {
                field.classList.add('out-of-stock');
            } else if (value <= 5) {
                field.classList.add('low-stock');
            } else {
                field.classList.add('good-stock');
            }
        });
    </script>
</body>
</html>