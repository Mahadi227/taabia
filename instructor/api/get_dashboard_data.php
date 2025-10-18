<?php

/**
 * API endpoint for instructor dashboard data
 * Provides real-time earnings and transactions data
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('instructor');

// Set JSON header
header('Content-Type: application/json');

$instructor_id = $_SESSION['user_id'];
$response = ['success' => false, 'data' => null, 'error' => null];

try {
    // Get earnings data for the last 12 months
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, 
               SUM(amount) as total,
               COUNT(*) as count
        FROM transactions
        WHERE instructor_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute([$instructor_id]);
    $monthly_earnings = $stmt->fetchAll();

    // Fill missing months with zero values
    $complete_earnings = [];
    $current_date = new DateTime();
    for ($i = 11; $i >= 0; $i--) {
        $month = $current_date->format('Y-m');
        $found = false;

        foreach ($monthly_earnings as $earning) {
            if ($earning['month'] === $month) {
                $complete_earnings[] = $earning;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $complete_earnings[] = [
                'month' => $month,
                'total' => 0,
                'count' => 0
            ];
        }

        $current_date->modify('-1 month');
    }

    // Get recent transactions (last 10)
    $stmt = $pdo->prepare("
        SELECT t.id, t.type, t.amount, t.created_at,
               u.full_name AS buyer_name,
               c.title as course_title,
               CASE 
                   WHEN t.type = 'course' THEN 'course'
                   ELSE 'product'
               END as transaction_type
        FROM transactions t
        JOIN users u ON t.student_id = u.id
        LEFT JOIN courses c ON t.course_id = c.id
        WHERE t.instructor_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$instructor_id]);
    $recent_transactions = $stmt->fetchAll();

    // Get current statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT t.id) as total_transactions,
            SUM(t.amount) as total_earnings,
            COUNT(DISTINCT c.id) as total_courses,
            COUNT(DISTINCT sc.student_id) as total_students
        FROM transactions t
        LEFT JOIN courses c ON t.course_id = c.id AND c.instructor_id = ?
        LEFT JOIN student_courses sc ON c.id = sc.course_id
        WHERE t.instructor_id = ?
    ");
    $stmt->execute([$instructor_id, $instructor_id]);
    $stats = $stmt->fetch();

    // Get today's earnings
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as today_earnings, COUNT(*) as today_transactions
        FROM transactions 
        WHERE instructor_id = ? 
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$instructor_id]);
    $today_stats = $stmt->fetch();

    // Get this week's earnings
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as week_earnings, COUNT(*) as week_transactions
        FROM transactions 
        WHERE instructor_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->execute([$instructor_id]);
    $week_stats = $stmt->fetch();

    // Get this month's earnings
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as month_earnings, COUNT(*) as month_transactions
        FROM transactions 
        WHERE instructor_id = ? 
        AND YEAR(created_at) = YEAR(NOW()) 
        AND MONTH(created_at) = MONTH(NOW())
    ");
    $stmt->execute([$instructor_id]);
    $month_stats = $stmt->fetch();

    $response = [
        'success' => true,
        'data' => [
            'monthly_earnings' => array_reverse($complete_earnings),
            'recent_transactions' => $recent_transactions,
            'statistics' => [
                'total_transactions' => $stats['total_transactions'] ?? 0,
                'total_earnings' => $stats['total_earnings'] ?? 0,
                'total_courses' => $stats['total_courses'] ?? 0,
                'total_students' => $stats['total_students'] ?? 0,
                'today_earnings' => $today_stats['today_earnings'] ?? 0,
                'today_transactions' => $today_stats['today_transactions'] ?? 0,
                'week_earnings' => $week_stats['week_earnings'] ?? 0,
                'week_transactions' => $week_stats['week_transactions'] ?? 0,
                'month_earnings' => $month_stats['month_earnings'] ?? 0,
                'month_transactions' => $month_stats['month_transactions'] ?? 0
            ]
        ],
        'timestamp' => time()
    ];
} catch (PDOException $e) {
    $response['error'] = 'Database error: ' . $e->getMessage();
    error_log("Dashboard API error: " . $e->getMessage());
} catch (Exception $e) {
    $response['error'] = 'Server error: ' . $e->getMessage();
    error_log("Dashboard API error: " . $e->getMessage());
}

echo json_encode($response);
