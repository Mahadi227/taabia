<?php
require_once 'PaymentGatewayInterface.php';

class MomoPaymentGateway implements PaymentGatewayInterface
{
    private $apiKey;
    private $merchantId;
    private $environment; // 'sandbox' or 'live'

    public function __construct($config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->merchantId = $config['merchant_id'] ?? '';
        $this->environment = $config['environment'] ?? 'sandbox';
    }

    public function initialize($paymentData)
    {
        try {
            $baseUrl = $this->environment === 'live' 
                ? 'https://api.mtn.com' 
                : 'https://sandbox.mtn.com';
            
            $url = $baseUrl . '/collection/v1_0/requesttopay';
            
            $data = [
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'EUR',
                'externalId' => $paymentData['reference'] ?? self::generateTransactionRef(),
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $paymentData['phone_number']
                ],
                'payerMessage' => $paymentData['description'] ?? 'Payment for order',
                'payeeNote' => $paymentData['description'] ?? 'Payment received'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'X-Reference-Id: ' . $data['externalId'],
                'X-Target-Environment: ' . $this->environment,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 202) {
                return [
                    'success' => true,
                    'reference' => $data['externalId'],
                    'status' => 'pending'
                ];
            } else {
                $error = json_decode($response, true);
                return [
                    'success' => false,
                    'error' => $error['message'] ?? 'Payment initialization failed'
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
            $baseUrl = $this->environment === 'live' 
                ? 'https://api.mtn.com' 
                : 'https://sandbox.mtn.com';
            
            $url = $baseUrl . '/collection/v1_0/requesttopay/' . $transactionId;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->getAccessToken(),
                'X-Target-Environment: ' . $this->environment
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $result = json_decode($response, true);
                return [
                    'success' => true,
                    'status' => $result['status'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'reference' => $result['externalId']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Transaction verification failed'
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
        // Mobile Money doesn't provide a direct payment URL
        // It's typically handled through USSD or mobile app
        return null;
    }

    private function getAccessToken()
    {
        // This is a simplified version. In production, you'd want to cache the token
        $baseUrl = $this->environment === 'live' 
            ? 'https://api.mtn.com' 
            : 'https://sandbox.mtn.com';
        
        $url = $baseUrl . '/collection/token/';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->merchantId . ':' . $this->apiKey);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Target-Environment: ' . $this->environment
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['access_token'] ?? '';
    }

    public function validateWebhookSignature($payload, $signature)
    {
        // Mobile Money webhook validation would depend on the specific implementation
        // This is a placeholder for the actual validation logic
        $expectedSignature = hash_hmac('sha256', $payload, $this->apiKey);
        return hash_equals($expectedSignature, $signature);
    }

    public static function generateTransactionRef()
    {
        return 'momo_' . time() . '_' . uniqid();
    }
}
