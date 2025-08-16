<?php
require_once '../../includes/i18n.php';
require_once '../../includes/db.php';

// Get dynamic statistics
try {
    $stats = [
        'courses' => $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'")->fetchColumn(),
        'students' => $pdo->query("SELECT COUNT(*) FROM student_courses")->fetchColumn(),
        'instructors' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND status = 'active'")->fetchColumn(),
        'events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'active'")->fetchColumn()
    ];
} catch (PDOException $e) {
    $stats = ['courses' => 0, 'students' => 0, 'instructors' => 0, 'events' => 0];
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('about') ?> | TaaBia</title>
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

        .banner {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--text-white);
            padding: var(--spacing-2xl) 0;
            text-align: center;
        }

        .banner h1 {
            font-size: 3rem;
            margin-bottom: var(--spacing-lg);
        }

        .banner p {
            font-size: 1.25rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .content {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .content h2 {
            color: var(--primary-color);
            margin-bottom: var(--spacing-lg);
            font-size: 1.5rem;
        }

        .content p {
            margin-bottom: var(--spacing-lg);
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-2xl);
        }

        .feature {
            text-align: center;
            padding: var(--spacing-lg);
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .feature:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .feature i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
        }

        .feature h3 {
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }

        .feature p {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-2xl);
        }

        .stat {
            text-align: center;
            padding: var(--spacing-lg);
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: var(--spacing-sm);
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .team-section {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-xl);
        }

        .team-member {
            text-align: center;
            padding: var(--spacing-lg);
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .team-member:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .team-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            color: var(--text-white);
            font-size: 2rem;
        }

        .team-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .team-role {
            color: var(--primary-color);
            font-size: 0.875rem;
            margin-bottom: var(--spacing-sm);
        }

        .team-bio {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .founder-section {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .founder-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: var(--spacing-xl);
            align-items: center;
        }

        .founder-image {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            font-size: 4rem;
            margin: 0 auto;
        }

        .testimonials-section {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-2xl);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            margin-top: var(--spacing-xl);
        }

        .testimonial {
            background: var(--bg-secondary);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            position: relative;
        }

        .testimonial::before {
            content: '"';
            font-size: 3rem;
            color: var(--primary-color);
            position: absolute;
            top: -10px;
            left: 20px;
        }

        .testimonial-text {
            color: var(--text-secondary);
            font-style: italic;
            margin-bottom: var(--spacing-md);
            padding-top: var(--spacing-md);
        }

        .testimonial-author {
            font-weight: 600;
            color: var(--text-primary);
        }

        .testimonial-role {
            color: var(--text-secondary);
            font-size: 0.875rem;
        }

        .footer {
            background: var(--text-primary);
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
            color: var(--primary-light);
        }

        .footer-section p, .footer-section a {
            color: var(--text-secondary);
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
            color: var(--text-secondary);
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
            
            .features, .stats, .team-grid, .testimonials-grid {
                grid-template-columns: 1fr;
            }
            
            .founder-content {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .banner h1 {
                font-size: 2rem;
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
    <!-- Banner -->
    <section class="banner">
        <div class="container">
                         <h1><?= __('about') ?> <?= __('about_taabia') ?></h1>
             <p><?= __('about_description') ?></p>
        </div>
    </section>

    <!-- Main Content -->
    <section class="section">
        <div class="container">
            <div class="content">
                                 <h2><i class="fas fa-bullseye"></i> <?= __('our_mission') ?></h2>
                 <p>
                     <?= __('mission_description') ?>
                 </p>
                 
                 <h2><i class="fas fa-eye"></i> <?= __('our_vision') ?></h2>
                 <p>
                     <?= __('vision_description') ?>
                 </p>
                 
                 <h2><i class="fas fa-heart"></i> <?= __('our_values') ?></h2>
                 <p>
                     <strong><?= __('excellence') ?>:</strong> <?= __('excellence_description') ?><br>
                     <strong><?= __('innovation') ?>:</strong> <?= __('innovation_description') ?><br>
                     <strong><?= __('community') ?>:</strong> <?= __('community_description') ?><br>
                     <strong><?= __('accessibility') ?>:</strong> <?= __('accessibility_description') ?>
                 </p>
            </div>
            
            <div class="features">
                <div class="feature">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Features quality </h3>
                    <p>Features quality</p>
                </div>
                
                <div class="feature">
                    <i class="fas fa-shopping-bag"></i>
                    <h3>Features innovative products</h3>
                    <p>Features_innovativeproducts</p>
                </div>
                
                <div class="feature">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Features enriching events</h3>
                    <p>Features enriching events</p>
                </div>
                
                <div class="feature">
                    <i class="fas fa-users"></i>
                    <h3>Features active community </h3>
                    <p>Features active community</p>
                </div>
            </div>
            
            <div class="stats">
                <div class="stat">
                    <div class="stat-number"><?= $stats['courses'] ?>+</div>
                    <div class="stat-label"><?= $stats_courses_label ?></div>
                </div>
                
                <div class="stat">
                    <div class="stat-number"><?= $stats['students'] ?>+</div>
                    <div class="stat-label"><?= $stats_students_label ?></div>
                </div>
                
                <div class="stat">
                    <div class="stat-number"><?= $stats['instructors'] ?>+</div>
                    <div class="stat-label"><?= $stats_instructors_label ?></div>
                </div>
                
                <div class="stat">
                    <div class="stat-number"><?= $stats['events'] ?>+</div>
                    <div class="stat-label"><?= $stats_events_label ?></div>
                </div>
            </div>
        </div>
    </section>


    

  <?php
  // Récupération des chiffres clés
  require_once '../../includes/db.php';

  $total_students   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1")->fetchColumn();
  $total_teachers   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'instructor' AND is_active = 1")->fetchColumn();
  $total_courses    = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published' AND is_active = 1")->fetchColumn();
  $total_products   = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active' AND is_active = 1")->fetchColumn();
  ?>
  <section class="section stats">
    <h2>🚀 Nos chiffres clés</h2>
    <div class="stats-grid">
      <div class="stat">
        <h3><?= number_format($total_students) ?></h3>
        <p>Étudiants inscrits</p>
      </div>
      <div class="stat">
        <h3><?= number_format($total_teachers) ?></h3>
        <p>Formateurs actifs</p>
      </div>
      <div class="stat">
        <h3><?= number_format($total_courses) ?></h3>
        <p>Cours en ligne</p>
      </div>
      <div class="stat">
        <h3><?= number_format($total_products) ?></h3>
        <p>Produits vendus</p>
      </div>
    </div>
  </section>

  <style>
    .stats {
      background: #ffffff;
      text-align: center;
      padding: 3rem 2rem;
    }

    .stats h2 {
      font-size: 2rem;
      margin-bottom: 2rem;
      color: #004d40;
    }

    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 2rem;
      max-width: 900px;
      margin: 0 auto;
    }

    .stat {
      background: #f1f1f1;
      padding: 2rem;
      border-radius: 10px;
      box-shadow: 0 0 8px rgba(0, 0, 0, 0.05);
      transition: transform 0.3s ease;
    }

    .stat:hover {
      transform: translateY(-5px);
    }

    .stat h3 {
      font-size: 2.2rem;
      color: #009688;
      margin-bottom: 0.5rem;
    }

    .stat p {
      font-size: 1rem;
      margin: 0;
      color: #444;
    }
  </style>


    <!-- Team Section -->
    <section class="section">
        <div class="container">
            <div class="team-section">
                <div class="section-title">
                    <h1><i class="fas fa-users"></i> Our Team</h1>
                    <p> Team description </p>
                </div>
                
                <div class="team-grid">
                    <div class="team-member">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Sarah Johnson</div>
                        <div class="team-role">Directrice Générale</div>
                        <div class="team-bio">Experte en éducation avec 10+ ans d'expérience dans l'innovation pédagogique</div>
                    </div>
                    
                    <div class="team-member">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Kwame Mensah</div>
                        <div class="team-role">Directeur Technique</div>
                        <div class="team-bio">Spécialiste en développement web et solutions technologiques innovantes</div>
                    </div>
                    
                    <div class="team-member">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Aisha Diallo</div>
                        <div class="team-role">Responsable Pédagogique</div>
                        <div class="team-bio">Experte en conception de programmes éducatifs et formation des instructeurs</div>
                    </div>
                    
                    <div class="team-member">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">David Osei</div>
                        <div class="team-role">Responsable Marketing</div>
                        <div class="team-bio">Spécialiste en stratégies marketing digital et développement de la communauté</div>
                    </div>

                    
                    <div class="team-member">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Kwame Mensah</div>
                        <div class="team-role">Directeur Technique</div>
                        <div class="team-bio">Spécialiste en développement web et solutions technologiques innovantes</div>
                    </div>
                    
                    <div class="team-member">
                        <div class="team-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="team-name">Aisha Diallo</div>
                        <div class="team-role">Responsable Pédagogique</div>
                        <div class="team-bio">Experte en conception de programmes éducatifs et formation des instructeurs</div>
                    </div>
                    
                </div>
            </div>
        </div>
    </section>

    <!-- Founder Section -->
    <section class="section">
        <div class="container">
            <div class="founder-section">
                <div class="section-title">
                    <h1><i class="fas fa-crown"></i> Founder</h1>
                    <p>Founder description</p>
                </div>
                
                <div class="founder-content">
                    <div class="founder-image">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    
                    <div>
                        <h2>Dr. Faycal Soumana Adamou</h2>
                        <p style="color: var(--primary-color); font-weight: 600; margin-bottom: var(--spacing-md);">
                            Fondateur & CEO de TaaBia
                        </p>
                        <p style="margin-bottom: var(--spacing-lg);">
                            Dr. Faycal Soumana Adamou est un entrepreneur visionnaire et expert en éducation avec plus de 15 ans d'expérience 
                            dans le développement de solutions éducatives innovantes. Diplômé de l'Université du Ghana et de Harvard Business School, 
                            il a consacré sa carrière à démocratiser l'accès à l'éducation de qualité en Afrique.
                        </p>
                        <p>
                            Founder quote 
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section">
        <div class="container">
            <div class="testimonials-section">
                <div class="section-title">
                    <h1><i class="fas fa-quote-left"></i> Testimonials title </h1>
                    <p>Testimonials description </p>
                </div>
                
                <div class="testimonials-grid">
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "Testimonial1 text "
                        </div>
                        <div class="testimonial-author">Fatou Diop</div>
                        <div class="testimonial-role">Développeuse Web, Dakar</div>
                    </div>
                    
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "Testimonial2_textc
                        </div>
                        <div class="testimonial-author">Dr. Kwesi Owusu</div>
                        <div class="testimonial-role">Instructeur en Marketing Digital</div>
                    </div>
                    
                    <div class="testimonial">
                        <div class="testimonial-text">
                            "Testimonial3_text  
                        </div>
                        <div class="testimonial-author">Aminata Traoré</div>
                        <div class="testimonial-role">Entrepreneure, Bamako</div>
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
                    <p>footer</p>
                    <p>footer mission description </p>
                </div>
                
                <div class="footer-section">
                    <h3>services  </h3>
                    <a href="courses.php"> Our courses  </a>
                    <a href="shop.php"> OUR shop  </a>
                    <a href="upcoming_events.php">Upcoming events  </a>
                    <a href="contact.php"> support  </a>
                </div>
                
                <div class="footer-section">
                    <h3>Contact us </h3>
                    <p><i class="fas fa-envelope"></i> footer contact email </p>
                    <p><i class="fas fa-phone"></i> +23353489333</p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
                </div>
                
                <div class="footer-section">
                    <h3>follow us</h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. Foote rights reserved .</p>
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
