<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once '../../includes/language_handler.php';

// Now load the session and other includes
require_once '../../includes/session.php';
require_once '../../includes/db.php';
require_once '../../includes/function.php';
require_once '../../includes/i18n.php';

// Initialize variables
$success = '';
$error = '';

// Process contact form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $subject = sanitize($_POST['subject'] ?? '');
    $message = sanitize($_POST['message'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($message)) {
        $error = __('all_fields_required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = __('invalid_email_format');
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = __('name_length_error');
    } elseif (strlen($message) < 10 || strlen($message) > 2000) {
        $error = __('message_length_error');
    } else {
        try {
            // Insert message into database
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $result = $stmt->execute([$name, $email, $subject, $message]);

            if ($result) {
                $success = __('message_sent_successfully');

                // Optional: Send email notification to admin (if SMTP is configured)
                // This is commented out to avoid the mail server error
                /*
                $admin_email = 'contact@taabia.com';
                $email_subject = "📩 " . __('new_contact_message') . " - " . $name;
                $email_body = __('message_from') . ": $name\n" . __('email') . ": $email\n\n" . __('message') . ":\n$message";
                $headers = "From: $email\r\nReply-To: $email\r\nContent-Type: text/plain; charset=UTF-8";
                
                if (mail($admin_email, $email_subject, $email_body, $headers)) {
                    // Email sent successfully
                }
                */
            } else {
                $error = __('error_saving_message');
            }
        } catch (PDOException $e) {
            error_log("Database error in send_contact.php: " . $e->getMessage());
            $error = __('database_error');
        }
    }
} else {
    // Direct access not allowed
    redirect('contact.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('contact') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">

    <style>
        .message-container {
            max-width: 600px;
            margin: 0 auto;
            padding: var(--spacing-xl);
            text-align: center;
        }

        .message-card {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-3xl);
            margin-bottom: var(--spacing-xl);
            border: 1px solid var(--border-color);
        }

        .message-icon {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
        }

        .success-icon {
            color: var(--success-color);
        }

        .error-icon {
            color: var(--danger-color);
        }

        .message-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
            font-weight: 600;
        }

        .message-text {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: var(--spacing-xl);
            line-height: 1.6;
        }

        .message-actions {
            display: flex;
            gap: var(--spacing-md);
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--gray-100);
            transform: translateY(-2px);
        }

        .contact-info {
            background: var(--light-color);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            margin-top: var(--spacing-xl);
            text-align: left;
        }

        .contact-info h3 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
            font-size: 1.25rem;
        }

        .contact-info p {
            color: var(--text-secondary);
            margin-bottom: var(--spacing-sm);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .contact-info i {
            color: var(--primary-color);
            width: 20px;
        }

        @media (max-width: 768px) {
            .message-actions {
                flex-direction: column;
            }

            .message-card {
                padding: var(--spacing-xl);
            }

            .message-title {
                font-size: 1.5rem;
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
                <li><a href="index.php" class="nav-link"><?= __('home') ?></a></li>
                <li><a href="courses.php" class="nav-link"><?= __('courses') ?></a></li>
                <li><a href="shop.php" class="nav-link"><?= __('shop') ?></a></li>
                <li><a href="upcoming_events.php" class="nav-link"><?= __('events') ?></a></li>
                <li><a href="blog.php" class="nav-link"><?= __('blog') ?></a></li>
                <li><a href="about.php" class="nav-link"><?= __('about') ?></a></li>
                <li><a href="contact.php" class="nav-link"><?= __('contact') ?></a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>
            </ul>

            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> <?= __('my_account') ?>
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> <?= __('logout') ?>
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> <?= __('login') ?>
                    </a>
                    <a href="../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> <?= __('register') ?>
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="message-container">
            <div class="message-card">
                <?php if (!empty($success)): ?>
                    <div class="message-icon success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="message-title"><?= __('message_sent') ?>!</h1>
                    <p class="message-text"><?= htmlspecialchars($success) ?></p>
                <?php elseif (!empty($error)): ?>
                    <div class="message-icon error-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h1 class="message-title"><?= __('error') ?></h1>
                    <p class="message-text"><?= htmlspecialchars($error) ?></p>
                <?php endif; ?>

                <div class="message-actions">
                    <a href="contact.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> <?= __('back_to_contact') ?>
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-home"></i> <?= __('home') ?>
                    </a>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="contact-info">
                <h3><i class="fas fa-info-circle"></i> <?= __('contact_information') ?></h3>
                <p><i class="fas fa-envelope"></i> <?= __('email') ?>: contact@taabia.com</p>
                <p><i class="fas fa-phone"></i> <?= __('phone') ?>: +233 XX XXX XXXX</p>
                <p><i class="fas fa-map-marker-alt"></i> <?= __('location') ?>: Accra, Ghana</p>
                <p><i class="fas fa-clock"></i> <?= __('response_time') ?>: <?= __('within_24_hours') ?></p>
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
                    <a href="contact.php"><?= __('support') ?></a>
                </div>

                <div class="footer-section">
                    <h3><?= __('contact') ?></h3>
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
                <p>&copy; <?= date('Y') ?> TaaBia. <?= __('footer_rights_reserved') ?></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('nav-menu');

        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close mobile menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });
    </script>
</body>

</html>