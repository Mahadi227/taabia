-- ============================================================================
-- QUIZZES SYSTEM DATABASE SCHEMA
-- ============================================================================
-- This schema creates the necessary tables for the quiz management system
-- including quizzes, questions, student attempts, and answers
-- ============================================================================

-- Table: quizzes
-- Stores quiz information and configuration
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    instructions TEXT,
    quiz_type ENUM('practice', 'graded', 'exam') DEFAULT 'practice',
    time_limit INT DEFAULT 30 COMMENT 'Time limit in minutes',
    passing_score INT DEFAULT 60 COMMENT 'Passing score percentage',
    max_attempts INT DEFAULT 3 COMMENT 'Maximum number of attempts allowed',
    shuffle_questions TINYINT(1) DEFAULT 0 COMMENT 'Whether to shuffle questions',
    show_correct_answers TINYINT(1) DEFAULT 1 COMMENT 'Show correct answers after completion',
    allow_review TINYINT(1) DEFAULT 1 COMMENT 'Allow students to review their answers',
    status ENUM('draft', 'active', 'archived') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses (id) ON DELETE CASCADE,
    INDEX idx_course_id (course_id),
    INDEX idx_status (status),
    INDEX idx_quiz_type (quiz_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quiz_questions
-- Stores questions for each quiz
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM(
        'multiple_choice',
        'true_false',
        'short_answer',
        'essay',
        'matching'
    ) NOT NULL,
    options JSON COMMENT 'Array of possible answers for multiple choice/matching',
    correct_answer JSON COMMENT 'Correct answer(s) - can be array for multiple choice',
    points INT DEFAULT 1 COMMENT 'Points awarded for correct answer',
    explanation TEXT COMMENT 'Explanation shown after answering',
    question_order INT DEFAULT 0 COMMENT 'Display order of questions',
    is_required TINYINT(1) DEFAULT 1 COMMENT 'Whether question must be answered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
    INDEX idx_quiz_id (quiz_id),
    INDEX idx_question_order (question_order),
    INDEX idx_question_type (question_type)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: student_quizzes
-- Stores student quiz attempts and results
CREATE TABLE IF NOT EXISTS student_quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    student_id INT NOT NULL,
    attempt_number INT DEFAULT 1 COMMENT 'Which attempt this is (1, 2, 3, etc.)',
    score DECIMAL(5, 2) COMMENT 'Final score as percentage',
    points_earned DECIMAL(10, 2) COMMENT 'Total points earned',
    total_points DECIMAL(10, 2) COMMENT 'Total possible points',
    started_at TIMESTAMP NULL COMMENT 'When student started the quiz',
    completed_at TIMESTAMP NULL COMMENT 'When student completed the quiz',
    time_taken INT COMMENT 'Time taken in seconds',
    status ENUM(
        'started',
        'in_progress',
        'completed',
        'abandoned'
    ) DEFAULT 'started',
    ip_address VARCHAR(45) COMMENT 'IP address of student',
    user_agent TEXT COMMENT 'Browser/device information',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_quiz_student (quiz_id, student_id),
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_completed_at (completed_at),
    UNIQUE KEY unique_attempt (
        quiz_id,
        student_id,
        attempt_number
    )
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: student_quiz_answers
-- Stores individual answers for each question in a quiz attempt
CREATE TABLE IF NOT EXISTS student_quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_quiz_id INT NOT NULL COMMENT 'Reference to student_quizzes table',
    question_id INT NOT NULL COMMENT 'Reference to quiz_questions table',
    answer JSON COMMENT 'Student answer(s) - format depends on question type',
    is_correct TINYINT(1) COMMENT 'Whether the answer is correct',
    points_earned DECIMAL(10, 2) DEFAULT 0 COMMENT 'Points earned for this answer',
    time_taken INT COMMENT 'Time spent on this question in seconds',
    answered_at TIMESTAMP NULL COMMENT 'When the question was answered',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_quiz_id) REFERENCES student_quizzes (id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE,
    INDEX idx_student_quiz (student_quiz_id),
    INDEX idx_question_id (question_id),
    INDEX idx_is_correct (is_correct),
    UNIQUE KEY unique_answer (student_quiz_id, question_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: quiz_analytics
-- Stores aggregated analytics for quizzes
CREATE TABLE IF NOT EXISTS quiz_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    total_attempts INT DEFAULT 0,
    completed_attempts INT DEFAULT 0,
    average_score DECIMAL(5, 2) DEFAULT 0,
    average_time INT DEFAULT 0 COMMENT 'Average time in seconds',
    pass_rate DECIMAL(5, 2) DEFAULT 0 COMMENT 'Percentage of students who passed',
    highest_score DECIMAL(5, 2) DEFAULT 0,
    lowest_score DECIMAL(5, 2) DEFAULT 0,
    last_calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
    UNIQUE KEY unique_quiz_analytics (quiz_id),
    INDEX idx_quiz_id (quiz_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Table: question_analytics
-- Stores analytics for individual questions
CREATE TABLE IF NOT EXISTS question_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT NOT NULL,
    times_asked INT DEFAULT 0,
    times_correct INT DEFAULT 0,
    times_incorrect INT DEFAULT 0,
    average_time INT DEFAULT 0 COMMENT 'Average time in seconds',
    difficulty_rating DECIMAL(3, 2) COMMENT 'Calculated difficulty (0-1, 1 being hardest)',
    last_calculated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES quiz_questions (id) ON DELETE CASCADE,
    UNIQUE KEY unique_question_analytics (question_id),
    INDEX idx_question_id (question_id),
    INDEX idx_difficulty_rating (difficulty_rating)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ============================================================================
-- SAMPLE DATA (OPTIONAL - FOR TESTING)
-- ============================================================================

-- Sample quiz for course 1 (Practice Quiz)
INSERT INTO
    quizzes (
        course_id,
        title,
        description,
        instructions,
        quiz_type,
        time_limit,
        passing_score,
        max_attempts,
        shuffle_questions,
        status
    )
VALUES (
        1,
        'Introduction to PHP - Practice Quiz',
        'Test your understanding of basic PHP concepts',
        'Read each question carefully and select the best answer. You have 30 minutes to complete this quiz.',
        'practice',
        30,
        70,
        3,
        1,
        'active'
    );

-- Sample questions for the practice quiz
SET @quiz_id = LAST_INSERT_ID();

INSERT INTO
    quiz_questions (
        quiz_id,
        question_text,
        question_type,
        options,
        correct_answer,
        points,
        explanation,
        question_order
    )
VALUES (
        @quiz_id,
        'What does PHP stand for?',
        'multiple_choice',
        '["Personal Home Page", "PHP: Hypertext Preprocessor", "Private Hypertext Processor", "Programming Hypertext Processor"]',
        '[1]',
        1,
        'PHP originally stood for Personal Home Page, but it now stands for PHP: Hypertext Preprocessor, which is a recursive acronym.',
        1
    ),
    (
        @quiz_id,
        'PHP code is executed on the server side.',
        'true_false',
        NULL,
        '"true"',
        1,
        'PHP is a server-side scripting language, meaning the code is executed on the web server before the result is sent to the client browser.',
        2
    ),
    (
        @quiz_id,
        'Which symbol is used to denote a variable in PHP?',
        'multiple_choice',
        '["@", "#", "$", "%"]',
        '[2]',
        1,
        'In PHP, variables are denoted by a dollar sign ($) followed by the variable name.',
        3
    ),
    (
        @quiz_id,
        'What is the correct way to end a PHP statement?',
        'short_answer',
        NULL,
        '";


"',
        1,
        'In PHP, statements must end with a semicolon (;).',
        4
    );

-- Sample graded quiz for course 1
INSERT INTO
    quizzes (
        course_id,
        title,
        description,
        instructions,
        quiz_type,
        time_limit,
        passing_score,
        max_attempts,
        shuffle_questions,
        status
    )
VALUES (
        1,
        'PHP Fundamentals - Graded Assessment',
        'This quiz will be graded and counts towards your final score',
        'You have 45 minutes to complete this assessment. Once started, you cannot pause. Make sure you have a stable internet connection.',
        'graded',
        45,
        80,
        2,
        1,
        'active'
    );

-- ============================================================================
-- VIEWS FOR CONVENIENT DATA RETRIEVAL
-- ============================================================================

-- View: quiz_results_summary
-- Provides a summary of quiz results for each student
CREATE OR REPLACE VIEW quiz_results_summary AS
SELECT
    sq.id AS attempt_id,
    sq.quiz_id,
    q.title AS quiz_title,
    q.course_id,
    c.title AS course_title,
    sq.student_id,
    u.full_name AS student_name,
    u.email AS student_email,
    sq.attempt_number,
    sq.score,
    sq.points_earned,
    sq.total_points,
    sq.started_at,
    sq.completed_at,
    sq.time_taken,
    sq.status,
    CASE
        WHEN sq.score >= q.passing_score THEN 'Pass'
        WHEN sq.score < q.passing_score THEN 'Fail'
        ELSE 'Incomplete'
    END AS result
FROM
    student_quizzes sq
    INNER JOIN quizzes q ON sq.quiz_id = q.id
    INNER JOIN courses c ON q.course_id = c.id
    INNER JOIN users u ON sq.student_id = u.id;

-- View: quiz_statistics
-- Provides detailed statistics for each quiz
CREATE OR REPLACE VIEW quiz_statistics AS
SELECT
    q.id AS quiz_id,
    q.title AS quiz_title,
    q.course_id,
    c.title AS course_title,
    q.quiz_type,
    q.passing_score,
    COUNT(DISTINCT sq.student_id) AS total_students,
    COUNT(sq.id) AS total_attempts,
    COUNT(
        CASE
            WHEN sq.status = 'completed' THEN 1
        END
    ) AS completed_attempts,
    AVG(
        CASE
            WHEN sq.status = 'completed' THEN sq.score
        END
    ) AS average_score,
    MAX(sq.score) AS highest_score,
    MIN(
        CASE
            WHEN sq.status = 'completed' THEN sq.score
        END
    ) AS lowest_score,
    AVG(
        CASE
            WHEN sq.status = 'completed' THEN sq.time_taken
        END
    ) AS average_time,
    COUNT(
        CASE
            WHEN sq.score >= q.passing_score THEN 1
        END
    ) AS students_passed,
    COUNT(
        CASE
            WHEN sq.score < q.passing_score
            AND sq.status = 'completed' THEN 1
        END
    ) AS students_failed
FROM
    quizzes q
    INNER JOIN courses c ON q.course_id = c.id
    LEFT JOIN student_quizzes sq ON q.id = sq.quiz_id
GROUP BY
    q.id,
    q.title,
    q.course_id,
    c.title,
    q.quiz_type,
    q.passing_score;

-- View: question_performance
-- Shows performance statistics for each question
CREATE OR REPLACE VIEW question_performance AS
SELECT
    qq.id AS question_id,
    qq.quiz_id,
    q.title AS quiz_title,
    qq.question_text,
    qq.question_type,
    qq.points,
    COUNT(sqa.id) AS times_answered,
    COUNT(
        CASE
            WHEN sqa.is_correct = 1 THEN 1
        END
    ) AS correct_answers,
    COUNT(
        CASE
            WHEN sqa.is_correct = 0 THEN 1
        END
    ) AS incorrect_answers,
    ROUND(
        (
            COUNT(
                CASE
                    WHEN sqa.is_correct = 1 THEN 1
                END
            ) * 100.0
        ) / NULLIF(COUNT(sqa.id), 0),
        2
    ) AS correct_percentage,
    AVG(sqa.time_taken) AS average_time
FROM
    quiz_questions qq
    INNER JOIN quizzes q ON qq.quiz_id = q.id
    LEFT JOIN student_quiz_answers sqa ON qq.id = sqa.question_id
GROUP BY
    qq.id,
    qq.quiz_id,
    q.title,
    qq.question_text,
    qq.question_type,
    qq.points;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER /
/

-- Procedure: calculate_quiz_analytics
-- Calculates and updates analytics for a specific quiz
CREATE PROCEDURE IF NOT EXISTS calculate_quiz_analytics(IN p_quiz_id INT)
BEGIN
    -- Calculate analytics
    INSERT INTO quiz_analytics (
        quiz_id, 
        total_attempts, 
        completed_attempts, 
        average_score, 
        average_time, 
        pass_rate,
        highest_score,
        lowest_score,
        last_calculated_at
    )
    SELECT 
        p_quiz_id,
        COUNT(*) AS total_attempts,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) AS completed_attempts,
        AVG(CASE WHEN status = 'completed' THEN score END) AS average_score,
        AVG(CASE WHEN status = 'completed' THEN time_taken END) AS average_time,
        (COUNT(CASE WHEN score >= (SELECT passing_score FROM quizzes WHERE id = p_quiz_id) THEN 1 END) * 100.0 / 
         NULLIF(COUNT(CASE WHEN status = 'completed' THEN 1 END), 0)) AS pass_rate,
        MAX(score) AS highest_score,
        MIN(CASE WHEN status = 'completed' THEN score END) AS lowest_score,
        NOW()
    FROM student_quizzes
    WHERE quiz_id = p_quiz_id
    ON DUPLICATE KEY UPDATE
        total_attempts = VALUES(total_attempts),
        completed_attempts = VALUES(completed_attempts),
        average_score = VALUES(average_score),
        average_time = VALUES(average_time),
        pass_rate = VALUES(pass_rate),
        highest_score = VALUES(highest_score),
        lowest_score = VALUES(lowest_score),
        last_calculated_at = NOW();
END
/
/

-- Procedure: calculate_question_analytics
-- Calculates and updates analytics for a specific question
CREATE PROCEDURE IF NOT EXISTS calculate_question_analytics(IN p_question_id INT)
BEGIN
    -- Calculate analytics
    INSERT INTO question_analytics (
        question_id,
        times_asked,
        times_correct,
        times_incorrect,
        average_time,
        difficulty_rating,
        last_calculated_at
    )
    SELECT 
        p_question_id,
        COUNT(*) AS times_asked,
        COUNT(CASE WHEN is_correct = 1 THEN 1 END) AS times_correct,
        COUNT(CASE WHEN is_correct = 0 THEN 1 END) AS times_incorrect,
        AVG(time_taken) AS average_time,
        1 - (COUNT(CASE WHEN is_correct = 1 THEN 1 END) * 1.0 / NULLIF(COUNT(*), 0)) AS difficulty_rating,
        NOW()
    FROM student_quiz_answers
    WHERE question_id = p_question_id
    ON DUPLICATE KEY UPDATE
        times_asked = VALUES(times_asked),
        times_correct = VALUES(times_correct),
        times_incorrect = VALUES(times_incorrect),
        average_time = VALUES(average_time),
        difficulty_rating = VALUES(difficulty_rating),
        last_calculated_at = NOW();
END
/
/

DELIMITER;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

DELIMITER /
/

-- Trigger: after_student_quiz_completed
-- Updates analytics when a student completes a quiz
CREATE TRIGGER IF NOT EXISTS after_student_quiz_completed
AFTER UPDATE ON student_quizzes
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        CALL calculate_quiz_analytics(NEW.quiz_id);
    END IF;
END
/
/

-- Trigger: after_quiz_answer_submitted
-- Updates question analytics when a student answers a question
CREATE TRIGGER IF NOT EXISTS after_quiz_answer_submitted
AFTER INSERT ON student_quiz_answers
FOR EACH ROW
BEGIN
    CALL calculate_question_analytics(NEW.question_id);
END
/
/

DELIMITER;

-- ============================================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- ============================================================================

-- Additional composite indexes for common queries
CREATE INDEX idx_quiz_status_type ON quizzes (status, quiz_type);

CREATE INDEX idx_student_quiz_status_score ON student_quizzes (status, score);

CREATE INDEX idx_student_quiz_completed ON student_quizzes (completed_at, status);

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================

SELECT '✅ Quiz system database schema created successfully!' AS message;

SELECT 'Tables created: quizzes, quiz_questions, student_quizzes, student_quiz_answers, quiz_analytics, question_analytics' AS tables;

SELECT 'Views created: quiz_results_summary, quiz_statistics, question_performance' AS views;

SELECT 'Stored procedures created: calculate_quiz_analytics, calculate_question_analytics' AS procedures;