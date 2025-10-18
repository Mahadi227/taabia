<?php
// Start session first to ensure proper language handling
require_once '../includes/session.php';

// Handle language switching
require_once '../includes/language_handler.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get instructor's courses
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.status,
               COUNT(l.id) as lesson_count,
               COUNT(sc.student_id) as enrollment_count
        FROM courses c
        LEFT JOIN lessons l ON c.id = l.course_id
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id
        ORDER BY c.title ASC
    ");
    $stmt->execute([$instructor_id]);
    $courses = $stmt->fetchAll();

    $success_message = '';
    $error_message = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $course_id = $_POST['course_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $video_url = trim($_POST['video_url'] ?? '');
        $lesson_order = (int)($_POST['lesson_order'] ?? 0);
        $lesson_type = $_POST['lesson_type'] ?? 'text';

        // Validation
        if (empty($course_id)) {
            $error_message = __("please_select_course");
        } elseif (empty($title)) {
            $error_message = __("lesson_title_required");
        } elseif (strlen($title) < 3) {
            $error_message = __("title_min_length");
        } else {
            // Verify course belongs to instructor
            $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
            $stmt->execute([$course_id, $instructor_id]);
            if ($stmt->rowCount() == 0) {
                $error_message = __("invalid_course");
            } else {
                // Insert lesson - Updated to match the actual table structure
                $stmt = $pdo->prepare("
                    INSERT INTO lessons (course_id, title, content, file_url, order_index, content_type, is_active, created_at, description) 
                    VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), ?)
                ");
                $stmt->execute([$course_id, $title, $content, $video_url, $lesson_order, $lesson_type, $content]);

                $success_message = __("lesson_added_successfully");

                // Clear form data
                $title = $content = $video_url = '';
                $lesson_order = 0;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in add_lesson: " . $e->getMessage());
    $error_message = __("error_occurred_try_again");
    $courses = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('add_lesson') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
    <style>
        /* Hamburger Menu Styles */
        .hamburger-menu-btn {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            transition: all 0.3s ease;
        }

        .hamburger-line {
            width: 100%;
            height: 3px;
            background: var(--primary-color, #2563eb);
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }

        .hamburger-menu-btn.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        .hamburger-menu-btn:hover .hamburger-line {
            background: var(--primary-dark, #1d4ed8);
        }

        /* Responsive Sidebar */
        .instructor-sidebar {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .instructor-sidebar.mobile-hidden {
            transform: translateX(-100%);
        }

        .instructor-sidebar.mobile-visible {
            transform: translateX(0);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        /* Mobile Overlay */
        .mobile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Enhanced Form Styles */
        .lesson-form {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .lesson-form-group {
            margin-bottom: 25px;
        }

        .lesson-form-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .lesson-form-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--gray-200);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
        }

        .lesson-form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .lesson-form-textarea {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }

        .lesson-form-select {
            cursor: pointer;
        }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .hamburger-menu-btn {
                display: flex;
            }

            .instructor-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                z-index: 1000;
                transform: translateX(-100%);
                box-shadow: none;
            }

            .instructor-sidebar.mobile-visible {
                transform: translateX(0);
                box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            }

            .instructor-main {
                margin-left: 0;
                width: 100%;
            }

            .instructor-header {
                padding-left: 60px;
            }

            .lesson-form {
                padding: 20px;
                margin: 0 15px 20px 15px;
            }

            .instructor-cards {
                grid-template-columns: 1fr;
                gap: 15px;
                margin: 0 15px 20px 15px;
            }

            .instructor-language-switcher {
                margin-top: 10px;
            }
        }

        @media (max-width: 480px) {
            .hamburger-menu-btn {
                top: 15px;
                left: 15px;
                width: 25px;
                height: 25px;
            }

            .hamburger-line {
                height: 2px;
            }

            .instructor-header {
                padding: 20px 15px 20px 50px;
            }

            .lesson-form {
                padding: 15px;
                margin: 0 10px 15px 10px;
            }

            .instructor-header>div {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Animation for smooth transitions */
        .instructor-layout {
            transition: all 0.3s ease;
        }

        /* Focus styles for accessibility */
        .hamburger-menu-btn:focus {
            outline: 2px solid var(--primary-color, #2563eb);
            outline-offset: 2px;
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Mobile Overlay -->
        <div class="mobile-overlay" id="mobileOverlay"></div>

        <!-- Sidebar -->
        <div class="instructor-sidebar" id="sidebar">
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
                <a href="add_lesson.php" class="instructor-nav-item active">
                    <i class="fas fa-play-circle"></i>
                    Ajouter une leçon
                </a>
                <a href="attendance_management.php" class="instructor-nav-item">
                    <i class="fas fa-calendar-check"></i>
                    Gestion de la présence
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

        <!-- Mobile Hamburger Menu Button -->
        <button class="hamburger-menu-btn" id="hamburgerMenuBtn" aria-label="<?= __('toggle_navigation') ?>">
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
            <span class="hamburger-line"></span>
        </button>

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('add_lesson') ?></h1>
                        <p><?= __('create_new_lesson_for_courses') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (!isset($success_message)) $success_message = ''; ?>
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

            <!-- Lesson Form -->
            <div class="instructor-form">
                <form method="POST" id="lessonForm">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="instructor-form-group">
                            <label for="course_id" class="lesson-form-label">
                                <i class="fas fa-book"></i> <?= __('associated_course') ?> *
                            </label>
                            <select name="course_id" id="course_id" required class="lesson-form-input lesson-form-select">
                                <option value="">-- <?= __('choose_course') ?> --</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>" <?= isset($_POST['course_id']) && $_POST['course_id'] == $course['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?>
                                        (<?= $course['lesson_count'] ?> leçons, <?= $course['enrollment_count'] ?> inscrits)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="lesson-form-group">
                            <label for="lesson_type" class="lesson-form-label">
                                <i class="fas fa-tag"></i> <?= __('lesson_type') ?>
                            </label>
                            <select name="lesson_type" id="lesson_type" class="lesson-form-input lesson-form-select">
                                <option value="text" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'text') ? 'selected' : '' ?>><?= __('text') ?></option>
                                <option value="video" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'video') ? 'selected' : '' ?>><?= __('video') ?></option>
                                <option value="pdf" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'pdf') ? 'selected' : '' ?>>PDF</option>
                                <option value="quiz" <?= (isset($_POST['lesson_type']) && $_POST['lesson_type'] == 'quiz') ? 'selected' : '' ?>><?= __('quiz') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="lesson-form-group">
                        <label for="title" class="lesson-form-label">
                            <i class="fas fa-heading"></i> <?= __('lesson_title') ?> *
                        </label>
                        <input type="text" name="title" id="title" required
                            class="lesson-form-input"
                            placeholder="<?= __('enter_lesson_title') ?>"
                            value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>

                    <div class="lesson-form-group">
                        <label for="content" class="lesson-form-label">
                            <i class="fas fa-align-left"></i> <?= __('content_description') ?>
                        </label>
                        <textarea name="content" id="content" rows="6"
                            class="lesson-form-input lesson-form-textarea"
                            placeholder="<?= __('describe_lesson_content') ?>"><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-6);">
                        <div class="lesson-form-group">
                            <label for="video_url" class="lesson-form-label">
                                <i class="fas fa-video"></i> <?= __('video_link_optional') ?>
                            </label>
                            <input type="url" name="video_url" id="video_url"
                                class="lesson-form-input"
                                placeholder="<?= __('video_url_placeholder') ?>"
                                value="<?= htmlspecialchars($_POST['video_url'] ?? '') ?>">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                <?= __('video_platforms_supported') ?>
                            </div>
                        </div>

                        <div class="lesson-form-group">
                            <label for="lesson_order" class="lesson-form-label">
                                <i class="fas fa-sort-numeric-up"></i> <?= __('display_order') ?>
                            </label>
                            <input type="number" name="lesson_order" id="lesson_order"
                                min="0" value="<?= $_POST['lesson_order'] ?? 0 ?>"
                                class="lesson-form-input">
                            <div style="font-size: var(--font-size-sm); color: var(--gray-500); margin-top: var(--spacing-1);">
                                <?= __('lesson_order_description') ?>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button type="submit" class="instructor-btn instructor-btn-primary">
                            <i class="fas fa-plus"></i>
                            <?= __('add_lesson') ?>
                        </button>

                        <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            <?= __('cancel') ?>
                        </a>

                        <div style="margin-left: auto; font-size: var(--font-size-sm); color: var(--gray-500);">
                            <i class="fas fa-info-circle"></i>
                            <?= __('lesson_will_be_added_to_selected_course') ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Course Information -->
            <?php if (count($courses) > 0): ?>
                <div class="instructor-table-container" style="margin-top: var(--spacing-8);">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-info-circle"></i> Vos cours disponibles
                        </h3>
                    </div>

                    <div style="padding: var(--spacing-6);">
                        <div class="instructor-course-grid">
                            <?php foreach ($courses as $course): ?>
                                <div class="instructor-course-card">
                                    <div class="instructor-course-image">
                                        <i class="fas fa-book"></i>
                                    </div>

                                    <div class="instructor-course-content">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-3);">
                                            <h3 class="instructor-course-title">
                                                <?= htmlspecialchars($course['title']) ?>
                                            </h3>
                                            <span class="instructor-badge <?= $course['status'] == 'published' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($course['status']) ?>
                                            </span>
                                        </div>

                                        <div class="instructor-course-stats">
                                            <span>
                                                <i class="fas fa-play-circle"></i>
                                                <?= $course['lesson_count'] ?> leçons
                                            </span>
                                            <span>
                                                <i class="fas fa-users"></i>
                                                <?= $course['enrollment_count'] ?> inscrits
                                            </span>
                                        </div>

                                        <div class="instructor-course-footer">
                                            <a href="course_lessons.php?course_id=<?= $course['id'] ?>"
                                                class="instructor-btn instructor-btn-primary"
                                                style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                                <i class="fas fa-play"></i>
                                                Gérer les leçons
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Retour aux cours
                </a>

                <a href="add_course.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-plus"></i>
                    Créer un nouveau cours
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('lessonForm');
            const titleInput = document.getElementById('title');
            const contentInput = document.getElementById('content');

            // Form validation
            form.addEventListener('submit', function(e) {
                const title = titleInput.value.trim();
                const courseId = document.getElementById('course_id').value;

                if (!courseId) {
                    e.preventDefault();
                    alert('Veuillez sélectionner un cours.');
                    return;
                }

                if (!title) {
                    e.preventDefault();
                    alert('Veuillez saisir un titre pour la leçon.');
                    titleInput.focus();
                    return;
                }

                if (title.length < 3) {
                    e.preventDefault();
                    alert('Le titre doit contenir au moins 3 caractères.');
                    titleInput.focus();
                    return;
                }
            });

            // Auto-resize textarea
            contentInput.addEventListener('input', function() {
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

        // Hamburger Menu Functionality
        function initializeHamburgerMenu() {
            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            let isMenuOpen = false;

            function toggleMenu() {
                isMenuOpen = !isMenuOpen;

                if (isMenuOpen) {
                    hamburgerBtn.classList.add('active');
                    sidebar.classList.remove('mobile-hidden');
                    sidebar.classList.add('mobile-visible');
                    mobileOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            function closeMenu() {
                if (isMenuOpen) {
                    isMenuOpen = false;
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }

            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleMenu);
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMenu);
            }

            const sidebarLinks = document.querySelectorAll('.instructor-nav-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', closeMenu);
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeMenu();
                    document.body.style.overflow = '';
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isMenuOpen) {
                    closeMenu();
                }
            });

            if (window.innerWidth <= 768) {
                sidebar.classList.add('mobile-hidden');
            }
        }

        // Initialize hamburger menu
        initializeHamburgerMenu();

        // Form enhancement
        function enhanceForm() {
            const form = document.getElementById('lessonForm');
            const inputs = form.querySelectorAll('input, textarea, select');

            inputs.forEach(input => {
                // Add real-time validation
                input.addEventListener('blur', function() {
                    validateField(this);
                });

                // Add focus styles
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });

                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
        }

        function validateField(field) {
            const value = field.value.trim();
            const fieldName = field.name;
            let isValid = true;
            let message = '';

            switch (fieldName) {
                case 'title':
                    if (value.length < 3) {
                        isValid = false;
                        message = '<?= __('title_min_length') ?>';
                    }
                    break;
                case 'course_id':
                    if (!value) {
                        isValid = false;
                        message = '<?= __('please_select_course') ?>';
                    }
                    break;
            }

            // Remove existing validation message
            const existingMessage = field.parentElement.querySelector('.validation-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            if (!isValid) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'validation-message';
                errorDiv.style.color = 'var(--danger-color)';
                errorDiv.style.fontSize = '12px';
                errorDiv.style.marginTop = '4px';
                errorDiv.textContent = message;
                field.parentElement.appendChild(errorDiv);
                field.style.borderColor = 'var(--danger-color)';
            } else {
                field.style.borderColor = 'var(--success-color)';
            }

            return isValid;
        }

        // Initialize form enhancement
        enhanceForm();
    </script>
</body>

</html>