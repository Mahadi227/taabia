<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Start session
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$product_id = intval($_POST['product_id'] ?? 0);
$type = $_POST['type'] ?? 'product';

if (!$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    // Get product details
    $query = "SELECT * FROM products WHERE id = ? AND status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }
    
    // Check stock
    if ($product['stock_quantity'] <= 0) {
        echo json_encode(['success' => false, 'message' => 'Product out of stock']);
        exit;
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = ['products' => [], 'courses' => []];
    }
    
    // Check if product already in cart
    if (isset($_SESSION['cart']['products'][$product_id])) {
        echo json_encode(['success' => false, 'message' => 'Product already in cart']);
        exit;
    }
    
    // Add product to cart with product_id as key
    $_SESSION['cart']['products'][$product_id] = [
        'id' => $product['id'],
        'name' => $product['name'],
        'price' => $product['price'],
        'image_url' => $product['image_url'],
        'quantity' => 1,
        'type' => 'product'
    ];
    
    // Calculate cart count
    $cart_count = count($_SESSION['cart']['products']) + count($_SESSION['cart']['courses']);
    
    // Get updated stock information
    $updated_stock = 0;
    try {
        $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stock_stmt->execute([$product_id]);
        $updated_stock = $stock_stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Error getting updated stock: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true, 
        'message' => 'Product added to cart successfully',
        'cart_count' => $cart_count,
        'new_stock' => (int)$updated_stock
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
