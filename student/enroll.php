<?php

/**
 * Course Enrollment Page
 * Modern LMS interface for enrolling in courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? 0;
$error_message = '';
$success_message = '';

// Validate course ID
if (!$course_id) {
    header('Location: all_courses.php');
    exit;
}

try {
    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as instructor_name, u.email as instructor_email
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        $_SESSION['error_message'] = __('course_not_found') ?? 'Cours introuvable';
        header('Location: all_courses.php');
        exit;
    }

    // Check if course is active
    if (!$course['is_active']) {
        $_SESSION['error_message'] = __('course_not_active') ?? 'Ce cours n\'est pas disponible';
        header('Location: all_courses.php');
        exit;
    }

    // Count lessons
    $stmt_lessons = $pdo->prepare("SELECT COUNT(*) FROM lessons WHERE course_id = ?");
    $stmt_lessons->execute([$course_id]);
    $lesson_count = $stmt_lessons->fetchColumn();

    // Count current enrollments
    $stmt_enroll = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE course_id = ?");
    $stmt_enroll->execute([$course_id]);
    $enrollment_count = $stmt_enroll->fetchColumn();

    // Check if already enrolled
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmt_check->execute([$student_id, $course_id]);
    $already_enrolled = $stmt_check->fetchColumn() > 0;

    if ($already_enrolled) {
        $_SESSION['info_message'] = __('already_enrolled') ?? 'Vous êtes déjà inscrit à ce cours';
        header('Location: view_course.php?course_id=' . $course_id);
        exit;
    }

    // Process enrollment
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_enrollment'])) {

        // Check if course is free or paid
        if ($course['price'] > 0) {
            // Create an order for the course first
            try {
                $stmt_order = $pdo->prepare("
                    INSERT INTO orders (buyer_id, product_id, total_amount, status, created_at, order_type)
                    VALUES (?, NULL, ?, 'pending', NOW(), 'course')
                ");
                $stmt_order->execute([$student_id, $course['price']]);
                $order_id = $pdo->lastInsertId();

                // Store course info in session for after payment
                $_SESSION['pending_course_enrollment'] = [
                    'course_id' => $course_id,
                    'course_title' => $course['title'],
                    'order_id' => $order_id
                ];

                error_log("Created order #$order_id for course enrollment - redirecting to payment");

                // Redirect to payment page
                header('Location: ../Payment/checkout.php?order_id=' . $order_id);
                exit;
            } catch (PDOException $e) {
                error_log("Error creating order for course: " . $e->getMessage());

                // Try alternative: redirect to course payment directly
                $_SESSION['pending_enrollment'] = [
                    'course_id' => $course_id,
                    'course_title' => $course['title'],
                    'price' => $course['price']
                ];
                header('Location: course_payment.php?course_id=' . $course_id);
                exit;
            }
        } else {
            // Free course - enroll directly
            // Try different INSERT queries based on table structure
            $insert_queries = [
                // Try 1: With enrolled_at column
                "INSERT INTO student_courses (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())",
                // Try 2: With created_at column only
                "INSERT INTO student_courses (student_id, course_id, created_at) VALUES (?, ?, NOW())",
                // Try 3: Minimal - just student_id and course_id
                "INSERT INTO student_courses (student_id, course_id) VALUES (?, ?)",
            ];

            $enrolled = false;
            $last_error = '';

            foreach ($insert_queries as $query) {
                try {
                    $stmt_insert = $pdo->prepare($query);
                    $stmt_insert->execute([$student_id, $course_id]);
                    $enrolled = true;
                    error_log("Enrollment successful with query: $query");
                    break;
                } catch (PDOException $e) {
                    $last_error = $e->getMessage();
                    error_log("Enrollment attempt failed: " . $e->getMessage());
                    continue;
                }
            }

            if ($enrolled) {
                $_SESSION['success_message'] = sprintf(
                    __('enrollment_success') ?? 'Félicitations ! Vous êtes inscrit au cours "%s"',
                    $course['title']
                );
                header('Location: view_course.php?course_id=' . $course_id);
                exit;
            } else {
                error_log("All enrollment attempts failed. Last error: $last_error");
                $error_message = (__('enrollment_failed') ?? 'Erreur lors de l\'inscription') . ': ' . $last_error;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in enroll.php: " . $e->getMessage());
    $error_message = __('database_error') ?? 'Erreur de base de données. Veuillez réessayer.';
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('enroll_course') ?? 'Inscription au Cours' ?> | TaaBia</title>

    <!-- External Resources -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --white: #ffffff;
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
            max-width: 1200px;
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

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            background: linear-gradient(135deg, var(--gray-50), white);
        }

        .card-header h3 {
            font-size: 1.25rem;
            color: var(--gray-900);
            font-weight: 700;
        }

        .card-body {
            padding: 2rem;
        }

        .course-preview {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .course-image {
            width: 100%;
            height: 200px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .course-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }

        .course-details h2 {
            color: var(--gray-900);
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .instructor-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .instructor-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .course-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
        }

        .stat-item i {
            color: var(--primary);
        }

        .benefits {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .benefits h4 {
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .benefits ul {
            list-style: none;
            padding: 0;
        }

        .benefits li {
            padding: 0.75rem 0;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .benefits li i {
            color: var(--success);
            font-size: 1.1rem;
        }

        .price-box {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 2rem;
            border-radius: 12px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .price-box.free {
            background: linear-gradient(135deg, var(--success), #059669);
        }

        .price-label {
            font-size: 0.875rem;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }

        .price-value {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
        }

        .price-description {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-lg {
            padding: 1.25rem 3rem;
            font-size: 1.125rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid var(--warning);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .main {
                margin-left: 0;
            }

            .course-preview {
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
                <a href="all_courses.php" class="nav-item active">
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
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i>
                    <?= __('profile') ?? 'Profil' ?>
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
                <h1><?= __('enroll_course') ?? 'Inscription au Cours' ?></h1>
                <p><?= __('review_enroll_confirm') ?? 'Vérifiez les détails et confirmez votre inscription' ?></p>
            </div>

            <!-- Error/Success Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($error_message) ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div><?= htmlspecialchars($success_message) ?></div>
                </div>
            <?php endif; ?>

            <!-- Course Preview -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> <?= __('course_details') ?? 'Détails du Cours' ?></h3>
                </div>
                <div class="card-body">
                    <div class="course-preview">
                        <div class="course-image">
                            <?php if (!empty($course['image_url'])): ?>
                                <img src="../uploads/<?= htmlspecialchars($course['image_url']) ?>"
                                    alt="<?= htmlspecialchars($course['title']) ?>">
                            <?php else: ?>
                                <i class="fas fa-book"></i>
                            <?php endif; ?>
                        </div>

                        <div class="course-details">
                            <h2><?= htmlspecialchars($course['title']) ?></h2>

                            <div class="instructor-info">
                                <div class="instructor-avatar">
                                    <?= strtoupper(substr($course['instructor_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600; color: var(--gray-900);">
                                        <?= htmlspecialchars($course['instructor_name']) ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: var(--gray-600);">
                                        <?= __('course_instructor') ?? 'Instructeur du cours' ?>
                                    </div>
                                </div>
                            </div>

                            <p style="color: var(--gray-700); line-height: 1.8; margin-bottom: 1.5rem;">
                                <?= nl2br(htmlspecialchars($course['description'] ?? '')) ?>
                            </p>

                            <div class="course-stats">
                                <div class="stat-item">
                                    <i class="fas fa-play-circle"></i>
                                    <span><strong><?= $lesson_count ?></strong> <?= __('lessons') ?? 'leçons' ?></span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-users"></i>
                                    <span><strong><?= $enrollment_count ?></strong> <?= __('students_enrolled') ?? 'inscrits' ?></span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?= __('lifetime_access') ?? 'Accès à vie' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Benefits -->
                    <div class="benefits">
                        <h4><i class="fas fa-gift"></i> <?= __('what_you_get') ?? 'Ce que vous obtiendrez' ?></h4>
                        <ul>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <?= __('access_all_lessons') ?? 'Accès illimité à toutes les leçons du cours' ?>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <?= __('track_progress') ?? 'Suivi automatique de votre progression' ?>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <?= __('instructor_support') ?? 'Support et réponses de l\'instructeur' ?>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <?= __('completion_certificate') ?? 'Certificat de complétion à la fin du cours' ?>
                            </li>
                            <li>
                                <i class="fas fa-check-circle"></i>
                                <?= __('lifetime_access_benefit') ?? 'Accès permanent au contenu, même après complétion' ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Pricing and Enrollment -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-shopping-cart"></i> <?= __('enrollment_confirmation') ?? 'Confirmation d\'Inscription' ?></h3>
                </div>
                <div class="card-body">
                    <!-- Price Box -->
                    <div class="price-box <?= $course['price'] == 0 ? 'free' : '' ?>">
                        <div class="price-label">
                            <?= $course['price'] == 0
                                ? (__('course_price') ?? 'Prix du cours')
                                : (__('course_price') ?? 'Prix du cours') ?>
                        </div>
                        <div class="price-value">
                            <?php if ($course['price'] == 0): ?>
                                <i class="fas fa-gift"></i> <?= __('free') ?? 'GRATUIT' ?>
                            <?php else: ?>
                                <?= number_format($course['price'], 2) ?> GHS
                            <?php endif; ?>
                        </div>
                        <div class="price-description">
                            <?= $course['price'] == 0
                                ? (__('free_instant_access') ?? 'Accès immédiat et gratuit')
                                : (__('one_time_payment') ?? 'Paiement unique - Accès à vie') ?>
                        </div>
                    </div>

                    <!-- Enrollment Notice -->
                    <?php if ($course['price'] > 0): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong><?= __('payment_required') ?? 'Paiement requis' ?></strong><br>
                                <?= __('redirect_payment_notice') ?? 'Vous serez redirigé vers la page de paiement pour finaliser votre inscription' ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                            <div>
                                <strong><?= __('free_enrollment') ?? 'Inscription gratuite' ?></strong><br>
                                <?= __('instant_access_notice') ?? 'Vous aurez un accès immédiat à toutes les leçons après l\'inscription' ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Enrollment Form -->
                    <form method="POST" id="enrollForm">
                        <input type="hidden" name="confirm_enrollment" value="1">

                        <div class="action-buttons">
                            <button type="submit" class="btn <?= $course['price'] > 0 ? 'btn-primary' : 'btn-success' ?> btn-lg">
                                <i class="fas fa-<?= $course['price'] > 0 ? 'credit-card' : 'user-plus' ?>"></i>
                                <?= $course['price'] > 0
                                    ? (__('proceed_payment') ?? 'Procéder au paiement')
                                    : (__('enroll_now') ?? 'S\'inscrire maintenant') ?>
                            </button>

                            <a href="all_courses.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-arrow-left"></i>
                                <?= __('back') ?? 'Retour' ?>
                            </a>
                        </div>

                        <p style="text-align: center; color: var(--gray-600); margin-top: 1.5rem; font-size: 0.875rem;">
                            <?= __('enrollment_terms') ?? 'En vous inscrivant, vous acceptez nos conditions d\'utilisation' ?>
                        </p>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add confirmation dialog
            const form = document.getElementById('enrollForm');
            form.addEventListener('submit', function(e) {
                const isFree = <?= $course['price'] == 0 ? 'true' : 'false' ?>;
                const courseName = <?= json_encode($course['title']) ?>;

                const confirmMessage = isFree ?
                    `<?= __('confirm_free_enrollment') ?? 'Confirmer l\'inscription gratuite au cours' ?> "${courseName}" ?` :
                    `<?= __('confirm_paid_enrollment') ?? 'Procéder au paiement pour le cours' ?> "${courseName}" ?`;

                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                }
            });

            // Animate elements
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);
            });
        });
    </script>
</body>

</html>