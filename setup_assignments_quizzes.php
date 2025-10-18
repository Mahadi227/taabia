<?php

/**
 * Setup Script for Assignments & Quizzes Tables
 * This script creates all necessary tables for the assignments and quizzes functionality
 */

require_once 'includes/db.php';

echo "<h1>TaaBia LMS - Assignments & Quizzes Setup</h1>";
echo "<p>Setting up database tables for Assignments and Quizzes...</p>";

$errors = [];
$success = [];

try {
    // Create assignments table
    echo "<h3>Creating 'assignments' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assignments` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `course_id` INT(11) NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `instructions` TEXT DEFAULT NULL,
            `file_path` VARCHAR(255) DEFAULT NULL,
            `deadline` DATETIME NOT NULL,
            `max_grade` INT(11) DEFAULT 100,
            `weight` DECIMAL(5,2) DEFAULT 0.00,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `course_id` (`course_id`),
            KEY `deadline` (`deadline`),
            CONSTRAINT `fk_assignments_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'assignments' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'assignments' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

try {
    // Create assignment_submissions table
    echo "<h3>Creating 'assignment_submissions' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `assignment_submissions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `assignment_id` INT(11) NOT NULL,
            `student_id` INT(11) NOT NULL,
            `file_path` VARCHAR(255) DEFAULT NULL,
            `submission_text` TEXT DEFAULT NULL,
            `submission_url` VARCHAR(500) DEFAULT NULL,
            `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `grade` DECIMAL(5,2) DEFAULT NULL,
            `feedback` TEXT DEFAULT NULL,
            `graded_at` DATETIME DEFAULT NULL,
            `graded_by` INT(11) DEFAULT NULL,
            `status` ENUM('submitted', 'graded', 'late', 'resubmitted') DEFAULT 'submitted',
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
            KEY `student_id` (`student_id`),
            KEY `graded_by` (`graded_by`),
            KEY `status` (`status`),
            CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_submissions_grader` FOREIGN KEY (`graded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'assignment_submissions' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'assignment_submissions' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

try {
    // Create quizzes table
    echo "<h3>Creating 'quizzes' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `quizzes` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `course_id` INT(11) NOT NULL,
            `lesson_id` INT(11) DEFAULT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `instructions` TEXT DEFAULT NULL,
            `time_limit` INT(11) DEFAULT NULL,
            `passing_score` INT(11) DEFAULT 70,
            `max_attempts` INT(11) DEFAULT 1,
            `allow_retake` BOOLEAN DEFAULT FALSE,
            `show_correct_answers` BOOLEAN DEFAULT TRUE,
            `randomize_questions` BOOLEAN DEFAULT FALSE,
            `randomize_answers` BOOLEAN DEFAULT FALSE,
            `available_from` DATETIME DEFAULT NULL,
            `available_until` DATETIME DEFAULT NULL,
            `weight` DECIMAL(5,2) DEFAULT 0.00,
            `is_active` BOOLEAN DEFAULT TRUE,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `course_id` (`course_id`),
            KEY `lesson_id` (`lesson_id`),
            KEY `is_active` (`is_active`),
            CONSTRAINT `fk_quizzes_course` FOREIGN KEY (`course_id`) REFERENCES `courses`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_quizzes_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'quizzes' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'quizzes' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

try {
    // Create quiz_questions table
    echo "<h3>Creating 'quiz_questions' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `quiz_questions` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `quiz_id` INT(11) NOT NULL,
            `question_text` TEXT NOT NULL,
            `question_type` ENUM('multiple_choice', 'true_false', 'short_answer', 'essay') DEFAULT 'multiple_choice',
            `points` INT(11) DEFAULT 1,
            `order_index` INT(11) DEFAULT 0,
            `explanation` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `quiz_id` (`quiz_id`),
            KEY `order_index` (`order_index`),
            CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'quiz_questions' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'quiz_questions' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

try {
    // Create quiz_answers table
    echo "<h3>Creating 'quiz_answers' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `quiz_answers` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `question_id` INT(11) NOT NULL,
            `answer_text` TEXT NOT NULL,
            `is_correct` BOOLEAN DEFAULT FALSE,
            `order_index` INT(11) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `question_id` (`question_id`),
            KEY `order_index` (`order_index`),
            CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'quiz_answers' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'quiz_answers' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

try {
    // Create quiz_attempts table
    echo "<h3>Creating 'quiz_attempts' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `quiz_attempts` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `quiz_id` INT(11) NOT NULL,
            `student_id` INT(11) NOT NULL,
            `attempt_number` INT(11) DEFAULT 1,
            `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `completed_at` DATETIME DEFAULT NULL,
            `time_taken` INT(11) DEFAULT NULL,
            `score` DECIMAL(5,2) DEFAULT NULL,
            `points_earned` INT(11) DEFAULT NULL,
            `total_points` INT(11) DEFAULT NULL,
            `status` ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress',
            `ip_address` VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `quiz_id` (`quiz_id`),
            KEY `student_id` (`student_id`),
            KEY `status` (`status`),
            KEY `completed_at` (`completed_at`),
            CONSTRAINT `fk_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'quiz_attempts' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'quiz_attempts' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

try {
    // Create quiz_responses table
    echo "<h3>Creating 'quiz_responses' table...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `quiz_responses` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `attempt_id` INT(11) NOT NULL,
            `question_id` INT(11) NOT NULL,
            `answer_id` INT(11) DEFAULT NULL,
            `answer_text` TEXT DEFAULT NULL,
            `is_correct` BOOLEAN DEFAULT FALSE,
            `points_earned` INT(11) DEFAULT 0,
            `answered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `unique_response` (`attempt_id`, `question_id`),
            KEY `question_id` (`question_id`),
            KEY `answer_id` (`answer_id`),
            CONSTRAINT `fk_responses_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_responses_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_responses_answer` FOREIGN KEY (`answer_id`) REFERENCES `quiz_answers`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $success[] = "✓ 'quiz_responses' table created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    $errors[] = "× Error creating 'quiz_responses' table: " . $e->getMessage();
    echo "<p style='color: red;'>× Error: " . $e->getMessage() . "</p>";
}

// Create indexes for better performance
echo "<h3>Creating performance indexes...</h3>";
try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_assignments_course_deadline ON `assignments`(`course_id`, `deadline`)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_submissions_student_status ON `assignment_submissions`(`student_id`, `status`)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_quizzes_course_active ON `quizzes`(`course_id`, `is_active`)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_attempts_student_quiz ON `quiz_attempts`(`student_id`, `quiz_id`)");
    $success[] = "✓ Performance indexes created successfully";
    echo "<p style='color: green;'>✓ Success!</p>";
} catch (PDOException $e) {
    echo "<p style='color: orange;'>⚠ Some indexes may already exist: " . $e->getMessage() . "</p>";
}

// Display summary
echo "<hr>";
echo "<h2>Setup Summary</h2>";

if (!empty($success)) {
    echo "<h3 style='color: green;'>✓ Success Messages:</h3>";
    echo "<ul>";
    foreach ($success as $msg) {
        echo "<li style='color: green;'>$msg</li>";
    }
    echo "</ul>";
}

if (!empty($errors)) {
    echo "<h3 style='color: red;'>× Errors:</h3>";
    echo "<ul>";
    foreach ($errors as $err) {
        echo "<li style='color: red;'>$err</li>";
    }
    echo "</ul>";
}

if (empty($errors)) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 1rem; border-radius: 5px; margin-top: 2rem;'>";
    echo "<h3>✓ Setup Completed Successfully!</h3>";
    echo "<p>All tables for Assignments and Quizzes have been created.</p>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Delete this setup file for security reasons</li>";
    echo "<li>Visit <a href='student/assignments.php'>Student Assignments Page</a></li>";
    echo "<li>Visit <a href='student/quizzes.php'>Student Quizzes Page</a></li>";
    echo "<li>As an instructor, you can now create assignments and quizzes for your courses</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 1rem; border-radius: 5px; margin-top: 2rem;'>";
    echo "<h3>⚠ Setup Completed with Errors</h3>";
    echo "<p>Some tables may not have been created. Please check the error messages above and fix any issues.</p>";
    echo "<p>Common issues:</p>";
    echo "<ul>";
    echo "<li>Foreign key constraints failing - make sure 'courses', 'users', and 'lessons' tables exist</li>";
    echo "<li>Permission errors - make sure your database user has CREATE TABLE privileges</li>";
    echo "<li>Tables already exist - if tables already exist, this is normal</li>";
    echo "</ul>";
    echo "</div>";
}

// Verify tables exist
echo "<hr>";
echo "<h3>Verifying Tables...</h3>";
try {
    $tables = ['assignments', 'assignment_submissions', 'quizzes', 'quiz_questions', 'quiz_answers', 'quiz_attempts', 'quiz_responses'];
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>Table Name</th><th>Status</th><th>Row Count</th></tr>";

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count_stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "<tr><td>$table</td><td style='color: green;'>✓ Exists</td><td>$count rows</td></tr>";
        } else {
            echo "<tr><td>$table</td><td style='color: red;'>× Not Found</td><td>-</td></tr>";
        }
    }
    echo "</table>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error verifying tables: " . $e->getMessage() . "</p>";
}

?>

<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 900px;
        margin: 2rem auto;
        padding: 2rem;
        background: #f5f5f5;
    }

    h1 {
        color: #667eea;
    }

    h3 {
        color: #333;
        margin-top: 1.5rem;
    }

    table {
        width: 100%;
        margin: 1rem 0;
        background: white;
    }

    th {
        background: #667eea;
        color: white;
        text-align: left;
    }
</style>











