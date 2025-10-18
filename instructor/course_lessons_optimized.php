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
                AVG(cr.rating) as avg_rating
            FROM courses c 
            LEFT JOIN student_courses sc ON c.id = sc.course_id 
            LEFT JOIN course_ratings cr ON c.id = cr.course_id
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
                COUNT(lv.id) as view_count,
                COUNT(lc.id) as completion_count,
                AVG(lr.rating) as avg_rating
            FROM lessons l
            LEFT JOIN lesson_views lv ON l.id = lv.lesson_id
            LEFT JOIN lesson_completions lc ON l.id = lc.lesson_id
            LEFT JOIN lesson_ratings lr ON l.id = lr.lesson_id
            WHERE l.course_id = ? 
            GROUP BY l.id
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
        // ============================================================================
        // CONFIGURATION & CONSTANTS
        // ============================================================================
        const CONFIG = {
            courseId: <?= $course_id ?>,
            language: '<?= $_SESSION['user_language'] ?? 'fr' ?>',
            apiEndpoint: 'api/course_analytics.php',
            refreshInterval: 30000, // 30 seconds
            chartColors: {
                primary: '#2563eb',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#06b6d4'
            }
        };

        // ============================================================================
        // UTILITY FUNCTIONS
        // ============================================================================
        const Utils = {
            /**
             * Format number with locale-specific separators
             */
            formatNumber: (num) => {
                return new Intl.NumberFormat(CONFIG.language).format(num);
            },

            /**
             * Format date with locale-specific format
             */
            formatDate: (dateString) => {
                return new Date(dateString).toLocaleDateString(CONFIG.language);
            },

            /**
             * Show loading state
             */
            showLoading: (elementId) => {
                const element = document.getElementById(elementId);
                if (element) {
                    element.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                }
            },

            /**
             * Show error state
             */
            showError: (elementId, message = 'Error') => {
                const element = document.getElementById(elementId);
                if (element) {
                    element.innerHTML = `<span class="error-text">${message}</span>`;
                }
            },

            /**
             * Debounce function calls
             */
            debounce: (func, wait) => {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
        };

        // ============================================================================
        // ANALYTICS MANAGER
        // ============================================================================
        class AnalyticsManager {
            constructor() {
                this.data = null;
                this.charts = {};
                this.isLoading = false;
                this.init();
            }

            /**
             * Initialize analytics system
             */
            init() {
                this.setupEventListeners();
                this.loadAnalyticsData();
                this.setupAutoRefresh();
            }

            /**
             * Setup event listeners
             */
            setupEventListeners() {
                // Tab switching
                document.querySelectorAll('.analytics-tab').forEach(tab => {
                    tab.addEventListener('click', (e) => {
                        this.switchTab(e.target.dataset.tab);
                    });
                });

                // Window resize handler for charts
                window.addEventListener('resize', Utils.debounce(() => {
                    this.resizeCharts();
                }, 250));
            }

            /**
             * Load analytics data from API
             */
            async loadAnalyticsData() {
                if (this.isLoading) return;

                this.isLoading = true;
                this.showLoadingState();

                try {
                    const response = await fetch(`${CONFIG.apiEndpoint}?course_id=${CONFIG.courseId}`);
                    const result = await response.json();

                    if (result.status === 'success') {
                        this.data = result.data;
                        this.updateAnalyticsDisplay();
                        this.hideLoadingState();
                    } else {
                        throw new Error(result.message || 'Failed to load analytics');
                    }
                } catch (error) {
                    console.error('Analytics loading error:', error);
                    this.showErrorState(error.message);
                } finally {
                    this.isLoading = false;
                }
            }

            /**
             * Show loading state
             */
            showLoadingState() {
                const elements = ['totalViews', 'totalCompletions', 'averageRating', 'averageTime'];
                elements.forEach(id => Utils.showLoading(id));
            }

            /**
             * Hide loading state
             */
            hideLoadingState() {
                // Loading state will be replaced by real data
            }

            /**
             * Show error state
             */
            showErrorState(message) {
                const elements = ['totalViews', 'totalCompletions', 'averageRating', 'averageTime'];
                elements.forEach(id => Utils.showError(id, 'Error'));

                // Show error notification
                this.showNotification(message, 'error');
            }

            /**
             * Update analytics display with real data
             */
            updateAnalyticsDisplay() {
                if (!this.data) return;

                // Update overview cards
                this.updateOverviewCards();

                // Update charts
                this.updateCharts();

                // Update lessons table
                this.updateLessonsTable();
            }

            /**
             * Update overview cards
             */
            updateOverviewCards() {
                const overview = this.data.overview;

                document.getElementById('totalViews').textContent = Utils.formatNumber(overview.total_views);
                document.getElementById('totalCompletions').textContent = Utils.formatNumber(overview.total_completions);
                document.getElementById('averageRating').textContent = overview.average_rating.toFixed(1);
                document.getElementById('averageTime').textContent = overview.average_time + 'm';

                // Update key metrics
                document.getElementById('completionRate').textContent = overview.completion_rate + '%';
                document.getElementById('engagementRate').textContent = overview.engagement_rate + '%';
                document.getElementById('successRate').textContent = overview.success_rate + '%';
                document.getElementById('totalStudents').textContent = Utils.formatNumber(overview.total_students);
            }

            /**
             * Update charts with real data
             */
            updateCharts() {
                this.createEngagementChart();
                this.createTrendsCharts();
            }

            /**
             * Create engagement chart
             */
            createEngagementChart() {
                const ctx = document.getElementById('engagementChart');
                if (!ctx || !this.data.engagement) return;

                const engagement = this.data.engagement;

                this.charts.engagement = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: [
                            '<?= __('completed') ?>',
                            '<?= __('in_progress') ?>',
                            '<?= __('not_started') ?>'
                        ],
                        datasets: [{
                            data: [
                                engagement.completed,
                                engagement.in_progress,
                                engagement.not_started
                            ],
                            backgroundColor: [
                                CONFIG.chartColors.success,
                                CONFIG.chartColors.warning,
                                CONFIG.chartColors.danger
                            ],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
            }

            /**
             * Create trends charts
             */
            createTrendsCharts() {
                this.createViewsTrendChart();
                this.createCompletionsTrendChart();
            }

            /**
             * Create views trend chart
             */
            createViewsTrendChart() {
                const ctx = document.getElementById('viewsTrendChart');
                if (!ctx || !this.data.trends.views) return;

                const viewsData = this.data.trends.views;
                const labels = viewsData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString(CONFIG.language, {
                        month: 'short',
                        year: 'numeric'
                    });
                });
                const values = viewsData.map(item => parseInt(item.views) || 0);

                this.charts.viewsTrend = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '<?= __('views') ?>',
                            data: values,
                            borderColor: CONFIG.chartColors.primary,
                            backgroundColor: CONFIG.chartColors.primary + '20',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            /**
             * Create completions trend chart
             */
            createCompletionsTrendChart() {
                const ctx = document.getElementById('completionsTrendChart');
                if (!ctx || !this.data.trends.completions) return;

                const completionsData = this.data.trends.completions;
                const labels = completionsData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString(CONFIG.language, {
                        month: 'short',
                        year: 'numeric'
                    });
                });
                const values = completionsData.map(item => parseInt(item.completions) || 0);

                this.charts.completionsTrend = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: '<?= __('completions') ?>',
                            data: values,
                            backgroundColor: CONFIG.chartColors.success,
                            borderRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            /**
             * Update lessons table
             */
            updateLessonsTable() {
                const tableBody = document.getElementById('lessonsAnalyticsTableBody');
                if (!tableBody || !this.data.lessons) return;

                tableBody.innerHTML = '';

                if (this.data.lessons.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td colspan="7" class="text-center text-muted">
                            <i class="fas fa-chart-line"></i>
                            <?= __('no_analytics_data_available') ?>
                        </td>
                    `;
                    tableBody.appendChild(row);
                    return;
                }

                this.data.lessons.forEach(lesson => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <strong>${lesson.title}</strong>
                            <br><small class="text-muted">${lesson.content_type} • ${lesson.duration}m</small>
                        </td>
                        <td>${Utils.formatNumber(lesson.views)}</td>
                        <td>${Utils.formatNumber(lesson.completions)}</td>
                        <td><span class="metric-value">${lesson.completion_rate}%</span></td>
                        <td>${lesson.avg_rating} <i class="fas fa-star" style="color: #fbbf24;"></i></td>
                        <td>${lesson.avg_time}m</td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewLessonDetails(${lesson.id}, '${lesson.title}')">
                                <i class="fas fa-eye"></i> <?= __('view_details') ?>
                            </button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            }

            /**
             * Switch between analytics tabs
             */
            switchTab(tabName) {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.analytics-tab').forEach(tab => tab.classList.remove('active'));
                document.querySelectorAll('.analytics-tab-content').forEach(content => content.classList.remove('active'));

                // Add active class to selected tab and content
                document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
                document.getElementById(`${tabName}-tab`).classList.add('active');
            }

            /**
             * Resize charts on window resize
             */
            resizeCharts() {
                Object.values(this.charts).forEach(chart => {
                    if (chart && typeof chart.resize === 'function') {
                        chart.resize();
                    }
                });
            }

            /**
             * Setup auto-refresh
             */
            setupAutoRefresh() {
                setInterval(() => {
                    this.loadAnalyticsData();
                }, CONFIG.refreshInterval);
            }

            /**
             * Show notification
             */
            showNotification(message, type = 'info') {
                // Simple notification system
                const notification = document.createElement('div');
                notification.className = `notification notification-${type}`;
                notification.innerHTML = `
                    <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    ${message}
                `;

                document.body.appendChild(notification);

                setTimeout(() => {
                    notification.remove();
                }, 5000);
            }
        }

        // ============================================================================
        // HELPER FUNCTIONS
        // ============================================================================

        /**
         * Get content type icon
         */
        function getContentTypeIcon(type) {
            const icons = {
                'text': 'file-alt',
                'video': 'video',
                'pdf': 'file-pdf',
                'quiz': 'question-circle'
            };
            return icons[type] || 'file';
        }

        /**
         * Truncate text to specified length
         */
        function truncateText(text, length) {
            if (!text) return '';
            return text.length > length ? text.substring(0, length) + '...' : text;
        }

        /**
         * View lesson details
         */
        function viewLessonDetails(lessonId, lessonTitle) {
            window.location.href = `lesson_edit.php?id=${lessonId}`;
        }

        // ============================================================================
        // INITIALIZATION
        // ============================================================================

        // Initialize analytics when DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            // Initialize analytics manager
            window.analyticsManager = new AnalyticsManager();

            // Initialize other components if needed
            console.log('Course lessons page initialized');
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