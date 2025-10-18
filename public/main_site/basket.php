<?php
// Start output buffering to prevent any accidental output
ob_start();

// Start session first
session_start();

require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
}

// Handle cart actions with improved logic
if (isset($_GET['add'])) {
    $item_id = (int)$_GET['add'];
    $item_type = $_GET['type'] ?? 'product';
    $quantity = (int)($_GET['quantity'] ?? 1);

    try {
        if ($item_type === 'course') {
            $stmt = $pdo->prepare("SELECT c.*, u.fullname AS instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.id = ? AND c.status = 'published'");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if ($item) {
                if (isset($_SESSION['cart']['courses'][$item_id])) {
                    $message = __("course_already_in_cart");
                } else {
                    $_SESSION['cart']['courses'][$item_id] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'price' => $item['price'],
                        'image_url' => $item['image_url'],
                        'instructor_name' => $item['instructor_name'],
                        'type' => 'course'
                    ];
                    $message = __("course_added_to_cart");
                }
            } else {
                $error = __("course_not_found");
            }
        } else {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if ($item) {
                // Check stock availability
                if ($item['stock_quantity'] <= 0) {
                    $error = __("product_out_of_stock");
                } elseif ($item['stock_quantity'] < $quantity) {
                    $error = __("insufficient_stock") . " {$item['stock_quantity']}";
                    $quantity = $item['stock_quantity']; // Set to available stock
                } else {
                    if (isset($_SESSION['cart']['products'][$item_id])) {
                        $new_quantity = $_SESSION['cart']['products'][$item_id]['quantity'] + $quantity;
                        if ($new_quantity <= $item['stock_quantity']) {
                            $_SESSION['cart']['products'][$item_id]['quantity'] = $new_quantity;
                            $message = __("product_added_to_cart_success");
                        } else {
                            $_SESSION['cart']['products'][$item_id]['quantity'] = $item['stock_quantity'];
                            $message = __("quantity_adjusted_to_stock");
                        }
                    } else {
                        $_SESSION['cart']['products'][$item_id] = [
                            'id' => $item['id'],
                            'name' => $item['name'],
                            'price' => $item['price'],
                            'image_url' => $item['image_url'],
                            'quantity' => $quantity,
                            'type' => 'product'
                        ];
                        $message = __("new_product_added_to_cart");
                    }
                }
            } else {
                $error = __("product_not_found");
            }
        }
    } catch (PDOException $e) {
        $error = __("error_adding_item");
    }
}

if (isset($_GET['remove'])) {
    $item_id = (int)$_GET['remove'];
    $item_type = $_GET['type'] ?? 'product';

    if ($item_type === 'course' && isset($_SESSION['cart']['courses'][$item_id])) {
        unset($_SESSION['cart']['courses'][$item_id]);
        $message = __("course_removed_from_cart");
    } elseif (isset($_SESSION['cart']['products'][$item_id])) {
        unset($_SESSION['cart']['products'][$item_id]);
        $message = __("product_removed_from_cart");
    }
}

if (isset($_GET['update'])) {
    $item_id = (int)$_GET['update'];
    $quantity = (int)$_GET['quantity'];
    $item_type = $_GET['type'] ?? 'product';

    if ($item_type === 'course') {
        // Courses are always quantity 1
        if (isset($_SESSION['cart']['courses'][$item_id])) {
            $message = __("quantity_updated");
        }
    } else {
        if ($quantity > 0 && isset($_SESSION['cart']['products'][$item_id])) {
            // Check stock availability before updating quantity
            try {
                $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? AND status = 'active'");
                $stmt->execute([$item_id]);
                $available_stock = $stmt->fetchColumn();

                if ($available_stock >= $quantity) {
                    $_SESSION['cart']['products'][$item_id]['quantity'] = $quantity;
                    $message = __("quantity_updated");
                } else {
                    $error = __("insufficient_stock") . " $available_stock";
                    // Set quantity to available stock
                    $_SESSION['cart']['products'][$item_id]['quantity'] = $available_stock;
                }
            } catch (PDOException $e) {
                $error = __("error_checking_stock");
            }
        } elseif (isset($_SESSION['cart']['products'][$item_id])) {
            unset($_SESSION['cart']['products'][$item_id]);
            $message = __("product_removed_from_cart");
        }
    }
}

if (isset($_GET['clear'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
    $message = __("cart_cleared");
}

// Calculate totals
$total_items = 0;
$total_price = 0;
$courses_total = 0;
$products_total = 0;

foreach ($_SESSION['cart']['courses'] as $item) {
    $total_items++;
    $courses_total += $item['price'];
}

foreach ($_SESSION['cart']['products'] as $item) {
    $total_items += $item['quantity'];
    $products_total += $item['price'] * $item['quantity'];
}

$total_price = $courses_total + $products_total;
$tax = $products_total * 0.15; // Tax only on products
$shipping = $products_total > 0 ? 0 : 0; // Free shipping
$total_with_tax = $total_price + $tax + $shipping;
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('cart') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .cart-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .cart-header h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .cart-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .cart-stats {
            display: flex;
            justify-content: center;
            gap: var(--spacing-xl);
            margin-bottom: var(--spacing-xl);
        }

        .stat-item {
            text-align: center;
            padding: var(--spacing-md);
            background: var(--bg-primary);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-light);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .message {
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-lg);
            text-align: center;
        }

        .message.success {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .message.error {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .cart-empty {
            text-align: center;
            padding: var(--spacing-3xl);
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .cart-empty i {
            font-size: 4rem;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-lg);
        }

        .cart-empty h2 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .cart-empty p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xl);
        }

        .cart-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }

        .cart-items {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            overflow: hidden;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 120px 1fr auto auto;
            gap: var(--spacing-lg);
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            align-items: center;
            transition: var(--transition);
        }

        .cart-item:hover {
            background: var(--bg-secondary);
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-light);
        }

        .cart-item-details h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
            font-size: 1.1rem;
        }

        .cart-item-price {
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.2rem;
        }

        .cart-item-instructor {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: var(--spacing-sm);
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .quantity-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            width: 36px;
            height: 36px;
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .quantity-input {
            width: 70px;
            text-align: center;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-sm);
            font-weight: 600;
        }

        .cart-item-actions {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }

        .remove-btn {
            background: var(--danger-color);
            color: var(--text-white);
            border: none;
            padding: var(--spacing-sm) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .remove-btn:hover {
            background: #d32f2f;
        }

        .cart-summary {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .summary-title {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: 1.3rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
            padding: var(--spacing-sm) 0;
        }

        .summary-row.total {
            border-top: 2px solid var(--border-color);
            padding-top: var(--spacing-md);
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .cart-actions {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            margin-top: var(--spacing-xl);
        }

        .btn-continue {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-checkout {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-clear {
            background: var(--danger-color);
            color: var(--text-white);
        }

        .cart-features {
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .feature-item i {
            color: var(--success-color);
        }

        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
            }

            .cart-item {
                grid-template-columns: 1fr;
                gap: var(--spacing-md);
                text-align: center;
            }

            .cart-item-image {
                width: 100px;
                height: 100px;
                margin: 0 auto;
            }

            .cart-stats {
                flex-direction: column;
                gap: var(--spacing-md);
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-graduation-cap"></i> TaaBia
            </a>

            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="index.php" class="nav-link"><?= __('welcome') ?></a></li>
                <li><a href="courses.php" class="nav-link"><?= __('courses') ?></a></li>
                <li><a href="shop.php" class="nav-link"><?= __('shop') ?></a></li>
                <li><a href="upcoming_events.php" class="nav-link"><?= __('events') ?></a></li>
                <li><a href="blog.php" class="nav-link"><?= __('blog') ?></a></li>
                <li><a href="about.php" class="nav-link"><?= __('about') ?></a></li>
                <li><a href="contact.php" class="nav-link"><?= __('contact') ?></a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>
                <li style="margin-left: auto;">
                    <?php include '../../includes/public_language_switcher.php'; ?>
                </li>
            </ul>

            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> <?= __('my_profile') ?>
                    </a>
                    <a href="../../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('login') ?>
                    </a>
                    <a href="../../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?= __('register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="cart-container">
            <div class="cart-header">
                <h1><i class="fas fa-shopping-cart"></i> <?= __('cart') ?></h1>
                <p><?= __('cart_description') ?></p>
            </div>

            <?php if (isset($message)): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if (empty($_SESSION['cart']['products']) && empty($_SESSION['cart']['courses'])): ?>
                <div class="cart-empty">
                    <i class="fas fa-shopping-cart"></i>
                    <h2><?= __('cart_empty') ?></h2>
                    <p><?= __('cart_empty_description') ?></p>
                    <a href="shop.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> <?= __('continue_shopping') ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="cart-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?= $total_items ?></div>
                        <div class="stat-label"><?= __('items') ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= count($_SESSION['cart']['products']) + count($_SESSION['cart']['courses']) ?></div>
                        <div class="stat-label"><?= __('products') ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?= number_format($total_price, 0, ',', ' ') ?></div>
                        <div class="stat-label">GHS</div>
                    </div>
                </div>

                <div class="cart-content">
                    <div class="cart-items">
                        <?php foreach ($_SESSION['cart']['products'] as $product_id => $item): ?>
                            <div class="cart-item">
                                <img src="<?= !empty($item['image_url']) ? '../../uploads/' . htmlspecialchars($item['image_url']) : '../../assets/img/default-product.jpg' ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="cart-item-image">
                                <div class="cart-item-details">
                                    <h3><?= htmlspecialchars($item['name']) ?></h3>
                                    <p class="cart-item-price"><?= number_format($item['price'], 0, ',', ' ') ?> GHS</p>
                                </div>
                                <div class="cart-item-quantity">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $product_id ?>, -1)" title="<?= __('decrease') ?>">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <input type="number" value="<?= $item['quantity'] ?>" min="1" class="quantity-input" onchange="updateQuantity(<?= $product_id ?>, this.value, true, 'product')" title="<?= __('quantity') ?>">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $product_id ?>, 1)" title="<?= __('increase') ?>">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="cart-item-actions">
                                    <button class="remove-btn" onclick="removeItem(<?= $product_id ?>)" title="<?= __('remove') ?>">
                                        <i class="fas fa-trash"></i> <?= __('remove') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ($_SESSION['cart']['courses'] as $course_id => $item): ?>
                            <div class="cart-item">
                                <img src="<?= !empty($item['image_url']) ? '../../uploads/' . htmlspecialchars($item['image_url']) : '../../assets/img/default-course.jpg' ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="cart-item-image">
                                <div class="cart-item-details">
                                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                                    <p class="cart-item-instructor"><?= __('instructor') ?>: <?= htmlspecialchars($item['instructor_name'] ?? 'Unknown') ?></p>
                                    <p class="cart-item-price"><?= number_format($item['price'], 0, ',', ' ') ?> GHS</p>
                                </div>
                                <div class="cart-item-quantity">
                                    <span class="quantity-display">1</span>
                                </div>
                                <div class="cart-item-actions">
                                    <button class="remove-btn" onclick="removeItem(<?= $course_id ?>, 'course')" title="<?= __('remove') ?>">
                                        <i class="fas fa-trash"></i> <?= __('remove') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="cart-summary">
                        <h3 class="summary-title"><?= __('order_summary') ?></h3>
                        <div class="summary-row">
                            <span><?= __('subtotal') ?> (<?= $total_items ?> <?= __('items') ?>)</span>
                            <span><?= number_format($total_price, 0, ',', ' ') ?> GHS</span>
                        </div>
                        <div class="summary-row">
                            <span><?= __('shipping') ?></span>
                            <span><?= __('free') ?></span>
                        </div>
                        <div class="summary-row">
                            <span><?= __('tax') ?> (15%)</span>
                            <span><?= number_format($tax, 0, ',', ' ') ?> GHS</span>
                        </div>
                        <div class="summary-row total">
                            <span><?= __('total') ?></span>
                            <span><?= number_format($total_with_tax, 0, ',', ' ') ?> GHS</span>
                        </div>

                        <div class="cart-actions">
                            <a href="shop.php" class="btn btn-continue">
                                <i class="fas fa-arrow-left"></i> <?= __('continue_shopping') ?>
                            </a>
                            <a href="checkout.php" class="btn btn-checkout">
                                <i class="fas fa-credit-card"></i> <?= __('checkout') ?>
                            </a>
                            <button onclick="clearCart()" class="btn btn-clear">
                                <i class="fas fa-trash"></i> <?= __('clear_cart') ?>
                            </button>
                        </div>

                        <div class="cart-features">
                            <div class="feature-item">
                                <i class="fas fa-shield-alt"></i>
                                <span><?= __('secure_payment') ?></span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-truck"></i>
                                <span><?= __('free_shipping') ?></span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-undo"></i>
                                <span><?= __('returns_accepted') ?></span>
                            </div>
                            <div class="feature-item">
                                <i class="fas fa-headset"></i>
                                <span><?= __('support_24_7') ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                    <p><?= __('footer_description') ?></p>
                    <p><?= __('footer_mission') ?></p>
                </div>

                <div class="footer-section">
                    <h3><?= __('footer_services') ?></h3>
                    <a href="courses.php"><?= __('courses') ?></a>
                    <a href="shop.php"><?= __('shop') ?></a>
                    <a href="upcoming_events.php"><?= __('events') ?></a>
                    <a href="contact.php"><?= __('contact') ?></a>
                </div>

                <div class="footer-section">
                    <h3><?= __('footer_contact') ?></h3>
                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                    <p><i class="fas fa-phone"></i> +233 XX XXX XXXX</p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
                </div>

                <div class="footer-section">
                    <h3><?= __('footer_follow_us') ?></h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. <?= __('footer_rights_reserved') ?>.</p>
            </div>
        </div>
    </footer>

    <script>
        // Create translations object for JavaScript
        const translations = {
            confirmRemove: '<?= __("confirm_remove_item") ?>',
            confirmClearCart: '<?= __("confirm_clear_cart") ?>'
        };

        function updateQuantity(itemId, change, isDirectInput = false, itemType = 'product') {
            let quantity;
            if (itemType === 'course') {
                quantity = 1; // Courses are always 1 quantity
            } else {
                if (isDirectInput) {
                    quantity = parseInt(change);
                } else {
                    const input = document.querySelector(`input[onchange*="${itemId}"]`);
                    if (input) {
                        const currentQuantity = parseInt(input.value);
                        quantity = currentQuantity + parseInt(change);
                    } else {
                        quantity = 1;
                    }
                }
            }

            if (quantity > 0) {
                window.location.href = `basket.php?update=${itemId}&quantity=${quantity}&type=${itemType}`;
            } else if (quantity === 0) {
                removeItem(itemId, itemType);
            }
        }

        function removeItem(itemId, itemType = 'product') {
            if (confirm(translations.confirmRemove)) {
                window.location.href = `basket.php?remove=${itemId}&type=${itemType}`;
            }
        }

        function clearCart() {
            if (confirm(translations.confirmClearCart)) {
                window.location.href = `basket.php?clear=1`;
            }
        }

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>

</html>