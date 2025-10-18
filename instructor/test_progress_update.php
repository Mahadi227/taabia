<?php

/**
 * Test script to debug progress update issues
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

echo "<h2>Progress Update Debug Test</h2>";

try {
    // Test 1: Check student_courses table structure
    echo "<h3>1. Checking student_courses table structure</h3>";
    $stmt = $pdo->query("DESCRIBE student_courses");
    $columns = $stmt->fetchAll();

    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test 2: Check if lesson_progress table exists
    echo "<h3>2. Checking lesson_progress table</h3>";
    try {
        $stmt = $pdo->query("DESCRIBE lesson_progress");
        $columns = $stmt->fetchAll();

        echo "<p style='color: green;'>✓ lesson_progress table exists</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        foreach ($columns as $column) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($column['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ lesson_progress table does not exist: " . htmlspecialchars($e->getMessage()) . "</p>";
    }

    // Test 3: Check sample data
    echo "<h3>3. Checking sample student_courses data</h3>";
    $stmt = $pdo->query("SELECT * FROM student_courses LIMIT 3");
    $samples = $stmt->fetchAll();

    if (empty($samples)) {
        echo "<p style='color: orange;'>⚠ No data in student_courses table</p>";
    } else {
        echo "<p style='color: green;'>✓ Found " . count($samples) . " records in student_courses</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        if (!empty($samples)) {
            echo "<tr>";
            foreach (array_keys($samples[0]) as $key) {
                echo "<th>" . htmlspecialchars($key) . "</th>";
            }
            echo "</tr>";
            foreach ($samples as $sample) {
                echo "<tr>";
                foreach ($sample as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
        }
        echo "</table>";
    }

    // Test 4: Test update query
    echo "<h3>4. Testing update query</h3>";
    if (!empty($samples)) {
        $test_student_id = $samples[0]['student_id'];
        $test_course_id = $samples[0]['course_id'];

        echo "<p>Testing with student_id: $test_student_id, course_id: $test_course_id</p>";

        try {
            // Test simple update first
            $stmt = $pdo->prepare("UPDATE student_courses SET progress_percent = ? WHERE student_id = ? AND course_id = ?");
            $stmt->execute([50, $test_student_id, $test_course_id]);
            echo "<p style='color: green;'>✓ Basic update works</p>";

            // Test with completed_at
            try {
                $stmt = $pdo->prepare("UPDATE student_courses SET progress_percent = ?, completed_at = ? WHERE student_id = ? AND course_id = ?");
                $stmt->execute([60, date('Y-m-d H:i:s'), $test_student_id, $test_course_id]);
                echo "<p style='color: green;'>✓ Update with completed_at works</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Update with completed_at failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            }

            // Test with status column
            try {
                $stmt = $pdo->prepare("UPDATE student_courses SET progress_percent = ?, status = ? WHERE student_id = ? AND course_id = ?");
                $stmt->execute([70, 'active', $test_student_id, $test_course_id]);
                echo "<p style='color: green;'>✓ Update with status works</p>";
            } catch (PDOException $e) {
                echo "<p style='color: red;'>✗ Update with status failed: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } catch (PDOException $e) {
            echo "<p style='color: red;'>✗ Basic update failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='students.php'>← Back to Students</a></p>";



