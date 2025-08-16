<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('instructor');

// Vérifie que l’étudiant est spécifié
if (!isset($_GET['student_id'])) {
    redirect('students.php');
}

$student_id = (int) $_GET['student_id'];

// Récupère les infos de l'étudiant
$stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("⛔ Étudiant introuvable.");
}

// Traitement de l’envoi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    $to = $student['email'];
    $headers = "From: no-reply@taabia.com\r\n";
    $headers .= "Content-type: text/plain; charset=UTF-8\r\n";

    if (mail($to, $subject, $message, $headers)) {
        echo "<script>alert('✉️ Message envoyé avec succès !'); window.location='students.php';</script>";
    } else {
        echo "<script>alert('❌ Échec de l\'envoi.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Envoyer un message à <?= htmlspecialchars($student['full_name']) ?></title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f4f4f4;
            padding: 2rem;
        }

        form {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 600px;
            margin: auto;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 1rem;
            color: #009688;
        }

        label {
            font-weight: bold;
            margin-top: 1rem;
            display: block;
        }

        input, textarea {
            width: 100%;
            padding: 0.7rem;
            margin-top: 0.3rem;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            margin-top: 1rem;
            padding: 0.8rem 1.5rem;
            background: #009688;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background-color: #00796b;
        }
    </style>
</head>
<body>

<form method="post">
    <h2>Envoyer un message à <?= htmlspecialchars($student['full_name']) ?></h2>

    <label>Objet</label>
    <input type="text" name="subject" required placeholder="Objet de l'email">

    <label>Message</label>
    <textarea name="message" rows="6" required placeholder="Votre message ici..."></textarea>

    <button type="submit">Envoyer</button>
</form>

</body>
</html>
