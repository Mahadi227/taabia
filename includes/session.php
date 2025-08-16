<?php
session_start();

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
        
        if ($user_lang && in_array($user_lang, ['fr', 'en'])) {
            setLanguage($user_lang);
        }
    } catch (Exception $e) {
        // Silently fail if database is not available
        error_log("Failed to load user language preference: " . $e->getMessage());
    }
}

// Ce fichier se contente de vérifier que l'utilisateur est connecté.
// Il NE fait PAS de redirection vers un rôle spécifique ici.
// Chaque page décidera de son propre contrôle d'accès via has_role() ou require_role()
?>
