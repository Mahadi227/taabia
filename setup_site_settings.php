<?php
/**
 * Setup Site Settings Migration
 * This script updates the database with the new site settings structure
 */

require_once 'includes/db.php';

try {
    echo "🔄 Starting site settings migration...\n";
    
    // Read and execute the migration SQL
    $sql = file_get_contents('database/update_site_settings.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $pdo->beginTransaction();
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            echo "✅ Executed: " . substr($statement, 0, 50) . "...\n";
        }
    }
    
    $pdo->commit();
    
    echo "✅ Site settings migration completed successfully!\n";
    echo "🎉 You can now access the comprehensive site settings at: admin/site_settings.php\n";
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
