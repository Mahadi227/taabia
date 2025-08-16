<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$success_message = '';
$error_message = '';

try {
    // Get student information and enrollment details
    $stmt = $pdo->prepare("
        SELECT s.id, s.full_name, s.email, s.created_at as joined_date,
               COUNT(sc.course_id) as enrolled_courses,
               AVG(sc.progress) as avg_progress
        FROM students s
        LEFT JOIN student_courses sc ON s.id = sc.student_id
        LEFT JOIN courses c ON sc.course_id = c.id
        WHERE s.id = ? AND c.instructor_id = ?
        GROUP BY s.id
    ");
    $stmt->execute([$student_id, $instructor_id]);
    $student = $stmt->fetch();

    if (!$student) {
        header('Location: students.php?error=student_not_found');
        exit;
    }

    // Get enrolled courses for this instructor
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, sc.progress, sc.enrolled_at, sc.last_activity
        FROM student_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        WHERE sc.student_id = ? AND c.instructor_id = ?
        ORDER BY sc.enrolled_at DESC
    ");
    $stmt->execute([$student_id, $instructor_id]);
    $enrolled_courses = $stmt->fetchAll();

    // Get recent messages with this student
    $stmt = $pdo->prepare("
        SELECT m.*, 
               CASE 
                   WHEN m.sender_id = ? THEN 'sent'
                   ELSE 'received'
               END as message_type
        FROM messages m
        WHERE (m.sender_id = ? AND m.receiver_id = ?) 
           OR (m.sender_id = ? AND m.receiver_id = ?)
        ORDER BY m.sent_at DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id, $instructor_id, $student_id, $student_id, $instructor_id]);
    $recent_messages = $stmt->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $message_type = $_POST['message_type'] ?? 'general';

        // Validation
        if (empty($subject)) {
            $error_message = "L'objet du message est obligatoire.";
        } elseif (strlen($subject) < 3) {
            $error_message = "L'objet doit contenir au moins 3 caractères.";
        } elseif (empty($message)) {
            $error_message = "Le contenu du message est obligatoire.";
        } elseif (strlen($message) < 10) {
            $error_message = "Le message doit contenir au moins 10 caractères.";
        } else {
            // Insert message
            $stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, receiver_id, subject, message, message_type, sent_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$instructor_id, $student_id, $subject, $message, $message_type]);

            $success_message = "Message envoyé avec succès !";
            
            // Clear form data
            $subject = $message = '';
            $message_type = 'general';
            
            // Refresh recent messages
            $stmt = $pdo->prepare("
                SELECT m.*, 
                       CASE 
                           WHEN m.sender_id = ? THEN 'sent'
                           ELSE 'received'
                       END as message_type
                FROM messages m
                WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                   OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.sent_at DESC
                LIMIT 10
            ");
            $stmt->execute([$instructor_id, $instructor_id, $student_id, $student_id, $instructor_id]);
            $recent_messages = $stmt->fetchAll();
        }
    }

} catch (PDOException $e) {
    error_log("Database error in message_student: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
    $student = null;
    $enrolled_courses = [];
    $recent_messages = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Message à <?= htmlspecialchars($student['full_name'] ?? 'Étudiant') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p>Espace Formateur</p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    Nouveau cours
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="students.php" class="instructor-nav-item active">
                    <i class="fas fa-users"></i>
                    Mes étudiants
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    Devoirs à valider
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    Mes gains
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Transactions
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    Paiements
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <h1>Message à l'étudiant</h1>
                <p>Communiquez avec <?= htmlspecialchars($student['full_name'] ?? 'votre étudiant') ?></p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div style="
                    background: var(--success-color); 
                    color: var(--white); 
                    padding: var(--spacing-4); 
                    border-radius: var(--radius-lg); 
                    margin-bottom: var(--spacing-6);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                ">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="
                    background: var(--danger-color); 
                    color: var(--white); 
                    padding: var(--spacing-4); 
                    border-radius: var(--radius-lg); 
                    margin-bottom: var(--spacing-6);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                ">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Student Information -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-user"></i> Informations de l'étudiant
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: var(--spacing-6);">
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2);">
                                <i class="fas fa-user-circle"></i> <?= htmlspecialchars($student['full_name']) ?>
                            </div>
                            <div style="color: var(--gray-600); margin-bottom: var(--spacing-1);">
                                <?= htmlspecialchars($student['email']) ?>
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Inscrit le <?= date('d/m/Y', strtotime($student['joined_date'])) ?>
                            </div>
                        </div>
                        
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-2);">
                                <i class="fas fa-graduation-cap"></i> Cours suivis
                            </div>
                            <div style="color: var(--gray-600); margin-bottom: var(--spacing-1);">
                                <?= $student['enrolled_courses'] ?> cours
                            </div>
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                Progression moyenne: <?= round($student['avg_progress'] ?? 0, 1) ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrolled Courses -->
            <?php if (count($enrolled_courses) > 0): ?>
                <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-book"></i> Cours suivis
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-course-grid">
                            <?php foreach ($enrolled_courses as $course): ?>
                                <div class="instructor-course-card">
                                    <div class="instructor-course-image">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    
                                    <div class="instructor-course-content">
                                        <h3 class="instructor-course-title">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </h3>
                                        
                                        <div class="instructor-course-stats">
                                            <span>
                                                <i class="fas fa-percentage"></i>
                                                <?= round($course['progress'], 1) ?>% complété
                                            </span>
                                            <span>
                                                <i class="fas fa-calendar"></i>
                                                Inscrit le <?= date('d/m/Y', strtotime($course['enrolled_at'])) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="instructor-course-footer">
                                            <a href="view_student_progress.php?student_id=<?= $student_id ?>&course_id=<?= $course['id'] ?>" 
                                               class="instructor-btn instructor-btn-primary"
                                               style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-chart-line"></i>
                                                Voir la progression
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Message Form -->
            <div class="instructor-form">
                <form method="POST" id="messageForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="subject" class="instructor-form-label">
                                <i class="fas fa-tag"></i> Objet *
                            </label>
                            <input type="text" name="subject" id="subject" required 
                                   class="instructor-form-input" 
                                   placeholder="Sujet de votre message"
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="message_type" class="instructor-form-label">
                                <i class="fas fa-envelope"></i> Type de message
                            </label>
                            <select name="message_type" id="message_type" class="instructor-form-input instructor-form-select">
                                <option value="general" <?= (isset($_POST['message_type']) && $_POST['message_type'] == 'general') ? 'selected' : '' ?>>Général</option>
                                <option value="feedback" <?= (isset($_POST['message_type']) && $_POST['message_type'] == 'feedback') ? 'selected' : '' ?>>Feedback</option>
                                <option value="support" <?= (isset($_POST['message_type']) && $_POST['message_type'] == 'support') ? 'selected' : '' ?>>Support</option>
                                <option value="encouragement" <?= (isset($_POST['message_type']) && $_POST['message_type'] == 'encouragement') ? 'selected' : '' ?>>Encouragement</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="instructor-form-group">
                        <label for="message" class="instructor-form-label">
                            <i class="fas fa-comment"></i> Message *
                        </label>
                        <textarea name="message" id="message" rows="6" required 
                                  class="instructor-form-input instructor-form-textarea" 
                                  placeholder="Tapez votre message ici..."
                                  style="resize: vertical;"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Envoyer le message
                        </button>
                        
                        <a href="students.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                        
                        <div style="margin-left: auto; font-size: var(--font-size-sm); color: var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            Le message sera envoyé immédiatement
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Messages -->
            <?php if (count($recent_messages) > 0): ?>
                <div class="instructor-table-container" style="margin-top: var(--spacing-8);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-history"></i> Messages récents
                        </h3>
                    </div>
                    
                    <div style="padding: var(--spacing-6);">
                        <?php foreach ($recent_messages as $msg): ?>
                            <div style="
                                background: <?= $msg['message_type'] == 'sent' ? 'var(--primary-color)' : 'var(--gray-50)' ?>; 
                                color: <?= $msg['message_type'] == 'sent' ? 'var(--white)' : 'var(--gray-900)' ?>; 
                                padding: var(--spacing-4); 
                                border-radius: var(--radius-lg);
                                margin-bottom: var(--spacing-4);
                            ">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-2);">
                                    <div style="font-weight: 600;">
                                        <?= htmlspecialchars($msg['subject']) ?>
                                    </div>
                                    <span style="
                                        font-size: var(--font-size-xs); 
                                        opacity: 0.8;
                                    ">
                                        <?= date('d/m/Y à H:i', strtotime($msg['sent_at'])) ?>
                                    </span>
                                </div>
                                
                                <div style="
                                    line-height: 1.6;
                                    <?= $msg['message_type'] == 'sent' ? 'opacity: 0.9;' : '' ?>
                                ">
                                    <?= nl2br(htmlspecialchars($msg['message'])) ?>
                                </div>
                                
                                <div style="
                                    margin-top: var(--spacing-2);
                                    font-size: var(--font-size-xs);
                                    opacity: 0.7;
                                ">
                                    <i class="fas fa-<?= $msg['message_type'] == 'sent' ? 'arrow-right' : 'arrow-left' ?>"></i>
                                    <?= $msg['message_type'] == 'sent' ? 'Envoyé' : 'Reçu' ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="students.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux étudiants
                </a>
                
                <a href="view_student_progress.php?student_id=<?= $student_id ?>" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-chart-line"></i>
                    Voir la progression
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('messageForm');
            const subjectInput = document.getElementById('subject');
            const messageInput = document.getElementById('message');
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const subject = subjectInput.value.trim();
                const message = messageInput.value.trim();
                
                if (!subject) {
                    e.preventDefault();
                    alert('Veuillez saisir un objet pour le message.');
                    subjectInput.focus();
                    return;
                }
                
                if (subject.length < 3) {
                    e.preventDefault();
                    alert('L\'objet doit contenir au moins 3 caractères.');
                    subjectInput.focus();
                    return;
                }
                
                if (!message) {
                    e.preventDefault();
                    alert('Veuillez saisir un message.');
                    messageInput.focus();
                    return;
                }
                
                if (message.length < 10) {
                    e.preventDefault();
                    alert('Le message doit contenir au moins 10 caractères.');
                    messageInput.focus();
                    return;
                }
            });
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Character counter for subject
            subjectInput.addEventListener('input', function() {
                const length = this.value.length;
                const maxLength = 100;
                
                if (length > maxLength) {
                    this.style.borderColor = 'var(--danger-color)';
                } else {
                    this.style.borderColor = 'var(--gray-200)';
                }
            });
        });
    </script>
</body>
</html>
