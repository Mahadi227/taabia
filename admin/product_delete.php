<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php'; // Ajout nécessaire
require_role('admin');

// Vérifier si l'ID est présent et valide
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('products.php');
}

$id = (int) $_GET['id'];

// Supprimer le produit
$stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
$stmt->execute([$id]);

// Redirection
redirect('products.php');
