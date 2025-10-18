<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

try {
    if (!isset($_GET['id'])) {
        header('Location: my_courses.php');
        exit;
    }

    $course_id = (int) $_GET['id'];

    // Verify course belongs to instructor
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: my_courses.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $category = $_POST['category'] ?? 'general';
        $level = $_POST['level'] ?? 'beginner';
        $duration = $_POST['duration'] ?? '';
        $status = $_POST['status'] ?? 'draft';
        $remove_image = isset($_POST['remove_image']);
        $uploaded_image_filename = null;

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
            // Optional image upload handling
            if (isset($_FILES['course_image']) && is_array($_FILES['course_image']) && ($_FILES['course_image']['error'] !== UPLOAD_ERR_NO_FILE)) {
                $file = $_FILES['course_image'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_message = "Erreur lors du téléchargement de l'image (code: " . (int)$file['error'] . ").";
                } else {
                    $allowedMime = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    if (!isset($allowedMime[$mime])) {
                        $error_message = "Format d'image non supporté. Formats acceptés: JPG, PNG, WEBP.";
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $error_message = "L'image est trop volumineuse (max 5MB).";
                    } else {
                        $uploadsDir = realpath(__DIR__ . '/../uploads');
                        if ($uploadsDir === false) {
                            $error_message = "Le dossier d'upload est introuvable.";
                        } else {
                            $ext = $allowedMime[$mime];
                            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
                            $unique = time() . '_' . bin2hex(random_bytes(4));
                            $filename = "course_{$unique}_{$safeBase}.{$ext}";
                            $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
                            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $error_message = "Impossible d'enregistrer l'image.";
                            } else {
                                @chmod($targetPath, 0644);
                                $uploaded_image_filename = $filename;
                            }
                        }
                    }
                }
            }

            if (empty($error_message)) {
                // Update course
                $stmt = $pdo->prepare("
                UPDATE courses 
                SET title = ?, description = ?, price = ?, category = ?, level = ?, duration = ?, status = ?, updated_at = NOW()
                WHERE id = ? AND instructor_id = ?
            ");
                $stmt->execute([$title, $description, $price, $category, $level, $duration, $status, $course_id, $instructor_id]);

                // Determine image column
                $columns = $pdo->query("SHOW COLUMNS FROM courses")->fetchAll(PDO::FETCH_COLUMN);
                $imgCol = null;
                if (in_array('image_url', $columns, true)) {
                    $imgCol = 'image_url';
                } elseif (in_array('thumbnail_url', $columns, true)) {
                    $imgCol = 'thumbnail_url';
                }

                if ($imgCol) {
                    $currentImage = $course[$imgCol] ?? null;

                    if ($remove_image && $currentImage) {
                        $upd = $pdo->prepare("UPDATE courses SET {$imgCol} = NULL WHERE id = ? AND instructor_id = ?");
                        $upd->execute([$course_id, $instructor_id]);
                        $path = realpath(__DIR__ . '/../uploads/' . $currentImage);
                        if ($path && is_file($path)) {
                            @unlink($path);
                        }
                    }

                    if ($uploaded_image_filename) {
                        $upd = $pdo->prepare("UPDATE courses SET {$imgCol} = ? WHERE id = ? AND instructor_id = ?");
                        $upd->execute([$uploaded_image_filename, $course_id, $instructor_id]);
                        if ($currentImage) {
                            $path = realpath(__DIR__ . '/../uploads/' . $currentImage);
                            if ($path && is_file($path)) {
                                @unlink($path);
                            }
                        }
                    }
                }

                $success_message = "Formation mise à jour avec succès !";

                // Refresh course data
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND instructor_id = ?");
                $stmt->execute([$course_id, $instructor_id]);
                $course = $stmt->fetch();
            }
        }
    }

    // Get course statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $lesson_count = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $enrollment_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error in edit_course: " . $e->getMessage());
    $error_message = "Une erreur est survenue. Veuillez réessayer.";
    $lesson_count = 0;
    $enrollment_count = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier le cours | TaaBia</title>
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
                <a href="add_course.php" class="instructor-nav-item">
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
                <h1>Modifier le cours</h1>
                <p>Mettez à jour les informations de votre formation</p>
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

            <!-- Course Information -->
            <div class="instructor-table-container" style="margin-bottom: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-info-circle"></i> Informations du cours
                    </h3>
                </div>

                <div style="padding: var(--spacing-6);">
                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-4);">
                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                <i class="fas fa-play-circle"></i> Leçons
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= $lesson_count ?> leçons créées
                            </div>
                        </div>

                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                <i class="fas fa-users"></i> Inscriptions
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= $enrollment_count ?> étudiants inscrits
                            </div>
                        </div>

                        <div style="
                            background: var(--gray-50); 
                            padding: var(--spacing-4); 
                            border-radius: var(--radius-lg);
                        ">
                            <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                <i class="fas fa-calendar"></i> Créé le
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= date('d/m/Y', strtotime($course['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Edit Form -->
            <div class="instructor-form">
                <form method="POST" id="courseForm" enctype="multipart/form-data">
                    <div
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="title" class="instructor-form-label">
                                <i class="fas fa-heading"></i> Titre du cours *
                            </label>
                            <input type="text" name="title" id="title" required class="instructor-form-input"
                                placeholder="Titre de votre formation"
                                value="<?= htmlspecialchars($course['title']) ?>">
                        </div>

                        <div class="instructor-form-group">
                            <label for="category" class="instructor-form-label">
                                <i class="fas fa-tag"></i> Catégorie
                            </label>
                            <select name="category" id="category" class="instructor-form-input instructor-form-select">
                                <option value="general" <?= $course['category'] == 'general' ? 'selected' : '' ?>>
                                    Général</option>
                                <option value="technology" <?= $course['category'] == 'technology' ? 'selected' : '' ?>>
                                    Technologie</option>
                                <option value="business" <?= $course['category'] == 'business' ? 'selected' : '' ?>>
                                    Business</option>
                                <option value="design" <?= $course['category'] == 'design' ? 'selected' : '' ?>>Design
                                </option>
                                <option value="marketing" <?= $course['category'] == 'marketing' ? 'selected' : '' ?>>
                                    Marketing</option>
                                <option value="languages" <?= $course['category'] == 'languages' ? 'selected' : '' ?>>
                                    Langues</option>
                                <option value="other" <?= $course['category'] == 'other' ? 'selected' : '' ?>>Autre
                                </option>
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
                            style="resize: vertical;"><?= htmlspecialchars($course['description']) ?></textarea>
                    </div>

                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="price" class="instructor-form-label">
                                <i class="fas fa-coins"></i> Prix (GHS)
                            </label>
                            <input type="number" name="price" id="price" min="0" step="0.01"
                                class="instructor-form-input" placeholder="0.00" value="<?= $course['price'] ?? 0 ?>">
                        </div>

                        <div class="instructor-form-group">
                            <label for="level" class="instructor-form-label">
                                <i class="fas fa-signal"></i> Niveau
                            </label>
                            <select name="level" id="level" class="instructor-form-input instructor-form-select">
                                <option value="beginner" <?= $course['level'] == 'beginner' ? 'selected' : '' ?>>
                                    Débutant</option>
                                <option value="intermediate"
                                    <?= $course['level'] == 'intermediate' ? 'selected' : '' ?>>Intermédiaire</option>
                                <option value="advanced" <?= $course['level'] == 'advanced' ? 'selected' : '' ?>>Avancé
                                </option>
                                <option value="expert" <?= $course['level'] == 'expert' ? 'selected' : '' ?>>Expert
                                </option>
                            </select>
                        </div>

                        <div class="instructor-form-group">
                            <label for="duration" class="instructor-form-label">
                                <i class="fas fa-clock"></i> Durée estimée
                            </label>
                            <input type="text" name="duration" id="duration" class="instructor-form-input"
                                placeholder="ex: 2 heures, 1 semaine"
                                value="<?= htmlspecialchars($course['duration'] ?? '') ?>">
                        </div>

                        <div class="instructor-form-group">
                            <label for="status" class="instructor-form-label">
                                <i class="fas fa-toggle-on"></i> Statut
                            </label>
                            <select name="status" id="status" class="instructor-form-input instructor-form-select">
                                <option value="draft" <?= $course['status'] == 'draft' ? 'selected' : '' ?>>Brouillon
                                </option>
                                <option value="published" <?= $course['status'] == 'published' ? 'selected' : '' ?>>
                                    Publié</option>
                                <option value="archived" <?= $course['status'] == 'archived' ? 'selected' : '' ?>>
                                    Archivé</option>
                            </select>
                        </div>
                    </div>

                    <div class="instructor-form-group" style="margin-bottom: var(--spacing-6);">
                        <label class="instructor-form-label">
                            <i class="fas fa-image"></i> Image de couverture
                        </label>
                        <?php $imgPreview = $course['image_url'] ?? ($course['thumbnail_url'] ?? null);
                        if ($imgPreview): ?>
                        <div style="margin-bottom: .5rem;">
                            <img src="../uploads/<?= htmlspecialchars($imgPreview) ?>" alt="Aperçu"
                                style="max-width: 100%; border-radius: 8px; box-shadow: var(--shadow-light);">
                        </div>
                        <?php endif; ?>
                        <input type="file" name="course_image" id="course_image" class="instructor-form-input"
                            accept="image/jpeg,image/png,image/webp">
                        <small>JPG/PNG/WEBP, 5MB max.</small>
                        <?php if ($imgPreview): ?>
                        <div style="margin-top:.5rem;">
                            <label style="display:inline-flex; align-items:center; gap:.5rem;">
                                <input type="checkbox" name="remove_image"> Supprimer l'image actuelle
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="phone">Numéro de téléphone</label>
                        <input type="text" name="phone" id="phone" class="form-control"
                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Ex: +221 77 123 45 67">
                    </div>

                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn_primary">
                            <i class="fas fa-save"></i>
                            Enregistrer les modifications
                        </button>

                        <a href="my_courses.php" class="instructor-btn instructor-btn_secondary">
                            <i class="fas fa-times"></i>
                            Annuler
                        </a>

                        <div style="margin-left: auto; display: flex; gap: var(--spacing-2);">
                            <a href="course_lessons.php?course_id=<?= $course_id ?>"
                                class="instructor-btn instructor-btn_success">
                                <i class="fas fa-play"></i>
                                Gérer les leçons
                            </a>

                            <a href="course_students.php?course_id=<?= $course_id ?>"
                                class="instructor-btn instructor-btn_info">
                                <i class="fas fa-users"></i>
                                Voir les étudiants
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="instructor-btn instructor-btn_secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>

                <a href="add_lesson.php" class="instructor-btn instructor-btn_success">
                    <i class="fas fa-plus"></i>
                    Ajouter une leçon
                </a>

                <a href="course_stats.php?course_id=<?= $course_id ?>" class="instructor-btn instructor-btn_info">
                    <i class="fas fa-chart-bar"></i>
                    Statistiques du cours
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