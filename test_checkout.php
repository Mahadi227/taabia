<?php
// Test script to verify checkout functionality
session_start();

echo "<h1>Checkout Process Test</h1>";

// Check if cart exists and has items
if (!isset($_SESSION['cart']) || (empty($_SESSION['cart']['products']) && empty($_SESSION['cart']['courses']))) {
    echo "<p>❌ Cart is empty. Please add items to cart first.</p>";
    echo "<p><a href='public/main_site/shop.php'>Go to Shop</a></p>";
    echo "<p><a href='test_cart_working.php'>Add Test Items to Cart</a></p>";
    exit;
}

echo "<p>✅ Cart has items</p>";

// Display cart contents
echo "<h2>Current Cart Contents:</h2>";
echo "<pre>";
print_r($_SESSION['cart']);
echo "</pre>";

// Calculate totals
$total_items = 0;
$total_price = 0;

foreach ($_SESSION['cart']['courses'] as $item) {
    $total_items++;
    $total_price += $item['price'];
}

foreach ($_SESSION['cart']['products'] as $item) {
    $total_items += $item['quantity'];
    $total_price += $item['price'] * $item['quantity'];
}

$tax = $total_price * 0.15;
$total_with_tax = $total_price + $tax;

echo "<h2>Order Summary:</h2>";
echo "<p>Total items: $total_items</p>";
echo "<p>Subtotal: " . number_format($total_price, 0, ',', ' ') . " FCFA</p>";
echo "<p>Tax (15%): " . number_format($tax, 0, ',', ' ') . " FCFA</p>";
echo "<p>Total: " . number_format($total_with_tax, 0, ',', ' ') . " FCFA</p>";

// Test database connection
echo "<h2>Database Test:</h2>";
try {
    require_once 'includes/db.php';
    
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Orders table exists</p>";
    } else {
        echo "<p>❌ Orders table missing</p>";
    }
    
    // Check if order_items table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'order_items'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Order_items table exists</p>";
    } else {
        echo "<p>❌ Order_items table missing</p>";
    }
    
    // Check if payments table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'payments'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Payments table exists</p>";
    } else {
        echo "<p>❌ Payments table missing</p>";
    }
    
    // Check products stock
    echo "<h3>Product Stock Check:</h3>";
    foreach ($_SESSION['cart']['products'] as $item) {
        $stmt = $pdo->prepare("SELECT name, stock_quantity FROM products WHERE id = ?");
        $stmt->execute([$item['id']]);
        $product = $stmt->fetch();
        
        if ($product) {
            $status = $product['stock_quantity'] >= $item['quantity'] ? '✅' : '❌';
            echo "<p>$status {$product['name']}: Stock {$product['stock_quantity']}, Required {$item['quantity']}</p>";
        } else {
            echo "<p>❌ Product ID {$item['id']} not found</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test links
echo "<h2>Test Links:</h2>";
echo "<p><a href='public/main_site/basket.php'>View Basket</a></p>";
echo "<p><a href='public/main_site/checkout.php'>Go to Checkout</a></p>";
echo "<p><a href='public/main_site/shop.php'>Continue Shopping</a></p>";

// Test checkout form
echo "<h2>Test Checkout Form:</h2>";
echo "<form method='POST' action='public/main_site/checkout.php' target='_blank'>";
echo "<p><strong>Test Order Form:</strong></p>";
echo "<p>Name: <input type='text' name='name' value='Test User' required></p>";
echo "<p>Email: <input type='email' name='email' value='test@example.com' required></p>";
echo "<p>Phone: <input type='tel' name='phone' value='+233123456789' required></p>";
echo "<p>Address: <textarea name='address' required>123 Test Street, Test City</textarea></p>";
echo "<p>City: <input type='text' name='city' value='Accra' required></p>";
echo "<p>Postal Code: <input type='text' name='postal_code' value='00233' required></p>";
echo "<p>Payment Method: <select name='payment_method' required>";
echo "<option value='cash_on_delivery'>Cash on Delivery</option>";
echo "<option value='bank_transfer'>Bank Transfer</option>";
echo "<option value='mobile_money'>Mobile Money</option>";
echo "</select></p>";
echo "<button type='submit'>Test Place Order</button>";
echo "</form>";

echo "<h2>Instructions:</h2>";
echo "<ol>";
echo "<li>Click 'Go to Checkout' to test the checkout process</li>";
echo "<li>Fill in the checkout form with test data</li>";
echo "<li>Submit the order to test the complete flow</li>";
echo "<li>Check if you're redirected to the success page</li>";
echo "<li>Verify that the cart is cleared after successful order</li>";
echo "</ol>";

echo "<h2>Expected Results:</h2>";
echo "<ul>";
echo "<li>✅ Checkout page should load with cart items</li>";
echo "<li>✅ Form should validate required fields</li>";
echo "<li>✅ Order should be created in database</li>";
echo "<li>✅ Stock should be reduced for products</li>";
echo "<li>✅ Cart should be cleared after successful order</li>";
echo "<li>✅ User should be redirected to success page</li>";
echo "</ul>";
?>























