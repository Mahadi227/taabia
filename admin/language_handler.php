<?php

/**
 * Admin Language Handler
 * Handles language switching specifically for admin panel
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle language switching
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $new_lang = $_GET['lang'];

    // Store in session
    $_SESSION['user_language'] = $new_lang;

    // Update database if user is logged in
    if (isset($_SESSION['user_id'])) {
        try {
            require_once '../includes/db.php';
            $stmt = $pdo->prepare("UPDATE users SET language_preference = ? WHERE id = ?");
            $stmt->execute([$new_lang, $_SESSION['user_id']]);
        } catch (Exception $e) {
            error_log("Failed to update user language preference: " . $e->getMessage());
        }
    }

    // Redirect to remove the lang parameter from URL
    $current_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: " . $current_url);
    exit();
}
