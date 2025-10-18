<?php
require_once 'includes/db.php';

echo "<h1>Test de correction des stocks</h1>";

try {
    // Test 1: Vérifier la structure de la table products
    echo "<h2>1. Structure de la table products</h2>";
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test 2: Vérifier les produits avec leurs stocks
    echo "<h2>2. Produits avec leurs stocks (stock_quantity)</h2>";
    $stmt = $pdo->query("SELECT id, name, stock_quantity, status FROM products LIMIT 10");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "❌ Aucun produit trouvé<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Stock</th><th>Statut</th></tr>";
        foreach ($products as $product) {
            echo "<tr>";
            echo "<td>" . $product['id'] . "</td>";
            echo "<td>" . htmlspecialchars($product['name']) . "</td>";
            echo "<td>" . $product['stock_quantity'] . "</td>";
            echo "<td>" . $product['status'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test 3: Tester la requête exacte de get_stock_status.php
    echo "<h2>3. Test de la requête get_stock_status.php</h2>";
    $product_ids = [1, 2, 3]; // Test avec quelques IDs
    
    $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
    $query = "
        SELECT id, stock_quantity, created_at
        FROM products 
        WHERE id IN ($placeholders) AND status = 'active'
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($product_ids);
    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($stocks)) {
        echo "❌ Aucun stock trouvé pour les IDs: " . implode(', ', $product_ids) . "<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Stock</th><th>Créé le</th></tr>";
        foreach ($stocks as $stock) {
            echo "<tr>";
            echo "<td>" . $stock['id'] . "</td>";
            echo "<td>" . $stock['stock_quantity'] . "</td>";
            echo "<td>" . $stock['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Test 4: Simuler l'appel AJAX vers get_stock_status.php
    echo "<h2>4. Test de l'appel AJAX vers get_stock_status.php</h2>";
    echo "<div id='ajax-result'>Test en cours...</div>";
    
    echo "<script>
        fetch('public/main_site/get_stock_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ product_ids: [1, 2, 3] })
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('ajax-result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('ajax-result').innerHTML = 'Erreur: ' + error.message;
        });
    </script>";

    // Test 5: Vérifier les logs d'erreur
    echo "<h2>5. Vérification des logs d'erreur</h2>";
    $log_file = 'error.log';
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        if (strpos($log_content, 'Stock status error') !== false) {
            echo "⚠️ Erreurs trouvées dans les logs liées aux stocks<br>";
            echo "<pre>" . htmlspecialchars(substr($log_content, -500)) . "</pre>";
        } else {
            echo "✅ Aucune erreur liée aux stocks dans les logs<br>";
        }
    } else {
        echo "ℹ️ Fichier de log non trouvé<br>";
    }

    echo "<h2>6. Résumé des corrections</h2>";
    echo "<ul>";
    echo "<li>✅ Colonne 'stock' changée en 'stock_quantity' dans get_stock_status.php</li>";
    echo "<li>✅ Colonne 'stock' changée en 'stock_quantity' dans shop.php</li>";
    echo "<li>✅ Colonne 'stock' changée en 'stock_quantity' dans view_product.php</li>";
    echo "<li>✅ Colonne 'stock' changée en 'stock_quantity' dans admin/add_product.php</li>";
    echo "<li>✅ Colonne 'stock' changée en 'stock_quantity' dans les fichiers de test</li>";
    echo "<li>✅ Assurez-vous que les colonnes 'id', 'stock_quantity', 'status' existent</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "❌ Erreur de base de données: " . $e->getMessage();
}
?>




























