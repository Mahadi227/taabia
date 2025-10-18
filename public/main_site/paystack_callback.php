<?php
require_once '../../includes/db.php';
require_once '../../includes/paystack.php';
session_start();

$reference = $_GET['reference'] ?? '';
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$reference || !$order_id) {
    die('Invalid callback parameters');
}

list($ok, $err, $data) = paystackVerify($reference);
if (!$ok) {
    // Mark transaction failed
    try {
        $stmt = $pdo->prepare("UPDATE transactions SET payment_status = 'failed', status = 'failed' WHERE reference = ?");
        $stmt->execute([$reference]);
    } catch (Exception $e) {}
    die('Payment verification failed: ' . htmlspecialchars($err));
}

// Expecting $data['status'] === 'success'
$status = $data['status'] ?? '';
$amountMinor = (int)($data['amount'] ?? 0); // in kobo/pesewas
$currency = $data['currency'] ?? 'GHS';
$paidAt = $data['paid_at'] ?? null;

if ($status !== 'success') {
    try {
        $stmt = $pdo->prepare("UPDATE transactions SET payment_status = 'failed', status = 'failed' WHERE reference = ?");
        $stmt->execute([$reference]);
    } catch (Exception $e) {}
    die('Payment not successful');
}

try {
    $pdo->beginTransaction();

    // Update transaction
    $stmt = $pdo->prepare("UPDATE transactions SET payment_status = 'success', status = 'completed', currency = ?, transaction_id = ?, updated_at = NOW() WHERE reference = ?");
    $stmt->execute([$currency, $data['id'] ?? null, $reference]);

    // Update order to paid
    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$order_id]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    die('Database error on finalize: ' . htmlspecialchars($e->getMessage()));
}

// Clear cart safely
$_SESSION['cart'] = ['products' => [], 'courses' => []];

header('Location: order_success.php?order_id=' . $order_id);
exit;























