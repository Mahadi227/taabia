-- Attendance System Database Tables
-- Comprehensive attendance tracking for courses and lessons

-- Attendance sessions table (for tracking when attendance is taken)
CREATE TABLE attendance_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    lesson_id INT,
    session_title VARCHAR(255) NOT NULL,
    session_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    session_type ENUM('lesson', 'quiz', 'assignment', 'meeting', 'other') DEFAULT 'lesson',
    instructor_id INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student attendance records
CREATE TABLE student_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT NOT NULL,
    student_id INT NOT NULL,
    attendance_status ENUM('present', 'absent', 'late', 'excused') DEFAULT 'absent',
    check_in_time TIMESTAMP NULL,
    check_out_time TIMESTAMP NULL,
    notes TEXT,
    recorded_by INT NOT NULL, -- instructor who recorded the attendance
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendance (session_id, student_id)
);

-- Attendance settings for courses
CREATE TABLE course_attendance_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    attendance_required BOOLEAN DEFAULT FALSE,
    minimum_attendance_percent DECIMAL(5,2) DEFAULT 80.00,
    auto_mark_absent_after_minutes INT DEFAULT 15,
    allow_late_checkin BOOLEAN DEFAULT TRUE,
    late_threshold_minutes INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_course_settings (course_id)
);

-- Attendance reports and statistics
CREATE TABLE attendance_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    student_id INT NOT NULL,
    total_sessions INT DEFAULT 0,
    sessions_attended INT DEFAULT 0,
    sessions_absent INT DEFAULT 0,
    sessions_late INT DEFAULT 0,
    sessions_excused INT DEFAULT 0,
    attendance_percentage DECIMAL(5,2) DEFAULT 0.00,
    report_period_start DATE,
    report_period_end DATE,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_report (course_id, student_id, report_period_start, report_period_end)
);

-- Insert default attendance settings for existing courses
INSERT INTO course_attendance_settings (course_id, attendance_required, minimum_attendance_percent, auto_mark_absent_after_minutes, allow_late_checkin, late_threshold_minutes)
SELECT 
    c.id,
    FALSE, -- attendance not required by default
    80.00, -- 80% minimum attendance
    15,    -- mark absent after 15 minutes
    TRUE,  -- allow late check-in
    10     -- late threshold 10 minutes
FROM courses c
WHERE NOT EXISTS (
    SELECT 1 FROM course_attendance_settings cas WHERE cas.course_id = c.id
);

-- Create indexes for better performance
CREATE INDEX idx_attendance_sessions_course ON attendance_sessions(course_id);
CREATE INDEX idx_attendance_sessions_date ON attendance_sessions(session_date);
CREATE INDEX idx_student_attendance_session ON student_attendance(session_id);
CREATE INDEX idx_student_attendance_student ON student_attendance(student_id);
CREATE INDEX idx_attendance_reports_course_student ON attendance_reports(course_id, student_id); 