$type = $_GET['provider']; // flutterwave, etc.

switch ($type) {
    case 'flutterwave':
        $gateway = new FlutterwaveGateway();
        break;
    // ...
}

$transactionId = $_GET['transaction_id'];
$response = $gateway->verify($transactionId);

if ($response['status'] == 'success') {
    // Mise à jour en base : status = 'success'
} else {
    // status = 'failed'
}
