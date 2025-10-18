<?php

/**
 * Test Student Dashboard
 * This script tests the student dashboard functionality
 */

session_start();

// Set up test session data
$_SESSION['user_id'] = 1; // Assuming user ID 1 exists
$_SESSION['role'] = 'student';
$_SESSION['fullname'] = 'Test Student';
$_SESSION['user_language'] = 'en';

echo "<h1>Student Dashboard Test</h1>";

try {
    // Test 1: Check if student dashboard file exists
    echo "<h2>Test 1: File Existence</h2>";
    if (file_exists('public/student/index.php')) {
        echo "✓ Student dashboard file exists<br>";
    } else {
        echo "✗ Student dashboard file not found<br>";
    }

    // Test 2: Check required includes
    echo "<h2>Test 2: Required Files</h2>";
    $required_files = [
        'includes/db.php',
        'includes/function.php',
        'includes/community_functions.php',
        'includes/i18n.php'
    ];

    $missing_files = [];
    foreach ($required_files as $file) {
        if (!file_exists($file)) {
            $missing_files[] = $file;
        }
    }

    if (empty($missing_files)) {
        echo "✓ All required files exist<br>";
    } else {
        echo "✗ Missing files:<br>";
        foreach ($missing_files as $file) {
            echo "- $file<br>";
        }
    }

    // Test 3: Check language files
    echo "<h2>Test 3: Language Files</h2>";
    if (file_exists('lang/community_en.php') && file_exists('lang/community_fr.php')) {
        echo "✓ Community language files exist<br>";
    } else {
        echo "✗ Community language files missing<br>";
    }

    // Test 4: Test database connection
    echo "<h2>Test 4: Database Connection</h2>";
    try {
        require_once 'includes/db.php';
        $stmt = $pdo->query("SELECT 1");
        echo "✓ Database connection successful<br>";
    } catch (Exception $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
    }

    // Test 5: Test community functions
    echo "<h2>Test 5: Community Functions</h2>";
    try {
        require_once 'includes/community_functions.php';
        echo "✓ Community functions loaded successfully<br>";
    } catch (Exception $e) {
        echo "✗ Community functions failed: " . $e->getMessage() . "<br>";
    }

    // Test 6: Test language system
    echo "<h2>Test 6: Language System</h2>";
    try {
        require_once 'includes/i18n.php';
        echo "✓ Language system loaded successfully<br>";
    } catch (Exception $e) {
        echo "✗ Language system failed: " . $e->getMessage() . "<br>";
    }

    echo "<h2>Test Summary</h2>";
    echo "✓ Student dashboard test completed!<br>";
    echo "<br><strong>Next Steps:</strong><br>";
    echo "1. Access the student dashboard at: <a href='public/student/index.php'>public/student/index.php</a><br>";
    echo "2. Make sure you're logged in as a student<br>";
    echo "3. Test the community functionality<br>";
    echo "4. Verify language switching works<br>";
    echo "5. Test all navigation links<br>";
} catch (Exception $e) {
    echo "<h2>Test Failed</h2>";
    echo "✗ Error: " . $e->getMessage() . "<br>";
}
