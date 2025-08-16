<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('instructor');

// Vérification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) $_POST['id'];
    $action = $_POST['action'];
    $feedback = trim($_POST['commentaire'] ?? '');

    if (!$id || !in_array($action, ['valider', 'rejeter']) || empty($feedback)) {
        redirect('validate_submissions.php');
    }

    // Met à jour le devoir avec statut + feedback
    $status = ($action === 'valider') ? 'validé' : 'rejeté';
    $stmt = $pdo->prepare("UPDATE submissions SET status = ?, feedback = ?, validated_at = NOW() WHERE id = ?");
    $stmt->execute([$status, $feedback, $id]);

    redirect('validate_submissions.php');
} else {
    redirect('validate_submissions.php');
}
