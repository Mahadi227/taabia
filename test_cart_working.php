<?php
// Simple test to verify cart is working
session_start();

echo "<h1>Cart Working Test</h1>";

// Initialize cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
}

// Add a test product
$_SESSION['cart']['products'][1] = [
    'id' => 1,
    'name' => 'Test Product',
    'price' => 5000,
    'image_url' => 'test.jpg',
    'quantity' => 1,
    'type' => 'product'
];

echo "<p>✅ Test product added to cart</p>";

// Display cart contents
echo "<h2>Cart Contents:</h2>";
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

echo "<h2>Cart Summary:</h2>";
echo "<p>Total items: $total_items</p>";
echo "<p>Total price: " . number_format($total_price, 0, ',', ' ') . " FCFA</p>";

echo "<h2>Test Links:</h2>";
echo "<p><a href='public/main_site/basket.php' target='_blank'>View Basket</a></p>";
echo "<p><a href='public/main_site/shop.php' target='_blank'>Go to Shop</a></p>";
echo "<p><a href='test_add_to_cart.php' target='_blank'>Test Add to Cart</a></p>";

echo "<h2>Instructions:</h2>";
echo "<ol>";
echo "<li>Click 'View Basket' to see if the product appears in the basket</li>";
echo "<li>Click 'Go to Shop' to test adding products from the shop</li>";
echo "<li>Click 'Test Add to Cart' to test the add_to_cart.php functionality</li>";
echo "</ol>";

echo "<h2>Expected Results:</h2>";
echo "<ul>";
echo "<li>✅ Basket should show the test product</li>";
echo "<li>✅ Shop should allow adding products to cart</li>";
echo "<li>✅ Cart count should update in navigation</li>";
echo "<li>✅ Products should persist in cart between pages</li>";
echo "</ul>";
?>























