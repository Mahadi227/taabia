<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

// Get date range from URL parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Today

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date_from) || !DateTime::createFromFormat('Y-m-d', $date_to)) {
    $date_from = date('Y-m-01');
    $date_to = date('Y-m-d');
}

// Get overall statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_certificates,
        COUNT(DISTINCT student_id) as unique_students,
        COUNT(DISTINCT course_id) as courses_with_certificates,
        COUNT(DISTINCT instructor_name) as unique_instructors,
        AVG(final_grade) as average_grade,
        COUNT(CASE WHEN DATE(issue_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as last_30_days,
        COUNT(CASE WHEN DATE(issue_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as last_7_days,
        COUNT(CASE WHEN DATE(issue_date) = CURDATE() THEN 1 END) as today
    FROM course_certificates
    WHERE DATE(issue_date) BETWEEN ? AND ?
";

$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$date_from, $date_to]);
$stats = $stats_stmt->fetch();

// Get monthly certificate trends (last 12 months)
$monthly_query = "
    SELECT 
        DATE_FORMAT(issue_date, '%Y-%m') as month,
        COUNT(*) as certificate_count
    FROM course_certificates
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";
$monthly_stmt = $pdo->query($monthly_query);
$monthly_data = $monthly_stmt->fetchAll();

// Get daily certificate trends (last 30 days)
$daily_query = "
    SELECT 
        DATE(issue_date) as date,
        COUNT(*) as certificate_count
    FROM course_certificates
    WHERE issue_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(issue_date)
    ORDER BY date DESC
    LIMIT 30
";
$daily_stmt = $pdo->query($daily_query);
$daily_data = $daily_stmt->fetchAll();

// Get top courses by certificates issued
$top_courses_query = "
    SELECT 
        c.title as course_title,
        c.id as course_id,
        COUNT(cc.id) as certificate_count,
        AVG(cc.final_grade) as average_grade,
        COUNT(DISTINCT cc.student_id) as unique_students
    FROM course_certificates cc
    INNER JOIN courses c ON cc.course_id = c.id
    WHERE DATE(cc.issue_date) BETWEEN ? AND ?
    GROUP BY c.id, c.title
    ORDER BY certificate_count DESC
    LIMIT 10
";
$top_courses_stmt = $pdo->prepare($top_courses_query);
$top_courses_stmt->execute([$date_from, $date_to]);
$top_courses = $top_courses_stmt->fetchAll();

// Get top instructors by certificates issued
$top_instructors_query = "
    SELECT 
        cc.instructor_name,
        COUNT(cc.id) as certificate_count,
        AVG(cc.final_grade) as average_grade,
        COUNT(DISTINCT cc.student_id) as unique_students,
        COUNT(DISTINCT cc.course_id) as courses_count
    FROM course_certificates cc
    WHERE DATE(cc.issue_date) BETWEEN ? AND ?
    GROUP BY cc.instructor_name
    ORDER BY certificate_count DESC
    LIMIT 10
";
$top_instructors_stmt = $pdo->prepare($top_instructors_query);
$top_instructors_stmt->execute([$date_from, $date_to]);
$top_instructors = $top_instructors_stmt->fetchAll();

// Get grade distribution
$grade_distribution_query = "
    SELECT 
        CASE 
            WHEN final_grade >= 90 THEN 'Excellent (90-100%)'
            WHEN final_grade >= 80 THEN 'Good (80-89%)'
            WHEN final_grade >= 70 THEN 'Satisfactory (70-79%)'
            WHEN final_grade >= 60 THEN 'Pass (60-69%)'
            ELSE 'Below 60%'
        END as grade_range,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM course_certificates WHERE DATE(issue_date) BETWEEN ? AND ?), 2) as percentage
    FROM course_certificates
    WHERE DATE(issue_date) BETWEEN ? AND ?
    GROUP BY grade_range
    ORDER BY 
        CASE 
            WHEN final_grade >= 90 THEN 1
            WHEN final_grade >= 80 THEN 2
            WHEN final_grade >= 70 THEN 3
            WHEN final_grade >= 60 THEN 4
            ELSE 5
        END
";
$grade_dist_stmt = $pdo->prepare($grade_distribution_query);
$grade_dist_stmt->execute([$date_from, $date_to, $date_from, $date_to]);
$grade_distribution = $grade_dist_stmt->fetchAll();

// Get verification statistics
$verification_stats_query = "
    SELECT 
        COUNT(DISTINCT cc.id) as total_certificates,
        COUNT(DISTINCT cv.certificate_id) as verified_certificates,
        COUNT(cv.id) as total_verifications,
        COUNT(DISTINCT cv.ip_address) as unique_ips
    FROM course_certificates cc
    LEFT JOIN certificate_verifications cv ON cc.id = cv.certificate_id
    WHERE DATE(cc.issue_date) BETWEEN ? AND ?
";
$verification_stats_stmt = $pdo->prepare($verification_stats_query);
$verification_stats_stmt->execute([$date_from, $date_to]);
$verification_stats = $verification_stats_stmt->fetch();

// Get sharing statistics
$sharing_stats_query = "
    SELECT 
        platform,
        COUNT(*) as share_count
    FROM certificate_shares cs
    INNER JOIN course_certificates cc ON cs.certificate_id = cc.id
    WHERE DATE(cc.issue_date) BETWEEN ? AND ?
    GROUP BY platform
    ORDER BY share_count DESC
";
$sharing_stats_stmt = $pdo->prepare($sharing_stats_query);
$sharing_stats_stmt->execute([$date_from, $date_to]);
$sharing_stats = $sharing_stats_stmt->fetchAll();

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('certificate_analytics') ?> - <?= __('admin_panel') ?></title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .analytics-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .date-filter {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .date-filter h3 {
            margin: 0 0 1rem 0;
            color: #333;
        }

        .date-inputs {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .date-inputs input {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-number.primary {
            color: #667eea;
        }

        .stat-number.success {
            color: #38a169;
        }

        .stat-number.warning {
            color: #ed8936;
        }

        .stat-number.info {
            color: #3182ce;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .stat-subtitle {
            color: #999;
            font-size: 0.8rem;
        }

        .chart-container {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .chart-title {
            margin: 0 0 1.5rem 0;
            color: #333;
            font-size: 1.2rem;
        }

        .chart-wrapper {
            position: relative;
            height: 400px;
        }

        .tables-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .table-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .table-title {
            margin: 0;
            color: #333;
            font-size: 1.1rem;
        }

        .table-content {
            padding: 1rem;
        }

        .table-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .table-item:last-child {
            border-bottom: none;
        }

        .item-title {
            font-weight: 500;
            color: #333;
        }

        .item-meta {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .item-stats {
            text-align: right;
        }

        .stat-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .grade-bar {
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .grade-fill {
            height: 20px;
            background: linear-gradient(90deg, #e53e3e, #ed8936, #38a169);
            transition: width 0.3s ease;
        }

        .grade-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .grade-range {
            font-weight: 500;
            color: #333;
        }

        .grade-count {
            color: #666;
            font-size: 0.9rem;
        }

        .platform-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
            margin-right: 0.5rem;
        }

        .platform-linkedin {
            background: #0077b5;
            color: white;
        }

        .platform-facebook {
            background: #4267b2;
            color: white;
        }

        .platform-twitter {
            background: #1da1f2;
            color: white;
        }

        .platform-email {
            background: #6c757d;
            color: white;
        }

        .platform-download {
            background: #28a745;
            color: white;
        }

        .platform-other {
            background: #6c757d;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ddd;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <h1><?= __('certificate_analytics') ?></h1>
                <p><?= __('detailed_certificate_statistics_and_analytics') ?></p>
            </div>

            <div class="analytics-container">
                <!-- Date Filter -->
                <div class="date-filter">
                    <h3><?= __('filter_by_date_range') ?></h3>
                    <form method="GET" class="date-form">
                        <div class="date-inputs">
                            <label for="date_from"><?= __('from_date') ?>:</label>
                            <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($date_from) ?>">

                            <label for="date_to"><?= __('to_date') ?>:</label>
                            <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($date_to) ?>">

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> <?= __('apply_filter') ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Key Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number primary"><?= number_format($stats['total_certificates']) ?></div>
                        <div class="stat-label"><?= __('total_certificates') ?></div>
                        <div class="stat-subtitle"><?= __('period') ?>: <?= date('M j', strtotime($date_from)) ?> - <?= date('M j, Y', strtotime($date_to)) ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number success"><?= number_format($stats['unique_students']) ?></div>
                        <div class="stat-label"><?= __('unique_students') ?></div>
                        <div class="stat-subtitle"><?= __('students_receiving_certificates') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number info"><?= number_format($stats['courses_with_certificates']) ?></div>
                        <div class="stat-label"><?= __('courses_with_certificates') ?></div>
                        <div class="stat-subtitle"><?= __('courses_issuing_certificates') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number warning"><?= round($stats['average_grade'], 1) ?>%</div>
                        <div class="stat-label"><?= __('average_grade') ?></div>
                        <div class="stat-subtitle"><?= __('across_all_certificates') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number primary"><?= number_format($stats['last_30_days']) ?></div>
                        <div class="stat-label"><?= __('last_30_days') ?></div>
                        <div class="stat-subtitle"><?= __('certificates_issued') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number success"><?= number_format($stats['last_7_days']) ?></div>
                        <div class="stat-label"><?= __('last_7_days') ?></div>
                        <div class="stat-subtitle"><?= __('certificates_issued') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number info"><?= number_format($stats['today']) ?></div>
                        <div class="stat-label"><?= __('today') ?></div>
                        <div class="stat-subtitle"><?= __('certificates_issued') ?></div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-number warning"><?= number_format($verification_stats['total_verifications']) ?></div>
                        <div class="stat-label"><?= __('total_verifications') ?></div>
                        <div class="stat-subtitle"><?= __('verification_attempts') ?></div>
                    </div>
                </div>

                <!-- Monthly Trends Chart -->
                <div class="chart-container">
                    <h3 class="chart-title"><?= __('monthly_certificate_trends') ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                <!-- Grade Distribution -->
                <div class="chart-container">
                    <h3 class="chart-title"><?= __('grade_distribution') ?></h3>
                    <div class="chart-wrapper">
                        <canvas id="gradeChart"></canvas>
                    </div>
                </div>

                <!-- Tables Grid -->
                <div class="tables-grid">
                    <!-- Top Courses -->
                    <div class="table-container">
                        <div class="table-header">
                            <h3 class="table-title"><?= __('top_courses_by_certificates') ?></h3>
                        </div>
                        <div class="table-content">
                            <?php if (!empty($top_courses)): ?>
                                <?php foreach ($top_courses as $course): ?>
                                    <div class="table-item">
                                        <div>
                                            <div class="item-title"><?= htmlspecialchars($course['course_title']) ?></div>
                                            <div class="item-meta">
                                                <?= $course['unique_students'] ?> <?= __('students') ?> •
                                                <?= round($course['average_grade'], 1) ?>% <?= __('avg_grade') ?>
                                            </div>
                                        </div>
                                        <div class="item-stats">
                                            <span class="stat-badge"><?= $course['certificate_count'] ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <p><?= __('no_data_available') ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Top Instructors -->
                    <div class="table-container">
                        <div class="table-header">
                            <h3 class="table-title"><?= __('top_instructors_by_certificates') ?></h3>
                        </div>
                        <div class="table-content">
                            <?php if (!empty($top_instructors)): ?>
                                <?php foreach ($top_instructors as $instructor): ?>
                                    <div class="table-item">
                                        <div>
                                            <div class="item-title"><?= htmlspecialchars($instructor['instructor_name']) ?></div>
                                            <div class="item-meta">
                                                <?= $instructor['unique_students'] ?> <?= __('students') ?> •
                                                <?= $instructor['courses_count'] ?> <?= __('courses') ?> •
                                                <?= round($instructor['average_grade'], 1) ?>% <?= __('avg_grade') ?>
                                            </div>
                                        </div>
                                        <div class="item-stats">
                                            <span class="stat-badge"><?= $instructor['certificate_count'] ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <p><?= __('no_data_available') ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Grade Distribution Table -->
                <?php if (!empty($grade_distribution)): ?>
                    <div class="chart-container">
                        <h3 class="chart-title"><?= __('detailed_grade_distribution') ?></h3>
                        <?php foreach ($grade_distribution as $grade): ?>
                            <div class="grade-item">
                                <div class="grade-range"><?= htmlspecialchars($grade['grade_range']) ?></div>
                                <div class="grade-count"><?= $grade['count'] ?> (<?= $grade['percentage'] ?>%)</div>
                            </div>
                            <div class="grade-bar">
                                <div class="grade-fill" style="width: <?= $grade['percentage'] ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Sharing Statistics -->
                <?php if (!empty($sharing_stats)): ?>
                    <div class="chart-container">
                        <h3 class="chart-title"><?= __('certificate_sharing_by_platform') ?></h3>
                        <div class="chart-wrapper">
                            <canvas id="sharingChart"></canvas>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Monthly Trends Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_reverse(array_column($monthly_data, 'month'))) ?>,
                datasets: [{
                    label: '<?= __('certificates_issued') ?>',
                    data: <?= json_encode(array_reverse(array_column($monthly_data, 'certificate_count'))) ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($grade_distribution, 'grade_range')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($grade_distribution, 'count')) ?>,
                    backgroundColor: [
                        '#38a169', // Green for excellent
                        '#3182ce', // Blue for good
                        '#ed8936', // Orange for satisfactory
                        '#e53e3e', // Red for pass
                        '#6c757d' // Gray for below 60%
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });

        // Sharing Chart
        <?php if (!empty($sharing_stats)): ?>
            const sharingCtx = document.getElementById('sharingChart').getContext('2d');
            new Chart(sharingCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($sharing_stats, 'platform')) ?>,
                    datasets: [{
                        label: '<?= __('shares') ?>',
                        data: <?= json_encode(array_column($sharing_stats, 'share_count')) ?>,
                        backgroundColor: [
                            '#0077b5', // LinkedIn
                            '#4267b2', // Facebook
                            '#1da1f2', // Twitter
                            '#6c757d', // Email
                            '#28a745', // Download
                            '#6c757d' // Other
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>
