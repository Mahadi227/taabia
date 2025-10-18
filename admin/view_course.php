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
$instructor = null;
$lessons = [];
$enrollments = [];
$enrollment_count = 0;
$total_revenue = 0;
$completion_rate = 0;

if ($course_id > 0) {
    try {
        // Get course details with instructor info
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name as instructor_name, u.email as instructor_email
            FROM courses c 
            LEFT JOIN users u ON c.instructor_id = u.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch();

        if ($course) {
            // Get lessons for this course
            $stmt = $pdo->prepare("
                SELECT id, title, content as description, content_type, order_index, created_at
                FROM course_contents 
                WHERE course_id = ? 
                ORDER BY order_index ASC
            ");
            $stmt->execute([$course_id]);
            $lessons = $stmt->fetchAll();

            // Get enrollment count and revenue
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as enrollment_count
                FROM student_courses 
                WHERE course_id = ?
            ");
            $stmt->execute([$course_id]);
            $enrollment_count = $stmt->fetchColumn();

            // Get total revenue
            $total_revenue = $course['price'] * $enrollment_count;

            // Get completion rate (if we have progress tracking)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as completed_count
                FROM student_courses 
                WHERE course_id = ? AND status = 'completed'
            ");
            $stmt->execute([$course_id]);
            $completed_count = $stmt->fetchColumn();
            $completion_rate = $enrollment_count > 0 ? round(($completed_count / $enrollment_count) * 100, 1) : 0;

            // Get recent enrollments
            $stmt = $pdo->prepare("
                SELECT sc.*, u.full_name, u.email
                FROM student_courses sc
                LEFT JOIN users u ON sc.student_id = u.id
                WHERE sc.course_id = ?
                ORDER BY sc.enrolled_at DESC
                LIMIT 5
            ");
            $stmt->execute([$course_id]);
            $enrollments = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Database error in admin/view_course.php: " . $e->getMessage());
    }
}

if (!$course) {
    redirect('courses.php');
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('view_course') ?> | <?= __('admin_panel') ?> | TaaBia</title>

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
                        <h1><i class="fas fa-eye"></i> <?= __('course_details') ?></h1>
                        <p><?= __('course_complete_info') ?></p>
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
                            <a href="course_edit.php?id=<?= $course['id'] ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i> <?= __('edit') ?>
                            </a>
                            <a href="course_toggle.php?id=<?= $course['id'] ?>&action=<?= $course['status'] === 'published' ? 'archive' : 'publish' ?>"
                                class="btn <?= $course['status'] === 'published' ? 'btn-warning' : 'btn-success' ?>"
                                onclick="return confirm('<?= $course['status'] === 'published' ? __('confirm_archive') : __('confirm_publish') ?>')">
                                <i class="fas <?= $course['status'] === 'published' ? 'fa-archive' : 'fa-check' ?>"></i>
                                <?= $course['status'] === 'published' ? __('archive') : __('publish') ?>
                            </a>
                            <a href="course_delete.php?id=<?= $course['id'] ?>" class="btn btn-danger"
                                onclick="return confirm('<?= __('confirm_delete_course') ?>')">
                                <i class="fas fa-trash"></i> <?= __('delete') ?>
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
            <!-- Course Information -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-book"></i> <?= __('course_information') ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="course-header">
                                <h2><?= htmlspecialchars($course['title']) ?></h2>
                                <div class="course-meta">
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
                                    <span class="price">
                                        <?php if ((float)($course['price'] ?? 0) <= 0): ?>
                                            <?= __('free') ?>
                                        <?php else: ?>
                                            GHS <?= number_format($course['price'], 2) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <?php
                            $imgPreview = $course['image_url'] ?? ($course['thumbnail_url'] ?? null);
                            if ($imgPreview): ?>
                                <div style="margin-bottom: 1rem;">
                                    <img src="../uploads/<?= htmlspecialchars($imgPreview) ?>"
                                        alt="<?= __('course_image_alt') ?>"
                                        style="width:100%; max-height: 320px; object-fit: cover; border-radius: var(--border-radius); box-shadow: var(--shadow-light); cursor: pointer;"
                                        onclick="openImageModal('../uploads/<?= htmlspecialchars($imgPreview) ?>')">
                                </div>
                            <?php endif; ?>
                            <div class="course-description">
                                <h4><?= __('description') ?></h4>
                                <p><?= nl2br(htmlspecialchars($course['description'])) ?></p>
                            </div>

                            <div class="course-details">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <strong><?= __('instructor') ?>:</strong>
                                            <span><?= htmlspecialchars($course['instructor_name'] ?? __('not_assigned')) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong><?= __('instructor_email') ?>:</strong>
                                            <span><?= htmlspecialchars($course['instructor_email'] ?? __('not_available')) ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="detail-item">
                                            <strong><?= __('creation_date') ?>:</strong>
                                            <span><?= date('d/m/Y H:i', strtotime($course['created_at'])) ?></span>
                                        </div>
                                        <div class="detail-item">
                                            <strong><?= __('last_modified') ?>:</strong>
                                            <span><?= isset($course['updated_at']) ? date('d/m/Y H:i', strtotime($course['updated_at'])) : __('not_modified') ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Statistics -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-bar"></i> <?= __('statistics') ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #4caf50, #66bb6a);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format($enrollment_count) ?></div>
                                    <div class="stat-label"><?= __('enrollments') ?></div>
                                </div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #2196f3, #42a5f5);">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number"><?= number_format(count($lessons)) ?></div>
                                    <div class="stat-label"><?= __('lessons') ?></div>
                                </div>
                            </div>

                            <div class="stat-item">
                                <div class="stat-icon" style="background: linear-gradient(45deg, #ff9800, #ffb74d);">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number">GHS <?= number_format($total_revenue, 2) ?></div>
                                    <div class="stat-label"><?= __('total_revenue') ?></div>
                                </div>
                            </div>

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

            <!-- Lessons -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-play-circle"></i> <?= __('lessons') ?> (<?= count($lessons) ?>)</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($lessons)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-2"><?= __('no_lessons_available') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= __('order') ?></th>
                                        <th><?= __('lesson_title') ?></th>
                                        <th><?= __('lesson_description') ?></th>
                                        <th><?= __('lesson_type') ?></th>
                                        <th><?= __('creation_date') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lessons as $lesson): ?>
                                        <tr>
                                            <td><?= $lesson['order_index'] ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($lesson['title']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars(substr($lesson['description'], 0, 100)) ?>...</td>
                                            <td>
                                                <span class="badge badge-info"><?= htmlspecialchars($lesson['content_type'] ?? __('text')) ?></span>
                                            </td>
                                            <td><?= date('d/m/Y', strtotime($lesson['created_at'])) ?></td>
                                            <td>
                                                <div class="lesson-actions">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewLesson(<?= $lesson['id'] ?>)" title="<?= __('view') ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="editLesson(<?= $lesson['id'] ?>)" title="<?= __('edit') ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Enrollments -->
            <div class="card mt-4">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> <?= __('recent_enrollments') ?></h3>
                </div>
                <div class="card-body">
                    <?php if (empty($enrollments)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-info-circle" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-2"><?= __('no_enrollments') ?></p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?= __('student') ?></th>
                                        <th><?= __('email') ?></th>
                                        <th><?= __('enrollment_date') ?></th>
                                        <th><?= __('status') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollments as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($enrollment['full_name']) ?></strong>
                                            </td>
                                            <td><?= htmlspecialchars($enrollment['email']) ?></td>
                                            <td><?= date('d/m/Y H:i', strtotime($enrollment['enrolled_at'])) ?></td>
                                            <td>
                                                <span class="badge badge-success"><?= __('enrolled') ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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

    <style>
        .course-header {
            margin-bottom: 2rem;
        }

        .course-header h2 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .course-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .course-meta .price {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--success-color);
        }

        .course-description {
            margin-bottom: 2rem;
        }

        .course-description h4 {
            margin-bottom: 1rem;
            color: var(--text-primary);
        }

        .course-description p {
            line-height: 1.6;
            color: var(--text-secondary);
        }

        .course-details {
            background: var(--bg-light);
            padding: 1.5rem;
            border-radius: var(--border-radius);
        }

        .detail-item {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .detail-item strong {
            color: var(--text-primary);
        }

        .detail-item span {
            color: var(--text-secondary);
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
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

        /* Lesson Actions */
        .lesson-actions {
            display: flex;
            gap: 5px;
        }

        .lesson-actions .btn {
            padding: 4px 8px;
            font-size: 12px;
        }

        /* Enhanced Statistics */
        .stat-item {
            transition: transform 0.2s ease;
        }

        .stat-item:hover {
            transform: translateY(-2px);
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

        // Lesson Management Functions
        function viewLesson(lessonId) {
            // Open lesson in a new tab or modal
            window.open(`lesson_view.php?id=${lessonId}`, '_blank');
        }

        function editLesson(lessonId) {
            // Redirect to lesson edit page
            window.location.href = `lesson_edit.php?id=${lessonId}&course_id=<?= $course_id ?>`;
        }

        // Enhanced Course Actions
        function archiveCourse() {
            if (confirm('<?= __('confirm_archive') ?>')) {
                window.location.href = `course_toggle.php?id=<?= $course_id ?>&action=archive`;
            }
        }

        function publishCourse() {
            if (confirm('<?= __('confirm_publish') ?>')) {
                window.location.href = `course_toggle.php?id=<?= $course_id ?>&action=publish`;
            }
        }

        function deleteCourse() {
            if (confirm('<?= __('confirm_delete_course') ?>')) {
                window.location.href = `course_delete.php?id=<?= $course_id ?>`;
            }
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
        });
    </script>
</body>

</html>