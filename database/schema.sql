-- TaaBia Skills & Market Database Schema
-- Comprehensive database structure for the integrated learning and e-commerce platform

-- Users table (multi-role: admin, instructor, student, vendor)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'instructor', 'student', 'vendor') NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    bio TEXT,
    profile_image VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Courses table
CREATE TABLE courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    instructor_id INT NOT NULL,
    category VARCHAR(100),
    level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    duration VARCHAR(50),
    image_url VARCHAR(255),
    video_url VARCHAR(255),
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Course lessons/content
CREATE TABLE course_contents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    course_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    content_type ENUM('video', 'pdf', 'text', 'quiz') DEFAULT 'text',
    file_url VARCHAR(255),
    order_index INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- Student course enrollments
CREATE TABLE student_courses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_percent INT DEFAULT 0,
    completed_at TIMESTAMP NULL,
    status ENUM('active', 'completed', 'dropped') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, course_id)
);

-- Products table (for vendors)
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    vendor_id INT NOT NULL,
    category VARCHAR(100),
    stock_quantity INT DEFAULT 0,
    image_url VARCHAR(255),
    status ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders table
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    buyer_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid', 'shipped', 'delivered', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50),
    shipping_address TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items (for products)
CREATE TABLE order_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT,
    course_id INT,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
);

-- Transactions table
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_id VARCHAR(100) UNIQUE NOT NULL,
    order_id INT,
    student_id INT,
    instructor_id INT,
    vendor_id INT,
    type ENUM('course_purchase', 'product_purchase', 'payout', 'refund') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'GHS',
    payment_gateway VARCHAR(50),
    gateway_transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (instructor_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Events table
CREATE TABLE events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATETIME NOT NULL,
    duration INT DEFAULT 60, -- in minutes
    max_participants INT,
    price DECIMAL(10,2) DEFAULT 0.00,
    organizer_id INT NOT NULL,
    event_type ENUM('webinar', 'workshop', 'meetup', 'conference') DEFAULT 'webinar',
    meeting_url VARCHAR(255),
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organizer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Event registrations
CREATE TABLE event_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
);

-- Messages table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payout requests
CREATE TABLE payout_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    account_details TEXT,
    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Course submissions (assignments)
CREATE TABLE course_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    content_id INT NOT NULL,
    submission_text TEXT,
    file_url VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted',
    grade DECIMAL(5,2),
    feedback TEXT,
    graded_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES course_contents(id) ON DELETE CASCADE
);

-- Contact messages
CREATE TABLE contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('platform_name', 'TaaBia Skills & Market', 'Platform name'),
('platform_description', 'Integrated learning and e-commerce platform', 'Platform description'),
('currency', 'GHS', 'Default currency'),
('commission_rate', '10', 'Platform commission rate in percentage'),
('min_payout_amount', '50', 'Minimum payout amount'),
('max_file_size', '10485760', 'Maximum file upload size in bytes');

-- Insert default admin user (password: admin123)
INSERT INTO users (fullname, email, password, role, is_active, email_verified) VALUES
('Admin', 'admin@taabia.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, TRUE);

-- Blog System Tables
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    featured_image VARCHAR(255),
    author_id INT NOT NULL,
    category_id INT,
    status ENUM('published', 'draft', 'archived') DEFAULT 'draft',
    published_at TIMESTAMP NULL,
    meta_title VARCHAR(255),
    meta_description TEXT,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS blog_tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    slug VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

-- Insert default blog categories
INSERT INTO blog_categories (name, slug, description) VALUES
('Formation', 'formation', 'Articles sur les tendances et innovations en formation'),
('Développement Personnel', 'developpement-personnel', 'Conseils et stratégies pour le développement personnel'),
('Technologie', 'technologie', 'Actualités et innovations technologiques'),
('Entreprise', 'entreprise', 'Conseils et stratégies pour les entreprises'),
('Événements', 'evenements', 'Actualités et comptes-rendus d\'événements');

-- Insert sample blog posts
INSERT INTO blog_posts (title, slug, content, excerpt, author_id, category_id, status, published_at, meta_title, meta_description) VALUES
('Les Tendances de la Formation en 2024', 'tendances-formation-2024', '<h2>Introduction</h2><p>L\'année 2024 marque un tournant majeur dans le domaine de la formation professionnelle...</p><h2>1. L\'Intelligence Artificielle dans la Formation</h2><p>L\'IA révolutionne la façon dont nous apprenons...</p><h2>2. La Formation Hybride</h2><p>La combinaison du présentiel et du digital...</p><h2>3. L\'Apprentissage Personnalisé</h2><p>Chaque apprenant a des besoins uniques...</p><h2>Conclusion</h2><p>Ces tendances façonnent l\'avenir de la formation...</p>', 'Découvrez les nouvelles approches de formation qui révolutionnent l\'apprentissage professionnel et améliorent l\'engagement des apprenants.', 1, 1, 'published', '2024-01-15 10:00:00', 'Tendances Formation 2024', 'Découvrez les nouvelles tendances en formation professionnelle pour 2024'),
('L\'Importance de la Formation Continue', 'importance-formation-continue', '<h2>Pourquoi la Formation Continue est Essentielle</h2><p>Dans un monde en constante évolution...</p><h2>Avantages de la Formation Continue</h2><ul><li>Mise à jour des compétences</li><li>Adaptation aux nouvelles technologies</li><li>Amélioration de la productivité</li></ul><h2>Stratégies de Formation Continue</h2><p>Voici comment intégrer la formation continue...</p>', 'Pourquoi la formation continue est essentielle pour maintenir la compétitivité dans un marché en constante évolution.', 1, 2, 'published', '2024-01-10 14:30:00', 'Formation Continue', 'L\'importance de la formation continue dans le monde professionnel'),
('Formation à Distance : Bonnes Pratiques', 'formation-distance-bonnes-pratiques', '<h2>Les Défis de la Formation à Distance</h2><p>La formation à distance présente des défis uniques...</p><h2>Bonnes Pratiques</h2><ol><li>Créer un environnement d\'apprentissage engageant</li><li>Utiliser des outils interactifs</li><li>Maintenir une communication régulière</li></ol><h2>Outils Recommandés</h2><p>Voici les outils les plus efficaces...</p>', 'Conseils et stratégies pour optimiser l\'efficacité de vos programmes de formation à distance.', 1, 1, 'published', '2024-01-05 09:15:00', 'Formation à Distance', 'Bonnes pratiques pour la formation à distance');


-- Event registrations

CREATE TABLE event_registrations (

    id INT PRIMARY KEY AUTO_INCREMENT,

    event_id INT NOT NULL,

    participant_id INT NOT NULL,

    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM('registered', 'attended', 'no_show', 'cancelled') DEFAULT 'registered',

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,

    FOREIGN KEY (participant_id) REFERENCES users(id) ON DELETE CASCADE,

    UNIQUE KEY unique_registration (event_id, participant_id)

);



-- Messages table

CREATE TABLE messages (

    id INT PRIMARY KEY AUTO_INCREMENT,

    sender_id INT NOT NULL,

    receiver_id INT NOT NULL,

    subject VARCHAR(255),

    message TEXT NOT NULL,

    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,

    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE

);



-- Payout requests

CREATE TABLE payout_requests (

    id INT PRIMARY KEY AUTO_INCREMENT,

    user_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    payment_method VARCHAR(50),

    account_details TEXT,

    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',

    processed_at TIMESTAMP NULL,

    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

);



-- Course submissions (assignments)

CREATE TABLE course_submissions (

    id INT PRIMARY KEY AUTO_INCREMENT,

    student_id INT NOT NULL,

    course_id INT NOT NULL,

    content_id INT NOT NULL,

    submission_text TEXT,

    file_url VARCHAR(255),

    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted',

    grade DECIMAL(5,2),

    feedback TEXT,

    graded_at TIMESTAMP NULL,

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,

    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,

    FOREIGN KEY (content_id) REFERENCES course_contents(id) ON DELETE CASCADE

);



-- Contact messages

CREATE TABLE contact_messages (

    id INT PRIMARY KEY AUTO_INCREMENT,

    name VARCHAR(255) NOT NULL,

    email VARCHAR(255) NOT NULL,

    subject VARCHAR(255),

    message TEXT NOT NULL,

    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);



-- System settings

CREATE TABLE system_settings (

    id INT PRIMARY KEY AUTO_INCREMENT,

    setting_key VARCHAR(100) UNIQUE NOT NULL,

    setting_value TEXT,

    description TEXT,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);



-- Insert default system settings

INSERT INTO system_settings (setting_key, setting_value, description) VALUES

('platform_name', 'TaaBia Skills & Market', 'Platform name'),

('platform_description', 'Integrated learning and e-commerce platform', 'Platform description'),

('currency', 'GHS', 'Default currency'),

('commission_rate', '10', 'Platform commission rate in percentage'),

('min_payout_amount', '50', 'Minimum payout amount'),

('max_file_size', '10485760', 'Maximum file upload size in bytes');



-- Insert default admin user (password: admin123)

INSERT INTO users (fullname, email, password, role, is_active, email_verified) VALUES

('Admin', 'admin@taabia.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, TRUE);



-- Blog System Tables

CREATE TABLE IF NOT EXISTS blog_categories (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    slug VARCHAR(100) UNIQUE NOT NULL,

    description TEXT,

    status ENUM('active', 'inactive') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);



CREATE TABLE IF NOT EXISTS blog_posts (

    id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,

    slug VARCHAR(255) UNIQUE NOT NULL,

    content LONGTEXT NOT NULL,

    excerpt TEXT,

    featured_image VARCHAR(255),

    author_id INT NOT NULL,

    category_id INT,

    status ENUM('published', 'draft', 'archived') DEFAULT 'draft',

    published_at TIMESTAMP NULL,

    meta_title VARCHAR(255),

    meta_description TEXT,

    view_count INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,

    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL

);



CREATE TABLE IF NOT EXISTS blog_tags (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(50) NOT NULL,

    slug VARCHAR(50) UNIQUE NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);



CREATE TABLE IF NOT EXISTS blog_post_tags (

    post_id INT NOT NULL,

    tag_id INT NOT NULL,

    PRIMARY KEY (post_id, tag_id),

    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,

    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE

);



-- Insert default blog categories

INSERT INTO blog_categories (name, slug, description) VALUES

('Formation', 'formation', 'Articles sur les tendances et innovations en formation'),

('Développement Personnel', 'developpement-personnel', 'Conseils et stratégies pour le développement personnel'),

('Technologie', 'technologie', 'Actualités et innovations technologiques'),

('Entreprise', 'entreprise', 'Conseils et stratégies pour les entreprises'),

('Événements', 'evenements', 'Actualités et comptes-rendus d\'événements');



-- Insert sample blog posts

INSERT INTO blog_posts (title, slug, content, excerpt, author_id, category_id, status, published_at, meta_title, meta_description) VALUES

('Les Tendances de la Formation en 2024', 'tendances-formation-2024', '<h2>Introduction</h2><p>L\'année 2024 marque un tournant majeur dans le domaine de la formation professionnelle...</p><h2>1. L\'Intelligence Artificielle dans la Formation</h2><p>L\'IA révolutionne la façon dont nous apprenons...</p><h2>2. La Formation Hybride</h2><p>La combinaison du présentiel et du digital...</p><h2>3. L\'Apprentissage Personnalisé</h2><p>Chaque apprenant a des besoins uniques...</p><h2>Conclusion</h2><p>Ces tendances façonnent l\'avenir de la formation...</p>', 'Découvrez les nouvelles approches de formation qui révolutionnent l\'apprentissage professionnel et améliorent l\'engagement des apprenants.', 1, 1, 'published', '2024-01-15 10:00:00', 'Tendances Formation 2024', 'Découvrez les nouvelles tendances en formation professionnelle pour 2024'),

('L\'Importance de la Formation Continue', 'importance-formation-continue', '<h2>Pourquoi la Formation Continue est Essentielle</h2><p>Dans un monde en constante évolution...</p><h2>Avantages de la Formation Continue</h2><ul><li>Mise à jour des compétences</li><li>Adaptation aux nouvelles technologies</li><li>Amélioration de la productivité</li></ul><h2>Stratégies de Formation Continue</h2><p>Voici comment intégrer la formation continue...</p>', 'Pourquoi la formation continue est essentielle pour maintenir la compétitivité dans un marché en constante évolution.', 1, 2, 'published', '2024-01-10 14:30:00', 'Formation Continue', 'L\'importance de la formation continue dans le monde professionnel'),

('Formation à Distance : Bonnes Pratiques', 'formation-distance-bonnes-pratiques', '<h2>Les Défis de la Formation à Distance</h2><p>La formation à distance présente des défis uniques...</p><h2>Bonnes Pratiques</h2><ol><li>Créer un environnement d\'apprentissage engageant</li><li>Utiliser des outils interactifs</li><li>Maintenir une communication régulière</li></ol><h2>Outils Recommandés</h2><p>Voici les outils les plus efficaces...</p>', 'Conseils et stratégies pour optimiser l\'efficacité de vos programmes de formation à distance.', 1, 1, 'published', '2024-01-05 09:15:00', 'Formation à Distance', 'Bonnes pratiques pour la formation à distance');




-- Event registrations

CREATE TABLE event_registrations (

    id INT PRIMARY KEY AUTO_INCREMENT,

    event_id INT NOT NULL,

    participant_id INT NOT NULL,

    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM('registered', 'attended', 'no_show', 'cancelled') DEFAULT 'registered',

    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,

    FOREIGN KEY (participant_id) REFERENCES users(id) ON DELETE CASCADE,

    UNIQUE KEY unique_registration (event_id, participant_id)

);



-- Messages table

CREATE TABLE messages (

    id INT PRIMARY KEY AUTO_INCREMENT,

    sender_id INT NOT NULL,

    receiver_id INT NOT NULL,

    subject VARCHAR(255),

    message TEXT NOT NULL,

    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,

    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE

);



-- Payout requests

CREATE TABLE payout_requests (

    id INT PRIMARY KEY AUTO_INCREMENT,

    user_id INT NOT NULL,

    amount DECIMAL(10,2) NOT NULL,

    payment_method VARCHAR(50),

    account_details TEXT,

    status ENUM('pending', 'approved', 'rejected', 'processed') DEFAULT 'pending',

    processed_at TIMESTAMP NULL,

    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE

);



-- Course submissions (assignments)

CREATE TABLE course_submissions (

    id INT PRIMARY KEY AUTO_INCREMENT,

    student_id INT NOT NULL,

    course_id INT NOT NULL,

    content_id INT NOT NULL,

    submission_text TEXT,

    file_url VARCHAR(255),

    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    status ENUM('submitted', 'graded', 'returned') DEFAULT 'submitted',

    grade DECIMAL(5,2),

    feedback TEXT,

    graded_at TIMESTAMP NULL,

    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,

    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,

    FOREIGN KEY (content_id) REFERENCES course_contents(id) ON DELETE CASCADE

);



-- Contact messages

CREATE TABLE contact_messages (

    id INT PRIMARY KEY AUTO_INCREMENT,

    name VARCHAR(255) NOT NULL,

    email VARCHAR(255) NOT NULL,

    subject VARCHAR(255),

    message TEXT NOT NULL,

    is_read BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);



-- System settings

CREATE TABLE system_settings (

    id INT PRIMARY KEY AUTO_INCREMENT,

    setting_key VARCHAR(100) UNIQUE NOT NULL,

    setting_value TEXT,

    description TEXT,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);



-- Insert default system settings

INSERT INTO system_settings (setting_key, setting_value, description) VALUES

('platform_name', 'TaaBia Skills & Market', 'Platform name'),

('platform_description', 'Integrated learning and e-commerce platform', 'Platform description'),

('currency', 'GHS', 'Default currency'),

('commission_rate', '10', 'Platform commission rate in percentage'),

('min_payout_amount', '50', 'Minimum payout amount'),

('max_file_size', '10485760', 'Maximum file upload size in bytes');



-- Insert default admin user (password: admin123)

INSERT INTO users (fullname, email, password, role, is_active, email_verified) VALUES

('Admin', 'admin@taabia.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', TRUE, TRUE);



-- Blog System Tables

CREATE TABLE IF NOT EXISTS blog_categories (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(100) NOT NULL,

    slug VARCHAR(100) UNIQUE NOT NULL,

    description TEXT,

    status ENUM('active', 'inactive') DEFAULT 'active',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

);



CREATE TABLE IF NOT EXISTS blog_posts (

    id INT AUTO_INCREMENT PRIMARY KEY,

    title VARCHAR(255) NOT NULL,

    slug VARCHAR(255) UNIQUE NOT NULL,

    content LONGTEXT NOT NULL,

    excerpt TEXT,

    featured_image VARCHAR(255),

    author_id INT NOT NULL,

    category_id INT,

    status ENUM('published', 'draft', 'archived') DEFAULT 'draft',

    published_at TIMESTAMP NULL,

    meta_title VARCHAR(255),

    meta_description TEXT,

    view_count INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,

    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL

);



CREATE TABLE IF NOT EXISTS blog_tags (

    id INT AUTO_INCREMENT PRIMARY KEY,

    name VARCHAR(50) NOT NULL,

    slug VARCHAR(50) UNIQUE NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

);



CREATE TABLE IF NOT EXISTS blog_post_tags (

    post_id INT NOT NULL,

    tag_id INT NOT NULL,

    PRIMARY KEY (post_id, tag_id),

    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,

    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE

);



-- Insert default blog categories

INSERT INTO blog_categories (name, slug, description) VALUES

('Formation', 'formation', 'Articles sur les tendances et innovations en formation'),

('Développement Personnel', 'developpement-personnel', 'Conseils et stratégies pour le développement personnel'),

('Technologie', 'technologie', 'Actualités et innovations technologiques'),

('Entreprise', 'entreprise', 'Conseils et stratégies pour les entreprises'),

('Événements', 'evenements', 'Actualités et comptes-rendus d\'événements');



-- Insert sample blog posts

INSERT INTO blog_posts (title, slug, content, excerpt, author_id, category_id, status, published_at, meta_title, meta_description) VALUES

('Les Tendances de la Formation en 2024', 'tendances-formation-2024', '<h2>Introduction</h2><p>L\'année 2024 marque un tournant majeur dans le domaine de la formation professionnelle...</p><h2>1. L\'Intelligence Artificielle dans la Formation</h2><p>L\'IA révolutionne la façon dont nous apprenons...</p><h2>2. La Formation Hybride</h2><p>La combinaison du présentiel et du digital...</p><h2>3. L\'Apprentissage Personnalisé</h2><p>Chaque apprenant a des besoins uniques...</p><h2>Conclusion</h2><p>Ces tendances façonnent l\'avenir de la formation...</p>', 'Découvrez les nouvelles approches de formation qui révolutionnent l\'apprentissage professionnel et améliorent l\'engagement des apprenants.', 1, 1, 'published', '2024-01-15 10:00:00', 'Tendances Formation 2024', 'Découvrez les nouvelles tendances en formation professionnelle pour 2024'),

('L\'Importance de la Formation Continue', 'importance-formation-continue', '<h2>Pourquoi la Formation Continue est Essentielle</h2><p>Dans un monde en constante évolution...</p><h2>Avantages de la Formation Continue</h2><ul><li>Mise à jour des compétences</li><li>Adaptation aux nouvelles technologies</li><li>Amélioration de la productivité</li></ul><h2>Stratégies de Formation Continue</h2><p>Voici comment intégrer la formation continue...</p>', 'Pourquoi la formation continue est essentielle pour maintenir la compétitivité dans un marché en constante évolution.', 1, 2, 'published', '2024-01-10 14:30:00', 'Formation Continue', 'L\'importance de la formation continue dans le monde professionnel'),

('Formation à Distance : Bonnes Pratiques', 'formation-distance-bonnes-pratiques', '<h2>Les Défis de la Formation à Distance</h2><p>La formation à distance présente des défis uniques...</p><h2>Bonnes Pratiques</h2><ol><li>Créer un environnement d\'apprentissage engageant</li><li>Utiliser des outils interactifs</li><li>Maintenir une communication régulière</li></ol><h2>Outils Recommandés</h2><p>Voici les outils les plus efficaces...</p>', 'Conseils et stratégies pour optimiser l\'efficacité de vos programmes de formation à distance.', 1, 1, 'published', '2024-01-05 09:15:00', 'Formation à Distance', 'Bonnes pratiques pour la formation à distance');