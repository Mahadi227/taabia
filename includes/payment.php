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
 * Payment Processor Class
 */
class PaymentProcessor {
    private $pdo;
    private $config;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->config = PaymentConfig::class;
    }

    /**
     * Initialize payment based on user location and preferences
     */
    public function initializePayment($order_id, $amount, $currency = 'GHS', $payment_method = 'auto') {
        try {
            // Get order details
            $stmt = $this->pdo->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception("Order not found");
            }

            // Determine payment method based on location and preferences
            $payment_method = $this->determinePaymentMethod($currency, $payment_method);

            // Create payment record
            $payment_id = $this->createPaymentRecord($order_id, $amount, $currency, $payment_method);

            // Initialize payment gateway
            $payment_data = $this->initializeGateway($payment_method, $payment_id, $amount, $currency, $order);

            return [
                'success' => true,
                'payment_id' => $payment_id,
                'payment_method' => $payment_method,
                'gateway_data' => $payment_data
            ];

        } catch (Exception $e) {
            error_log("Payment initialization error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Determine the best payment method based on currency and location
     */
    private function determinePaymentMethod($currency, $preferred_method) {
        if ($preferred_method !== 'auto') {
            return $preferred_method;
        }

        // Local Ghana payments
        if ($currency === 'GHS') {
            $methods = ['paystack', 'flutterwave', 'mtn_momo'];
            return $methods[array_rand($methods)];
        }

        // International payments
        if (in_array($currency, ['USD', 'EUR', 'GBP'])) {
            $methods = ['stripe', 'paypal'];
            return $methods[array_rand($methods)];
        }

        return 'stripe';
    }

    /**
     * Create payment record in database
     */
    private function createPaymentRecord($order_id, $amount, $currency, $payment_method) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payments (order_id, amount, currency, payment_method, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', NOW())
        ");
        $stmt->execute([$order_id, $amount, $currency, $payment_method]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Initialize payment gateway
     */
    private function initializeGateway($method, $payment_id, $amount, $currency, $order) {
        switch ($method) {
            case 'paystack':
                return $this->initializePaystack($payment_id, $amount, $currency, $order);
            case 'flutterwave':
                return $this->initializeFlutterwave($payment_id, $amount, $currency, $order);
            case 'mtn_momo':
                return $this->initializeMTNMomo($payment_id, $amount, $currency, $order);
            case 'stripe':
                return $this->initializeStripe($payment_id, $amount, $currency, $order);
            case 'paypal':
                return $this->initializePayPal($payment_id, $amount, $currency, $order);
            default:
                throw new Exception("Unsupported payment method: $method");
        }
    }

    /**
     * Initialize Paystack payment
     */
    private function initializePaystack($payment_id, $amount, $currency, $order) {
        $config = PaymentConfig::$PAYSTACK;
        
        $data = [
            'amount' => $amount * 100,
            'email' => $order['buyer_email'],
            'reference' => 'TAA_' . $payment_id . '_' . time(),
            'callback_url' => PaymentConfig::$GENERAL['webhook_url'] . '?gateway=paystack',
            'currency' => $currency,
            'metadata' => [
                'payment_id' => $payment_id,
                'order_id' => $order['id']
            ]
        ];

        $response = $this->makeApiCall('https://api.paystack.co/transaction/initialize', $data, [
            'Authorization: Bearer ' . $config['secret_key'],
            'Content-Type: application/json'
        ]);

        if ($response['status']) {
            return [
                'authorization_url' => $response['data']['authorization_url'],
                'reference' => $response['data']['reference'],
                'access_code' => $response['data']['access_code']
            ];
        } else {
            throw new Exception("Paystack initialization failed: " . $response['message']);
        }
    }

    /**
     * Initialize Flutterwave payment
     */
    private function initializeFlutterwave($payment_id, $amount, $currency, $order) {
        $config = PaymentConfig::$FLUTTERWAVE;
        
        $data = [
            'tx_ref' => 'TAA_' . $payment_id . '_' . time(),
            'amount' => $amount,
            'currency' => $currency,
            'redirect_url' => PaymentConfig::$GENERAL['webhook_url'] . '?gateway=flutterwave',
            'customer' => [
                'email' => $order['buyer_email'],
                'name' => $order['buyer_name'] ?? 'Customer'
            ],
            'customizations' => [
                'title' => 'TaaBia Course Payment',
                'description' => 'Payment for order #' . $order['id']
            ]
        ];

        $response = $this->makeApiCall('https://api.flutterwave.com/v3/payments', $data, [
            'Authorization: Bearer ' . $config['secret_key'],
            'Content-Type: application/json'
        ]);

        if ($response['status'] === 'success') {
            return [
                'payment_url' => $response['data']['link'],
                'reference' => $response['data']['tx_ref']
            ];
        } else {
            throw new Exception("Flutterwave initialization failed: " . $response['message']);
        }
    }

    /**
     * Initialize MTN Mobile Money payment
     */
    private function initializeMTNMomo($payment_id, $amount, $currency, $order) {
        $config = PaymentConfig::$MTN_MOMO;
        
        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'externalId' => 'TAA_' . $payment_id,
            'payer' => [
                'partyIdType' => 'MSISDN',
                'partyId' => $order['buyer_phone'] ?? ''
            ],
            'payerMessage' => 'Payment for TaaBia course',
            'payeeNote' => 'Course payment'
        ];

        $base_url = $config['environment'] === 'production' 
            ? 'https://proxy.momoapi.mtn.com' 
            : 'https://sandbox.momodeveloper.mtn.com';

        $response = $this->makeApiCall($base_url . '/collection/v1_0/requesttopay', $data, [
            'Authorization: Bearer ' . $this->getMTNToken(),
            'X-Reference-Id: ' . $data['externalId'],
            'X-Target-Environment: ' . $config['environment'],
            'Content-Type: application/json'
        ]);

        return [
            'reference' => $data['externalId'],
            'status' => 'pending'
        ];
    }

    /**
     * Initialize Stripe payment
     */
    private function initializeStripe($payment_id, $amount, $currency, $order) {
        $config = PaymentConfig::$STRIPE;
        
        $data = [
            'amount' => $amount * 100,
            'currency' => strtolower($currency),
            'metadata' => [
                'payment_id' => $payment_id,
                'order_id' => $order['id']
            ],
            'description' => 'TaaBia Course Payment - Order #' . $order['id']
        ];

        $response = $this->makeApiCall('https://api.stripe.com/v1/payment_intents', $data, [
            'Authorization: Bearer ' . $config['secret_key'],
            'Content-Type: application/x-www-form-urlencoded'
        ], 'POST', true);

        if (isset($response['id'])) {
            return [
                'client_secret' => $response['client_secret'],
                'payment_intent_id' => $response['id'],
                'publishable_key' => $config['publishable_key']
            ];
        } else {
            throw new Exception("Stripe initialization failed");
        }
    }

    /**
     * Initialize PayPal payment
     */
    private function initializePayPal($payment_id, $amount, $currency, $order) {
        $config = PaymentConfig::$PAYPAL;
        
        $data = [
            'intent' => 'CAPTURE',
            'application_context' => [
                'return_url' => PaymentConfig::$GENERAL['webhook_url'] . '?gateway=paypal&success=true',
                'cancel_url' => PaymentConfig::$GENERAL['webhook_url'] . '?gateway=paypal&success=false'
            ],
            'purchase_units' => [
                [
                    'reference_id' => 'TAA_' . $payment_id,
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ],
                    'description' => 'TaaBia Course Payment - Order #' . $order['id']
                ]
            ]
        ];

        $base_url = $config['environment'] === 'production' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';

        $response = $this->makeApiCall($base_url . '/v2/checkout/orders', $data, [
            'Authorization: Bearer ' . $this->getPayPalToken(),
            'Content-Type: application/json'
        ]);

        if (isset($response['id'])) {
            return [
                'order_id' => $response['id'],
                'approval_url' => $response['links'][1]['href'] ?? '',
                'environment' => $config['environment']
            ];
        } else {
            throw new Exception("PayPal initialization failed");
        }
    }

    /**
     * Make API call to payment gateways
     */
    private function makeApiCall($url, $data, $headers = [], $method = 'POST', $form_encoded = false) {
        $ch = curl_init();
        
        if ($form_encoded) {
            $post_data = http_build_query($data);
        } else {
            $post_data = json_encode($data);
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => $method === 'POST',
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("API call failed");
        }

        $decoded_response = json_decode($response, true);
        
        if ($http_code >= 400) {
            throw new Exception("API error: " . ($decoded_response['message'] ?? 'Unknown error'));
        }

        return $decoded_response;
    }

    /**
     * Get MTN MoMo access token
     */
    private function getMTNToken() {
        $config = PaymentConfig::$MTN_MOMO;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://sandbox.momodeveloper.mtn.com/collection/token/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($config['api_key'] . ':' . $config['merchant_id']),
                'X-Reference-Id: ' . uniqid(),
                'X-Target-Environment: ' . $config['environment'],
                'Ocp-Apim-Subscription-Key: ' . $config['api_key']
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded['access_token'] ?? '';
    }

    /**
     * Get PayPal access token
     */
    private function getPayPalToken() {
        $config = PaymentConfig::$PAYPAL;
        
        $base_url = $config['environment'] === 'production' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $base_url . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']),
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);
        return $decoded['access_token'] ?? '';
    }

    /**
     * Verify payment
     */
    public function verifyPayment($payment_id, $gateway, $reference) {
        try {
            switch ($gateway) {
                case 'paystack':
                    return $this->verifyPaystackPayment($reference);
                case 'flutterwave':
                    return $this->verifyFlutterwavePayment($reference);
                case 'mtn_momo':
                    return $this->verifyMTNMomoPayment($reference);
                case 'stripe':
                    return $this->verifyStripePayment($reference);
                case 'paypal':
                    return $this->verifyPayPalPayment($reference);
                default:
                    throw new Exception("Unsupported gateway: $gateway");
            }
        } catch (Exception $e) {
            error_log("Payment verification error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($payment_id, $status, $gateway_data = []) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE payments 
                SET status = ?, gateway_data = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, json_encode($gateway_data), $payment_id]);

            if ($status === 'completed') {
                $stmt = $this->pdo->prepare("
                    UPDATE orders 
                    SET status = 'completed', completed_at = NOW() 
                    WHERE id = (SELECT order_id FROM payments WHERE id = ?)
                ");
                $stmt->execute([$payment_id]);
            }

            return true;
        } catch (Exception $e) {
            error_log("Payment status update error: " . $e->getMessage());
            return false;
        }
    }

    // Individual verification methods for each gateway
    private function verifyPaystackPayment($reference) {
        $config = PaymentConfig::$PAYSTACK;
        
        $response = $this->makeApiCall(
            "https://api.paystack.co/transaction/verify/$reference",
            [],
            ['Authorization: Bearer ' . $config['secret_key']],
            'GET'
        );

        return [
            'success' => $response['status'] && $response['data']['status'] === 'success',
            'amount' => $response['data']['amount'] / 100,
            'currency' => $response['data']['currency'],
            'gateway_data' => $response
        ];
    }

    private function verifyFlutterwavePayment($reference) {
        $config = PaymentConfig::$FLUTTERWAVE;
        
        $response = $this->makeApiCall(
            "https://api.flutterwave.com/v3/transactions/$reference/verify",
            [],
            ['Authorization: Bearer ' . $config['secret_key']],
            'GET'
        );

        return [
            'success' => $response['status'] === 'success' && $response['data']['status'] === 'successful',
            'amount' => $response['data']['amount'],
            'currency' => $response['data']['currency'],
            'gateway_data' => $response
        ];
    }

    private function verifyMTNMomoPayment($reference) {
        return ['success' => true, 'amount' => 0, 'currency' => 'GHS'];
    }

    private function verifyStripePayment($payment_intent_id) {
        $config = PaymentConfig::$STRIPE;
        
        $response = $this->makeApiCall(
            "https://api.stripe.com/v1/payment_intents/$payment_intent_id",
            [],
            ['Authorization: Bearer ' . $config['secret_key']],
            'GET'
        );

        return [
            'success' => $response['status'] === 'succeeded',
            'amount' => $response['amount'] / 100,
            'currency' => strtoupper($response['currency']),
            'gateway_data' => $response
        ];
    }

    private function verifyPayPalPayment($order_id) {
        $config = PaymentConfig::$PAYPAL;
        
        $base_url = $config['environment'] === 'production' 
            ? 'https://api-m.paypal.com' 
            : 'https://api-m.sandbox.paypal.com';

        $response = $this->makeApiCall(
            "$base_url/v2/checkout/orders/$order_id",
            [],
            ['Authorization: Bearer ' . $this->getPayPalToken()],
            'GET'
        );

        return [
            'success' => $response['status'] === 'COMPLETED',
            'amount' => $response['purchase_units'][0]['amount']['value'],
            'currency' => $response['purchase_units'][0]['amount']['currency_code'],
            'gateway_data' => $response
        ];
    }
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
