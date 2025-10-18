-- ============================================================================
-- CERTIFICATES SYSTEM DATABASE SCHEMA
-- ============================================================================
-- This schema creates the necessary tables for the certification system
-- including certificates, templates, and verification
-- ============================================================================

-- Table: course_certificates
-- Stores certificate information for completed courses
CREATE TABLE IF NOT EXISTS course_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    certificate_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Unique certificate identifier',
    student_name VARCHAR(255) NOT NULL,
    course_title VARCHAR(255) NOT NULL,
    instructor_name VARCHAR(255) NOT NULL,
    completion_date DATE NOT NULL,
    issue_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    final_grade DECIMAL(5,2) COMMENT 'Final grade percentage',
    certificate_template VARCHAR(50) DEFAULT 'default' COMMENT 'Template used',
    certificate_url VARCHAR(500) COMMENT 'Path to generated PDF',
    verification_code VARCHAR(100) UNIQUE NOT NULL COMMENT 'Code for certificate verification',
    is_verified TINYINT(1) DEFAULT 1,
    metadata JSON COMMENT 'Additional certificate data',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_course (student_id, course_id),
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_verification_code (verification_code),
    INDEX idx_issue_date (issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: certificate_templates
-- Stores different certificate template designs
CREATE TABLE IF NOT EXISTS certificate_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL UNIQUE,
    template_description TEXT,
    background_image VARCHAR(500) COMMENT 'Path to background image',
    logo_image VARCHAR(500) COMMENT 'Path to logo',
    layout_config JSON COMMENT 'Template layout configuration',
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    created_by INT COMMENT 'Admin or instructor who created it',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_name (template_name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: certificate_verifications
-- Logs certificate verification attempts
CREATE TABLE IF NOT EXISTS certificate_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_id INT NOT NULL,
    verification_code VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    verified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verification_status ENUM('success', 'failed', 'suspicious') DEFAULT 'success',
    FOREIGN KEY (certificate_id) REFERENCES course_certificates(id) ON DELETE CASCADE,
    INDEX idx_certificate_id (certificate_id),
    INDEX idx_verification_code (verification_code),
    INDEX idx_verified_at (verified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: certificate_shares
-- Tracks when and where certificates are shared
CREATE TABLE IF NOT EXISTS certificate_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    certificate_id INT NOT NULL,
    platform ENUM('linkedin', 'facebook', 'twitter', 'email', 'download', 'other') NOT NULL,
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (certificate_id) REFERENCES course_certificates(id) ON DELETE CASCADE,
    INDEX idx_certificate_id (certificate_id),
    INDEX idx_platform (platform),
    INDEX idx_shared_at (shared_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT CERTIFICATE TEMPLATES
-- ============================================================================

-- Insert default certificate template
INSERT INTO certificate_templates (template_name, template_description, layout_config, is_active, is_default)
VALUES 
('default', 'Default Professional Certificate Template', 
'{
    "font_family": "Arial, sans-serif",
    "title_font_size": 36,
    "name_font_size": 48,
    "body_font_size": 14,
    "primary_color": "#667eea",
    "secondary_color": "#764ba2",
    "border_style": "elegant",
    "seal_position": "bottom-right"
}', 1, 1),

('modern', 'Modern Minimalist Certificate',
'{
    "font_family": "Helvetica, sans-serif",
    "title_font_size": 32,
    "name_font_size": 44,
    "body_font_size": 12,
    "primary_color": "#2563eb",
    "secondary_color": "#1e40af",
    "border_style": "minimal",
    "seal_position": "bottom-center"
}', 1, 0),

('classic', 'Classic Formal Certificate',
'{
    "font_family": "Times New Roman, serif",
    "title_font_size": 40,
    "name_font_size": 52,
    "body_font_size": 16,
    "primary_color": "#1f2937",
    "secondary_color": "#4b5563",
    "border_style": "ornate",
    "seal_position": "bottom-left"
}', 1, 0);

-- ============================================================================
-- VIEWS FOR CONVENIENT DATA RETRIEVAL
-- ============================================================================

-- View: certificate_details
-- Provides complete certificate information
CREATE OR REPLACE VIEW certificate_details AS
SELECT 
    cc.id,
    cc.certificate_number,
    cc.student_name,
    cc.course_title,
    cc.instructor_name,
    cc.completion_date,
    cc.issue_date,
    cc.final_grade,
    cc.certificate_url,
    cc.verification_code,
    u.email as student_email,
    u.full_name as current_student_name,
    c.title as current_course_title,
    c.description as course_description,
    i.full_name as current_instructor_name,
    ct.template_name,
    COUNT(DISTINCT cv.id) as verification_count,
    COUNT(DISTINCT cs.id) as share_count
FROM course_certificates cc
LEFT JOIN users u ON cc.student_id = u.id
LEFT JOIN courses c ON cc.course_id = c.id
LEFT JOIN users i ON c.instructor_id = i.id
LEFT JOIN certificate_templates ct ON cc.certificate_template = ct.template_name
LEFT JOIN certificate_verifications cv ON cc.id = cv.certificate_id
LEFT JOIN certificate_shares cs ON cc.id = cs.certificate_id
GROUP BY cc.id;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER //

-- Procedure: generate_certificate_number
-- Generates a unique certificate number
CREATE PROCEDURE IF NOT EXISTS generate_certificate_number(
    IN p_course_id INT,
    IN p_student_id INT,
    OUT p_certificate_number VARCHAR(50)
)
BEGIN
    DECLARE cert_count INT;
    DECLARE year_suffix VARCHAR(4);
    
    SET year_suffix = DATE_FORMAT(NOW(), '%Y');
    
    -- Get count of certificates for this year
    SELECT COUNT(*) INTO cert_count 
    FROM course_certificates 
    WHERE YEAR(issue_date) = YEAR(NOW());
    
    -- Generate certificate number: CERT-YYYY-XXXXX
    SET p_certificate_number = CONCAT('CERT-', year_suffix, '-', LPAD(cert_count + 1, 5, '0'));
END //

-- Procedure: generate_verification_code
-- Generates a secure verification code
CREATE PROCEDURE IF NOT EXISTS generate_verification_code(
    IN p_certificate_id INT,
    OUT p_verification_code VARCHAR(100)
)
BEGIN
    -- Generate verification code: VER-TIMESTAMP-RANDOM
    SET p_verification_code = CONCAT(
        'VER-',
        UNIX_TIMESTAMP(),
        '-',
        SUBSTRING(MD5(RAND()), 1, 8)
    );
END //

DELIMITER ;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

DELIMITER //

-- Trigger: after_course_completed
-- Automatically generates certificate when student completes a course
CREATE TRIGGER IF NOT EXISTS after_course_completed
AFTER UPDATE ON student_courses
FOR EACH ROW
BEGIN
    DECLARE cert_exists INT;
    DECLARE cert_number VARCHAR(50);
    DECLARE verify_code VARCHAR(100);
    DECLARE student_name VARCHAR(255);
    DECLARE course_title VARCHAR(255);
    DECLARE instructor_name VARCHAR(255);
    
    -- Check if progress reached 100% and certificate doesn't exist
    IF NEW.progress_percent >= 100 AND OLD.progress_percent < 100 THEN
        SELECT COUNT(*) INTO cert_exists 
        FROM course_certificates 
        WHERE student_id = NEW.student_id AND course_id = NEW.course_id;
        
        IF cert_exists = 0 THEN
            -- Get student name
            SELECT full_name INTO student_name 
            FROM users WHERE id = NEW.student_id;
            
            -- Get course title and instructor name
            SELECT c.title, u.full_name INTO course_title, instructor_name
            FROM courses c
            INNER JOIN users u ON c.instructor_id = u.id
            WHERE c.id = NEW.course_id;
            
            -- Generate certificate number
            CALL generate_certificate_number(NEW.course_id, NEW.student_id, @cert_num);
            
            -- Generate verification code
            SET verify_code = CONCAT('VER-', UNIX_TIMESTAMP(), '-', SUBSTRING(MD5(RAND()), 1, 8));
            
            -- Insert certificate
            INSERT INTO course_certificates (
                student_id, 
                course_id, 
                certificate_number, 
                student_name,
                course_title,
                instructor_name,
                completion_date,
                verification_code,
                final_grade
            ) VALUES (
                NEW.student_id,
                NEW.course_id,
                @cert_num,
                student_name,
                course_title,
                instructor_name,
                CURDATE(),
                verify_code,
                NEW.progress_percent
            );
        END IF;
    END IF;
END //

DELIMITER ;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================

CREATE INDEX idx_cert_student_course ON course_certificates(student_id, course_id);
CREATE INDEX idx_cert_issue_date ON course_certificates(issue_date);
CREATE INDEX idx_template_active ON certificate_templates(is_active, is_default);

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================

SELECT '✅ Certificates system database schema created successfully!' AS message;
SELECT 'Tables created: course_certificates, certificate_templates, certificate_verifications, certificate_shares' AS tables;
SELECT 'Views created: certificate_details' AS views;
SELECT 'Triggers created: after_course_completed (auto-generates certificates at 100%)' AS triggers;
SELECT 'Default templates: default, modern, classic' AS templates;
