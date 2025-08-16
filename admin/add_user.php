<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if (empty($full_name)) {
        $error_message = "Le nom complet est obligatoire.";
    } elseif (empty($email)) {
        $error_message = "L'email est obligatoire.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "L'email n'est pas valide.";
    } elseif (empty($password)) {
        $error_message = "Le mot de passe est obligatoire.";
    } elseif (strlen($password) < 6) {
        $error_message = "Le mot de passe doit contenir au moins 6 caractères.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = "Cet email est déjà utilisé.";
        } else {
            // Insert user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO users (full_name, email, password, role, phone, address, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$full_name, $email, $hashed_password, $role, $phone, $address, $is_active])) {
                $success_message = "Utilisateur créé avec succès !";
                // Clear form data
                $full_name = $email = $phone = $address = '';
                $role = 'student';
                $is_active = 1;
            } else {
                $error_message = "Erreur lors de la création de l'utilisateur.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un utilisateur | Admin | TaaBia</title>
    
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
            <p><?php
                $current_user = null;
                try {
                    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->execute([current_user_id()]);
                    $current_user = $stmt->fetch();
                } catch (PDOException $e) {
                    error_log("Error fetching current user: " . $e->getMessage());
                }
                echo htmlspecialchars($current_user['full_name'] ?? 'Administrateur');
            ?></p>
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
                    <h1>Ajouter un utilisateur</h1>
                    <p>Créer un nouveau compte utilisateur</p>
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
                    <h3 class="card-title">Informations de l'utilisateur</h3>
                </div>
                
                <div class="card-body">
                    <?php if ($success_message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="full_name" class="form-label">Nom complet *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?= htmlspecialchars($full_name ?? '') ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?= htmlspecialchars($email ?? '') ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="password" class="form-label">Mot de passe *</label>
                                <input type="password" id="password" name="password" class="form-control" required>
                                <small class="form-text">Minimum 6 caractères</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="role" class="form-label">Rôle *</label>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="student" <?= ($role ?? '') === 'student' ? 'selected' : '' ?>>Étudiant</option>
                                    <option value="instructor" <?= ($role ?? '') === 'instructor' ? 'selected' : '' ?>>Instructeur</option>
                                    <option value="vendor" <?= ($role ?? '') === 'vendor' ? 'selected' : '' ?>>Vendeur</option>
                                    <option value="admin" <?= ($role ?? '') === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="form-label">Téléphone</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?= htmlspecialchars($phone ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="is_active" class="form-label">Statut</label>
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="is_active" name="is_active" 
                                               <?= ($is_active ?? 1) == 1 ? 'checked' : '' ?>>
                                        <span class="checkmark"></span>
                                        Utilisateur actif
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">Adresse</label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?= htmlspecialchars($address ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Créer l'utilisateur
                            </button>
                            
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add smooth interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Add click effects to buttons
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html> 