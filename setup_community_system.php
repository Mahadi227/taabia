<?php

/**
 * Community System Setup Script
 * This script sets up the community system database tables and initial data
 */

require_once 'includes/db.php';

try {
    echo "Setting up Community System...\n";

    // Read and execute the community system SQL
    $sql = file_get_contents('database/community_system.sql');

    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            $pdo->exec($statement);
            echo "Executed: " . substr($statement, 0, 50) . "...\n";
        }
    }

    echo "Community system setup completed successfully!\n";
    echo "Database tables created:\n";
    echo "- communities\n";
    echo "- community_members\n";
    echo "- community_posts\n";
    echo "- post_comments\n";
    echo "- post_likes\n";
    echo "- comment_likes\n";
    echo "- community_invitations\n";
    echo "- community_notifications\n";
    echo "- community_categories\n";
    echo "- community_category_assignments\n";

    echo "\nSample data inserted:\n";
    echo "- Default community categories\n";
    echo "- Sample communities\n";
    echo "- Community category assignments\n";

    echo "\nNext steps:\n";
    echo "1. Update your navigation menus to include community links\n";
    echo "2. Add community language files to your i18n system\n";
    echo "3. Test the community functionality\n";
    echo "4. Configure community permissions in system settings\n";
} catch (Exception $e) {
    echo "Error setting up community system: " . $e->getMessage() . "\n";
    exit(1);
}






