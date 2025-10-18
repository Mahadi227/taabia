<?php

/**
 * Language Switching Handler
 * Handles language switching requests and redirects
 * Note: This file assumes session is already started by the calling script
 */

// Handle language switching
if (isset($_GET['lang']) && !empty($_GET['lang'])) {
    // Ensure session is started (should be done by session.php)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $requested_lang = $_GET['lang'];

    // Available languages
    $available_languages = ['fr', 'en'];

    // Validate the requested language
    if (in_array($requested_lang, $available_languages)) {
        // Set the language in session
        $_SESSION['user_language'] = $requested_lang;

        // Update database if user is logged in
        if (isset($_SESSION['user_id'])) {
            try {
                require_once __DIR__ . '/db.php';
                $stmt = $pdo->prepare("UPDATE users SET language_preference = ? WHERE id = ?");
                $stmt->execute([$requested_lang, $_SESSION['user_id']]);
            } catch (Exception $e) {
                error_log("Failed to update user language preference: " . $e->getMessage());
            }
        }

        // Get the current URL without language parameter
        $current_url = $_SERVER['REQUEST_URI'];
        $current_url = preg_replace('/[?&]lang=[^&]*/', '', $current_url);

        // Remove trailing ? if it exists
        $current_url = rtrim($current_url, '?');

        // Redirect to remove the language parameter from URL and reload with new language
        header("Location: " . $current_url);
        exit();
    }
}
