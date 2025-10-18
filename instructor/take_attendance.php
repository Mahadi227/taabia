<?php

/**
 * Take Attendance Page
 * Allows instructors to take attendance for their courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';

// Check if user is logged in and is an instructor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    flash_message(__('access_denied'), 'error');
    redirect('../login.php');
}

$instructor_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $course_id = $_POST['course_id'] ?? null;
        $lesson_id = $_POST['lesson_id'] ?? null;
        $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $attendance_type = $_POST['attendance_type'] ?? 'lesson';
        $attendance_data = $_POST['attendance'] ?? [];
        $notes = $_POST['notes'] ?? '';

        if (!$course_id) {
            throw new Exception(__('course_required'));
        }

        // Validate that the course belongs to the instructor
        $course_check = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND instructor_id = ?");
        $course_check->execute([$course_id, $instructor_id]);
        if (!$course_check->fetch()) {
            throw new Exception(__('unauthorized_course_access'));
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert attendance records
        $insert_stmt = $pdo->prepare("
            INSERT INTO attendance (course_id, student_id, lesson_id, attendance_date, status, attendance_type, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            attendance_type = VALUES(attendance_type),
            notes = VALUES(notes),
            updated_at = NOW()
        ");

        $success_count = 0;
        foreach ($attendance_data as $student_id => $status) {
            if (!empty($status)) {
                $insert_stmt->execute([$course_id, $student_id, $lesson_id, $attendance_date, $status, $attendance_type, $notes]);
                $success_count++;
            }
        }

        $pdo->commit();

        if ($success_count > 0) {
            flash_message(sprintf(__('attendance_saved_success'), $success_count), 'success');
        } else {
            flash_message(__('no_attendance_recorded'), 'warning');
        }

        // Redirect to prevent resubmission
        redirect("take_attendance.php?course_id={$course_id}&lesson_id={$lesson_id}");
    } catch (Exception $e) {
        $pdo->rollBack();
        flash_message($e->getMessage(), 'error');
    }
}

// Get course ID from URL or form
$selected_course_id = $_GET['course_id'] ?? $_POST['course_id'] ?? null;
$selected_lesson_id = $_GET['lesson_id'] ?? $_POST['lesson_id'] ?? null;
$selected_date = $_GET['date'] ?? $_POST['attendance_date'] ?? date('Y-m-d');
$selected_type = $_GET['type'] ?? $_POST['attendance_type'] ?? 'lesson';

// Fetch instructor's courses
$courses_query = "
    SELECT c.id, c.title, c.code, c.status,
           COUNT(sc.student_id) as enrollment_count
    FROM courses c
    LEFT JOIN student_courses sc ON c.id = sc.course_id
    WHERE c.instructor_id = ? AND c.status IN ('published', 'active')
    GROUP BY c.id, c.title, c.code, c.status
    ORDER BY c.title ASC
";
$courses_stmt = $pdo->prepare($courses_query);
$courses_stmt->execute([$instructor_id]);
$courses = $courses_stmt->fetchAll();

// Fetch lessons for selected course
$lessons = [];
if ($selected_course_id) {
    $lessons_query = "
        SELECT id, title, lesson_date, start_time, end_time, lesson_type
        FROM lessons
        WHERE course_id = ? AND status = 'published'
        ORDER BY lesson_date DESC, start_time DESC
    ";
    $lessons_stmt = $pdo->prepare($lessons_query);
    $lessons_stmt->execute([$selected_course_id]);
    $lessons = $lessons_stmt->fetchAll();
}

// Fetch students for selected course
$students = [];
$existing_attendance = [];
if ($selected_course_id) {
    // Get enrolled students
    $students_query = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
               sc.enrolled_at, sc.progress_percent
        FROM users u
        INNER JOIN student_courses sc ON u.id = sc.student_id
        WHERE sc.course_id = ? AND u.status = 'active'
        ORDER BY u.last_name ASC, u.first_name ASC
    ";
    $students_stmt = $pdo->prepare($students_query);
    $students_stmt->execute([$selected_course_id]);
    $students = $students_stmt->fetchAll();

    // Get existing attendance for the selected date and lesson
    if ($selected_date && ($selected_lesson_id || $selected_course_id)) {
        $existing_query = "
            SELECT student_id, status, notes
            FROM attendance
            WHERE course_id = ? AND attendance_date = ? AND attendance_type = ?
        ";
        $params = [$selected_course_id, $selected_date, $selected_type];

        if ($selected_lesson_id) {
            $existing_query .= " AND lesson_id = ?";
            $params[] = $selected_lesson_id;
        } else {
            $existing_query .= " AND lesson_id IS NULL";
        }

        $existing_stmt = $pdo->prepare($existing_query);
        $existing_stmt->execute($params);
        $existing_attendance = [];

        while ($row = $existing_stmt->fetch()) {
            $existing_attendance[$row['student_id']] = [
                'status' => $row['status'],
                'notes' => $row['notes']
            ];
        }
    }
}

// Get recent attendance for the course
$recent_attendance = [];
if ($selected_course_id) {
    $recent_query = "
        SELECT a.attendance_date, a.status, a.attendance_type, a.notes,
               u.first_name, u.last_name, l.title as lesson_title
        FROM attendance a
        INNER JOIN users u ON a.student_id = u.id
        LEFT JOIN lessons l ON a.lesson_id = l.id
        WHERE a.course_id = ?
        ORDER BY a.attendance_date DESC, a.created_at DESC
        LIMIT 10
    ";
    $recent_stmt = $pdo->prepare($recent_query);
    $recent_stmt->execute([$selected_course_id]);
    $recent_attendance = $recent_stmt->fetchAll();
}

$page_title = __('take_attendance');
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo __('app_name'); ?></title>

    <!-- CSS Files -->
    <link rel="stylesheet" href="instructor-styles.css">
    <link rel="stylesheet" href="../includes/instructor_sidebar.css">
    <link rel="stylesheet" href="assets/css/take-attendance.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <?php include '../includes/instructor_sidebar.php'; ?>

    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">
                        <i class="fas fa-clipboard-check"></i>
                        <?php echo __('take_attendance'); ?>
                    </h1>
                    <p class="page-description"><?php echo __('take_attendance_description'); ?></p>
                </div>
                <div class="header-actions">
                    <a href="attendance_reports.php" class="btn btn-secondary">
                        <i class="fas fa-chart-bar"></i>
                        <?php echo __('view_reports'); ?>
                    </a>
                    <a href="my_courses.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        <?php echo __('back_to_courses'); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="flash-message flash-<?php echo $_SESSION['flash_type']; ?>">
                <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : ($_SESSION['flash_type'] === 'error' ? 'exclamation-circle' : 'info-circle'); ?>"></i>
                <?php echo $_SESSION['flash_message']; ?>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>

        <!-- Course Selection -->
        <div class="content-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-book"></i>
                    <?php echo __('select_course'); ?>
                </h2>
            </div>

            <div class="course-selection">
                <form method="GET" class="course-form" id="courseSelectionForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="course_id" class="form-label"><?php echo __('course'); ?></label>
                            <select name="course_id" id="course_id" class="form-select" required>
                                <option value=""><?php echo __('select_course'); ?></option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>"
                                        <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course['title']); ?>
                                        (<?php echo htmlspecialchars($course['code']); ?>)
                                        - <?php echo $course['enrollment_count']; ?> <?php echo __('students'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="lesson_id" class="form-label"><?php echo __('lesson'); ?> (<?php echo __('optional'); ?>)</label>
                            <select name="lesson_id" id="lesson_id" class="form-select">
                                <option value=""><?php echo __('all_lessons'); ?></option>
                                <?php foreach ($lessons as $lesson): ?>
                                    <option value="<?php echo $lesson['id']; ?>"
                                        <?php echo ($selected_lesson_id == $lesson['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($lesson['title']); ?>
                                        <?php if ($lesson['lesson_date']): ?>
                                            - <?php echo date('d/m/Y', strtotime($lesson['lesson_date'])); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="date" class="form-label"><?php echo __('attendance_date'); ?></label>
                            <input type="date" name="date" id="date" class="form-input"
                                value="<?php echo $selected_date; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="type" class="form-label"><?php echo __('attendance_type'); ?></label>
                            <select name="type" id="type" class="form-select">
                                <option value="lesson" <?php echo ($selected_type === 'lesson') ? 'selected' : ''; ?>>
                                    <?php echo __('lesson_attendance'); ?>
                                </option>
                                <option value="exam" <?php echo ($selected_type === 'exam') ? 'selected' : ''; ?>>
                                    <?php echo __('exam_attendance'); ?>
                                </option>
                                <option value="assignment" <?php echo ($selected_type === 'assignment') ? 'selected' : ''; ?>>
                                    <?php echo __('assignment_attendance'); ?>
                                </option>
                            </select>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                                <?php echo __('load_students'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_course_id && count($students) > 0): ?>
            <!-- Attendance Form -->
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i>
                        <?php echo __('student_attendance'); ?>
                        <span class="badge badge-info"><?php echo count($students); ?> <?php echo __('students'); ?></span>
                    </h2>
                    <div class="section-actions">
                        <button type="button" class="btn btn-outline" id="markAllPresent">
                            <i class="fas fa-check-double"></i>
                            <?php echo __('mark_all_present'); ?>
                        </button>
                        <button type="button" class="btn btn-outline" id="markAllAbsent">
                            <i class="fas fa-times"></i>
                            <?php echo __('mark_all_absent'); ?>
                        </button>
                        <button type="button" class="btn btn-outline" id="clearAllAttendance">
                            <i class="fas fa-eraser"></i>
                            <?php echo __('clear_all'); ?>
                        </button>
                    </div>
                </div>

                <form method="POST" class="attendance-form" id="attendanceForm">
                    <input type="hidden" name="course_id" value="<?php echo $selected_course_id; ?>">
                    <input type="hidden" name="lesson_id" value="<?php echo $selected_lesson_id; ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                    <input type="hidden" name="attendance_type" value="<?php echo $selected_type; ?>">

                    <div class="attendance-grid">
                        <?php foreach ($students as $student): ?>
                            <div class="attendance-card" data-student-id="<?php echo $student['id']; ?>">
                                <div class="student-info">
                                    <div class="student-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="student-details">
                                        <h4 class="student-name">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                        </h4>
                                        <p class="student-email"><?php echo htmlspecialchars($student['email']); ?></p>
                                        <p class="student-progress">
                                            <i class="fas fa-chart-line"></i>
                                            <?php echo $student['progress_percent']; ?>% <?php echo __('progress'); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="attendance-controls">
                                    <div class="status-buttons">
                                        <label class="status-option">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="present"
                                                <?php echo isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']]['status'] === 'present' ? 'checked' : ''; ?>>
                                            <span class="status-label status-present">
                                                <i class="fas fa-check"></i>
                                                <?php echo __('present'); ?>
                                            </span>
                                        </label>

                                        <label class="status-option">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="absent"
                                                <?php echo isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']]['status'] === 'absent' ? 'checked' : ''; ?>>
                                            <span class="status-label status-absent">
                                                <i class="fas fa-times"></i>
                                                <?php echo __('absent'); ?>
                                            </span>
                                        </label>

                                        <label class="status-option">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="late"
                                                <?php echo isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']]['status'] === 'late' ? 'checked' : ''; ?>>
                                            <span class="status-label status-late">
                                                <i class="fas fa-clock"></i>
                                                <?php echo __('late'); ?>
                                            </span>
                                        </label>

                                        <label class="status-option">
                                            <input type="radio" name="attendance[<?php echo $student['id']; ?>]" value="excused"
                                                <?php echo isset($existing_attendance[$student['id']]) && $existing_attendance[$student['id']]['status'] === 'excused' ? 'checked' : ''; ?>>
                                            <span class="status-label status-excused">
                                                <i class="fas fa-user-check"></i>
                                                <?php echo __('excused'); ?>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="attendance-notes">
                                        <textarea name="notes[<?php echo $student['id']; ?>]"
                                            class="form-textarea"
                                            placeholder="<?php echo __('add_notes'); ?>"
                                            rows="2"><?php echo isset($existing_attendance[$student['id']]) ? htmlspecialchars($existing_attendance[$student['id']]['notes']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="attendance-summary">
                        <div class="summary-stats">
                            <div class="stat-item">
                                <span class="stat-label"><?php echo __('present'); ?>:</span>
                                <span class="stat-value" id="presentCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php echo __('absent'); ?>:</span>
                                <span class="stat-value" id="absentCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php echo __('late'); ?>:</span>
                                <span class="stat-value" id="lateCount">0</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php echo __('excused'); ?>:</span>
                                <span class="stat-value" id="excusedCount">0</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-outline" id="previewAttendance">
                                <i class="fas fa-eye"></i>
                                <?php echo __('preview'); ?>
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?php echo __('save_attendance'); ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Recent Attendance -->
            <?php if (count($recent_attendance) > 0): ?>
                <div class="content-section">
                    <div class="section-header">
                        <h2 class="section-title">
                            <i class="fas fa-history"></i>
                            <?php echo __('recent_attendance'); ?>
                        </h2>
                    </div>

                    <div class="recent-attendance">
                        <div class="attendance-table">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th><?php echo __('date'); ?></th>
                                        <th><?php echo __('student'); ?></th>
                                        <th><?php echo __('lesson'); ?></th>
                                        <th><?php echo __('status'); ?></th>
                                        <th><?php echo __('type'); ?></th>
                                        <th><?php echo __('notes'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $record): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($record['attendance_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></td>
                                            <td><?php echo $record['lesson_title'] ? htmlspecialchars($record['lesson_title']) : __('no_lesson'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php echo $record['status']; ?>">
                                                    <?php echo __($record['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo __($record['attendance_type'] . '_attendance'); ?></td>
                                            <td><?php echo $record['notes'] ? htmlspecialchars($record['notes']) : __('no_notes'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php elseif ($selected_course_id && count($students) === 0): ?>
            <!-- No Students -->
            <div class="content-section">
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3><?php echo __('no_students_enrolled'); ?></h3>
                    <p><?php echo __('no_students_enrolled_description'); ?></p>
                    <a href="course_students.php?course_id=<?php echo $selected_course_id; ?>" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        <?php echo __('manage_students'); ?>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Preview Modal -->
    <div class="modal" id="previewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"><?php echo __('attendance_preview'); ?></h3>
                <button type="button" class="modal-close" id="closePreviewModal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="preview-summary">
                    <h4><?php echo __('attendance_summary'); ?></h4>
                    <div class="preview-stats" id="previewStats">
                        <!-- Stats will be populated by JavaScript -->
                    </div>
                </div>
                <div class="preview-list" id="previewList">
                    <!-- Attendance list will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" id="cancelPreview"><?php echo __('cancel'); ?></button>
                <button type="button" class="btn btn-primary" id="confirmSave"><?php echo __('save_attendance'); ?></button>
            </div>
        </div>
    </div>

    <!-- JavaScript Files -->
    <script src="assets/js/take-attendance.js"></script>
</body>

</html>