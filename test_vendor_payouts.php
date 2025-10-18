<?php
// Simple test to check vendor payouts functionality
require_once 'includes/db.php';
require_once 'includes/function.php';

echo "<h1>Vendor Payouts Test</h1>";

try {
    // Check if tables exist
    $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    $payoutRequestsExists = $pdo->query("SHOW TABLES LIKE 'payout_requests'")->rowCount() > 0;
    
    echo "<h2>Table Status:</h2>";
    echo "Transactions table exists: " . ($transactionsExists ? 'YES' : 'NO') . "<br>";
    echo "Payout_requests table exists: " . ($payoutRequestsExists ? 'YES' : 'NO') . "<br>";
    
    if ($transactionsExists) {
        // Check transactions table structure
        $stmt = $pdo->query("DESCRIBE transactions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>Transactions table columns:</h3>";
        echo implode(', ', $columns) . "<br>";
        
        // Check for vendor transactions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE vendor_id IS NOT NULL");
        $stmt->execute();
        $vendorTransactions = $stmt->fetchColumn();
        echo "Vendor transactions count: " . $vendorTransactions . "<br>";
    }
    
    if ($payoutRequestsExists) {
        // Check payout_requests table structure
        $stmt = $pdo->query("DESCRIBE payout_requests");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<h3>Payout_requests table columns:</h3>";
        echo implode(', ', $columns) . "<br>";
        
        // Check for payout requests
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payout_requests");
        $stmt->execute();
        $payoutRequests = $stmt->fetchColumn();
        echo "Payout requests count: " . $payoutRequests . "<br>";
    }
    
    // Test a sample vendor ID
    $testVendorId = 1;
    echo "<h3>Testing with vendor ID: " . $testVendorId . "</h3>";
    
    if ($transactionsExists) {
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(amount), 0) 
            FROM transactions 
            WHERE vendor_id = ? AND type = 'product_purchase' AND status = 'completed'
        ");
        $stmt->execute([$testVendorId]);
        $totalEarnings = $stmt->fetchColumn();
        echo "Total earnings for vendor " . $testVendorId . ": " . $totalEarnings . " GHS<br>";
    }
    
    if ($payoutRequestsExists) {
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(amount), 0) 
            FROM payout_requests 
            WHERE user_id = ? AND status = 'processed'
        ");
        $stmt->execute([$testVendorId]);
        $totalPaidOut = $stmt->fetchColumn();
        echo "Total paid out for vendor " . $testVendorId . ": " . $totalPaidOut . " GHS<br>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error:</h2>";
    echo $e->getMessage();
}
?>
























