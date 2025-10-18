<?php
// Debug script to test view_student_progress functionality
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

echo "<h2>Debug View Student Progress</h2>";

// Get parameters
$student_id = $_GET['student_id'] ?? null;
$course_id = $_GET['course_id'] ?? null;
$instructor_id = $_SESSION['user_id'] ?? null;

echo "<p><strong>Parameters:</strong></p>";
echo "<p>student_id: " . ($student_id ?? 'NULL') . "</p>";
echo "<p>course_id: " . ($course_id ?? 'NULL') . "</p>";
echo "<p>instructor_id: " . ($instructor_id ?? 'NULL') . "</p>";

if (!$student_id || !$course_id) {
    echo "<p style='color: red;'><strong>ERROR:</strong> Missing student_id or course_id</p>";
    exit;
}

try {
    // Test 1: Check if course belongs to instructor
    echo "<h3>Test 1: Course ownership</h3>";
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $instructor_id]);
    $course = $stmt->fetch();

    if ($course) {
        echo "<p style='color: green;'>✓ Course found: " . htmlspecialchars($course['title']) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Course not found or doesn't belong to instructor</p>";

        // Check if course exists at all
        $stmt2 = $pdo->prepare("SELECT id, title, instructor_id FROM courses WHERE id = ?");
        $stmt2->execute([$course_id]);
        $course_check = $stmt2->fetch();
        if ($course_check) {
            echo "<p>Course exists but belongs to instructor_id: " . $course_check['instructor_id'] . " (current instructor: " . $instructor_id . ")</p>";
        } else {
            echo "<p>Course with ID $course_id does not exist at all</p>";
        }
    }

    // Test 2: Check student_courses table
    echo "<h3>Test 2: Student enrollment</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            sc.*,
            u.fullname,
            u.email,
            u.role
        FROM student_courses sc
        JOIN users u ON sc.student_id = u.id
        WHERE sc.student_id = ? AND sc.course_id = ?
    ");
    $stmt->execute([$student_id, $course_id]);
    $enrollment = $stmt->fetch();

    if ($enrollment) {
        echo "<p style='color: green;'>✓ Enrollment found:</p>";
        echo "<ul>";
        echo "<li>Student: " . htmlspecialchars($enrollment['fullname']) . "</li>";
        echo "<li>Email: " . htmlspecialchars($enrollment['email']) . "</li>";
        echo "<li>Role: " . htmlspecialchars($enrollment['role']) . "</li>";
        echo "<li>Progress: " . $enrollment['progress_percent'] . "%</li>";
        echo "<li>Enrolled at: " . $enrollment['enrolled_at'] . "</li>";
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>✗ No enrollment found</p>";

        // Check if student exists
        $stmt2 = $pdo->prepare("SELECT id, fullname, email, role FROM users WHERE id = ?");
        $stmt2->execute([$student_id]);
        $student = $stmt2->fetch();
        if ($student) {
            echo "<p>Student exists: " . htmlspecialchars($student['fullname']) . " (role: " . $student['role'] . ")</p>";
        } else {
            echo "<p>Student with ID $student_id does not exist</p>";
        }

        // Check if there are any enrollments for this course
        $stmt3 = $pdo->prepare("SELECT COUNT(*) as count FROM student_courses WHERE course_id = ?");
        $stmt3->execute([$course_id]);
        $enrollment_count = $stmt3->fetchColumn();
        echo "<p>Total enrollments for this course: $enrollment_count</p>";
    }

    // Test 3: Check the full query from view_student_progress.php
    echo "<h3>Test 3: Full enrollment query</h3>";
    $stmt = $pdo->prepare("
        SELECT 
            u.id as student_id,
            COALESCE(u.fullname, '') as full_name,
            u.email,
            u.phone,
            u.profile_image as avatar,
            u.created_at as joined_date,
            u.last_login,
            u.is_active as user_status,
            sc.progress_percent as progress,
            sc.enrolled_at,
            sc.enrolled_at as last_activity,
            sc.completed_at,
            c.id as course_id,
            c.title as course_title,
            c.description as course_description,
            c.price as course_price
        FROM student_courses sc
        JOIN users u ON sc.student_id = u.id
        JOIN courses c ON sc.course_id = c.id
        WHERE sc.student_id = ? AND sc.course_id = ? AND u.role = 'student'
    ");
    $stmt->execute([$student_id, $course_id]);
    $full_enrollment = $stmt->fetch();

    if ($full_enrollment) {
        echo "<p style='color: green;'>✓ Full enrollment query successful</p>";
        echo "<p>Student: " . htmlspecialchars($full_enrollment['full_name']) . "</p>";
        echo "<p>Course: " . htmlspecialchars($full_enrollment['course_title']) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ Full enrollment query failed</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='students.php'>← Back to Students</a></p>";



