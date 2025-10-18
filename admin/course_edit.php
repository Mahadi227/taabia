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

$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$course = null;
$success_message = '';
$error_message = '';
$enrollment_count = 0;
$total_revenue = 0;
$completion_rate = 0;
$lessons_count = 0;

// Get course data
if ($course_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();

        if ($course) {
            // Get enrollment count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $enrollment_count = $stmt->fetchColumn();

            // Get lessons count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_contents WHERE course_id = ?");
            $stmt->execute([$course_id]);
            $lessons_count = $stmt->fetchColumn();

            // Calculate total revenue
            $total_revenue = $course['price'] * $enrollment_count;

            // Get completion rate
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE course_id = ? AND status = 'completed'");
            $stmt->execute([$course_id]);
            $completed_count = $stmt->fetchColumn();
            $completion_rate = $enrollment_count > 0 ? round(($completed_count / $enrollment_count) * 100, 1) : 0;
        }
    } catch (PDOException $e) {
        error_log("Database error in admin/course_edit.php: " . $e->getMessage());
    }
}

if (!$course) {
    redirect('courses.php');
}

// Get instructors for dropdown
try {
    $instructors = $pdo->query("SELECT id, full_name FROM users WHERE role = 'instructor' AND is_active = 1 ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in admin/course_edit.php: " . $e->getMessage());
    $instructors = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $instructor_id = (int) ($_POST['instructor_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $remove_image = isset($_POST['remove_image']);
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
            // Handle image removal or upload (validate first)
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
                // Update base fields
                $stmt = $pdo->prepare("
                    UPDATE courses 
                    SET title = ?, description = ?, price = ?, instructor_id = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");

                if ($stmt->execute([$title, $description, $price, $instructor_id, $status, $course_id])) {
                    // Determine image column
                    $columns = $pdo->query("SHOW COLUMNS FROM courses")->fetchAll(PDO::FETCH_COLUMN);
                    $imgCol = null;
                    if (in_array('image_url', $columns, true)) {
                        $imgCol = 'image_url';
                    } elseif (in_array('thumbnail_url', $columns, true)) {
                        $imgCol = 'thumbnail_url';
                    }

                    if ($imgCol) {
                        // Existing image filename
                        $currentImage = $course[$imgCol] ?? null;

                        if ($remove_image && $currentImage) {
                            $upd = $pdo->prepare("UPDATE courses SET {$imgCol} = NULL WHERE id = ?");
                            $upd->execute([$course_id]);
                            // Attempt to delete file
                            $path = realpath(__DIR__ . '/../uploads/' . $currentImage);
                            if ($path && is_file($path)) {
                                @unlink($path);
                            }
                        }

                        if ($uploaded_image_filename) {
                            $upd = $pdo->prepare("UPDATE courses SET {$imgCol} = ? WHERE id = ?");
                            $upd->execute([$uploaded_image_filename, $course_id]);
                            // Delete old file if replaced
                            if ($currentImage) {
                                $path = realpath(__DIR__ . '/../uploads/' . $currentImage);
                                if ($path && is_file($path)) {
                                    @unlink($path);
                                }
                            }
                        }
                    }

                    $success_message = __("course_updated_successfully");

                    // Refresh course data
                    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
                    $stmt->execute([$course_id]);
                    $course = $stmt->fetch();
                } else {
                    $error_message = __("error_updating_course");
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Database error in course_edit: " . $e->getMessage());
        $error_message = __("database_error");
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_course') ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Styles -->
    <link rel="stylesheet" href="admin-styles.css">
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
                        <h1><i class="fas fa-edit"></i> <?= __('edit_course') ?></h1>
                        <p><?= __('modify_course_info') ?></p>
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

                        <div class="d-flex gap-2">
                            <a href="view_course.php?id=<?= $course['id'] ?>" class="btn btn-info">
                                <i class="fas fa-eye"></i> <?= __('view') ?>
                            </a>
                            <a href="courses.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                            </a>
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
                                        value="<?= htmlspecialchars($course['title']) ?>" required
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
                                        <option value="draft" <?= $course['status'] === 'draft' ? 'selected' : '' ?>><?= __('draft') ?></option>
                                        <option value="published" <?= $course['status'] === 'published' ? 'selected' : '' ?>><?= __('published') ?></option>
                                        <option value="archived" <?= $course['status'] === 'archived' ? 'selected' : '' ?>><?= __('archived') ?></option>
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
                                        value="<?= htmlspecialchars($course['price']) ?>" step="0.01" min="0" required
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
                                                <?= $course['instructor_id'] == $instructor['id'] ? 'selected' : '' ?>>
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
                                        placeholder="<?= __('course_description') ?>"><?= htmlspecialchars($course['description']) ?></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label"><i class="fas fa-image"></i> <?= __('cover_image') ?></label>
                                    <?php
                                    // Determine image column for preview
                                    $imgPreview = $course['image_url'] ?? ($course['thumbnail_url'] ?? null);
                                    if ($imgPreview): ?>
                                        <div class="course-image-container" style="margin-bottom: .5rem;">
                                            <img src="../uploads/<?= htmlspecialchars($imgPreview) ?>"
                                                alt="<?= __('image_preview') ?>"
                                                id="currentImagePreview"
                                                style="max-width: 100%; border-radius: 8px; box-shadow: var(--shadow-light); cursor: pointer;"
                                                onclick="openImageModal('../uploads/<?= htmlspecialchars($imgPreview) ?>')">
                                            <div class="image-overlay">
                                                <i class="fas fa-search-plus"></i>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="image-upload-section">
                                        <input type="file" name="course_image" id="course_image" class="form-control" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this)">
                                        <small class="text-muted">JPG/PNG/WEBP, 5MB max.</small>

                                        <!-- New image preview -->
                                        <div id="newImagePreview" style="display: none; margin-top: 1rem;">
                                            <img id="previewImg" src="" alt="<?= __('image_preview') ?>" style="max-width: 100%; max-height: 200px; border-radius: 8px; box-shadow: var(--shadow-light);">
                                            <div style="margin-top: 0.5rem;">
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearImagePreview()">
                                                    <i class="fas fa-times"></i> <?= __('remove') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if ($imgPreview): ?>
                                        <div style="margin-top:.5rem;">
                                            <label style="display:inline-flex; align-items:center; gap:.5rem;">
                                                <input type="checkbox" name="remove_image" id="removeImageCheckbox" onchange="toggleImageRemoval()"> <?= __('remove_current_image') ?>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex gap-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?= __('save_changes') ?>
                                    </button>

                                    <a href="view_course.php?id=<?= $course['id'] ?>" class="btn btn-info">
                                        <i class="fas fa-eye"></i> <?= __('view_course_btn') ?>
                                    </a>

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

            <!-- Course Information -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> <?= __('course_information') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong><?= __('course_id') ?>:</strong>
                                <span>#<?= $course['id'] ?></span>
                            </div>
                            <div class="info-item">
                                <strong><?= __('creation_date') ?>:</strong>
                                <span><?= date('d/m/Y H:i', strtotime($course['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <strong><?= __('last_modified') ?>:</strong>
                                <span><?= isset($course['updated_at']) ? date('d/m/Y H:i', strtotime($course['updated_at'])) : __('not_modified') ?></span>
                            </div>
                            <div class="info-item">
                                <strong><?= __('current_status') ?>:</strong>
                                <span class="badge <?php
                                                    switch ($course['status']) {
                                                        case 'published':
                                                            echo 'badge-success';
                                                            break;
                                                        case 'draft':
                                                            echo 'badge-warning';
                                                            break;
                                                        case 'archived':
                                                            echo 'badge-danger';
                                                            break;
                                                        default:
                                                            echo 'badge-secondary';
                                                            break;
                                                    }
                                                    ?>">
                                    <?php
                                    switch ($course['status']) {
                                        case 'published':
                                            echo __('published');
                                            break;
                                        case 'draft':
                                            echo __('draft');
                                            break;
                                        case 'archived':
                                            echo __('archived');
                                            break;
                                        default:
                                            echo htmlspecialchars($course['status']);
                                            break;
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Course Statistics -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> <?= __('statistics') ?></h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($enrollment_count) ?></div>
                                    <div class="stat-label"><?= __('enrollments') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($lessons_count) ?></div>
                                    <div class="stat-label"><?= __('lessons') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">GHS <?= number_format($total_revenue, 2) ?></div>
                                    <div class="stat-label"><?= __('total_revenue') ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #9c27b0, #ba68c8);">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= $completion_rate ?>%</div>
                                    <div class="stat-label"><?= __('completion_rate') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
        <div class="modal-content" onclick="event.stopPropagation()">
            <span class="close" onclick="closeImageModal()">&times;</span>
            <img id="modalImage" src="" alt="<?= __('course_image_alt') ?>" style="width: 100%; max-height: 90vh; object-fit: contain;">
        </div>
    </div>

    <script>
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
        });
    </script>

    <style>
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        /* Statistics Styling */
        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .stat-content .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .stat-content .stat-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        /* Image Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: relative;
            margin: 5% auto;
            padding: 20px;
            width: 90%;
            max-width: 800px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
        }

        .close {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            z-index: 2001;
        }

        .close:hover,
        .close:focus {
            color: var(--primary-color);
        }

        /* Course Image Enhancement */
        .course-image-container {
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .course-image-container img {
            transition: transform 0.3s ease;
        }

        .course-image-container:hover img {
            transform: scale(1.05);
        }

        .image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .course-image-container:hover .image-overlay {
            opacity: 1;
        }

        .image-overlay i {
            color: white;
            font-size: 2rem;
        }

        /* Image Upload Section */
        .image-upload-section {
            margin-top: 1rem;
        }

        #newImagePreview {
            border: 2px dashed var(--border-color);
            border-radius: var(--border-radius);
            padding: 1rem;
            text-align: center;
            background: var(--bg-light);
        }

        /* Enhanced Form Styling */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.1);
            outline: none;
        }

        /* Enhanced Button Styling */
        .btn {
            border-radius: var(--border-radius-sm);
            font-weight: 500;
            transition: var(--transition);
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-outline-danger {
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
            background: transparent;
        }

        .btn-outline-danger:hover {
            background: var(--danger-color);
            color: white;
        }

        .info-item strong {
            color: var(--text-primary);
        }

        .info-item span {
            color: var(--text-secondary);
        }
    </style>

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

        // Image Modal Functions
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = 'block';
            modalImg.src = imageSrc;
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restore scrolling
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeImageModal();
            }
        });

        // Image Preview Functions
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('newImagePreview');
                    const previewImg = document.getElementById('previewImg');
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearImagePreview() {
            const preview = document.getElementById('newImagePreview');
            const fileInput = document.getElementById('course_image');
            const previewImg = document.getElementById('previewImg');

            preview.style.display = 'none';
            previewImg.src = '';
            fileInput.value = '';
        }

        function toggleImageRemoval() {
            const checkbox = document.getElementById('removeImageCheckbox');
            const currentImage = document.getElementById('currentImagePreview');
            const fileInput = document.getElementById('course_image');

            if (checkbox.checked) {
                if (currentImage) {
                    currentImage.style.opacity = '0.5';
                    currentImage.style.filter = 'grayscale(100%)';
                }
                fileInput.disabled = true;
            } else {
                if (currentImage) {
                    currentImage.style.opacity = '1';
                    currentImage.style.filter = 'none';
                }
                fileInput.disabled = false;
            }
        }

        // Enhanced Form Validation
        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const instructor = document.getElementById('instructor_id').value;
            const status = document.getElementById('status').value;

            let isValid = true;
            let errorMessage = '';

            if (!title) {
                errorMessage += '<?= __('title_required') ?>\n';
                isValid = false;
            } else if (title.length < 3) {
                errorMessage += '<?= __('title_min_length') ?>\n';
                isValid = false;
            }

            if (!description) {
                errorMessage += '<?= __('description_required') ?>\n';
                isValid = false;
            } else if (description.length < 10) {
                errorMessage += '<?= __('description_min_length') ?>\n';
                isValid = false;
            }

            if (isNaN(price) || price < 0) {
                errorMessage += '<?= __('price_required') ?>\n';
                isValid = false;
            }

            if (!instructor) {
                errorMessage += '<?= __('instructor_required') ?>\n';
                isValid = false;
            }

            if (!status) {
                errorMessage += '<?= __('status_required') ?>\n';
                isValid = false;
            }

            if (!isValid) {
                alert(errorMessage);
            }

            return isValid;
        }

        // Add smooth scrolling for better UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading animation for statistics
            const statItems = document.querySelectorAll('.stat-item');
            statItems.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    item.style.transition = 'all 0.6s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Enhanced form submission
            const form = document.getElementById('courseForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return false;
                    }

                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('saving') ?>...';
                        submitBtn.disabled = true;
                    }
                });
            }

            // Auto-save draft functionality (optional)
            let autoSaveTimeout;
            const formInputs = form.querySelectorAll('input, textarea, select');
            formInputs.forEach(input => {
                input.addEventListener('input', function() {
                    clearTimeout(autoSaveTimeout);
                    autoSaveTimeout = setTimeout(() => {
                        // Auto-save logic could be implemented here
                        console.log('Auto-save triggered');
                    }, 5000); // Auto-save after 5 seconds of inactivity
                });
            });
        });
    </script>
</body>

</html>