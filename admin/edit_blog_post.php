<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

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

// Get all tags
try {
    $all_tags = $pdo->query("SELECT * FROM blog_tags ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $all_tags = [];
}

// Get post data with tags
try {
    $stmt = $pdo->prepare("
        SELECT bp.*, u.fullname as author_name,
               GROUP_CONCAT(bt.id) as tag_ids,
               GROUP_CONCAT(bt.name) as tag_names
        FROM blog_posts bp
        LEFT JOIN users u ON bp.author_id = u.id
        LEFT JOIN blog_post_tags bpt ON bp.id = bpt.post_id
        LEFT JOIN blog_tags bt ON bpt.tag_id = bt.id
        WHERE bp.id = ?
        GROUP BY bp.id
    ");
    $stmt->execute([$post_id]);
    $post = $stmt->fetch();

    if (!$post) {
        header('Location: blog_posts.php');
        exit();
    }

    // Parse tags
    $post_tags = [];
    if ($post['tag_ids']) {
        $tag_ids = explode(',', $post['tag_ids']);
        $tag_names = explode(',', $post['tag_names']);
        $post_tags = array_combine($tag_ids, $tag_names);
    }
} catch (PDOException $e) {
    header('Location: blog_posts.php');
    exit();
}

// Handle image upload
if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = '../uploads/blog/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_extension = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($file_extension, $allowed_extensions)) {
        $new_filename = 'blog_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $upload_path)) {
            // Delete old image if exists
            if ($post['featured_image'] && file_exists('../' . $post['featured_image'])) {
                unlink('../' . $post['featured_image']);
            }
            $post['featured_image'] = 'uploads/blog/' . $new_filename;
        }
    }
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
    $featured_image = $post['featured_image']; // Keep existing if no new upload
    $tags = isset($_POST['tags']) ? $_POST['tags'] : [];

    // Generate slug from title
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');

    // Ensure unique slug
    $original_slug = $slug;
    $counter = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM blog_posts WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $post_id]);
        if (!$stmt->fetch()) break;
        $slug = $original_slug . '-' . $counter;
        $counter++;
    }

    // Validate required fields
    $errors = [];
    if (empty($title)) $errors[] = __("title_required");
    if (empty($content)) $errors[] = __("content_required");
    if (empty($excerpt)) $errors[] = __("excerpt_required");
    if (strlen($title) > 255) $errors[] = __("title_too_long");
    if (strlen($excerpt) > 500) $errors[] = __("excerpt_too_long");

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update blog post
            $stmt = $pdo->prepare("
                UPDATE blog_posts 
                SET title = ?, slug = ?, content = ?, excerpt = ?, category_id = ?, status = ?, 
                    published_at = ?, meta_title = ?, meta_description = ?, featured_image = ?, updated_at = CURRENT_TIMESTAMP
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
                $featured_image,
                $post_id
            ]);

            // Update tags
            $stmt = $pdo->prepare("DELETE FROM blog_post_tags WHERE post_id = ?");
            $stmt->execute([$post_id]);

            if (!empty($tags)) {
                $stmt = $pdo->prepare("INSERT INTO blog_post_tags (post_id, tag_id) VALUES (?, ?)");
                foreach ($tags as $tag_id) {
                    $stmt->execute([$post_id, $tag_id]);
                }
            }

            $pdo->commit();
            $success_message = __("post_updated_successfully");

            // Update post data for display
            $post['title'] = $title;
            $post['content'] = $content;
            $post['excerpt'] = $excerpt;
            $post['category_id'] = $category_id;
            $post['status'] = $status;
            $post['meta_title'] = $meta_title;
            $post['meta_description'] = $meta_description;
            $post['featured_image'] = $featured_image;
            $post_tags = [];
            if (!empty($tags)) {
                foreach ($tags as $tag_id) {
                    foreach ($all_tags as $tag) {
                        if ($tag['id'] == $tag_id) {
                            $post_tags[$tag['id']] = $tag['name'];
                            break;
                        }
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error_message = __("error_updating_post") . ": " . $e->getMessage();
        }
    }
}

// Handle AJAX auto-save
if (isset($_POST['auto_save'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $excerpt = trim($_POST['excerpt']);

    try {
        $stmt = $pdo->prepare("
            UPDATE blog_posts 
            SET title = ?, content = ?, excerpt = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$title, $content, $excerpt, $post_id]);
        echo json_encode(['success' => true, 'message' => 'Sauvegarde automatique réussie']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur de sauvegarde automatique']);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_post') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        .form-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .main-form {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-light);
        }

        .sidebar-form {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            height: fit-content;
        }

        .image-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            margin-bottom: 1rem;
        }

        .image-upload-area:hover {
            border-color: var(--primary-color);
            background: rgba(0, 150, 136, 0.05);
        }

        .image-upload-area.dragover {
            border-color: var(--primary-color);
            background: rgba(0, 150, 136, 0.1);
        }

        .current-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--border-radius-sm);
            margin-bottom: 1rem;
        }

        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .tag-item {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tag-remove {
            cursor: pointer;
            font-weight: bold;
        }

        .tag-remove:hover {
            color: var(--danger-color);
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .auto-save-indicator {
            font-size: 0.875rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .auto-save-indicator.saving {
            color: var(--warning-color);
        }

        .auto-save-indicator.saved {
            color: var(--success-color);
        }

        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            overflow-y: auto;
        }

        .preview-content {
            background: white;
            margin: 2rem auto;
            max-width: 800px;
            border-radius: var(--border-radius);
            padding: 2rem;
            position: relative;
        }

        .preview-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            cursor: pointer;
            font-size: 1.2rem;
        }

        .slug-preview {
            background: var(--bg-secondary);
            padding: 0.5rem;
            border-radius: var(--border-radius-sm);
            font-family: monospace;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .character-count {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-align: right;
            margin-top: 0.25rem;
        }

        .character-count.warning {
            color: var(--warning-color);
        }

        .character-count.danger {
            color: var(--danger-color);
        }

        @media (max-width: 768px) {
            .form-container {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
    <!-- Include TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
</head>

<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> <?= __('edit_post') ?></h1>
                        <p><?= __('modify_blog_post') ?></p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 20px;">
                        <!-- Language Switcher -->
                        <div class="admin-language-switcher">
                            <div class="admin-language-dropdown">
                                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                                    <i class="fas fa-globe"></i>
                                    <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="admin-language-menu" id="adminLanguageDropdown">
                                    <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                        <span class="language-flag">🇫🇷</span>
                                        <span class="language-name">Français</span>
                                        <?php if (getCurrentLanguage() == 'fr'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                        <span class="language-flag">🇬🇧</span>
                                        <span class="language-name">English</span>
                                        <?php if (getCurrentLanguage() == 'en'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-info" onclick="previewPost()">
                            <i class="fas fa-eye"></i> <?= __('preview') ?>
                        </button>
                        <a href="blog_posts.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?= __('back_to_posts') ?>
                        </a>

                        <!-- User Menu -->
                        <div class="user-menu">
                            <div class="user-avatar"><i class="fas fa-user"></i></div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
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

            <!-- Form Container -->
            <div class="form-container">
                <!-- Main Form -->
                <div class="main-form">
                    <form method="POST" id="blogForm" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="title" class="form-label">Titre *</label>
                            <input type="text" id="title" name="title" class="form-control"
                                value="<?= htmlspecialchars($post['title']) ?>" required maxlength="255">
                            <div class="character-count" id="title-count">0/255</div>
                            <div class="slug-preview" id="slug-preview">
                                URL: /blog/<?= htmlspecialchars($post['slug']) ?>
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

                        <div class="form-group">
                            <label for="excerpt" class="form-label">Extrait *</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" rows="3"
                                placeholder="Résumé court de l'article..." required maxlength="500"><?= htmlspecialchars($post['excerpt']) ?></textarea>
                            <div class="character-count" id="excerpt-count">0/500</div>
                        </div>

                        <div class="form-group">
                            <label for="content" class="form-label">Contenu *</label>
                            <textarea id="content" name="content" class="form-control" required><?= htmlspecialchars($post['content']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="meta_title" class="form-label">Titre SEO</label>
                            <input type="text" id="meta_title" name="meta_title" class="form-control"
                                value="<?= htmlspecialchars($post['meta_title']) ?>"
                                placeholder="Titre pour les moteurs de recherche" maxlength="255">
                            <div class="character-count" id="meta-title-count">0/255</div>
                        </div>

                        <div class="form-group">
                            <label for="meta_description" class="form-label">Description SEO</label>
                            <textarea id="meta_description" name="meta_description" class="form-control" rows="3"
                                placeholder="Description pour les moteurs de recherche" maxlength="500"><?= htmlspecialchars($post['meta_description']) ?></textarea>
                            <div class="character-count" id="meta-description-count">0/500</div>
                        </div>

                        <div class="form-actions">
                            <div class="auto-save-indicator" id="auto-save-indicator">
                                <i class="fas fa-circle"></i>
                                <span>Prêt</span>
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Mettre à Jour
                                </button>
                                <a href="blog_posts.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Annuler
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Sidebar Form -->
                <div class="sidebar-form">
                    <h3><i class="fas fa-image"></i> Image à la Une</h3>

                    <div class="image-upload-area" id="image-upload-area">
                        <?php if ($post['featured_image']): ?>
                            <img src="../<?= htmlspecialchars($post['featured_image']) ?>" alt="Image actuelle" class="current-image">
                            <p>Cliquez pour changer l'image</p>
                        <?php else: ?>
                            <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                            <p>Glissez-déposez une image ou cliquez pour sélectionner</p>
                        <?php endif; ?>
                    </div>
                    <input type="file" id="featured_image" name="featured_image" accept="image/*" style="display: none;">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeImage()" <?= !$post['featured_image'] ? 'style="display:none;"' : '' ?>>
                        <i class="fas fa-trash"></i> Supprimer l'image
                    </button>

                    <h3 style="margin-top: 2rem;"><i class="fas fa-tags"></i> Tags</h3>
                    <div class="form-group">
                        <select id="tag-select" class="form-control">
                            <option value="">Ajouter un tag</option>
                            <?php foreach ($all_tags as $tag): ?>
                                <option value="<?= $tag['id'] ?>" data-name="<?= htmlspecialchars($tag['name']) ?>">
                                    <?= htmlspecialchars($tag['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="tags-container" id="selected-tags">
                        <?php foreach ($post_tags as $tag_id => $tag_name): ?>
                            <div class="tag-item" data-tag-id="<?= $tag_id ?>">
                                <?= htmlspecialchars($tag_name) ?>
                                <span class="tag-remove" onclick="removeTag(<?= $tag_id ?>)">×</span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <h3 style="margin-top: 2rem;"><i class="fas fa-info-circle"></i> Informations</h3>
                    <div class="form-group">
                        <label class="form-label">Auteur</label>
                        <p><?= htmlspecialchars($post['author_name']) ?></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Créé le</label>
                        <p><?= date('d/m/Y à H:i', strtotime($post['created_at'])) ?></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Modifié le</label>
                        <p><?= date('d/m/Y à H:i', strtotime($post['updated_at'])) ?></p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vues</label>
                        <p><?= number_format($post['view_count']) ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="preview-modal" id="preview-modal">
        <div class="preview-content">
            <button class="preview-close" onclick="closePreview()">&times;</button>
            <div id="preview-content"></div>
        </div>
    </div>

    <script>
        // TinyMCE Configuration
        tinymce.init({
            selector: '#content',
            plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount checklist mediaembed casechange export formatpainter pageembed linkchecker a11ychecker tinymcespellchecker permanentpen powerpaste advtable advcode editimage tinycomments tableofcontents footnotes mergetags autocorrect typography inlinecss',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table mergetags | addcomment showcomments | spellcheckdialog a11ycheck typography | align lineheight | checklist numlist bullist indent outdent | emoticons charmap | removeformat',
            tinycomments_mode: 'embedded',
            tinycomments_author: 'Author name',
            mergetags_list: [{
                    value: 'First.Name',
                    title: 'First Name'
                },
                {
                    value: 'Email',
                    title: 'Email'
                },
            ],
            height: 400,
            setup: function(editor) {
                editor.on('change', function() {
                    autoSave();
                });
            }
        });

        // Character counters
        function updateCharacterCount(elementId, maxLength) {
            const element = document.getElementById(elementId);
            const counter = document.getElementById(elementId.replace('-', '-') + '-count');
            const length = element.value.length;

            counter.textContent = `${length}/${maxLength}`;

            if (length > maxLength * 0.9) {
                counter.className = 'character-count danger';
            } else if (length > maxLength * 0.8) {
                counter.className = 'character-count warning';
            } else {
                counter.className = 'character-count';
            }
        }

        // Slug generation
        function generateSlug(title) {
            return title
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .replace(/^-|-$/g, '');
        }

        // Auto-save functionality
        let autoSaveTimeout;

        function autoSave() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                const indicator = document.getElementById('auto-save-indicator');
                indicator.className = 'auto-save-indicator saving';
                indicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Sauvegarde...</span>';

                const formData = new FormData();
                formData.append('auto_save', '1');
                formData.append('title', document.getElementById('title').value);
                formData.append('content', tinymce.get('content').getContent());
                formData.append('excerpt', document.getElementById('excerpt').value);

                fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            indicator.className = 'auto-save-indicator saved';
                            indicator.innerHTML = '<i class="fas fa-check-circle"></i><span>Sauvegardé</span>';
                            setTimeout(() => {
                                indicator.className = 'auto-save-indicator';
                                indicator.innerHTML = '<i class="fas fa-circle"></i><span>Prêt</span>';
                            }, 2000);
                        } else {
                            indicator.className = 'auto-save-indicator';
                            indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Erreur</span>';
                        }
                    })
                    .catch(error => {
                        indicator.className = 'auto-save-indicator';
                        indicator.innerHTML = '<i class="fas fa-exclamation-circle"></i><span>Erreur</span>';
                    });
            }, 2000);
        }

        // Image upload
        const imageUploadArea = document.getElementById('image-upload-area');
        const imageInput = document.getElementById('featured_image');

        imageUploadArea.addEventListener('click', () => imageInput.click());
        imageUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            imageUploadArea.classList.add('dragover');
        });
        imageUploadArea.addEventListener('dragleave', () => {
            imageUploadArea.classList.remove('dragover');
        });
        imageUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            imageUploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                imageInput.files = files;
                handleImageUpload(files[0]);
            }
        });

        imageInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImageUpload(e.target.files[0]);
            }
        });

        function handleImageUpload(file) {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    imageUploadArea.innerHTML = `<img src="${e.target.result}" alt="Nouvelle image" class="current-image"><p>Cliquez pour changer l'image</p>`;
                    document.querySelector('button[onclick="removeImage()"]').style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }

        function removeImage() {
            imageUploadArea.innerHTML = '<i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: 1rem;"></i><p>Glissez-déposez une image ou cliquez pour sélectionner</p>';
            imageInput.value = '';
            document.querySelector('button[onclick="removeImage()"]').style.display = 'none';
        }

        // Tags management
        const tagSelect = document.getElementById('tag-select');
        const selectedTags = document.getElementById('selected-tags');
        const selectedTagIds = new Set(<?= json_encode(array_keys($post_tags)) ?>);

        tagSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            if (option.value && !selectedTagIds.has(parseInt(option.value))) {
                addTag(parseInt(option.value), option.dataset.name);
                this.selectedIndex = 0;
            }
        });

        function addTag(tagId, tagName) {
            selectedTagIds.add(tagId);
            const tagElement = document.createElement('div');
            tagElement.className = 'tag-item';
            tagElement.dataset.tagId = tagId;
            tagElement.innerHTML = `${tagName} <span class="tag-remove" onclick="removeTag(${tagId})">×</span>`;
            selectedTags.appendChild(tagElement);
        }

        function removeTag(tagId) {
            selectedTagIds.delete(tagId);
            const tagElement = document.querySelector(`[data-tag-id="${tagId}"]`);
            if (tagElement) {
                tagElement.remove();
            }
        }

        // Update form with selected tags
        document.getElementById('blogForm').addEventListener('submit', function() {
            const hiddenInputs = document.querySelectorAll('input[name="tags[]"]');
            hiddenInputs.forEach(input => input.remove());

            selectedTagIds.forEach(tagId => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tags[]';
                input.value = tagId;
                this.appendChild(input);
            });
        });

        // Event listeners for character counting and slug generation
        document.getElementById('title').addEventListener('input', function() {
            updateCharacterCount('title', 255);
            document.getElementById('slug-preview').textContent = 'URL: /blog/' + generateSlug(this.value);
        });

        document.getElementById('excerpt').addEventListener('input', function() {
            updateCharacterCount('excerpt', 500);
        });

        document.getElementById('meta_title').addEventListener('input', function() {
            updateCharacterCount('meta_title', 255);
        });

        document.getElementById('meta_description').addEventListener('input', function() {
            updateCharacterCount('meta_description', 500);
        });

        // Preview functionality
        function previewPost() {
            const title = document.getElementById('title').value;
            const content = tinymce.get('content').getContent();
            const excerpt = document.getElementById('excerpt').value;
            const category = document.getElementById('category_id').selectedOptions[0]?.text || 'Non catégorisé';
            const status = document.getElementById('status').value;

            const previewContent = document.getElementById('preview-content');
            previewContent.innerHTML = `
                <article style="max-width: 100%;">
                    <header style="margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color);">
                        <h1 style="color: var(--primary-color); margin-bottom: 1rem;">${title}</h1>
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.9rem;">
                            <span><i class="fas fa-folder"></i> ${category}</span>
                            <span><i class="fas fa-calendar"></i> ${new Date().toLocaleDateString('fr-FR')}</span>
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($post['author_name']) ?></span>
                        </div>
                        <p style="font-size: 1.1rem; color: var(--text-secondary); line-height: 1.6;">${excerpt}</p>
                    </header>
                    <div style="line-height: 1.8; color: var(--text-primary);">
                        ${content}
                    </div>
                    <footer style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                        <div class="tags-container">
                            ${Array.from(selectedTagIds).map(tagId => {
                                const tagElement = document.querySelector(`[data-tag-id="${tagId}"]`);
                                return tagElement ? `<span class="tag-item">${tagElement.textContent.replace('×', '').trim()}</span>` : '';
                            }).join('')}
                        </div>
                    </footer>
                </article>
            `;

            document.getElementById('preview-modal').style.display = 'block';
        }

        function closePreview() {
            document.getElementById('preview-modal').style.display = 'none';
        }

        // Close preview on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePreview();
            }
        });

        // Initialize character counts
        document.addEventListener('DOMContentLoaded', function() {
            updateCharacterCount('title', 255);
            updateCharacterCount('excerpt', 500);
            updateCharacterCount('meta_title', 255);
            updateCharacterCount('meta_description', 500);
        });
    </script>
</body>

</html>