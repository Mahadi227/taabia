<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php'; // pour redirect()

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Recherche de l'utilisateur
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Vérification mot de passe
    if ($user && password_verify($password, $user['password'])) {
        // Démarrage session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'] ?? ''; // facultatif

        // Redirection selon rôle
        switch ($user['role']) {
            case 'admin':
                redirect('../admin/index.php');
                break;
            case 'instructor':
                redirect('../instructor/index.php');
                break;
            case 'student':
                redirect('../student/index.php');
                break;
            case 'vendor':
                redirect('../vendor/index.php');
                break;
            default:
                session_destroy();
                redirect('login.php'); // rôle inconnu
        }
    } else {
        // Échec d'authentification
        echo "<script>alert('Email ou mot de passe incorrect');window.location='login.php';</script>";
        exit;
    }
}
