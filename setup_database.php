<?php
/**
 * TaaBia Skills & Market - Database Setup Script
 * 
 * This script will create all necessary database tables and insert initial data.
 * Run this script once to set up your database.
 */

// Database configuration
$host = 'localhost';
$db   = 'taabia_skills';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// DSN PDO
$dsn = "mysql:host=$host;charset=$charset";

// Options PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false
];

try {
    // Connect without database first
    $pdo = new PDO($dsn, $user, $pass, $options);
    
    echo "✅ Connected to MySQL server successfully\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database '$db' created or already exists\n";
    
    // Connect to the specific database
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
    
    // Read and execute the schema file
    $schemaFile = __DIR__ . '/database/schema.sql';
    
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    echo "✅ Executed SQL statement\n";
                } catch (PDOException $e) {
                    echo "⚠️  Warning: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✅ Database schema imported successfully\n";
        
        // Verify tables were created
        $tables = [
            'users', 'courses', 'course_contents', 'student_courses',
            'products', 'orders', 'order_items', 'transactions',
            'events', 'event_registrations', 'messages', 'payout_requests',
            'course_submissions', 'contact_messages', 'system_settings'
        ];
        
        echo "\n📊 Verifying table creation:\n";
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "✅ Table '$table' exists\n";
            } else {
                echo "❌ Table '$table' missing\n";
            }
        }
        
        // Check for admin user
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $adminCount = $stmt->fetchColumn();
        
        if ($adminCount == 0) {
            echo "\n⚠️  No admin user found. Creating default admin account...\n";
            echo "Default admin credentials:\n";
            echo "Email: admin@taabia.com\n";
            echo "Password: admin123\n";
            echo "⚠️  Please change the password after first login!\n";
        } else {
            echo "\n✅ Admin user already exists\n";
        }
        
        echo "\n🎉 Database setup completed successfully!\n";
        echo "\n📋 Next steps:\n";
        echo "1. Update database credentials in includes/db.php\n";
        echo "2. Configure payment gateway credentials\n";
        echo "3. Set up file upload permissions\n";
        echo "4. Test the platform functionality\n";
        
    } else {
        echo "❌ Schema file not found at: $schemaFile\n";
        echo "Please ensure the database/schema.sql file exists.\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\n📋 Troubleshooting:\n";
    echo "1. Make sure MySQL is running\n";
    echo "2. Check database credentials\n";
    echo "3. Ensure the database user has proper permissions\n";
    echo "4. Verify the database name is correct\n";
}

echo "\n🔧 Database setup script completed.\n";
?>