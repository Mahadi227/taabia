<?php
require_once 'includes/db.php';

echo "<h1>Test de la pagination des cours</h1>";

// Test 1: Vérifier le nombre total de cours
echo "<h2>1. Nombre total de cours</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM courses WHERE status = 'published'");
    $total_courses = $stmt->fetchColumn();
    echo "✅ Total des cours publiés: $total_courses<br>";
    
    $courses_per_page = 20;
    $total_pages = ceil($total_courses / $courses_per_page);
    echo "✅ Nombre de pages nécessaires: $total_pages<br>";
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// Test 2: Tester la pagination
echo "<h2>2. Test de pagination</h2>";
for ($page = 1; $page <= min(3, $total_pages); $page++) {
    $offset = ($page - 1) * $courses_per_page;
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS instructor_name 
            FROM courses c 
            LEFT JOIN users u ON c.instructor_id = u.id 
            WHERE c.status = 'published'
            ORDER BY c.created_at DESC
            LIMIT $courses_per_page OFFSET $offset
        ");
        $stmt->execute();
        $courses = $stmt->fetchAll();
        
        echo "✅ Page $page: " . count($courses) . " cours<br>";
    } catch (PDOException $e) {
        echo "❌ Erreur page $page: " . $e->getMessage() . "<br>";
    }
}

echo "<h2>3. Liens de test</h2>";
echo "<ul>";
echo "<li><a href='public/main_site/courses.php' target='_blank'>Tous les cours</a></li>";
echo "<li><a href='public/main_site/courses.php?page=2' target='_blank'>Page 2</a></li>";
echo "<li><a href='public/main_site/courses.php?search=French&page=1' target='_blank'>Recherche 'French'</a></li>";
echo "</ul>";
?>
