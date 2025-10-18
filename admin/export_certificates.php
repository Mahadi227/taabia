<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

// Get filter parameters (same as certificates.php)
$search = $_GET['search'] ?? '';
$student_filter = $_GET['student'] ?? '';
$course_filter = $_GET['course'] ?? '';
$instructor_filter = $_GET['instructor'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$grade_min = $_GET['grade_min'] ?? '';
$grade_max = $_GET['grade_max'] ?? '';
$format = $_GET['format'] ?? 'csv';

// Build WHERE clause (same logic as certificates.php)
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(cc.student_name LIKE ? OR cc.course_title LIKE ? OR cc.instructor_name LIKE ? OR cc.certificate_number LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($student_filter)) {
    $where_conditions[] = "cc.student_name LIKE ?";
    $params[] = "%$student_filter%";
}

if (!empty($course_filter)) {
    $where_conditions[] = "cc.course_title LIKE ?";
    $params[] = "%$course_filter%";
}

if (!empty($instructor_filter)) {
    $where_conditions[] = "cc.instructor_name LIKE ?";
    $params[] = "%$instructor_filter%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(cc.issue_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(cc.issue_date) <= ?";
    $params[] = $date_to;
}

if (!empty($grade_min)) {
    $where_conditions[] = "cc.final_grade >= ?";
    $params[] = $grade_min;
}

if (!empty($grade_max)) {
    $where_conditions[] = "cc.final_grade <= ?";
    $params[] = $grade_max;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get certificates data
$certificates_query = "
    SELECT cc.*, 
           u.email as student_email,
           c.title as current_course_title,
           c.description as course_description,
           i.full_name as current_instructor_name,
           i.email as instructor_email,
           COUNT(DISTINCT cv.id) as verification_count,
           COUNT(DISTINCT cs.id) as share_count
    FROM course_certificates cc
    LEFT JOIN users u ON cc.student_id = u.id
    LEFT JOIN courses c ON cc.course_id = c.id
    LEFT JOIN users i ON c.instructor_id = i.id
    LEFT JOIN certificate_verifications cv ON cc.id = cv.certificate_id
    LEFT JOIN certificate_shares cs ON cc.id = cs.certificate_id
    $where_clause
    GROUP BY cc.id
    ORDER BY cc.issue_date DESC
";

$certificates_stmt = $pdo->prepare($certificates_query);
$certificates_stmt->execute($params);
$certificates = $certificates_stmt->fetchAll();

// Clear any previous output
ob_end_clean();

if ($format === 'csv') {
    // Export as CSV
    $filename = 'certificates_export_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // CSV headers
    $headers = [
        'Certificate Number',
        'Student Name',
        'Student Email',
        'Course Title',
        'Course Description',
        'Instructor Name',
        'Instructor Email',
        'Completion Date',
        'Issue Date',
        'Final Grade (%)',
        'Certificate Template',
        'Certificate URL',
        'Verification Code',
        'Verification Count',
        'Share Count',
        'Is Verified'
    ];

    fputcsv($output, $headers);

    // CSV data
    foreach ($certificates as $cert) {
        $row = [
            $cert['certificate_number'],
            $cert['student_name'],
            $cert['student_email'],
            $cert['course_title'],
            $cert['course_description'],
            $cert['instructor_name'],
            $cert['instructor_email'],
            $cert['completion_date'],
            $cert['issue_date'],
            $cert['final_grade'],
            $cert['certificate_template'],
            $cert['certificate_url'],
            $cert['verification_code'],
            $cert['verification_count'],
            $cert['share_count'],
            $cert['is_verified'] ? 'Yes' : 'No'
        ];

        fputcsv($output, $row);
    }

    fclose($output);
} elseif ($format === 'excel') {
    // Export as Excel (using simple HTML table that Excel can open)
    $filename = 'certificates_export_' . date('Y-m-d_H-i-s') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<table border="1">';

    // Headers
    echo '<tr>';
    echo '<th>Certificate Number</th>';
    echo '<th>Student Name</th>';
    echo '<th>Student Email</th>';
    echo '<th>Course Title</th>';
    echo '<th>Course Description</th>';
    echo '<th>Instructor Name</th>';
    echo '<th>Instructor Email</th>';
    echo '<th>Completion Date</th>';
    echo '<th>Issue Date</th>';
    echo '<th>Final Grade (%)</th>';
    echo '<th>Certificate Template</th>';
    echo '<th>Certificate URL</th>';
    echo '<th>Verification Code</th>';
    echo '<th>Verification Count</th>';
    echo '<th>Share Count</th>';
    echo '<th>Is Verified</th>';
    echo '</tr>';

    // Data rows
    foreach ($certificates as $cert) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($cert['certificate_number']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['student_name']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['student_email']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['course_title']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['course_description']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['instructor_name']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['instructor_email']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['completion_date']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['issue_date']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['final_grade']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['certificate_template']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['certificate_url']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['verification_code']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['verification_count']) . '</td>';
        echo '<td>' . htmlspecialchars($cert['share_count']) . '</td>';
        echo '<td>' . ($cert['is_verified'] ? 'Yes' : 'No') . '</td>';
        echo '</tr>';
    }

    echo '</table></body></html>';
} elseif ($format === 'json') {
    // Export as JSON
    $filename = 'certificates_export_' . date('Y-m-d_H-i-s') . '.json';

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Prepare data for JSON export
    $export_data = [
        'export_info' => [
            'exported_at' => date('Y-m-d H:i:s'),
            'total_records' => count($certificates),
            'filters_applied' => [
                'search' => $search,
                'student' => $student_filter,
                'course' => $course_filter,
                'instructor' => $instructor_filter,
                'date_from' => $date_from,
                'date_to' => $date_to,
                'grade_min' => $grade_min,
                'grade_max' => $grade_max
            ]
        ],
        'certificates' => $certificates
    ];

    echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    // Invalid format
    header('Location: certificates.php?error=invalid_format');
    exit();
}

exit();







