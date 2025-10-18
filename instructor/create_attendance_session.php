<?php

/**
 * Create Attendance Session Page - Professional LMS Version
 * 
 * Advanced session creation with modern UI, validation, and comprehensive features
 */

// ============================================================================
// INITIALIZATION & SECURITY
// ============================================================================

ob_start();
require_once '../includes/language_handler.php';
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

require_role('instructor');
$instructor_id = $_SESSION['user_id'];

// ============================================================================
// INPUT VALIDATION & PROCESSING
// ============================================================================

/**
 * Validate and sanitize input parameters
 */
function validateInputs()
{
    return [
        'course_id' => (int)($_POST['course_id'] ?? 0),
        'lesson_id' => (int)($_POST['lesson_id'] ?? 0) ?: null,
        'session_title' => trim($_POST['session_title'] ?? ''),
        'session_date' => $_POST['session_date'] ?? '',
        'start_time' => $_POST['start_time'] ?? '',
        'end_time' => $_POST['end_time'] ?? '',
        'session_type' => in_array($_POST['session_type'] ?? '', ['lesson', 'quiz', 'assignment', 'meeting', 'exam', 'workshop', 'other']) ? $_POST['session_type'] : 'lesson',
        'description' => trim($_POST['description'] ?? ''),
        'location' => trim($_POST['location'] ?? ''),
        'max_students' => (int)($_POST['max_students'] ?? 0) ?: null,
        'is_mandatory' => isset($_POST['is_mandatory']) ? 1 : 0,
        'auto_mark_absent' => isset($_POST['auto_mark_absent']) ? 1 : 0,
        'reminder_enabled' => isset($_POST['reminder_enabled']) ? 1 : 0,
        'reminder_time' => (int)($_POST['reminder_time'] ?? 30)
    ];
}

/**
 * Handle session creation
 */
function createAttendanceSession($pdo, $inputs, $instructor_id)
{
    try {
        // Verify course belongs to instructor
        $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE id = ? AND instructor_id = ?");
        $stmt->execute([$inputs['course_id'], $instructor_id]);
        $course = $stmt->fetch();

        if (!$course) {
            return ['success' => false, 'message' => __('invalid_course_selected')];
        }

        // Validate required fields
        if (empty($inputs['session_title'])) {
            return ['success' => false, 'message' => __('session_title_required')];
        }

        if (strlen($inputs['session_title']) < 3) {
            return ['success' => false, 'message' => __('session_title_too_short')];
        }

        if (empty($inputs['session_date'])) {
            return ['success' => false, 'message' => __('session_date_required')];
        }

        // Validate date is not in the past
        if (strtotime($inputs['session_date']) < strtotime(date('Y-m-d'))) {
            return ['success' => false, 'message' => __('session_date_cannot_be_past')];
        }

        // Validate time range if both times are provided
        if (!empty($inputs['start_time']) && !empty($inputs['end_time'])) {
            $start_datetime = strtotime($inputs['session_date'] . ' ' . $inputs['start_time']);
            $end_datetime = strtotime($inputs['session_date'] . ' ' . $inputs['end_time']);

            if ($end_datetime <= $start_datetime) {
                return ['success' => false, 'message' => __('end_time_must_be_after_start_time')];
            }
        }

        // Check for duplicate session on same date and time
        $stmt = $pdo->prepare("
            SELECT id FROM attendance_sessions 
            WHERE course_id = ? AND session_date = ? AND start_time = ? AND is_active = 1
        ");
        $stmt->execute([$inputs['course_id'], $inputs['session_date'], $inputs['start_time']]);
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => __('duplicate_session_exists')];
        }

        // Insert attendance session
        $stmt = $pdo->prepare("
            INSERT INTO attendance_sessions (
                course_id, lesson_id, session_title, session_date, start_time, end_time, 
                session_type, description, location, max_students, is_mandatory, 
                auto_mark_absent, reminder_enabled, reminder_time, instructor_id, 
                is_active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");

        $stmt->execute([
            $inputs['course_id'],
            $inputs['lesson_id'],
            $inputs['session_title'],
            $inputs['session_date'],
            $inputs['start_time'],
            $inputs['end_time'],
            $inputs['session_type'],
            $inputs['description'],
            $inputs['location'],
            $inputs['max_students'],
            $inputs['is_mandatory'],
            $inputs['auto_mark_absent'],
            $inputs['reminder_enabled'],
            $inputs['reminder_time'],
            $instructor_id
        ]);

        $session_id = $pdo->lastInsertId();

        // Auto-create attendance records for all enrolled students if auto_mark_absent is enabled
        if ($inputs['auto_mark_absent']) {
            $stmt = $pdo->prepare("
                INSERT INTO student_attendance (session_id, student_id, attendance_status, recorded_by, created_at)
                SELECT ?, sc.student_id, 'absent', ?, NOW()
                FROM student_courses sc
                WHERE sc.course_id = ?
            ");
            $stmt->execute([$session_id, $instructor_id, $inputs['course_id']]);
        }

        return [
            'success' => true,
            'message' => __('attendance_session_created_successfully'),
            'session_id' => $session_id,
            'course_title' => $course['title']
        ];
    } catch (PDOException $e) {
        error_log("Error creating attendance session: " . $e->getMessage());
        return ['success' => false, 'message' => __('error_creating_session')];
    }
}

// Process form submission
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputs = validateInputs();
    $result = createAttendanceSession($pdo, $inputs, $instructor_id);
}

// ============================================================================
// DATA FETCHING FUNCTIONS
// ============================================================================

/**
 * Fetch instructor's courses with enrollment data
 */
function fetchInstructorCourses($pdo, $instructor_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                c.id, 
                c.title, 
                c.status,
                c.image_url,
                COUNT(DISTINCT sc.student_id) as enrollment_count,
                COUNT(DISTINCT as2.id) as session_count,
                c.created_at
            FROM courses c
            LEFT JOIN student_courses sc ON c.id = sc.course_id
            LEFT JOIN attendance_sessions as2 ON c.id = as2.course_id AND as2.is_active = 1
            WHERE c.instructor_id = ? AND c.status = 'published'
            GROUP BY c.id, c.title, c.status, c.image_url, c.created_at
            ORDER BY c.title ASC
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching courses: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch lessons for a specific course
 */
function fetchCourseLessons($pdo, $course_id)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                title, 
                lesson_order,
                duration,
                status
            FROM lessons 
            WHERE course_id = ? AND status = 'active'
            ORDER BY lesson_order ASC, title ASC
        ");
        $stmt->execute([$course_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching lessons: " . $e->getMessage());
        return [];
    }
}

/**
 * Fetch recent sessions for context
 */
function fetchRecentSessions($pdo, $instructor_id, $limit = 5)
{
    try {
        $stmt = $pdo->prepare("
            SELECT 
                as2.id,
                as2.session_title,
                as2.session_date,
                as2.start_time,
                as2.session_type,
                c.title as course_title,
                COUNT(sa.id) as attendance_count,
                COUNT(CASE WHEN sa.attendance_status IN ('present', 'late') THEN 1 END) as present_count
            FROM attendance_sessions as2
            JOIN courses c ON as2.course_id = c.id
            LEFT JOIN student_attendance sa ON as2.id = sa.session_id
            WHERE as2.instructor_id = ?
            GROUP BY as2.id
            ORDER BY as2.session_date DESC, as2.start_time DESC
            LIMIT ?
        ");
        $stmt->execute([$instructor_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching recent sessions: " . $e->getMessage());
        return [];
    }
}

// ============================================================================
// DATA RETRIEVAL
// ============================================================================

$courses = fetchInstructorCourses($pdo, $instructor_id);
$lessons = [];
$recent_sessions = fetchRecentSessions($pdo, $instructor_id);

// Get lessons for selected course
if (isset($_GET['course_id']) || isset($_POST['course_id'])) {
    $course_id = $_GET['course_id'] ?? $_POST['course_id'];
    $lessons = fetchCourseLessons($pdo, $course_id);
}

?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('create_attendance_session') ?> | TaaBia</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">

    <style>
        /* Professional Modern Design */
        .instructor-main {
            margin-left: 280px;
            padding: var(--spacing-8);
            background-color: var(--gray-50);
            min-height: 100vh;
        }

        @media (max-width: 1024px) {
            .instructor-main {
                margin-left: 0;
                padding: var(--spacing-4);
            }
        }

        /* Header Section */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-info h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 0 0.5rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-info p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: white;
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
            box-shadow: var(--shadow);
        }

        .alert-error {
            background: white;
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
            box-shadow: var(--shadow);
        }

        /* Form Section */
        .form-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .form-header {
            background: var(--gray-50);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .form-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .form-content {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label.required::after {
            content: '*';
            color: var(--danger-color);
            margin-left: 0.25rem;
        }

        .form-input {
            padding: 0.875rem 1rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            transition: all 0.2s ease;
            font-family: 'Inter', sans-serif;
            background: white;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }

        .form-input.error {
            border-color: var(--danger-color);
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='m6 8 4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: var(--radius-md);
            border: 2px solid var(--gray-200);
            transition: all 0.2s ease;
        }

        .form-checkbox:hover {
            background: var(--gray-100);
        }

        .form-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .form-checkbox label {
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            margin: 0;
        }

        .form-help {
            font-size: 0.85rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .form-error {
            font-size: 0.85rem;
            color: var(--danger-color);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Course Cards */
        .courses-section {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .courses-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .courses-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .course-card {
            background: var(--gray-50);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .course-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .course-card.selected {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .course-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .course-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0;
        }

        .course-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .course-status.published {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .course-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .course-stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .course-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Recent Sessions */
        .recent-sessions {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .recent-sessions-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .recent-sessions-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-900);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .recent-sessions-list {
            padding: 1.5rem 2rem;
        }

        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }

        .session-item:hover {
            background: var(--gray-50);
        }

        .session-info h4 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin: 0 0 0.25rem 0;
        }

        .session-info p {
            font-size: 0.85rem;
            color: var(--gray-600);
            margin: 0;
        }

        .session-stats {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .session-stat {
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
            justify-content: flex-end;
            padding: 2rem;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-200);
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: var(--success-dark);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .courses-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
    <div class="instructor-layout">
        <!-- Sidebar -->
        <?php include '../includes/instructor_sidebar.php'; ?>

        <!-- Main Content -->
        <div class="instructor-main">
            <!-- Alert Messages -->
            <?php if ($result): ?>
                <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>">
                    <i class="fas fa-<?= $result['success'] ? 'check-circle' : 'exclamation-circle' ?>" style="font-size: 1.5rem;"></i>
                    <strong><?= $result['message'] ?></strong>
                    <?php if ($result['success'] && isset($result['session_id'])): ?>
                        <div style="margin-top: 0.5rem; font-size: 0.9rem;">
                            <a href="attendance_management.php" style="color: inherit; text-decoration: underline;">
                                <?= __('view_attendance_management') ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <header class="page-header">
                <div class="header-content">
                    <div class="header-info">
                        <h1>
                            <i class="fas fa-plus-circle"></i>
                            <?= __('create_attendance_session') ?>
                        </h1>
                        <p><?= __('create_new_attendance_session_for_students') ?></p>
                    </div>
                    <div class="header-actions">
                        <?php include '../includes/instructor_language_switcher.php'; ?>
                        <a href="attendance_management.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?= __('back_to_management') ?>
                        </a>
                    </div>
                </div>
            </header>

            <!-- Session Creation Form -->
            <section class="form-section">
                <div class="form-header">
                    <h3 class="form-title">
                        <i class="fas fa-calendar-plus"></i>
                        <?= __('session_details') ?>
                    </h3>
                </div>

                <form method="POST" id="sessionForm" class="form-content">
                    <!-- Basic Information -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="course_id" class="form-label required">
                                <i class="fas fa-book"></i>
                                <?= __('course') ?>
                            </label>
                            <select name="course_id" id="course_id" required class="form-input form-select">
                                <option value=""><?= __('select_course') ?></option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?= $course['id'] ?>"
                                        <?= (isset($_POST['course_id']) && $_POST['course_id'] == $course['id']) || (isset($_GET['course_id']) && $_GET['course_id'] == $course['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($course['title']) ?>
                                        (<?= $course['enrollment_count'] ?> <?= __('students') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-help"><?= __('select_course_for_session') ?></div>
                        </div>

                        <div class="form-group">
                            <label for="session_type" class="form-label">
                                <i class="fas fa-tag"></i>
                                <?= __('session_type') ?>
                            </label>
                            <select name="session_type" id="session_type" class="form-input form-select">
                                <option value="lesson" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'lesson') ? 'selected' : '' ?>><?= __('lesson') ?></option>
                                <option value="quiz" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'quiz') ? 'selected' : '' ?>><?= __('quiz') ?></option>
                                <option value="assignment" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'assignment') ? 'selected' : '' ?>><?= __('assignment') ?></option>
                                <option value="exam" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'exam') ? 'selected' : '' ?>><?= __('exam') ?></option>
                                <option value="workshop" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'workshop') ? 'selected' : '' ?>><?= __('workshop') ?></option>
                                <option value="meeting" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'meeting') ? 'selected' : '' ?>><?= __('meeting') ?></option>
                                <option value="other" <?= (isset($_POST['session_type']) && $_POST['session_type'] == 'other') ? 'selected' : '' ?>><?= __('other') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="session_title" class="form-label required">
                            <i class="fas fa-heading"></i>
                            <?= __('session_title') ?>
                        </label>
                        <input type="text" name="session_title" id="session_title" required
                            class="form-input"
                            placeholder="<?= __('enter_session_title') ?>"
                            value="<?= htmlspecialchars($_POST['session_title'] ?? '') ?>">
                        <div class="form-help"><?= __('session_title_help') ?></div>
                    </div>

                    <!-- Date and Time -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="session_date" class="form-label required">
                                <i class="fas fa-calendar"></i>
                                <?= __('session_date') ?>
                            </label>
                            <input type="date" name="session_date" id="session_date" required
                                class="form-input"
                                value="<?= $_POST['session_date'] ?? date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label for="start_time" class="form-label">
                                <i class="fas fa-clock"></i>
                                <?= __('start_time') ?>
                            </label>
                            <input type="time" name="start_time" id="start_time"
                                class="form-input"
                                value="<?= $_POST['start_time'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="end_time" class="form-label">
                                <i class="fas fa-clock"></i>
                                <?= __('end_time') ?>
                            </label>
                            <input type="time" name="end_time" id="end_time"
                                class="form-input"
                                value="<?= $_POST['end_time'] ?? '' ?>">
                        </div>
                    </div>

                    <!-- Additional Information -->
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="lesson_id" class="form-label">
                                <i class="fas fa-play-circle"></i>
                                <?= __('associated_lesson') ?>
                            </label>
                            <select name="lesson_id" id="lesson_id" class="form-input form-select">
                                <option value=""><?= __('no_specific_lesson') ?></option>
                                <?php foreach ($lessons as $lesson): ?>
                                    <option value="<?= $lesson['id'] ?>" <?= (isset($_POST['lesson_id']) && $_POST['lesson_id'] == $lesson['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lesson['title']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="location" class="form-label">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= __('location') ?>
                            </label>
                            <input type="text" name="location" id="location"
                                class="form-input"
                                placeholder="<?= __('enter_location_optional') ?>"
                                value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label for="max_students" class="form-label">
                                <i class="fas fa-users"></i>
                                <?= __('max_students') ?>
                            </label>
                            <input type="number" name="max_students" id="max_students"
                                class="form-input"
                                min="1"
                                placeholder="<?= __('unlimited') ?>"
                                value="<?= $_POST['max_students'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">
                            <i class="fas fa-align-left"></i>
                            <?= __('description') ?>
                        </label>
                        <textarea name="description" id="description"
                            class="form-input form-textarea"
                            placeholder="<?= __('session_description_optional') ?>"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <!-- Session Options -->
                    <div style="margin-top: 2rem;">
                        <h4 style="margin: 0 0 1rem 0; color: var(--gray-900); font-size: 1.1rem;">
                            <i class="fas fa-cog"></i> <?= __('session_options') ?>
                        </h4>

                        <div class="form-grid">
                            <div class="form-checkbox">
                                <input type="checkbox" name="is_mandatory" id="is_mandatory"
                                    <?= isset($_POST['is_mandatory']) ? 'checked' : '' ?>>
                                <label for="is_mandatory">
                                    <strong><?= __('mandatory_session') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem;">
                                        <?= __('mandatory_session_help') ?>
                                    </div>
                                </label>
                            </div>

                            <div class="form-checkbox">
                                <input type="checkbox" name="auto_mark_absent" id="auto_mark_absent"
                                    <?= isset($_POST['auto_mark_absent']) ? 'checked' : '' ?>>
                                <label for="auto_mark_absent">
                                    <strong><?= __('auto_mark_absent') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem;">
                                        <?= __('auto_mark_absent_help') ?>
                                    </div>
                                </label>
                            </div>

                            <div class="form-checkbox">
                                <input type="checkbox" name="reminder_enabled" id="reminder_enabled"
                                    <?= isset($_POST['reminder_enabled']) ? 'checked' : '' ?>>
                                <label for="reminder_enabled">
                                    <strong><?= __('enable_reminders') ?></strong>
                                    <div style="font-size: 0.85rem; color: var(--gray-500); margin-top: 0.25rem;">
                                        <?= __('enable_reminders_help') ?>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="form-group" id="reminder_time_group" style="display: none;">
                            <label for="reminder_time" class="form-label">
                                <i class="fas fa-bell"></i>
                                <?= __('reminder_time') ?>
                            </label>
                            <select name="reminder_time" id="reminder_time" class="form-input form-select">
                                <option value="15" <?= (isset($_POST['reminder_time']) && $_POST['reminder_time'] == 15) ? 'selected' : '' ?>>15 <?= __('minutes_before') ?></option>
                                <option value="30" <?= (isset($_POST['reminder_time']) && $_POST['reminder_time'] == 30) ? 'selected' : '' ?>>30 <?= __('minutes_before') ?></option>
                                <option value="60" <?= (isset($_POST['reminder_time']) && $_POST['reminder_time'] == 60) ? 'selected' : '' ?>>1 <?= __('hour_before') ?></option>
                                <option value="120" <?= (isset($_POST['reminder_time']) && $_POST['reminder_time'] == 120) ? 'selected' : '' ?>>2 <?= __('hours_before') ?></option>
                                <option value="1440" <?= (isset($_POST['reminder_time']) && $_POST['reminder_time'] == 1440) ? 'selected' : '' ?>>1 <?= __('day_before') ?></option>
                            </select>
                        </div>
                    </div>
                </form>

                <div class="form-actions">
                    <a href="attendance_management.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> <?= __('cancel') ?>
                    </a>
                    <button type="submit" form="sessionForm" class="btn btn-primary">
                        <i class="fas fa-plus"></i> <?= __('create_session') ?>
                    </button>
                </div>
            </section>

            <!-- Available Courses -->
            <?php if (count($courses) > 0): ?>
                <section class="courses-section">
                    <div class="courses-header">
                        <h3 class="courses-title">
                            <i class="fas fa-book-open"></i>
                            <?= __('available_courses') ?>
                        </h3>
                    </div>

                    <div class="courses-grid">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card" onclick="selectCourse(<?= $course['id'] ?>)">
                                <div class="course-header">
                                    <h4 class="course-title"><?= htmlspecialchars($course['title']) ?></h4>
                                    <span class="course-status <?= $course['status'] ?>">
                                        <?= __($course['status']) ?>
                                    </span>
                                </div>

                                <div class="course-stats">
                                    <div class="course-stat">
                                        <i class="fas fa-users"></i>
                                        <?= $course['enrollment_count'] ?> <?= __('students') ?>
                                    </div>
                                    <div class="course-stat">
                                        <i class="fas fa-calendar"></i>
                                        <?= $course['session_count'] ?> <?= __('sessions') ?>
                                    </div>
                                </div>

                                <div class="course-actions">
                                    <button type="button" class="btn btn-primary" onclick="selectCourse(<?= $course['id'] ?>)">
                                        <i class="fas fa-plus"></i> <?= __('select_course') ?>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Recent Sessions -->
            <?php if (count($recent_sessions) > 0): ?>
                <section class="recent-sessions">
                    <div class="recent-sessions-header">
                        <h3 class="recent-sessions-title">
                            <i class="fas fa-history"></i>
                            <?= __('recent_sessions') ?>
                        </h3>
                    </div>

                    <div class="recent-sessions-list">
                        <?php foreach ($recent_sessions as $session): ?>
                            <div class="session-item">
                                <div class="session-info">
                                    <h4><?= htmlspecialchars($session['session_title']) ?></h4>
                                    <p>
                                        <?= htmlspecialchars($session['course_title']) ?> •
                                        <?= date('M j, Y', strtotime($session['session_date'])) ?> •
                                        <?= date('g:i A', strtotime($session['start_time'])) ?>
                                    </p>
                                </div>
                                <div class="session-stats">
                                    <div class="session-stat">
                                        <i class="fas fa-users"></i>
                                        <?= $session['attendance_count'] ?> <?= __('total') ?>
                                    </div>
                                    <div class="session-stat">
                                        <i class="fas fa-check-circle"></i>
                                        <?= $session['present_count'] ?> <?= __('present') ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Configuration
        window.sessionConfig = {
            language: '<?= $_SESSION['user_language'] ?? 'fr' ?>',
            instructorId: <?= $instructor_id ?>,
            courses: <?= json_encode($courses) ?>,
            lessons: <?= json_encode($lessons) ?>
        };

        // Form validation and interaction
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('sessionForm');
            const courseSelect = document.getElementById('course_id');
            const lessonSelect = document.getElementById('lesson_id');
            const reminderCheckbox = document.getElementById('reminder_enabled');
            const reminderTimeGroup = document.getElementById('reminder_time_group');

            // Load lessons when course is selected
            courseSelect.addEventListener('change', function() {
                const courseId = this.value;
                if (courseId) {
                    // Clear current lessons
                    lessonSelect.innerHTML = '<option value=""><?= __('no_specific_lesson') ?></option>';

                    // Fetch lessons for selected course
                    fetch(`get_lessons.php?course_id=${courseId}`)
                        .then(response => response.json())
                        .then(lessons => {
                            lessons.forEach(lesson => {
                                const option = document.createElement('option');
                                option.value = lesson.id;
                                option.textContent = lesson.title;
                                lessonSelect.appendChild(option);
                            });
                        })
                        .catch(error => {
                            console.error('Error loading lessons:', error);
                        });
                }
            });

            // Toggle reminder time field
            reminderCheckbox.addEventListener('change', function() {
                reminderTimeGroup.style.display = this.checked ? 'block' : 'none';
            });

            // Initialize reminder time visibility
            reminderTimeGroup.style.display = reminderCheckbox.checked ? 'block' : 'none';

            // Form validation
            form.addEventListener('submit', function(e) {
                const courseId = courseSelect.value;
                const sessionTitle = document.getElementById('session_title').value.trim();
                const sessionDate = document.getElementById('session_date').value;
                const startTime = document.getElementById('start_time').value;
                const endTime = document.getElementById('end_time').value;

                // Clear previous errors
                document.querySelectorAll('.form-input.error').forEach(input => {
                    input.classList.remove('error');
                });
                document.querySelectorAll('.form-error').forEach(error => {
                    error.remove();
                });

                let hasErrors = false;

                if (!courseId) {
                    showFieldError(courseSelect, '<?= __('please_select_course') ?>');
                    hasErrors = true;
                }

                if (!sessionTitle) {
                    showFieldError(document.getElementById('session_title'), '<?= __('please_enter_session_title') ?>');
                    hasErrors = true;
                } else if (sessionTitle.length < 3) {
                    showFieldError(document.getElementById('session_title'), '<?= __('session_title_too_short') ?>');
                    hasErrors = true;
                }

                if (!sessionDate) {
                    showFieldError(document.getElementById('session_date'), '<?= __('please_select_session_date') ?>');
                    hasErrors = true;
                } else if (new Date(sessionDate) < new Date().setHours(0, 0, 0, 0)) {
                    showFieldError(document.getElementById('session_date'), '<?= __('session_date_cannot_be_past') ?>');
                    hasErrors = true;
                }

                if (startTime && endTime) {
                    const startDateTime = new Date(sessionDate + ' ' + startTime);
                    const endDateTime = new Date(sessionDate + ' ' + endTime);

                    if (endDateTime <= startDateTime) {
                        showFieldError(document.getElementById('end_time'), '<?= __('end_time_must_be_after_start_time') ?>');
                        hasErrors = true;
                    }
                }

                if (hasErrors) {
                    e.preventDefault();
                    return;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('creating') ?>...';
                submitBtn.disabled = true;
            });

            // Auto-hide success messages
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });

        function selectCourse(courseId) {
            document.getElementById('course_id').value = courseId;
            document.getElementById('course_id').dispatchEvent(new Event('change'));

            // Scroll to form
            document.querySelector('.form-section').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function showFieldError(field, message) {
            field.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'form-error';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
            field.parentNode.appendChild(errorDiv);
        }

        // Initialize date picker with restrictions
        flatpickr("#session_date", {
            minDate: "today",
            dateFormat: "Y-m-d",
            locale: "<?= $_SESSION['user_language'] ?? 'fr' ?>"
        });
    </script>
</body>

</html>