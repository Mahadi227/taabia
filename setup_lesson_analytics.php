<?php

/**
 * Setup script for lesson analytics tables
 * Run this script to create the necessary database tables for lesson analytics
 */

require_once 'includes/db.php';

try {
    // Read the SQL file
    $sql = file_get_contents('database/lesson_analytics_simple.sql');

    if ($sql === false) {
        throw new Exception('Could not read lesson_analytics_tables.sql file');
    }

    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    $pdo = new PDO("mysql:host=localhost;dbname=taabia_skills;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    $success_count = 0;
    $error_count = 0;

    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }

        try {
            $pdo->exec($statement);
            $success_count++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            $error_count++;
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }

    $pdo->commit();

    echo "\n=== Setup Complete ===\n";
    echo "Successful statements: $success_count\n";
    echo "Failed statements: $error_count\n";

    if ($error_count === 0) {
        echo "✓ All lesson analytics tables created successfully!\n";
    } else {
        echo "⚠ Some statements failed. Check the errors above.\n";
    }
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
