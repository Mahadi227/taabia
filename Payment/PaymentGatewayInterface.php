<?php
interface PaymentGatewayInterface {
    public function initialize(array $data);
    public function verify(string $transactionId);
    public function getPaymentUrl(): string;
}
class FlutterwavePaymentGateway implements PaymentGatewayInterface {
    private $publicKey;
    private $secretKey;
    private $encryptionKey;
    private $baseUrl;
    private $paymentUrl;

    public function __construct($publicKey, $secretKey, $encryptionKey, $baseUrl) {
        $this->publicKey = $publicKey;
        $this->secretKey = $secretKey;
        $this->encryptionKey = $encryptionKey;
        $this->baseUrl = $baseUrl;
    }

    public function initialize(array $data) {
        // Implementation for initializing payment
    }

    public function verify(string $transactionId) {
        // Implementation for verifying payment
    }

    public function getPaymentUrl(): string {
        return $this->paymentUrl;
    }
}                                                   