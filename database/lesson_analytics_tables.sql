-- Lesson Analytics Database Tables
-- Tables to track lesson performance, student engagement, and analytics

-- Lesson views tracking
CREATE TABLE IF NOT EXISTS lesson_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_duration INT DEFAULT 0, -- in seconds
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (lesson_id) REFERENCES course_contents (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_lesson_views_lesson (lesson_id),
    INDEX idx_lesson_views_student (student_id),
    INDEX idx_lesson_views_date (viewed_at)
);

-- Lesson completions tracking
CREATE TABLE IF NOT EXISTS lesson_completions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completion_time INT DEFAULT 0, -- total time spent in seconds
    score DECIMAL(5, 2) DEFAULT NULL, -- if there's a quiz or assessment
    attempts INT DEFAULT 1,
    FOREIGN KEY (lesson_id) REFERENCES course_contents (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_completion (lesson_id, student_id),
    INDEX idx_lesson_completions_lesson (lesson_id),
    INDEX idx_lesson_completions_student (student_id),
    INDEX idx_lesson_completions_date (completed_at)
);

-- Lesson ratings and reviews
CREATE TABLE IF NOT EXISTS lesson_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    rating INT NOT NULL CHECK (
        rating >= 1
        AND rating <= 5
    ),
    review TEXT,
    rated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES course_contents (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_rating (lesson_id, student_id),
    INDEX idx_lesson_ratings_lesson (lesson_id),
    INDEX idx_lesson_ratings_student (student_id),
    INDEX idx_lesson_ratings_date (rated_at)
);

-- Lesson progress tracking (detailed time tracking)
CREATE TABLE IF NOT EXISTS lesson_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    progress_percent DECIMAL(5, 2) DEFAULT 0.00,
    time_spent INT DEFAULT 0, -- in seconds
    last_position INT DEFAULT 0, -- for video lessons, last watched position
    last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_completed BOOLEAN DEFAULT FALSE,
    completion_date TIMESTAMP NULL,
    FOREIGN KEY (lesson_id) REFERENCES course_contents (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_progress (lesson_id, student_id),
    INDEX idx_lesson_progress_lesson (lesson_id),
    INDEX idx_lesson_progress_student (student_id),
    INDEX idx_lesson_progress_accessed (last_accessed)
);

-- Lesson interactions tracking (clicks, pauses, etc.)
CREATE TABLE IF NOT EXISTS lesson_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    student_id INT NOT NULL,
    interaction_type ENUM(
        'play',
        'pause',
        'seek',
        'replay',
        'download',
        'bookmark',
        'note'
    ) NOT NULL,
    interaction_data JSON, -- store additional data like timestamp, position, etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES course_contents (id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users (id) ON DELETE CASCADE,
    INDEX idx_lesson_interactions_lesson (lesson_id),
    INDEX idx_lesson_interactions_student (student_id),
    INDEX idx_lesson_interactions_type (interaction_type),
    INDEX idx_lesson_interactions_date (created_at)
);

-- Lesson analytics summary (for caching and performance)
CREATE TABLE IF NOT EXISTS lesson_analytics_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    date DATE NOT NULL,
    total_views INT DEFAULT 0,
    unique_views INT DEFAULT 0,
    completions INT DEFAULT 0,
    avg_rating DECIMAL(3, 2) DEFAULT 0.00,
    avg_time_spent INT DEFAULT 0, -- in seconds
    engagement_score DECIMAL(5, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES course_contents (id) ON DELETE CASCADE,
    UNIQUE KEY unique_lesson_date (lesson_id, date),
    INDEX idx_lesson_analytics_lesson (lesson_id),
    INDEX idx_lesson_analytics_date (date)
);

-- Insert sample data for testing (optional)
-- This will be populated by the application as students interact with lessons

-- Create triggers to automatically update analytics summary
CREATE TRIGGER IF NOT EXISTS update_lesson_analytics_on_view
AFTER INSERT ON lesson_views
FOR EACH ROW
BEGIN
    INSERT INTO lesson_analytics_summary (lesson_id, date, total_views, unique_views)
    VALUES (NEW.lesson_id, CURDATE(), 1, 1)
    ON DUPLICATE KEY UPDATE
        total_views = total_views + 1,
        unique_views = (
            SELECT COUNT(DISTINCT student_id) 
            FROM lesson_views 
            WHERE lesson_id = NEW.lesson_id 
            AND DATE(viewed_at) = CURDATE()
        ),
        updated_at = CURRENT_TIMESTAMP;
END;

CREATE TRIGGER IF NOT EXISTS update_lesson_analytics_on_completion
AFTER INSERT ON lesson_completions
FOR EACH ROW
BEGIN
    INSERT INTO lesson_analytics_summary (lesson_id, date, completions)
    VALUES (NEW.lesson_id, CURDATE(), 1)
    ON DUPLICATE KEY UPDATE
        completions = completions + 1,
        updated_at = CURRENT_TIMESTAMP;
END;

CREATE TRIGGER IF NOT EXISTS update_lesson_analytics_on_rating
AFTER INSERT ON lesson_ratings
FOR EACH ROW
BEGIN
    UPDATE lesson_analytics_summary 
    SET avg_rating = (
        SELECT AVG(rating) 
        FROM lesson_ratings 
        WHERE lesson_id = NEW.lesson_id 
        AND DATE(rated_at) = CURDATE()
    ),
    updated_at = CURRENT_TIMESTAMP
    WHERE lesson_id = NEW.lesson_id AND date = CURDATE();
END;