<?php
require_once '../includes/db.php';

if (!isset($_GET['id'])) {
    die('ID de transaction non spécifié.');
}

$id = $_GET['id'];

// Récupérer la transaction
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    die('Transaction introuvable.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $currency = $_POST['currency'];
    $payment_method = $_POST['payment_method'];
    $payment_status = $_POST['payment_status'];
    $type = $_POST['type'];

    // Mettre à jour la transaction
    $stmt = $pdo->prepare("UPDATE transactions SET amount = ?, currency = ?, payment_method = ?, payment_status = ?, type = ? WHERE id = ?");
    $stmt->execute([$amount, $currency, $payment_method, $payment_status, $type, $id]);

    header('Location: transactions.php?success=1');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier une transaction</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <a href="../admin/index.php" class="btn-dashboard" style="display:inline-block; margin-top:15px; padding:10px 20px; background:#00796b; color:white; text-decoration:none; border-radius:5px;">⬅ Retour au tableau de bord</a>

    <h2>Modifier la transaction #<?= $transaction['id'] ?></h2>
    <form method="POST">
        <label>Montant:</label>
        <input type="number" step="0.01" name="amount" value="<?= $transaction['amount'] ?>" required><br>

        <label>Devise:</label>
        <input type="text" name="currency" value="<?= $transaction['currency'] ?>" required><br>

        <label>Méthode de paiement:</label>
        <input type="text" name="payment_method" value="<?= $transaction['payment_method'] ?>"><br>

        <label>Statut de paiement:</label>
        <select name="payment_status">
            <option value="pending" <?= $transaction['payment_status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
            <option value="success" <?= $transaction['payment_status'] === 'success' ? 'selected' : '' ?>>Succès</option>
            <option value="failed" <?= $transaction['payment_status'] === 'failed' ? 'selected' : '' ?>>Échoué</option>
        </select><br>

        <label>Type:</label>
        <select name="type">
            <option value="course" <?= $transaction['type'] === 'course' ? 'selected' : '' ?>>Cours</option>
            <option value="product" <?= $transaction['type'] === 'product' ? 'selected' : '' ?>>Produit</option>
            <option value="subscription" <?= $transaction['type'] === 'subscription' ? 'selected' : '' ?>>Abonnement</option>
        </select><br><br>

        <button type="submit">Mettre à jour</button>
        <a href="transactions.php">Annuler</a>
    </form>
</body>
</html>