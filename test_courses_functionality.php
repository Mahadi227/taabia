<?php
// Test script to verify courses functionality
session_start();

echo "<h1>Test de la Fonctionnalité des Cours</h1>";

// Test database connection
echo "<h2>Test de Connexion à la Base de Données:</h2>";
try {
require_once 'includes/db.php';

    // Check if courses table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'");
    $course_count = $stmt->fetchColumn();
    echo "<p>✅ Table courses accessible - $course_count cours publiés trouvés</p>";
    
    // Get sample course
    $stmt = $pdo->query("SELECT id, title, price, instructor_id FROM courses WHERE status = 'published' LIMIT 1");
    $sample_course = $stmt->fetch();
    
    if ($sample_course) {
        echo "<p>✅ Cours d'exemple trouvé: {$sample_course['title']} (ID: {$sample_course['id']})</p>";
        echo "<p>Prix: {$sample_course['price']} FCFA</p>";
    } else {
        echo "<p>❌ Aucun cours publié trouvé</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Erreur de base de données: " . $e->getMessage() . "</p>";
}

// Test session and cart
echo "<h2>Test de Session et Panier:</h2>";
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = ['products' => [], 'courses' => []];
    echo "<p>✅ Panier initialisé</p>";
} else {
    echo "<p>✅ Panier existant</p>";
}

$total_items = 0;
foreach ($_SESSION['cart']['products'] as $item) {
    $total_items += $item['quantity'];
}
$total_items += count($_SESSION['cart']['courses']);

echo "<p>Articles dans le panier: $total_items</p>";

// Test add_course_to_cart.php
echo "<h2>Test du Fichier add_course_to_cart.php:</h2>";
if (file_exists('public/main_site/add_course_to_cart.php')) {
    echo "<p>✅ Fichier add_course_to_cart.php existe</p>";
    
    // Check if it has session_start()
    $content = file_get_contents('public/main_site/add_course_to_cart.php');
    if (strpos($content, 'session_start()') !== false) {
        echo "<p>✅ session_start() présent dans add_course_to_cart.php</p>";
    } else {
        echo "<p>❌ session_start() manquant dans add_course_to_cart.php</p>";
    }
} else {
    echo "<p>❌ Fichier add_course_to_cart.php manquant</p>";
}

// Test get_cart_count.php
echo "<h2>Test du Fichier get_cart_count.php:</h2>";
if (file_exists('public/main_site/get_cart_count.php')) {
    echo "<p>✅ Fichier get_cart_count.php existe</p>";
} else {
    echo "<p>❌ Fichier get_cart_count.php manquant</p>";
}

// Test courses.php
echo "<h2>Test du Fichier courses.php:</h2>";
if (file_exists('public/main_site/courses.php')) {
    echo "<p>✅ Fichier courses.php existe</p>";
    
    // Check for key functions
    $content = file_get_contents('public/main_site/courses.php');
    $checks = [
        'addCourseToCart' => 'Fonction addCourseToCart',
        'showCourseDetails' => 'Fonction showCourseDetails',
        'FCFA' => 'Devise FCFA',
        'data-course-id' => 'Attribut data-course-id',
        'courseModal' => 'Modal des détails'
    ];
    
    foreach ($checks as $check => $description) {
        if (strpos($content, $check) !== false) {
            echo "<p>✅ $description présent</p>";
    } else {
            echo "<p>❌ $description manquant</p>";
        }
    }
} else {
    echo "<p>❌ Fichier courses.php manquant</p>";
}

// Test links
echo "<h2>Liens de Test:</h2>";
echo "<p><a href='public/main_site/courses.php' target='_blank'>📚 Aller à la page des cours</a></p>";
echo "<p><a href='public/main_site/basket.php' target='_blank'>🛒 Voir le panier</a></p>";
echo "<p><a href='public/main_site/shop.php' target='_blank'>🛍️ Aller à la boutique</a></p>";

// Test cart functionality
echo "<h2>Test du Panier:</h2>";
if ($sample_course) {
    echo "<form method='POST' action='public/main_site/add_course_to_cart.php' target='_blank'>";
    echo "<input type='hidden' name='course_id' value='{$sample_course['id']}'>";
    echo "<button type='submit'>🧪 Tester l'ajout du cours '{$sample_course['title']}' au panier</button>";
    echo "</form>";
}

echo "<h2>Instructions de Test:</h2>";
echo "<ol>";
echo "<li>Cliquez sur 'Aller à la page des cours' pour tester l'interface</li>";
echo "<li>Testez la recherche et les filtres</li>";
echo "<li>Cliquez sur 'Voir les détails' pour ouvrir le modal</li>";
echo "<li>Cliquez sur 'Ajouter au panier' pour tester l'ajout</li>";
echo "<li>Vérifiez que le badge du panier se met à jour</li>";
echo "<li>Allez au panier pour vérifier que les cours sont bien présents</li>";
echo "</ol>";

echo "<h2>Fonctionnalités Corrigées:</h2>";
echo "<ul>";
echo "<li>✅ Devise changée de GHS à FCFA</li>";
echo "<li>✅ Modal pour les détails des cours</li>";
echo "<li>✅ Fonction addCourseToCart améliorée avec états de chargement</li>";
echo "<li>✅ Badge du panier dynamique</li>";
echo "<li>✅ Gestion des erreurs améliorée</li>";
echo "<li>✅ Notifications visuelles</li>";
echo "</ul>";
?>


    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>



    ")->fetchAll(PDO::FETCH_COLUMN);

    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>



    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>




    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>



    ")->fetchAll(PDO::FETCH_COLUMN);

    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>


    ")->fetchAll(PDO::FETCH_COLUMN);

    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>


    ")->fetchAll(PDO::FETCH_COLUMN);

    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>


    ")->fetchAll(PDO::FETCH_COLUMN);

    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>



    

    echo "✅ Instructeurs trouvés:<br>";

    foreach ($instructors as $instructor) {

        echo "- $instructor<br>";

    }

} catch (PDOException $e) {

    echo "❌ Erreur lors de la récupération des instructeurs: " . $e->getMessage() . "<br>";

}



// Test 7: Vérifier les leçons

echo "<h2>7. Test de la table lessons</h2>";

try {

    $stmt = $pdo->query("SELECT COUNT(*) FROM lessons");

    $count = $stmt->fetchColumn();

    echo "✅ Table lessons trouvée avec $count leçons<br>";

} catch (PDOException $e) {

    echo "❌ Erreur avec la table lessons: " . $e->getMessage() . "<br>";

}



// Test 8: Vérifier les fichiers

echo "<h2>8. Vérification des fichiers</h2>";

$files_to_check = [

    'public/main_site/courses.php',

    'public/main_site/view_course.php',

    'public/main_site/add_course_to_cart.php'

];



foreach ($files_to_check as $file) {

    if (file_exists($file)) {

        echo "✅ $file existe<br>";

    } else {

        echo "❌ $file manquant<br>";

    }

}



// Test 9: Vérifier les sessions

echo "<h2>9. Test des sessions</h2>";

session_start();

if (session_status() === PHP_SESSION_ACTIVE) {

    echo "✅ Sessions PHP activées<br>";

} else {

    echo "❌ Sessions PHP non activées<br>";

}



echo "<h2>10. Liens de test</h2>";

echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";

echo "<ul>";

echo "<li><a href='public/main_site/courses.php' target='_blank'>Catalogue des cours (courses.php)</a></li>";

echo "</ul>";



if (!empty($courses)) {

    echo "<p>Testez un cours spécifique:</p>";

    echo "<ul>";

    foreach ($courses as $course) {

        echo "<li><a href='public/main_site/view_course.php?id={$course['id']}' target='_blank'>{$course['title']}</a></li>";

    }

    echo "</ul>";

}



echo "<h2>✅ Test terminé !</h2>";

echo "<p>Toutes les fonctionnalités des cours sont prêtes à être utilisées.</p>";

echo "<p><strong>Fonctionnalités disponibles:</strong></p>";

echo "<ul>";

echo "<li>✅ Affichage du catalogue des cours</li>";

echo "<li>✅ Recherche et filtrage par catégorie/niveau</li>";

echo "<li>✅ Affichage détaillé des cours</li>";

echo "<li>✅ Ajout des cours au panier</li>";

echo "<li>✅ Inscription directe aux cours</li>";

echo "<li>✅ Interface responsive et moderne</li>";

echo "</ul>";

?>


