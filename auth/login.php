<?php
session_start();
require_once '../includes/i18n.php';
require_once '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Redirection selon rôle
        switch ($user['role']) {
            case 'admin': header("Location: ../admin/index.php"); break;
            case 'instructor': header("Location: ../instructor/index.php"); break;
            case 'student': header("Location: ../student/index.php"); break;
            case 'vendor': header("Location: ../vendor/index.php"); break;
        }
    } else {
        echo "Identifiants invalides.";
    }
}
?>


<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('login') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to right, #ffb300, #009688);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .login-container {
            background-color: #fff;
            padding: 3rem 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .login-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .login-container form {
            display: flex;
            flex-direction: column;
        }
        .login-container input {
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        .login-container button {
            padding: 0.9rem;
            background-color: #009688;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .login-container button:hover {
            background-color: #00796b;
        }
        .login-container .link {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }
        .login-container .link a {
            color: #009688;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2><?= __('login') ?></h2>
            <?php include '../includes/public_language_switcher.php'; ?>
        </div>
        <form method="POST" action="login_action.php">
            <input type="email" name="email" placeholder="<?= __('email') ?>" required>
            <input type="password" name="password" placeholder="<?= __('password') ?>" required>
            <button type="submit"><?= __('se_connecter') ?></button>
        </form>
        <div class="link">
            <?= __('pas_de_compte') ?> <a href="register.php"><?= __('s_inscrire') ?></a>
        </div>
    </div>
</body>
</html>
