<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$course_id = intval($_POST['course_id'] ?? 0);

if (!$course_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit;
}

try {
    // Get course details with instructor name
    $query = "SELECT c.*, u.full_name AS instructor_name FROM courses c LEFT JOIN users u ON c.instructor_id = u.id WHERE c.id = ? AND c.status = 'published'";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if (!$course) {
        echo json_encode(['success' => false, 'message' => 'Course not found or not available']);
        exit;
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = ['products' => [], 'courses' => []];
    }
    
    // Check if course already in cart
    if (isset($_SESSION['cart']['courses'][$course_id])) {
        echo json_encode(['success' => false, 'message' => 'Course already in cart']);
        exit;
    }
    
    // Add course to cart with course_id as key
    $_SESSION['cart']['courses'][$course_id] = [
        'id' => $course['id'],
        'title' => $course['title'],
        'price' => $course['price'],
        'image_url' => $course['image_url'],
        'instructor_name' => $course['instructor_name'],
        'type' => 'course'
    ];
    
    // Calculate cart count: sum product quantities + number of courses
    $total_items = 0;
    foreach ($_SESSION['cart']['products'] as $productItem) {
        $total_items += (int)($productItem['quantity'] ?? 0);
    }
    $total_items += count($_SESSION['cart']['courses']);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Course added to cart successfully',
        'cart_count' => $total_items
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>


