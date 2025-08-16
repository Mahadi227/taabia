<?php
session_start();

require_once '../includes/function.php';

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accès refusé | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9f9f9;
            margin: 0;
            padding: 0;
            display: flex;
            height: 100vh;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .box {
            background-color: #fff;
            padding: 3rem;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            max-width: 500px;
        }

        h1 {
            font-size: 2rem;
            color: #e53935;
            margin-bottom: 1rem;
        }

        p {
            color: #555;
            margin-bottom: 2rem;
        }

        a {
            display: inline-block;
            text-decoration: none;
            background-color: #009688;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: bold;
            transition: background 0.2s ease-in-out;
        }

        a:hover {
            background-color: #00796b;
        }

        .emoji {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="box">
    <div class="emoji">🚫</div>
    <h1>Accès refusé</h1>
    <p>Vous n’avez pas les droits nécessaires pour accéder à cette page.</p>
    <a href="../auth/login.php">Retour à l'accueil</a>
</div>

</body>
</html>
