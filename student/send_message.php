<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Get reply information
$reply_to = $_GET['reply_to'] ?? null;
$recipient = $_GET['recipient'] ?? null;

try {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $receiver_id = $_POST['receiver_id'] ?? null;
        $subject = trim($_POST['subject'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($receiver_id && $subject && $content) {
            // Insert into messages table with correct field names
            $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, content, message, sent_at) 
                                   VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$student_id, $receiver_id, $subject, $content, $content]);

            $_SESSION['success_message'] = __('message_sent_successfully');
            header('Location: messages.php');
            exit;
        } else {
            $error = __('all_fields_required');
        }
    }

    // Get instructors list
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email,
               (SELECT COUNT(*) FROM courses WHERE instructor_id = u.id) as course_count
        FROM users u 
        WHERE u.role = 'instructor' AND u.is_active = 1
        ORDER BY u.full_name ASC
    ");
    $stmt->execute();
    $instructors = $stmt->fetchAll();

    // Get reply message if replying
    $reply_message = null;
    if ($reply_to) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.full_name as sender_name
            FROM messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.id = ? AND m.receiver_id = ?
        ");
        $stmt->execute([$reply_to, $student_id]);
        $reply_message = $stmt->fetch();
    }

} catch (PDOException $e) {
    error_log("Database error in send_message: " . $e->getMessage());
    $error = __('database_error');
    $instructors = [];
    $reply_message = null;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('send_message') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?></p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-search"></i>
                    <?= __('browse_courses') ?>
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?>
                </a>
                <a href="send_message.php" class="student-nav-item active">
                    <i class="fas fa-paper-plane"></i>
                    <?= __('send_message') ?>
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_orders') ?>
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?>
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <h1><i class="fas fa-paper-plane"></i> <?= __('send_message') ?></h1>
                <div class="student-header-actions">
                    <a href="messages.php" class="student-btn student-btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('back_to_messages') ?>
                    </a>
                </div>
            </div>

            <div class="student-content">
                <?php if (isset($error)): ?>
                    <div class="student-alert student-alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="student-alert student-alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= htmlspecialchars($_SESSION['success_message']) ?>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <div class="student-card">
                    <div class="student-card-header">
                        <h2><i class="fas fa-edit"></i> <?= __('compose_message') ?></h2>
                    </div>
                    
                    <div class="student-card-body">
                        <?php if ($reply_message): ?>
                            <div class="reply-context">
                                <h4><?= __('replying_to') ?>: <?= htmlspecialchars($reply_message['sender_name']) ?></h4>
                                <div class="original-message">
                                    <strong><?= __('original_message') ?>:</strong>
                                    <p><?= htmlspecialchars($reply_message['content'] ?: $reply_message['message']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="message-form">
                            <div class="form-group">
                                <label for="receiver_id"><?= __('recipient') ?></label>
                                <select name="receiver_id" id="receiver_id" required>
                                    <option value="">-- <?= __('choose_instructor') ?> --</option>
                                    <?php foreach ($instructors as $inst): ?>
                                        <option value="<?= $inst['id'] ?>" 
                                                <?= ($recipient == $inst['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($inst['full_name']) ?> 
                                            (<?= $inst['course_count'] ?> <?= __('courses') ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="subject"><?= __('subject') ?></label>
                                <input type="text" name="subject" id="subject" required 
                                       value="<?= $reply_message ? 'Re: ' . htmlspecialchars($reply_message['subject']) : '' ?>">
                            </div>

                            <div class="form-group">
                                <label for="content"><?= __('message') ?></label>
                                <textarea name="content" id="content" rows="8" required 
                                          placeholder="<?= __('type_your_message_here') ?>"><?= $reply_message ? "\n\n--- " . __('original_message') . " ---\n" . htmlspecialchars($reply_message['content'] ?: $reply_message['message']) : '' ?></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="student-btn student-btn-primary">
                                    <i class="fas fa-paper-plane"></i> <?= __('send_message') ?>
                                </button>
                                <a href="messages.php" class="student-btn student-btn-secondary">
                                    <i class="fas fa-times"></i> <?= __('cancel') ?>
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus on subject field if replying
        <?php if ($reply_message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('content').focus();
        });
        <?php endif; ?>
    </script>
</body>
</html>
