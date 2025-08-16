<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_id = (int)$_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $content = trim($_POST['content']);
    
    $errors = [];
    
    if (empty($subject)) {
        $errors[] = "Le sujet est requis.";
    }
    
    if (empty($content)) {
        $errors[] = "Le contenu du message est requis.";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, subject, content, sent_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$student_id, $recipient_id, $subject, $content]);
            
            flash_message("Message envoyé avec succès !", 'success');
            header('Location: messages.php');
            exit;
        } catch (PDOException $e) {
            error_log("Database error sending message: " . $e->getMessage());
            $errors[] = "Une erreur est survenue lors de l'envoi du message.";
        }
    }
}

// Handle message marking as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $message_id = (int)$_POST['message_id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND receiver_id = ?");
        $stmt->execute([$message_id, $student_id]);
    } catch (PDOException $e) {
        error_log("Database error marking message read: " . $e->getMessage());
    }
    
    header('Location: messages.php');
    exit;
}

// Search and filter functionality
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Build the query with filters
$where_conditions = ["m.receiver_id = ?"];
$params = [$student_id];

if (!empty($search)) {
    $where_conditions[] = "(m.subject LIKE ? OR m.content LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter !== 'all') {
    switch ($filter) {
        case 'unread':
            $where_conditions[] = "m.is_read = 0";
            break;
        case 'read':
            $where_conditions[] = "m.is_read = 1";
            break;
        case 'instructors':
            $where_conditions[] = "u.role = 'instructor'";
            break;
        case 'admins':
            $where_conditions[] = "u.role = 'admin'";
            break;
    }
}

$where_clause = implode(' AND ', $where_conditions);

// Order by clause
$order_clause = match($sort_by) {
    'recent' => 'm.sent_at DESC',
    'oldest' => 'm.sent_at ASC',
    'sender' => 'u.full_name ASC',
    'subject' => 'm.subject ASC',
    default => 'm.sent_at DESC'
};

try {
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name AS sender_name, u.role AS sender_role, u.email AS sender_email,
               CASE WHEN m.is_read = 1 THEN 'read' ELSE 'unread' END as read_status
        FROM messages m
        LEFT JOIN users u ON m.sender_id = u.id
        WHERE $where_clause
        ORDER BY $order_clause
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();

    // Get statistics
    $total_messages = count($messages);
    $unread_messages = array_filter($messages, fn($m) => $m['is_read'] == 0);
    $instructor_messages = array_filter($messages, fn($m) => $m['sender_role'] == 'instructor');
    $admin_messages = array_filter($messages, fn($m) => $m['sender_role'] == 'admin');

    // Get instructors for new message
    $stmtInstructors = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name, u.email, 
               (SELECT COUNT(*) FROM courses WHERE instructor_id = u.id) as course_count
        FROM users u
        JOIN courses c ON u.id = c.instructor_id
        JOIN student_courses sc ON c.id = sc.course_id
        WHERE u.role = 'instructor' AND sc.student_id = ?
        ORDER BY u.full_name
    ");
    $stmtInstructors->execute([$student_id]);
    $instructors = $stmtInstructors->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in messages: " . $e->getMessage());
    $messages = [];
    $total_messages = 0;
    $unread_messages = [];
    $instructor_messages = [];
    $admin_messages = [];
    $instructors = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | TaaBia</title>
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
                <p>Espace Apprenant</p>
            </div>
            
            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    Découvrir les cours
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
                <a href="course_lessons.php" class="student-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Mes leçons
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Mes achats
                </a>
                <a href="messages.php" class="student-nav-item active">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="profile.php" class="student-nav-item">
                    <i class="fas fa-user"></i>
                    Mon profil
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1>Messages</h1>
                        <p>Communiquez avec vos instructeurs et l'équipe</p>
                    </div>
                    <div>
                        <button onclick="openNewMessageModal()" class="student-btn student-btn-primary">
                            <i class="fas fa-plus"></i>
                            Nouveau message
                        </button>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="student-cards" style="margin-bottom: var(--spacing-6);">
                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon primary">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Total des messages</div>
                    <div class="student-card-value"><?= $total_messages ?></div>
                    <div class="student-card-description">Tous vos messages</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon warning">
                            <i class="fas fa-envelope-open"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Non lus</div>
                    <div class="student-card-value"><?= count($unread_messages) ?></div>
                    <div class="student-card-description">Messages non lus</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon success">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Instructeurs</div>
                    <div class="student-card-value"><?= count($instructor_messages) ?></div>
                    <div class="student-card-description">Messages d'instructeurs</div>
                </div>

                <div class="student-card">
                    <div class="student-card-header">
                        <div class="student-card-icon info">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                    <div class="student-card-title">Administration</div>
                    <div class="student-card-value"><?= count($admin_messages) ?></div>
                    <div class="student-card-description">Messages de l'équipe</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="student-card" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6);">
                    <form method="GET" style="display: flex; gap: var(--spacing-4); align-items: end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                                Rechercher
                            </label>
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="Rechercher dans les messages..." 
                                   style="width: 100%; padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px;">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                                Filtrer
                            </label>
                            <select name="filter" style="padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px; min-width: 150px;">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Tous les messages</option>
                                <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Non lus</option>
                                <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Lus</option>
                                <option value="instructors" <?= $filter === 'instructors' ? 'selected' : '' ?>>Instructeurs</option>
                                <option value="admins" <?= $filter === 'admins' ? 'selected' : '' ?>>Administration</option>
                            </select>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                                Trier par
                            </label>
                            <select name="sort" style="padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px; min-width: 150px;">
                                <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Plus récents</option>
                                <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Plus anciens</option>
                                <option value="sender" <?= $sort_by === 'sender' ? 'selected' : '' ?>>Expéditeur</option>
                                <option value="subject" <?= $sort_by === 'subject' ? 'selected' : '' ?>>Sujet</option>
                            </select>
                        </div>
                        
                        <div>
                            <button type="submit" class="student-btn student-btn-primary" style="padding: 0.75rem 1.5rem;">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Messages List -->
            <div class="student-card">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-list"></i> Messages (<?= $total_messages ?>)
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-4);">
                    <?php if (count($messages) > 0): ?>
                        <?php foreach ($messages as $message): ?>
                            <div style="display: flex; align-items: start; padding: var(--spacing-4); border: 1px solid var(--gray-200); border-radius: 8px; margin-bottom: var(--spacing-3); transition: all 0.3s ease; <?= $message['is_read'] == 0 ? 'background: var(--primary-color); color: white;' : '' ?>">
                                <div style="margin-right: var(--spacing-4);">
                                    <div style="width: 50px; height: 50px; background: <?= $message['is_read'] == 0 ? 'white' : 'var(--gray-200)' ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: <?= $message['is_read'] == 0 ? 'var(--primary-color)' : 'var(--gray-600)' ?>;">
                                        <i class="fas fa-<?= $message['sender_role'] === 'instructor' ? 'chalkboard-teacher' : ($message['sender_role'] === 'admin' ? 'user-shield' : 'user') ?>"></i>
                                    </div>
                                </div>
                                
                                <div style="flex: 1;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                        <div>
                                            <div style="font-weight: 600; margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($message['sender_name']) ?>
                                                <span style="font-size: 0.75rem; opacity: 0.7; margin-left: 0.5rem;">
                                                    (<?= ucfirst($message['sender_role']) ?>)
                                                </span>
                                            </div>
                                            <div style="font-weight: 500; margin-bottom: 0.25rem;">
                                                <?= htmlspecialchars($message['subject']) ?>
                                            </div>
                                        </div>
                                        <div style="text-align: right;">
                                            <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.25rem;">
                                                <?= date('d/m/Y H:i', strtotime($message['sent_at'])) ?>
                                            </div>
                                            <?php if ($message['is_read'] == 0): ?>
                                                <span class="student-badge" style="background: white; color: var(--primary-color);">Nouveau</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div style="font-size: 0.875rem; opacity: 0.8; margin-bottom: 0.5rem; line-height: 1.5;">
                                        <?= htmlspecialchars(substr($message['content'], 0, 150)) ?>...
                                    </div>
                                    
                                    <div style="display: flex; gap: var(--spacing-3);">
                                        <button onclick="viewMessage(<?= $message['id'] ?>)" class="student-btn student-btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                            <i class="fas fa-eye"></i> Lire
                                        </button>
                                        
                                        <?php if ($message['is_read'] == 0): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="message_id" value="<?= $message['id'] ?>">
                                                <button type="submit" name="mark_read" class="student-btn student-btn-success" style="padding: 0.5rem 1rem; font-size: 0.875rem;">
                                                    <i class="fas fa-check"></i> Marquer comme lu
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-8); color: var(--gray-500);">
                            <i class="fas fa-envelope" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Aucun message trouvé.</p>
                            <button onclick="openNewMessageModal()" class="student-btn student-btn-primary">
                                <i class="fas fa-plus"></i> Envoyer un message
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Message Modal -->
    <div id="newMessageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: var(--spacing-6); width: 90%; max-width: 600px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-6);">
                <h3 style="margin: 0; color: var(--gray-900);">Nouveau message</h3>
                <button onclick="closeNewMessageModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-500);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST">
                <div style="margin-bottom: var(--spacing-4);">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                        Destinataire *
                    </label>
                    <select name="recipient_id" required style="width: 100%; padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px;">
                        <option value="">Sélectionner un destinataire</option>
                        <?php foreach ($instructors as $instructor): ?>
                            <option value="<?= $instructor['id'] ?>">
                                <?= htmlspecialchars($instructor['full_name']) ?> (<?= $instructor['course_count'] ?> cours)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: var(--spacing-4);">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                        Sujet *
                    </label>
                    <input type="text" name="subject" required placeholder="Sujet du message" 
                           style="width: 100%; padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px;">
                </div>
                
                <div style="margin-bottom: var(--spacing-6);">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--gray-700);">
                        Message *
                    </label>
                    <textarea name="content" required placeholder="Contenu de votre message..." rows="6"
                              style="width: 100%; padding: 0.75rem; border: 2px solid var(--gray-200); border-radius: 8px; resize: vertical;"></textarea>
                </div>
                
                <div style="display: flex; gap: var(--spacing-3); justify-content: flex-end;">
                    <button type="button" onclick="closeNewMessageModal()" class="student-btn student-btn-secondary">
                        Annuler
                    </button>
                    <button type="submit" name="send_message" class="student-btn student-btn-primary">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Message View Modal -->
    <div id="messageViewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000;">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: var(--spacing-6); width: 90%; max-width: 700px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-6);">
                <h3 style="margin: 0; color: var(--gray-900);">Détails du message</h3>
                <button onclick="closeMessageViewModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--gray-500);">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="messageContent">
                <!-- Message content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function openNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'block';
        }
        
        function closeNewMessageModal() {
            document.getElementById('newMessageModal').style.display = 'none';
        }
        
        function openMessageViewModal() {
            document.getElementById('messageViewModal').style.display = 'block';
        }
        
        function closeMessageViewModal() {
            document.getElementById('messageViewModal').style.display = 'none';
        }
        
        function viewMessage(messageId) {
            // Load message content via AJAX or redirect to a message view page
            window.location.href = `view_message.php?id=${messageId}`;
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to message cards
            const messageCards = document.querySelectorAll('.student-card > div > div > div');
            messageCards.forEach(card => {
                if (!card.style.background.includes('var(--primary-color)')) {
                    card.addEventListener('mouseenter', function() {
                        this.style.transform = 'translateY(-2px)';
                        this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.1)';
                    });
                    
                    card.addEventListener('mouseleave', function() {
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = 'none';
                    });
                }
            });

            // Auto-submit form when filters change
            const filterForm = document.querySelector('form');
            const filterInputs = filterForm.querySelectorAll('select');
            
            filterInputs.forEach(input => {
                input.addEventListener('change', function() {
                    filterForm.submit();
                });
            });

            // Close modals when clicking outside
            window.addEventListener('click', function(event) {
                const newMessageModal = document.getElementById('newMessageModal');
                const messageViewModal = document.getElementById('messageViewModal');
                
                if (event.target === newMessageModal) {
                    closeNewMessageModal();
                }
                if (event.target === messageViewModal) {
                    closeMessageViewModal();
                }
            });
        });
    </script>
</body>
</html>
