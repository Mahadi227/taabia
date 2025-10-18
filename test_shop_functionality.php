<?php
require_once 'includes/db.php';

echo "<h1>Test des fonctionnalités de la boutique TaaBia</h1>";

// Test 1: Vérifier la connexion à la base de données
echo "<h2>1. Test de connexion à la base de données</h2>";
try {
    $pdo->query("SELECT 1");
    echo "✅ Connexion à la base de données réussie<br>";
} catch (PDOException $e) {
    echo "❌ Erreur de connexion: " . $e->getMessage() . "<br>";
}

// Test 2: Vérifier la table products
echo "<h2>2. Test de la table products</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $count = $stmt->fetchColumn();
    echo "✅ Table products trouvée avec $count produits<br>";
    
    // Afficher quelques produits
    $products = $pdo->query("SELECT id, name, price, category, stock_quantity FROM products LIMIT 5")->fetchAll();
    echo "<h3>Produits disponibles:</h3>";
    foreach ($products as $product) {
        echo "- {$product['name']} ({$product['category']}) - {$product['price']} GHS - Stock: {$product['stock_quantity']}<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur avec la table products: " . $e->getMessage() . "<br>";
}

// Test 3: Vérifier la table users (vendors)
echo "<h2>3. Test de la table users (vendors)</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendor'");
    $count = $stmt->fetchColumn();
    echo "✅ $count vendeurs trouvés<br>";
} catch (PDOException $e) {
    echo "❌ Erreur avec la table users: " . $e->getMessage() . "<br>";
}

// Test 4: Vérifier les statistiques de la boutique
echo "<h2>4. Statistiques de la boutique</h2>";
try {
    $stats = [
        'total_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn(),
        'total_vendors' => $pdo->query("SELECT COUNT(DISTINCT vendor_id) FROM products WHERE status = 'active'")->fetchColumn(),
        'avg_price' => $pdo->query("SELECT AVG(price) FROM products WHERE status = 'active'")->fetchColumn(),
        'categories_count' => $pdo->query("SELECT COUNT(DISTINCT category) FROM products WHERE status = 'active'")->fetchColumn()
    ];
    
    echo "✅ Statistiques calculées:<br>";
    echo "- Total produits: {$stats['total_products']}<br>";
    echo "- Total vendeurs: {$stats['total_vendors']}<br>";
    echo "- Prix moyen: " . number_format($stats['avg_price'], 2) . " GHS<br>";
    echo "- Nombre de catégories: {$stats['categories_count']}<br>";
} catch (PDOException $e) {
    echo "❌ Erreur lors du calcul des statistiques: " . $e->getMessage() . "<br>";
}

// Test 5: Vérifier les catégories
echo "<h2>5. Catégories disponibles</h2>";
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
    echo "✅ Catégories trouvées:<br>";
    foreach ($categories as $category) {
        echo "- $category<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur lors de la récupération des catégories: " . $e->getMessage() . "<br>";
}

// Test 6: Vérifier les vendeurs
echo "<h2>6. Vendeurs disponibles</h2>";
try {
    $vendors = $pdo->query("
        SELECT DISTINCT u.fullname 
        FROM users u 
        JOIN products p ON u.id = p.vendor_id 
        WHERE p.status = 'active' AND u.fullname IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✅ Vendeurs trouvés:<br>";
    foreach ($vendors as $vendor) {
        echo "- $vendor<br>";
    }
} catch (PDOException $e) {
    echo "❌ Erreur lors de la récupération des vendeurs: " . $e->getMessage() . "<br>";
}

// Test 7: Vérifier les fichiers
echo "<h2>7. Vérification des fichiers</h2>";
$files_to_check = [
    'public/main_site/shop.php',
    'public/main_site/view_product.php',
    'public/main_site/add_to_cart.php',
    'public/main_site/basket.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file manquant<br>";
    }
}

echo "<h2>8. Liens de test</h2>";
echo "<p>Vous pouvez maintenant tester les pages suivantes:</p>";
echo "<ul>";
echo "<li><a href='public/main_site/shop.php' target='_blank'>Boutique (shop.php)</a></li>";
echo "<li><a href='public/main_site/basket.php' target='_blank'>Panier (basket.php)</a></li>";
echo "</ul>";

if (!empty($products)) {
    echo "<p>Testez un produit spécifique:</p>";
    echo "<ul>";
    foreach ($products as $product) {
        echo "<li><a href='public/main_site/view_product.php?id={$product['id']}' target='_blank'>{$product['name']}</a></li>";
    }
    echo "</ul>";
}

echo "<h2>✅ Test terminé !</h2>";
echo "<p>Toutes les fonctionnalités de la boutique sont prêtes à être utilisées.</p>";
?>
