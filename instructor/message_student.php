<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$student_id = $_GET['id'] ?? null;

if (!$student_id) {
    header('Location: students.php');
    exit;
}

// Get student information
$stmt = $pdo->prepare("
    SELECT u.id, u.fullname, u.email 
    FROM users u 
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header('Location: students.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject && $message) {
        // Insert message into database
        $stmt = $pdo->prepare("
            INSERT INTO messages (sender_id, receiver_id, subject, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$instructor_id, $student_id, $subject, $message]);

        header('Location: students.php?message_sent=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer un message | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .card {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }

        .card h2 {
            color: #2d3748;
            margin-bottom: 1rem;
            font-size: 1.8rem;
            font-weight: 700;
            text-align: center;
        }

        .card p {
            color: #718096;
            margin-bottom: 2rem;
            line-height: 1.6;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            min-height: 120px;
            resize: vertical;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
            text-align: center;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-2px);
        }

        .student-info {
            background: #f7fafc;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .student-info h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .student-info p {
            color: #718096;
            margin: 0;
        }
    </style>
</head>

<body>
    <div class="card">
        <h2><i class="fas fa-envelope"></i> Envoyer un message</h2>

        <div class="student-info">
            <h3><i class="fas fa-user"></i> <?= htmlspecialchars($student['fullname'] ?: 'Étudiant') ?></h3>
            <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($student['email']) ?></p>
        </div>

        <form method="post">
            <div class="form-group">
                <label for="subject">Sujet :</label>
                <input type="text"
                    name="subject"
                    id="subject"
                    placeholder="Sujet du message"
                    required>
            </div>

            <div class="form-group">
                <label for="message">Message :</label>
                <textarea name="message"
                    id="message"
                    placeholder="Tapez votre message ici..."
                    required></textarea>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Envoyer le message
            </button>

            <a href="students.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour aux étudiants
            </a>
        </form>
    </div>
</body>

</html>