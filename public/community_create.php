<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$current_user_id = current_user_id();
$current_role = $_SESSION['role'];

// Check if user can create communities (admin, instructor, or students/vendors with permission)
if (!has_role('admin') && !has_role('instructor')) {
    // For students and vendors, check if they have permission to create communities
    // This could be a setting in the system_settings table
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_student_communities'");
    $stmt->execute();
    $allow_student_communities = $stmt->fetch()['setting_value'] ?? '0';

    if ($allow_student_communities !== '1') {
        redirect('communities.php');
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $privacy = sanitize($_POST['privacy']);
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];

    try {
        $pdo->beginTransaction();

        // Create community
        $stmt = $pdo->prepare("
            INSERT INTO communities (name, description, created_by, privacy) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$name, $description, $current_user_id, $privacy]);
        $community_id = $pdo->lastInsertId();

        // Add creator as admin
        $stmt = $pdo->prepare("
            INSERT INTO community_members (community_id, user_id, role) 
            VALUES (?, ?, 'admin')
        ");
        $stmt->execute([$community_id, $current_user_id]);

        // Assign categories
        if (!empty($categories)) {
            $stmt = $pdo->prepare("
                INSERT INTO community_category_assignments (community_id, category_id) 
                VALUES (?, ?)
            ");
            foreach ($categories as $category_id) {
                $stmt->execute([$community_id, $category_id]);
            }
        }

        $pdo->commit();
        $success_message = t('community_created_successfully');

        // Redirect to the new community
        redirect("community.php?id=$community_id");
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = t('error_creating_community') . ': ' . $e->getMessage();
    }
}

// Get community categories
$stmt = $pdo->prepare("SELECT * FROM community_categories WHERE is_active = 1 ORDER BY sort_order, name");
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('create_community'); ?> - TaaBia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .create-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .form-card {
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        .category-card {
            transition: transform 0.2s;
            cursor: pointer;
        }

        .category-card:hover {
            transform: translateY(-2px);
        }

        .category-card.selected {
            border-color: #007bff;
            background-color: #f8f9ff;
        }
    </style>
</head>

<body>
    <?php include '../includes/modern_ui.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/public_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Create Community Header -->
                <div class="create-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1><i class="fas fa-plus"></i> <?php echo t('create_community'); ?></h1>
                            <p class="mb-0"><?php echo t('create_community_description'); ?></p>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="communities.php" class="btn btn-light">
                                <i class="fas fa-arrow-left"></i> <?php echo t('back_to_communities'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <form method="POST" class="card form-card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit"></i> <?php echo t('community_details'); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="name" class="form-label"><?php echo t('community_name'); ?> *</label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                        placeholder="<?php echo t('enter_community_name'); ?>">
                                    <div class="form-text"><?php echo t('choose_descriptive_name'); ?></div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label"><?php echo t('description'); ?></label>
                                    <textarea class="form-control" id="description" name="description" rows="4"
                                        placeholder="<?php echo t('describe_your_community'); ?>"></textarea>
                                    <div class="form-text"><?php echo t('help_people_understand'); ?></div>
                                </div>

                                <div class="mb-3">
                                    <label for="privacy" class="form-label"><?php echo t('privacy'); ?> *</label>
                                    <select class="form-select" id="privacy" name="privacy" required>
                                        <option value="public"><?php echo t('public'); ?> - <?php echo t('anyone_can_join'); ?></option>
                                        <option value="private"><?php echo t('private'); ?> - <?php echo t('approval_required'); ?></option>
                                        <option value="invite_only"><?php echo t('invite_only'); ?> - <?php echo t('invitation_required'); ?></option>
                                    </select>
                                    <div class="form-text"><?php echo t('privacy_description'); ?></div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-4">
                        <!-- Categories -->
                        <div class="card form-card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-tags"></i> <?php echo t('categories'); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small"><?php echo t('select_relevant_categories'); ?></p>
                                <div class="row">
                                    <?php foreach ($categories as $category): ?>
                                        <div class="col-12 mb-2">
                                            <div class="card category-card" onclick="toggleCategory(<?php echo $category['id']; ?>)">
                                                <div class="card-body p-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            name="categories[]" value="<?php echo $category['id']; ?>"
                                                            id="cat_<?php echo $category['id']; ?>">
                                                        <label class="form-check-label" for="cat_<?php echo $category['id']; ?>">
                                                            <i class="<?php echo $category['icon']; ?>" style="color: <?php echo $category['color']; ?>"></i>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Guidelines -->
                        <div class="card form-card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle"></i> <?php echo t('community_guidelines'); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        <?php echo t('be_respectful'); ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        <?php echo t('follow_platform_rules'); ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        <?php echo t('moderate_content'); ?>
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i>
                                        <?php echo t('encourage_engagement'); ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card form-card">
                            <div class="card-body text-center">
                                <button type="submit" form="community-form" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus"></i> <?php echo t('create_community'); ?>
                                </button>
                                <a href="communities.php" class="btn btn-outline-secondary btn-lg ms-2">
                                    <i class="fas fa-times"></i> <?php echo t('cancel'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleCategory(categoryId) {
            const checkbox = document.getElementById('cat_' + categoryId);
            const card = checkbox.closest('.category-card');

            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        }

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const nameInput = document.getElementById('name');
            const privacySelect = document.getElementById('privacy');

            form.addEventListener('submit', function(e) {
                if (!nameInput.value.trim()) {
                    e.preventDefault();
                    alert('<?php echo t('community_name_required'); ?>');
                    nameInput.focus();
                    return;
                }

                if (!privacySelect.value) {
                    e.preventDefault();
                    alert('<?php echo t('privacy_required'); ?>');
                    privacySelect.focus();
                    return;
                }
            });
        });
    </script>
</body>

</html>





