<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Initialize variables
$available_balance = 0;
$pending_payouts = 0;
$total_payouts = 0;
$payout_requests = [];
$payout_history = [];

try {
    // Check if required tables exist
    $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    $payoutRequestsExists = $pdo->query("SHOW TABLES LIKE 'payout_requests'")->rowCount() > 0;
    
    if ($transactionsExists) {
        // Calculate available balance (completed transactions - paid out)
        $stmt = $pdo->prepare("
            SELECT IFNULL(SUM(amount), 0) 
            FROM transactions 
            WHERE vendor_id = ? AND type = 'product_purchase' AND status = 'completed'
        ");
        if ($stmt->execute([$vendor_id])) {
            $total_earnings = $stmt->fetchColumn();
        }
        
        if ($payoutRequestsExists) {
            // Get total paid out
            $stmt = $pdo->prepare("
                SELECT IFNULL(SUM(amount), 0) 
                FROM payout_requests 
                WHERE vendor_id = ? AND status = 'paid'
            ");
            if ($stmt->execute([$vendor_id])) {
                $total_paid_out = $stmt->fetchColumn();
            }
            
            // Get pending payouts
            $stmt = $pdo->prepare("
                SELECT IFNULL(SUM(amount), 0) 
                FROM payout_requests 
                WHERE vendor_id = ? AND status = 'pending'
            ");
            if ($stmt->execute([$vendor_id])) {
                $pending_payouts = $stmt->fetchColumn();
            }
            
            $available_balance = $total_earnings - $total_paid_out - $pending_payouts;
            $total_payouts = $total_paid_out;
            
            // Get payout requests
            $stmt = $pdo->prepare("
                SELECT * FROM payout_requests 
                WHERE vendor_id = ? 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            if ($stmt->execute([$vendor_id])) {
                $payout_requests = $stmt->fetchAll();
            }
        } else {
            $available_balance = $total_earnings;
        }
    }
} catch (PDOException $e) {
    error_log("Database error in vendor payouts: " . $e->getMessage());
}

// Handle payout request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    $amount = (float)$_POST['amount'];
    
    if ($amount <= 0) {
        $error = 'Le montant doit être positif';
    } elseif ($amount > $available_balance) {
        $error = 'Le montant demandé dépasse votre solde disponible';
    } elseif ($amount < 50) {
        $error = 'Le montant minimum pour un paiement est de 50 GHS';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO payout_requests (vendor_id, amount, status, payment_method, bank_details)
                VALUES (?, ?, 'pending', ?, ?)
            ");
            
            $payment_method = sanitize($_POST['payment_method']);
            $bank_details = sanitize($_POST['bank_details']);
            
            if ($stmt->execute([$vendor_id, $amount, $payment_method, $bank_details])) {
                $message = 'Demande de paiement soumise avec succès !';
                $available_balance -= $amount;
                $pending_payouts += $amount;
                
                // Refresh payout requests
                $stmt = $pdo->prepare("
                    SELECT * FROM payout_requests 
                    WHERE vendor_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 10
                ");
                if ($stmt->execute([$vendor_id])) {
                    $payout_requests = $stmt->fetchAll();
                }
            } else {
                $error = 'Erreur lors de la soumission de la demande';
            }
        } catch (PDOException $e) {
            $error = 'Erreur lors de la soumission de la demande: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Paiements | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        .sidebar {
            width: 250px;
            height: 100vh;
            background: #00796b;
            color: white;
            position: fixed;
            padding: 2rem 1rem;
        }

        .sidebar h2 {
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.4rem;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 0.8rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 5px;
            transition: background-color 0.3s;
        }

        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-card h3 {
            color: #00796b;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
        }

        .payout-form {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .payout-form h3 {
            margin-bottom: 1.5rem;
            color: #00796b;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #00796b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background: #00695c;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .payouts-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .payouts-section h3 {
            margin-bottom: 1rem;
            color: #00796b;
        }

        .payouts-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payouts-table th,
        .payouts-table td {
            padding: 0.8rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .payouts-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        .payouts-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .payouts-table {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏪 Vendeur</h2>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Mes Produits</a>
        <a href="add_product.php"><i class="fas fa-plus"></i> Ajouter Produit</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> Mes Gains</a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> Paiements</a>
        <a href="profile.php"><i class="fas fa-user"></i> Mon Profil</a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="header">
            <h1>Mes Paiements</h1>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-wallet"></i> Solde Disponible</h3>
                <div class="value"><?= number_format($available_balance, 2) ?> GHS</div>
                <p>Montant disponible pour paiement</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-clock"></i> En Attente</h3>
                <div class="value"><?= number_format($pending_payouts, 2) ?> GHS</div>
                <p>Paiements en cours de traitement</p>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Total Payé</h3>
                <div class="value"><?= number_format($total_payouts, 2) ?> GHS</div>
                <p>Total des paiements reçus</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="payout-form">
            <h3><i class="fas fa-hand-holding-usd"></i> Demander un Paiement</h3>
            
            <?php if ($available_balance >= 50): ?>
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">Montant (GHS) *</label>
                        <input type="number" id="amount" name="amount" step="0.01" min="50" max="<?= $available_balance ?>" required>
                        <small>Montant minimum: 50 GHS</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Méthode de Paiement *</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="">Sélectionner une méthode</option>
                            <option value="Mobile Money">Mobile Money</option>
                            <option value="Bank Transfer">Virement Bancaire</option>
                            <option value="PayPal">PayPal</option>
                            <option value="Cash">Espèces</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bank_details">Détails de Paiement *</label>
                    <textarea id="bank_details" name="bank_details" placeholder="Entrez vos détails de paiement (numéro de compte, nom de banque, etc.)" required></textarea>
                </div>
                
                <button type="submit" name="request_payout" class="btn">
                    <i class="fas fa-paper-plane"></i> Soumettre la Demande
                </button>
            </form>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-wallet"></i>
                <h4>Solde insuffisant</h4>
                <p>Vous devez avoir au moins 50 GHS de solde disponible pour demander un paiement.</p>
                <a href="earnings.php" class="btn">Voir mes gains</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="payouts-section">
            <h3><i class="fas fa-list"></i> Historique des Demandes de Paiement</h3>
            
            <?php if (empty($payout_requests)): ?>
            <div class="empty-state">
                <i class="fas fa-hand-holding-usd"></i>
                <h4>Aucune demande de paiement</h4>
                <p>Vous n'avez pas encore soumis de demande de paiement.</p>
            </div>
            <?php else: ?>
            <table class="payouts-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Méthode</th>
                        <th>Statut</th>
                        <th>Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payout_requests as $payout): ?>
                    <tr>
                        <td>
                            <?= date('d/m/Y H:i', strtotime($payout['created_at'])) ?>
                        </td>
                        <td>
                            <strong><?= number_format($payout['amount'], 2) ?> GHS</strong>
                        </td>
                        <td>
                            <?= htmlspecialchars($payout['payment_method']) ?>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $payout['status'] ?>">
                                <?= ucfirst($payout['status']) ?>
                            </span>
                        </td>
                        <td>
                            <small><?= htmlspecialchars($payout['bank_details']) ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>