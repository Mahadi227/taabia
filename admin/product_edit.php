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

if (!isset($_GET['id'])) redirect('products.php');
$id = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch();
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
    $stock_quantity = (int) ($_POST['stock_quantity'] ?? 0);
    $category = sanitize($_POST['category'] ?? '');
    $image = $product['image_url'];

    // Validation
    $errors = [];
    if (empty($name)) {
        $errors[] = __("name_required");
    }
    if ($price <= 0) {
        $errors[] = __("price_required");
    }
    if (empty($description)) {
        $errors[] = __("description_required");
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

            $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, description = ?, status = ?, vendor_id = ?, image_url = ?, stock_quantity = ?, category = ? WHERE id = ?");
            $stmt->execute([$name, $price, $description, $status, $vendor_id, $image, $stock_quantity, $category, $id]);

            redirect('products.php');
        } catch (PDOException $e) {
            error_log("Database error in admin/product_edit.php update: " . $e->getMessage());
            $errors[] = __("error_updating_product");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_product') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> <?= __('edit_product') ?></h1>
                        <p><?= __('modify_course_info') ?></p>
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
                            <a href="view_product.php?id=<?= $product['id'] ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i>
                                <?= __('view') ?>
                            </a>
                            <a href="products.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <?= __('back') ?>
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
        </header>

        <!-- Content -->
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('edit_product') ?></h3>
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
                            <label for="name" class="form-label"><?= __('product_name') ?> *</label>
                            <input type="text" id="name" name="name" class="form-control"
                                value="<?= htmlspecialchars($product['name']) ?>" required>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                            <div class="form-group">
                                <label for="price" class="form-label"><?= __('product_price') ?> (GHS) *</label>
                                <input type="number" id="price" name="price" class="form-control"
                                    step="0.01" min="0"
                                    value="<?= htmlspecialchars($product['price']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label for="status" class="form-label"><?= __('status') ?> *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>><?= __('active') ?></option>
                                    <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>><?= __('inactive') ?></option>
                                    <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>><?= __('draft') ?></option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="vendor_id" class="form-label"><?= __('vendor') ?> *</label>
                            <select id="vendor_id" name="vendor_id" class="form-control" required>
                                <option value="">-- <?= __('choose_vendor') ?> --</option>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?= $vendor['id'] ?>" <?= $product['vendor_id'] == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($vendor['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                            <div class="form-group">
                                <label for="stock_quantity" class="form-label"><?= __('stock_quantity') ?></label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control"
                                    min="0" value="<?= htmlspecialchars($product['stock_quantity'] ?? 0) ?>">
                            </div>

                            <div class="form-group">
                                <label for="category" class="form-label"><?= __('product_category') ?></label>
                                <input type="text" id="category" name="category" class="form-control"
                                    value="<?= htmlspecialchars($product['category'] ?? '') ?>"
                                    placeholder="<?= __('product_category') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label"><?= __('product_description') ?> *</label>
                            <textarea id="description" name="description" class="form-control"
                                rows="4" required><?= htmlspecialchars($product['description']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= __('current_image') ?></label>
                            <?php if ($product['image_url']): ?>
                                <div style="margin-top: var(--spacing-sm);">
                                    <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                        style="max-width: 200px; height: auto; border-radius: var(--radius-sm);">
                                </div>
                            <?php else: ?>
                                <div style="color: var(--gray-500); font-style: italic;">
                                    <?= __('no_image') ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="image" class="form-label"><?= __('change_image') ?></label>
                            <input type="file" id="image" name="image" class="form-control" accept="image/*">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-600); margin-top: var(--spacing-xs);">
                                <?= __('image_optional') ?>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="products.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <?= __('cancel') ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?= __('save_changes') ?>
                            </button>
                        </div>
                    </form>
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

        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const submitBtn = document.querySelector('button[type="submit"]');

            if (form && submitBtn) {
                form.addEventListener('submit', function(e) {
                    // Add loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('saving') ?>...';
                    submitBtn.disabled = true;
                });
            }
        });
    </script>
</body>

</html>