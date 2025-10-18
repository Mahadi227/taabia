<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Start session
session_start();

// Check if cart is empty
if (empty($_SESSION['cart']['products']) && empty($_SESSION['cart']['courses'])) {
    header('Location: basket.php');
    exit;
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $city = $_POST['city'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $payment_method = $_POST['payment_method'] ?? '';
    
    if ($name && $email && $phone && $address && $city && $postal_code && $payment_method) {
        // Validate stock availability before creating order
        $stock_errors = [];
        foreach ($_SESSION['cart']['products'] as $item) {
            $stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ? AND status = 'active'");
            $stmt->execute([$item['id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $stock_errors[] = "Produit non trouvé ou indisponible";
                break;
            }
            
            if ($product['stock_quantity'] < $item['quantity']) {
                $stock_errors[] = "Stock insuffisant pour {$product['name']}. Disponible: {$product['stock_quantity']}, Demandé: {$item['quantity']}";
            }
        }
        
        if (!empty($stock_errors)) {
            $error = implode("<br>", $stock_errors);
        } else {
            // Create order
            try {
                $pdo->beginTransaction();
            
                // Generate order number
                $order_number = 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("
                    INSERT INTO orders (buyer_id, total_amount, status, payment_method, ordered_at, created_at, updated_at)
                    VALUES (?, ?, 'pending', ?, NOW(), NOW(), NOW())
                ");
                $stmt->execute([$_SESSION['user_id'] ?? null, $total_with_tax, $payment_method]);
                $order_id = $pdo->lastInsertId();
                
                // Add order items for products and reduce stock
                foreach ($_SESSION['cart']['products'] as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, unit_price, price, created_at)
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");
                    $total_item_price = $item['price'] * $item['quantity'];
                    $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price'], $total_item_price]);
                    
                    // Reduce stock
                    $stmt = $pdo->prepare("
                        UPDATE products 
                        SET stock_quantity = stock_quantity - ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['id']]);
                }
                
                // Add order items for courses
                foreach ($_SESSION['cart']['courses'] as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, unit_price, price, created_at)
                        VALUES (?, ?, 1, ?, ?, NOW())
                    ");
                    $stmt->execute([$order_id, null, 1, $item['price'], $item['price']]);
                }
                
                // Create transaction record instead of payment
                // Decide payment flow
                if ($payment_method === 'paystack') {
                    // Prepare pending transaction
                    $reference = 'PSK-' . date('YmdHis') . '-' . mt_rand(1000, 9999);
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, order_id, amount, status, payment_method, payment_status, type, created_at, reference, currency)
                        VALUES (?, ?, ?, 'pending', 'paystack', 'pending', 'product', NOW(), ?, 'GHS')
                    ");
                    $stmt->execute([$_SESSION['user_id'] ?? null, $order_id, $total_with_tax, $reference]);
                    $pdo->commit();

                    // Initialize Paystack
                    require_once '../../includes/paystack.php';
                    $customerEmail = $email;
                    $amountMinor = (int) round($total_with_tax * 100); // pesewas
                    $callbackUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/paystack_callback.php?order_id=' . $order_id;
                    list($ok, $err, $psData) = paystackInitialize($customerEmail, $amountMinor, $reference, 'GHS', $callbackUrl);
                    if (!$ok) {
                        $error = 'Erreur Paystack: ' . htmlspecialchars($err);
                    } else {
                        // Redirect to Paystack authorization_url
                        header('Location: ' . $psData['authorization_url']);
                        exit;
                    }
                } else {
                    // Offline methods: mark as completed
                    $stmt = $pdo->prepare("
                        INSERT INTO transactions (user_id, order_id, amount, status, payment_method, payment_status, type, created_at)
                        VALUES (?, ?, ?, 'completed', ?, 'success', 'product', NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'] ?? null, $order_id, $total_with_tax, $payment_method]);

                    $pdo->commit();

                    // Clear cart
                    $_SESSION['cart'] = ['products' => [], 'courses' => []];

                    // Redirect to success page
                    header("Location: order_success.php?order_id=$order_id");
                    exit;
                }
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Erreur lors de la création de la commande: " . $e->getMessage();
            }
        } // Close the else block for stock validation
    } else {
        $error = "Veuillez remplir tous les champs obligatoires";
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('checkout_title') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .checkout-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .checkout-header h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .checkout-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .checkout-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }

        .checkout-form {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
        }

        .form-section {
            margin-bottom: var(--spacing-xl);
        }

        .form-section h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: 1.2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
            font-weight: 600;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }

        .payment-method {
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-md);
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
        }

        .payment-method input[type="radio"] {
            display: none;
        }

        .payment-method input[type="radio"]:checked + .payment-method-content {
            border-color: var(--primary-color);
            background: rgba(0, 150, 136, 0.05);
        }

        .payment-method-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .payment-method i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .order-summary {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .order-summary h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
        }

        .order-items {
            margin-bottom: var(--spacing-lg);
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md) 0;
            border-bottom: 1px solid var(--border-color);
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-details {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }

        .item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: var(--border-radius-sm);
        }

        .item-info h4 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
            font-size: 0.9rem;
        }

        .item-price {
            color: var(--primary-color);
            font-weight: 600;
        }

        .summary-totals {
            border-top: 2px solid var(--border-color);
            padding-top: var(--spacing-lg);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-md);
        }

        .summary-row.total {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            border-top: 1px solid var(--border-color);
            padding-top: var(--spacing-md);
        }

        .checkout-actions {
            margin-top: var(--spacing-xl);
        }

        .btn-back {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-place-order {
            background: var(--primary-color);
            color: var(--text-white);
            width: 100%;
        }

        .error-message {
            background: var(--danger-color);
            color: var(--text-white);
            padding: var(--spacing-md);
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-lg);
        }

        .security-badges {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
            justify-content: center;
        }

        .security-badge {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .security-badge i {
            color: var(--success-color);
        }

        @media (max-width: 768px) {
            .checkout-content {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .payment-methods {
                grid-template-columns: 1fr;
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
        <div class="checkout-container">
            <div class="checkout-header">
                <h1><i class="fas fa-credit-card"></i> <?= __('checkout_title') ?></h1>
                <p><?= __('checkout_description') ?></p>
            </div>

            <?php if (isset($error)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="checkout-content">
                <div class="checkout-form">
                    <form method="POST" action="">
                        <div class="form-section">
                            <h3><i class="fas fa-shipping-fast"></i> <?= __('shipping_information') ?></h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name"><?= __('full_name') ?> *</label>
                                    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="email"><?= __('email_address') ?> *</label>
                                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone"><?= __('phone_number') ?> *</label>
                                    <input type="tel" id="phone" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label for="city"><?= __('city') ?> *</label>
                                    <input type="text" id="city" name="city" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group full-width">
                                <label for="address"><?= __('shipping_address') ?> *</label>
                                <textarea id="address" name="address" rows="3" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="postal_code"><?= __('postal_code') ?> *</label>
                                <input type="text" id="postal_code" name="postal_code" required value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-section">
                            <h3><i class="fas fa-credit-card"></i> <?= __('payment_information') ?></h3>
                            <div class="form-group">
                                <label for="payment_method"><?= __('payment_method') ?> *</label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value=""><?= __('select_payment_method') ?></option>
                                    <option value="cash_on_delivery" <?= ($_POST['payment_method'] ?? '') === 'cash_on_delivery' ? 'selected' : '' ?>><?= __('cash_on_delivery') ?></option>
                                    <option value="bank_transfer" <?= ($_POST['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : '' ?>><?= __('bank_transfer') ?></option>
                                    <option value="mobile_money" <?= ($_POST['payment_method'] ?? '') === 'mobile_money' ? 'selected' : '' ?>><?= __('mobile_money') ?></option>
                                    <option value="paystack" <?= ($_POST['payment_method'] ?? '') === 'paystack' ? 'selected' : '' ?>><?= __('paystack') ?></option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn-place-order">
                            <i class="fas fa-lock"></i> <?= __('place_order') ?>
                        </button>
                    </form>
                </div>

                <div class="order-summary">
                    <h3 class="summary-title"><i class="fas fa-shopping-bag"></i> <?= __('order_review') ?></h3>
                    
                    <div class="order-items">
                        <?php foreach ($_SESSION['cart']['products'] as $item): ?>
                            <div class="order-item">
                                <div class="item-details">
                                    <h4><?= htmlspecialchars($item['name']) ?></h4>
                                    <small><?= __('quantity') ?>: <?= $item['quantity'] ?></small>
                                </div>
                                <div class="item-price">
                                    <?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> GHS
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php foreach ($_SESSION['cart']['courses'] as $item): ?>
                            <div class="order-item">
                                <div class="item-details">
                                    <h4><?= htmlspecialchars($item['title']) ?></h4>
                                    <small><?= __('course') ?></small>
                                </div>
                                <div class="item-price">
                                    <?= number_format($item['price'], 0, ',', ' ') ?> GHS
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-row">
                        <span><?= __('subtotal') ?></span>
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
                </div>
            </div>
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
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--danger-color)';
                } else {
                    field.style.borderColor = 'var(--border-color)';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires');
            }
        });
    </script>
</body>
</html>




        }



        .summary-totals {

            border-top: 2px solid var(--border-color);

            padding-top: var(--spacing-lg);

        }



        .summary-row {

            display: flex;

            justify-content: space-between;

            margin-bottom: var(--spacing-md);

        }



        .summary-row.total {

            font-size: 1.2rem;

            font-weight: 600;

            color: var(--primary-color);

            border-top: 1px solid var(--border-color);

            padding-top: var(--spacing-md);

        }



        .checkout-actions {

            margin-top: var(--spacing-xl);

        }



        .btn-back {

            background: var(--bg-secondary);

            color: var(--text-primary);

            border: 1px solid var(--border-color);

        }



        .btn-place-order {

            background: var(--primary-color);

            color: var(--text-white);

            width: 100%;

        }



        .error-message {

            background: var(--danger-color);

            color: var(--text-white);

            padding: var(--spacing-md);

            border-radius: var(--border-radius-sm);

            margin-bottom: var(--spacing-lg);

        }



        .security-badges {

            display: flex;

            gap: var(--spacing-md);

            margin-top: var(--spacing-lg);

            justify-content: center;

        }



        .security-badge {

            display: flex;

            align-items: center;

            gap: var(--spacing-xs);

            color: var(--text-secondary);

            font-size: 0.9rem;

        }



        .security-badge i {

            color: var(--success-color);

        }



        @media (max-width: 768px) {

            .checkout-content {

                grid-template-columns: 1fr;

            }



            .form-row {

                grid-template-columns: 1fr;

            }



            .payment-methods {

                grid-template-columns: 1fr;

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

            <ul class="nav-menu">

                <li><a href="index.php" class="nav-link">Accueil</a></li>

                <li><a href="courses.php" class="nav-link">Formations</a></li>

                <li><a href="shop.php" class="nav-link">Boutique</a></li>

                <li><a href="upcoming_events.php" class="nav-link">Événements</a></li>

                <li><a href="about.php" class="nav-link">À propos</a></li>

                <li><a href="contact.php" class="nav-link">Contact</a></li>

                <?php if (isset($_SESSION['user_id'])): ?>

                    <li><a href="../student/index.php" class="nav-link">Mon Compte</a></li>

                    <li><a href="../../auth/logout.php" class="nav-link">Déconnexion</a></li>

                <?php else: ?>

                    <li><a href="../../auth/login.php" class="btn btn-primary">Connexion</a></li>

                <?php endif; ?>

            </ul>

        </nav>

    </header>



    <!-- Main Content -->

    <main class="main">

        <div class="checkout-container">

            <div class="checkout-header">

                <h1><i class="fas fa-credit-card"></i> Finaliser la commande</h1>

                <p>Complétez vos informations pour finaliser votre commande</p>

            </div>



            <?php if (isset($error)): ?>

                <div class="error-message">

                    <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>

                </div>

            <?php endif; ?>



            <div class="checkout-content">

                <div class="checkout-form">

                    <form method="post" action="">

                        <div class="form-section">

                            <h3><i class="fas fa-user"></i> Informations personnelles</h3>

                            <div class="form-row">

                                <div class="form-group">

                                    <label for="name">Nom complet *</label>

                                    <input type="text" id="name" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">

                                </div>

                                <div class="form-group">

                                    <label for="email">Email *</label>

                                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

                                </div>

                            </div>

                            <div class="form-group">

                                <label for="phone">Téléphone *</label>

                                <input type="tel" id="phone" name="phone" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

                            </div>

                        </div>



                        <div class="form-section">

                            <h3><i class="fas fa-map-marker-alt"></i> Adresse de livraison</h3>

                            <div class="form-group full-width">

                                <label for="address">Adresse complète *</label>

                                <textarea id="address" name="address" rows="3" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>

                            </div>

                            <div class="form-row">

                                <div class="form-group">

                                    <label for="city">Ville *</label>

                                    <input type="text" id="city" name="city" required value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">

                                </div>

                                <div class="form-group">

                                    <label for="postal_code">Code postal *</label>

                                    <input type="text" id="postal_code" name="postal_code" required value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">

                                </div>

                            </div>

                        </div>



                        <div class="form-section">

                            <h3><i class="fas fa-credit-card"></i> Mode de paiement sécurisé</h3>

                            <div class="payment-methods">

                                <label class="payment-method">

                                    <input type="radio" name="payment_method" value="mobile_money" required>

                                    <div class="payment-method-content">

                                        <i class="fas fa-mobile-alt"></i>

                                        <span>Mobile Money</span>

                                        <small>MTN, Airtel, Vodafone</small>

                                    </div>

                                </label>

                                <label class="payment-method">

                                    <input type="radio" name="payment_method" value="bank_transfer" required>

                                    <div class="payment-method-content">

                                        <i class="fas fa-university"></i>

                                        <span>Virement bancaire</span>

                                        <small>Transfert direct</small>

                                    </div>

                                </label>

                                <label class="payment-method">

                                    <input type="radio" name="payment_method" value="cash_on_delivery" required>

                                    <div class="payment-method-content">

                                        <i class="fas fa-money-bill-wave"></i>

                                        <span>Paiement à la livraison</span>

                                        <small>Payez à la réception</small>

                                    </div>

                                </label>

                            </div>

                            

                            <div class="security-badges">

                                <div class="security-badge">

                                    <i class="fas fa-shield-alt"></i>

                                    <span>Paiement sécurisé</span>

                                </div>

                                <div class="security-badge">

                                    <i class="fas fa-lock"></i>

                                    <span>SSL Encrypté</span>

                                </div>

                                <div class="security-badge">

                                    <i class="fas fa-check-circle"></i>

                                    <span>Garantie 100%</span>

                                </div>

                            </div>

                        </div>



                        <div class="checkout-actions">

                            <a href="basket.php" class="btn btn-back">

                                <i class="fas fa-arrow-left"></i> Retour au panier

                            </a>

                            <button type="submit" class="btn btn-place-order">

                                <i class="fas fa-check"></i> Confirmer la commande

                            </button>

                        </div>

                    </form>

                </div>



                <div class="order-summary">

                    <h3>Résumé de la commande</h3>

                    <div class="order-items">

                        <?php foreach ($_SESSION['cart']['products'] as $item): ?>

                            <div class="order-item">

                                <div class="item-details">

                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-image">

                                    <div class="item-info">

                                        <h4><?= htmlspecialchars($item['name']) ?></h4>

                                        <span>Quantité: <?= $item['quantity'] ?></span>

                                    </div>

                                </div>

                                <div class="item-price">

                                    <?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> GHS

                                </div>

                            </div>

                        <?php endforeach; ?>

                        <?php foreach ($_SESSION['cart']['courses'] as $item): ?>

                            <div class="order-item">

                                <div class="item-details">

                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="item-image">

                                    <div class="item-info">

                                        <h4><?= htmlspecialchars($item['name']) ?></h4>

                                        <span>Quantité: 1</span>

                                    </div>

                                </div>

                                <div class="item-price">

                                    <?= number_format($item['price'], 0, ',', ' ') ?> GHS

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>



                    <div class="summary-totals">

                        <div class="summary-row">

                            <span>Sous-total</span>

                            <span><?= number_format($total_price, 0, ',', ' ') ?> GHS</span>

                        </div>

                        <div class="summary-row">

                            <span>Livraison</span>

                            <span>Gratuit</span>

                        </div>

                        <div class="summary-row">

                            <span>Taxes (15%)</span>

                            <span><?= number_format($tax, 0, ',', ' ') ?> GHS</span>

                        </div>

                        <div class="summary-row total">

                            <span>Total</span>

                            <span><?= number_format($total_with_tax, 0, ',', ' ') ?> GHS</span>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </main>



    <!-- Footer -->

    <footer class="footer">

        <div class="container">

            <div class="footer-content">

                <div class="footer-section">

                    <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>

                    <p>Votre plateforme d'apprentissage et d'innovation en Afrique</p>

                    <p>Démocratiser l'accès à l'éducation et aux produits innovants</p>

                </div>

                

                <div class="footer-section">

                    <h3>Services</h3>

                    <a href="courses.php">Formations</a>

                    <a href="shop.php">Boutique</a>

                    <a href="upcoming_events.php">Événements</a>

                    <a href="contact.php">Support</a>

                </div>

                

                <div class="footer-section">

                    <h3>Contact</h3>

                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>

                    <p><i class="fas fa-phone"></i> +233 XX XXX XXXX</p>

                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>

                </div>

                

                <div class="footer-section">

                    <h3>Suivez-nous</h3>

                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>

                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>

                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>

                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>

                </div>

            </div>

            

            <div class="footer-bottom">

                <p>&copy; <?= date('Y') ?> TaaBia. Tous droits réservés.</p>

            </div>

        </div>

    </footer>

</body>

</html>






