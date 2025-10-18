<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['product_ids']) || !is_array($input['product_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid product IDs']);
    exit;
}

$product_ids = array_map('intval', $input['product_ids']);

try {
    // Get current stock for all products
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $query = "
        SELECT id, stock_quantity, created_at 
        FROM products 
        WHERE id IN ($placeholders) AND status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($product_ids);
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'stocks' => array_map(function($stock) {
            return [
                'product_id' => (int)$stock['id'],
                'stock' => (int)$stock['stock_quantity'],
                'last_updated' => $stock['created_at']
            ];
        }, $stocks)
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log('Stock status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
