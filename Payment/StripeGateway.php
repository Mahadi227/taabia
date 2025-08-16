<?php
require_once 'PaymentGatewayInterface.php';

class StripePaymentGateway implements PaymentGatewayInterface
{
    private $secretKey;
    private $publishableKey;
    private $webhookSecret;

    public function __construct($config = [])
    {
        $this->secretKey = $config['secret_key'] ?? '';
        $this->publishableKey = $config['publishable_key'] ?? '';
        $this->webhookSecret = $config['webhook_secret'] ?? '';
    }

    public function initialize($paymentData)
    {
        try {
            // Set your secret key
            \Stripe\Stripe::setApiKey($this->secretKey);

            // Create a PaymentIntent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $paymentData['amount'] * 100, // Convert to cents
                'currency' => $paymentData['currency'] ?? 'usd',
                'metadata' => [
                    'order_id' => $paymentData['order_id'],
                    'customer_email' => $paymentData['customer_email'],
                    'description' => $paymentData['description']
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'publishable_key' => $this->publishableKey
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function verify($transactionId)
    {
        try {
            \Stripe\Stripe::setApiKey($this->secretKey);
            
            $paymentIntent = \Stripe\PaymentIntent::retrieve($transactionId);
            
            return [
                'success' => true,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100, // Convert from cents
                'currency' => $paymentIntent->currency,
                'metadata' => $paymentIntent->metadata->toArray()
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPaymentUrl($paymentData)
    {
        // Stripe doesn't provide a direct payment URL like some other gateways
        // This would typically be handled by the frontend with Stripe.js
        return null;
    }

    public function validateWebhookSignature($payload, $signature)
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );
            return $event;
        } catch (\UnexpectedValueException $e) {
            return false;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return false;
        }
    }

    public static function generateTransactionRef()
    {
        return 'stripe_' . time() . '_' . uniqid();
    }
}
