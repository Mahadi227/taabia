<?php

/**
 * Course Lessons Management Page - Optimized Version
 * 
 * This page displays course lessons with dynamic analytics and management features.
 * Optimized for performance, maintainability, and user experience.
 */

// ============================================================================
// INITIALIZATION & SECURITY
// ============================================================================

// Start session and handle authentication
require_once '../includes/session.php';
require_once '../includes/language_handler.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Security check
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// ============================================================================
// DATA VALIDATION & RETRIEVAL
// ============================================================================

/**
 * Validate and sanitize course ID
 */
function validateCourseId($course_id)
{
    if (!isset($course_id) || !is_numeric($course_id)) {
        flash_message(__('invalid_course'), 'error');
        redirect('my_courses.php');
    }
    return (int)$course_id;
}

/**
 * Fetch course data with enrollment count
 */
function fetchCourseData($pdo, $course_id, $instructor_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.*, 
                COUNT(sc.student_id) as enrolled_students,
                0 as avg_rating
            FROM courses c 
            LEFT JOIN student_courses sc ON c.id = sc.course_id 
            WHERE c.id = ? AND c.instructor_id = ? 
            GROUP BY c.id
        ");
        $stmt->execute([$course_id, $instructor_id]);
        $course = $stmt->fetch();

        if (!$course) {
            flash_message(__('course_not_found_or_no_permission'), 'error');
            redirect('my_courses.php');
        }

        return $course;
    } catch (PDOException $e) {
        error_log("Course fetch error: " . $e->getMessage());
        flash_message(__('database_error'), 'error');
        redirect('my_courses.php');
    }
}

/**
 * Fetch lessons with optimized query
 */
function fetchLessons($pdo, $course_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                l.*,
                0 as view_count,
                0 as completion_count,
                0 as avg_rating
            FROM lessons l
            WHERE l.course_id = ? 
            ORDER BY l.order_index ASC, l.id ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Lessons fetch error: " . $e->getMessage());
        return [];
    }
}

// Execute data retrieval
$course_id = validateCourseId($_GET['course_id'] ?? null);
$course = fetchCourseData($pdo, $course_id, $instructor_id);
$lessons = fetchLessons($pdo, $course_id);

// ============================================================================
// HTML OUTPUT
// ============================================================================
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('course_lessons') ?> - <?= htmlspecialchars($course['title']) ?></title>

    <!-- External Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Styles -->
    <link rel="stylesheet" href="../assets/css/instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">
    <link rel="stylesheet" href="assets/css/course-lessons.css">

    <!-- Preload Critical Resources -->
    <link rel="preload" href="api/course_analytics.php" as="fetch" crossorigin>
</head>

<body>
    <!-- ======================================================================== -->
    <!-- SIDEBAR NAVIGATION -->
    <!-- ======================================================================== -->
    <?php include '../includes/instructor_sidebar.php'; ?>

    <!-- ======================================================================== -->
    <!-- MAIN CONTENT -->
    <!-- ======================================================================== -->
    <div class="main-content">
        <!-- Header Section -->
        <header class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">
                        <i class="fas fa-book-open"></i>
                        <?= __('course_lessons') ?>
                    </h1>
                    <p class="page-description"><?= __('manage_lessons_for_course') ?></p>
                </div>

                <div class="header-actions">
                    <?php include '../includes/instructor_language_switcher.php'; ?>
                    <a href="add_lesson.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?= __('add_lesson') ?>
                    </a>
                    <a href="my_courses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('back_to_courses') ?>
                    </a>
                </div>
            </div>
        </header>

        <!-- ======================================================================== -->
        <!-- COURSE INFORMATION CARD -->
        <!-- ======================================================================== -->
        <section class="course-info-section">
            <div class="course-info-card">
                <div class="course-header">
                    <h2 class="course-title"><?= htmlspecialchars($course['title']) ?></h2>
                    <p class="course-description"><?= htmlspecialchars($course['description']) ?></p>
                </div>

                <div class="course-stats">
                    <div class="stat-item">
                        <i class="fas fa-users"></i>
                        <span class="stat-value"><?= $course['enrolled_students'] ?></span>
                        <span class="stat-label"><?= __('students') ?></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-book"></i>
                        <span class="stat-value"><?= count($lessons) ?></span>
                        <span class="stat-label"><?= __('lessons') ?></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-star"></i>
                        <span class="stat-value"><?= number_format($course['avg_rating'] ?? 0, 1) ?></span>
                        <span class="stat-label"><?= __('stars') ?></span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================================== -->
        <!-- LESSON ANALYTICS SECTION -->
        <!-- ======================================================================== -->
        <section class="analytics-section" id="analyticsSection">
            <div class="analytics-container">
                <div class="analytics-header">
                    <h2 class="analytics-title">
                        <i class="fas fa-chart-line"></i> <?= __('lesson_analytics') ?>
                    </h2>
                    <p class="analytics-description"><?= __('track_lesson_performance_and_engagement') ?></p>
                </div>

                <!-- Analytics Overview Cards -->
                <div class="analytics-overview" id="analyticsOverview">
                    <div class="analytics-card">
                        <div class="analytics-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <div class="analytics-content">
                            <div class="analytics-value" id="totalViews">-</div>
                            <div class="analytics-label"><?= __('total_views') ?></div>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="analytics-content">
                            <div class="analytics-value" id="totalCompletions">-</div>
                            <div class="analytics-label"><?= __('completions') ?></div>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="analytics-content">
                            <div class="analytics-value" id="averageRating">-</div>
                            <div class="analytics-label"><?= __('average_rating') ?></div>
                        </div>
                    </div>

                    <div class="analytics-card">
                        <div class="analytics-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="analytics-content">
                            <div class="analytics-value" id="averageTime">-</div>
                            <div class="analytics-label"><?= __('average_time') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Analytics Tabs -->
                <div class="analytics-tabs-container">
                    <div class="analytics-tabs">
                        <button class="analytics-tab active" data-tab="overview">
                            <i class="fas fa-chart-bar"></i> <?= __('overview') ?>
                        </button>
                        <button class="analytics-tab" data-tab="lessons">
                            <i class="fas fa-list"></i> <?= __('by_lesson') ?>
                        </button>
                        <button class="analytics-tab" data-tab="trends">
                            <i class="fas fa-trending-up"></i> <?= __('trends') ?>
                        </button>
                    </div>

                    <!-- Overview Tab -->
                    <div class="analytics-tab-content active" id="overview-tab">
                        <div class="analytics-grid">
                            <div class="analytics-chart-container">
                                <h3><?= __('engagement_overview') ?></h3>
                                <canvas id="engagementChart" width="400" height="200"></canvas>
                            </div>
                            <div class="analytics-stats-container">
                                <h3><?= __('key_metrics') ?></h3>
                                <div class="metrics-list">
                                    <div class="metric-item">
                                        <span class="metric-label"><?= __('completion_rate') ?>:</span>
                                        <span class="metric-value" id="completionRate">-</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label"><?= __('engagement_rate') ?>:</span>
                                        <span class="metric-value" id="engagementRate">-</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label"><?= __('success_rate') ?>:</span>
                                        <span class="metric-value" id="successRate">-</span>
                                    </div>
                                    <div class="metric-item">
                                        <span class="metric-label"><?= __('total_students') ?>:</span>
                                        <span class="metric-value" id="totalStudents">-</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- By Lesson Tab -->
                    <div class="analytics-tab-content" id="lessons-tab">
                        <div class="lessons-analytics-table">
                            <table class="analytics-table">
                                <thead>
                                    <tr>
                                        <th><?= __('lesson') ?></th>
                                        <th><?= __('views') ?></th>
                                        <th><?= __('completions') ?></th>
                                        <th><?= __('completion_rate') ?></th>
                                        <th><?= __('avg_rating') ?></th>
                                        <th><?= __('avg_time') ?></th>
                                        <th><?= __('actions') ?></th>
                                    </tr>
                                </thead>
                                <tbody id="lessonsAnalyticsTableBody">
                                    <!-- Dynamic content loaded here -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Trends Tab -->
                    <div class="analytics-tab-content" id="trends-tab">
                        <div class="trends-container">
                            <div class="trends-chart">
                                <h3><?= __('views_trend') ?></h3>
                                <canvas id="viewsTrendChart" width="400" height="200"></canvas>
                            </div>
                            <div class="trends-chart">
                                <h3><?= __('completions_trend') ?></h3>
                                <canvas id="completionsTrendChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ======================================================================== -->
        <!-- LESSONS MANAGEMENT SECTION -->
        <!-- ======================================================================== -->
        <section class="lessons-section">
            <div class="lessons-container">
                <div class="lessons-header">
                    <h2 class="lessons-title">
                        <i class="fas fa-list"></i> <?= __('lessons') ?>
                        <span class="lessons-count">(<?= count($lessons) ?>)</span>
                    </h2>
                </div>

                <?php if (empty($lessons)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-book-open"></i>
                        </div>
                        <h3><?= __('no_lessons_found') ?></h3>
                        <p><?= __('no_lessons_added_yet') ?></p>
                        <a href="add_lesson.php?course_id=<?= $course_id ?>" class="btn btn-primary">
                            <i class="fas fa-plus"></i> <?= __('add_first_lesson') ?>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="lessons-grid">
                        <?php foreach ($lessons as $index => $lesson): ?>
                            <div class="lesson-card" data-lesson-id="<?= $lesson['id'] ?>">
                                <div class="lesson-header">
                                    <h3 class="lesson-title"><?= htmlspecialchars($lesson['title'] ?? 'Sans titre') ?></h3>
                                    <span class="lesson-type-badge <?= strtolower($lesson['content_type'] ?? 'text') ?>">
                                        <i class="fas fa-<?= getContentTypeIcon($lesson['content_type'] ?? 'text') ?>"></i>
                                        <?= ucfirst($lesson['content_type'] ?? 'Texte') ?>
                                    </span>
                                </div>

                                <div class="lesson-body">
                                    <p class="lesson-description">
                                        <?= truncateText($lesson['content'] ?? '', 150) ?>
                                    </p>

                                    <div class="lesson-meta">
                                        <div class="lesson-meta-item">
                                            <i class="fas fa-hashtag"></i>
                                            <span><?= __('order') ?>: <?= $lesson['order_index'] ?? $lesson['id'] ?></span>
                                        </div>
                                        <div class="lesson-meta-item">
                                            <i class="fas fa-eye"></i>
                                            <span><?= $lesson['view_count'] ?? 0 ?> <?= __('views') ?></span>
                                        </div>
                                        <div class="lesson-meta-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?= $lesson['completion_count'] ?? 0 ?> <?= __('completions') ?></span>
                                        </div>
                                        <?php if ($lesson['avg_rating']): ?>
                                            <div class="lesson-meta-item">
                                                <i class="fas fa-star"></i>
                                                <span><?= number_format($lesson['avg_rating'], 1) ?> <?= __('rating') ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="lesson-actions">
                                        <a href="view_lesson.php?id=<?= $lesson['id'] ?>"
                                            class="lesson-action-btn view-btn"
                                            title="<?= __('view_lesson') ?>">
                                            <i class="fas fa-eye"></i> <?= __('view') ?>
                                        </a>
                                        <a href="lesson_edit.php?id=<?= $lesson['id'] ?>"
                                            class="lesson-action-btn edit-btn"
                                            title="<?= __('edit_lesson') ?>">
                                            <i class="fas fa-edit"></i> <?= __('edit') ?>
                                        </a>
                                        <a href="lesson_delete.php?id=<?= $lesson['id'] ?>"
                                            class="lesson-action-btn delete-btn"
                                            title="<?= __('delete_lesson') ?>"
                                            onclick="return confirm('<?= __('confirm_delete_lesson') ?>')">
                                            <i class="fas fa-trash"></i> <?= __('delete') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- ======================================================================== -->
    <!-- JAVASCRIPT -->
    <!-- ======================================================================== -->
    <script>
        // Set configuration from PHP
        window.courseId = <?= $course_id ?>;
        window.language = '<?= $_SESSION['user_language'] ?? 'fr' ?>';
    </script>
    <script src="assets/js/course-lessons.js"></script>
    <script>
        // Simple initialization for compatibility
        document.addEventListener('DOMContentLoaded', () => {
            console.log('Course lessons page loaded');
        });
    </script>
</body>

</html>
<?php
// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get content type icon for PHP
 */
function getContentTypeIcon($type)
{
    $icons = [
        'text' => 'file-alt',
        'video' => 'video',
        'pdf' => 'file-pdf',
        'quiz' => 'question-circle'
    ];
    return $icons[$type] ?? 'file';
}

/**
 * Truncate text to specified length
 */
function truncateText($text, $length)
{
    if (empty($text)) return '';
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}
?>