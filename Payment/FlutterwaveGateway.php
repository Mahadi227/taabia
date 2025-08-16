<?php
require_once 'PaymentGatewayInterface.php';

class FlutterwaveGateway implements PaymentGatewayInterface {
    private $publicKey;
    private $secretKey;
    private $encryptionKey;
    private $baseUrl;
    private $paymentUrl;

    public function __construct($publicKey, $secretKey, $encryptionKey, $baseUrl = 'https://api.flutterwave.com/v3') {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->encryptionKey = $encryptionKey;
        $this->baseUrl = $baseUrl;
    }

    public function initialize(array $data) {
        $payload = [
            'tx_ref' => $data['transaction_id'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'GHS',
            'redirect_url' => $data['redirect_url'],
            'customer' => [
                'email' => $data['customer_email'],
                'name' => $data['customer_name'],
                'phone_number' => $data['customer_phone'] ?? ''
            ],
            'meta' => [
                'order_id' => $data['order_id'] ?? '',
                'product_id' => $data['product_id'] ?? '',
                'course_id' => $data['course_id'] ?? ''
            ],
            'customizations' => [
                'title' => 'TaaBia Skills & Market',
                'description' => $data['description'] ?? 'Payment for products/services',
                'logo' => 'https://taabia.com/assets/img/logo.png'
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $this->publicKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/payments');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result['status'] === 'success') {
                $this->paymentUrl = $result['data']['link'];
                return [
                    'success' => true,
                    'payment_url' => $this->paymentUrl,
                    'transaction_id' => $result['data']['reference']
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to initialize payment'
        ];
    }

    public function verify(string $transactionId) {
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/transactions/' . $transactionId . '/verify');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result['status'] === 'success') {
                $transaction = $result['data'];
                return [
                    'success' => true,
                    'status' => $transaction['status'],
                    'amount' => $transaction['amount'],
                    'currency' => $transaction['currency'],
                    'customer_email' => $transaction['customer']['email'],
                    'customer_name' => $transaction['customer']['name'],
                    'payment_type' => $transaction['payment_type'],
                    'created_at' => $transaction['created_at']
                ];
            }
        }

        return [
            'success' => false,
            'message' => 'Failed to verify transaction'
        ];
    }

    public function getPaymentUrl(): string {
        return $this->paymentUrl;
    }

    // Helper method to generate transaction reference
    public static function generateTransactionRef(): string {
        return 'TAA_' . time() . '_' . rand(1000, 9999);
    }

    // Helper method to validate webhook signature
    public function validateWebhookSignature($data, $signature) {
        $expectedSignature = hash_hmac('sha256', $data, $this->encryptionKey);
        return hash_equals($expectedSignature, $signature);
    }
}
