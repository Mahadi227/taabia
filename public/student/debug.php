<?php
// Debug file to test student dashboard
echo "Debug: Starting...\n";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "Debug: Session started\n";

// Check if includes exist
$includes = [
    '../../includes/db.php',
    '../../includes/function.php',
    '../../includes/community_functions.php',
    '../../includes/i18n.php'
];

foreach ($includes as $include) {
    if (file_exists($include)) {
        echo "Debug: Found $include\n";
    } else {
        echo "Debug: Missing $include\n";
    }
}

// Try to include the files
try {
    require_once '../../includes/db.php';
    echo "Debug: db.php included\n";
    
    require_once '../../includes/function.php';
    echo "Debug: function.php included\n";
    
    require_once '../../includes/community_functions.php';
    echo "Debug: community_functions.php included\n";
    
    require_once '../../includes/i18n.php';
    echo "Debug: i18n.php included\n";
    
    // Check if functions exist
    if (function_exists('is_logged_in')) {
        echo "Debug: is_logged_in function exists\n";
    } else {
        echo "Debug: is_logged_in function NOT found\n";
    }
    
    if (function_exists('has_role')) {
        echo "Debug: has_role function exists\n";
    } else {
        echo "Debug: has_role function NOT found\n";
    }
    
    if (function_exists('current_user_id')) {
        echo "Debug: current_user_id function exists\n";
    } else {
        echo "Debug: current_user_id function NOT found\n";
    }
    
    if (function_exists('t')) {
        echo "Debug: t function exists\n";
    } else {
        echo "Debug: t function NOT found\n";
    }
    
    // Check session data
    echo "Debug: Session data:\n";
    print_r($_SESSION);
    
} catch (Exception $e) {
    echo "Debug: Error: " . $e->getMessage() . "\n";
}

echo "Debug: Complete\n";
?>






