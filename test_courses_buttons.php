<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test des boutons des cours</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .course-card {
            border: 1px solid #ddd;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
        }
        .notification-success {
            background: #28a745;
        }
        .notification-error {
            background: #dc3545;
        }
    </style>
</head>
<body>
    <h1>Test des boutons des cours</h1>
    
    <div class="course-card">
        <h3>Cours de test</h3>
        <p>Ceci est un cours de test pour vérifier que les boutons fonctionnent.</p>
        <p>Prix: 100.00 GHS</p>
        
        <div class="course-actions">
            <a href="public/main_site/view_course.php?id=1" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Voir les détails
            </a>
            <button onclick="addCourseToCart(1)" class="btn btn-primary">
                <i class="fas fa-cart-plus"></i> Ajouter au panier
            </button>
        </div>
    </div>

    <div class="course-card">
        <h3>Autre cours de test</h3>
        <p>Un autre cours pour tester.</p>
        <p>Prix: 150.00 GHS</p>
        
        <div class="course-actions">
            <a href="public/main_site/view_course.php?id=7" class="btn btn-secondary">
                <i class="fas fa-eye"></i> Voir les détails
            </a>
            <button onclick="addCourseToCart(7)" class="btn btn-primary">
                <i class="fas fa-cart-plus"></i> Ajouter au panier
            </button>
        </div>
    </div>

    <script>
        // Add course to cart functionality
        function addCourseToCart(courseId) {
            console.log('Adding course to cart:', courseId);
            
            fetch('public/main_site/add_course_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'course_id=' + courseId
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showNotification('Cours ajouté au panier avec succès !', 'success');
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
            console.log('Showing notification:', message, type);
            
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div style="display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

        // Test function
        function testAddToCart() {
            addCourseToCart(1);
        }
    </script>

    <div style="margin-top: 30px;">
        <h3>Test manuel</h3>
        <button onclick="testAddToCart()" style="background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
            Tester l'ajout au panier (ID: 1)
        </button>
    </div>

    <div style="margin-top: 20px;">
        <h3>Instructions</h3>
        <ol>
            <li>Ouvrez la console du navigateur (F12)</li>
            <li>Cliquez sur "Ajouter au panier" pour voir les logs</li>
            <li>Vérifiez que les notifications apparaissent</li>
            <li>Testez le bouton "Voir les détails"</li>
        </ol>
    </div>
</body>
</html>

