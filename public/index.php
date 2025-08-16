<?php
session_start();

// Si l'utilisateur est connecté, redirige selon son rôle
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: ../admin/index.php");
            exit;
        case 'instructor':
            header("Location: ../instructor/index.php");
            exit;
        case 'student':
            header("Location: ../student/index.php");
            exit;
        case 'vendor':
            header("Location: ../vendor/index.php");
            exit;
        default:
            session_destroy();
            header("Location: auth/login.php");
            exit;
    }
} else {
    // Si utilisateur non connecté, redirige vers le site public principal
    header("Location: main_site/index.php");
    exit;
}
