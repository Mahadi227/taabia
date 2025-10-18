<?php

/**
 * Community System Test Script
 * This script tests the community system functionality
 */

session_start();
require_once 'includes/db.php';
require_once 'includes/function.php';
require_once 'includes/community_functions.php';
require_once 'includes/i18n.php';

// Test user login (you may need to adjust this based on your test user)
$_SESSION['user_id'] = 1; // Assuming user ID 1 exists
$_SESSION['role'] = 'admin';

echo "<h1>Community System Test</h1>";

try {
    // Test 1: Database Connection
    echo "<h2>Test 1: Database Connection</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM communities");
    $result = $stmt->fetch();
    echo "✓ Database connection successful. Communities table has {$result['count']} records.<br>";

    // Test 2: Community Categories
    echo "<h2>Test 2: Community Categories</h2>";
    $categories = get_community_categories();
    echo "✓ Found " . count($categories) . " community categories:<br>";
    foreach ($categories as $category) {
        echo "- {$category['name']} ({$category['description']})<br>";
    }

    // Test 3: User Communities
    echo "<h2>Test 3: User Communities</h2>";
    $user_communities = get_user_communities(1);
    echo "✓ User has " . count($user_communities) . " communities:<br>";
    foreach ($user_communities as $community) {
        echo "- {$community['name']} (Role: {$community['role']})<br>";
    }

    // Test 4: Community Permissions
    echo "<h2>Test 4: Community Permissions</h2>";
    if (!empty($user_communities)) {
        $community_id = $user_communities[0]['id'];
        $permissions = get_community_permissions($community_id, 1);
        echo "✓ Community permissions for user:<br>";
        foreach ($permissions as $permission => $value) {
            echo "- {$permission}: " . ($value ? 'Yes' : 'No') . "<br>";
        }
    }

    // Test 5: Community Statistics
    echo "<h2>Test 5: Community Statistics</h2>";
    if (!empty($user_communities)) {
        $community_id = $user_communities[0]['id'];
        $stats = get_community_stats($community_id);
        echo "✓ Community statistics:<br>";
        echo "- Members: {$stats['member_count']}<br>";
        echo "- Posts: {$stats['post_count']}<br>";
        echo "- Comments: {$stats['comment_count']}<br>";
        echo "- Likes: {$stats['like_count']}<br>";
    }

    // Test 6: Trending Communities
    echo "<h2>Test 6: Trending Communities</h2>";
    $trending = get_trending_communities(5);
    echo "✓ Found " . count($trending) . " trending communities:<br>";
    foreach ($trending as $community) {
        echo "- {$community['name']} ({$community['member_count']} members, {$community['post_count']} posts)<br>";
    }

    // Test 7: Search Communities
    echo "<h2>Test 7: Search Communities</h2>";
    $search_results = search_communities('web', null, 'public', 5);
    echo "✓ Search for 'web' returned " . count($search_results) . " results:<br>";
    foreach ($search_results as $community) {
        echo "- {$community['name']} ({$community['privacy']})<br>";
    }

    // Test 8: Community Creation Permission
    echo "<h2>Test 8: Community Creation Permission</h2>";
    $can_create = can_create_communities(1);
    echo "✓ User can create communities: " . ($can_create ? 'Yes' : 'No') . "<br>";

    // Test 9: Language Support
    echo "<h2>Test 9: Language Support</h2>";
    echo "✓ Testing language functions:<br>";
    echo "- " . t('communities') . "<br>";
    echo "- " . t('create_community') . "<br>";
    echo "- " . t('join_community') . "<br>";
    echo "- " . t('my_communities') . "<br>";

    // Test 10: File Structure
    echo "<h2>Test 10: File Structure</h2>";
    $required_files = [
        'database/community_system.sql',
        'admin/communities.php',
        'admin/community_details.php',
        'public/communities.php',
        'public/community.php',
        'public/community_create.php',
        'public/community_post.php',
        'api/community_actions.php',
        'includes/community_functions.php',
        'lang/community_en.php',
        'lang/community_fr.php',
        'setup_community_system.php'
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
            echo "- {$file}<br>";
        }
    }

    echo "<h2>Test Summary</h2>";
    echo "✓ Community system test completed successfully!<br>";
    echo "<br><strong>Next Steps:</strong><br>";
    echo "1. Run setup_community_system.php to create database tables<br>";
    echo "2. Update your navigation menus to include community links<br>";
    echo "3. Test the community functionality in your browser<br>";
    echo "4. Configure community permissions in system settings<br>";
} catch (Exception $e) {
    echo "<h2>Test Failed</h2>";
    echo "✗ Error: " . $e->getMessage() . "<br>";
    echo "<br>Please check your database connection and ensure all required files exist.";
}






