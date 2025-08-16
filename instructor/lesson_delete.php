
<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$lesson_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$lesson_id) {
    flash_message("ID de leçon invalide.", 'error');
    redirect('lessons.php');
}

// Verify lesson belongs to this instructor before deletion
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.title as course_title 
        FROM lessons l 
        JOIN courses c ON l.course_id = c.id 
        WHERE l.id = ? AND c.instructor_id = ?
    ");
    $stmt->execute([$lesson_id, $instructor_id]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        flash_message("Leçon non trouvée ou vous n'avez pas les permissions.", 'error');
        redirect('lessons.php');
    }
    
    // Delete the lesson
    $delete_stmt = $pdo->prepare("DELETE FROM lessons WHERE id = ?");
    $delete_stmt->execute([$lesson_id]);
    
    flash_message("Leçon supprimée avec succès !", 'success');
    redirect('lessons.php');
    
} catch (PDOException $e) {
    error_log("Database error in lesson_delete: " . $e->getMessage());
    flash_message("Erreur lors de la suppression de la leçon.", 'error');
    redirect('lessons.php');
}
?>
