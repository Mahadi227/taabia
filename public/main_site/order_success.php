<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Start session
session_start();

$order_id = $_GET['order_id'] ?? null;

// Get order details if order_id is provided
$order = null;
if ($order_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, t.status as payment_status 
            FROM orders o 
            LEFT JOIN transactions t ON o.id = t.order_id 
            WHERE o.id = ?
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
    } catch (PDOException $e) {
        // Order not found or error
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('order_confirmation') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .success-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--spacing-xl);
            text-align: center;
        }

        .success-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-3xl);
            margin-bottom: var(--spacing-xl);
        }

        .success-icon {
            font-size: 4rem;
            color: var(--success-color);
            margin-bottom: var(--spacing-lg);
        }

        .success-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .success-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: var(--spacing-xl);
        }

        .order-details {
            background: var(--bg-secondary);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }

        .order-number {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .order-info {
            margin-top: var(--spacing-md);
            color: var(--text-secondary);
        }

        .success-actions {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        @media (max-width: 768px) {
            .success-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
     <!-- Header -->
     <header class="header">
        <nav class="navbar">
            <a href="index.php" class="logo">
                <i class="fas fa-graduation-cap"></i> TaaBia
            </a>
            
            <button class="hamburger" id="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </button>
            <ul class="nav-menu" id="nav-menu">
                <li><a href="index.php" class="nav-link"><?= __('welcome') ?></a></li>
                <li><a href="courses.php" class="nav-link"><?= __('courses') ?></a></li>
                <li><a href="shop.php" class="nav-link"><?= __('shop') ?></a></li>
                <li><a href="upcoming_events.php" class="nav-link"><?= __('events') ?></a></li>
                <li><a href="blog.php" class="nav-link"><?= __('blog') ?></a></li>
                <li><a href="about.php" class="nav-link"><?= __('about') ?></a></li>
                <li><a href="contact.php" class="nav-link"><?= __('contact') ?></a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>
                <li style="margin-left: auto;">
                    <?php include '../../includes/public_language_switcher.php'; ?>
                </li>
            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> <?= __('my_profile') ?>
                    </a>
                    <a href="../../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('login') ?>
                    </a>
                    <a href="../../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?= __('register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="success-container">
            <div class="success-card">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="success-title"><?= __('order_success_title') ?></h1>
                <p class="success-message"><?= __('order_success_message') ?></p>
                
                <?php if ($order): ?>
                    <div class="order-details">
                        <div class="order-number">
                            <?= __('order_number') ?>: #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                        </div>
                        <div class="order-info">
                            <p><strong><?= __('order_date') ?>:</strong> <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></p>
                            <p><strong><?= __('total') ?>:</strong> <?= number_format($order['total_amount'], 0, ',', ' ') ?> GHS</p>
                            <p><strong><?= __('payment_method') ?>:</strong> <?= ucfirst(str_replace('_', ' ', $order['payment_method'])) ?></p>
                            <p><strong><?= __('status') ?>:</strong> <?= ucfirst($order['status']) ?></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="success-actions">
                    <a href="shop.php" class="btn btn-secondary">
                        <i class="fas fa-shopping-bag"></i> <?= __('continue_shopping') ?>
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="../student/orders.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> <?= __('view_order') ?>
                        </a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> <?= __('back_to_home') ?>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                    <p><?= __('footer_description') ?></p>
                    <p><?= __('footer_mission') ?></p>
                </div>
                
                <div class="footer-section">
                    <h3><?= __('footer_services') ?></h3>
                    <a href="courses.php"><?= __('courses') ?></a>
                    <a href="shop.php"><?= __('shop') ?></a>
                    <a href="upcoming_events.php"><?= __('events') ?></a>
                    <a href="contact.php"><?= __('contact') ?></a>
                </div>
                
                <div class="footer-section">
                    <h3><?= __('footer_contact') ?></h3>
                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                    <p><i class="fas fa-phone"></i> +233 XX XXX XXXX</p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
                </div>
                
                <div class="footer-section">
                    <h3><?= __('footer_follow_us') ?></h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. <?= __('footer_rights_reserved') ?>.</p>
            </div>
        </div>
    </footer>
</body>
</html>
