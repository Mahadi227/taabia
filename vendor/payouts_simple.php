<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// For testing, let's use a fixed vendor ID
$vendor_id = 1; // Change this to test with different vendors
$message = '';
$error = '';

// Initialize variables
$available_balance = 0;
$pending_payouts = 0;
$total_payouts = 0;
$payout_requests = [];

try {
    // Check if required tables exist
    $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    $payoutRequestsExists = $pdo->query("SHOW TABLES LIKE 'payout_requests'")->rowCount() > 0;
    
    echo "<h2>Debug Info:</h2>";
    echo "Transactions table exists: " . ($transactionsExists ? 'YES' : 'NO') . "<br>";
    echo "Payout_requests table exists: " . ($payoutRequestsExists ? 'YES' : 'NO') . "<br>";
    
    if ($transactionsExists) {
        // Calculate available balance (completed transactions - paid out)
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(amount), 0) 
            FROM transactions 
            WHERE vendor_id = ? AND type = 'product_purchase' AND status = 'completed'
        ");
        if ($stmt->execute([$vendor_id])) {
            $total_earnings = $stmt->fetchColumn();
            echo "Total earnings: " . $total_earnings . "<br>";
        }
        
        if ($payoutRequestsExists) {
            // Get total paid out
            $stmt = $pdo->prepare("
                SELECT IFNULL(SUM(amount), 0) 
                FROM payout_requests 
                WHERE user_id = ? AND status = 'processed'
            ");
            if ($stmt->execute([$vendor_id])) {
                $total_paid_out = $stmt->fetchColumn();
                echo "Total paid out: " . $total_paid_out . "<br>";
            }
            
            // Get pending payouts
            $stmt = $pdo->prepare("
                SELECT IFNULL(SUM(amount), 0) 
                FROM payout_requests 
                WHERE user_id = ? AND status = 'pending'
            ");
            if ($stmt->execute([$vendor_id])) {
                $pending_payouts = $stmt->fetchColumn();
                echo "Pending payouts: " . $pending_payouts . "<br>";
            }
            
            $available_balance = $total_earnings - $total_paid_out - $pending_payouts;
            $total_payouts = $total_paid_out;
            
            // Get payout requests
            $stmt = $pdo->prepare("
                SELECT * FROM payout_requests 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            if ($stmt->execute([$vendor_id])) {
                $payout_requests = $stmt->fetchAll();
                echo "Payout requests found: " . count($payout_requests) . "<br>";
            }
        } else {
            $available_balance = $total_earnings;
        }
    }
} catch (PDOException $e) {
    error_log("Database error in vendor payouts: " . $e->getMessage());
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Payouts - Simple Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .stats { display: flex; gap: 20px; margin: 20px 0; }
        .stat-card { background: #f5f5f5; padding: 15px; border-radius: 5px; }
        .error { color: red; }
        .success { color: green; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>Vendor Payouts - Simple Test</h1>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if ($message): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>
    
    <div class="stats">
        <div class="stat-card">
            <h3>Available Balance</h3>
            <div><?= number_format($available_balance, 2) ?> GHS</div>
        </div>
        <div class="stat-card">
            <h3>Pending Payouts</h3>
            <div><?= number_format($pending_payouts, 2) ?> GHS</div>
        </div>
        <div class="stat-card">
            <h3>Total Paid</h3>
            <div><?= number_format($total_payouts, 2) ?> GHS</div>
        </div>
    </div>
    
    <h2>Payout Requests</h2>
    <?php if (empty($payout_requests)): ?>
        <p>No payout requests found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payout_requests as $payout): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($payout['created_at'])) ?></td>
                    <td><?= number_format($payout['amount'], 2) ?> GHS</td>
                    <td><?= htmlspecialchars($payout['payment_method']) ?></td>
                    <td><?= ucfirst($payout['status']) ?></td>
                    <td><?= htmlspecialchars($payout['account_details']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <h2>Test Payout Request</h2>
    <form method="POST">
        <div>
            <label>Amount (GHS):</label>
            <input type="number" name="amount" step="0.01" min="50" max="<?= $available_balance ?>" required>
        </div>
        <div>
            <label>Payment Method:</label>
            <select name="payment_method" required>
                <option value="">Select method</option>
                <option value="Mobile Money">Mobile Money</option>
                <option value="Bank Transfer">Bank Transfer</option>
                <option value="PayPal">PayPal</option>
            </select>
        </div>
        <div>
            <label>Account Details:</label>
            <textarea name="bank_details" required></textarea>
        </div>
        <button type="submit" name="request_payout">Submit Request</button>
    </form>
    
    <?php
    // Handle payout request
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
        $amount = filter_var($_POST['amount'], FILTER_VALIDATE_FLOAT);
        $payment_method = $_POST['payment_method'];
        $bank_details = $_POST['bank_details'];
        
        if ($amount && $amount > 0 && $amount <= $available_balance && $amount >= 50) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO payout_requests (user_id, amount, status, payment_method, account_details, created_at)
                    VALUES (?, ?, 'pending', ?, ?, NOW())
                ");
                
                if ($stmt->execute([$vendor_id, $amount, $payment_method, $bank_details])) {
                    echo "<div class='success'>Payout request submitted successfully!</div>";
                    // Refresh the page to show updated data
                    echo "<script>location.reload();</script>";
                } else {
                    echo "<div class='error'>Error submitting request</div>";
                }
            } catch (PDOException $e) {
                echo "<div class='error'>Database error: " . $e->getMessage() . "</div>";
            }
        } else {
            echo "<div class='error'>Invalid amount or insufficient balance</div>";
        }
    }
    ?>
</body>
</html>
























