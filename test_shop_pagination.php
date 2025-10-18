<?php
require_once 'includes/db.php';

echo "<h1>Test de la pagination de la boutique</h1>";

// Test 1: Vérifier le nombre total de produits
echo "<h2>1. Nombre total de produits</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'");
    $total_products = $stmt->fetchColumn();
    echo "✅ Total des produits actifs: $total_products<br>";
    
    $products_per_page = 20;
    $total_pages = ceil($total_products / $products_per_page);
    echo "✅ Nombre de pages nécessaires: $total_pages<br>";
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// Test 2: Tester la pagination
echo "<h2>2. Test de pagination</h2>";
for ($page = 1; $page <= min(3, $total_pages); $page++) {
    $offset = ($page - 1) * $products_per_page;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, u.fullname AS vendor_name 
            FROM products p 
            LEFT JOIN users u ON p.vendor_id = u.id 
            WHERE p.status = 'active'
            ORDER BY p.created_at DESC
            LIMIT $products_per_page OFFSET $offset
        ");
        $stmt->execute();
        $products = $stmt->fetchAll();
        
        echo "✅ Page $page: " . count($products) . " produits<br>";
        
        if (!empty($products)) {
            echo "   - Premier produit: " . $products[0]['name'] . "<br>";
            echo "   - Dernier produit: " . $products[count($products) - 1]['name'] . "<br>";
        }
    } catch (PDOException $e) {
        echo "❌ Erreur page $page: " . $e->getMessage() . "<br>";
    }
}

// Test 3: Tester avec des filtres
echo "<h2>3. Test de pagination avec filtres</h2>";
$search = "test";
$offset = 0;

try {
    // Compter le total avec filtre
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.status = 'active' AND (p.name LIKE ? OR p.description LIKE ?)
    ");
    $count_stmt->execute(["%$search%", "%$search%"]);
    $filtered_total = $count_stmt->fetchColumn();
    
    echo "✅ Produits avec recherche '$search': $filtered_total<br>";
    
    // Récupérer les produits avec filtre et pagination
    $stmt = $pdo->prepare("
        SELECT p.*, u.fullname AS vendor_name 
        FROM products p 
        LEFT JOIN users u ON p.vendor_id = u.id 
        WHERE p.status = 'active' AND (p.name LIKE ? OR p.description LIKE ?)
        ORDER BY p.created_at DESC
        LIMIT $products_per_page OFFSET $offset
    ");
    $stmt->execute(["%$search%", "%$search%"]);
    $filtered_products = $stmt->fetchAll();
    
    echo "✅ Page 1 avec filtre: " . count($filtered_products) . " produits<br>";
    
    if (!empty($filtered_products)) {
        echo "   - Premier produit filtré: " . $filtered_products[0]['name'] . "<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur avec filtres: " . $e->getMessage() . "<br>";
}

echo "<h2>4. Liens de test</h2>";
echo "<ul>";
echo "<li><a href='public/main_site/shop.php' target='_blank'>Tous les produits</a></li>";
echo "<li><a href='public/main_site/shop.php?page=2' target='_blank'>Page 2</a></li>";
echo "<li><a href='public/main_site/shop.php?search=test&page=1' target='_blank'>Recherche 'test'</a></li>";
echo "<li><a href='public/main_site/shop.php?category=Electronics&page=1' target='_blank'>Catégorie 'Electronics'</a></li>";
echo "</ul>";

echo "<h2>5. Instructions de test</h2>";
echo "<ol>";
echo "<li>Cliquez sur les liens ci-dessus pour tester la pagination</li>";
echo "<li>Vérifiez que seuls 20 produits s'affichent par page</li>";
echo "<li>Testez les boutons 'Précédent' et 'Suivant'</li>";
echo "<li>Vérifiez que les filtres sont conservés lors de la navigation</li>";
echo "<li>Testez la pagination avec des recherches et filtres</li>";
echo "</ol>";
?>

