<?php
// Test script to verify add_to_cart.php functionality
session_start();

echo "<h1>Add to Cart Test</h1>";

// Test 1: Direct POST to add_to_cart.php
echo "<h2>Test 1: Direct POST to add_to_cart.php</h2>";

// Simulate a POST request to add_to_cart.php
$post_data = [
    'product_id' => 1,
    'type' => 'product'
];

// Create a cURL request to test add_to_cart.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/workstation/taabia/public/main_site/add_to_cart.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIE, 'PHPSESSID=' . session_id());

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Code: $http_code</p>";
echo "<p>Response: $response</p>";

// Test 2: Check current cart
echo "<h2>Test 2: Current Cart Contents</h2>";
echo "<pre>";
print_r($_SESSION['cart'] ?? 'No cart');
echo "</pre>";

// Test 3: Manual cart addition
echo "<h2>Test 3: Manual Cart Addition</h2>";
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
}

// Add a test product manually
$_SESSION['cart']['products'][1] = [
    'id' => 1,
    'name' => 'Test Product',
    'price' => 5000,
    'image_url' => 'test.jpg',
    'quantity' => 1,
    'type' => 'product'
];

echo "<p>✅ Test product added manually</p>";

// Test 4: Check cart after manual addition
echo "<h2>Test 4: Cart After Manual Addition</h2>";
echo "<pre>";
print_r($_SESSION['cart']);
echo "</pre>";

// Test 5: Test basket.php
echo "<h2>Test 5: Basket.php Test</h2>";
echo "<p><a href='public/main_site/basket.php' target='_blank'>Open Basket</a></p>";

// Test 6: Check if add_to_cart.php file exists and is readable
echo "<h2>Test 6: File Check</h2>";
$add_to_cart_file = 'public/main_site/add_to_cart.php';
if (file_exists($add_to_cart_file)) {
    echo "<p>✅ $add_to_cart_file exists</p>";
    if (is_readable($add_to_cart_file)) {
        echo "<p>✅ $add_to_cart_file is readable</p>";
    } else {
        echo "<p>❌ $add_to_cart_file is not readable</p>";
    }
} else {
    echo "<p>❌ $add_to_cart_file does not exist</p>";
}

// Test 7: Check database connection
echo "<h2>Test 7: Database Test</h2>";
try {
    require_once 'includes/db.php';
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $product_count = $stmt->fetchColumn();
    echo "<p>✅ Database connected. Active products: $product_count</p>";
    
    if ($product_count > 0) {
        $stmt = $pdo->query("SELECT id, name, price, stock_quantity FROM products WHERE status = 'active' LIMIT 3");
        $products = $stmt->fetchAll();
        echo "<h3>Available Products:</h3>";
        foreach ($products as $product) {
            echo "<p>ID: {$product['id']}, Name: {$product['name']}, Price: {$product['price']} FCFA, Stock: {$product['stock_quantity']}</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

// Test 8: Session information
echo "<h2>Test 8: Session Information</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";
echo "<p>Session Save Path: " . session_save_path() . "</p>";

// Test 9: Simple form to test add to cart
echo "<h2>Test 9: Simple Add to Cart Form</h2>";
echo "<form method='POST' action='public/main_site/add_to_cart.php' target='_blank'>";
echo "<input type='hidden' name='product_id' value='1'>";
echo "<input type='hidden' name='type' value='product'>";
echo "<button type='submit'>Add Product ID 1 to Cart</button>";
echo "</form>";

echo "<h2>Debug Links:</h2>";
echo "<p><a href='debug_cart.php'>Debug Cart</a></p>";
echo "<p><a href='public/main_site/shop.php'>Go to Shop</a></p>";
echo "<p><a href='public/main_site/basket.php'>View Basket</a></p>";
?>























