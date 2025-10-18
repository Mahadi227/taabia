<?php

/**
 * Student Profile Page - Modern LMS
 * View and manage student profile with statistics
 */

// Enable error logging for debugging
error_log("=== PROFILE.PHP LOADING ===");

try {
    require_once '../includes/session.php';
    error_log("Session loaded - User ID: " . ($_SESSION['user_id'] ?? 'Not set'));

    require_once '../includes/db.php';
    error_log("Database loaded");

    require_once '../includes/function.php';
    error_log("Functions loaded");

    require_once '../includes/language_handler.php';
    error_log("Language handler loaded");

    require_role('student');
    error_log("Role check passed");

    $student_id = $_SESSION['user_id'];
    error_log("Student ID: $student_id");
} catch (Exception $e) {
    error_log("FATAL ERROR in profile.php includes: " . $e->getMessage());
    die("Erreur de chargement de la page. Consultez les logs PHP pour plus de détails. Error: " . htmlspecialchars($e->getMessage()));
}

// Initialize variables with defaults
$user = [];
$total_courses = 0;
$avg_progress = 0;
$certificates_count = 0;
$total_spent = 0;
$recent_courses = [];
$recent_certificates = [];

try {
    // Get user information
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$student_id]);
    $user = $stmt->fetch();

    if (!$user) {
        error_log("User not found for ID: $student_id");
        header('Location: ../auth/logout.php');
        exit;
    }

    error_log("Profile loaded for: " . ($user['full_name'] ?? $user['fullname'] ?? 'Unknown'));

    // Get enrolled courses count
    $stmt_courses = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ?");
    $stmt_courses->execute([$student_id]);
    $total_courses = $stmt_courses->fetchColumn();

    // Calculate progress
    try {
        $stmt_progress = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT l.id) as total_lessons,
                COUNT(DISTINCT lp.lesson_id) as viewed_lessons
            FROM student_courses sc
            JOIN lessons l ON l.course_id = sc.course_id
            LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.student_id = sc.student_id
            WHERE sc.student_id = ?
        ");
        $stmt_progress->execute([$student_id]);
        $progress_data = $stmt_progress->fetch();
        $avg_progress = $progress_data['total_lessons'] > 0
            ? round(($progress_data['viewed_lessons'] / $progress_data['total_lessons']) * 100)
            : 0;
    } catch (PDOException $e) {
        $avg_progress = 0;
    }

    // Get certificates count
    try {
        $stmt_certs = $pdo->prepare("SELECT COUNT(*) FROM course_certificates WHERE student_id = ?");
        $stmt_certs->execute([$student_id]);
        $certificates_count = $stmt_certs->fetchColumn();
    } catch (PDOException $e) {
        $certificates_count = 0;
    }

    // Get total spent
    try {
        $stmt_spent = $pdo->prepare("
            SELECT SUM(total_amount) FROM orders 
            WHERE buyer_id = ? AND status = 'completed'
        ");
        $stmt_spent->execute([$student_id]);
        $total_spent = $stmt_spent->fetchColumn() ?? 0;
    } catch (PDOException $e) {
        $total_spent = 0;
    }

    // Get recent courses - try different date columns
    try {
        // Try with enrolled_at first
        $stmt_recent_courses = $pdo->prepare("
            SELECT c.id, c.title, c.image_url
            FROM student_courses sc
            JOIN courses c ON sc.course_id = c.id
            WHERE sc.student_id = ?
            ORDER BY sc.enrolled_at DESC
            LIMIT 3
        ");
        $stmt_recent_courses->execute([$student_id]);
        $recent_courses = $stmt_recent_courses->fetchAll();
    } catch (PDOException $e) {
        // Try with last_accessed
        try {
            $stmt_recent_courses = $pdo->prepare("
                SELECT c.id, c.title, c.image_url
                FROM student_courses sc
                JOIN courses c ON sc.course_id = c.id
                WHERE sc.student_id = ?
                ORDER BY sc.last_accessed DESC
                LIMIT 3
            ");
            $stmt_recent_courses->execute([$student_id]);
            $recent_courses = $stmt_recent_courses->fetchAll();
        } catch (PDOException $e2) {
            // Just order by course ID
            try {
                $stmt_recent_courses = $pdo->prepare("
                    SELECT c.id, c.title, c.image_url
                    FROM student_courses sc
                    JOIN courses c ON sc.course_id = c.id
                    WHERE sc.student_id = ?
                    ORDER BY c.id DESC
                    LIMIT 3
                ");
                $stmt_recent_courses->execute([$student_id]);
                $recent_courses = $stmt_recent_courses->fetchAll();
            } catch (PDOException $e3) {
                $recent_courses = [];
            }
        }
    }

    // Get recent certificates - try different date columns
    $recent_certificates = [];
    $cert_queries = [
        "SELECT cc.*, c.title as course_title FROM course_certificates cc JOIN courses c ON cc.course_id = c.id WHERE cc.student_id = ? ORDER BY cc.issued_at DESC LIMIT 3",
        "SELECT cc.*, c.title as course_title FROM course_certificates cc JOIN courses c ON cc.course_id = c.id WHERE cc.student_id = ? ORDER BY cc.completion_date DESC LIMIT 3",
        "SELECT cc.*, c.title as course_title FROM course_certificates cc JOIN courses c ON cc.course_id = c.id WHERE cc.student_id = ? ORDER BY cc.created_at DESC LIMIT 3",
        "SELECT cc.*, c.title as course_title FROM course_certificates cc JOIN courses c ON cc.course_id = c.id WHERE cc.student_id = ? ORDER BY cc.id DESC LIMIT 3"
    ];

    foreach ($cert_queries as $query) {
        try {
            $stmt_recent_certs = $pdo->prepare($query);
            $stmt_recent_certs->execute([$student_id]);
            $recent_certificates = $stmt_recent_certs->fetchAll();
            break;
        } catch (PDOException $e) {
            continue;
        }
    }
} catch (PDOException $e) {
    error_log("Error in profile.php: " . $e->getMessage());
    // Don't redirect on error - show profile with limited data
    $error_message = "Erreur de chargement des données: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('profile') ?? 'Mon Profil' ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --info: #3b82f6;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
        }

        .container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .sidebar-header h2 {
            color: white;
            font-size: 1.5rem;
            font-weight: 800;
        }

        .sidebar-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.875rem;
        }

        .nav {
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.875rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item i {
            width: 24px;
            margin-right: 0.75rem;
        }

        .nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
        }

        .nav-item.active {
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.1), transparent);
            color: var(--primary);
            border-left: 3px solid var(--primary);
        }

        /* Main */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .page-header p {
            color: var(--gray-600);
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .profile-header {
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            padding: 3rem 2rem;
            color: white;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            border: 5px solid white;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--primary);
            font-weight: 800;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-body {
            padding: 2rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: 12px;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
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

        .stat-icon.primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .stat-icon.success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.info {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-900);
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .card-header h3 {
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 700;
        }

        .card-body {
            padding: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .course-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            border-radius: 8px;
            transition: background 0.3s ease;
        }

        .course-item:hover {
            background: var(--gray-50);
        }

        .course-thumb {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .course-thumb img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-graduation-cap"></i> TaaBia</h2>
                <p><?= __('student_space') ?? 'Espace Étudiant' ?></p>
            </div>

            <nav class="nav">
                <a href="index.php" class="nav-item">
                    <i class="fas fa-th-large"></i>
                    <?= __('dashboard') ?? 'Tableau de Bord' ?>
                </a>
                <a href="my_courses.php" class="nav-item">
                    <i class="fas fa-book"></i>
                    <?= __('my_courses') ?? 'Mes Cours' ?>
                </a>
                <a href="all_courses.php" class="nav-item">
                    <i class="fas fa-compass"></i>
                    <?= __('discover_courses') ?? 'Découvrir' ?>
                </a>
                <a href="course_lessons.php" class="nav-item">
                    <i class="fas fa-play-circle"></i>
                    <?= __('my_lessons') ?? 'Mes Leçons' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="my_certificates.php" class="nav-item">
                    <i class="fas fa-award"></i>
                    <?= __('certificates') ?? 'Certificats' ?>
                </a>
                <a href="profile.php" class="nav-item active">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Mon Profil' ?>
                </a>
                <a href="../auth/logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <?= __('logout') ?? 'Déconnexion' ?>
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main">
            <!-- Page Header -->
            <div class="page-header">
                <h1><?= __('my_profile') ?? 'Mon Profil' ?></h1>
                <p><?= __('manage_account') ?? 'Gérez vos informations personnelles et vos paramètres' ?></p>

                <?php if (isset($error_message)): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; color: #991b1b;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="../uploads/<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile">
                        <?php else: ?>
                            <?= strtoupper(substr($user['full_name'] ?? $user['fullname'] ?? 'U', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">
                        <?= htmlspecialchars($user['full_name'] ?? $user['fullname'] ?? 'Utilisateur') ?>
                    </h2>
                    <p style="font-size: 1.125rem; opacity: 0.9;">
                        <i class="fas fa-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                    </p>
                    <div style="margin-top: 1rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="edit_profile.php" class="btn btn-primary" style="background: white; color: var(--primary);">
                            <i class="fas fa-edit"></i> <?= __('edit_profile') ?? 'Modifier le profil' ?>
                        </a>
                        <a href="language_settings.php" class="btn btn-secondary" style="background: rgba(255, 255, 255, 0.2); color: white; border: 1px solid white;">
                            <i class="fas fa-globe"></i> <?= __('language') ?? 'Langue' ?>
                        </a>
                    </div>
                </div>

                <div class="profile-body">
                    <h3 style="margin-bottom: 1.5rem; color: var(--gray-900);">
                        <i class="fas fa-info-circle"></i> <?= __('personal_information') ?? 'Informations Personnelles' ?>
                    </h3>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-user"></i> <?= __('full_name') ?? 'Nom Complet' ?>
                            </div>
                            <div class="info-value"><?= htmlspecialchars($user['full_name'] ?? $user['fullname'] ?? 'N/A') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-envelope"></i> <?= __('email') ?? 'Email' ?>
                            </div>
                            <div class="info-value"><?= htmlspecialchars($user['email']) ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-phone"></i> <?= __('phone') ?? 'Téléphone' ?>
                            </div>
                            <div class="info-value"><?= htmlspecialchars($user['phone'] ?? 'Non renseigné') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-calendar"></i> <?= __('member_since') ?? 'Membre depuis' ?>
                            </div>
                            <div class="info-value"><?= date('d F Y', strtotime($user['created_at'])) ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-user-tag"></i> <?= __('role') ?? 'Rôle' ?>
                            </div>
                            <div class="info-value"><?= ucfirst($user['role'] ?? 'Student') ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label">
                                <i class="fas fa-globe"></i> <?= __('language') ?? 'Langue' ?>
                            </div>
                            <div class="info-value"><?= strtoupper($user['language'] ?? $_SESSION['user_language'] ?? 'FR') ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="stat-label"><?= __('enrolled_courses') ?? 'Cours Inscrits' ?></div>
                    <div class="stat-value"><?= $total_courses ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-label"><?= __('average_progress') ?? 'Progression Moyenne' ?></div>
                    <div class="stat-value"><?= $avg_progress ?>%</div>
                </div>

                <a href="my_certificates.php" style="text-decoration: none;">
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-award"></i>
                        </div>
                        <div class="stat-label"><?= __('certificates_earned') ?? 'Certificats Obtenus' ?></div>
                        <div class="stat-value"><?= $certificates_count ?></div>
                    </div>
                </a>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-label"><?= __('total_spent') ?? 'Total Dépensé' ?></div>
                    <div class="stat-value"><?= number_format($total_spent, 0) ?> GHS</div>
                </div>
            </div>

            <!-- Recent Courses -->
            <?php if (!empty($recent_courses)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-book-open"></i> <?= __('recent_courses') ?? 'Cours Récents' ?></h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recent_courses as $course): ?>
                            <div class="course-item">
                                <div class="course-thumb">
                                    <?php if (!empty($course['image_url'])): ?>
                                        <img src="../uploads/<?= htmlspecialchars($course['image_url']) ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-book"></i>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--gray-900);">
                                        <?= htmlspecialchars($course['title']) ?>
                                    </div>
                                </div>
                                <a href="view_course.php?course_id=<?= $course['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-arrow-right"></i> <?= __('view') ?? 'Voir' ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Certificates -->
            <?php if (!empty($recent_certificates)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-trophy"></i> <?= __('my_certificates') ?? 'Mes Certificats' ?></h3>
                    </div>
                    <div class="card-body">
                        <?php foreach ($recent_certificates as $cert): ?>
                            <div class="course-item">
                                <div style="width: 60px; height: 60px; border-radius: 8px; background: linear-gradient(135deg, #fbbf24, #f59e0b); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem;">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: var(--gray-900); margin-bottom: 0.25rem;">
                                        <?= htmlspecialchars($cert['course_title']) ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                                        <?php
                                        $cert_date = $cert['issued_at'] ?? $cert['completion_date'] ?? $cert['created_at'] ?? null;
                                        if ($cert_date) {
                                            echo (__('issued_on') ?? 'Délivré le') . ' ' . date('d F Y', strtotime($cert_date));
                                        }
                                        ?>
                                    </div>
                                </div>
                                <a href="../instructor/view_certificate.php?id=<?= $cert['id'] ?>"
                                    class="btn btn-primary" target="_blank">
                                    <i class="fas fa-download"></i> <?= __('download') ?? 'Télécharger' ?>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Account Actions -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> <?= __('account_settings') ?? 'Paramètres du Compte' ?></h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; gap: 1rem;">
                        <a href="edit_profile.php" class="btn btn-primary" style="justify-content: flex-start;">
                            <i class="fas fa-user-edit"></i>
                            <?= __('edit_profile') ?? 'Modifier mon profil' ?>
                        </a>

                        <a href="language_settings.php" class="btn btn-secondary" style="justify-content: flex-start;">
                            <i class="fas fa-globe"></i>
                            <?= __('language_settings') ?? 'Paramètres de langue' ?>
                        </a>

                        <button onclick="if(confirm('<?= __('confirm_logout') ?? 'Êtes-vous sûr de vouloir vous déconnecter ?' ?>')) window.location.href='../auth/logout.php'"
                            class="btn btn-danger" style="justify-content: flex-start;">
                            <i class="fas fa-sign-out-alt"></i>
                            <?= __('logout') ?? 'Se déconnecter' ?>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards
            const cards = document.querySelectorAll('.stat-card, .card, .profile-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>

</html>