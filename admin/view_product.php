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

if (!isset($_GET['id'])) redirect('products.php');
$product_id = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        redirect('products.php');
    }

    // Get product statistics
    $sales_count = 0;
    $total_revenue = 0;
    $recent_sales = [];

    try {
        // Get sales count and total revenue
        $stats_stmt = $pdo->prepare("
            SELECT COUNT(*) as sales_count, SUM(total_amount) as total_revenue 
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            WHERE oi.product_id = ? AND o.status = 'completed'
        ");
        $stats_stmt->execute([$product_id]);
        $stats = $stats_stmt->fetch();

        if ($stats) {
            $sales_count = $stats['sales_count'] ?? 0;
            $total_revenue = $stats['total_revenue'] ?? 0;
        }

        // Get recent sales
        $sales_stmt = $pdo->prepare("
            SELECT oi.*, o.created_at as sale_date, u.full_name as customer_name, u.email as customer_email
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id 
            LEFT JOIN users u ON o.user_id = u.id
            WHERE oi.product_id = ? AND o.status = 'completed'
            ORDER BY o.created_at DESC 
            LIMIT 5
        ");
        $sales_stmt->execute([$product_id]);
        $recent_sales = $sales_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error getting product stats: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Database error in admin/view_product.php: " . $e->getMessage());
    redirect('products.php');
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('view_product') ?> | <?= __('admin_panel') ?> | TaaBia</title>
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

        /* Statistics Styles */
        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
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
                        <h1><i class="fas fa-eye"></i> <?= __('product_details') ?></h1>
                        <p><?= __('product_complete_info') ?></p>
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
                            <a href="product_edit.php?id=<?= $product['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                <?= __('edit') ?>
                            </a>
                            <a href="products.php" class="btn btn-secondary">
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
            <!-- Product Information -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?= __('product_information') ?></h3>
                </div>

                <div style="padding: var(--spacing-lg);">
                    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-xl);">
                        <!-- Product Details -->
                        <div>
                            <div style="margin-bottom: var(--spacing-lg);">
                                <h4 style="margin-bottom: var(--spacing-sm); color: var(--text-primary);">
                                    <?= htmlspecialchars($product['name']) ?>
                                </h4>
                                <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--success-color); margin-bottom: var(--spacing-md);">
                                    <?= number_format($product['price'], 2) ?> GHS
                                </div>
                                <div style="color: var(--text-secondary); line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-lg);">
                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-md); 
                                    border-radius: var(--radius-lg);
                                ">
                                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                        <i class="fas fa-store"></i> <?= __('vendor') ?>
                                    </div>
                                    <div style="color: var(--gray-700);">
                                        <?= htmlspecialchars($product['vendor_name'] ?? 'N/A') ?>
                                    </div>
                                </div>

                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-md); 
                                    border-radius: var(--radius-lg);
                                ">
                                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                        <i class="fas fa-info-circle"></i> <?= __('status') ?>
                                    </div>
                                    <div>
                                        <?php
                                        $status_labels = [
                                            'active' => [__('active'), 'badge-success'],
                                            'inactive' => [__('inactive'), 'badge-warning'],
                                            'draft' => [__('draft'), 'badge-info']
                                        ];
                                        $status_info = $status_labels[$product['status']] ?? [__('unknown'), 'badge-secondary'];
                                        ?>
                                        <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                                    </div>
                                </div>

                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-md); 
                                    border-radius: var(--radius-lg);
                                ">
                                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-sm);">
                                        <i class="fas fa-calendar"></i> <?= __('creation_date') ?>
                                    </div>
                                    <div style="color: var(--gray-700);">
                                        <?= date('d/m/Y', strtotime($product['created_at'])) ?>
                                    </div>
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-600);">
                                        <?= date('H:i', strtotime($product['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Image -->
                        <div>
                            <?php if ($product['image_url']): ?>
                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-md); 
                                    border-radius: var(--radius-lg);
                                    text-align: center;
                                ">
                                    <img src="../uploads/<?= htmlspecialchars($product['image_url']) ?>"
                                        alt="<?= htmlspecialchars($product['name']) ?>"
                                        style="max-width: 100%; height: auto; border-radius: var(--radius-sm);">
                                </div>
                            <?php else: ?>
                                <div style="
                                    background: var(--gray-50); 
                                    padding: var(--spacing-xl); 
                                    border-radius: var(--radius-lg);
                                    text-align: center;
                                    color: var(--gray-500);
                                ">
                                    <i class="fas fa-image" style="font-size: 3rem; margin-bottom: var(--spacing-md);"></i>
                                    <div><?= __('no_image') ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> <?= __('statistics') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                    <i class="fas fa-shopping-cart"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($sales_count) ?></div>
                                    <div class="stat-label"><?= __('product_sales_count') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">GHS <?= number_format($total_revenue, 2) ?></div>
                                    <div class="stat-label"><?= __('product_total_revenue') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-boxes"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($product['stock_quantity'] ?? 0) ?></div>
                                    <div class="stat-label"><?= __('stock_quantity') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #9c27b0, #ba68c8);">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= date('d/m/Y', strtotime($product['created_at'])) ?></div>
                                    <div class="stat-label"><?= __('creation_date') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Sales -->
            <?php if (!empty($recent_sales)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> <?= __('product_recent_sales') ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= __('customer') ?></th>
                                        <th><?= __('sale_date') ?></th>
                                        <th><?= __('quantity_sold') ?></th>
                                        <th><?= __('sale_amount') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_sales as $sale): ?>
                                        <tr>
                                            <td>
                                                <div>
                                                    <div style="font-weight: 600;"><?= htmlspecialchars($sale['customer_name'] ?? __('unknown')) ?></div>
                                                    <div style="font-size: 0.875rem; color: var(--text-secondary);"><?= htmlspecialchars($sale['customer_email'] ?? '') ?></div>
                                                </div>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?></td>
                                            <td><?= $sale['quantity'] ?></td>
                                            <td>GHS <?= number_format($sale['total_amount'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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

        // Add loading animation to statistics
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(stat => {
                const finalValue = stat.textContent;
                stat.textContent = '0';

                setTimeout(() => {
                    let currentValue = 0;
                    const increment = parseFloat(finalValue.replace(/[^\d.]/g, '')) / 50;
                    const timer = setInterval(() => {
                        currentValue += increment;
                        if (currentValue >= parseFloat(finalValue.replace(/[^\d.]/g, ''))) {
                            stat.textContent = finalValue;
                            clearInterval(timer);
                        } else {
                            if (finalValue.includes('GHS')) {
                                stat.textContent = 'GHS ' + Math.floor(currentValue).toLocaleString();
                            } else {
                                stat.textContent = Math.floor(currentValue).toLocaleString();
                            }
                        }
                    }, 30);
                }, 500);
            });
        });
    </script>
</body>

</html>