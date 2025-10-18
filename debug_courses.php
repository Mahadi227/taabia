<?php
require_once 'includes/db.php';
session_start();

echo "<h1>Débogage des cours TaaBia</h1>";

// Test 1: Vérifier les sessions
echo "<h2>1. Test des sessions</h2>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "✅ Sessions PHP activées<br>";
    echo "Session ID: " . session_id() . "<br>";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Non connecté') . "<br>";
} else {
    echo "❌ Sessions PHP non activées<br>";
}

// Test 2: Vérifier la connexion à la base de données
echo "<h2>2. Test de connexion à la base de données</h2>";
try {
    $pdo->query("SELECT 1");
    echo "✅ Connexion à la base de données réussie<br>";
} catch (PDOException $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
}

// Test 3: Vérifier qu'il y a des cours disponibles
echo "<h2>3. Test des cours disponibles</h2>";
try {
    $stmt = $pdo->query("SELECT id, title, status FROM courses LIMIT 5");
    $courses = $stmt->fetchAll();
    echo "✅ " . count($courses) . " cours trouvés<br>";
    foreach ($courses as $course) {
        echo "- ID: {$course['id']}, Titre: {$course['title']}, Statut: {$course['status']}<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur lors de la récupération des cours: " . $e->getMessage() . "<br>";
}

// Test 4: Vérifier le panier
echo "<h2>4. Test du panier</h2>";
if (isset($_SESSION['cart'])) {
    echo "✅ Panier existant<br>";
    echo "Produits: " . count($_SESSION['cart']['products'] ?? []) . "<br>";
    echo "Cours: " . count($_SESSION['cart']['courses'] ?? []) . "<br>";
} else {
    echo "❌ Aucun panier trouvé<br>";
}

// Test 5: Tester l'ajout d'un cours au panier
echo "<h2>5. Test d'ajout au panier</h2>";
if (!empty($courses)) {
    $test_course = $courses[0];
    echo "Test avec le cours: {$test_course['title']} (ID: {$test_course['id']})<br>";
    
    // Simuler l'ajout au panier
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = ['products' => [], 'courses' => []];
    }
    
    // Vérifier si le cours est déjà dans le panier
    $course_in_cart = false;
    foreach ($_SESSION['cart']['courses'] as $cart_item) {
        if ($cart_item['id'] == $test_course['id']) {
            $course_in_cart = true;
            break;
        }
    }
    
    if (!$course_in_cart) {
        $_SESSION['cart']['courses'][] = [
            'id' => $test_course['id'],
            'title' => $test_course['title'],
            'price' => 100.00,
            'image_url' => '',
            'instructor_id' => 1
        ];
        echo "✅ Cours ajouté au panier avec succès<br>";
    } else {
        echo "⚠️ Cours déjà dans le panier<br>";
    }
    
    echo "Total dans le panier: " . (count($_SESSION['cart']['products']) + count($_SESSION['cart']['courses'])) . "<br>";
} else {
    echo "❌ Aucun cours disponible pour le test<br>";
}

// Test 6: Vérifier les fichiers
echo "<h2>6. Test des fichiers</h2>";
$files_to_check = [
    'public/main_site/add_course_to_cart.php',
    'public/main_site/courses_ajax.php',
    'public/main_site/view_course.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file n'existe pas<br>";
    }
}

echo "<h2>7. Test de la fonction addCourseToCart</h2>";
echo "<button onclick='testAddToCart()'>Tester l'ajout au panier</button>";
echo "<div id='test-result'></div>";

?>

<script>
function testAddToCart() {
    const resultDiv = document.getElementById('test-result');
    resultDiv.innerHTML = 'Test en cours...';
    
    fetch('public/main_site/add_course_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'course_id=1'
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.innerHTML = `
            <div style="background: ${data.success ? '#d4edda' : '#f8d7da'}; 
                        border: 1px solid ${data.success ? '#c3e6cb' : '#f5c6cb'}; 
                        padding: 10px; margin: 10px 0; border-radius: 5px;">
                <strong>Résultat:</strong> ${data.success ? 'Succès' : 'Erreur'}<br>
                <strong>Message:</strong> ${data.message}<br>
                <strong>Cart Count:</strong> ${data.cart_count || 'N/A'}
            </div>
        `;
    })
    .catch(error => {
        resultDiv.innerHTML = `
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; 
                        padding: 10px; margin: 10px 0; border-radius: 5px;">
                <strong>Erreur:</strong> ${error.message}
            </div>
        `;
    });
}
</script>

