<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php'; // pour redirect() si nécessaire
require_role('admin');

// Vérifie l'ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('products.php');
}
$id = (int) $_GET['id'];

// Récupérer le produit
$stmt = $pdo->prepare("SELECT status FROM products WHERE id = ?");
$stmt->execute([$id]);
$product = $stmt->fetch();

if ($product) {
    $new_status = ($product['status'] === 'active') ? 'inactive' : 'active';

    // Mise à jour du statut
    $update = $pdo->prepare("UPDATE products SET status = ? WHERE id = ?");
    $update->execute([$new_status, $id]);
}

redirect('products.php');
