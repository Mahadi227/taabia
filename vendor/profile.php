<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('vendor');

$vendor_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get vendor profile
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch();
} catch (PDOException $e) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sanitize($_POST['fullname']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $bio = sanitize($_POST['bio']);
    
    // Validation
    if (empty($fullname)) {
        $error = 'Le nom complet est requis';
    } elseif (empty($email)) {
        $error = 'L\'email est requis';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format d\'email invalide';
    } else {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $vendor_id]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé par un autre utilisateur';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET fullname = ?, email = ?, phone = ?, address = ?, bio = ?
                    WHERE id = ?
                ");
                $stmt->execute([$fullname, $email, $phone, $address, $bio, $vendor_id]);
                
                $message = 'Profil mis à jour avec succès !';
                
                // Refresh vendor data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$vendor_id]);
                $vendor = $stmt->fetch();
                
                // Update session
                $_SESSION['full_name'] = $fullname;
                $_SESSION['email'] = $email;
                
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour du profil: ' . $e->getMessage();
            }
        }
    }
}

// Get vendor statistics
$total_products = 0;
$total_sales = 0;
$total_earnings = 0;

try {
    // Check if tables exist
    $productsExists = $pdo->query("SHOW TABLES LIKE 'products'")->rowCount() > 0;
    $transactionsExists = $pdo->query("SHOW TABLES LIKE 'transactions'")->rowCount() > 0;
    
    if ($productsExists) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE vendor_id = ?");
        if ($stmt->execute([$vendor_id])) {
            $total_products = $stmt->fetchColumn();
        }
    }
    
    if ($transactionsExists) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE vendor_id = ? AND type = 'product_purchase'");
        if ($stmt->execute([$vendor_id])) {
            $total_sales = $stmt->fetchColumn();
        }
        
        $stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM transactions WHERE vendor_id = ? AND type = 'product_purchase' AND status = 'completed'");
        if ($stmt->execute([$vendor_id])) {
            $total_earnings = $stmt->fetchColumn();
        }
    }
} catch (PDOException $e) {
    error_log("Database error in vendor profile: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mon Profil | TaaBia</title>
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

        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        .profile-card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #00796b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: white;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .profile-email {
            color: #666;
            margin-bottom: 1rem;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #00796b;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .form-header h2 {
            color: #00796b;
            margin-bottom: 0.5rem;
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
            min-height: 100px;
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

        .member-since {
            color: #666;
            font-size: 0.9rem;
            margin-top: 1rem;
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
            .profile-container {
                grid-template-columns: 1fr;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .profile-stats {
                grid-template-columns: 1fr;
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
            <h1>Mon Profil</h1>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <div class="profile-card">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-name"><?= htmlspecialchars($vendor['fullname']) ?></div>
                <div class="profile-email"><?= htmlspecialchars($vendor['email']) ?></div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= $total_products ?></div>
                        <div class="stat-label">Produits</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $total_sales ?></div>
                        <div class="stat-label">Ventes</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= number_format($total_earnings, 0) ?></div>
                        <div class="stat-label">GHS</div>
                    </div>
                </div>
                
                <div class="member-since">
                    Membre depuis <?= date('M Y', strtotime($vendor['created_at'])) ?>
                </div>
            </div>

            <div class="form-container">
                <div class="form-header">
                    <h2><i class="fas fa-edit"></i> Modifier le Profil</h2>
                    <p>Mettez à jour vos informations personnelles</p>
                </div>

                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="fullname">Nom Complet *</label>
                            <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($vendor['fullname']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($vendor['email']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="address">Adresse</label>
                            <input type="text" id="address" name="address" value="<?= htmlspecialchars($vendor['address'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="bio">Bio</label>
                        <textarea id="bio" name="bio" placeholder="Parlez-nous de vous et de votre boutique..."><?= htmlspecialchars($vendor['bio'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Mettre à jour le profil
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>