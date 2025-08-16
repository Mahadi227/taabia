<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('contact') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #009688;
            --primary-light: #4db6ac;
            --primary-dark: #00695c;
            --secondary-color: #00bcd4;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --text-primary: #212121;
            --text-secondary: #757575;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #fafafa;
            --border-color: #e0e0e0;
            --border-radius: 12px;
            --border-radius-sm: 6px;
            --shadow-light: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 8px rgba(0,0,0,0.12);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-family);
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .header {
            background: var(--bg-primary);
            box-shadow: var(--shadow-light);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-md) var(--spacing-xl);
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: var(--spacing-xl);
            align-items: center;
        }

        .nav-link {
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .nav-link:hover {
            color: var(--primary-color);
        }

        .nav-actions {
            display: flex;
            gap: var(--spacing-md);
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--spacing-xl);
        }

        .section {
            padding: var(--spacing-2xl) 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .section-title h1 {
            font-size: 2.25rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .section-title p {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-2xl);
            margin-top: var(--spacing-xl);
        }

        .contact-form {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-label {
            display: block;
            margin-bottom: var(--spacing-sm);
            font-weight: 500;
            color: var(--text-primary);
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .contact-info {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--bg-secondary);
            border-radius: var(--border-radius-sm);
        }

        .contact-item i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-right: var(--spacing-md);
            width: 40px;
            text-align: center;
        }

        .contact-item-content h3 {
            margin-bottom: var(--spacing-xs);
            color: var(--text-primary);
        }

        .contact-item-content p {
            color: var(--text-secondary);
            margin: 0;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: var(--spacing-md);
                padding: var(--spacing-md);
            }
            
            .nav-menu {
                flex-direction: column;
                gap: var(--spacing-md);
            }
            
            .nav-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .contact-content {
                grid-template-columns: 1fr;
            }
        }

    
        .footer {
            background: #00796b;
            color: var(--text-white);
            padding: var(--spacing-2xl) 0;
            margin-top: var(--spacing-2xl);
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-xl);
        }

        .footer-section h3 {
            margin-bottom: var(--spacing-md);
            color: white;
        }

        .footer-section p, .footer-section a {
            color: while;
            text-decoration: none;
            margin-bottom: var(--spacing-sm);
            display: block;
        }

        .footer-section a:hover {
            color: var(--primary-light);
        }

        .footer-bottom {
            border-top: 1px solid var(--text-secondary);
            padding-top: var(--spacing-lg);
            margin-top: var(--spacing-xl);
            text-align: center;
            color: while;
        }

        @media (max-width: 768px) {
            .footer-content {
                grid-template-columns: 1fr;
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
            
            <ul class="nav-menu">
                                 <li><a href="index.php" class="nav-link"><?= __('welcome') ?></a></li>
                 <li><a href="courses.php" class="nav-link"><?= __('courses') ?></a></li>
                 <li><a href="shop.php" class="nav-link"><?= __('shop') ?></a></li>
                 <li><a href="upcoming_events.php" class="nav-link"><?= __('events') ?></a></li>
                 <li><a href="about.php" class="nav-link"><?= __('about') ?></a></li>
                 <li><a href="contact.php" class="nav-link"><?= __('contact') ?></a></li>
                 
                 <li style="margin-left: auto;">
                     <?php include '../../includes/public_language_switcher.php'; ?>
                 </li>
            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                                         <a href="../student/index.php" class="btn btn-secondary">
                         <i class="fas fa-user"></i> <?= __('my_profile') ?>
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
    <section class="section">
        <div class="container">
            <div class="section-title">
                                 <h1><i class="fas fa-envelope"></i> <?= __('contact_us') ?></h1>
                 <p><?= __('contact_us_description') ?></p>
            </div>
            
            <div class="contact-content">
                <div class="contact-form">
                                         <h2><i class="fas fa-paper-plane"></i> <?= __('send_message') ?></h2>
                    <form action="send_contact.php" method="POST">
                        <div class="form-group">
                                                         <label for="name" class="form-label"><?= __('full_name') ?> *</label>
                             <input type="text" id="name" name="name" class="form-control" required>
                         </div>
                         
                         <div class="form-group">
                             <label for="email" class="form-label"><?= __('email') ?> *</label>
                             <input type="email" id="email" name="email" class="form-control" required>
                         </div>
                         
                         <div class="form-group">
                             <label for="subject" class="form-label"><?= __('subject') ?> *</label>
                             <input type="text" id="subject" name="subject" class="form-control" required>
                         </div>
                         
                         <div class="form-group">
                             <label for="message" class="form-label"><?= __('message') ?> *</label>
                             <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                         </div>
                         
                         <button type="submit" class="btn btn-primary">
                             <i class="fas fa-send"></i> <?= __('send_message') ?>
                         </button>
                    </form>
                </div>
                
                <div class="contact-info">
                                         <h2><i class="fas fa-info-circle"></i> <?= __('contact_info') ?></h2>
                    
                    <div class="contact-item">
                        <i class="fas fa-envelope"></i>
                        <div class="contact-item-content">
                                                         <h3><?= __('email') ?></h3>
                             <p>contact@taabia.com</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="fas fa-phone"></i>
                        <div class="contact-item-content">
                                                         <h3><?= __('phone') ?></h3>
                             <p>+23353489333 </p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="contact-item-content">
                                                         <h3><?= __('address') ?></h3>
                             <p>Accra, Ghana</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <i class="fas fa-clock"></i>
                        <div class="contact-item-content">
                                                         <h3><?= __('opening_hours') ?></h3>
                             <p><?= __('opening_hours_content') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="section">
        <div class="container">
            <div class="section-title">
                <h1><i class="fas fa-map-marker-alt"></i> Our location </h1>
                <p>Our location description </p>
            </div>
            
            <div style="background: var(--bg-primary); padding: var(--spacing-xl); border-radius: var(--border-radius); box-shadow: var(--shadow-light);">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-xl);">
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: var(--spacing-lg);">
                            <i class="fas fa-info-circle"></i> location info
                        </h3>
                        
                        <div style="margin-bottom: var(--spacing-lg);">
                            <h4 style="color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                                address location 
                            </h4>
                            <p style="color: var(--text-secondary);">
                                <i class="fas fa-map-marker-alt" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                              Accra, Ghana
                            </p>
                        </div>
                        
                        <div style="margin-bottom: var(--spacing-lg);">
                            <h4 style="color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                                Opening hours info 
                            </h4>
                            <p style="color: var(--text-secondary);">
                                <i class="fas fa-clock" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                                Mon - Fri: 9:00 AM - 6:00 PM
                                Sat: 9:00 AM - 2:00 PM
                            </p>
                        </div>
                        
                        <div style="margin-bottom: var(--spacing-lg);">
                            <h4 style="color: var(--text-primary); margin-bottom: var(--spacing-sm);">
                        Transport location info  
                            </h4>
                            <p style="color: var(--text-secondary);">
                                <i class="fas fa-bus" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                            Transport location info content 
                                <i class="fas fa-car" style="color: var(--primary-color); margin-right: var(--spacing-sm);"></i>
                               Parking location info content 
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <div style="
                            width: 100%; 
                            height: 300px; 
                            background: var(--bg-secondary); 
                            border-radius: var(--border-radius-sm);
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: var(--text-secondary);
                        ">
                            <div style="text-align: center;">
                                <i class="fas fa-map" style="font-size: 3rem; margin-bottom: var(--spacing-md); opacity: 0.5;"></i>
                                <p> Map interactive description </p>
                                <p style="font-size: 0.875rem;">
                                   Map integration description  
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-graduation-cap"></i> TaaBia</h3>
                    <p>Votre plateforme de formation et e-commerce de confiance.  </p>
                    <p>Découvrez des formations de qualité, des produits innovants et des événements enrichissants.</p>
                </div>
                
                <div class="footer-section">
                    <h3>Services </h3>
                    <a href="courses.php">courses link</a>
                    <a href="shop.php">Shop link </a>
                    <a href="upcoming_events.php"> Events link </a>
                    <a href="contact.php">Support link</a>
                </div>
                
                <div class="footer-section">
                    <h3>Contact us </h3>
                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                    <p><i class="fas fa-phone"></i> +23353489333 </p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra,Ghana</p>
                </div>
                
                <div class="footer-section">
                    <h3>Follow us </h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. All_rights_reserved_text .</p>
            </div>
        </div>
    </footer>

    
    <script>
        // Hamburger Menu Functionality
        const hamburger = document.getElementById('hamburger');
        const navMenu = document.getElementById('nav-menu');

        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navMenu.classList.toggle('active');
        });

        // Close menu when clicking on a link
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', () => {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            });
        });

        // Close menu when clicking outside
        document.addEventListener('click', (e) => {
            if (!hamburger.contains(e.target) && !navMenu.contains(e.target)) {
                hamburger.classList.remove('active');
                navMenu.classList.remove('active');
            }
        });
    </script>
</body>
</html>
