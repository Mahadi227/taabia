<?php
/**
 * Attendance System Setup Script
 * Run this script to set up the attendance system database tables
 */

require_once 'includes/db.php';

echo "Setting up Attendance System...\n\n";

try {
    // Read the attendance system SQL file
    $sql_file = 'database/attendance_system.sql';
    
    if (!file_exists($sql_file)) {
        echo "Error: SQL file not found: $sql_file\n";
        exit(1);
    }
    
    $sql_content = file_get_contents($sql_file);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql_content)),
        function($stmt) { return !empty($stmt) && !preg_match('/^--/', $stmt); }
    );
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
            $success_count++;
            echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
        } catch (PDOException $e) {
            $error_count++;
            echo "✗ Error: " . $e->getMessage() . "\n";
            echo "   Statement: " . substr($statement, 0, 100) . "...\n";
        }
    }
    
    echo "\n";
    echo "Setup completed!\n";
    echo "Successful statements: $success_count\n";
    echo "Failed statements: $error_count\n";
    
    if ($error_count > 0) {
        echo "\nSome statements failed. Please check the errors above.\n";
        exit(1);
    } else {
        echo "\nAttendance system setup completed successfully!\n";
        echo "You can now use the attendance features in the student and instructor areas.\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 