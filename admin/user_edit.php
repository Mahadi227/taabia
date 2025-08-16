<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('admin');

$message = '';
$error = '';

// Get user ID from URL
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$user_id) {
    redirect('users.php');
}

// Get user details
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        redirect('users.php');
    }
} catch (PDOException $e) {
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $role = sanitize($_POST['role']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validation
    if (empty($full_name)) {
        $error = 'Le nom complet est requis';
    } elseif (empty($email)) {
        $error = 'L\'email est requis';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Format d\'email invalide';
    } elseif (!in_array($role, ['admin', 'instructor', 'student', 'vendor'])) {
        $error = 'Rôle invalide';
    } else {
        // Check if email already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error = 'Cet email est déjà utilisé par un autre utilisateur';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = ?, email = ?, phone = ?, address = ?, role = ?, is_active = ?
                    WHERE id = ?
                ");
                $stmt->execute([$full_name, $email, $phone, $address, $role, $is_active, $user_id]);
                
                $message = 'Utilisateur mis à jour avec succès !';
                
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
            } catch (PDOException $e) {
                $error = 'Erreur lors de la mise à jour de l\'utilisateur: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier l'Utilisateur | TaaBia Admin</title>
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
            background: #2c3e50;
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

        .form-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            margin: 0 auto;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-header h1 {
            color: #2c3e50;
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
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            font-family: inherit;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            background: #2c3e50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background: #34495e;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
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

        .user-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }

        .user-info p {
            margin-bottom: 0.5rem;
        }

        .user-info strong {
            color: #2c3e50;
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
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>👨‍💼 <?php
            $current_user = null;
            try {
                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([current_user_id()]);
                $current_user = $stmt->fetch();
            } catch (PDOException $e) {
                error_log("Error fetching current user: " . $e->getMessage());
            }
            echo htmlspecialchars($current_user['full_name'] ?? 'Admin');
        ?></h2>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="users.php"><i class="fas fa-users"></i> Utilisateurs</a>
        <a href="courses.php"><i class="fas fa-graduation-cap"></i> Cours</a>
        <a href="products.php"><i class="fas fa-box"></i> Produits</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Commandes</a>
        <a href="events.php"><i class="fas fa-calendar"></i> Événements</a>
        <a href="earnings.php"><i class="fas fa-money-bill-wave"></i> Gains</a>
        <a href="payouts.php"><i class="fas fa-hand-holding-usd"></i> Paiements</a>
        <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
    </div>

    <div class="main-content">
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-edit"></i> Modifier l'Utilisateur</h1>
                <p>Modifiez les informations de l'utilisateur</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success"><?= $message ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="user-info">
                <p><strong>ID:</strong> <?= $user['id'] ?></p>
                <p><strong>Date d'inscription:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></p>
                <p><strong>Dernière connexion:</strong> <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?></p>
            </div>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Nom Complet *</label>
                        <input type="text" id="full_name" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <input type="tel" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <input type="text" id="address" name="address" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Rôle *</label>
                        <select id="role" name="role" required>
                            <option value="student" <?= $user['role'] === 'student' ? 'selected' : '' ?>>Étudiant</option>
                            <option value="instructor" <?= $user['role'] === 'instructor' ? 'selected' : '' ?>>Instructeur</option>
                            <option value="vendor" <?= $user['role'] === 'vendor' ? 'selected' : '' ?>>Vendeur</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="is_active">Statut *</label>
                        <select id="is_active" name="is_active" required>
                            <option value="1" <?= $user['is_active'] == 1 ? 'selected' : '' ?>>Actif</option>
                            <option value="0" <?= $user['is_active'] == 0 ? 'selected' : '' ?>>Inactif</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
