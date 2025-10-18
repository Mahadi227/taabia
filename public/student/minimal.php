<?php
// Minimal student dashboard test
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../../auth/login.php');
    exit;
}

// Load required files
require_once '../../includes/db.php';
require_once '../../includes/function.php';
require_once '../../includes/i18n.php';

$user_name = $_SESSION['fullname'] ?? 'Student';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Minimal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-graduation-cap me-2"></i>TaaBia
            </a>
            <div class="navbar-nav">
                <a class="nav-link" href="../../auth/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="jumbotron bg-light p-4 rounded">
                    <h1 class="display-4">Welcome, <?= htmlspecialchars($user_name) ?>!</h1>
                    <p class="lead">This is a minimal test version of your student dashboard.</p>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-book text-primary"></i> My Courses
                                </h5>
                                <p class="card-text">View and manage your enrolled courses.</p>
                                <a href="courses.php" class="btn btn-primary">Go to Courses</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-users text-success"></i> Communities
                                </h5>
                                <p class="card-text">Join and participate in communities.</p>
                                <a href="../communities.php" class="btn btn-success">Browse Communities</a>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-user text-info"></i> Profile
                                </h5>
                                <p class="card-text">Manage your profile settings.</p>
                                <a href="profile.php" class="btn btn-info">Edit Profile</a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="alert alert-info">
                        <h5>Debug Information</h5>
                        <p><strong>User ID:</strong> <?= htmlspecialchars($_SESSION['user_id']) ?></p>
                        <p><strong>Role:</strong> <?= htmlspecialchars($_SESSION['role']) ?></p>
                        <p><strong>Language:</strong> <?= htmlspecialchars($_SESSION['user_language'] ?? 'Not set') ?></p>
                        <p><strong>Translation Test:</strong> <?= function_exists('t') ? t('dashboard') : 'Translation not working' ?></p>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="index.php" class="btn btn-warning">Try Full Dashboard</a>
                    <a href="test.php" class="btn btn-secondary">Run Full Test</a>
                </div>
            </div>
        </div>
    </div>
</body>

</html>





