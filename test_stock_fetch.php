<?php
require_once 'includes/db.php';

echo "<h1>Test de récupération des stocks</h1>";

// Test 1: Vérifier la structure de la table products
echo "<h2>1. Structure de la table products</h2>";
try {
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
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// Test 2: Vérifier les produits avec leurs stocks
echo "<h2>2. Produits avec leurs stocks</h2>";
try {
    $stmt = $pdo->query("SELECT id, name, stock_quantity, status FROM products LIMIT 10");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($products)) {
        echo "❌ Aucun produit trouvé dans la base de données<br>";
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
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// Test 3: Tester la requête exacte de get_stock_status.php
echo "<h2>3. Test de la requête get_stock_status.php</h2>";
try {
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
} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "<br>";
}

// Test 4: Simuler l'appel AJAX
echo "<h2>4. Test de simulation AJAX</h2>";
echo "<button onclick='testAjaxCall()'>Tester l'appel AJAX</button>";
echo "<div id='ajax-result' style='margin-top: 10px; padding: 10px; border: 1px solid #ccc;'></div>";

echo "<script>
function testAjaxCall() {
    const resultDiv = document.getElementById('ajax-result');
    resultDiv.innerHTML = 'Envoi de la requête...';
    
    fetch('public/main_site/get_stock_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ product_ids: [1, 2, 3] })
    })
    .then(response => response.json())
    .then(data => {
        resultDiv.innerHTML = '<strong>Réponse:</strong><br>' + JSON.stringify(data, null, 2);
    })
    .catch(error => {
        resultDiv.innerHTML = '<strong>Erreur:</strong><br>' + error.message;
    });
}
</script>";

// Test 5: Vérifier les logs d'erreur
echo "<h2>5. Vérification des logs d'erreur</h2>";
$error_log_path = ini_get('error_log');
if ($error_log_path) {
    echo "Log d'erreur configuré: $error_log_path<br>";
    if (file_exists($error_log_path)) {
        $log_content = file_get_contents($error_log_path);
        if (strpos($log_content, 'Stock status error') !== false) {
            echo "⚠️ Erreurs trouvées dans les logs liées aux stocks<br>";
        } else {
            echo "✅ Aucune erreur liée aux stocks dans les logs<br>";
        }
    } else {
        echo "⚠️ Fichier de log non trouvé<br>";
    }
} else {
    echo "⚠️ Aucun log d'erreur configuré<br>";
}

echo "<h2>6. Instructions de débogage</h2>";
echo "<ol>";
echo "<li>Vérifiez que la table 'products' existe et contient des données</li>";
echo "<li>Assurez-vous que les colonnes 'id', 'stock', 'status' existent</li>";
echo "<li>Vérifiez que les produits ont le statut 'active'</li>";
echo "<li>Testez l'appel AJAX en cliquant sur le bouton ci-dessus</li>";
echo "<li>Vérifiez la console du navigateur pour les erreurs JavaScript</li>";
echo "</ol>";
?>

