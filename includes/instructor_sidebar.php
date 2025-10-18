<?php

/**
 * Instructor Sidebar Navigation
 * 
 * Reusable sidebar component for instructor dashboard pages
 */

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Sidebar Styles -->
<link rel="stylesheet" href="../includes/instructor_sidebar.css">

<!-- Sidebar -->
<div class="instructor-sidebar" id="sidebar">
    <div class="instructor-sidebar-header">
        <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia Skills&Market</h2>
        <p><?= __('instructor_space') ?></p>
    </div>

    <nav class="instructor-nav">
        <a href="index.php" class="instructor-nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i>
            <?= __('dashboard') ?>
        </a>
        <a href="my_courses.php" class="instructor-nav-item <?= $current_page == 'my_courses.php' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <?= __('my_courses') ?>
        </a>
        <a href="add_course.php" class="instructor-nav-item <?= $current_page == 'add_course.php' ? 'active' : '' ?>">
            <i class="fas fa-plus-circle"></i>
            <?= __('new_course') ?>
        </a>
        <a href="add_lesson.php" class="instructor-nav-item <?= $current_page == 'add_lesson.php' ? 'active' : '' ?>">
            <i class="fas fa-play-circle"></i>
            <?= __('add_lesson') ?>
        </a>
        <a href="students.php" class="instructor-nav-item <?= $current_page == 'students.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i>
            <?= __('my_students') ?>
        </a>
        <a href="student_progress.php" class="instructor-nav-item <?= $current_page == 'student_progress.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <?= __('student_progress') ?>
        </a>
        <a href="assignments.php" class="instructor-nav-item <?= $current_page == 'assignments.php' ? 'active' : '' ?>">
            <i class="fas fa-tasks"></i>
            <?= __('assignments') ?>
        </a>
        <a href="quizzes.php" class="instructor-nav-item <?= $current_page == 'quizzes.php' ? 'active' : '' ?>">
            <i class="fas fa-question-circle"></i>
            <?= __('quizzes') ?>
        </a>
        <a href="validate_submissions.php" class="instructor-nav-item <?= $current_page == 'validate_submissions.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-check"></i>
            <?= __('validate_submissions') ?>
        </a>
        <a href="materials.php" class="instructor-nav-item <?= $current_page == 'materials.php' ? 'active' : '' ?>">
            <i class="fas fa-folder-open"></i>
            <?= __('course_materials') ?>
        </a>
        <a href="take_attendance.php" class="instructor-nav-item <?= $current_page == 'take_attendance.php' ? 'active' : '' ?>">
            <i class="fas fa-calendar-check"></i>
            <?= __('take_attendance') ?>
        </a>
        <a href="attendance_reports.php" class="instructor-nav-item <?= $current_page == 'attendance_reports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i>
            <?= __('attendance_reports') ?>
        </a>
        <a href="course_analytics.php" class="instructor-nav-item <?= $current_page == 'course_analytics.php' ? 'active' : '' ?>">
            <i class="fas fa-analytics"></i>
            <?= __('course_analytics') ?>
        </a>
        <a href="reports.php" class="instructor-nav-item <?= $current_page == 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-file-alt"></i>
            <?= __('reports_analytics') ?>
        </a>
        <a href="earnings.php" class="instructor-nav-item <?= $current_page == 'earnings.php' ? 'active' : '' ?>">
            <i class="fas fa-dollar-sign"></i>
            <?= __('my_earnings') ?>
        </a>
        <a href="transactions.php" class="instructor-nav-item <?= $current_page == 'transactions.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-cart"></i>
            <?= __('transactions') ?>
        </a>
        <a href="payouts.php" class="instructor-nav-item <?= $current_page == 'payouts.php' ? 'active' : '' ?>">
            <i class="fas fa-money-bill-wave"></i>
            <?= __('payments') ?>
        </a>
        <a href="profile.php" class="instructor-nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i>
            <?= __('profile') ?>
        </a>
        <a href="../auth/logout.php" class="instructor-nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <?= __('logout') ?>
        </a>
    </nav>
</div>

<!-- Mobile Overlay -->
<div class="mobile-overlay" id="mobileOverlay"></div>

<!-- Hamburger Menu Button -->
<button class="hamburger-menu-btn" id="hamburgerMenuBtn">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<script>
    // Hamburger menu functionality
    document.addEventListener('DOMContentLoaded', function() {
        const hamburgerBtn = document.getElementById('hamburgerMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const mobileOverlay = document.getElementById('mobileOverlay');

        if (hamburgerBtn && sidebar && mobileOverlay) {
            hamburgerBtn.addEventListener('click', function() {
                sidebar.classList.toggle('mobile-visible');
                mobileOverlay.classList.toggle('active');
                document.body.classList.toggle('sidebar-open');
            });

            mobileOverlay.addEventListener('click', function() {
                sidebar.classList.remove('mobile-visible');
                mobileOverlay.classList.remove('active');
                document.body.classList.remove('sidebar-open');
            });

            // Close sidebar when clicking on nav items on mobile
            const navItems = sidebar.querySelectorAll('.instructor-nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        sidebar.classList.remove('mobile-visible');
                        mobileOverlay.classList.remove('active');
                        document.body.classList.remove('sidebar-open');
                    }
                });
            });
        }
    });
</script>