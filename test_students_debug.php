<?php

/**
 * Debug script to check students data in database
 */

require_once 'includes/db.php';

echo "<h1>🔍 Students Database Debug</h1>";
echo "<style>body{font-family:monospace; margin:20px;} .section{background:#f8f9fa; padding:15px; margin:10px 0; border-radius:5px; border-left:4px solid #007bff;} .error{color:red;} .success{color:green;} .info{color:blue;}</style>";

try {
    // Check database connection
    echo "<div class='section'>";
    echo "<h2>📊 Database Connection</h2>";
    echo "<span class='success'>✅ Connected to database successfully</span><br>";
    echo "Database: " . $pdo->query("SELECT DATABASE()")->fetchColumn() . "<br>";
    echo "</div>";

    // Check all tables
    echo "<div class='section'>";
    echo "<h2>🗄️ Available Tables</h2>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
    echo "</div>";

    // Check users table
    echo "<div class='section'>";
    echo "<h2>👥 Users Table</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
    $total_users = $stmt->fetchColumn();
    echo "Total users: $total_users<br>";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'student'");
    $total_students = $stmt->fetchColumn();
    echo "Total students: $total_students<br>";

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'instructor'");
    $total_instructors = $stmt->fetchColumn();
    echo "Total instructors: $total_instructors<br>";

    if ($total_students > 0) {
        echo "<br><strong>Sample students:</strong><br>";
        $stmt = $pdo->query("SELECT id, fullname, email, role, created_at FROM users WHERE role = 'student' LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "- ID: {$row['id']}, Name: {$row['fullname']}, Email: {$row['email']}, Created: {$row['created_at']}<br>";
        }
    }
    echo "</div>";

    // Check courses table
    echo "<div class='section'>";
    echo "<h2>📚 Courses Table</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM courses");
    $total_courses = $stmt->fetchColumn();
    echo "Total courses: $total_courses<br>";

    if ($total_courses > 0) {
        echo "<br><strong>Sample courses:</strong><br>";
        $stmt = $pdo->query("SELECT id, title, instructor_id, status, created_at FROM courses LIMIT 5");
        while ($row = $stmt->fetch()) {
            echo "- ID: {$row['id']}, Title: {$row['title']}, Instructor: {$row['instructor_id']}, Status: {$row['status']}<br>";
        }
    }
    echo "</div>";

    // Check student_courses table
    echo "<div class='section'>";
    echo "<h2>🎓 Student Courses Table</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_courses'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM student_courses");
        $total_enrollments = $stmt->fetchColumn();
        echo "Total enrollments: $total_enrollments<br>";

        if ($total_enrollments > 0) {
            echo "<br><strong>Sample enrollments:</strong><br>";
            $stmt = $pdo->query("SELECT student_id, course_id, enrolled_at, progress_percent FROM student_courses LIMIT 5");
            while ($row = $stmt->fetch()) {
                echo "- Student: {$row['student_id']}, Course: {$row['course_id']}, Progress: {$row['progress_percent']}%, Enrolled: {$row['enrolled_at']}<br>";
            }
        }
    } else {
        echo "<span class='error'>❌ student_courses table does not exist!</span><br>";
    }
    echo "</div>";

    // Check transactions table
    echo "<div class='section'>";
    echo "<h2>💳 Transactions Table</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'transactions'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions");
        $total_transactions = $stmt->fetchColumn();
        echo "Total transactions: $total_transactions<br>";

        $stmt = $pdo->query("SELECT COUNT(*) as total FROM transactions WHERE status = 'completed'");
        $completed_transactions = $stmt->fetchColumn();
        echo "Completed transactions: $completed_transactions<br>";

        if ($completed_transactions > 0) {
            echo "<br><strong>Sample completed transactions:</strong><br>";
            $stmt = $pdo->query("SELECT buyer_id, course_id, status, created_at FROM transactions WHERE status = 'completed' LIMIT 5");
            while ($row = $stmt->fetch()) {
                echo "- Buyer: {$row['buyer_id']}, Course: {$row['course_id']}, Status: {$row['status']}, Date: {$row['created_at']}<br>";
            }
        }
    } else {
        echo "<span class='error'>❌ transactions table does not exist!</span><br>";
    }
    echo "</div>";

    // Test specific instructor (assuming instructor ID 1)
    echo "<div class='section'>";
    echo "<h2>👨‍🏫 Test for Instructor ID 1</h2>";

    // Check if instructor exists
    $stmt = $pdo->prepare("SELECT id, fullname, email, role FROM users WHERE id = 1 AND role = 'instructor'");
    $stmt->execute();
    $instructor = $stmt->fetch();

    if ($instructor) {
        echo "<span class='success'>✅ Instructor found: {$instructor['fullname']} ({$instructor['email']})</span><br>";

        // Check instructor's courses
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE instructor_id = 1");
        $stmt->execute();
        $instructor_courses = $stmt->fetchColumn();
        echo "Instructor's courses: $instructor_courses<br>";

        if ($instructor_courses > 0) {
            echo "<br><strong>Instructor's courses:</strong><br>";
            $stmt = $pdo->prepare("SELECT id, title, status FROM courses WHERE instructor_id = 1");
            $stmt->execute();
            while ($row = $stmt->fetch()) {
                echo "- ID: {$row['id']}, Title: {$row['title']}, Status: {$row['status']}<br>";
            }
        }

        // Check students enrolled in instructor's courses
        if ($instructor_courses > 0) {
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT sc.student_id) as student_count
                FROM student_courses sc
                JOIN courses c ON sc.course_id = c.id
                WHERE c.instructor_id = 1
            ");
            $stmt->execute();
            $enrolled_students = $stmt->fetchColumn();
            echo "<br>Students enrolled in instructor's courses: $enrolled_students<br>";

            if ($enrolled_students > 0) {
                echo "<br><strong>Enrolled students:</strong><br>";
                $stmt = $pdo->prepare("
                    SELECT DISTINCT u.id, u.fullname, u.email, c.title as course_title
                    FROM student_courses sc
                    JOIN users u ON sc.student_id = u.id
                    JOIN courses c ON sc.course_id = c.id
                    WHERE c.instructor_id = 1 AND u.role = 'student'
                    LIMIT 10
                ");
                $stmt->execute();
                while ($row = $stmt->fetch()) {
                    echo "- Student: {$row['fullname']} ({$row['email']}) in course: {$row['course_title']}<br>";
                }
            }
        }
    } else {
        echo "<span class='error'>❌ No instructor found with ID 1</span><br>";

        // Show all instructors
        echo "<br><strong>Available instructors:</strong><br>";
        $stmt = $pdo->query("SELECT id, fullname, email FROM users WHERE role = 'instructor'");
        while ($row = $stmt->fetch()) {
            echo "- ID: {$row['id']}, Name: {$row['fullname']}, Email: {$row['email']}<br>";
        }
    }
    echo "</div>";
} catch (PDOException $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>❌ Database Error</h2>";
    echo "Error: " . $e->getMessage();
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>🔧 Next Steps</h2>";
echo "1. Check if you have students in the users table with role = 'student'<br>";
echo "2. Check if you have courses with instructor_id matching your instructor<br>";
echo "3. Check if you have enrollments in student_courses table<br>";
echo "4. If no student_courses table, check transactions table for course purchases<br>";
echo "</div>";
