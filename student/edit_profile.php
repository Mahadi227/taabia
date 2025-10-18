<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

try {
    // Récupérer les infos existantes
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../auth/logout.php');
        exit;
    }

    $success_message = '';
    $error_message = '';

    // Traitement formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $image = $user['profile_image'];

        // Validation
        if (empty($name) || empty($email)) {
            $error_message = "Tous les champs obligatoires doivent être remplis.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "L'adresse email n'est pas valide.";
        } else {
            // Upload d'image
            if (!empty($_FILES['profile_image']['name'])) {
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_types)) {
                    $error_message = "Seuls les fichiers JPG, PNG et GIF sont autorisés.";
                } elseif ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) { // 5MB
                    $error_message = "La taille du fichier ne doit pas dépasser 5MB.";
                } else {
                    $filename = 'student_' . $student_id . '_' . time() . '.' . $ext;
                    $destination = '../uploads/' . $filename;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                        $image = $filename;
                    } else {
                        $error_message = "Erreur lors du téléchargement de l'image.";
                    }
                }
            }

            if (empty($error_message)) {
                // Mise à jour
                $update = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, profile_image = ? WHERE id = ?");
                $update->execute([$name, $email, $image, $student_id]);

                $_SESSION['name'] = $name;
                $success_message = "Profil mis à jour avec succès !";

                // Refresh user data
                $stmt->execute([$student_id]);
                $user = $stmt->fetch();
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in edit_profile: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier mon profil | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="student-styles.css">
</head>

<body>
    <div class="student-layout">
        <!-- Sidebar -->
        <div class="student-sidebar">
            <div class="student-sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p>Espace Apprenant</p>
            </div>

            <nav class="student-nav">
                <a href="index.php" class="student-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="all_courses.php" class="student-nav-item">
                    <i class="fas fa-book-open"></i>
                    Découvrir les cours
                </a>
                <a href="my_courses.php" class="student-nav-item">
                    <i class="fas fa-graduation-cap"></i>
                    Mes cours
                </a>
                <a href="course_lessons.php" class="student-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Mes leçons
                </a>
                <a href="orders.php" class="student-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Mes achats
                </a>
                <a href="messages.php" class="student-nav-item">
                    <i class="fas fa-envelope"></i>
                    Messages
                </a>
                <a href="profile.php" class="student-nav-item active">
                    <i class="fas fa-user"></i>
                    Mon profil
                </a>
                <a href="../auth/logout.php" class="student-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="student-main">
            <div class="student-header">
                <h1>Modifier mon profil</h1>
                <p>Mettez à jour vos informations personnelles</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div style="
                    background: var(--success-color); 
                    color: var(--white); 
                    padding: var(--spacing-4); 
                    border-radius: var(--radius-lg); 
                    margin-bottom: var(--spacing-6);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                ">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="
                    background: var(--danger-color); 
                    color: var(--white); 
                    padding: var(--spacing-4); 
                    border-radius: var(--radius-lg); 
                    margin-bottom: var(--spacing-6);
                    display: flex;
                    align-items: center;
                    gap: var(--spacing-2);
                ">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Form -->
            <div class="student-table-container">
                <div style="padding: var(--spacing-6);">
                    <form method="post" enctype="multipart/form-data" id="profileForm">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                            <div>
                                <label for="full_name" style="display: block; margin-bottom: var(--spacing-2); font-weight: 600; color: var(--gray-700);">
                                    <i class="fas fa-user"></i> Nom complet *
                                </label>
                                <input type="text" name="full_name" id="full_name"
                                    value="<?= htmlspecialchars($user['full_name']) ?>"
                                    required
                                    class="student-search-input"
                                    placeholder="Votre nom complet">
                            </div>

                            <div>
                                <label for="email" style="display: block; margin-bottom: var(--spacing-2); font-weight: 600; color: var(--gray-700);">
                                    <i class="fas fa-envelope"></i> Adresse email *
                                </label>
                                <input type="email" name="email" id="email"
                                    value="<?= htmlspecialchars($user['email']) ?>"
                                    required
                                    class="student-search-input"
                                    placeholder="votre@email.com">
                            </div>
                        </div>

                        <div style="margin-bottom: var(--spacing-6);">
                            <label for="profile_image" style="display: block; margin-bottom: var(--spacing-2); font-weight: 600; color: var(--gray-700);">
                                <i class="fas fa-camera"></i> Photo de profil
                            </label>

                            <div style="display: flex; align-items: center; gap: var(--spacing-4);">
                                <div style="
                                    width: 80px; 
                                    height: 80px; 
                                    border-radius: 50%; 
                                    overflow: hidden; 
                                    border: 3px solid var(--primary-color);
                                    background: var(--gray-100);
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                ">
                                    <?php if ($user['profile_image']): ?>
                                        <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>"
                                            alt="Photo de profil"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user" style="font-size: var(--font-size-xl); color: var(--gray-400);"></i>
                                    <?php endif; ?>
                                </div>

                                <div style="flex: 1;">
                                    <input type="file" name="profile_image" id="profile_image"
                                        accept="image/*"
                                        class="student-search-input"
                                        style="padding: var(--spacing-2);">
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                        Formats acceptés: JPG, PNG, GIF (max 5MB)
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                            <button type="submit" class="student-btn student-btn-primary">
                                <i class="fas fa-save"></i>
                                Enregistrer les modifications
                            </button>

                            <a href="profile.php" class="student-btn student-btn-secondary">
                                <i class="fas fa-times"></i>
                                Annuler
                            </a>

                            <a href="change_password.php" class="student-btn student-btn-success">
                                <i class="fas fa-key"></i>
                                Changer le mot de passe
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="student-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-info-circle"></i> Informations du compte
                    </h3>
                </div>

                <div style="padding: var(--spacing-6);">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-4);">
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                <i class="fas fa-calendar"></i> Date d'inscription
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </div>
                        </div>

                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                <i class="fas fa-user-tag"></i> Rôle
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= ucfirst($user['role']) ?>
                            </div>
                        </div>

                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                <i class="fas fa-toggle-on"></i> Statut
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="profile.php" class="student-btn student-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour au profil
                </a>

                <a href="change_password.php" class="student-btn student-btn-warning">
                    <i class="fas fa-key"></i>
                    Changer le mot de passe
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('profileForm');
            const nameInput = document.getElementById('full_name');
            const emailInput = document.getElementById('email');
            const imageInput = document.getElementById('profile_image');

            // Form validation
            form.addEventListener('submit', function(e) {
                const name = nameInput.value.trim();
                const email = emailInput.value.trim();

                if (!name) {
                    e.preventDefault();
                    alert('Veuillez saisir votre nom complet.');
                    nameInput.focus();
                    return;
                }

                if (!email) {
                    e.preventDefault();
                    alert('Veuillez saisir votre adresse email.');
                    emailInput.focus();
                    return;
                }

                if (!email.includes('@')) {
                    e.preventDefault();
                    alert('Veuillez saisir une adresse email valide.');
                    emailInput.focus();
                    return;
                }
            });

            // Image preview
            imageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.querySelector('.profile-image img') || document.querySelector('.profile-image i');
                        if (img) {
                            if (img.tagName === 'IMG') {
                                img.src = e.target.result;
                            } else {
                                img.parentElement.innerHTML = `<img src="${e.target.result}" alt="Photo de profil" style="width: 100%; height: 100%; object-fit: cover;">`;
                            }
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>

</html>