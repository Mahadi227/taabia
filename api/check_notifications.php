<?php
// API endpoint to check for new notifications
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

require_once '../includes/db.php';

try {
    $user_id = $_SESSION['user_id'];

    // Count unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM community_notifications 
        WHERE user_id = ? AND read_at IS NULL
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
} catch (PDOException $e) {
    error_log("Database error in check_notifications.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}






