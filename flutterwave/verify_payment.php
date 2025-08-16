<?php
require_once 'flutterwave_config.php';

if (isset($_GET['transaction_id'])) {
    $transaction_id = $_GET['transaction_id'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => FLW_BASE_URL . "/transactions/$transaction_id/verify",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . FLW_SECRET_KEY
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $result = json_decode($response);
    if ($result->status === 'success' && $result->data->status === 'successful') {
        $amount = $result->data->amount;
        $email = $result->data->customer->email;
        $tx_ref = $result->data->tx_ref;
        $payment_method = $result->data->payment_type;

        // ✅ Enregistre la transaction dans la base de données
        // INSERT INTO transactions (user_email, amount, tx_ref, status, payment_method) VALUES (...)

        echo "✅ Paiement réussi de GHS $amount. Merci $email.";
    } else {
        echo "❌ Paiement échoué.";
    }
} else {
    echo "❌ Transaction invalide.";
}
