<?php
require_once 'PaymentGatewayInterface.php';

class PaystackPaymentGateway implements PaymentGatewayInterface
{
    private $secretKey;
    private $publicKey;
    private $webhookSecret;

    public function __construct($config = [])
    {
        $this->secretKey = $config['secret_key'] ?? '';
        $this->publicKey = $config['public_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    public function initialize($paymentData)
    {
        try {
            $url = 'https://api.paystack.co/transaction/initialize';
            
            $data = [
                'amount' => $paymentData['amount'] * 100, // Convert to kobo (smallest currency unit)
                'email' => $paymentData['customer_email'],
                'reference' => $paymentData['reference'] ?? self::generateTransactionRef(),
                'callback_url' => $paymentData['callback_url'] ?? '',
                'currency' => $paymentData['currency'] ?? 'NGN',
                'metadata' => [
                    'order_id' => $paymentData['order_id'],
                    'description' => $paymentData['description']
                ]
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result['status']) {
                    return [
                        'success' => true,
                        'authorization_url' => $result['data']['authorization_url'],
                        'reference' => $result['data']['reference'],
                        'access_code' => $result['data']['access_code']
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $result['message'] ?? 'Payment initialization failed'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $httpCode
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function verify($transactionId)
    {
        try {
            $url = 'https://api.paystack.co/transaction/verify/' . $transactionId;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->secretKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                if ($result['status']) {
                    $transaction = $result['data'];
                    return [
                        'success' => true,
                        'status' => $transaction['status'],
                        'amount' => $transaction['amount'] / 100, // Convert from kobo
                        'currency' => $transaction['currency'],
                        'reference' => $transaction['reference'],
                        'metadata' => $transaction['metadata'] ?? []
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => $result['message'] ?? 'Transaction verification failed'
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $httpCode
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPaymentUrl($paymentData)
    {
        $result = $this->initialize($paymentData);
        if ($result['success']) {
            return $result['authorization_url'];
        }
        return null;
    }

    public function validateWebhookSignature($payload, $signature)
    {
        $computedSignature = hash_hmac('sha512', $payload, $this->webhookSecret);
        return hash_equals($computedSignature, $signature);
    }

    public static function generateTransactionRef()
    {
        return 'paystack_' . time() . '_' . uniqid();
    }
}
