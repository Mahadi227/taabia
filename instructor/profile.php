<?php

/**
 * Instructor Profile Page - Professional Modern Design
 * Complete data integration with database
 */

// ============================================================================
// INITIALIZATION & SECURITY
// ============================================================================

ob_start();
require_once '../includes/language_handler.php';
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

require_role('instructor');
$instructor_id = $_SESSION['user_id'];

// ============================================================================
// HANDLE PROFILE UPDATE
// ============================================================================

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Validate required fields
        if (empty($full_name) || empty($email)) {
            $error_message = __('name_and_email_required');
        } else {
            // Update user profile
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = ?, email = ?, phone = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$full_name, $email, $phone, $instructor_id]);

            $success_message = __('profile_updated_successfully');

            // Refresh page to show new data
            header("Location: profile.php?updated=1");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Profile update error: " . $e->getMessage());
        $error_message = __('error_updating_profile');
    }
}

// Check for update success parameter
if (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $success_message = __('profile_updated_successfully');
}

// ============================================================================
// HANDLE PROFILE IMAGE UPLOAD
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    try {
        $file = $_FILES['profile_image'];

        // Validate file
        if ($file['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            if (!in_array($file['type'], $allowed_types)) {
                $error_message = __('invalid_image_format');
            } elseif ($file['size'] > $max_size) {
                $error_message = __('image_too_large');
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'instructor_' . $instructor_id . '_' . time() . '.' . $extension;
                $upload_path = $upload_dir . $filename;

                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    // Update database
                    $stmt = $pdo->prepare("
                        UPDATE users 
                        SET profile_image = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$filename, $instructor_id]);

                    header("Location: profile.php?updated=1");
                    exit;
                } else {
                    $error_message = __('error_uploading_image');
                }
            }
        }
    } catch (Exception $e) {
        error_log("Image upload error: " . $e->getMessage());
        $error_message = __('error_uploading_image');
    }
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

try {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$instructor_id]);
    $user = $stmt->fetch();

    if (!$user) {
        header('Location: ../auth/logout.php');
        exit;
    }

    // Get instructor statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_courses = $stmt->fetchColumn();

    // Try multiple tables for students count with fallback
    $student_queries = [
        "SELECT COUNT(DISTINCT student_id) FROM student_courses sc INNER JOIN courses c ON sc.course_id = c.id WHERE c.instructor_id = ?",
        "SELECT COUNT(DISTINCT student_id) FROM course_enrollments ce INNER JOIN courses c ON ce.course_id = c.id WHERE c.instructor_id = ?",
        "SELECT COUNT(DISTINCT user_id) FROM user_courses uc INNER JOIN courses c ON uc.course_id = c.id WHERE c.instructor_id = ?"
    ];

    $total_students = 0;
    foreach ($student_queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$instructor_id]);
            $count = $stmt->fetchColumn();
            if ($count > 0) {
                $total_students = $count;
                break;
            }
        } catch (PDOException $e) {
            continue;
        }
    }

    // Get lessons count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM lessons
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmt->execute([$instructor_id]);
    $total_lessons = $stmt->fetchColumn();

    // Get earnings data with fallback
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count, SUM(oi.price * oi.quantity) as total 
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            LEFT JOIN courses c ON oi.course_id = c.id
            WHERE c.instructor_id = ? AND o.status = 'completed'
        ");
        $stmt->execute([$instructor_id]);
        $earnings = $stmt->fetch();
        $total_sales = $earnings['count'] ?? 0;
        $total_earnings = $earnings['total'] ?? 0;
    } catch (PDOException $e) {
        $total_sales = 0;
        $total_earnings = 0;
    }

    // Get top performing courses
    $top_courses = [];
    $course_queries = [
        "SELECT c.title, c.id, c.thumbnail, c.price,
               COUNT(DISTINCT sc.student_id) as enrollment_count,
               AVG(COALESCE(sc.progress, 0)) as avg_progress
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id, c.title, c.thumbnail, c.price
        ORDER BY enrollment_count DESC
        LIMIT 5",

        "SELECT c.title, c.id, c.thumbnail, c.price,
               COUNT(DISTINCT ce.student_id) as enrollment_count,
               AVG(COALESCE(ce.progress, 0)) as avg_progress
        FROM courses c
        LEFT JOIN course_enrollments ce ON c.id = ce.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id, c.title, c.thumbnail, c.price
        ORDER BY enrollment_count DESC
        LIMIT 5"
    ];

    foreach ($course_queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$instructor_id]);
            $result = $stmt->fetchAll();
            if (!empty($result)) {
                $top_courses = $result;
                break;
            }
        } catch (PDOException $e) {
            continue;
        }
    }

    // Get recent activity
    $recent_activity = [];
    try {
        $stmt = $pdo->prepare("
            (SELECT 'course_created' as type, c.title as name, c.created_at as date
             FROM courses c WHERE c.instructor_id = ?)
            UNION ALL
            (SELECT 'lesson_created' as type, l.title as name, l.created_at as date
             FROM lessons l 
             INNER JOIN courses c ON l.course_id = c.id 
             WHERE c.instructor_id = ?)
            ORDER BY date DESC
            LIMIT 8
        ");
        $stmt->execute([$instructor_id, $instructor_id]);
        $recent_activity = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Recent activity error: " . $e->getMessage());
    }
} catch (PDOException $e) {
    error_log("Database error in instructor profile: " . $e->getMessage());
    $user = [
        'full_name' => '',
        'fullname' => '',
        'email' => '',
        'phone' => '',
        'profile_image' => '',
        'profile_img' => '',
        'role' => 'instructor',
        'created_at' => date('Y-m-d H:i:s'),
        'is_active' => 1
    ];
    $total_courses = 0;
    $total_students = 0;
    $total_lessons = 0;
    $total_sales = 0;
    $total_earnings = 0;
    $top_courses = [];
    $recent_activity = [];
}

// Get profile image path
$profile_image = $user['profile_image'] ?? $user['profile_img'] ?? '';
$profile_image_path = $profile_image ? '../uploads/profiles/' . $profile_image : '';
$display_name = $user['full_name'] ?? $user['fullname'] ?? __('instructor');
$avatar_letter = strtoupper(substr($display_name, 0, 1));

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_profile') ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <style>
        /* Professional Modern Profile Design */
        .instructor-main {
            margin-left: 280px;
            padding: var(--spacing-8);
            background-color: var(--gray-50);
            min-height: 100vh;
        }

        @media (max-width: 1024px) {
            .instructor-main {
                margin-left: 0;
                padding: var(--spacing-4);
            }
        }

        /* Profile Hero Section */
        .profile-hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 3rem 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .profile-hero::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .profile-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            gap: 2rem;
        }

        .profile-image-container {
            position: relative;
        }

        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--primary-color);
            font-weight: 800;
            border: 5px solid white;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            object-fit: cover;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .upload-overlay {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 40px;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            border: 3px solid white;
            transition: all 0.3s ease;
        }

        .upload-overlay:hover {
            background: var(--success-dark);
            transform: scale(1.1);
        }

        .profile-info {
            flex: 1;
            color: white;
        }

        .profile-name {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .profile-role {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0 0 1rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-meta {
            display: flex;
            gap: 2rem;
            margin-top: 1.5rem;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            opacity: 0.95;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: white;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
            box-shadow: var(--shadow);
        }

        .alert-error {
            background: white;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
            box-shadow: var(--shadow);
        }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            border-top: 3px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.green {
            border-top-color: var(--success-color);
        }

        .stat-card.orange {
            border-top-color: var(--warning-color);
        }

        .stat-card.blue {
            border-top-color: var(--info-color);
        }

        .stat-card.purple {
            border-top-color: #8b5cf6;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.green {
            background: linear-gradient(135deg, var(--success-color), var(--success-dark));
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, var(--warning-color), var(--warning-dark));
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, var(--info-color), #0284c7);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-value {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }

        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        /* Form Section */
        .form-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0 0 2rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-100);
        }

        .section-title i {
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-input:disabled {
            background: var(--gray-100);
            cursor: not-allowed;
            color: var(--gray-500);
        }

        /* Buttons */
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            padding: 0.875rem 1.75rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        /* Activity & Courses List */
        .list-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .list-item {
            padding: 1rem;
            border-bottom: 1px solid var(--gray-100);
            transition: background 0.2s ease;
        }

        .list-item:hover {
            background: var(--gray-50);
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .course-rank {
            width: 32px;
            height: 32px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .course-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.5rem 0;
        }

        .course-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .activity-icon.course {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary-color);
        }

        .activity-icon.lesson {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        /* Professional Footer Styles */
        .instructor-footer {
            background: linear-gradient(135deg, var(--gray-900) 0%, var(--gray-800) 100%);
            color: white;
            margin-top: 3rem;
            border-top: 3px solid var(--primary-color);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            padding: 3rem 2rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-section h4 {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0 0 1.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-section h4::before {
            content: '';
            width: 3px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .footer-logo h3 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .footer-logo h3 i {
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .footer-logo p {
            color: var(--gray-300);
            font-size: 0.95rem;
            margin: 0 0 1.5rem 0;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            width: 40px;
            height: 40px;
            background: var(--gray-700);
            color: var(--gray-300);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
        }

        .social-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 0.75rem;
        }

        .footer-links a {
            color: var(--gray-300);
            text-decoration: none;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            transition: all 0.2s ease;
            border-radius: var(--radius-sm);
        }

        .footer-links a:hover {
            color: var(--primary-color);
            padding-left: 0.5rem;
            background: rgba(37, 99, 235, 0.1);
        }

        .footer-links a i {
            width: 16px;
            text-align: center;
            font-size: 0.9rem;
        }

        .footer-bottom {
            background: var(--gray-900);
            border-top: 1px solid var(--gray-700);
            padding: 1.5rem 2rem;
        }

        .footer-bottom-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-copyright p {
            color: var(--gray-400);
            font-size: 0.9rem;
            margin: 0;
        }

        .footer-links-bottom {
            display: flex;
            gap: 2rem;
        }

        .footer-links-bottom a {
            color: var(--gray-400);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }

        .footer-links-bottom a:hover {
            color: var(--primary-color);
        }

        .footer-language {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .footer-language span {
            color: var(--gray-400);
            font-size: 0.9rem;
        }

        .footer-language a {
            color: var(--gray-400);
            text-decoration: none;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .footer-language a:hover,
        .footer-language a.active {
            background: var(--primary-color);
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }

            .footer-content {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 1.5rem;
                padding: 2rem 1rem 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .profile-hero-content {
                flex-direction: column;
                text-align: center;
            }

            .profile-meta {
                flex-direction: column;
                gap: 0.75rem;
            }

            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }

            .profile-name {
                font-size: 2rem;
            }

            .footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 2rem 1rem;
            }

            .footer-bottom-content {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }

            .footer-links-bottom {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }

            .social-links {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .footer-links-bottom {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <?php include '../includes/instructor_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="instructor-main">
            <!-- Alert Messages -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <strong><?= $success_message ?></strong>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <strong><?= $error_message ?></strong>
                </div>
            <?php endif; ?>

            <!-- Profile Hero -->
            <div class="profile-hero">
                <div class="profile-hero-content">
                    <div class="profile-image-container">
                        <div class="profile-avatar">
                            <?php if ($profile_image_path && file_exists($profile_image_path)): ?>
                                <img src="<?= $profile_image_path ?>" alt="<?= htmlspecialchars($display_name) ?>">
                            <?php else: ?>
                                <?= $avatar_letter ?>
                            <?php endif; ?>
                        </div>
                        <label for="profile_image_upload" class="upload-overlay" title="<?= __('change_profile_picture') ?>">
                            <i class="fas fa-camera"></i>
                        </label>
                        <form method="POST" enctype="multipart/form-data" id="imageUploadForm" style="display: none;">
                            <input type="file" id="profile_image_upload" name="profile_image" accept="image/*" onchange="document.getElementById('imageUploadForm').submit()">
                        </form>
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?= htmlspecialchars($display_name) ?></h1>
                        <p class="profile-role">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <?= __('instructor') ?>
                        </p>
                        <div class="profile-meta">
                            <div class="profile-meta-item">
                                <i class="fas fa-envelope"></i>
                                <?= htmlspecialchars($user['email'] ?? '') ?>
                            </div>
                            <?php if ($user['phone']): ?>
                                <div class="profile-meta-item">
                                    <i class="fas fa-phone"></i>
                                    <?= htmlspecialchars($user['phone']) ?>
                                </div>
                            <?php endif; ?>
                            <div class="profile-meta-item">
                                <i class="fas fa-calendar"></i>
                                <?= __('joined') ?> <?= date('M Y', strtotime($user['created_at'] ?? 'now')) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Statistics -->
            <div class="dashboard-grid">
                <div class="stat-card green">
                    <div class="stat-icon green">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_courses) ?></div>
                    <div class="stat-label"><?= __('total_courses') ?></div>
                </div>

                <div class="stat-card blue">
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_students) ?></div>
                    <div class="stat-label"><?= __('total_students') ?></div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon purple">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-value"><?= number_format($total_lessons) ?></div>
                    <div class="stat-label"><?= __('total_lessons') ?></div>
                </div>

                <div class="stat-card orange">
                    <div class="stat-icon orange">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">$<?= number_format($total_earnings, 0) ?></div>
                    <div class="stat-label"><?= __('total_earnings') ?></div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Edit Profile Form -->
                <div class="form-card">
                    <h2 class="section-title">
                        <i class="fas fa-edit"></i>
                        <?= __('edit_profile') ?>
                    </h2>

                    <form method="POST" action="">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="form-group">
                            <label class="form-label"><?= __('full_name') ?> <span style="color: var(--danger-color);">*</span></label>
                            <input type="text" name="full_name" class="form-input"
                                value="<?= htmlspecialchars($user['full_name'] ?? $user['fullname'] ?? '') ?>"
                                required placeholder="<?= __('enter_your_full_name') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= __('email') ?> <span style="color: var(--danger-color);">*</span></label>
                            <input type="email" name="email" class="form-input"
                                value="<?= htmlspecialchars($user['email'] ?? '') ?>"
                                required placeholder="<?= __('enter_your_email') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= __('phone') ?></label>
                            <input type="text" name="phone" class="form-input"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                                placeholder="+1 (234) 567-8900">
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= __('user_role') ?></label>
                            <input type="text" class="form-input"
                                value="<?= htmlspecialchars(ucfirst($user['role'] ?? 'instructor')) ?>"
                                readonly disabled>
                        </div>

                        <div class="form-group">
                            <label class="form-label"><?= __('account_status') ?></label>
                            <input type="text" class="form-input"
                                value="<?= $user['is_active'] ? __('active') : __('inactive') ?>"
                                readonly disabled>
                        </div>

                        <div class="btn-group">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                                <i class="fas fa-times"></i> <?= __('cancel') ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?= __('save_changes') ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Sidebar Info -->
                <div>
                    <!-- Recent Activity -->
                    <?php if (!empty($recent_activity)): ?>
                        <div class="list-card" style="margin-bottom: 1.5rem;">
                            <h3 class="section-title">
                                <i class="fas fa-clock"></i>
                                <?= __('recent_activity') ?>
                            </h3>
                            <?php foreach (array_slice($recent_activity, 0, 6) as $activity): ?>
                                <div class="list-item">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div class="activity-icon <?= $activity['type'] == 'course_created' ? 'course' : 'lesson' ?>">
                                            <i class="fas fa-<?= $activity['type'] == 'course_created' ? 'book' : 'play-circle' ?>"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 500; color: var(--gray-900); font-size: 0.9rem;">
                                                <?= htmlspecialchars($activity['name']) ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--gray-500);">
                                                <?= timeAgo($activity['date']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Quick Stats -->
                    <div class="list-card">
                        <h3 class="section-title">
                            <i class="fas fa-chart-pie"></i>
                            <?= __('quick_stats') ?>
                        </h3>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-md);">
                                <span style="font-weight: 500; color: var(--gray-700);"><?= __('courses_published') ?></span>
                                <strong style="color: var(--primary-color); font-size: 1.25rem;"><?= number_format($total_courses) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-md);">
                                <span style="font-weight: 500; color: var(--gray-700);"><?= __('active_students') ?></span>
                                <strong style="color: var(--success-color); font-size: 1.25rem;"><?= number_format($total_students) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-md);">
                                <span style="font-weight: 500; color: var(--gray-700);"><?= __('lessons_created') ?></span>
                                <strong style="color: var(--info-color); font-size: 1.25rem;"><?= number_format($total_lessons) ?></strong>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: var(--gray-50); border-radius: var(--radius-md);">
                                <span style="font-weight: 500; color: var(--gray-700);"><?= __('total_sales') ?></span>
                                <strong style="color: var(--warning-color); font-size: 1.25rem;"><?= number_format($total_sales) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performing Courses -->
            <?php if (!empty($top_courses)): ?>
                <div class="list-card">
                    <h2 class="section-title">
                        <i class="fas fa-trophy"></i>
                        <?= __('top_performing_courses') ?>
                    </h2>
                    <?php foreach ($top_courses as $index => $course): ?>
                        <div class="list-item">
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div class="course-rank">
                                    <?= $index + 1 ?>
                                </div>
                                <div class="course-info" style="flex: 1;">
                                    <h4><?= htmlspecialchars($course['title']) ?></h4>
                                    <div class="course-meta">
                                        <span>
                                            <i class="fas fa-users"></i>
                                            <?= number_format($course['enrollment_count']) ?> <?= __('students') ?>
                                        </span>
                                        <span>
                                            <i class="fas fa-chart-line"></i>
                                            <?= number_format($course['avg_progress'] ?? 0, 1) ?>% <?= __('progress') ?>
                                        </span>
                                        <?php if ($course['price']): ?>
                                            <span>
                                                <i class="fas fa-tag"></i>
                                                $<?= number_format($course['price'], 2) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Professional Site Map Footer -->
            <footer class="instructor-footer">
                <div class="footer-content">
                    <div class="footer-section">
                        <div class="footer-logo">
                            <h3><i class="fas fa-graduation-cap"></i> TaaBia LMS</h3>
                            <p><?= __('professional_learning_platform') ?></p>
                        </div>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-link" title="Twitter"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="social-link" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>

                    <div class="footer-section">
                        <h4><?= __('instructor_dashboard') ?></h4>
                        <ul class="footer-links">
                            <li><a href="index.php"><i class="fas fa-tachometer-alt"></i> <?= __('dashboard') ?></a></li>
                            <li><a href="courses.php"><i class="fas fa-book"></i> <?= __('my_courses') ?></a></li>
                            <li><a href="students.php"><i class="fas fa-users"></i> <?= __('my_students') ?></a></li>
                            <li><a href="earnings.php"><i class="fas fa-dollar-sign"></i> <?= __('my_earnings') ?></a></li>
                            <li><a href="transactions.php"><i class="fas fa-receipt"></i> <?= __('transactions') ?></a></li>
                            <li><a href="payouts.php"><i class="fas fa-credit-card"></i> <?= __('payouts') ?></a></li>
                        </ul>
                    </div>

                    <div class="footer-section">
                        <h4><?= __('course_management') ?></h4>
                        <ul class="footer-links">
                            <li><a href="add_course.php"><i class="fas fa-plus-circle"></i> <?= __('add_course') ?></a></li>
                            <li><a href="course_lessons.php"><i class="fas fa-play-circle"></i> <?= __('manage_lessons') ?></a></li>
                            <li><a href="validate_submissions.php"><i class="fas fa-check-circle"></i> <?= __('validate_submissions') ?></a></li>
                            <li><a href="upload_content.php"><i class="fas fa-upload"></i> <?= __('upload_content') ?></a></li>
                        </ul>
                    </div>

                    <div class="footer-section">
                        <h4><?= __('account_management') ?></h4>
                        <ul class="footer-links">
                            <li><a href="profile.php"><i class="fas fa-user"></i> <?= __('my_profile') ?></a></li>
                            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <?= __('logout') ?></a></li>
                            <li><a href="../public/index.php"><i class="fas fa-home"></i> <?= __('visit_website') ?></a></li>
                            <li><a href="../public/main_site/contact.php"><i class="fas fa-envelope"></i> <?= __('contact_support') ?></a></li>
                        </ul>
                    </div>

                    <div class="footer-section">
                        <h4><?= __('analytics_reports') ?></h4>
                        <ul class="footer-links">
                            <li><a href="earnings.php#revenue-chart"><i class="fas fa-chart-line"></i> <?= __('revenue_analytics') ?></a></li>
                            <li><a href="students.php#progress-chart"><i class="fas fa-chart-bar"></i> <?= __('student_progress') ?></a></li>
                            <li><a href="transactions.php#performance-chart"><i class="fas fa-chart-pie"></i> <?= __('performance_metrics') ?></a></li>
                            <li><a href="payouts.php#monthly-chart"><i class="fas fa-chart-area"></i> <?= __('payout_history') ?></a></li>
                        </ul>
                    </div>
                </div>

                <div class="footer-bottom">
                    <div class="footer-bottom-content">
                        <div class="footer-copyright">
                            <p>&copy; <?= date('Y') ?> TaaBia LMS. <?= __('all_rights_reserved') ?>.</p>
                        </div>
                        <div class="footer-links-bottom">
                            <a href="../public/main_site/privacy.php"><?= __('privacy_policy') ?></a>
                            <a href="../public/main_site/terms.php"><?= __('terms_of_service') ?></a>
                            <a href="../public/main_site/about.php"><?= __('about_us') ?></a>
                        </div>
                        <div class="footer-language">
                            <span><?= __('language') ?>:</span>
                            <a href="?lang=en" class="<?= ($_SESSION['user_language'] ?? 'fr') == 'en' ? 'active' : '' ?>">EN</a>
                            <a href="?lang=fr" class="<?= ($_SESSION['user_language'] ?? 'fr') == 'fr' ? 'active' : '' ?>">FR</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script>
        // Auto-hide success message
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.transition = 'all 0.5s ease';
                    successAlert.style.opacity = '0';
                    successAlert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        successAlert.remove();
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>

</html>