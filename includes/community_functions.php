<?php

/**
 * Community System Functions
 * Helper functions for the community system
 */

// Get user's communities
function get_user_communities($user_id, $limit = null)
{
    global $pdo;

    $sql = "
        SELECT c.*, cm.role, cm.joined_at,
               COUNT(DISTINCT other_members.user_id) as member_count,
               COUNT(DISTINCT cp.id) as post_count
        FROM communities c
        INNER JOIN community_members cm ON c.id = cm.community_id
        LEFT JOIN community_members other_members ON c.id = other_members.community_id AND other_members.status = 'active'
        LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
        WHERE cm.user_id = ? AND cm.status = 'active' AND c.status = 'active'
        GROUP BY c.id
        ORDER BY cm.joined_at DESC
    ";

    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get community members
function get_community_members($community_id, $limit = null)
{
    global $pdo;

    $sql = "
        SELECT cm.*, u.fullname, u.profile_image, u.role as user_role
        FROM community_members cm
        LEFT JOIN users u ON cm.user_id = u.id
        WHERE cm.community_id = ? AND cm.status = 'active'
        ORDER BY 
            CASE cm.role 
                WHEN 'admin' THEN 1 
                WHEN 'moderator' THEN 2 
                WHEN 'member' THEN 3 
            END,
            cm.joined_at ASC
    ";

    if ($limit) {
        $sql .= " LIMIT " . (int)$limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$community_id]);
    return $stmt->fetchAll();
}

// Get community posts
function get_community_posts($community_id, $limit = 10, $offset = 0)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT cp.*, u.fullname as author_name, u.profile_image as author_image,
               COUNT(DISTINCT pc.id) as comment_count,
               COUNT(DISTINCT pl.id) as like_count
        FROM community_posts cp
        LEFT JOIN users u ON cp.author_id = u.id
        LEFT JOIN post_comments pc ON cp.id = pc.post_id AND pc.status = 'published'
        LEFT JOIN post_likes pl ON cp.id = pl.post_id
        WHERE cp.community_id = ? AND cp.status = 'published'
        GROUP BY cp.id
        ORDER BY cp.is_pinned DESC, cp.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$community_id, $limit, $offset]);
    return $stmt->fetchAll();
}

// Check if user is member of community
function is_community_member($community_id, $user_id)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT cm.role 
        FROM community_members cm
        WHERE cm.community_id = ? AND cm.user_id = ? AND cm.status = 'active'
    ");
    $stmt->execute([$community_id, $user_id]);
    $result = $stmt->fetch();

    return $result ? $result['role'] : false;
}

// Get community statistics
function get_community_stats($community_id)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT cm.user_id) as member_count,
            COUNT(DISTINCT cp.id) as post_count,
            COUNT(DISTINCT pc.id) as comment_count,
            COUNT(DISTINCT pl.id) as like_count
        FROM communities c
        LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.status = 'active'
        LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
        LEFT JOIN post_comments pc ON cp.id = pc.post_id AND pc.status = 'published'
        LEFT JOIN post_likes pl ON cp.id = pl.post_id
        WHERE c.id = ?
    ");
    $stmt->execute([$community_id]);
    return $stmt->fetch();
}

// Get trending communities
function get_trending_communities($limit = 10)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(DISTINCT cm.user_id) as member_count,
               COUNT(DISTINCT cp.id) as post_count,
               COUNT(DISTINCT pc.id) as comment_count
        FROM communities c
        LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.status = 'active'
        LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
        LEFT JOIN post_comments pc ON cp.id = pc.post_id AND pc.status = 'published'
        WHERE c.status = 'active' AND c.privacy = 'public'
        GROUP BY c.id
        ORDER BY (member_count * 0.3 + post_count * 0.4 + comment_count * 0.3) DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// Get recent activity for user
function get_user_community_activity($user_id, $limit = 20)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT 'post' as type, cp.id, cp.title, cp.content, cp.created_at, c.name as community_name, c.id as community_id
        FROM community_posts cp
        INNER JOIN communities c ON cp.community_id = c.id
        INNER JOIN community_members cm ON c.id = cm.community_id
        WHERE cm.user_id = ? AND cm.status = 'active' AND cp.status = 'published'
        UNION ALL
        SELECT 'comment' as type, pc.id, NULL as title, pc.content, pc.created_at, c.name as community_name, c.id as community_id
        FROM post_comments pc
        INNER JOIN community_posts cp ON pc.post_id = cp.id
        INNER JOIN communities c ON cp.community_id = c.id
        INNER JOIN community_members cm ON c.id = cm.community_id
        WHERE cm.user_id = ? AND cm.status = 'active' AND pc.status = 'published'
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $user_id, $limit]);
    return $stmt->fetchAll();
}

// Send community notification
function send_community_notification($user_id, $community_id, $type, $title, $message, $related_post_id = null, $related_comment_id = null)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO community_notifications 
            (user_id, community_id, type, title, message, related_post_id, related_comment_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $community_id, $type, $title, $message, $related_post_id, $related_comment_id]);
    } catch (Exception $e) {
        return false;
    }
}

// Get user's unread notifications
function get_user_notifications($user_id, $limit = 20)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT cn.*, c.name as community_name
        FROM community_notifications cn
        LEFT JOIN communities c ON cn.community_id = c.id
        WHERE cn.user_id = ? AND cn.is_read = 0
        ORDER BY cn.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

// Mark notification as read
function mark_notification_read($notification_id, $user_id)
{
    global $pdo;

    $stmt = $pdo->prepare("
        UPDATE community_notifications 
        SET is_read = 1 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$notification_id, $user_id]);
}

// Get community categories
function get_community_categories($active_only = true)
{
    global $pdo;

    $sql = "SELECT * FROM community_categories";
    if ($active_only) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order, name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Search communities
function search_communities($query, $category_id = null, $privacy = null, $limit = 20, $offset = 0)
{
    global $pdo;

    $where_conditions = ["c.status = 'active'"];
    $params = [];

    if ($query) {
        $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
        $params[] = "%$query%";
        $params[] = "%$query%";
    }

    if ($category_id) {
        $where_conditions[] = "cca.category_id = ?";
        $params[] = $category_id;
    }

    if ($privacy) {
        $where_conditions[] = "c.privacy = ?";
        $params[] = $privacy;
    }

    $where_clause = implode(' AND ', $where_conditions);

    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.fullname as creator_name,
               COUNT(DISTINCT cm.user_id) as member_count,
               COUNT(DISTINCT cp.id) as post_count
        FROM communities c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.status = 'active'
        LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
        LEFT JOIN community_category_assignments cca ON c.id = cca.community_id
        WHERE $where_clause
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Check if user can create communities
function can_create_communities($user_id)
{
    global $pdo;

    // Admins and instructors can always create communities
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (in_array($user['role'], ['admin', 'instructor'])) {
        return true;
    }

    // Check if students/vendors are allowed to create communities
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'allow_student_communities'");
    $stmt->execute();
    $setting = $stmt->fetch();

    return $setting && $setting['setting_value'] === '1';
}

// Get community permissions for user
function get_community_permissions($community_id, $user_id)
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT cm.role, c.created_by
        FROM community_members cm
        INNER JOIN communities c ON cm.community_id = c.id
        WHERE cm.community_id = ? AND cm.user_id = ? AND cm.status = 'active'
    ");
    $stmt->execute([$community_id, $user_id]);
    $membership = $stmt->fetch();

    if (!$membership) {
        return [
            'can_view' => false,
            'can_post' => false,
            'can_comment' => false,
            'can_moderate' => false,
            'can_manage' => false
        ];
    }

    $is_creator = $membership['created_by'] == $user_id;
    $is_admin = $membership['role'] === 'admin';
    $is_moderator = $membership['role'] === 'moderator';

    return [
        'can_view' => true,
        'can_post' => true,
        'can_comment' => true,
        'can_moderate' => $is_admin || $is_moderator,
        'can_manage' => $is_creator || $is_admin
    ];
}






