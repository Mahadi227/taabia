<?php
// Diagnostic script for student dashboard issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Student Dashboard Diagnostic</h1>";

// Test 1: Session
echo "<h2>1. Session Test</h2>";
session_start();
echo "Session started: " . (session_status() === PHP_SESSION_ACTIVE ? "✅ Yes" : "❌ No") . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session data: <pre>" . print_r($_SESSION, true) . "</pre>";

// Test 2: File existence
echo "<h2>2. File Existence Test</h2>";
$files = [
    '../../includes/db.php',
    '../../includes/function.php',
    '../../includes/community_functions.php',
    '../../includes/i18n.php'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    echo "$file: " . ($exists ? "✅ Exists" : "❌ Missing") . "<br>";
}

// Test 3: Include files
echo "<h2>3. Include Test</h2>";
try {
    require_once '../../includes/db.php';
    echo "db.php: ✅ Loaded<br>";

    require_once '../../includes/function.php';
    echo "function.php: ✅ Loaded<br>";

    require_once '../../includes/i18n.php';
    echo "i18n.php: ✅ Loaded<br>";

    if (isset($pdo)) {
        echo "Database connection: ✅ Available<br>";
    } else {
        echo "Database connection: ❌ Not available<br>";
    }
} catch (Exception $e) {
    echo "Include error: ❌ " . $e->getMessage() . "<br>";
}

// Test 4: Function availability
echo "<h2>4. Function Test</h2>";
$functions = ['is_logged_in', 'has_role', 'current_user_id', 't'];
foreach ($functions as $func) {
    $exists = function_exists($func);
    echo "$func(): " . ($exists ? "✅ Available" : "❌ Missing") . "<br>";
}

// Test 5: Authentication
echo "<h2>5. Authentication Test</h2>";
if (function_exists('is_logged_in')) {
    $logged_in = is_logged_in();
    echo "is_logged_in(): " . ($logged_in ? "✅ True" : "❌ False") . "<br>";

    if ($logged_in && function_exists('has_role')) {
        $is_student = has_role('student');
        echo "has_role('student'): " . ($is_student ? "✅ True" : "❌ False") . "<br>";
    }
}

// Test 6: Database connection
echo "<h2>6. Database Test</h2>";
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT 1");
        echo "Database query: ✅ Working<br>";
    } catch (Exception $e) {
        echo "Database query: ❌ Error: " . $e->getMessage() . "<br>";
    }
}

// Test 7: Translation
echo "<h2>7. Translation Test</h2>";
if (function_exists('t')) {
    $test_translation = t('dashboard');
    echo "Translation test: ✅ '$test_translation'<br>";
} else {
    echo "Translation test: ❌ Function not available<br>";
}

echo "<h2>Next Steps</h2>";
echo "<p>If all tests pass, the issue might be:</p>";
echo "<ul>";
echo "<li>User not logged in as student</li>";
echo "<li>Session timeout</li>";
echo "<li>Browser cache issues</li>";
echo "<li>Server configuration</li>";
echo "</ul>";

echo "<p><a href='minimal.php'>Try Minimal Dashboard</a></p>";
echo "<p><a href='../../auth/login.php'>Go to Login</a></p>";






