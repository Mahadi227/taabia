<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('admin');

$success_message = '';
$error_message = '';

// Récupérer les formateurs
try {
    $instructors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'instructor' AND is_active = 1 ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in admin/add_course.php: " . $e->getMessage());
    $instructors = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $instructor_id = (int) ($_POST['instructor_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $uploaded_image_filename = null;

        // Validation
        if (empty($title)) {
            $error_message = __("title_required");
        } elseif (strlen($title) < 3) {
            $error_message = __("title_min_length");
        } elseif (empty($description)) {
            $error_message = __("description_required");
        } elseif (strlen($description) < 10) {
            $error_message = __("description_min_length");
        } elseif ($price <= 0) {
            $error_message = __("price_required");
        } elseif (empty($instructor_id)) {
            $error_message = __("instructor_required");
        } elseif (empty($status)) {
            $error_message = __("status_required");
        } else {
            // Optional image upload handling
            if (isset($_FILES['course_image']) && is_array($_FILES['course_image']) && ($_FILES['course_image']['error'] !== UPLOAD_ERR_NO_FILE)) {
                $file = $_FILES['course_image'];
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $error_message = __("image_upload_error") . " (code: " . (int)$file['error'] . ").";
                } else {
                    $allowedMime = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp'
                    ];
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = $finfo->file($file['tmp_name']);
                    if (!isset($allowedMime[$mime])) {
                        $error_message = __("image_format_error");
                    } elseif ($file['size'] > 5 * 1024 * 1024) {
                        $error_message = __("image_size_error");
                    } else {
                        $uploadsDir = realpath(__DIR__ . '/../uploads');
                        if ($uploadsDir === false) {
                            $error_message = __("upload_folder_error");
                        } else {
                            $ext = $allowedMime[$mime];
                            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower(pathinfo($file['name'], PATHINFO_FILENAME)));
                            $unique = time() . '_' . bin2hex(random_bytes(4));
                            $filename = "course_{$unique}_{$safeBase}.{$ext}";
                            $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $filename;
                            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                                $error_message = __("image_save_error");
                            } else {
                                @chmod($targetPath, 0644);
                                $uploaded_image_filename = $filename;
                            }
                        }
                    }
                }
            }

            if (empty($error_message)) {
                // Insert course and then update image column if provided
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    INSERT INTO courses (title, description, price, instructor_id, status, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                if ($stmt->execute([$title, $description, $price, $instructor_id, $status])) {
                    $courseId = (int)$pdo->lastInsertId();
                    if ($uploaded_image_filename) {
                        // Determine which image column exists: image_url or thumbnail_url
                        $columns = $pdo->query("SHOW COLUMNS FROM courses")->fetchAll(PDO::FETCH_COLUMN);
                        $imgCol = null;
                        if (in_array('image_url', $columns, true)) {
                            $imgCol = 'image_url';
                        } elseif (in_array('thumbnail_url', $columns, true)) {
                            $imgCol = 'thumbnail_url';
                        }
                        if ($imgCol) {
                            $upd = $pdo->prepare("UPDATE courses SET {$imgCol} = ? WHERE id = ?");
                            $upd->execute([$uploaded_image_filename, $courseId]);
                        }
                    }
                    $pdo->commit();
                    $success_message = __("course_created_successfully");
                    // Clear form data
                    $title = $description = $price = $instructor_id = $status = '';
                } else {
                    $pdo->rollBack();
                    $error_message = __("error_creating_course");
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in add_course: " . $e->getMessage());
        $error_message = __("database_error");
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_course') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">

    <style>
        /* Admin Language Switcher */
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
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: white;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--light-color);
        }

        .admin-language-item.active {
            background: var(--primary-color);
            color: white;
        }

        .language-flag {
            font-size: 16px;
        }

        .language-name {
            flex: 1;
            font-size: 14px;
        }

        .admin-language-item i {
            font-size: 12px;
            margin-left: auto;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-plus-circle"></i> <?= __('add_course') ?></h1>
                        <p><?= __('create_course') ?></p>
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

                        <div class="user-menu">
                            <?php
                            $current_user = null;
                            try {
                                $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
                                $stmt->execute([current_user_id()]);
                                $current_user = $stmt->fetch();
                            } catch (PDOException $e) {
                                error_log("Error fetching current user: " . $e->getMessage());
                            }
                            ?>
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success mb-4">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger mb-4">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Course Form -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-book"></i> <?= __('course_information') ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="courseForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <label for="title" class="form-label">
                                        <i class="fas fa-heading"></i> <?= __('course_title') ?> *
                                    </label>
                                    <input type="text" name="title" id="title" class="form-control"
                                        value="<?= htmlspecialchars($title ?? '') ?>" required
                                        placeholder="<?= __('course_title') ?>">
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <label for="status" class="form-label">
                                        <i class="fas fa-toggle-on"></i> <?= __('course_status') ?> *
                                    </label>
                                    <select name="status" id="status" class="form-control" required>
                                        <option value="">-- <?= __('choose_status') ?> --</option>
                                        <option value="draft" <?= ($status ?? '') === 'draft' ? 'selected' : '' ?>><?= __('draft') ?></option>
                                        <option value="published" <?= ($status ?? '') === 'published' ? 'selected' : '' ?>><?= __('published') ?></option>
                                        <option value="archived" <?= ($status ?? '') === 'archived' ? 'selected' : '' ?>><?= __('archived') ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="price" class="form-label">
                                        <i class="fas fa-money-bill-wave"></i> <?= __('course_price') ?> (GHS) *
                                    </label>
                                    <input type="number" name="price" id="price" class="form-control"
                                        value="<?= htmlspecialchars($price ?? '') ?>" step="0.01" min="0" required
                                        placeholder="0.00">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="instructor_id" class="form-label">
                                        <i class="fas fa-user-tie"></i> <?= __('course_instructor') ?> *
                                    </label>
                                    <select name="instructor_id" id="instructor_id" class="form-control" required>
                                        <option value="">-- <?= __('choose_instructor') ?> --</option>
                                        <?php foreach ($instructors as $instructor): ?>
                                            <option value="<?= $instructor['id'] ?>"
                                                <?= ($instructor_id ?? '') == $instructor['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($instructor['full_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="description" class="form-label">
                                        <i class="fas fa-align-left"></i> <?= __('course_description') ?> *
                                    </label>
                                    <textarea name="description" id="description" class="form-control" rows="6"
                                        placeholder="<?= __('course_description') ?>"><?= htmlspecialchars($description ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="course_image" class="form-label">
                                        <i class="fas fa-image"></i> <?= __('cover_image') ?> (JPG/PNG/WEBP, max 5MB)
                                    </label>
                                    <input type="file" name="course_image" id="course_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                    <small class="text-muted"><?= __('image_optional') ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> <?= __('create_course') ?>
                                    </button>

                                    <a href="courses.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                                    </a>

                                    <button type="reset" class="btn btn-warning">
                                        <i class="fas fa-undo"></i> <?= __('reset') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-lightning-bolt"></i> <?= __('quick_actions') ?></h3>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-3 flex-wrap">
                        <a href="courses.php" class="btn btn-outline-primary">
                            <i class="fas fa-list"></i> <?= __('view_all_courses') ?>
                        </a>

                        <a href="add_product.php" class="btn btn-outline-success">
                            <i class="fas fa-plus"></i> <?= __('add_product') ?>
                        </a>

                        <a href="add_event.php" class="btn btn-outline-info">
                            <i class="fas fa-calendar-plus"></i> <?= __('create_event') ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> <?= __('course_statistics') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php
                        try {
                            // Total courses
                            $stmt = $pdo->query("SELECT COUNT(*) FROM courses");
                            $total_courses = $stmt->fetchColumn();

                            // Published courses
                            $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'");
                            $published_courses = $stmt->fetchColumn();

                            // Draft courses
                            $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'draft'");
                            $draft_courses = $stmt->fetchColumn();

                            // Active instructors
                            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND is_active = 1");
                            $active_instructors = $stmt->fetchColumn();
                        } catch (PDOException $e) {
                            error_log("Database error in add_course.php stats: " . $e->getMessage());
                            $total_courses = $published_courses = $draft_courses = $active_instructors = 0;
                        }
                        ?>

                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($total_courses) ?></div>
                                    <div class="stat-label"><?= __('total_courses') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($published_courses) ?></div>
                                    <div class="stat-label"><?= __('published_courses') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-edit"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($draft_courses) ?></div>
                                    <div class="stat-label"><?= __('draft_courses') ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="stat-card">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #9c27b0, #ba68c8);">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($active_instructors) ?></div>
                                    <div class="stat-label"><?= __('active_instructors') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close admin language dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const switcher = document.querySelector('.admin-language-switcher');

            if (switcher && !switcher.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('courseForm');
            const priceInput = document.getElementById('price');
            const titleInput = document.getElementById('title');
            const descriptionInput = document.getElementById('description');

            // Price validation
            priceInput.addEventListener('input', function() {
                const value = parseFloat(this.value);
                if (value < 0) {
                    this.value = 0;
                }
            });

            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const description = descriptionInput.value.trim();
                const price = parseFloat(priceInput.value);
                const instructor = document.getElementById('instructor_id').value;
                const status = document.getElementById('status').value;

                if (!title) {
                    e.preventDefault();
                    alert('<?= __('title_required') ?>');
                    titleInput.focus();
                    return;
                }

                if (title.length < 3) {
                    e.preventDefault();
                    alert('<?= __('title_min_length') ?>');
                    titleInput.focus();
                    return;
                }

                if (!description) {
                    e.preventDefault();
                    alert('<?= __('description_required') ?>');
                    descriptionInput.focus();
                    return;
                }

                if (description.length < 10) {
                    e.preventDefault();
                    alert('<?= __('description_min_length') ?>');
                    descriptionInput.focus();
                    return;
                }

                if (price <= 0) {
                    e.preventDefault();
                    alert('<?= __('price_required') ?>');
                    priceInput.focus();
                    return;
                }

                if (!instructor) {
                    e.preventDefault();
                    alert('<?= __('instructor_required') ?>');
                    document.getElementById('instructor_id').focus();
                    return;
                }

                if (!status) {
                    e.preventDefault();
                    alert('<?= __('status_required') ?>');
                    document.getElementById('status').focus();
                    return;
                }
            });

            // Auto-save draft functionality
            let autoSaveTimer;
            const inputs = [titleInput, descriptionInput, priceInput];

            inputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        // Here you could implement auto-save functionality
                        console.log('Auto-save triggered...');
                    }, 2000);
                });
            });
        });
    </script>
</body>

</html>