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

if (!isset($_GET['id'])) redirect('orders.php');
$order_id = (int) $_GET['id'];

try {
    // Get order details
    $stmt = $pdo->prepare("
        SELECT o.*, u.full_name, u.email, u.phone
        FROM orders o
        JOIN users u ON o.buyer_id = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        redirect('orders.php');
    }

    // Get order items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    // Get all products for adding items
    $products = $pdo->query("SELECT id, name, price FROM products WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in admin/edit_order.php: " . $e->getMessage());
    redirect('orders.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = sanitize($_POST['status']);
    $total_amount = (float) $_POST['total_amount'];
    $notes = sanitize($_POST['notes']);

    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, total_amount = ?, notes = ? WHERE id = ?");
        $stmt->execute([$status, $total_amount, $notes, $order_id]);

        redirect("order_view.php?id=$order_id");
    } catch (PDOException $e) {
        error_log("Database error in admin/edit_order.php update: " . $e->getMessage());
        $error_message = "Erreur lors de la mise à jour de la commande.";
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_order') ?> #<?= $order_id ?> | <?= __('admin_panel') ?> | TaaBia</title>
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
                        <h1><i class="fas fa-edit"></i> <?= __('edit_order') ?> #<?= $order_id ?></h1>
                        <p><?= __('modify_order_info') ?></p>
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
                            <a href="order_view.php?id=<?= $order_id ?>" class="btn btn-secondary">
                                <i class="fas fa-eye"></i>
                                <?= __('view') ?>
                            </a>
                            <a href="orders.php" class="btn btn-outline-secondary">
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
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
            <?php endif; ?>

            <!-- Order Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('order_information') ?></h3>
                </div>

                <div style="padding: var(--spacing-lg);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-lg);">
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-md); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-user"></i> <?= __('customer') ?>
                            </div>
                            <div style="color: var(--gray-700);">
                                <?= htmlspecialchars($order['full_name']) ?>
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                <?= htmlspecialchars($order['email']) ?>
                            </div>
                            <?php if ($order['phone']): ?>
                                <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                    <?= htmlspecialchars($order['phone']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-md); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                <i class="fas fa-calendar"></i> <?= __('order_date') ?>
                            </div>
                            <div style="color: var(--gray-700);">
                                <?= date('d/m/Y', strtotime($order['ordered_at'])) ?>
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                <?= date('H:i', strtotime($order['ordered_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('edit_order') ?></h3>
                </div>

                <div style="padding: var(--spacing-lg);">
                    <form method="POST">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-lg);">
                            <div class="form-group">
                                <label for="status" class="form-label"><?= __('status') ?> *</label>
                                <select id="status" name="status" class="form-control" required>
                                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>><?= __('processing') ?></option>
                                    <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>><?= __('shipped') ?></option>
                                    <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>><?= __('delivered') ?></option>
                                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>><?= __('cancelled') ?></option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="total_amount" class="form-label"><?= __('total_amount') ?> (GHS) *</label>
                                <input type="number" id="total_amount" name="total_amount" class="form-control"
                                    step="0.01" min="0"
                                    value="<?= htmlspecialchars($order['total_amount']) ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes" class="form-label"><?= __('notes') ?></label>
                            <textarea id="notes" name="notes" class="form-control"
                                rows="4"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                        </div>

                        <div class="form-actions">
                            <a href="order_view.php?id=<?= $order_id ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                <?= __('cancel') ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?= __('update') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Items -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('order_items') ?></h3>
                    <div class="d-flex gap-2">
                        <span class="badge badge-primary"><?= count($items) ?> <?= __('items') ?></span>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produit</th>
                                <th>Prix unitaire</th>
                                <th>Quantité</th>
                                <th>Sous-total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-center">
                                            <?php if ($item['image_url']): ?>
                                                <img src="../uploads/<?= htmlspecialchars($item['image_url']) ?>"
                                                    alt="<?= htmlspecialchars($item['name']) ?>"
                                                    style="width: 40px; height: 40px; object-fit: cover; border-radius: var(--radius-sm); margin-right: var(--spacing-sm);"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div style="width: 40px; height: 40px; background: var(--gray-200); border-radius: var(--radius-sm); margin-right: var(--spacing-sm); display: none; align-items: center; justify-content: center;">
                                                    <i class="fas fa-box" style="color: var(--gray-500);"></i>
                                                </div>
                                            <?php else: ?>
                                                <div style="width: 40px; height: 40px; background: var(--gray-200); border-radius: var(--radius-sm); margin-right: var(--spacing-sm); display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-box" style="color: var(--gray-500);"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 600; color: var(--text-primary);">
                                                    <?= htmlspecialchars($item['name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?= number_format($item['unit_price'], 2) ?> GHS</div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?= $item['quantity'] ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--success-color);">
                                            <?= number_format($item['quantity'] * $item['unit_price'], 2) ?> GHS
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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
    </script>
</body>

</html>