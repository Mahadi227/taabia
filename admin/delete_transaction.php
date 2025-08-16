<?php
require_once '../includes/db.php';

if (!isset($_GET['id'])) {
    die('ID de transaction non spécifié.');
}

$id = $_GET['id'];

// Vérifier si la transaction existe
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    die('Transaction introuvable.');
}

// Supprimer la transaction
$stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
$stmt->execute([$id]);

header("Location: transactions.php?deleted=1");
exit;
?>