<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
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
            $error_message = __("name_required");
        } elseif (strlen($name) < 3) {
            $error_message = __("name_min_length");
        } elseif ($price <= 0) {
            $error_message = __("price_required");
        } elseif ($stock < 0) {
            $error_message = __("stock_min_value");
        } else {
            // Handle image upload
            if (!empty($_FILES['image']['name'])) {
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $file_type = $_FILES['image']['type'];

                if (!in_array($file_type, $allowed_types)) {
                    $error_message = __("image_format_error");
                } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) { // 5MB limit
                    $error_message = __("image_size_error");
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
                        $error_message = __("image_upload_error");
                    }
                }
            }

            if (empty($error_message)) {
                $status = 'active';
                $vendor_id = null; // Admin created product

                $stmt = $pdo->prepare("
                    INSERT INTO products (vendor_id, name, price, description, image_url, stock_quantity, category, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                if ($stmt->execute([$vendor_id, $name, $price, $description, $image, $stock, $category, $status])) {
                    $success_message = __("product_created_successfully");

                    // Clear form data
                    $name = $price = $description = $stock = $category = '';
                } else {
                    $error_message = __("error_creating_product");
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in add_product: " . $e->getMessage());
        $error_message = __("database_error");
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
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_product_title') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
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
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-plus-circle"></i> <?= __('add_product_title') ?></h1>
                        <p><?= __('add_product_description') ?></p>
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

                        <div class="d-flex gap-2">
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <?= __('back_to_list') ?>
                            </a>
                        </div>

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
                    <h3><i class="fas fa-box"></i> <?= __('product_information') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="productForm">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-tag"></i> <?= __('product_name') ?> *
                                    </label>
                                    <input type="text" name="name" id="name" class="form-control"
                                        value="<?= htmlspecialchars($name ?? '') ?>" required
                                        placeholder="<?= __('product_name') ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="category" class="form-label">
                                        <i class="fas fa-folder"></i> <?= __('product_category') ?>
                                    </label>
                                    <select name="category" id="category" class="form-control">
                                        <option value="">-- <?= __('choose_category') ?> --</option>
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
                                        <i class="fas fa-money-bill-wave"></i> <?= __('product_price') ?> (GHS) *
                                    </label>
                                    <input type="number" name="price" id="price" class="form-control"
                                        value="<?= htmlspecialchars($price ?? '') ?>" step="0.01" min="0" required
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="stock" class="form-label">
                                        <i class="fas fa-boxes"></i> <?= __('stock_quantity') ?>
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
                                        <i class="fas fa-align-left"></i> <?= __('product_description') ?>
                                    </label>
                                    <textarea name="description" id="description" class="form-control" rows="4"
                                        placeholder="<?= __('product_description') ?>"><?= htmlspecialchars($description ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="image" class="form-label">
                                        <i class="fas fa-image"></i> <?= __('product_image') ?>
                                    </label>
                                    <input type="file" name="image" id="image" class="form-control"
                                        accept="image/*">
                                    <small class="text-muted">
                                        <?= __('image_optional') ?>
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> <?= __('create_product') ?>
                                    </button>

                                    <a href="products.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                                    </a>

                                    <button type="reset" class="btn btn-warning">
                                        <i class="fas fa-undo"></i> <?= __('reset') ?>
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
                    <h3><i class="fas fa-lightning-bolt"></i> <?= __('quick_actions') ?></h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="products.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> <?= __('view_all_products') ?>
                        </a>

                        <a href="add_course.php" class="btn btn-outline-success">
                            <i class="fas fa-plus"></i> <?= __('add_course') ?>
                        </a>

                        <a href="orders.php" class="btn btn-outline-info">
                            <i class="fas fa-shopping-cart"></i> <?= __('manage_orders') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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