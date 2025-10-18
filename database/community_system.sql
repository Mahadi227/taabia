-- Community System Database Schema
-- Mini social community system integrated into TaaBia Skills & Market

-- Communities table
CREATE TABLE communities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    cover_image VARCHAR(255),
    created_by INT NOT NULL,
    privacy ENUM(
        'public',
        'private',
        'invite_only'
    ) DEFAULT 'public',
    status ENUM(
        'active',
        'archived',
        'suspended'
    ) DEFAULT 'active',
    member_count INT DEFAULT 0,
    post_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE CASCADE
);

-- Community members table
CREATE TABLE community_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    role ENUM(
        'member',
        'moderator',
        'admin'
    ) DEFAULT 'member',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'banned', 'left') DEFAULT 'active',
    FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY unique_membership (community_id, user_id)
);

-- Community posts table
CREATE TABLE community_posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    author_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT NOT NULL,
    post_type ENUM(
        'text',
        'image',
        'video',
        'link',
        'poll'
    ) DEFAULT 'text',
    media_url VARCHAR(255),
    poll_options JSON,
    poll_end_date TIMESTAMP NULL,
    is_pinned BOOLEAN DEFAULT FALSE,
    is_announcement BOOLEAN DEFAULT FALSE,
    status ENUM(
        'published',
        'draft',
        'archived',
        'deleted'
    ) DEFAULT 'published',
    like_count INT DEFAULT 0,
    comment_count INT DEFAULT 0,
    view_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Post comments table
CREATE TABLE post_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    author_id INT NOT NULL,
    parent_id INT NULL,
    content TEXT NOT NULL,
    like_count INT DEFAULT 0,
    status ENUM(
        'published',
        'deleted',
        'hidden'
    ) DEFAULT 'published',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts (id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES post_comments (id) ON DELETE CASCADE
);

-- Post likes table
CREATE TABLE post_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES community_posts (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY unique_post_like (post_id, user_id)
);

-- Comment likes table
CREATE TABLE comment_likes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (comment_id) REFERENCES post_comments (id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE KEY unique_comment_like (comment_id, user_id)
);

-- Community invitations table
CREATE TABLE community_invitations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    community_id INT NOT NULL,
    invited_by INT NOT NULL,
    invited_user_id INT NULL,
    invited_email VARCHAR(255) NULL,
    role ENUM('member', 'moderator') DEFAULT 'member',
    status ENUM(
        'pending',
        'accepted',
        'declined',
        'expired'
    ) DEFAULT 'pending',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE CASCADE,
    FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (invited_user_id) REFERENCES users (id) ON DELETE CASCADE
);

-- Community notifications table
CREATE TABLE community_notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    community_id INT NOT NULL,
    type ENUM(
        'new_post',
        'new_comment',
        'new_like',
        'new_member',
        'post_mentioned',
        'comment_mentioned'
    ) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    related_post_id INT NULL,
    related_comment_id INT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE CASCADE,
    FOREIGN KEY (related_post_id) REFERENCES community_posts (id) ON DELETE CASCADE,
    FOREIGN KEY (related_comment_id) REFERENCES post_comments (id) ON DELETE CASCADE
);

-- Community categories table
CREATE TABLE community_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#007bff',
    icon VARCHAR(50),
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Community category assignments
CREATE TABLE community_category_assignments (
    community_id INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (community_id, category_id),
    FOREIGN KEY (community_id) REFERENCES communities (id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES community_categories (id) ON DELETE CASCADE
);

-- Insert default community categories
INSERT INTO
    community_categories (
        name,
        description,
        color,
        icon,
        sort_order
    )
VALUES (
        'Formation & Éducation',
        'Communautés dédiées à l\'apprentissage et à la formation',
        '#28a745',
        'fas fa-graduation-cap',
        1
    ),
    (
        'Technologie',
        'Discussions sur les nouvelles technologies et innovations',
        '#007bff',
        'fas fa-laptop-code',
        2
    ),
    (
        'Business & Entrepreneuriat',
        'Échanges sur les stratégies d\'entreprise et l\'entrepreneuriat',
        '#ffc107',
        'fas fa-briefcase',
        3
    ),
    (
        'Développement Personnel',
        'Conseils et partages sur le développement personnel',
        '#e83e8c',
        'fas fa-user-friends',
        4
    ),
    (
        'Réseautage',
        'Opportunités de networking et de collaboration',
        '#6f42c1',
        'fas fa-network-wired',
        5
    ),
    (
        'Général',
        'Discussions générales et échanges libres',
        '#6c757d',
        'fas fa-comments',
        6
    );

-- Insert sample communities
INSERT INTO
    communities (
        name,
        description,
        created_by,
        privacy,
        member_count,
        post_count
    )
VALUES (
        'Développeurs Web',
        'Communauté pour les développeurs web et les passionnés de programmation',
        1,
        'public',
        0,
        0
    ),
    (
        'Entrepreneurs TaaBia',
        'Espace d\'échange pour les entrepreneurs de la plateforme',
        1,
        'public',
        0,
        0
    ),
    (
        'Formation Continue',
        'Partage d\'expériences et conseils en formation professionnelle',
        1,
        'public',
        0,
        0
    );

-- Insert sample community category assignments
INSERT INTO
    community_category_assignments (community_id, category_id)
VALUES (1, 2), -- Développeurs Web -> Technologie
    (2, 3), -- Entrepreneurs TaaBia -> Business & Entrepreneuriat
    (3, 1);
-- Formation Continue -> Formation & Éducation

-- Create indexes for better performance
CREATE INDEX idx_communities_status ON communities (status);

CREATE INDEX idx_communities_created_by ON communities (created_by);

CREATE INDEX idx_community_members_user ON community_members (user_id);

CREATE INDEX idx_community_members_community ON community_members (community_id);

CREATE INDEX idx_community_posts_community ON community_posts (community_id);

CREATE INDEX idx_community_posts_author ON community_posts (author_id);

CREATE INDEX idx_community_posts_status ON community_posts (status);

CREATE INDEX idx_post_comments_post ON post_comments (post_id);

CREATE INDEX idx_post_comments_author ON post_comments (author_id);

CREATE INDEX idx_community_notifications_user ON community_notifications (user_id);

CREATE INDEX idx_community_notifications_read ON community_notifications (is_read);