<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

try {
    // Get comprehensive statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = ?");
    $stmt->execute([$instructor_id]);
    $total_courses = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT student_id)
        FROM student_courses sc
        INNER JOIN courses c ON sc.course_id = c.id
        WHERE c.instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $total_students = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM course_contents
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmt->execute([$instructor_id]);
    $total_contents = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM lessons
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?)
    ");
    $stmt->execute([$instructor_id]);
    $total_lessons = $stmt->fetchColumn();

    // Get earnings data
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total 
        FROM transactions 
        WHERE instructor_id = ?
    ");
    $stmt->execute([$instructor_id]);
    $earnings = $stmt->fetch();
    $total_sales = $earnings['count'] ?? 0;
    $total_earnings = $earnings['total'] ?? 0;

    // Get recent transactions
    $stmt = $pdo->prepare("
        SELECT t.id, t.type, t.amount, u.full_name AS buyer_name, t.created_at,
               c.title as course_title
        FROM transactions t
        JOIN users u ON t.student_id = u.id
        LEFT JOIN courses c ON t.course_id = c.id
        WHERE t.instructor_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id]);
    $recent_transactions = $stmt->fetchAll();

    // Get monthly earnings for chart
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total
        FROM transactions
        WHERE instructor_id = ?
        GROUP BY month
        ORDER BY month ASC
        LIMIT 12
    ");
    $stmt->execute([$instructor_id]);
    $monthly_earnings = $stmt->fetchAll();

    // Get pending submissions
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM submissions 
        WHERE course_id IN (SELECT id FROM courses WHERE instructor_id = ?) 
        AND status = 'pending'
    ");
    $stmt->execute([$instructor_id]);
    $pending_submissions = $stmt->fetchColumn();

    // Get course performance
    $stmt = $pdo->prepare("
        SELECT c.title, c.id,
               COUNT(sc.student_id) as enrollment_count,
               AVG(sc.progress_percent) as avg_progress
        FROM courses c
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE c.instructor_id = ?
        GROUP BY c.id
        ORDER BY enrollment_count DESC
        LIMIT 5
    ");
    $stmt->execute([$instructor_id]);
    $top_courses = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error in instructor dashboard: " . $e->getMessage());
    $total_courses = 0;
    $total_students = 0;
    $total_contents = 0;
    $total_lessons = 0;
    $total_sales = 0;
    $total_earnings = 0;
    $recent_transactions = [];
    $monthly_earnings = [];
    $pending_submissions = 0;
    $top_courses = [];
}
?>

<!DOCTYPE html>
<html lang="<?= getCurrentLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?> <?= __('instructor_space') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="instructor-styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <div class="instructor-sidebar">
            <div class="instructor-sidebar-header">
                <h2><i class="fas fa-chalkboard-teacher"></i> TaaBia</h2>
                <p><?= __('instructor_space') ?></p>
            </div>
            
            <nav class="instructor-nav">
                <a href="index.php" class="instructor-nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <?= __('dashboard') ?>
                </a>
                <a href="my_courses.php" class="instructor-nav-item">
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
                    <?= __('pending_submissions') ?>
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

        <!-- Main Content -->
        <div class="instructor-main">
            <div class="instructor-header">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h1><?= __('dashboard') ?> <?= __('instructor_space') ?></h1>
                        <p><?= __('welcome') ?> <?= __('instructor_space') ?> - <?= __('manage_courses_students') ?></p>
                    </div>
                    <div>
                        <?php include '../includes/language_switcher.php'; ?>
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
                    <div class="instructor-card-title"><?= __('published_courses') ?></div>
                    <div class="instructor-card-value"><?= $total_courses ?></div>
                    <div class="instructor-card-description"><?= __('active_courses') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('enrolled_students') ?></div>
                    <div class="instructor-card-value"><?= $total_students ?></div>
                    <div class="instructor-card-description"><?= __('active_students') ?></div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon info">
                            <i class="fas fa-play-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title"><?= __('created_lessons') ?></div>
                    <div class="instructor-card-value"><?= $total_lessons ?></div>
                    <div class="instructor-card-description">Contenus disponibles</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon warning">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Devoirs à valider</div>
                    <div class="instructor-card-value"><?= $pending_submissions ?></div>
                    <div class="instructor-card-description">En attente</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon accent">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Ventes totales</div>
                    <div class="instructor-card-value"><?= $total_sales ?></div>
                    <div class="instructor-card-description">Transactions</div>
                </div>

                <div class="instructor-card">
                    <div class="instructor-card-header">
                        <div class="instructor-card-icon success">
                            <i class="fas fa-coins"></i>
                        </div>
                    </div>
                    <div class="instructor-card-title">Gains totaux</div>
                    <div class="instructor-card-value"><?= number_format($total_earnings, 2) ?> GHS</div>
                    <div class="instructor-card-description">Revenus générés</div>
                </div>
            </div>

            <!-- Charts and Analytics -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-6); margin-bottom: var(--spacing-8);">
                <!-- Earnings Chart -->
                <div class="instructor-table-container">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-chart-line"></i> Évolution des gains
                        </h3>
                    </div>
                    <div style="padding: var(--spacing-6);">
                        <canvas id="earningsChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Top Courses -->
                <div class="instructor-table-container">
                    <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                        <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                            <i class="fas fa-trophy"></i> Cours populaires
                        </h3>
                    </div>
                    <div style="padding: var(--spacing-4);">
                        <?php if (count($top_courses) > 0): ?>
                            <?php foreach ($top_courses as $course): ?>
                                <div style="
                                    display: flex; 
                                    justify-content: space-between; 
                                    align-items: center; 
                                    padding: var(--spacing-3) 0; 
                                    border-bottom: 1px solid var(--gray-100);
                                ">
                                    <div>
                                        <div style="font-weight: 600; color: var(--gray-900); margin-bottom: var(--spacing-1);">
                                            <?= htmlspecialchars($course['title']) ?>
                                        </div>
                                        <div style="font-size: var(--font-size-sm); color: var(--gray-500);">
                                            <?= $course['enrollment_count'] ?> inscrits
                                        </div>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: var(--primary-color);">
                                            <?= round($course['avg_progress'] ?? 0) ?>%
                                        </div>
                                        <div class="instructor-progress" style="width: 60px; margin-top: var(--spacing-1);">
                                            <div class="instructor-progress-bar" style="width: <?= $course['avg_progress'] ?? 0 ?>%;"></div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="instructor-empty">
                                <div class="instructor-empty-icon">
                                    <i class="fas fa-book"></i>
                                </div>
                                <div class="instructor-empty-title">Aucun cours</div>
                                <div class="instructor-empty-description">
                                    Créez votre premier cours pour commencer
                                </div>
                                <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                                    <i class="fas fa-plus"></i>
                                    Créer un cours
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="instructor-table-container">
                <div style="padding: var(--spacing-6); border-bottom: 1px solid var(--gray-200);">
                    <h3 style="margin: 0; color: var(--gray-900); font-size: var(--font-size-lg);">
                        <i class="fas fa-clock"></i> Transactions récentes
                    </h3>
                </div>
                
                <?php if (count($recent_transactions) > 0): ?>
                    <table class="instructor-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Cours</th>
                                <th>Étudiant</th>
                                <th>Montant</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                                <tr>
                                    <td>#<?= $transaction['id'] ?></td>
                                    <td>
                                        <span class="instructor-badge <?= $transaction['type'] == 'course' ? 'success' : 'info' ?>">
                                            <?= ucfirst($transaction['type']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($transaction['course_title'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($transaction['buyer_name']) ?></td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--success-color);">
                                            <?= number_format($transaction['amount'], 2) ?> GHS
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="instructor-empty">
                        <div class="instructor-empty-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="instructor-empty-title">Aucune transaction</div>
                        <div class="instructor-empty-description">
                            Aucune transaction récente trouvée
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div style="margin-top: var(--spacing-8); display: flex; gap: var(--spacing-4); flex-wrap: wrap;">
                <a href="add_course.php" class="instructor-btn instructor-btn-primary">
                    <i class="fas fa-plus"></i>
                    Créer un nouveau cours
                </a>
                
                <a href="validate_submissions.php" class="instructor-btn instructor-btn-warning">
                    <i class="fas fa-check-circle"></i>
                    Valider les devoirs
                </a>
                
                <a href="students.php" class="instructor-btn instructor-btn-success">
                    <i class="fas fa-users"></i>
                    Gérer mes étudiants
                </a>
                
                <a href="earnings.php" class="instructor-btn instructor-btn-info">
                    <i class="fas fa-chart-line"></i>
                    Voir mes gains
                </a>
            </div>
        </div>
    </div>

    <script>
        // Earnings Chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const earningsChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($monthly_earnings, 'month')) ?>,
                datasets: [{
                    label: 'Gains mensuels (GHS)',
                    data: <?= json_encode(array_column($monthly_earnings, 'total')) ?>,
                    borderColor: 'var(--primary-color)',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
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
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.instructor-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Animate progress bars
            const progressBars = document.querySelectorAll('.instructor-progress-bar');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>