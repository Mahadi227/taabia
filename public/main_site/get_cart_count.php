<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Start session
session_start();

header('Content-Type: application/json');

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
}

// Calculate total items in cart
$total_items = 0;

// Count products
foreach ($_SESSION['cart']['products'] as $item) {
    $total_items += $item['quantity'];
}

// Count courses
$total_items += count($_SESSION['cart']['courses']);

echo json_encode([
    'success' => true,
    'cart_count' => $total_items
]);
?>























