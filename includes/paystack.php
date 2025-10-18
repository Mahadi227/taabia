<?php
// Simple Paystack helper
// Set your keys here or via environment variables
// For security, prefer environment variables
$PAYSTACK_SECRET_KEY = getenv('PAYSTACK_SECRET_KEY') ?: 'YOUR_PAYSTACK_SECRET_KEY';
$PAYSTACK_PUBLIC_KEY = getenv('PAYSTACK_PUBLIC_KEY') ?: 'YOUR_PAYSTACK_PUBLIC_KEY';
$PAYSTACK_BASE_URL = 'https://api.paystack.co';

function paystackInitialize(string $email, int $amountMinor, string $reference, string $currency = 'GHS', string $callbackUrl = '') {
    global $PAYSTACK_SECRET_KEY, $PAYSTACK_BASE_URL;
    $data = [
        'email' => $email,
        'amount' => $amountMinor,
        'reference' => $reference,
        'currency' => $currency,
    ];
    if (!empty($callbackUrl)) {
        $data['callback_url'] = $callbackUrl;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $PAYSTACK_BASE_URL . '/transaction/initialize');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $PAYSTACK_SECRET_KEY,
        'Cache-Control: no-cache',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [false, 'Curl error: ' . $error, null];
    }
    curl_close($ch);
    $json = json_decode($response, true);
    if (!is_array($json) || empty($json['status'])) {
        return [false, 'Invalid Paystack response', $json];
    }
    if ($json['status'] !== true) {
        return [false, $json['message'] ?? 'Paystack initialize failed', $json];
    }
    return [true, null, $json['data']];
}

function paystackVerify(string $reference) {
    global $PAYSTACK_SECRET_KEY, $PAYSTACK_BASE_URL;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $PAYSTACK_BASE_URL . '/transaction/verify/' . rawurlencode($reference));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $PAYSTACK_SECRET_KEY,
        'Cache-Control: no-cache',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [false, 'Curl error: ' . $error, null];
    }
    curl_close($ch);
    $json = json_decode($response, true);
    if (!is_array($json) || empty($json['status'])) {
        return [false, 'Invalid Paystack response', $json];
    }
    if ($json['status'] !== true) {
        return [false, $json['message'] ?? 'Verification failed', $json];
    }
    return [true, null, $json['data']];
}























