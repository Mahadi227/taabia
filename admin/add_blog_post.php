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

// Get categories for dropdown
try {
    $categories = $pdo->query("SELECT * FROM blog_categories WHERE status = 'active' ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $categories = [];
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
    if (empty($title)) $errors[] = __("title_required");
    if (empty($content)) $errors[] = __("content_required");
    if (empty($excerpt)) $errors[] = __("excerpt_required");

    // Handle image upload
    $featured_image = null;
    if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/blog/';

        // Create upload directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_info = $_FILES['featured_image'];
        $file_extension = strtolower(pathinfo($file_info['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        // Validate file extension
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Format d'image non supporté. Utilisez JPG, PNG, GIF ou WebP.";
        }

        // Validate file size (max 5MB)
        if ($file_info['size'] > 5 * 1024 * 1024) {
            $errors[] = "L'image ne doit pas dépasser 5MB.";
        }

        // Validate image type
        $image_info = getimagesize($file_info['tmp_name']);
        if ($image_info === false) {
            $errors[] = "Le fichier n'est pas une image valide.";
        }

        if (empty($errors)) {
            // Generate unique filename
            $filename = uniqid() . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $filename;

            // Move uploaded file
            if (move_uploaded_file($file_info['tmp_name'], $upload_path)) {
                $featured_image = 'blog/' . $filename;
            } else {
                $errors[] = __("image_upload_error");
            }
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO blog_posts (title, slug, content, excerpt, author_id, category_id, status, published_at, meta_title, meta_description, featured_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;

            $stmt->execute([
                $title,
                $slug,
                $content,
                $excerpt,
                $_SESSION['user_id'],
                $category_id,
                $status,
                $published_at,
                $meta_title,
                $meta_description,
                $featured_image
            ]);

            $success_message = __("post_created_successfully");

            // Clear form data
            $title = $content = $excerpt = $meta_title = $meta_description = '';
            $category_id = '';
            $status = 'draft';
        } catch (PDOException $e) {
            $error_message = __("error_creating_post");
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('new_post') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        /* File Upload Styles */
        .file-upload-container {
            position: relative;
            margin-bottom: 1rem;
        }

        .file-input {
            display: none;
        }

        .file-upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .file-upload-label:hover {
            border-color: #009688;
            background: #f0fdfa;
        }

        .file-upload-label i {
            font-size: 2rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .file-upload-text {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.25rem;
        }

        .file-upload-hint {
            color: #6b7280;
            font-size: 0.875rem;
        }

        .image-preview {
            position: relative;
            margin-top: 1rem;
            display: inline-block;
        }

        .image-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.3s ease;
        }

        .remove-image:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        .file-upload-label.dragover {
            border-color: #009688;
            background: #f0fdfa;
            transform: scale(1.02);
        }

        /* Admin Language Switcher Styles */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .admin-language-btn:hover {
            background: var(--gray-100);
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--gray-100);
        }

        .admin-language-item.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .language-flag {
            font-size: 1rem;
        }

        .language-name {
            flex: 1;
        }

        .admin-language-item i {
            color: var(--success-color);
            font-size: 0.75rem;
        }
    </style>
    <!-- Include TinyMCE -->
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
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
            height: 400
        });

        // Image upload and preview functionality
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-img').src = e.target.result;
                    document.getElementById('image-preview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeImage() {
            document.getElementById('featured_image').value = '';
            document.getElementById('image-preview').style.display = 'none';
            document.getElementById('preview-img').src = '';
        }

        // Drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('featured_image');
            const fileLabel = document.querySelector('.file-upload-label');

            // Drag and drop events
            fileLabel.addEventListener('dragover', function(e) {
                e.preventDefault();
                fileLabel.classList.add('dragover');
            });

            fileLabel.addEventListener('dragleave', function(e) {
                e.preventDefault();
                fileLabel.classList.remove('dragover');
            });

            fileLabel.addEventListener('drop', function(e) {
                e.preventDefault();
                fileLabel.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImage(fileInput);
                }
            });

            // Click to upload
            fileLabel.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
        });

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (dropdown && button && !button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
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
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-plus"></i> <?= __('new_post') ?></h1>
                        <p><?= __('create_new_blog_post') ?></p>
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

            <!-- Form -->
            <div class="card">
                <form method="POST" class="form" enctype="multipart/form-data">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="title" class="form-label"><?= __('title') ?> *</label>
                            <input type="text" id="title" name="title" class="form-control"
                                value="<?= htmlspecialchars($title ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category_id" class="form-label"><?= __('category') ?></label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value=""><?= __('select_category') ?></option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"
                                        <?= ($category_id ?? '') == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="status" class="form-label"><?= __('status') ?></label>
                            <select id="status" name="status" class="form-control">
                                <option value="draft" <?= ($status ?? 'draft') === 'draft' ? 'selected' : '' ?>><?= __('draft') ?></option>
                                <option value="published" <?= ($status ?? 'draft') === 'published' ? 'selected' : '' ?>><?= __('published') ?></option>
                                <option value="archived" <?= ($status ?? 'draft') === 'archived' ? 'selected' : '' ?>><?= __('archived') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="featured_image" class="form-label"><?= __('featured_image') ?></label>
                            <div class="file-upload-container">
                                <input type="file" id="featured_image" name="featured_image" class="file-input"
                                    accept="image/*" onchange="previewImage(this)">
                                <label for="featured_image" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span class="file-upload-text">Choisir une image</span>
                                    <small class="file-upload-hint">JPG, PNG, GIF, WebP (max 5MB)</small>
                                </label>
                                <div id="image-preview" class="image-preview" style="display: none;">
                                    <img id="preview-img" src="" alt="Aperçu">
                                    <button type="button" class="remove-image" onclick="removeImage()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="excerpt" class="form-label"><?= __('excerpt') ?> *</label>
                            <textarea id="excerpt" name="excerpt" class="form-control" rows="3"
                                placeholder="Résumé court de l'article..." required><?= htmlspecialchars($excerpt ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="content" class="form-label"><?= __('content') ?> *</label>
                            <textarea id="content" name="content" class="form-control" required><?= htmlspecialchars($content ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="meta_title" class="form-label"><?= __('seo_title') ?></label>
                            <input type="text" id="meta_title" name="meta_title" class="form-control"
                                value="<?= htmlspecialchars($meta_title ?? '') ?>"
                                placeholder="Titre pour les moteurs de recherche">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="meta_description" class="form-label"><?= __('seo_description') ?></label>
                            <textarea id="meta_description" name="meta_description" class="form-control" rows="3"
                                placeholder="Description pour les moteurs de recherche"><?= htmlspecialchars($meta_description ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= __('create_post') ?>
                        </button>
                        <a href="blog_posts.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> <?= __('cancel') ?>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>