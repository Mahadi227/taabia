<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get existing user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$instructor_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../auth/logout.php');
        exit;
    }

    $success_message = '';
    $error_message = '';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $image = $user['profile_image'];

        // Validation
        if (empty($name) || empty($email)) {
            $error_message = __('all_fields_required');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = __('invalid_email');
        } else {
            // Image upload
            if (!empty($_FILES['profile_image']['name'])) {
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed_types)) {
                    $error_message = __('invalid_image_format');
                } elseif ($_FILES['profile_image']['size'] > 5 * 1024 * 1024) { // 5MB
                    $error_message = __('file_too_large');
                } else {
                    $filename = 'instructor_' . $instructor_id . '_' . time() . '.' . $ext;
                    $destination = '../uploads/' . $filename;

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
                        $image = $filename;
                    } else {
                        $error_message = __('upload_error');
                    }
                }
            }

            if (empty($error_message)) {
                // Update user information
                $update = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, profile_image = ? WHERE id = ?");
                $update->execute([$name, $email, $image, $instructor_id]);

                $_SESSION['name'] = $name;
                $success_message = __('profile_updated_successfully');
                
                // Refresh user data
                $stmt->execute([$instructor_id]);
                $user = $stmt->fetch();
            }
        }
    }

} catch (PDOException $e) {
    error_log("Database error in instructor edit_profile: " . $e->getMessage());
    $error_message = __('database_error');
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['lang'] ?? 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_profile') ?> | TaaBia</title>
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
                <p><?= __('instructor_space') ?></p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus"></i>
                    <?= __('new_course') ?>
                </a>
                <a href="add_lesson.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <?= __('add') ?> <?= __('lesson') ?>
                </a>
                <a href="attendance_management.php" class="instructor-nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <?= __('attendance') ?>
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    <?= __('my_students') ?>
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    <?= __('pending_submissions') ?>
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-coins"></i>
                    <?= __('my_earnings') ?>
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-exchange-alt"></i>
                    <?= __('transactions') ?>
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= __('payouts') ?>
                </a>
                <a href="profile.php" class="instructor-nav-item active">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?>
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('edit_profile') ?></h1>
                        <p><?= __('edit_profile_description') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="instructor-alert instructor-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="instructor-alert instructor-alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <!-- Profile Form -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6);">
                    <form method="post" enctype="multipart/form-data" id="profileForm">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                            <div>
                                <label for="full_name" style="display: block; margin-bottom: var(--spacing-2); font-weight: 600; color: var(--gray-700);">
                                    <i class="fas fa-user"></i> <?= __('full_name') ?> *
                                </label>
                                <input type="text" name="full_name" id="full_name" 
                                       value="<?= htmlspecialchars($user['full_name']) ?>" 
                                       required 
                                       class="instructor-search-input"
                                       placeholder="<?= __('enter_full_name') ?>">
                            </div>
                            
                            <div>
                                <label for="email" style="display: block; margin-bottom: var(--spacing-2); font-weight: 600; color: var(--gray-700);">
                                    <i class="fas fa-envelope"></i> <?= __('email_address') ?> *
                                </label>
                                <input type="email" name="email" id="email" 
                                       value="<?= htmlspecialchars($user['email']) ?>" 
                                       required 
                                       class="instructor-search-input"
                                       placeholder="<?= __('enter_email') ?>">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: var(--spacing-6);">
                            <label for="profile_image" style="display: block; margin-bottom: var(--spacing-2); font-weight: 600; color: var(--gray-700);">
                                <i class="fas fa-camera"></i> <?= __('profile_image') ?>
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
                                             alt="<?= __('profile_image') ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i class="fas fa-user" style="font-size: var(--font-size-xl); color: var(--gray-400);"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div style="flex: 1;">
                                    <input type="file" name="profile_image" id="profile_image" 
                                           accept="image/*" 
                                           class="instructor-search-input"
                                           style="padding: var(--spacing-2);">
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                        <?= __('accepted_formats') ?>: JPG, PNG, GIF (<?= __('max_size') ?> 5MB)
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: var(--spacing-4); align-items: center; flex-wrap: wrap;">
                            <button type="submit" class="instructor-btn instructor-btn-primary">
                                <i class="fas fa-save"></i>
                                <?= __('save_changes') ?>
                            </button>
                            
                            <a href="profile.php" class="instructor-btn instructor-btn-secondary">
                                <i class="fas fa-times"></i>
                                <?= __('cancel') ?>
                            </a>
                            
                            <a href="change_password.php" class="instructor-btn instructor-btn-success">
                                <i class="fas fa-key"></i>
                                <?= __('change_password') ?>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="instructor-table-container" style="margin-top: var(--spacing-6);">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-info-circle"></i> <?= __('account_information') ?>
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
                                <i class="fas fa-calendar"></i> <?= __('registration_date') ?>
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
                                <i class="fas fa-user-tag"></i> <?= __('role') ?>
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
                                <i class="fas fa-toggle-on"></i> <?= __('status') ?>
                            </div>
                            <div style="color: var(--gray-600);">
                                <?= $user['is_active'] ? __('active') : __('inactive') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="profile.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_profile') ?>
                </a>
                
                <a href="change_password.php" class="instructor-btn instructor-btn-warning">
                    <i class="fas fa-key"></i>
                    <?= __('change_password') ?>
                </a>
                
                <a href="my_courses.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
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
                    alert('<?= __('please_enter_full_name') ?>');
                    nameInput.focus();
                    return;
                }
                
                if (!email) {
                    e.preventDefault();
                    alert('<?= __('please_enter_email') ?>');
                    emailInput.focus();
                    return;
                }
                
                if (!email.includes('@')) {
                    e.preventDefault();
                    alert('<?= __('please_enter_valid_email') ?>');
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
                                img.parentElement.innerHTML = `<img src="${e.target.result}" alt="<?= __('profile_image') ?>" style="width: 100%; height: 100%; object-fit: cover;">`;
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