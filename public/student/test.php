<?php
// Simple test version of student dashboard
session_start();

// Check if user is logged in (simplified check)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    echo "User not logged in. Redirecting to login...";
    // For testing, let's just show a message
    echo "<h1>Not logged in</h1>";
    echo "<p>Please <a href='../../auth/login.php'>login</a> first.</p>";
    exit;
}

if ($_SESSION['role'] !== 'student') {
    echo "<h1>Access Denied</h1>";
    echo "<p>You need to be a student to access this page.</p>";
    exit;
}

// Try to include the files one by one
try {
    require_once '../../includes/db.php';
    echo "<!-- Database connection loaded -->\n";

    require_once '../../includes/function.php';
    echo "<!-- Functions loaded -->\n";

    require_once '../../includes/community_functions.php';
    echo "<!-- Community functions loaded -->\n";

    require_once '../../includes/i18n.php';
    echo "<!-- Translation system loaded -->\n";
} catch (Exception $e) {
    echo "<h1>Error Loading Files</h1>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test translation function
if (function_exists('t')) {
    $welcome_msg = t('welcome_back');
} else {
    $welcome_msg = 'Welcome back';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-12">
                <h1 class="text-success">✅ Student Dashboard Test Page</h1>
                <div class="alert alert-success">
                    <h4>Success!</h4>
                    <p>All required files loaded successfully.</p>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3>User Information</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>User ID:</strong> <?= htmlspecialchars($_SESSION['user_id'] ?? 'Not set') ?></p>
                        <p><strong>Full Name:</strong> <?= htmlspecialchars($_SESSION['fullname'] ?? 'Not set') ?></p>
                        <p><strong>Role:</strong> <?= htmlspecialchars($_SESSION['role'] ?? 'Not set') ?></p>
                        <p><strong>Language:</strong> <?= htmlspecialchars($_SESSION['user_language'] ?? 'Not set') ?></p>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h3>Translation Test</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Welcome Message:</strong> <?= htmlspecialchars($welcome_msg) ?></p>
                        <p><strong>Dashboard:</strong> <?= function_exists('t') ? t('dashboard') : 'Dashboard' ?></p>
                        <p><strong>Communities:</strong> <?= function_exists('t') ? t('communities') : 'Communities' ?></p>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="index.php" class="btn btn-primary">Go to Full Dashboard</a>
                    <a href="../../auth/logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>





