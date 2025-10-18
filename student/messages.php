<?php

/**
 * Messages Page - Modern LMS
 * Gmail-style messaging interface
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Handle message actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['send_message'])) {
        $recipient_id = (int)$_POST['recipient_id'];
        $subject = trim($_POST['subject']);
        $content = trim($_POST['content']);

        if (!empty($subject) && !empty($content) && $recipient_id > 0) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO messages (sender_id, receiver_id, subject, message, created_at, sent_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$student_id, $recipient_id, $subject, $content]);

                $_SESSION['success_message'] = __('message_sent') ?? 'Message envoyé avec succès';
                header('Location: messages.php');
                exit;
            } catch (PDOException $e) {
                error_log("Error sending message: " . $e->getMessage());
                $error_message = __('error_sending') ?? 'Erreur lors de l\'envoi';
            }
        }
    }

    if (isset($_POST['mark_read'])) {
        $message_id = (int)$_POST['message_id'];
        try {
            // Try different column names
            $update_queries = [
                "UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND receiver_id = ?",
                "UPDATE messages SET read_at = NOW() WHERE id = ? AND receiver_id = ?",
                "UPDATE messages SET status = 'read' WHERE id = ? AND receiver_id = ?"
            ];

            foreach ($update_queries as $query) {
                try {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$message_id, $student_id]);
                    break;
                } catch (PDOException $e) {
                    continue;
                }
            }
        } catch (PDOException $e) {
            error_log("Error marking read: " . $e->getMessage());
        }

        header('Location: messages.php');
        exit;
    }
}

// Filters
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';

// Build query
$where_conditions = ["m.receiver_id = ?"];
$params = [$student_id];

if (!empty($search)) {
    $where_conditions[] = "(m.subject LIKE ? OR m.message LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filter by read status - try different column names
if ($filter === 'unread') {
    $where_conditions[] = "(m.is_read = 0 OR m.read_at IS NULL OR m.status = 'unread')";
} elseif ($filter === 'read') {
    $where_conditions[] = "(m.is_read = 1 OR m.read_at IS NOT NULL OR m.status = 'read')";
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get messages
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name AS sender_name, u.role AS sender_role
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE $where_clause
        ORDER BY m.created_at DESC, m.sent_at DESC, m.id DESC
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Check read status dynamically
    foreach ($messages as &$msg) {
        if (isset($msg['is_read'])) {
            $msg['is_unread'] = $msg['is_read'] == 0;
        } elseif (isset($msg['read_at'])) {
            $msg['is_unread'] = $msg['read_at'] === null;
        } elseif (isset($msg['status'])) {
            $msg['is_unread'] = $msg['status'] === 'unread';
        } else {
            $msg['is_unread'] = true; // Default to unread
        }
    }
    unset($msg);

    // Statistics
    $total_messages = count($messages);
    $unread_count = count(array_filter($messages, fn($m) => $m['is_unread']));
    $instructor_count = count(array_filter($messages, fn($m) => $m['sender_role'] === 'instructor'));

    // Get instructors for compose
    $stmt_instructors = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name
        FROM users u
        JOIN courses c ON u.id = c.instructor_id
        JOIN student_courses sc ON c.id = sc.course_id
        WHERE u.role = 'instructor' AND sc.student_id = ?
        ORDER BY u.full_name
    ");
    $stmt_instructors->execute([$student_id]);
    $instructors = $stmt_instructors->fetchAll();
} catch (PDOException $e) {
    error_log("Error in messages.php: " . $e->getMessage());
    $messages = [];
    $total_messages = 0;
    $unread_count = 0;
    $instructor_count = 0;
    $instructors = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('messages') ?? 'Messages' ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
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
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            margin-right: 0.75rem;
        }

        .nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), transparent);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
        }

        .form-input,
        .form-select {
            padding: 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Messages */
        .messages-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .message-item {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .message-item:hover {
            background: var(--gray-50);
        }

        .message-item.unread {
            background: rgba(99, 102, 241, 0.05);
            border-left: 4px solid var(--primary);
        }

        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.75rem;
        }

        .sender-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sender-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.125rem;
        }

        .message-subject {
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .message-preview {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.6;
        }

        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-unread {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .badge-instructor {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-admin {
            background: #fef3c7;
            color: #92400e;
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            color: var(--gray-900);
            font-size: 1.5rem;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray-500);
            cursor: pointer;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 150px;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .filters-form {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
            </div>

            <nav class="nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item">
                    <i class="fas fa-compass"></i>
                    <?= __('discover_courses') ?? 'Découvrir' ?>
                </a>
                <a href="course_lessons.php" class="nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?? 'Mes Leçons' ?>
                </a>
                <a href="messages.php" class="nav-item active">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Profil' ?>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><?= __('messages') ?? 'Messages' ?></h1>
                    <p><?= __('communicate_instructors') ?? 'Communiquez avec vos instructeurs' ?></p>
                </div>
                <button onclick="openComposeModal()" class="btn btn-primary">
                    <i class="fas fa-plus"></i> <?= __('compose') ?? 'Nouveau Message' ?>
                </button>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($_SESSION['success_message']) ?></div>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($error_message) ?></div>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stat-label"><?= __('total_messages') ?? 'Total' ?></div>
                    <div class="stat-value"><?= $total_messages ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-envelope-open"></i>
                    </div>
                    <div class="stat-label"><?= __('unread') ?? 'Non Lus' ?></div>
                    <div class="stat-value"><?= $unread_count ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="stat-label"><?= __('instructors') ?? 'Instructeurs' ?></div>
                    <div class="stat-value"><?= $instructor_count ?></div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filters-form">
                    <input type="text" name="search" class="form-input"
                        placeholder="<?= __('search_messages') ?? 'Rechercher dans les messages...' ?>"
                        value="<?= htmlspecialchars($search) ?>">

                    <select name="filter" class="form-select">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>
                            <?= __('all_messages') ?? 'Tous les messages' ?>
                        </option>
                        <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>
                            <?= __('unread') ?? 'Non lus' ?>
                        </option>
                        <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>
                            <?= __('read') ?? 'Lus' ?>
                        </option>
                    </select>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> <?= __('search') ?? 'Rechercher' ?>
                    </button>
                </form>
            </div>

            <!-- Messages List -->
            <div class="messages-container">
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="message-item <?= $message['is_unread'] ? 'unread' : '' ?>"
                            onclick="viewMessage(<?= $message['id'] ?>)">
                            <div class="message-header">
                                <div class="sender-info">
                                    <div class="sender-avatar">
                                        <?= strtoupper(substr($message['sender_name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                                            <?= htmlspecialchars($message['sender_name'] ?? 'Inconnu') ?>
                                        </div>
                                        <div style="font-size: 0.875rem;">
                                            <span class="badge badge-<?= $message['sender_role'] ?? 'student' ?>">
                                                <?= ucfirst($message['sender_role'] ?? 'Student') ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                                        <?= date('d/m/Y H:i', strtotime($message['created_at'] ?? $message['sent_at'])) ?>
                                    </div>
                                    <?php if ($message['is_unread']): ?>
                                        <span class="badge badge-unread">
                                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> <?= __('new') ?? 'Nouveau' ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="message-subject">
                                <?= htmlspecialchars($message['subject']) ?>
                            </div>

                            <div class="message-preview">
                                <?= htmlspecialchars(substr($message['message'] ?? $message['content'] ?? '', 0, 120)) ?>...
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3><?= __('no_messages') ?? 'Aucun message' ?></h3>
                        <p><?= __('inbox_empty') ?? 'Votre boîte de réception est vide' ?></p>
                        <button onclick="openComposeModal()" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> <?= __('send_first_message') ?? 'Envoyer votre premier message' ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Compose Modal -->
    <div id="composeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-pen"></i> <?= __('new_message') ?? 'Nouveau Message' ?></h3>
                <button class="close-btn" onclick="closeComposeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label><?= __('recipient') ?? 'Destinataire' ?> *</label>
                    <select name="recipient_id" required>
                        <option value=""><?= __('select_recipient') ?? 'Sélectionner un destinataire' ?></option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= $instructor['id'] ?>">
                                <?= htmlspecialchars($instructor['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><?= __('subject') ?? 'Sujet' ?> *</label>
                    <input type="text" name="subject" required
                        placeholder="<?= __('message_subject') ?? 'Sujet du message' ?>">
                </div>

                <div class="form-group">
                    <label><?= __('message') ?? 'Message' ?> *</label>
                    <textarea name="content" required
                        placeholder="<?= __('your_message') ?? 'Écrivez votre message ici...' ?>"></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeComposeModal()" class="btn btn-secondary">
                        <?= __('cancel') ?? 'Annuler' ?>
                    </button>
                    <button type="submit" name="send_message" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> <?= __('send') ?? 'Envoyer' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openComposeModal() {
            document.getElementById('composeModal').classList.add('active');
        }

        function closeComposeModal() {
            document.getElementById('composeModal').classList.remove('active');
        }

        function viewMessage(messageId) {
            window.location.href = `view_message.php?id=${messageId}`;
        }

        // Close modal when clicking outside
        document.getElementById('composeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeComposeModal();
            }
        });

        // Auto-submit on filter change
        document.querySelectorAll('.filters-form select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Animate messages
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.message-item');
            messages.forEach((msg, index) => {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    msg.style.transition = 'all 0.5s ease';
                    msg.style.opacity = '1';
                    msg.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>

</html>