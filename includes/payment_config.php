<?php
/**
 * Payment System Configuration
 * Supports local (Ghana) and international payment methods
 */

// Payment Configuration
class PaymentConfig {
    // Paystack Configuration (Ghana - Local)
    public static $PAYSTACK = [
        'enabled' => true,
        'public_key' => 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'secret_key' => 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'webhook_secret' => 'whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'currency' => 'GHS',
        'country' => 'GH'
    ];

    // Flutterwave Configuration (Ghana - Local)
    public static $FLUTTERWAVE = [
        'enabled' => true,
        'public_key' => 'FLWPUBK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'secret_key' => 'FLWSECK-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'webhook_secret' => 'whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'currency' => 'GHS',
        'country' => 'GH'
    ];

    // MTN Mobile Money Configuration (Ghana - Local)
    public static $MTN_MOMO = [
        'enabled' => true,
        'api_key' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'merchant_id' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'environment' => 'sandbox',
        'currency' => 'GHS',
        'country' => 'GH'
    ];

    // Stripe Configuration (International)
    public static $STRIPE = [
        'enabled' => true,
        'publishable_key' => 'pk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'secret_key' => 'sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'webhook_secret' => 'whsec_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'currency' => 'USD',
        'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD']
    ];

    // PayPal Configuration (International)
    public static $PAYPAL = [
        'enabled' => true,
        'client_id' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'client_secret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'environment' => 'sandbox',
        'currency' => 'USD',
        'supported_currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD']
    ];

    // General Payment Settings
    public static $GENERAL = [
        'default_currency' => 'GHS',
        'supported_currencies' => ['GHS', 'USD', 'EUR', 'GBP'],
        'exchange_rate_api' => 'https://api.exchangerate-api.com/v4/latest/GHS',
        'payment_timeout' => 300,
        'max_retry_attempts' => 3,
        'webhook_url' => 'https://yourdomain.com/payment/webhook.php'
    ];
}

/**
 * Payment Helper Functions
 */
function formatCurrency($amount, $currency = 'GHS') {
    $symbols = [
        'GHS' => '₵',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£'
    ];

    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . number_format($amount, 2);
}

function getSupportedPaymentMethods($currency = 'GHS') {
    $methods = [];
    
    if ($currency === 'GHS') {
        if (PaymentConfig::$PAYSTACK['enabled']) $methods[] = 'paystack';
        if (PaymentConfig::$FLUTTERWAVE['enabled']) $methods[] = 'flutterwave';
        if (PaymentConfig::$MTN_MOMO['enabled']) $methods[] = 'mtn_momo';
    }
    
    if (in_array($currency, ['USD', 'EUR', 'GBP'])) {
        if (PaymentConfig::$STRIPE['enabled']) $methods[] = 'stripe';
        if (PaymentConfig::$PAYPAL['enabled']) $methods[] = 'paypal';
    }
    
    return $methods;
}

function getPaymentMethodName($method) {
    $names = [
        'paystack' => 'Paystack (Card/Mobile Money)',
        'flutterwave' => 'Flutterwave (Card/Mobile Money)',
        'mtn_momo' => 'MTN Mobile Money',
        'stripe' => 'Stripe (Card)',
        'paypal' => 'PayPal'
    ];
    
    return $names[$method] ?? ucfirst($method);
}

function getPaymentMethodIcon($method) {
    $icons = [
        'paystack' => 'fas fa-credit-card',
        'flutterwave' => 'fas fa-credit-card',
        'mtn_momo' => 'fas fa-mobile-alt',
        'stripe' => 'fab fa-stripe',
        'paypal' => 'fab fa-paypal'
    ];
    
    return $icons[$method] ?? 'fas fa-money-bill';
}
?>
