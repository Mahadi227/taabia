<?php

/**
 * My Certificates Page
 * Display all earned certificates
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];

try {
    // Get all certificates for this student - try different date columns
    $certificates = [];
    $queries = [
        // Try 1: with issued_at
        "SELECT cc.*, c.title as course_title, c.description as course_description, c.image_url as course_image, u.full_name as instructor_name
         FROM course_certificates cc
         JOIN courses c ON cc.course_id = c.id
         JOIN users u ON c.instructor_id = u.id
         WHERE cc.student_id = ?
         ORDER BY cc.issued_at DESC, cc.completion_date DESC",

        // Try 2: with completion_date only
        "SELECT cc.*, c.title as course_title, c.description as course_description, c.image_url as course_image, u.full_name as instructor_name
         FROM course_certificates cc
         JOIN courses c ON cc.course_id = c.id
         JOIN users u ON c.instructor_id = u.id
         WHERE cc.student_id = ?
         ORDER BY cc.completion_date DESC",

        // Try 3: with created_at
        "SELECT cc.*, c.title as course_title, c.description as course_description, c.image_url as course_image, u.full_name as instructor_name
         FROM course_certificates cc
         JOIN courses c ON cc.course_id = c.id
         JOIN users u ON c.instructor_id = u.id
         WHERE cc.student_id = ?
         ORDER BY cc.created_at DESC",

        // Try 4: just by ID
        "SELECT cc.*, c.title as course_title, c.description as course_description, c.image_url as course_image, u.full_name as instructor_name
         FROM course_certificates cc
         JOIN courses c ON cc.course_id = c.id
         JOIN users u ON c.instructor_id = u.id
         WHERE cc.student_id = ?
         ORDER BY cc.id DESC"
    ];

    foreach ($queries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute([$student_id]);
            $certificates = $stmt->fetchAll();
            error_log("Certificates loaded successfully");
            break;
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            continue;
        }
    }

    $total_certificates = count($certificates);
    error_log("Student $student_id has $total_certificates certificates");
} catch (PDOException $e) {
    error_log("Error loading certificates: " . $e->getMessage());
    $certificates = [];
    $total_certificates = 0;
    $error_message = "Erreur de chargement des certificats: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('my_certificates') ?? 'Mes Certificats' ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --gold: #fbbf24;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-600: #4b5563;
            --gray-700: #374151;
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

        /* Certificate Grid */
        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .certificate-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 3px solid transparent;
        }

        .certificate-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15);
            border-color: var(--gold);
        }

        .certificate-header {
            background: linear-gradient(135deg, var(--gold), #f59e0b);
            padding: 2rem;
            text-align: center;
            color: white;
        }

        .certificate-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .certificate-body {
            padding: 2rem;
        }

        .course-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .certificate-info {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.875rem;
        }

        .info-label {
            color: var(--gray-600);
        }

        .info-value {
            font-weight: 600;
            color: var(--gray-900);
        }

        .certificate-actions {
            display: flex;
            gap: 0.75rem;
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
            flex: 1;
            justify-content: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .empty-state i {
            font-size: 5rem;
            color: var(--gray-200);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .empty-state p {
            color: var(--gray-600);
            margin-bottom: 2rem;
        }

        .stat-banner {
            background: linear-gradient(135deg, var(--gold), #f59e0b);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            text-align: center;
            margin-bottom: 2rem;
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.3);
        }

        .stat-banner h2 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .certificates-grid {
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
                <a href="my_certificates.php" class="nav-item active">
                    <i class="fas fa-award"></i>
                    <?= __('certificates') ?? 'Certificats' ?>
                </a>
                <a href="messages.php" class="nav-item">
                    <i class="fas fa-envelope"></i>
                    <?= __('messages') ?? 'Messages' ?>
                </a>
                <a href="orders.php" class="nav-item">
                    <i class="fas fa-shopping-cart"></i>
                    <?= __('my_purchases') ?? 'Mes Achats' ?>
                </a>
                <a href="profile.php" class="nav-item">
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
                <h1><i class="fas fa-trophy"></i> <?= __('my_certificates') ?? 'Mes Certificats' ?></h1>
                <p><?= __('view_download_certificates') ?? 'Consultez et téléchargez vos certificats de réussite' ?></p>

                <?php if (isset($error_message)): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 8px; color: #991b1b;">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Total Certificates Banner -->
            <?php if ($total_certificates > 0): ?>
                <div class="stat-banner">
                    <div style="font-size: 1rem; opacity: 0.9; margin-bottom: 0.5rem;">
                        <?= __('total_certificates_earned') ?? 'Total de Certificats Obtenus' ?>
                    </div>
                    <h2><?= $total_certificates ?></h2>
                    <p style="opacity: 0.9;">
                        <?= __('congratulations_achievement') ?? 'Félicitations pour vos accomplissements !' ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Certificates Grid -->
            <?php if (!empty($certificates)): ?>
                <div class="certificates-grid">
                    <?php foreach ($certificates as $cert): ?>
                        <div class="certificate-card">
                            <div class="certificate-header">
                                <div class="certificate-icon">
                                    <i class="fas fa-certificate"></i>
                                </div>
                                <h3 style="font-size: 1.25rem; font-weight: 700;">
                                    <?= __('certificate_completion') ?? 'Certificat de Complétion' ?>
                                </h3>
                            </div>

                            <div class="certificate-body">
                                <h4 class="course-title"><?= htmlspecialchars($cert['course_title']) ?></h4>

                                <div class="certificate-info">
                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-hashtag"></i> <?= __('certificate_number') ?? 'Numéro' ?>:
                                        </span>
                                        <span class="info-value"><?= htmlspecialchars($cert['certificate_number']) ?></span>
                                    </div>

                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-calendar"></i> <?= __('completion_date') ?? 'Date de complétion' ?>:
                                        </span>
                                        <span class="info-value"><?= date('d/m/Y', strtotime($cert['completion_date'])) ?></span>
                                    </div>

                                    <?php if (isset($cert['issued_at'])): ?>
                                        <div class="info-row">
                                            <span class="info-label">
                                                <i class="fas fa-calendar-check"></i> <?= __('issued_on') ?? 'Délivré le' ?>:
                                            </span>
                                            <span class="info-value"><?= date('d/m/Y', strtotime($cert['issued_at'])) ?></span>
                                        </div>
                                    <?php elseif (isset($cert['created_at'])): ?>
                                        <div class="info-row">
                                            <span class="info-label">
                                                <i class="fas fa-calendar-check"></i> <?= __('created_on') ?? 'Créé le' ?>:
                                            </span>
                                            <span class="info-value"><?= date('d/m/Y', strtotime($cert['created_at'])) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (isset($cert['final_grade']) && $cert['final_grade'] > 0): ?>
                                        <div class="info-row">
                                            <span class="info-label">
                                                <i class="fas fa-star"></i> <?= __('final_grade') ?? 'Note finale' ?>:
                                            </span>
                                            <span class="info-value"><?= round($cert['final_grade'], 1) ?>%</span>
                                        </div>
                                    <?php endif; ?>

                                    <div class="info-row">
                                        <span class="info-label">
                                            <i class="fas fa-user"></i> <?= __('instructor') ?? 'Instructeur' ?>:
                                        </span>
                                        <span class="info-value"><?= htmlspecialchars($cert['instructor_name']) ?></span>
                                    </div>
                                </div>

                                <div class="certificate-actions">
                                    <a href="../instructor/view_certificate.php?id=<?= $cert['id'] ?>"
                                        class="btn btn-primary" target="_blank">
                                        <i class="fas fa-eye"></i> <?= __('view') ?? 'Voir' ?>
                                    </a>
                                    <a href="../instructor/generate_certificate.php?id=<?= $cert['id'] ?>"
                                        class="btn btn-secondary" target="_blank">
                                        <i class="fas fa-download"></i> <?= __('download') ?? 'Télécharger' ?>
                                    </a>
                                </div>

                                <!-- Verification Code -->
                                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--gray-50); border-radius: 8px; text-align: center;">
                                    <small style="color: var(--gray-600); display: block; margin-bottom: 0.25rem;">
                                        <?= __('verification_code') ?? 'Code de vérification' ?>:
                                    </small>
                                    <code style="font-weight: 700; color: var(--primary); font-size: 0.95rem;">
                                        <?= htmlspecialchars($cert['verification_code']) ?>
                                    </code>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-certificate"></i>
                    <h3><?= __('no_certificates_yet') ?? 'Aucun certificat obtenu' ?></h3>
                    <p><?= __('complete_courses_earn') ?? 'Complétez vos cours à 100% pour obtenir des certificats' ?></p>
                    <a href="my_courses.php" class="btn btn-primary">
                        <i class="fas fa-book"></i> <?= __('view_my_courses') ?? 'Voir mes cours' ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div style="background: white; padding: 2rem; border-radius: 12px; margin-top: 2rem; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);">
                <h3 style="color: var(--gray-900); margin-bottom: 1rem;">
                    <i class="fas fa-info-circle"></i> <?= __('about_certificates') ?? 'À propos des certificats' ?>
                </h3>
                <ul style="color: #004085; line-height: 1.8; padding-left: 1.5rem;">
                    <li><?= __('Les certificats sont générés automatiquement lorsque vous complétez 100% d\'un cours') ?? 'Les certificats sont générés automatiquement lorsque vous complétez 100% d\'un cours' ?></li>
                    <li><?= __('Chaque certificat contient un code de vérification unique') ?? 'Chaque certificat contient un code de vérification unique' ?></li>
                    <li><?= __('Vous pouvez télécharger vos certificats en PDF à tout moment') ?? 'Vous pouvez télécharger vos certificats en PDF à tout moment' ?></li>
                    <li><?= __('Les certificats sont reconnus et peuvent être partagés sur LinkedIn') ?? 'Les certificats sont reconnus et peuvent être partagés sur LinkedIn' ?></li>
                </ul>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate certificates
            const cards = document.querySelectorAll('.certificate-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'scale(0.9)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'scale(1)';
                }, index * 100);
            });
        });
    </script>
</body>

</html>