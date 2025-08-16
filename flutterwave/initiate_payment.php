<?php
require_once 'flutterwave_config.php';

$amount = $_POST['amount'];
$email = $_POST['email'];
$fullname = $_POST['name'];
$course_or_product_id = $_POST['item_id']; // cours ou produit

$tx_ref = 'TX_' . uniqid(); // unique transaction ref
$callback_url = "http://localhost/dashboard/taabia/flutterwave/verify_payment.php";

$data = [
    'tx_ref' => $tx_ref,
    'amount' => $amount,
    'currency' => 'GHS',
    'redirect_url' => $callback_url,
    'customer' => [
        'email' => $email,
        'name' => $fullname
    ],
    'customizations' => [
        'title' => 'Paiement TaaBia',
        'description' => 'Achat de formation ou produit'
    ]
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => FLW_BASE_URL . '/payments',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . FLW_SECRET_KEY,
        "Content-Type: application/json"
    ]
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("Erreur de connexion à Flutterwave: $err");
} else {
    $result = json_decode($response);
    if (isset($result->data->link)) {
        header("Location: " . $result->data->link); // redirige vers la page de paiement Flutterwave
        exit;
    } else {
        echo "Erreur d'initialisation du paiement.";
    }
}
