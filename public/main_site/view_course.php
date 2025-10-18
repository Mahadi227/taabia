<?php
require_once '../../includes/db.php';

$course_id = $_GET['id'] ?? null;

if (!$course_id) {
    header('Location: courses.php');
    exit;
}

try {
    // Get course details with instructor information
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS instructor_name 
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        WHERE c.id = ? AND c.status = 'published'
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: courses.php');
        exit;
    }

    // Get course lessons
    $stmt = $pdo->prepare("
        SELECT * FROM lessons 
        WHERE course_id = ? AND status = 'active'
        ORDER BY lesson_order ASC
    ");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();

} catch (PDOException $e) {
    header('Location: courses.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($course['title']) ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="main-styles.css">
    <style>
        .course-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--spacing-xl);
        }

        .course-header {
            text-align: center;
            margin-bottom: var(--spacing-2xl);
        }

        .course-header h1 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-sm);
        }

        .course-header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        .course-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }

        .course-details {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
        }

        .course-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-lg);
        }

        .course-info-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }

        .course-info-item i {
            color: var(--primary-color);
            width: 20px;
        }

        .course-price {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
            margin: var(--spacing-lg) 0;
        }

        .course-description {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: var(--spacing-xl);
        }

        .lessons-section {
            margin-bottom: var(--spacing-xl);
        }

        .lessons-title {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: 1.5rem;
        }

        .lesson-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
            padding: var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            margin-bottom: var(--spacing-sm);
            transition: var(--transition);
        }

        .lesson-item:hover {
            border-color: var(--primary-color);
            background: rgba(0, 150, 136, 0.05);
        }

        .lesson-icon {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .lesson-info {
            flex: 1;
        }

        .lesson-title {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
        }

        .lesson-meta {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .enrollment-section {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            padding: var(--spacing-xl);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .enrollment-title {
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
            font-size: 1.5rem;
        }

        .enrollment-features {
            margin-bottom: var(--spacing-xl);
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-md);
        }

        .feature-item i {
            color: var(--success-color);
        }

        .enrollment-price {
            text-align: center;
            margin-bottom: var(--spacing-xl);
        }

        .price-amount {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .price-currency {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .course-content {
                grid-template-columns: 1fr;
            }

            .course-info {
                grid-template-columns: 1fr;
            }
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

        .course-header {
            background: var(--bg-primary);
            padding: var(--spacing-2xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            margin-bottom: var(--spacing-xl);
        }

        .course-title {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
        }

        .course-meta {
            display: flex;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
            color: var(--text-secondary);
        }

        .course-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: var(--spacing-lg);
        }

        .course-description {
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: var(--spacing-lg);
        }

        .course-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: var(--spacing-xl);
        }

        .lessons-section {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
        }

        .lessons-title {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
        }

        .lesson-item {
            display: flex;
            align-items: center;
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .lesson-item:hover {
            background: var(--bg-secondary);
        }

        .lesson-item:last-child {
            border-bottom: none;
        }

        .lesson-icon {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-white);
            margin-right: var(--spacing-md);
        }

        .lesson-info {
            flex: 1;
        }

        .lesson-title {
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: var(--spacing-xs);
        }

        .lesson-meta {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .enrollment-section {
            background: var(--bg-primary);
            padding: var(--spacing-xl);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-light);
            height: fit-content;
        }

        .enrollment-title {
            font-size: 1.25rem;
            color: var(--text-primary);
            margin-bottom: var(--spacing-lg);
        }

        .enrollment-features {
            margin-bottom: var(--spacing-lg);
        }

        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }

        .feature-item i {
            color: var(--success-color);
            margin-right: var(--spacing-sm);
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
            
            .course-content {
                grid-template-columns: 1fr;
            }
            
            .course-meta {
                flex-direction: column;
                gap: var(--spacing-sm);
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
                <li><a href="index.php" class="nav-link">Accueil</a></li>
                <li><a href="courses.php" class="nav-link">Formations</a></li>
                <li><a href="shop.php" class="nav-link">Boutique</a></li>
                <li><a href="upcoming_events.php" class="nav-link">Événements</a></li>
                <li><a href="blog.php" class="nav-link">Blog</a></li>
                <li><a href="about.php" class="nav-link">À propos</a></li>
                <li><a href="contact.php" class="nav-link">Contact</a></li>
                <li><a href="basket.php" class="nav-link"><i class="fas fa-shopping-cart"></i></a></li>

            </ul>
            
            <div class="nav-actions">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="../student/index.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> Mon Compte
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                <?php else: ?>
                    <a href="../auth/login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a>
                    <a href="../auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Inscription
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    <!-- Main Content -->
    <main class="main">
        <div class="course-container">
            <div class="course-header">
                <h1><?= htmlspecialchars($course['title']) ?></h1>
                <p>Découvrez ce cours exceptionnel et commencez votre apprentissage</p>
            </div>
            
            <div class="course-content">
                <div class="course-details">
                    <div class="course-info">
                        <div class="course-info-item">
                            <i class="fas fa-user"></i>
                            <span>Instructeur: <?= htmlspecialchars($course['instructor_name']) ?></span>
                        </div>
                        <div class="course-info-item">
                            <i class="fas fa-play-circle"></i>
                            <span><?= count($lessons) ?> leçons</span>
                        </div>
                        <div class="course-info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Créé le <?= date('d/m/Y', strtotime($course['created_at'])) ?></span>
                        </div>
                    </div>
                    
                    <div class="course-price">
                        <?php if ((float)($course['price'] ?? 0) <= 0): ?>
                            Gratuit
                        <?php else: ?>
                            <?= number_format($course['price'], 0, ',', ' ') ?> GHS
                        <?php endif; ?>
                    </div>
                    
                    <p class="course-description">
                        <?= nl2br(htmlspecialchars($course['description'])) ?>
                    </p>
                    
                    <div class="lessons-section">
                        <h2 class="lessons-title">
                            <i class="fas fa-list"></i> Contenu du cours
                        </h2>
                        
                        <?php if (!empty($lessons)): ?>
                            <?php foreach ($lessons as $lesson): ?>
                                <div class="lesson-item">
                                    <div class="lesson-icon">
                                        <i class="fas fa-<?= $lesson['type'] == 'video' ? 'video' : ($lesson['type'] == 'pdf' ? 'file-pdf' : 'file-text') ?>"></i>
                                    </div>
                                    <div class="lesson-info">
                                        <div class="lesson-title"><?= htmlspecialchars($lesson['title']) ?></div>
                                        <div class="lesson-meta">
                                            Leçon #<?= $lesson['lesson_order'] ?> • <?= ucfirst($lesson['type']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: var(--text-secondary); text-align: center; padding: var(--spacing-xl);">
                                <i class="fas fa-info-circle"></i> Aucune leçon disponible pour le moment
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="enrollment-section">
                    <h3 class="enrollment-title">
                        <i class="fas fa-graduation-cap"></i> Inscription au cours
                    </h3>
                    
                    <div class="enrollment-price">
                        <?php if ((float)($course['price'] ?? 0) <= 0): ?>
                            <div class="price-amount">Gratuit</div>
                        <?php else: ?>
                            <div class="price-amount"><?= number_format($course['price'], 0, ',', ' ') ?></div>
                            <div class="price-currency">GHS</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="enrollment-features">
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Accès illimité au contenu</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Support des instructeurs</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Certificat de completion</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check"></i>
                            <span>Accès à vie</span>
                        </div>
                    </div>
                    
                    <div class="enrollment-actions">
                        <button onclick="addCourseToCart(<?= $course['id'] ?>)" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: var(--spacing-sm);">
                            <i class="fas fa-cart-plus"></i> Ajouter au panier
                        </button>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="../student/enroll.php?course_id=<?= $course['id'] ?>" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-graduation-cap"></i> S'inscrire directement
                            </a>
                        <?php else: ?>
                            <a href="../auth/login.php" class="btn btn-secondary" style="width: 100%; justify-content: center;">
                                <i class="fas fa-sign-in-alt"></i> Se connecter pour s'inscrire
                            </a>
                        <?php endif; ?>
                    </div>
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
                    <p>Votre plateforme d'apprentissage et d'innovation en Afrique</p>
                    <p>Démocratiser l'accès à l'éducation et aux produits innovants</p>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <a href="courses.php">Formations</a>
                    <a href="shop.php">Boutique</a>
                    <a href="upcoming_events.php">Événements</a>
                    <a href="contact.php">Support</a>
                </div>
                
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p><i class="fas fa-envelope"></i> contact@taabia.com</p>
                    <p><i class="fas fa-phone"></i> +233 XX XXX XXXX</p>
                    <p><i class="fas fa-map-marker-alt"></i> Accra, Ghana</p>
                </div>
                
                <div class="footer-section">
                    <h3>Suivez-nous</h3>
                    <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
                    <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
                    <a href="#"><i class="fab fa-linkedin"></i> LinkedIn</a>
                    <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> TaaBia. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <style>
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
            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <script>
        // Add course to cart functionality
        function addCourseToCart(courseId) {
            fetch('add_course_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'course_id=' + courseId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Cours ajouté au panier avec succès !', 'success');
                    updateCartCount(data.cart_count);
                } else {
                    showNotification(data.message || 'Erreur lors de l\'ajout au panier', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('Erreur de connexion', 'error');
            });
        }

        // Show notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div style="position: fixed; top: 20px; right: 20px; z-index: 1000; padding: 15px 20px; border-radius: 8px; color: white; font-weight: 500; box-shadow: 0 4px 12px rgba(0,0,0,0.15); max-width: 300px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                        <span>${message}</span>
                    </div>
                </div>
            `;
            
            // Style based on type
            const notificationDiv = notification.querySelector('div');
            if (type === 'success') {
                notificationDiv.style.background = '#4caf50';
            } else if (type === 'error') {
                notificationDiv.style.background = '#f44336';
            } else {
                notificationDiv.style.background = '#009688';
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Update cart count
        function updateCartCount(count) {
            const cartBadge = document.querySelector('.cart-count');
            if (cartBadge) {
                cartBadge.textContent = count;
                cartBadge.style.display = count > 0 ? 'block' : 'none';
            }
        }
    </script>
</body>
</html>
