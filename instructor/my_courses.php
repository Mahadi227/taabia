<?php
// Start session first to ensure proper language handling
require_once '../includes/session.php';

// Handle language switching
require_once '../includes/language_handler.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Search and filter functionality
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'recent';

// Build the query with filters
$where_conditions = ["c.instructor_id = ?"];
$params = [$instructor_id];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "c.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Order by clause
$order_clause = match ($sort_by) {
    'recent' => 'c.created_at DESC',
    'oldest' => 'c.created_at ASC',
    'name' => 'c.title ASC',
    'enrollments' => 'enrollment_count DESC',
    'progress' => 'avg_progress DESC',
    default => 'c.created_at DESC'
};

try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(sc.student_id) as enrollment_count,
               AVG(sc.progress_percent) as avg_progress,
               COUNT(l.id) as lesson_count,
               CASE 
                               WHEN c.status = 'published' THEN 'success'
            WHEN c.status = 'draft' THEN 'warning'
            WHEN c.status = 'archived' THEN 'danger'
                   ELSE 'info'
               END as status_class
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        LEFT JOIN lessons l ON c.id = l.course_id
        WHERE $where_clause
        GROUP BY c.id
        ORDER BY $order_clause
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll();


    // Get statistics
    $total_courses = count($courses);
    $published_courses = array_filter($courses, fn($c) => $c['status'] == 'published');
    $draft_courses = array_filter($courses, fn($c) => $c['status'] == 'draft');
    $archived_courses = array_filter($courses, fn($c) => $c['status'] == 'archived');

    $total_enrollments = array_sum(array_column($courses, 'enrollment_count'));
    $avg_progress = $total_courses > 0 ? array_sum(array_column($courses, 'avg_progress')) / $total_courses : 0;
} catch (PDOException $e) {
    error_log("Database error in my_courses: " . $e->getMessage());
    $courses = [];
    $total_courses = 0;
    $active_courses = [];
    $draft_courses = [];
    $inactive_courses = [];
    $total_enrollments = 0;
    $avg_progress = 0;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_courses') ?> | TaaBia</title>
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
            background: var(--primary-color, rgb(210, 12, 12));
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
            background: var(--primary-dark, rgb(209, 14, 14));
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

            /* Adjust language switcher for mobile */
            .instructor-language-switcher {
                margin-top: 10px;
            }

            /* Mobile-friendly cards */
            .instructor-cards {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .instructor-course-grid {
                grid-template-columns: 1fr;
            }

            /* Mobile-friendly filters */
            .instructor-filters {
                flex-direction: column;
                gap: 10px;
            }

            .instructor-search {
                flex-direction: column;
                gap: 10px;
            }

            .instructor-search-input,
            .instructor-filter-select {
                width: 100%;
                margin-bottom: 0;
            }

            /* Mobile-friendly buttons */
            .instructor-btn {
                padding: 10px 15px;
                font-size: 14px;
            }

            /* Dynamic button enhancements */
            .instructor-course-footer .instructor-btn {
                position: relative;
                overflow: hidden;
                transition: all 0.3s ease;
                cursor: pointer;
                pointer-events: auto;
                z-index: 10;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .instructor-course-footer .instructor-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                text-decoration: none;
            }

            .instructor-course-footer .instructor-btn:active {
                transform: translateY(0);
            }

            .instructor-course-footer .instructor-btn::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
                transition: left 0.5s;
                pointer-events: none;
            }

            .instructor-course-footer .instructor-btn:hover::before {
                left: 100%;
            }

            /* Count badges in buttons */
            .instructor-course-footer .instructor-btn {
                font-weight: 600;
            }

            .instructor-course-footer .instructor-btn i {
                margin-right: 0.5rem;
            }

            /* Ensure buttons are clickable */
            .instructor-course-footer a {
                pointer-events: auto !important;
                cursor: pointer !important;
                position: relative !important;
                z-index: 100 !important;
            }

            /* Override any conflicting styles */
            .instructor-course-card a {
                pointer-events: auto !important;
                cursor: pointer !important;
            }

            /* Ensure no overlay is blocking clicks */
            .instructor-course-card::before,
            .instructor-course-card::after {
                pointer-events: none !important;
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

            .instructor-header h1 {
                font-size: 1.5rem;
            }

            .instructor-header p {
                font-size: 0.9rem;
            }

            /* Stack header content vertically on very small screens */
            .instructor-header>div {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Tablet Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .instructor-sidebar {
                width: 250px;
            }

            .instructor-main {
                margin-left: 250px;
            }

            .instructor-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        /* Animation for smooth transitions */
        .instructor-layout {
            transition: all 0.3s ease;
        }

        /* Focus styles for accessibility */
        .hamburger-menu-btn:focus {
            outline: 2px solid var(--primary-color, #004085);
            outline-offset: 2px;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .hamburger-line {
                background: #ffffff;
            }

            .hamburger-menu-btn:hover .hamburger-line {
                background: #e5e7eb;
            }

            .mobile-overlay {
                background: rgba(0, 0, 0, 0.7);
            }
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
                <p><?= __('instructor_space') ?></p>
            </div>

            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item active">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?>
                </a>
                <a href="add_course.php" class="instructor-nav-item">
                    <i class="fas fa-plus-circle"></i>
                    <?= __('new_course') ?>
                </a>
                <a href="students.php" class="instructor-nav-item">
                    <i class="fas fa-users"></i>
                    <?= __('my_students') ?>
                </a>
                <a href="validate_submissions.php" class="instructor-nav-item">
                    <i class="fas fa-check-circle"></i>
                    <?= __('assignments_to_validate') ?>
                </a>
                <a href="earnings.php" class="instructor-nav-item">
                    <i class="fas fa-chart-line"></i>
                    <?= __('my_earnings') ?>
                </a>
                <a href="transactions.php" class="instructor-nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('transactions') ?>
                </a>
                <a href="payouts.php" class="instructor-nav-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <?= __('payments') ?>
                </a>
                <a href="profile.php" class="instructor-nav-item">
                    <i class="fas fa-user"></i>
                    <?= __('profile') ?>
                </a>
                <a href="../auth/logout.php" class="instructor-nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?>
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
                        <h1><?= __('my_courses') ?></h1>
                        <p><?= __('manage_courses_performance') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="instructor-cards">
                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon primary">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_courses') ?></div>
                    <div class="instructor-card-value"><?= $total_courses ?></div>
                    <div class="instructor-card-description"><?= __('courses_created') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('published_courses') ?></div>
                    <div class="instructor-card-value"><?= count($published_courses) ?></div>
                    <div class="instructor-card-description"><?= __('published_courses') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-edit"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('drafts') ?></div>
                    <div class="instructor-card-value"><?= count($draft_courses) ?></div>
                    <div class="instructor-card-description"><?= __('in_creation') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('total_enrolled') ?></div>
                    <div class="instructor-card-value"><?= $total_enrollments ?></div>
                    <div class="instructor-card-description"><?= __('enrolled_students') ?></div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="instructor-filters">
                <form method="GET" class="instructor-search">
                    <input
                        type="text"
                        name="search"
                        placeholder="<?= __('search_course_placeholder') ?>"
                        value="<?= htmlspecialchars($search) ?>"
                        class="instructor-search-input">

                    <select name="status" class="instructor-filter-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>><?= __('all_statuses') ?></option>
                        <option value="published" <?= $status_filter === 'published' ? 'selected' : '' ?>><?= __('published') ?></option>
                        <option value="draft" <?= $status_filter === 'draft' ? 'selected' : '' ?>><?= __('drafts') ?></option>
                        <option value="archived" <?= $status_filter === 'archived' ? 'selected' : '' ?>><?= __('archived') ?></option>
                    </select>

                    <select name="sort" class="instructor-filter-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>><?= __('most_recent') ?></option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>><?= __('oldest') ?></option>
                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>><?= __('name_a_z') ?></option>
                        <option value="enrollments" <?= $sort_by === 'enrollments' ? 'selected' : '' ?>><?= __('most_enrollments') ?></option>
                        <option value="progress" <?= $sort_by === 'progress' ? 'selected' : '' ?>><?= __('best_progress') ?></option>
                    </select>

                    <button type="submit" class="instructor-btn instructor-btn-primary">
                        <i class="fas fa-search"></i>
                        <?= __('search') ?>
                    </button>

                    <?php if (!empty($search) || $status_filter !== 'all' || $sort_by !== 'recent'): ?>
                        <a href="my_courses.php" class="instructor-btn instructor-btn-secondary">
                            <i class="fas fa-times"></i>
                            <?= __('reset') ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Quick Actions -->
            <div style="margin-bottom: var(--spacing-6); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-plus"></i>
                    <?= __('create_new_course') ?>
                </a>

                <a href="course_stats.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-chart-bar"></i>
                    <?= __('detailed_statistics') ?>
                </a>
            </div>

            <!-- Courses Grid -->
            <?php if (count($courses) > 0): ?>
                <div class="instructor-course-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="instructor-course-card">
                            <div class="instructor-course-image" style="height:160px; overflow:hidden; border-top-left-radius: var(--radius-lg); border-top-right-radius: var(--radius-lg); background: <?= ($course['image_url'] ?? ($course['thumbnail_url'] ?? null)) ? 'transparent' : 'linear-gradient(135deg, #009688, #00bcd4)' ?>; display:flex; align-items:center; justify-content:center;">
                                <?php $img = $course['image_url'] ?? ($course['thumbnail_url'] ?? null);
                                if ($img): ?>
                                    <img src="../uploads/<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($course['title']) ?>" style="width:100%; height: 100%; object-fit: cover; display:block;">
                                <?php else: ?>
                                    <i class="fas fa-book" style="color:#fff; font-size: 2rem;"></i>
                                <?php endif; ?>
                            </div>

                            <div class="instructor-course-content">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: var(--spacing-3);">
                                    <h3 class="instructor-course-title">
                                        <?= htmlspecialchars($course['title']) ?>
                                    </h3>
                                    <span class="instructor-badge <?= $course['status_class'] ?>">
                                        <?= ucfirst($course['status']) ?>
                                    </span>
                                </div>

                                <p class="instructor-course-description">
                                    <?= htmlspecialchars(substr($course['description'], 0, 100)) ?>...
                                </p>

                                <div class="instructor-course-stats">
                                    <span title="<?= __('total_enrolled_students') ?? 'Total étudiants inscrits' ?>">
                                        <i class="fas fa-users"></i>
                                        <?= $course['enrollment_count'] ?> <?= __('enrolled') ?>
                                    </span>
                                    <span title="<?= __('total_lessons_in_course') ?? 'Total leçons dans le cours' ?>">
                                        <i class="fas fa-play-circle"></i>
                                        <?= $course['lesson_count'] ?> <?= __('lessons') ?>
                                    </span>
                                    <span title="<?= __('average_student_progress') ?? 'Progression moyenne des étudiants' ?>">
                                        <i class="fas fa-chart-line"></i>
                                        <?= round($course['avg_progress'] ?? 0) ?>% <?= __('progress') ?>
                                    </span>
                                </div>

                                <div class="instructor-course-footer">
                                    <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                        <?= __('created_on') ?> <?= date('d/m/Y', strtotime($course['created_at'])) ?>
                                    </div>

                                    <div style="display: flex; gap: var(--spacing-2);">
                                        <a href="edit_course.php?id=<?= $course['id'] ?>"
                                            class="instructor-btn instructor-btn-secondary"
                                            style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs);">
                                            <i class="fas fa-edit"></i>
                                            <?= __('edit') ?>
                                        </a>

                                        <a href="course_lessons.php?course_id=<?= $course['id'] ?>"
                                            class="instructor-btn instructor-btn-primary"
                                            style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs); cursor: pointer;"
                                            title="<?= __('manage_lessons') ?? 'Gérer les leçons' ?>">
                                            <i class="fas fa-play"></i>
                                            <?= __('lessons') ?> (<?= $course['lesson_count'] ?>)
                                        </a>

                                        <a href="course_students.php?course_id=<?= $course['id'] ?>"
                                            class="instructor-btn instructor-btn-success"
                                            style="padding: var(--spacing-1) var(--spacing-2); font-size: var(--font-size-xs); cursor: pointer;"
                                            title="<?= __('manage_students') ?? 'Gérer les étudiants' ?>">
                                            <i class="fas fa-users"></i>
                                            <?= __('students') ?> (<?= $course['enrollment_count'] ?>)
                                        </a>

                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="instructor-empty">
                    <div class="instructor-empty-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="instructor-empty-title">
                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                            <?= __('no_courses_found') ?>
                        <?php else: ?>
                            <?= __('no_courses_created') ?>
                        <?php endif; ?>
                    </div>
                    <div class="instructor-empty-description">
                        <?php if (!empty($search) || $status_filter !== 'all'): ?>
                            <?= __('try_modifying_search_criteria') ?>
                        <?php else: ?>
                            <?= __('start_by_creating_first_course') ?>
                        <?php endif; ?>
                    </div>
                    <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                        <i class="fas fa-plus"></i>
                        <?= __('create_course') ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Global variables for dynamic features
        let courseStats = {
            totalCourses: <?= $total_courses ?>,
            publishedCourses: <?= count($published_courses) ?>,
            draftCourses: <?= count($draft_courses) ?>,
            totalEnrollments: <?= $total_enrollments ?>
        };

        document.addEventListener('DOMContentLoaded', function() {
            initializeDynamicFeatures();
            setupSearchEnhancements();
            animateStatisticsCards();
            addCourseCardInteractions();
            trackButtonClicks();
            initializeHamburgerMenu();
        });

        // Initialize dynamic features
        function initializeDynamicFeatures() {
            // Add real-time statistics updates
            updateStatisticsDisplay();

            // Add filter persistence
            setupFilterPersistence();
        }

        // Update statistics display with animations
        function updateStatisticsDisplay() {
            const statCards = document.querySelectorAll('.instructor-card-value');

            statCards.forEach((card, index) => {
                const finalValue = card.textContent;
                const numericValue = parseInt(finalValue.replace(/[^\d]/g, ''));

                if (!isNaN(numericValue) && numericValue > 0) {
                    animateCounter(card, 0, numericValue, 1000);
                }
            });
        }

        // Animate counter from start to end
        function animateCounter(element, start, end, duration) {
            const startTime = performance.now();

            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Use easing function for smooth animation
                const easeOutCubic = 1 - Math.pow(1 - progress, 3);
                const current = Math.round(start + (end - start) * easeOutCubic);

                element.textContent = current.toLocaleString();

                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                }
            }

            requestAnimationFrame(updateCounter);
        }

        // Setup enhanced search functionality
        function setupSearchEnhancements() {
            const searchInput = document.querySelector('.instructor-search-input');
            const searchForm = document.querySelector('.instructor-search');

            if (searchInput) {
                // Add search on type with debouncing
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        // Auto-submit search after 500ms of no typing
                        if (this.value.length >= 3 || this.value.length === 0) {
                            searchForm.submit();
                        }
                    }, 500);
                });

                // Add search suggestions dropdown
                searchInput.addEventListener('focus', showSearchSuggestions);
                searchInput.addEventListener('blur', hideSearchSuggestions);
            }
        }

        // Show search suggestions
        function showSearchSuggestions() {
            // This would typically fetch suggestions from an API
            // For now, we'll add a placeholder for future enhancement
            console.log('Search suggestions would be shown here');
        }

        // Hide search suggestions
        function hideSearchSuggestions() {
            // Hide suggestions dropdown
            console.log('Search suggestions hidden');
        }

        // Setup filter persistence in localStorage
        function setupFilterPersistence() {
            const filterSelects = document.querySelectorAll('.instructor-filter-select');
            const searchInput = document.querySelector('.instructor-search-input');

            // Load saved filters
            const savedFilters = JSON.parse(localStorage.getItem('my_courses_filters') || '{}');

            if (savedFilters.search && searchInput) {
                searchInput.value = savedFilters.search;
            }

            filterSelects.forEach(select => {
                if (savedFilters[select.name]) {
                    select.value = savedFilters[select.name];
                }

                // Save filter changes
                select.addEventListener('change', function() {
                    const filters = JSON.parse(localStorage.getItem('my_courses_filters') || '{}');
                    filters[this.name] = this.value;
                    localStorage.setItem('my_courses_filters', JSON.stringify(filters));
                });
            });

            // Save search input
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const filters = JSON.parse(localStorage.getItem('my_courses_filters') || '{}');
                    filters.search = this.value;
                    localStorage.setItem('my_courses_filters', JSON.stringify(filters));
                });
            }
        }

        // Animate statistics cards on load
        function animateStatisticsCards() {
            const cards = document.querySelectorAll('.instructor-card');

            cards.forEach((card, index) => {
                // Add staggered animation
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease-out';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }

        // Add enhanced course card interactions
        function addCourseCardInteractions() {
            const courseCards = document.querySelectorAll('.instructor-course-card');

            courseCards.forEach(card => {
                // Enhanced hover effects
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                    this.style.boxShadow = '0 20px 40px rgba(0,0,0,0.15)';
                    this.style.transition = 'all 0.3s ease';
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                    this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.1)';
                });

                // Add click tracking for analytics (only for non-link areas)
                card.addEventListener('click', function(e) {
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        // Track course view for analytics
                        const courseTitle = this.querySelector('.instructor-course-title').textContent;
                        console.log(`Course viewed: ${courseTitle}`);
                    }
                });

                // Add dynamic button interactions
                const lessonButtons = card.querySelectorAll('a[href*="course_lessons"]');
                const studentButtons = card.querySelectorAll('a[href*="course_students"]');

                lessonButtons.forEach(btn => {
                    btn.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.05)';
                        this.style.transition = 'all 0.2s ease';
                    });
                    btn.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });

                studentButtons.forEach(btn => {
                    btn.addEventListener('mouseenter', function() {
                        this.style.transform = 'scale(1.05)';
                        this.style.transition = 'all 0.2s ease';
                    });
                    btn.addEventListener('mouseleave', function() {
                        this.style.transform = 'scale(1)';
                    });
                });
            });
        }
        // Add real-time course statistics update (simulated)
        function updateCourseStatistics() {
            // This would typically fetch real-time data from an API
            // For demonstration, we'll simulate some updates

            setTimeout(() => {
                const enrollmentElements = document.querySelectorAll('.instructor-course-stats span:first-child');
                enrollmentElements.forEach(element => {
                    const currentText = element.textContent;
                    const match = currentText.match(/(\d+)/);
                    if (match) {
                        const currentCount = parseInt(match[1]);
                        // Simulate a small increase (for demo purposes)
                        const newCount = currentCount + Math.floor(Math.random() * 3);
                        element.innerHTML = element.innerHTML.replace(currentCount, newCount);

                        // Update the corresponding button count
                        const courseCard = element.closest('.instructor-course-card');
                        const studentButton = courseCard.querySelector('a[href*="course_students"]');
                        if (studentButton) {
                            const buttonText = studentButton.textContent;
                            const buttonMatch = buttonText.match(/\((\d+)\)/);
                            if (buttonMatch) {
                                studentButton.innerHTML = studentButton.innerHTML.replace(/\(\d+\)/, `(${newCount})`);
                            }
                        }
                    }
                });
            }, 5000); // Update after 5 seconds
        }

        // Add dynamic button click tracking
        function trackButtonClicks() {
            document.querySelectorAll('.instructor-course-footer .instructor-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    // Don't prevent default - let the link work normally
                    const courseTitle = this.closest('.instructor-course-card').querySelector('.instructor-course-title').textContent;
                    const action = this.textContent.trim();

                    console.log(`Clicked ${action} for course: ${courseTitle}`);
                });
            });
        }

        // Initialize real-time updates
        updateCourseStatistics();

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K to focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('.instructor-search-input');
                if (searchInput) {
                    searchInput.focus();
                }
            }

            // Escape to clear search
            if (e.key === 'Escape') {
                const searchInput = document.querySelector('.instructor-search-input');
                if (searchInput && searchInput.value) {
                    searchInput.value = '';
                    searchInput.form.submit();
                }
            }
        });

        // Add loading states for better UX
        function showLoadingState() {
            const courseGrid = document.querySelector('.instructor-course-grid');
            if (courseGrid) {
                courseGrid.style.opacity = '0.5';
                courseGrid.style.pointerEvents = 'none';
            }
        }

        function hideLoadingState() {
            const courseGrid = document.querySelector('.instructor-course-grid');
            if (courseGrid) {
                courseGrid.style.opacity = '1';
                courseGrid.style.pointerEvents = 'auto';
            }
        }

        // Override form submission to show loading state
        const searchForm = document.querySelector('.instructor-search');
        if (searchForm) {
            searchForm.addEventListener('submit', function() {
                showLoadingState();
            });
        }

        // Hamburger Menu Functionality
        function initializeHamburgerMenu() {
            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
            const sidebar = document.getElementById('sidebar');
            const mobileOverlay = document.getElementById('mobileOverlay');
            let isMenuOpen = false;

            // Toggle menu function
            function toggleMenu() {
                isMenuOpen = !isMenuOpen;

                if (isMenuOpen) {
                    // Open menu
                    hamburgerBtn.classList.add('active');
                    sidebar.classList.remove('mobile-hidden');
                    sidebar.classList.add('mobile-visible');
                    mobileOverlay.classList.add('active');
                    document.body.style.overflow = 'hidden'; // Prevent background scrolling
                } else {
                    // Close menu
                    hamburgerBtn.classList.remove('active');
                    sidebar.classList.remove('mobile-visible');
                    sidebar.classList.add('mobile-hidden');
                    mobileOverlay.classList.remove('active');
                    document.body.style.overflow = ''; // Restore scrolling
                }
            }

            // Close menu function
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

            // Event listeners
            if (hamburgerBtn) {
                hamburgerBtn.addEventListener('click', toggleMenu);
            }

            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', closeMenu);
            }

            // Close menu when clicking on sidebar links
            const sidebarLinks = document.querySelectorAll('.instructor-nav-item');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', closeMenu);
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    // Desktop view - ensure menu is closed and reset styles
                    closeMenu();
                    document.body.style.overflow = '';
                }
            });

            // Handle escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && isMenuOpen) {
                    closeMenu();
                }
            });

            // Initialize menu state based on screen size
            if (window.innerWidth <= 768) {
                sidebar.classList.add('mobile-hidden');
            }
        }

        // Enhanced mobile interactions
        function setupMobileInteractions() {
            // Add touch gestures for mobile
            let startX = 0;
            let startY = 0;
            const sidebar = document.getElementById('sidebar');

            if (sidebar) {
                sidebar.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                    startY = e.touches[0].clientY;
                });

                sidebar.addEventListener('touchmove', function(e) {
                    if (!startX || !startY) return;

                    const diffX = startX - e.touches[0].clientX;
                    const diffY = startY - e.touches[0].clientY;

                    // If horizontal swipe is greater than vertical
                    if (Math.abs(diffX) > Math.abs(diffY)) {
                        // Swipe left to close menu
                        if (diffX > 50) {
                            const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
                            if (hamburgerBtn && hamburgerBtn.classList.contains('active')) {
                                hamburgerBtn.click();
                            }
                        }
                    }

                    startX = 0;
                    startY = 0;
                });
            }
        }

        // Initialize mobile interactions
        setupMobileInteractions();

        // Add smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading state for navigation links
        document.querySelectorAll('.instructor-nav-item').forEach(link => {
            link.addEventListener('click', function() {
                // Add loading indicator
                const icon = this.querySelector('i');
                if (icon) {
                    const originalClass = icon.className;
                    icon.className = 'fas fa-spinner fa-spin';

                    // Reset icon after a delay (in case page doesn't reload immediately)
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 2000);
                }
            });
        });
    </script>
</body>

</html>