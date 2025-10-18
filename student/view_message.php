<?php

/**
 * View Message - Detailed message view
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$message_id = $_GET['id'] ?? 0;
$student_id = $_SESSION['user_id'];

if (!$message_id) {
    header('Location: messages.php');
    exit;
}

try {
    // Get message
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name AS sender_name, u.email AS sender_email, u.role AS sender_role
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE m.id = ? AND m.receiver_id = ?
    ");
    $stmt->execute([$message_id, $student_id]);
    $message = $stmt->fetch();

    if (!$message) {
        $_SESSION['error_message'] = __('message_not_found') ?? 'Message introuvable';
        header('Location: messages.php');
        exit;
    }

    // Mark as read
    try {
        $update_queries = [
            "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?",
            "UPDATE messages SET read_at = NOW() WHERE id = ?",
            "UPDATE messages SET status = 'read' WHERE id = ?"
        ];

        foreach ($update_queries as $query) {
            try {
                $stmt_update = $pdo->prepare($query);
                $stmt_update->execute([$message_id]);
                break;
            } catch (PDOException $e) {
                continue;
            }
        }
    } catch (PDOException $e) {
        error_log("Error marking message read: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Error in view_message: " . $e->getMessage());
    header('Location: messages.php');
    exit;
}

// Handle reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_content = trim($_POST['reply_content']);

    if (!empty($reply_content)) {
        try {
            $reply_subject = 'Re: ' . $message['subject'];
            $stmt_reply = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, subject, message, created_at, sent_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt_reply->execute([$student_id, $message['sender_id'], $reply_subject, $reply_content]);

            $_SESSION['success_message'] = __('reply_sent') ?? 'Réponse envoyée avec succès';
            header('Location: messages.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error sending reply: " . $e->getMessage());
            $error_message = __('error_sending_reply') ?? 'Erreur lors de l\'envoi de la réponse';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($message['subject']) ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
            padding: 2rem;
        }

        .message-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h1 {
            color: var(--gray-900);
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sender-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .message-content {
            color: var(--gray-700);
            line-height: 1.8;
            font-size: 1.05rem;
            white-space: pre-wrap;
        }

        .reply-section {
            padding: 2rem;
            background: var(--gray-50);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            resize: vertical;
            min-height: 120px;
            font-size: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
    </style>
</head>

<body>
    <div class="message-container">
        <a href="messages.php" style="display: inline-flex; align-items: center; gap: 0.5rem; color: white; text-decoration: none; margin-bottom: 1.5rem; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> <?= __('back_to_messages') ?? 'Retour aux messages' ?>
        </a>

        <!-- Message Card -->
        <div class="card">
            <div class="card-header">
                <h1><?= htmlspecialchars($message['subject']) ?></h1>

                <div class="sender-info">
                    <div class="sender-avatar">
                        <?= strtoupper(substr($message['sender_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--gray-900);">
                            <?= htmlspecialchars($message['sender_name'] ?? 'Inconnu') ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--gray-600);">
                            <?= htmlspecialchars($message['sender_email'] ?? '') ?> •
                            <?= ucfirst($message['sender_role'] ?? 'User') ?>
                        </div>
                        <div style="font-size: 0.875rem; color: var(--gray-600); margin-top: 0.25rem;">
                            <i class="fas fa-clock"></i>
                            <?= date('d F Y à H:i', strtotime($message['created_at'] ?? $message['sent_at'])) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="message-content">
                    <?= nl2br(htmlspecialchars($message['message'] ?? $message['content'] ?? '')) ?>
                </div>
            </div>

            <!-- Reply Section -->
            <div class="reply-section">
                <h3 style="margin-bottom: 1.5rem; color: var(--gray-900);">
                    <i class="fas fa-reply"></i> <?= __('reply') ?? 'Répondre' ?>
                </h3>

                <form method="POST">
                    <div class="form-group">
                        <label><?= __('your_reply') ?? 'Votre Réponse' ?></label>
                        <textarea name="reply_content" required
                            placeholder="<?= __('write_reply') ?? 'Écrivez votre réponse...' ?>"></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" name="send_reply" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> <?= __('send_reply') ?? 'Envoyer la réponse' ?>
                        </button>
                        <a href="messages.php" class="btn btn-secondary">
                            <?= __('cancel') ?? 'Annuler' ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>