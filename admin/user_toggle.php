<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$user_id = $_GET['id'] ?? null;
$action = $_GET['action'] ?? null;

if ($user_id && $action) {
    $user_id = (int) $user_id;
    
    try {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            $new_status = ($action === 'activate') ? 1 : 0;
            $update = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
            $update->execute([$new_status, $user_id]);
        }
    } catch (PDOException $e) {
        error_log("Database error in user_toggle.php: " . $e->getMessage());
    }
}

redirect('users.php');
