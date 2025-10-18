<?php

/**
 * Mark Message as Read
 * AJAX endpoint for marking contact messages as read
 */

header('Content-Type: application/json');

// Start session and include required files
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';

// Check if user is admin
if (!has_role('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !is_numeric($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid message ID']);
    exit;
}

$message_id = (int)$input['id'];

try {
    // Check if message exists
    $stmt = $pdo->prepare("SELECT id FROM contact_messages WHERE id = ?");
    $stmt->execute([$message_id]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Message not found']);
        exit;
    }

    // Update message as read
    $stmt = $pdo->prepare("UPDATE contact_messages SET is_read = 1, read_at = NOW() WHERE id = ?");
    $stmt->execute([$message_id]);

    echo json_encode(['success' => true, 'message' => 'Message marked as read']);
} catch (PDOException $e) {
    error_log("Database error in mark_message_read.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}



