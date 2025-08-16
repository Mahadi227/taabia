<?php
require_once '../includes/session.php';
require_once '../includes/function.php';
require_once '../includes/db.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = $_POST['category'] ?? 'general';
        $level = $_POST['level'] ?? 'beginner';
        $duration = $_POST['duration'] ?? '';
        $status = $_POST['status'] ?? 'draft';

        // Validation
        if (empty($title)) {
            $error_message = "Le titre de la formation est obligatoire.";
        } elseif (strlen($title) < 5) {
            $error_message = "Le titre doit contenir au moins 5 caractères.";
        } elseif (empty($description)) {
            $error_message = "La description est obligatoire.";
        } elseif (strlen($description) < 20) {
            $error_message = "La description doit contenir au moins 20 caractères.";
        } elseif ($price < 0) {
            $error_message = "Le prix ne peut pas être négatif.";
        } else {
            // Insert course
            $stmt = $pdo->prepare("
                INSERT INTO courses (title, description, instructor_id, price, category, level, duration, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $description, $instructor_id, $price, $category, $level, $duration, $status]);

            $course_id = $pdo->lastInsertId();
            $success_message = "Formation créée avec succès ! ID: " . $course_id;
            
            // Clear form data
            $title = $description = '';
            $price = 0;
            $category = 'general';
            $level = 'beginner';
            $duration = '';
            $status = 'draft';
        }
    }

    // Get instructor statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_courses = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ? AND status = 'published'");
    $stmt->execute([$instructor_id]);
    $active_courses = $stmt->fetchColumn();

} catch (PDOException $e) {
    error_log("Database error in add_course: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
    $total_courses = 0;
    $active_courses = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer un nouveau cours | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p>Espace Formateur</p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    Mes cours
                </a>
                <a href="add_course.php" class="instructor-nav-item active">
                    <i class="fas fa-plus-circle"></i>
                    Nouveau cours
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    Mes étudiants
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    Devoirs à valider
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    Mes gains
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    Transactions
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    Paiements
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <h1>Créer un nouveau cours</h1>
                <p>Créez une formation complète pour vos étudiants</p>
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

            <!-- Course Creation Form -->
            <div class="instructor-form">
                <form method="POST" id="courseForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="title" class="instructor-form-label">
                                <i class="fas fa-heading"></i> Titre du cours *
                            </label>
                            <input type="text" name="title" id="title" required 
                                   class="instructor-form-input" 
                                   placeholder="Titre de votre formation"
                                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="category" class="instructor-form-label">
                                <i class="fas fa-tag"></i> Catégorie
                            </label>
                            <select name="category" id="category" class="instructor-form-input instructor-form-select">
                                <option value="general" <?= (isset($_POST['category']) && $_POST['category'] == 'general') ? 'selected' : '' ?>>Général</option>
                                <option value="technology" <?= (isset($_POST['category']) && $_POST['category'] == 'technology') ? 'selected' : '' ?>>Technologie</option>
                                <option value="business" <?= (isset($_POST['category']) && $_POST['category'] == 'business') ? 'selected' : '' ?>>Business</option>
                                <option value="design" <?= (isset($_POST['category']) && $_POST['category'] == 'design') ? 'selected' : '' ?>>Design</option>
                                <option value="marketing" <?= (isset($_POST['category']) && $_POST['category'] == 'marketing') ? 'selected' : '' ?>>Marketing</option>
                                <option value="languages" <?= (isset($_POST['category']) && $_POST['category'] == 'languages') ? 'selected' : '' ?>>Langues</option>
                                <option value="other" <?= (isset($_POST['category']) && $_POST['category'] == 'other') ? 'selected' : '' ?>>Autre</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="instructor-form-group">
                        <label for="description" class="instructor-form-label">
                            <i class="fas fa-align-left"></i> Description *
                        </label>
                        <textarea name="description" id="description" rows="6" required 
                                  class="instructor-form-input instructor-form-textarea" 
                                  placeholder="Décrivez votre formation en détail..."
                                  style="resize: vertical;"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="price" class="instructor-form-label">
                                <i class="fas fa-coins"></i> Prix (GHS)
                            </label>
                            <input type="number" name="price" id="price" 
                                   min="0" step="0.01" 
                                   class="instructor-form-input" 
                                   placeholder="0.00"
                                   value="<?= $_POST['price'] ?? 0 ?>">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                Laissez 0 pour un cours gratuit
                            </div>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="level" class="instructor-form-label">
                                <i class="fas fa-signal"></i> Niveau
                            </label>
                            <select name="level" id="level" class="instructor-form-input instructor-form-select">
                                <option value="beginner" <?= (isset($_POST['level']) && $_POST['level'] == 'beginner') ? 'selected' : '' ?>>Débutant</option>
                                <option value="intermediate" <?= (isset($_POST['level']) && $_POST['level'] == 'intermediate') ? 'selected' : '' ?>>Intermédiaire</option>
                                <option value="advanced" <?= (isset($_POST['level']) && $_POST['level'] == 'advanced') ? 'selected' : '' ?>>Avancé</option>
                                <option value="expert" <?= (isset($_POST['level']) && $_POST['level'] == 'expert') ? 'selected' : '' ?>>Expert</option>
                            </select>
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="duration" class="instructor-form-label">
                                <i class="fas fa-clock"></i> Durée estimée
                            </label>
                            <input type="text" name="duration" id="duration" 
                                   class="instructor-form-input" 
                                   placeholder="ex: 2 heures, 1 semaine"
                                   value="<?= htmlspecialchars($_POST['duration'] ?? '') ?>">
                        </div>
                        
                        <div class="instructor-form-group">
                            <label for="status" class="instructor-form-label">
                                <i class="fas fa-toggle-on"></i> Statut
                            </label>
                            <select name="status" id="status" class="instructor-form-input instructor-form-select">
                                        <option value="draft" <?= (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : '' ?>>Brouillon</option>
        <option value="published" <?= (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : '' ?>>Publié</option>
        <option value="archived" <?= (isset($_POST['status']) && $_POST['status'] == 'archived') ? 'selected' : '' ?>>Archivé</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            Créer le cours
                        </button>
                        
                        <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>
                        
                        <div style="margin-left: auto; font-size: var(--font-size-sm); color: var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            Vous pourrez ajouter des leçons après la création
                        </div>
                    </div>
                </form>
            </div>

            <!-- Instructor Statistics -->
            <div class="instructor-table-container" style="margin-top: var(--spacing-8);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-chart-bar"></i> Vos statistiques
                    </h3>
                </div>
                
                <div style="padding: var(--spacing-6);">
                    <div class="instructor-cards" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <div class="instructor-card">
                            <div class="instructor-card-header">
                                <div class="instructor-card-icon primary">
                                    <i class="fas fa-book"></i>
                                </div>
                            </div>
                            <div class="instructor-card-title">Total des cours</div>
                            <div class="instructor-card-value"><?= $total_courses ?></div>
                            <div class="instructor-card-description">Cours créés</div>
                        </div>

                        <div class="instructor-card">
                            <div class="instructor-card-header">
                                <div class="instructor-card-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                            <div class="instructor-card-title">Cours actifs</div>
                            <div class="instructor-card-value"><?= $active_courses ?></div>
                            <div class="instructor-card-description">Cours publiés</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>
                
                <a href="add_lesson.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                
                <a href="students.php" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-users"></i>
                    Gérer les étudiants
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('courseForm');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');
            const priceInput = document.getElementById('price');
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const description = descriptionInput.value.trim();
                const price = parseFloat(priceInput.value);
                
                if (!title) {
                    e.preventDefault();
                    alert('Veuillez saisir un titre pour le cours.');
                    titleInput.focus();
                    return;
                }
                
                if (title.length < 5) {
                    e.preventDefault();
                    alert('Le titre doit contenir au moins 5 caractères.');
                    titleInput.focus();
                    return;
                }
                
                if (!description) {
                    e.preventDefault();
                    alert('Veuillez saisir une description pour le cours.');
                    descriptionInput.focus();
                    return;
                }
                
                if (description.length < 20) {
                    e.preventDefault();
                    alert('La description doit contenir au moins 20 caractères.');
                    descriptionInput.focus();
                    return;
                }
                
                if (price < 0) {
                    e.preventDefault();
                    alert('Le prix ne peut pas être négatif.');
                    priceInput.focus();
                    return;
                }
            });
            
            // Auto-resize textarea
            descriptionInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
            
            // Character counter for title
            titleInput.addEventListener('input', function() {
                const length = this.value.length;
                const maxLength = 100;
                
                if (length > maxLength) {
                    this.style.borderColor = 'var(--danger-color)';
                } else {
                    this.style.borderColor = 'var(--gray-200)';
                }
            });
        });
    </script>
</body>
</html>
