<?php
require_once '../../includes/db.php';
require_once '../../includes/i18n.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get search parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$level_filter = $_GET['level'] ?? '';

// Build query with filters
$where_conditions = ["c.status = 'published'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.category = ?";
    $params[] = $category_filter;
}

if (!empty($level_filter)) {
    $where_conditions[] = "c.level = ?";
    $params[] = $level_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get courses with instructor information and filters
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name AS instructor_name 
        FROM courses c 
        LEFT JOIN users u ON c.instructor_id = u.id 
        WHERE $where_clause
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $courses = [];
}

// Prepare response data
$response = [
    'success' => true,
    'count' => count($courses),
    'filters' => [
        'search' => $search,
        'category' => $category_filter,
        'level' => $level_filter
    ],
    'courses' => array_map(function($course) {
        return [
            'id' => $course['id'],
            'title' => htmlspecialchars($course['title']),
            'description' => htmlspecialchars($course['description']),
            'price' => $course['price'],
            'category' => $course['category'],
            'level' => $course['level'],
            'duration' => $course['duration'],
            'image_url' => $course['image_url'],
            'instructor_name' => htmlspecialchars($course['instructor_name'] ?? 'Instructeur'),
            'created_at' => $course['created_at']
        ];
    }, $courses)
];

// Return JSON response
echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
