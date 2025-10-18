<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize internationalization system
require_once __DIR__ . '/i18n.php';

// Vérifie si l'utilisateur est connecté
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../../auth/login.php");
    exit();
}

// Load user language preference from database if available
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/db.php';
        $stmt = $pdo->prepare("SELECT language_preference FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_lang = $stmt->fetchColumn();

        // Only set language from database if no session language is set
        if ($user_lang && in_array($user_lang, ['fr', 'en']) && !isset($_SESSION['user_language'])) {
            $_SESSION['user_language'] = $user_lang;
        }
    } catch (Exception $e) {
        // Silently fail if database is not available
        error_log("Failed to load user language preference: " . $e->getMessage());
    }
}

// Initialize language system
require_once __DIR__ . '/i18n.php';

// Ce fichier se contente de vérifier que l'utilisateur est connecté.
// Il NE fait PAS de redirection vers un rôle spécifique ici.
// Chaque page décidera de son propre contrôle d'accès via has_role() ou require_role()
