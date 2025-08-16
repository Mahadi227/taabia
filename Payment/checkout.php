<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/payment_config.php';
require_once '../includes/payment_processor.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    header('Location: ../student/orders.php');
    exit;
}

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, p.name as product_name, p.description as product_description, p.price as product_price
        FROM orders o
        LEFT JOIN products p ON o.product_id = p.id
        WHERE o.id = ? AND o.buyer_id = ?
    ");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header('Location: ../student/orders.php');
        exit;
    }

    // Get user details
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

} catch (PDOException $e) {
    error_log("Database error in checkout: " . $e->getMessage());
    header('Location: ../student/orders.php');
    exit;
}

// Handle payment initialization
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? 'auto';
    $currency = $_POST['currency'] ?? 'GHS';
    
    $processor = new PaymentProcessor($pdo);
    $result = $processor->initializePayment($order_id, $order['total_amount'], $currency, $payment_method);
    
    if ($result['success']) {
        // Redirect to payment gateway or show payment form
        $gateway_data = $result['gateway_data'];
        
        switch ($result['payment_method']) {
            case 'paystack':
                header('Location: ' . $gateway_data['authorization_url']);
                exit;
            case 'flutterwave':
                header('Location: ' . $gateway_data['payment_url']);
                exit;
            case 'stripe':
                // Show Stripe payment form
                $stripe_data = $gateway_data;
                break;
            case 'paypal':
                header('Location: ' . $gateway_data['approval_url']);
                exit;
            case 'mtn_momo':
                // Show MTN MoMo instructions
                $mtn_data = $gateway_data;
                break;
        }
    } else {
        $error = $result['error'];
    }
}

// Get supported payment methods for the currency
$supported_methods = getSupportedPaymentMethods($order['currency'] ?? 'GHS');
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://js.stripe.com/v3/"></script>
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
            line-height: 1.6;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        .checkout-main {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .checkout-sidebar {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            height: fit-content;
        }

        .checkout-header {
            padding: 2rem;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }

        .checkout-header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            color: #1976d2;
            margin-bottom: 0.5rem;
        }

        .checkout-header p {
            color: #666;
        }

        .payment-methods {
            padding: 2rem;
        }

        .payment-method {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: #1976d2;
            background: #f8f9fa;
        }

        .payment-method.selected {
            border-color: #1976d2;
            background: #e3f2fd;
        }

        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }

        .payment-method-icon {
            width: 40px;
            height: 40px;
            background: #1976d2;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }

        .payment-method-title {
            font-weight: 600;
            color: #333;
        }

        .payment-method-description {
            color: #666;
            font-size: 0.9rem;
        }

        .stripe-payment-form {
            padding: 2rem;
            display: none;
        }

        .stripe-payment-form.active {
            display: block;
        }

        .stripe-form-group {
            margin-bottom: 1.5rem;
        }

        .stripe-form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        .stripe-form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .stripe-form-group input:focus {
            outline: none;
            border-color: #1976d2;
        }

        .stripe-card-element {
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            background: white;
        }

        .stripe-card-element.focused {
            border-color: #1976d2;
        }

        .stripe-card-element.invalid {
            border-color: #d32f2f;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .order-summary {
            margin-bottom: 2rem;
        }

        .order-summary h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 1rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-label {
            color: #666;
        }

        .order-item-value {
            font-weight: 600;
            color: #333;
        }

        .order-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1976d2;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #ffcdd2;
        }

        .success-message {
            background: #e8f5e8;
            color: #2e7d32;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #c8e6c9;
        }

        .mtn-instructions {
            padding: 2rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .mtn-instructions h4 {
            color: #1976d2;
            margin-bottom: 1rem;
        }

        .mtn-instructions ol {
            padding-left: 1.5rem;
        }

        .mtn-instructions li {
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .checkout-grid {
                grid-template-columns: 1fr;
            }
            
            .checkout-sidebar {
                order: -1;
            }
        }
    </style>
</head>

<body>
    <div class="checkout-container">
        <div class="checkout-grid">
            <!-- Main Payment Section -->
            <div class="checkout-main">
                <div class="checkout-header">
                    <h1>Finaliser votre paiement</h1>
                    <p>Sélectionnez votre méthode de paiement préférée</p>
                </div>

                <?php if (isset($error)): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Payment Methods -->
                <div class="payment-methods">
                    <form method="POST" id="payment-form">
                        <input type="hidden" name="currency" value="<?= $order['currency'] ?? 'GHS' ?>">
                        
                        <?php foreach ($supported_methods as $method): ?>
                            <div class="payment-method" data-method="<?= $method ?>">
                                <div class="payment-method-header">
                                    <div class="payment-method-icon">
                                        <i class="<?= getPaymentMethodIcon($method) ?>"></i>
                                    </div>
                                    <div>
                                        <div class="payment-method-title">
                                            <?= getPaymentMethodName($method) ?>
                                        </div>
                                        <div class="payment-method-description">
                                            <?php
                                            switch ($method) {
                                                case 'paystack':
                                                    echo 'Carte bancaire, Mobile Money, et plus';
                                                    break;
                                                case 'flutterwave':
                                                    echo 'Carte bancaire, Mobile Money, et plus';
                                                    break;
                                                case 'mtn_momo':
                                                    echo 'Paiement via MTN Mobile Money';
                                                    break;
                                                case 'stripe':
                                                    echo 'Carte bancaire sécurisée';
                                                    break;
                                                case 'paypal':
                                                    echo 'Paiement via PayPal';
                                                    break;
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <input type="radio" name="payment_method" value="<?= $method ?>" style="display: none;">
                            </div>
                        <?php endforeach; ?>

                        <!-- Stripe Payment Form -->
                        <div class="stripe-payment-form" id="stripe-form">
                            <h3>Informations de paiement</h3>
                            <div class="stripe-form-group">
                                <label for="card-holder-name">Nom sur la carte</label>
                                <input type="text" id="card-holder-name" placeholder="Nom complet">
                            </div>
                            <div class="stripe-form-group">
                                <label for="card-element">Numéro de carte</label>
                                <div id="card-element" class="stripe-card-element"></div>
                            </div>
                            <div id="card-errors" class="error-message" style="display: none;"></div>
                        </div>

                        <!-- MTN MoMo Instructions -->
                        <div class="mtn-instructions" id="mtn-instructions" style="display: none;">
                            <h4><i class="fas fa-mobile-alt"></i> Instructions MTN Mobile Money</h4>
                            <ol>
                                <li>Assurez-vous d'avoir suffisamment de crédit sur votre compte MTN MoMo</li>
                                <li>Vous recevrez une notification de paiement sur votre téléphone</li>
                                <li>Entrez votre code PIN MTN MoMo pour confirmer le paiement</li>
                                <li>Attendez la confirmation de paiement</li>
                            </ol>
                        </div>

                        <div style="padding: 2rem; border-top: 1px solid #e0e0e0;">
                            <button type="submit" class="btn btn-primary" id="pay-button">
                                <i class="fas fa-lock"></i>
                                Payer <?= formatCurrency($order['total_amount'], $order['currency'] ?? 'GHS') ?>
                            </button>
                            <a href="../student/orders.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Retour aux commandes
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="checkout-sidebar">
                <div class="order-summary">
                    <h3>Résumé de la commande</h3>
                    
                    <div class="order-item">
                        <span class="order-item-label">Produit</span>
                        <span class="order-item-value"><?= htmlspecialchars($order['product_name'] ?? 'Cours') ?></span>
                    </div>
                    
                    <div class="order-item">
                        <span class="order-item-label">Prix</span>
                        <span class="order-item-value"><?= formatCurrency($order['product_price'], $order['currency'] ?? 'GHS') ?></span>
                    </div>
                    
                    <div class="order-item">
                        <span class="order-item-label">Frais</span>
                        <span class="order-item-value"><?= formatCurrency($order['total_amount'] - $order['product_price'], $order['currency'] ?? 'GHS') ?></span>
                    </div>
                    
                    <div class="order-item order-total">
                        <span class="order-item-label">Total</span>
                        <span class="order-item-value"><?= formatCurrency($order['total_amount'], $order['currency'] ?? 'GHS') ?></span>
                    </div>
                </div>

                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px;">
                    <h4 style="margin-bottom: 0.5rem; color: #1976d2;">
                        <i class="fas fa-shield-alt"></i> Paiement sécurisé
                    </h4>
                    <p style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                        Vos informations de paiement sont protégées par un chiffrement SSL de niveau bancaire.
                    </p>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <i class="fab fa-cc-visa" style="color: #1976d2; font-size: 1.5rem;"></i>
                        <i class="fab fa-cc-mastercard" style="color: #f39c12; font-size: 1.5rem;"></i>
                        <i class="fab fa-cc-amex" style="color: #3498db; font-size: 1.5rem;"></i>
                        <i class="fas fa-mobile-alt" style="color: #27ae60; font-size: 1.5rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                // Remove selected class from all methods
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('selected'));
                
                // Add selected class to clicked method
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Show/hide specific forms
                const methodType = this.dataset.method;
                
                // Hide all specific forms
                document.getElementById('stripe-form').classList.remove('active');
                document.getElementById('mtn-instructions').style.display = 'none';
                
                // Show relevant form
                if (methodType === 'stripe') {
                    document.getElementById('stripe-form').classList.add('active');
                } else if (methodType === 'mtn_momo') {
                    document.getElementById('mtn-instructions').style.display = 'block';
                }
            });
        });

        // Stripe integration
        <?php if (isset($stripe_data)): ?>
        const stripe = Stripe('<?= $stripe_data['publishable_key'] ?>');
        const elements = stripe.elements();
        
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
                invalid: {
                    color: '#9e2146',
                },
            },
        });
        
        cardElement.mount('#card-element');
        
        const form = document.getElementById('payment-form');
        const payButton = document.getElementById('pay-button');
        
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            
            payButton.disabled = true;
            payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement...';
            
            const {error, paymentMethod} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: document.getElementById('card-holder-name').value,
                },
            });
            
            if (error) {
                const errorElement = document.getElementById('card-errors');
                errorElement.textContent = error.message;
                errorElement.style.display = 'block';
                payButton.disabled = false;
                payButton.innerHTML = '<i class="fas fa-lock"></i> Payer <?= formatCurrency($order['total_amount'], $order['currency'] ?? 'GHS') ?>';
            } else {
                // Send payment method to server
                const formData = new FormData(form);
                formData.append('payment_method_id', paymentMethod.id);
                
                fetch('process_stripe_payment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        window.location.href = result.redirect_url;
                    } else {
                        const errorElement = document.getElementById('card-errors');
                        errorElement.textContent = result.error;
                        errorElement.style.display = 'block';
                        payButton.disabled = false;
                        payButton.innerHTML = '<i class="fas fa-lock"></i> Payer <?= formatCurrency($order['total_amount'], $order['currency'] ?? 'GHS') ?>';
                    }
                });
            }
        });
        <?php endif; ?>

        // Form validation
        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!selectedMethod) {
                e.preventDefault();
                alert('Veuillez sélectionner une méthode de paiement.');
                return;
            }
            
            // For Stripe, additional validation is handled by Stripe
            if (selectedMethod.value === 'stripe') {
                // Let Stripe handle the submission
                return;
            }
        });
    </script>
</body>
</html>
