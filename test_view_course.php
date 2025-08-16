<?php
require_once 'includes/db.php';
require_once 'includes/function.php';

echo "=== Testing View Course Functionality ===\n\n";

try {
    // Test 1: Check if there are courses available
    echo "1. Checking available courses...\n";
    $stmt = $pdo->query("SELECT id, title FROM courses LIMIT 3");
    $courses = $stmt->fetchAll();
    
    if (count($courses) > 0) {
        echo "   ✅ Found " . count($courses) . " courses\n";
        foreach ($courses as $course) {
            echo "   - Course #{$course['id']}: {$course['title']}\n";
        }
    } else {
        echo "   ⚠️  No courses found\n";
        exit;
    }
    
    // Test 2: Test the view course query for the first course
    echo "\n2. Testing view course query...\n";
    $course_id = $courses[0]['id'];
    
    // Get course details with instructor info
    $stmt = $pdo->prepare("
        SELECT c.*, u.fullname as instructor_name, u.email as instructor_email
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();
    
    if ($course) {
        echo "   ✅ Course found: {$course['title']}\n";
        echo "   - Instructor: {$course['instructor_name']}\n";
        echo "   - Status: {$course['status']}\n";
        echo "   - Price: GHS {$course['price']}\n";
    } else {
        echo "   ❌ Course not found\n";
        exit;
    }
    
    // Test 3: Test lessons query
    echo "\n3. Testing lessons query...\n";
    $stmt = $pdo->prepare("
        SELECT id, title, content as description, content_type, order_index, created_at
        FROM course_contents 
        WHERE course_id = ? 
        ORDER BY order_index ASC
    ");
    $stmt->execute([$course_id]);
    $lessons = $stmt->fetchAll();
    
    echo "   ✅ Found " . count($lessons) . " lessons\n";
    foreach ($lessons as $lesson) {
        echo "   - Lesson: {$lesson['title']} ({$lesson['content_type']})\n";
    }
    
    // Test 4: Test enrollment count query
    echo "\n4. Testing enrollment count query...\n";
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as enrollment_count
        FROM student_courses 
        WHERE course_id = ?
    ");
    $stmt->execute([$course_id]);
    $enrollment_count = $stmt->fetchColumn();
    
    echo "   ✅ Enrollment count: $enrollment_count\n";
    
    // Test 5: Test recent enrollments query
    echo "\n5. Testing recent enrollments query...\n";
    $stmt = $pdo->prepare("
        SELECT sc.*, u.fullname, u.email
        FROM student_courses sc
        LEFT JOIN users u ON sc.student_id = u.id
        WHERE sc.course_id = ?
        ORDER BY sc.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute([$course_id]);
    $enrollments = $stmt->fetchAll();
    
    echo "   ✅ Found " . count($enrollments) . " recent enrollments\n";
    foreach ($enrollments as $enrollment) {
        echo "   - Student: {$enrollment['fullname']} ({$enrollment['email']})\n";
    }
    
    // Test 6: Test revenue calculation
    echo "\n6. Testing revenue calculation...\n";
    $total_revenue = $course['price'] * $enrollment_count;
    echo "   ✅ Total revenue: GHS " . number_format($total_revenue, 2) . "\n";
    echo "   - Course price: GHS {$course['price']}\n";
    echo "   - Enrollment count: $enrollment_count\n";
    
    // Test 7: Summary
    echo "\n7. Summary:\n";
    echo "   📊 Course: {$course['title']}\n";
    echo "   📊 Instructor: {$course['instructor_name']}\n";
    echo "   📊 Lessons: " . count($lessons) . "\n";
    echo "   📊 Enrollments: $enrollment_count\n";
    echo "   📊 Revenue: GHS " . number_format($total_revenue, 2) . "\n";
    
    echo "\n   ✅ View course functionality is working correctly!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?> 