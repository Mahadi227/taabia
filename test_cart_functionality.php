<?php
// Test file to verify cart functionality
session_start();

echo "<h1>Cart Functionality Test</h1>";

// Test 1: Initialize cart
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = ['products' => [], 'courses' => []];
    echo "<p>✅ Cart initialized successfully</p>";
} else {
    echo "<p>✅ Cart already exists</p>";
}

// Test 2: Add a test product
$_SESSION['cart']['products'][1] = [
    'id' => 1,
    'name' => 'Test Product',
    'price' => 5000,
    'image_url' => 'test.jpg',
    'quantity' => 2,
    'type' => 'product'
];

echo "<p>✅ Test product added to cart</p>";

// Test 3: Add a test course
$_SESSION['cart']['courses'][1] = [
    'id' => 1,
    'title' => 'Test Course',
    'price' => 15000,
    'image_url' => 'course.jpg',
    'instructor_name' => 'Test Instructor',
    'type' => 'course'
];

echo "<p>✅ Test course added to cart</p>";

// Test 4: Calculate totals
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

echo "<p>✅ Total items: $total_items</p>";
echo "<p>✅ Total price: $total_price FCFA</p>";

// Test 5: Display cart contents
echo "<h2>Current Cart Contents:</h2>";
echo "<pre>";
print_r($_SESSION['cart']);
echo "</pre>";

// Test 6: Test cart operations
echo "<h2>Test Cart Operations:</h2>";
echo "<p><a href='public/main_site/basket.php'>View Basket</a></p>";
echo "<p><a href='public/main_site/basket.php?add=1&type=product'>Add Product</a></p>";
echo "<p><a href='public/main_site/basket.php?add=1&type=course'>Add Course</a></p>";
echo "<p><a href='public/main_site/basket.php?remove=1&type=product'>Remove Product</a></p>";
echo "<p><a href='public/main_site/basket.php?update=1&quantity=3&type=product'>Update Quantity</a></p>";
echo "<p><a href='public/main_site/basket.php?clear=1'>Clear Cart</a></p>";

echo "<h2>Cart Status:</h2>";
echo "<p>Products in cart: " . count($_SESSION['cart']['products']) . "</p>";
echo "<p>Courses in cart: " . count($_SESSION['cart']['courses']) . "</p>";
echo "<p>Total items: $total_items</p>";
echo "<p>Total price: " . number_format($total_price, 0, ',', ' ') . " FCFA</p>";
?>
