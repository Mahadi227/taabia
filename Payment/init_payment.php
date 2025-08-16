<?php
require_once 'payment/FlutterwaveGateway.php';
require_once 'payment/PaystackGateway.php';
// etc.

$type = $_POST['payment_type']; // 'flutterwave', 'stripe', etc.
$data = $_POST; // infos: user_id, amount, etc.

switch ($type) {
    case 'flutterwave':
        $gateway = new FlutterwaveGateway();
        break;
    case 'paystack':
        $gateway = new PaystackGateway();
        break;
    case 'stripe':
        $gateway = new StripeGateway();
        break;
    // etc.
    default:
        die('Méthode de paiement non prise en charge');
}

$gateway->initialize($data);
$paymentUrl = $gateway->getPaymentUrl();

// Redirection vers l'URL de paiement
header("Location: $paymentUrl");
exit;
