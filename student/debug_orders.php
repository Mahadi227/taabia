<?php

/**
 * Debug Orders Page
 * Diagnose why orders are not displaying
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('student');

$student_id = $_SESSION['user_id'];

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug Orders</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 2rem;
            background: #1e1e1e;
            color: #d4d4d4;
        }

        .section {
            background: #252526;
            border: 1px solid #3e3e42;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        h2 {
            color: #4ec9b0;
            margin-top: 0;
        }

        h3 {
            color: #dcdcaa;
        }

        .success {
            color: #4ec9b0;
        }

        .error {
            color: #f48771;
        }

        .warning {
            color: #ce9178;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }

        th,
        td {
            border: 1px solid #3e3e42;
            padding: 0.5rem;
            text-align: left;
        }

        th {
            background: #2d2d30;
            color: #4ec9b0;
        }

        pre {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            padding: 1rem;
            overflow-x: auto;
            color: #ce9178;
        }
    </style>
</head>

<body>
    <h1>🔍 Orders Debug Report</h1>
    <p>Student ID: <strong><?= $student_id ?></strong></p>

    <!-- SECTION 1: Check orders table structure -->
    <div class="section">
        <h2>1️⃣ Orders Table Structure</h2>
        <?php
        try {
            $stmt = $pdo->query("DESCRIBE orders");
            $columns = $stmt->fetchAll();

            echo "<p class='success'>✅ Table 'orders' exists with " . count($columns) . " columns:</p>";
            echo "<table>";
            echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
            foreach ($columns as $col) {
                echo "<tr>";
                echo "<td><strong>{$col['Field']}</strong></td>";
                echo "<td>{$col['Type']}</td>";
                echo "<td>{$col['Null']}</td>";
                echo "<td>{$col['Key']}</td>";
                echo "<td>{$col['Default']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <!-- SECTION 2: Count total orders -->
    <div class="section">
        <h2>2️⃣ Total Orders in Database</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM orders");
            $total = $stmt->fetch()['total'];

            if ($total > 0) {
                echo "<p class='success'>✅ Total orders in database: <strong>$total</strong></p>";
            } else {
                echo "<p class='warning'>⚠️ No orders found in the database</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <!-- SECTION 3: Check buyer_id column variations -->
    <div class="section">
        <h2>3️⃣ Check Different Column Names for Student ID</h2>
        <?php
        $possible_columns = ['buyer_id', 'student_id', 'user_id', 'customer_id'];

        foreach ($possible_columns as $col) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE $col = ?");
                $stmt->execute([$student_id]);
                $count = $stmt->fetch()['count'];

                if ($count > 0) {
                    echo "<p class='success'>✅ Found <strong>$count</strong> orders with <strong>$col = $student_id</strong></p>";
                } else {
                    echo "<p>⚪ No orders with <strong>$col = $student_id</strong></p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Column '$col' doesn't exist</p>";
            }
        }
        ?>
    </div>

    <!-- SECTION 4: Show all orders in database (first 10) -->
    <div class="section">
        <h2>4️⃣ Sample Orders Data (First 10 Rows)</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT * FROM orders LIMIT 10");
            $orders = $stmt->fetchAll();

            if (!empty($orders)) {
                echo "<p class='success'>✅ Sample of " . count($orders) . " orders:</p>";
                echo "<div style='overflow-x: auto;'><table>";

                // Headers
                echo "<tr>";
                foreach (array_keys($orders[0]) as $key) {
                    if (!is_numeric($key)) {
                        echo "<th>$key</th>";
                    }
                }
                echo "</tr>";

                // Data
                foreach ($orders as $order) {
                    echo "<tr>";
                    foreach ($order as $key => $value) {
                        if (!is_numeric($key)) {
                            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                        }
                    }
                    echo "</tr>";
                }
                echo "</table></div>";
            } else {
                echo "<p class='warning'>⚠️ No orders in database</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <!-- SECTION 5: Try different queries -->
    <div class="section">
        <h2>5️⃣ Test Different Queries</h2>
        <?php
        $test_queries = [
            "Query 1 (buyer_id)" => "SELECT * FROM orders WHERE buyer_id = $student_id",
            "Query 2 (student_id)" => "SELECT * FROM orders WHERE student_id = $student_id",
            "Query 3 (user_id)" => "SELECT * FROM orders WHERE user_id = $student_id",
            "Query 4 (all orders)" => "SELECT * FROM orders LIMIT 5"
        ];

        foreach ($test_queries as $name => $query) {
            echo "<h3>$name</h3>";
            echo "<pre>$query</pre>";

            try {
                $stmt = $pdo->query($query);
                $results = $stmt->fetchAll();
                $count = count($results);

                if ($count > 0) {
                    echo "<p class='success'>✅ Success: Found <strong>$count</strong> rows</p>";

                    // Show first result
                    if (isset($results[0])) {
                        echo "<details><summary>View first result</summary>";
                        echo "<pre>" . print_r($results[0], true) . "</pre>";
                        echo "</details>";
                    }
                } else {
                    echo "<p class='warning'>⚠️ Query succeeded but returned 0 rows</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>❌ Query failed: " . $e->getMessage() . "</p>";
            }
        }
        ?>
    </div>

    <!-- SECTION 6: Check products table -->
    <div class="section">
        <h2>6️⃣ Check Products Table</h2>
        <?php
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
            $total_products = $stmt->fetch()['total'];
            echo "<p class='success'>✅ Products table exists with <strong>$total_products</strong> products</p>";

            // Show sample products
            $stmt = $pdo->query("SELECT id, name FROM products LIMIT 5");
            $products = $stmt->fetchAll();
            if (!empty($products)) {
                echo "<p>Sample products:</p><ul>";
                foreach ($products as $p) {
                    echo "<li>ID: {$p['id']} - {$p['name']}</li>";
                }
                echo "</ul>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Products table error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <!-- SECTION 7: Check user info -->
    <div class="section">
        <h2>7️⃣ Current User Info</h2>
        <?php
        try {
            $stmt = $pdo->prepare("SELECT id, email, full_name, role FROM users WHERE id = ?");
            $stmt->execute([$student_id]);
            $user = $stmt->fetch();

            if ($user) {
                echo "<p class='success'>✅ User found:</p>";
                echo "<pre>" . print_r($user, true) . "</pre>";
            } else {
                echo "<p class='error'>❌ User not found!</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>

    <div class="section">
        <h2>📝 Recommendations</h2>
        <ul>
            <li>Check Section 3 to see which column name contains your orders</li>
            <li>Check Section 4 to see the actual data structure</li>
            <li>If orders exist but with different column names, we need to update the query</li>
            <li>If no orders exist at all, you may need to create test orders</li>
        </ul>
        <p><a href="orders.php" style="color: #4ec9b0;">← Back to Orders Page</a></p>
    </div>
</body>

</html>






