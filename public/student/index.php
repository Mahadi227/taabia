<?php
// Start output buffering to prevent any accidental output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle language switching first
if (isset($_GET['lang']) && in_array($_GET['lang'], ['fr', 'en'])) {
    $_SESSION['user_language'] = $_GET['lang'];
    // Redirect to remove lang parameter from URL
    $current_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $current_url");
    exit;
}

// Handle dark mode toggle
if (isset($_GET['theme'])) {
    $_SESSION['dark_mode'] = ($_GET['theme'] === 'dark');
    $current_url = strtok($_SERVER["REQUEST_URI"], '?');
    header("Location: $current_url");
    exit;
}

// Now load the other includes
require_once '../../includes/db.php';
require_once '../../includes/function.php';
require_once '../../includes/community_functions.php';
require_once '../../includes/i18n.php';

// Check if user is logged in and is a student
if (!is_logged_in() || !has_role('student')) {
    redirect('../../auth/login.php');
}

$current_user_id = current_user_id();
$is_dark_mode = $_SESSION['dark_mode'] ?? false;

// Initialize variables with default values
$total_enrolled_courses = 0;
$completed_courses = 0;
$total_communities = 0;
$total_posts = 0;
$total_comments = 0;
$upcoming_events = 0;
$recent_activity = [];

try {
    // Get student's enrolled courses
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total, 
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
        FROM student_courses 
        WHERE student_id = ?
    ");
    $stmt->execute([$current_user_id]);
    $course_stats = $stmt->fetch();
    $total_enrolled_courses = $course_stats['total'];
    $completed_courses = $course_stats['completed'];

    // Get community statistics
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT c.id) as communities,
               COUNT(DISTINCT cp.id) as posts,
               COUNT(DISTINCT pc.id) as comments
        FROM community_members cm
        LEFT JOIN communities c ON cm.community_id = c.id AND c.status = 'active'
        LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.author_id = ? AND cp.status = 'published'
        LEFT JOIN post_comments pc ON cp.id = pc.post_id AND pc.author_id = ? AND pc.status = 'published'
        WHERE cm.user_id = ? AND cm.status = 'active'
    ");
    $stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
    $community_stats = $stmt->fetch();
    $total_communities = $community_stats['communities'];
    $total_posts = $community_stats['posts'];
    $total_comments = $community_stats['comments'];

    // Get upcoming events
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM event_registrations er
        INNER JOIN events e ON er.event_id = e.id
        WHERE er.participant_id = ? AND e.event_date > NOW()
    ");
    $stmt->execute([$current_user_id]);
    $upcoming_events = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Database error in student/index.php: " . $e->getMessage());
}

// Get recent activities
try {
    // Get recent course enrollments
    $stmt = $pdo->prepare("
        SELECT 'course_enrollment' as type, 
               CONCAT('Enrolled in: ', c.title) as title, 
               sc.enrolled_at as time,
               c.id as related_id
        FROM student_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        WHERE sc.student_id = ?
        ORDER BY sc.enrolled_at DESC
        LIMIT 3
    ");
    $stmt->execute([$current_user_id]);
    $recent_enrollments = $stmt->fetchAll();

    // Get recent community activities
    $stmt = $pdo->prepare("
        SELECT 'community_post' as type,
               CONCAT('Posted in: ', c.name) as title,
               cp.created_at as time,
               cp.id as related_id
        FROM community_posts cp
        INNER JOIN communities c ON cp.community_id = c.id
        WHERE cp.author_id = ? AND cp.status = 'published'
        ORDER BY cp.created_at DESC
        LIMIT 3
    ");
    $stmt->execute([$current_user_id]);
    $recent_posts = $stmt->fetchAll();

    // Get recent community joins
    $stmt = $pdo->prepare("
        SELECT 'community_join' as type,
               CONCAT('Joined: ', c.name) as title,
               cm.joined_at as time,
               c.id as related_id
        FROM community_members cm
        INNER JOIN communities c ON cm.community_id = c.id
        WHERE cm.user_id = ? AND cm.status = 'active'
        ORDER BY cm.joined_at DESC
        LIMIT 3
    ");
    $stmt->execute([$current_user_id]);
    $recent_joins = $stmt->fetchAll();

    // Combine all activities and sort by time
    $all_activities = array_merge($recent_enrollments, $recent_posts, $recent_joins);
    usort($all_activities, function ($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    $recent_activity = array_slice($all_activities, 0, 8);
} catch (PDOException $e) {
    error_log("Database error in student/index.php activities: " . $e->getMessage());
}

// Get user's communities
$user_communities = get_user_communities($current_user_id, 5);

// Get trending communities
$trending_communities = get_trending_communities(5);

// Get recommended courses
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.fullname as instructor_name
        FROM courses c
        LEFT JOIN users u ON c.instructor_id = u.id
        WHERE c.status = 'published' AND c.id NOT IN (
            SELECT course_id FROM student_courses WHERE student_id = ?
        )
        ORDER BY c.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$current_user_id]);
    $recommended_courses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in student/index.php courses: " . $e->getMessage());
    $recommended_courses = [];
}

// Get upcoming events
try {
    $stmt = $pdo->prepare("
        SELECT e.*, 
               CASE WHEN er.id IS NOT NULL THEN 1 ELSE 0 END as is_registered
        FROM events e
        LEFT JOIN event_registrations er ON e.id = er.event_id AND er.participant_id = ?
        WHERE e.event_date > NOW() AND e.status = 'upcoming'
        ORDER BY e.event_date ASC
        LIMIT 4
    ");
    $stmt->execute([$current_user_id]);
    $upcoming_events_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in student/index.php events: " . $e->getMessage());
    $upcoming_events_list = [];
}

// Get user's recent posts
try {
    $stmt = $pdo->prepare("
        SELECT cp.*, c.name as community_name, c.id as community_id
        FROM community_posts cp
        INNER JOIN communities c ON cp.community_id = c.id
        WHERE cp.author_id = ? AND cp.status = 'published'
        ORDER BY cp.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$current_user_id]);
    $recent_posts_list = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in student/index.php posts: " . $e->getMessage());
    $recent_posts_list = [];
}

// Get user's learning progress
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT sc.course_id) as total_courses,
            COUNT(DISTINCT CASE WHEN sc.status = 'completed' THEN sc.course_id END) as completed_courses,
            COUNT(DISTINCT CASE WHEN sc.status = 'in_progress' THEN sc.course_id END) as in_progress_courses,
            COUNT(DISTINCT l.id) as total_lessons,
            COUNT(DISTINCT CASE WHEN sl.status = 'completed' THEN sl.lesson_id END) as completed_lessons
        FROM student_courses sc
        LEFT JOIN lessons l ON l.course_id = sc.course_id
        LEFT JOIN student_lessons sl ON sl.lesson_id = l.id AND sl.student_id = ?
        WHERE sc.student_id = ?
    ");
    $stmt->execute([$current_user_id, $current_user_id]);
    $progress_stats = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Database error in student/index.php progress: " . $e->getMessage());
    $progress_stats = [
        'total_courses' => 0,
        'completed_courses' => 0,
        'in_progress_courses' => 0,
        'total_lessons' => 0,
        'completed_lessons' => 0
    ];
}

// Get user's notifications
try {
    $stmt = $pdo->prepare("
        SELECT cn.*, c.name as community_name
        FROM community_notifications cn
        LEFT JOIN communities c ON cn.community_id = c.id
        WHERE cn.user_id = ? AND cn.read_at IS NULL
        ORDER BY cn.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$current_user_id]);
    $notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in student/index.php notifications: " . $e->getMessage());
    $notifications = [];
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>" data-theme="<?= $is_dark_mode ? 'dark' : 'light' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('student_dashboard') ?> | TaaBia</title>
    <meta name="description" content="<?= t('student_dashboard_description') ?>">
    <meta name="theme-color" content="#00796b">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #00796b;
            --primary-light: #4db6ac;
            --primary-dark: #004d40;
            --secondary-color: #00bcd4;
            --accent-color: #ff5722;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;

            --text-primary: #212121;
            --text-secondary: #757575;
            --text-light: #bdbdbd;

            --bg-primary: #fafafa;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f5f5f5;

            --border-color: #e0e0e0;
            --border-light: #f0f0f0;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-hover: 0 4px 8px rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        /* Dark Mode Variables */
        [data-theme="dark"] {
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-light: #757575;

            --bg-primary: #121212;
            --bg-secondary: #1e1e1e;
            --bg-tertiary: #2d2d2d;

            --border-color: #333333;
            --border-light: #404040;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            --shadow-hover: 0 4px 8px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Smooth transitions for theme switching */
        * {
            transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            box-shadow: var(--shadow);
            padding: 1rem 0;
            backdrop-filter: blur(10px);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.5rem;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
        }

        .main-container {
            margin-top: 2rem;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stats-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .stats-card:hover::before {
            opacity: 1;
        }

        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stats-icon.primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
        }

        .stats-icon.success {
            background: linear-gradient(135deg, var(--success-color), #66bb6a);
        }

        .stats-icon.info {
            background: linear-gradient(135deg, var(--info-color), #64b5f6);
        }

        .stats-icon.warning {
            background: linear-gradient(135deg, var(--warning-color), #ffb74d);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .stats-label {
            color: var(--text-secondary);
            font-weight: 500;
        }

        .section-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            box-shadow: var(--shadow);
            border: none;
            margin-bottom: 2rem;
        }

        .section-header {
            padding: 1.5rem 1.5rem 0 1.5rem;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .section-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        .community-card {
            border: 1px solid var(--border-light);
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .community-card:hover {
            border-color: var(--primary-color);
            box-shadow: var(--shadow);
        }

        .course-card {
            border: 1px solid var(--border-light);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .course-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .course-image {
            height: 120px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
        }

        .activity-item {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            transition: background-color 0.3s ease;
        }

        .activity-item:hover {
            background-color: var(--bg-tertiary);
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            margin-right: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        .badge {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-weight: 500;
        }

        .badge-primary {
            background-color: var(--primary-color);
        }

        .badge-success {
            background-color: var(--success-color);
        }

        .badge-info {
            background-color: var(--info-color);
        }

        .badge-warning {
            background-color: var(--warning-color);
        }

        /* Progress Bar Styles */
        .progress {
            height: 8px;
            border-radius: 10px;
            background-color: var(--bg-tertiary);
        }

        .progress-bar {
            border-radius: 10px;
            transition: width 1s ease-in-out;
        }

        /* Notification Bell */
        .notification-bell {
            position: relative;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Search Box */
        .search-box {
            position: relative;
        }

        .search-box input {
            padding-left: 40px;
            border-radius: 25px;
            border: 2px solid var(--border-light);
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .search-box input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(0, 121, 107, 0.25);
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .fab:hover {
            transform: scale(1.1);
            box-shadow: 0 15px 35px rgba(0, 121, 107, 0.4);
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--border-light);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }

        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 2rem;
            }

            .stats-number {
                font-size: 1.5rem;
            }

            .main-container {
                margin-top: 1rem;
            }

            .fab {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php" data-aos="fade-right">
                <i class="fas fa-graduation-cap me-2"></i>TaaBia
            </a>

            <!-- Search Box -->
            <div class="search-box d-none d-md-block me-3" data-aos="fade-down">
                <input type="text" class="form-control" placeholder="<?= t('search_communities_courses') ?>" id="searchInput">
                <i class="fas fa-search"></i>
            </div>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">
                            <i class="fas fa-home me-1"></i><?= t('dashboard') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../communities.php">
                            <i class="fas fa-users me-1"></i><?= t('communities') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">
                            <i class="fas fa-book me-1"></i><?= t('my_courses') ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-1"></i><?= t('profile') ?>
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <!-- Notifications -->
                    <li class="nav-item dropdown">
                        <a class="nav-link notification-bell" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell"></i>
                            <?php if (count($notifications) > 0): ?>
                                <span class="notification-badge"><?= count($notifications) ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="min-width: 300px;">
                            <li>
                                <h6 class="dropdown-header"><?= t('notifications') ?></h6>
                            </li>
                            <?php if (empty($notifications)): ?>
                                <li><span class="dropdown-item-text text-muted"><?= t('no_new_notifications') ?></span></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item" href="../community.php?id=<?= $notification['community_id'] ?>">
                                            <div class="d-flex align-items-start">
                                                <i class="fas fa-bell text-primary me-2 mt-1"></i>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($notification['title']) ?></div>
                                                    <small class="text-muted"><?= timeAgo($notification['created_at']) ?></small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <!-- Theme Toggle -->
                    <li class="nav-item">
                        <a class="nav-link" href="?theme=<?= $is_dark_mode ? 'light' : 'dark' ?>">
                            <i class="fas fa-<?= $is_dark_mode ? 'sun' : 'moon' ?>"></i>
                        </a>
                    </li>

                    <!-- Language Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-globe me-1"></i><?= $_SESSION['user_language'] ?? 'fr' ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?lang=fr">Français</a></li>
                            <li><a class="dropdown-item" href="?lang=en">English</a></li>
                        </ul>
                    </li>

                    <!-- User Menu -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i><?= t('profile') ?></a></li>
                            <li><a class="dropdown-item" href="courses.php"><i class="fas fa-book me-2"></i><?= t('my_courses') ?></a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i><?= t('logout') ?></a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><?= t('welcome_back') ?>, <?= htmlspecialchars($_SESSION['fullname'] ?? 'Student') ?>!</h1>
                    <p><?= t('student_dashboard_description') ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="../community_create.php" class="btn btn-light btn-lg">
                        <i class="fas fa-plus me-2"></i><?= t('create_community') ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="stats-card">
                    <div class="stats-icon primary">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stats-number"><?= $total_enrolled_courses ?></div>
                    <div class="stats-label"><?= t('enrolled_courses') ?></div>
                    <?php if ($progress_stats['total_courses'] > 0): ?>
                        <div class="mt-2">
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: <?= ($progress_stats['completed_courses'] / $progress_stats['total_courses']) * 100 ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $progress_stats['completed_courses'] ?>/<?= $progress_stats['total_courses'] ?> <?= t('completed') ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="stats-card">
                    <div class="stats-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?= $completed_courses ?></div>
                    <div class="stats-label"><?= t('completed_courses') ?></div>
                    <?php if ($progress_stats['completed_lessons'] > 0): ?>
                        <div class="mt-2">
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= ($progress_stats['completed_lessons'] / $progress_stats['total_lessons']) * 100 ?>%"></div>
                            </div>
                            <small class="text-muted"><?= $progress_stats['completed_lessons'] ?>/<?= $progress_stats['total_lessons'] ?> <?= t('lessons_completed') ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="stats-card">
                    <div class="stats-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?= $total_communities ?></div>
                    <div class="stats-label"><?= t('my_communities') ?></div>
                    <div class="mt-2">
                        <small class="text-muted"><?= $total_posts ?> <?= t('posts') ?> • <?= $total_comments ?> <?= t('comments') ?></small>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                <div class="stats-card">
                    <div class="stats-icon warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stats-number"><?= $upcoming_events ?></div>
                    <div class="stats-label"><?= t('upcoming_events') ?></div>
                    <div class="mt-2">
                        <small class="text-muted"><?= t('events_registered') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Learning Progress Chart -->
        <div class="row mb-4" data-aos="fade-up" data-aos-delay="500">
            <div class="col-12">
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-chart-line text-primary me-2"></i><?= t('learning_progress') ?>
                        </h3>
                        <p class="section-subtitle"><?= t('your_learning_journey') ?></p>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="progressChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- My Communities -->
                <div class="section-card" data-aos="fade-right">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-users text-primary me-2"></i><?= t('my_communities') ?>
                        </h3>
                        <p class="section-subtitle"><?= t('communities_you_joined') ?></p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($user_communities)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5><?= t('no_communities_yet') ?></h5>
                                <p class="text-muted"><?= t('join_your_first_community') ?></p>
                                <a href="../communities.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i><?= t('browse_communities') ?>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($user_communities as $community): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="community-card">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-1"><?= htmlspecialchars($community['name']) ?></h6>
                                                <span class="badge badge-<?= $community['role'] === 'admin' ? 'primary' : ($community['role'] === 'moderator' ? 'warning' : 'success') ?>">
                                                    <?= t($community['role']) ?>
                                                </span>
                                            </div>
                                            <p class="text-muted small mb-2"><?= htmlspecialchars(substr($community['description'], 0, 80)) ?>...</p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i><?= $community['member_count'] ?>
                                                    <i class="fas fa-comments ms-2 me-1"></i><?= $community['post_count'] ?>
                                                </small>
                                                <a href="../community.php?id=<?= $community['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    <?= t('view') ?>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="../communities.php" class="btn btn-outline-primary">
                                    <i class="fas fa-eye me-2"></i><?= t('view_all_communities') ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-history text-primary me-2"></i><?= t('recent_activity') ?>
                        </h3>
                        <p class="section-subtitle"><?= t('your_latest_actions') ?></p>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_activity)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                                <h5><?= t('no_recent_activity') ?></h5>
                                <p class="text-muted"><?= t('start_learning_and_connecting') ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex align-items-center">
                                        <div class="activity-icon bg-<?= $activity['type'] === 'course_enrollment' ? 'success' : ($activity['type'] === 'community_post' ? 'info' : 'warning') ?>">
                                            <i class="fas fa-<?= $activity['type'] === 'course_enrollment' ? 'book' : ($activity['type'] === 'community_post' ? 'comment' : 'users') ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($activity['title']) ?></h6>
                                            <small class="text-muted"><?= timeAgo($activity['time']) ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Trending Communities -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-fire text-warning me-2"></i><?= t('trending_communities') ?>
                        </h3>
                        <p class="section-subtitle"><?= t('popular_communities') ?></p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($trending_communities)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                <p class="text-muted small"><?= t('no_trending_communities') ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($trending_communities as $community): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-users text-white"></i>
                                        </div>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($community['name']) ?></h6>
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i><?= $community['member_count'] ?>
                                            <i class="fas fa-comments ms-2 me-1"></i><?= $community['post_count'] ?>
                                        </small>
                                    </div>
                                    <a href="../community.php?id=<?= $community['id'] ?>" class="btn btn-outline-primary btn-sm">
                                        <?= t('view') ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recommended Courses -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-star text-warning me-2"></i><?= t('recommended_courses') ?>
                        </h3>
                        <p class="section-subtitle"><?= t('courses_for_you') ?></p>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recommended_courses)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-book fa-2x text-muted mb-2"></i>
                                <p class="text-muted small"><?= t('no_recommended_courses') ?></p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recommended_courses as $course): ?>
                                <div class="course-card mb-3">
                                    <div class="course-image">
                                        <i class="fas fa-book"></i>
                                    </div>
                                    <div class="p-3">
                                        <h6 class="mb-1"><?= htmlspecialchars($course['title']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($course['instructor_name']) ?></small>
                                        <div class="mt-2">
                                            <span class="badge badge-primary"><?= htmlspecialchars($course['category']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="section-card">
                    <div class="section-header">
                        <h3 class="section-title">
                            <i class="fas fa-bolt text-warning me-2"></i><?= t('quick_actions') ?>
                        </h3>
                        <p class="section-subtitle"><?= t('common_tasks') ?></p>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../communities.php" class="btn btn-outline-primary">
                                <i class="fas fa-search me-2"></i><?= t('browse_communities') ?>
                            </a>
                            <a href="courses.php" class="btn btn-outline-success">
                                <i class="fas fa-book me-2"></i><?= t('my_courses') ?>
                            </a>
                            <a href="profile.php" class="btn btn-outline-info">
                                <i class="fas fa-user me-2"></i><?= t('edit_profile') ?>
                            </a>
                            <a href="../community_create.php" class="btn btn-outline-warning">
                                <i class="fas fa-plus me-2"></i><?= t('create_community') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Action Button -->
    <button class="fab" onclick="scrollToTop()" title="<?= t('back_to_top') ?>">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Progress Chart
        const ctx = document.getElementById('progressChart').getContext('2d');
        const progressChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['<?= t('completed_courses') ?>', '<?= t('in_progress_courses') ?>', '<?= t('not_started') ?>'],
                datasets: [{
                    data: [
                        <?= $progress_stats['completed_courses'] ?>,
                        <?= $progress_stats['in_progress_courses'] ?>,
                        <?= max(0, $progress_stats['total_courses'] - $progress_stats['completed_courses'] - $progress_stats['in_progress_courses']) ?>
                    ],
                    backgroundColor: [
                        '#4caf50',
                        '#ff9800',
                        '#e0e0e0'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            if (searchTerm.length > 2) {
                // Implement search logic here
                console.log('Searching for:', searchTerm);
            }
        });

        // Scroll to top function
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }

        // Show/hide FAB based on scroll position
        window.addEventListener('scroll', function() {
            const fab = document.querySelector('.fab');
            if (window.pageYOffset > 300) {
                fab.style.opacity = '1';
                fab.style.visibility = 'visible';
            } else {
                fab.style.opacity = '0';
                fab.style.visibility = 'hidden';
            }
        });

        // Auto-refresh notifications every 30 seconds
        setInterval(function() {
            // Check for new notifications
            fetch('../../api/check_notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > 0) {
                        updateNotificationBadge(data.count);
                    }
                })
                .catch(error => console.log('Notification check failed:', error));
        }, 30000);

        function updateNotificationBadge(count) {
            const badge = document.querySelector('.notification-badge');
            if (count > 0) {
                if (badge) {
                    badge.textContent = count;
                } else {
                    const bell = document.querySelector('.notification-bell');
                    bell.innerHTML = '<i class="fas fa-bell"></i><span class="notification-badge">' + count + '</span>';
                }
            }
        }

        // Theme persistence
        document.addEventListener('DOMContentLoaded', function() {
            const theme = '<?= $is_dark_mode ? "dark" : "light" ?>';
            document.documentElement.setAttribute('data-theme', theme);
        });

        // Add loading states to buttons
        document.querySelectorAll('a[href*="community"]').forEach(link => {
            link.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = 'loading';
                }
            });
        });

        // Smooth scroll for anchor links
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
    </script>
</body>

</html>

<?php
// End output buffering and flush
ob_end_flush();
?>