<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'])) {
    $user_id = (int) $_POST['user_id'];
    
    try {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "Utilisateur introuvable.";
        } else {
            // Prevent admin from deleting themselves
            if ($user_id == current_user_id()) {
                $error = "Vous ne pouvez pas supprimer votre propre compte.";
            } else {
                // Delete user
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$user_id])) {
                    $message = "Utilisateur '{$user['full_name']}' supprimé avec succès.";
                } else {
                    $error = "Erreur lors de la suppression de l'utilisateur.";
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in user_delete.php: " . $e->getMessage());
        $error = "Erreur lors de la suppression de l'utilisateur.";
    }
} elseif (isset($_GET['id'])) {
    $user_id = (int) $_GET['id'];
    
    try {
        // Get user details for confirmation
        $stmt = $pdo->prepare("SELECT id, full_name, email, role, created_at FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            redirect('users.php');
        }
    } catch (PDOException $e) {
        redirect('users.php');
    }
} else {
    redirect('users.php');
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer l'utilisateur | Admin | TaaBia</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>TaaBia Admin</h2>
            <p>Plateforme de gestion</p>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-chart-line"></i>
                    <span>Tableau de bord</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="users.php" class="nav-link active">
                    <i class="fas fa-users"></i>
                    <span>Utilisateurs</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="courses.php" class="nav-link">
                    <i class="fas fa-book"></i>
                    <span>Formations</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="products.php" class="nav-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Produits</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="events.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Événements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="blog_posts.php" class="nav-link">
                    <i class="fas fa-blog"></i>
                    <span>Blog</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="orders.php" class="nav-link">
                    <i class="fas fa-shopping-bag"></i>
                    <span>Commandes</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="payments.php" class="nav-link">
                    <i class="fas fa-credit-card"></i>
                    <span>Paiements</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="contact_messages.php" class="nav-link">
                    <i class="fas fa-envelope"></i>
                    <span>Messages</span>
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1>Supprimer l'utilisateur</h1>
                    <p>Confirmer la suppression d'un compte utilisateur</p>
                </div>
                
                <div class="header-right">
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Retour à la liste
                    </a>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Confirmation de suppression</h3>
                </div>

                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($message) ?>
                        </div>
                        <div class="text-center">
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i>
                                Retour à la liste des utilisateurs
                            </a>
                        </div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <div class="text-center">
                            <a href="users.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i>
                                Retour à la liste des utilisateurs
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="user-info">
                            <h4>Informations de l'utilisateur</h4>
                            <div class="user-details">
                                <p><strong>Nom:</strong> <?= htmlspecialchars($user['full_name']) ?></p>
                                <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                                <p><strong>Rôle:</strong> 
                                    <?php
                                    $role_labels = [
                                        'admin' => 'Administrateur',
                                        'instructor' => 'Instructeur',
                                        'student' => 'Étudiant',
                                        'vendor' => 'Vendeur'
                                    ];
                                    echo $role_labels[$user['role']] ?? $user['role'];
                                    ?>
                                </p>
                                <p><strong>Date d'inscription:</strong> <?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></p>
                            </div>
                        </div>

                        <div class="warning-box">
                            <div class="warning-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="warning-content">
                                <h4>Attention !</h4>
                                <p>Cette action est irréversible. Toutes les données associées à cet utilisateur seront définitivement supprimées.</p>
                                <ul>
                                    <li>Le compte utilisateur sera supprimé</li>
                                    <li>Toutes les données personnelles seront effacées</li>
                                    <li>Cette action ne peut pas être annulée</li>
                                </ul>
                            </div>
                        </div>

                        <form method="POST" class="delete-form">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Êtes-vous absolument sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible.')">
                                    <i class="fas fa-trash"></i>
                                    Confirmer la suppression
                                </button>
                                
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Annuler
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <style>
        .user-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #007bff;
        }

        .user-details p {
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .warning-icon {
            color: #f39c12;
            font-size: 2rem;
            flex-shrink: 0;
        }

        .warning-content h4 {
            color: #856404;
            margin-bottom: 0.5rem;
        }

        .warning-content p {
            color: #856404;
            margin-bottom: 1rem;
        }

        .warning-content ul {
            color: #856404;
            margin: 0;
            padding-left: 1.5rem;
        }

        .warning-content li {
            margin-bottom: 0.25rem;
        }

        .delete-form {
            margin-top: 2rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-danger {
            background: #dc3545;
            border-color: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        .text-center {
            text-align: center;
        }
    </style>
</body>
</html>
