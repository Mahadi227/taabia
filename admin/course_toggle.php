<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($course_id > 0 && in_array($action, ['publish', 'archive'])) {
    try {
        // Check if course exists
        $stmt = $pdo->prepare("SELECT id, title, status FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();
        
        if ($course) {
            $new_status = ($action === 'publish') ? 'published' : 'archived';
            
            // Update course status
            $update = $pdo->prepare("UPDATE courses SET status = ?, updated_at = NOW() WHERE id = ?");
            $update->execute([$new_status, $course_id]);
            
            $status_text = ($action === 'publish') ? 'publiée' : 'archivée';
            $_SESSION['success_message'] = "La formation '{$course['title']}' a été {$status_text} avec succès.";
        } else {
            $_SESSION['error_message'] = "Formation introuvable.";
        }
    } catch (PDOException $e) {
        error_log("Database error in course_toggle: " . $e->getMessage());
        $_SESSION['error_message'] = "Erreur lors de la modification du statut de la formation.";
    }
} else {
    $_SESSION['error_message'] = "Paramètres invalides.";
}

redirect('courses.php');
?>
