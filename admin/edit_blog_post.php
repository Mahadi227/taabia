<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

// Get post ID
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    header('Location: blog_posts.php');
    exit();
}

// Get categories for dropdown
try {
    $categories = $pdo->query("SELECT * FROM blog_categories WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}

// Get post data
try {
    $stmt = $pdo->prepare("
        SELECT * FROM blog_posts WHERE id = ?
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();
    
    if (!$post) {
        header('Location: blog_posts.php');
        exit();
    }
} catch (PDOException $e) {
    header('Location: blog_posts.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $excerpt = trim($_POST['excerpt']);
    $category_id = $_POST['category_id'] ?: null;
    $status = $_POST['status'];
    $meta_title = trim($_POST['meta_title']);
    $meta_description = trim($_POST['meta_description']);
    
    // Generate slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    
    // Validate required fields
    $errors = [];
    if (empty($title)) $errors[] = "Le titre est requis.";
    if (empty($content)) $errors[] = "Le contenu est requis.";
    if (empty($excerpt)) $errors[] = "L'extrait est requis.";
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE blog_posts 
                SET title = ?, slug = ?, content = ?, excerpt = ?, category_id = ?, status = ?, 
                    published_at = ?, meta_title = ?, meta_description = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
            
            $stmt->execute([
                $title,
                $slug,
                $content,
                $excerpt,
                $category_id,
                $status,
                $published_at,
                $meta_title,
                $meta_description,
                $post_id
            ]);
            
            $success_message = "Article mis à jour avec succès.";
            
            // Update post data for display
            $post['title'] = $title;
            $post['content'] = $content;
            $post['excerpt'] = $excerpt;
            $post['category_id'] = $category_id;
            $post['status'] = $status;
            $post['meta_title'] = $meta_title;
            $post['meta_description'] = $meta_description;
            
        } catch (PDOException $e) {
            $error_message = "Erreur lors de la mise à jour de l'article.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier l'Article | Admin TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
    <!-- Include TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        tinymce.init({
            selector: '#content',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount checklist mediaembed casechange export formatpainter pageembed linkchecker a11ychecker tinymcespellchecker permanentpen powerpaste advtable advcode editimage tinycomments tableofcontents footnotes mergetags autocorrect typography inlinecss',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
            tinycomments_mode: 'embedded',
            tinycomments_author: 'Author name',
            mergetags_list: [
                { value: 'First.Name', title: 'First Name' },
                { value: 'Email', title: 'Email' },
            ],
            height: 400
        });
    </script>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <h1><i class="fas fa-edit"></i> Modifier l'Article</h1>
                <a href="blog_posts.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Retour aux Articles
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= $error ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="card">
                <form method="POST" class="form">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title" class="form-label">Titre *</label>
                            <input type="text" id="title" name="title" class="form-control" 
                                   value="<?= htmlspecialchars($post['title']) ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id" class="form-label">Catégorie</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" 
                                            <?= $post['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label">Statut</label>
                            <select id="status" name="status" class="form-control">
                                <option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>>Brouillon</option>
                                <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>Publié</option>
                                <option value="archived" <?= $post['status'] === 'archived' ? 'selected' : '' ?>>Archivé</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="excerpt" class="form-label">Extrait *</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" rows="3" 
                                      placeholder="Résumé court de l'article..." required><?= htmlspecialchars($post['excerpt']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="content" class="form-label">Contenu *</label>
                            <textarea id="content" name="content" class="form-control" required><?= htmlspecialchars($post['content']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="meta_title" class="form-label">Titre SEO</label>
                            <input type="text" id="meta_title" name="meta_title" class="form-control" 
                                   value="<?= htmlspecialchars($post['meta_title']) ?>" 
                                   placeholder="Titre pour les moteurs de recherche">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="meta_description" class="form-label">Description SEO</label>
                            <textarea id="meta_description" name="meta_description" class="form-control" rows="3" 
                                      placeholder="Description pour les moteurs de recherche"><?= htmlspecialchars($post['meta_description']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à Jour
                        </button>
                        <a href="blog_posts.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 