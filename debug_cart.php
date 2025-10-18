<?php
// Debug script to test cart functionality
session_start();

echo "<h1>Cart Debug Information</h1>";

// Check if cart exists
if (!isset($_SESSION['cart'])) {
    echo "<p>❌ Cart not initialized</p>";
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
    echo "<p>✅ Cart initialized</p>";
} else {
    echo "<p>✅ Cart exists</p>";
}

// Display current cart contents
echo "<h2>Current Cart Contents:</h2>";
echo "<pre>";
print_r($_SESSION['cart']);
echo "</pre>";

// Test adding a product manually
if (isset($_GET['test_add'])) {
    $test_product_id = 1; // Change this to an actual product ID from your database
    
    $_SESSION['cart']['products'][$test_product_id] = [
        'id' => $test_product_id,
        'name' => 'Test Product',
        'price' => 5000,
        'image_url' => 'test.jpg',
        'quantity' => 1,
        'type' => 'product'
    ];
    
    echo "<p>✅ Test product added to cart</p>";
    echo "<p><a href='debug_cart.php'>Refresh to see updated cart</a></p>";
}

// Test clearing cart
if (isset($_GET['test_clear'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
    echo "<p>✅ Cart cleared</p>";
    echo "<p><a href='debug_cart.php'>Refresh to see updated cart</a></p>";
}

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

echo "<h2>Cart Statistics:</h2>";
echo "<p>Total items: $total_items</p>";
echo "<p>Total price: " . number_format($total_price, 0, ',', ' ') . " FCFA</p>";
echo "<p>Products in cart: " . count($_SESSION['cart']['products']) . "</p>";
echo "<p>Courses in cart: " . count($_SESSION['cart']['courses']) . "</p>";

// Test links
echo "<h2>Test Actions:</h2>";
echo "<p><a href='debug_cart.php?test_add=1'>Add Test Product</a></p>";
echo "<p><a href='debug_cart.php?test_clear=1'>Clear Cart</a></p>";
echo "<p><a href='public/main_site/basket.php'>View Basket</a></p>";
echo "<p><a href='public/main_site/shop.php'>Go to Shop</a></p>";

// Check if add_to_cart.php exists and is accessible
echo "<h2>File Checks:</h2>";
$files_to_check = [
    'public/main_site/add_to_cart.php',
    'public/main_site/add_course_to_cart.php',
    'public/main_site/basket.php',
    'public/main_site/shop.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p>✅ $file exists</p>";
    } else {
        echo "<p>❌ $file missing</p>";
    }
}

// Test database connection
echo "<h2>Database Test:</h2>";
try {
    require_once 'includes/db.php';
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $product_count = $stmt->fetchColumn();
    echo "<p>✅ Database connected. Active products: $product_count</p>";
    
    // Show some sample products
    $stmt = $pdo->query("SELECT id, name, price FROM products WHERE status = 'active' LIMIT 5");
    $products = $stmt->fetchAll();
    echo "<h3>Sample Products:</h3>";
    foreach ($products as $product) {
        echo "<p>ID: {$product['id']}, Name: {$product['name']}, Price: {$product['price']} FCFA</p>";
    }
} catch (Exception $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}

echo "<h2>Session Information:</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Session Status: " . session_status() . "</p>";
?>























