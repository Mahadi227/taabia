-- ================================================
-- TaaBia LMS - Assignments & Quizzes Schema
-- ================================================
-- This script creates the necessary tables for
-- Assignments and Quizzes functionality
-- ================================================

-- Table: assignments
-- Stores assignment information for courses
CREATE TABLE IF NOT EXISTS `assignments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `course_id` INT(11) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `instructions` TEXT DEFAULT NULL,
    `file_path` VARCHAR(255) DEFAULT NULL COMMENT 'Path to assignment instructions file',
    `deadline` DATETIME NOT NULL,
    `max_grade` INT(11) DEFAULT 100,
    `weight` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Weight in final grade calculation',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `course_id` (`course_id`),
    KEY `deadline` (`deadline`),
    CONSTRAINT `fk_assignments_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: assignment_submissions
-- Stores student submissions for assignments
CREATE TABLE IF NOT EXISTS `assignment_submissions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `assignment_id` INT(11) NOT NULL,
    `student_id` INT(11) NOT NULL,
    `file_path` VARCHAR(255) DEFAULT NULL COMMENT 'Path to submitted file',
    `submission_text` TEXT DEFAULT NULL COMMENT 'Text submission (if applicable)',
    `submission_url` VARCHAR(500) DEFAULT NULL COMMENT 'URL submission (if applicable)',
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `grade` DECIMAL(5, 2) DEFAULT NULL COMMENT 'Grade in percentage',
    `feedback` TEXT DEFAULT NULL COMMENT 'Instructor feedback',
    `graded_at` DATETIME DEFAULT NULL,
    `graded_by` INT(11) DEFAULT NULL COMMENT 'Instructor who graded',
    `status` ENUM(
        'submitted',
        'graded',
        'late',
        'resubmitted'
    ) DEFAULT 'submitted',
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_submission` (`assignment_id`, `student_id`),
    KEY `student_id` (`student_id`),
    KEY `graded_by` (`graded_by`),
    KEY `status` (`status`),
    CONSTRAINT `fk_submissions_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_submissions_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_submissions_grader` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quizzes
-- Stores quiz information for courses
CREATE TABLE IF NOT EXISTS `quizzes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `course_id` INT(11) NOT NULL,
    `lesson_id` INT(11) DEFAULT NULL COMMENT 'Optional: Link to a specific lesson',
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `instructions` TEXT DEFAULT NULL,
    `time_limit` INT(11) DEFAULT NULL COMMENT 'Time limit in minutes',
    `passing_score` INT(11) DEFAULT 70 COMMENT 'Minimum score to pass (percentage)',
    `max_attempts` INT(11) DEFAULT 1 COMMENT 'Maximum number of attempts allowed',
    `allow_retake` BOOLEAN DEFAULT FALSE,
    `show_correct_answers` BOOLEAN DEFAULT TRUE COMMENT 'Show correct answers after completion',
    `randomize_questions` BOOLEAN DEFAULT FALSE,
    `randomize_answers` BOOLEAN DEFAULT FALSE,
    `available_from` DATETIME DEFAULT NULL,
    `available_until` DATETIME DEFAULT NULL,
    `weight` DECIMAL(5, 2) DEFAULT 0.00 COMMENT 'Weight in final grade calculation',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `course_id` (`course_id`),
    KEY `lesson_id` (`lesson_id`),
    KEY `is_active` (`is_active`),
    CONSTRAINT `fk_quizzes_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_quizzes_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quiz_questions
-- Stores questions for each quiz
CREATE TABLE IF NOT EXISTS `quiz_questions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quiz_id` INT(11) NOT NULL,
    `question_text` TEXT NOT NULL,
    `question_type` ENUM(
        'multiple_choice',
        'true_false',
        'short_answer',
        'essay'
    ) DEFAULT 'multiple_choice',
    `points` INT(11) DEFAULT 1,
    `order_index` INT(11) DEFAULT 0,
    `explanation` TEXT DEFAULT NULL COMMENT 'Explanation shown after answering',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `quiz_id` (`quiz_id`),
    KEY `order_index` (`order_index`),
    CONSTRAINT `fk_questions_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quiz_answers
-- Stores possible answers for quiz questions
CREATE TABLE IF NOT EXISTS `quiz_answers` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `question_id` INT(11) NOT NULL,
    `answer_text` TEXT NOT NULL,
    `is_correct` BOOLEAN DEFAULT FALSE,
    `order_index` INT(11) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `question_id` (`question_id`),
    KEY `order_index` (`order_index`),
    CONSTRAINT `fk_answers_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quiz_attempts
-- Stores student attempts at quizzes
CREATE TABLE IF NOT EXISTS `quiz_attempts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `quiz_id` INT(11) NOT NULL,
    `student_id` INT(11) NOT NULL,
    `attempt_number` INT(11) DEFAULT 1,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    `time_taken` INT(11) DEFAULT NULL COMMENT 'Time taken in seconds',
    `score` DECIMAL(5, 2) DEFAULT NULL COMMENT 'Score in percentage',
    `points_earned` INT(11) DEFAULT NULL,
    `total_points` INT(11) DEFAULT NULL,
    `status` ENUM(
        'in_progress',
        'completed',
        'abandoned'
    ) DEFAULT 'in_progress',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `quiz_id` (`quiz_id`),
    KEY `student_id` (`student_id`),
    KEY `status` (`status`),
    KEY `completed_at` (`completed_at`),
    CONSTRAINT `fk_attempts_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attempts_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quiz_responses
-- Stores student responses to quiz questions
CREATE TABLE IF NOT EXISTS `quiz_responses` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `attempt_id` INT(11) NOT NULL,
    `question_id` INT(11) NOT NULL,
    `answer_id` INT(11) DEFAULT NULL COMMENT 'For multiple choice questions',
    `answer_text` TEXT DEFAULT NULL COMMENT 'For text-based questions',
    `is_correct` BOOLEAN DEFAULT FALSE,
    `points_earned` INT(11) DEFAULT 0,
    `answered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_response` (`attempt_id`, `question_id`),
    KEY `question_id` (`question_id`),
    KEY `answer_id` (`answer_id`),
    CONSTRAINT `fk_responses_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_responses_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_questions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_responses_answer` FOREIGN KEY (`answer_id`) REFERENCES `quiz_answers` (`id`) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ================================================
-- Sample Data (Optional)
-- ================================================

-- Example: Create a sample assignment
-- INSERT INTO `assignments` (`course_id`, `title`, `description`, `deadline`, `max_grade`)
-- VALUES (1, 'Essay: Introduction to Web Development', 'Write a 500-word essay about the history of web development.', '2025-12-31 23:59:59', 100);

-- Example: Create a sample quiz
-- INSERT INTO `quizzes` (`course_id`, `title`, `description`, `time_limit`, `passing_score`, `max_attempts`, `allow_retake`)
-- VALUES (1, 'HTML Basics Quiz', 'Test your knowledge of HTML fundamentals.', 30, 70, 3, TRUE);

-- ================================================
-- Indexes for Performance
-- ================================================

-- Additional indexes for common queries
CREATE INDEX idx_assignments_course_deadline ON `assignments` (`course_id`, `deadline`);

CREATE INDEX idx_submissions_student_status ON `assignment_submissions` (`student_id`, `status`);

CREATE INDEX idx_quizzes_course_active ON `quizzes` (`course_id`, `is_active`);

CREATE INDEX idx_attempts_student_quiz ON `quiz_attempts` (`student_id`, `quiz_id`);

-- ================================================
-- Views for Common Queries (Optional)
-- ================================================

-- View: Student assignment overview
CREATE OR REPLACE VIEW `view_student_assignments` AS
SELECT
    a.id as assignment_id,
    a.course_id,
    c.title as course_title,
    a.title as assignment_title,
    a.deadline,
    s.student_id,
    s.submitted_at,
    s.grade,
    s.status,
    CASE
        WHEN s.id IS NULL THEN 'pending'
        WHEN s.grade IS NOT NULL THEN 'graded'
        ELSE 'submitted'
    END as display_status
FROM
    `assignments` a
    LEFT JOIN `assignment_submissions` s ON a.id = s.assignment_id
    LEFT JOIN `courses` c ON a.course_id = c.id;

-- View: Student quiz overview
CREATE OR REPLACE VIEW `view_student_quizzes` AS
SELECT
    q.id as quiz_id,
    q.course_id,
    c.title as course_title,
    q.title as quiz_title,
    q.passing_score,
    qa.student_id,
    qa.completed_at,
    qa.score,
    qa.attempt_number,
    CASE
        WHEN qa.id IS NULL THEN 'not_started'
        WHEN qa.status = 'completed' THEN 'completed'
        ELSE 'in_progress'
    END as display_status
FROM
    `quizzes` q
    LEFT JOIN `quiz_attempts` qa ON q.id = qa.quiz_id
    LEFT JOIN `courses` c ON q.course_id = c.id
WHERE
    q.is_active = 1;

-- ================================================
-- Triggers for Automatic Actions
-- ================================================

-- Trigger: Update assignment submission status if late
DELIMITER / /

CREATE TRIGGER `check_late_submission`
BEFORE INSERT ON `assignment_submissions`
FOR EACH ROW
BEGIN
    DECLARE assignment_deadline DATETIME;
    SELECT deadline INTO assignment_deadline FROM assignments WHERE id = NEW.assignment_id;
    
    IF NEW.submitted_at > assignment_deadline THEN
        SET NEW.status = 'late';
    END IF;
END//

DELIMITER;

-- Trigger: Calculate quiz score automatically
DELIMITER / /

CREATE TRIGGER `calculate_quiz_score`
BEFORE UPDATE ON `quiz_attempts`
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND NEW.total_points > 0 THEN
        SET NEW.score = (NEW.points_earned / NEW.total_points) * 100;
    END IF;
END//

DELIMITER;

-- ================================================
-- Procedures for Common Operations
-- ================================================

-- Procedure: Get student progress in a course
DELIMITER / /

CREATE PROCEDURE `sp_get_student_course_progress`(
    IN p_student_id INT,
    IN p_course_id INT
)
BEGIN
    SELECT 
        'assignments' as type,
        COUNT(DISTINCT a.id) as total,
        COUNT(DISTINCT asub.id) as completed,
        AVG(asub.grade) as average_grade
    FROM assignments a
    LEFT JOIN assignment_submissions asub ON a.id = asub.assignment_id AND asub.student_id = p_student_id
    WHERE a.course_id = p_course_id
    
    UNION ALL
    
    SELECT 
        'quizzes' as type,
        COUNT(DISTINCT q.id) as total,
        COUNT(DISTINCT qa.id) as completed,
        AVG(qa.score) as average_grade
    FROM quizzes q
    LEFT JOIN quiz_attempts qa ON q.id = qa.quiz_id AND qa.student_id = p_student_id AND qa.status = 'completed'
    WHERE q.course_id = p_course_id AND q.is_active = 1;
END//

DELIMITER;

-- ================================================
-- End of Schema
-- ================================================

-- To execute this script:
-- 1. Open phpMyAdmin or MySQL command line
-- 2. Select your database (taabia_skills)
-- 3. Run this entire script
-- 4. Verify tables are created successfully

-- Note: Make sure the 'courses', 'users', and 'lessons' tables exist before running this script











