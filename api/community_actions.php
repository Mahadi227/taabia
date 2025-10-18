<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => t('unauthorized')]);
    exit;
}

$current_user_id = current_user_id();

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'like_post':
        $post_id = (int)($_POST['post_id'] ?? 0);

        if (!$post_id) {
            echo json_encode(['success' => false, 'message' => t('invalid_post_id')]);
            exit;
        }

        try {
            // Check if user already liked this post
            $stmt = $pdo->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$post_id, $current_user_id]);
            $existing_like = $stmt->fetch();

            if ($existing_like) {
                // Unlike the post
                $stmt = $pdo->prepare("DELETE FROM post_likes WHERE id = ?");
                $stmt->execute([$existing_like['id']]);

                // Update like count
                $stmt = $pdo->prepare("UPDATE community_posts SET like_count = like_count - 1 WHERE id = ?");
                $stmt->execute([$post_id]);

                echo json_encode(['success' => true, 'liked' => false, 'message' => t('post_unliked')]);
            } else {
                // Like the post
                $stmt = $pdo->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
                $stmt->execute([$post_id, $current_user_id]);

                // Update like count
                $stmt = $pdo->prepare("UPDATE community_posts SET like_count = like_count + 1 WHERE id = ?");
                $stmt->execute([$post_id]);

                echo json_encode(['success' => true, 'liked' => true, 'message' => t('post_liked')]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => t('error_processing_like')]);
        }
        break;

    case 'add_comment':
        $post_id = (int)($_POST['post_id'] ?? 0);
        $content = sanitize($_POST['content'] ?? '');
        $parent_id = (int)($_POST['parent_id'] ?? 0);

        if (!$post_id || !$content) {
            echo json_encode(['success' => false, 'message' => t('invalid_comment_data')]);
            exit;
        }

        try {
            // Check if user is member of the community
            $stmt = $pdo->prepare("
                SELECT cm.id 
                FROM community_members cm
                INNER JOIN community_posts cp ON cm.community_id = cp.community_id
                WHERE cp.id = ? AND cm.user_id = ? AND cm.status = 'active'
            ");
            $stmt->execute([$post_id, $current_user_id]);

            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => t('not_community_member')]);
                exit;
            }

            // Add comment
            $stmt = $pdo->prepare("
                INSERT INTO post_comments (post_id, author_id, content, parent_id) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$post_id, $current_user_id, $content, $parent_id ?: null]);
            $comment_id = $pdo->lastInsertId();

            // Update comment count
            $stmt = $pdo->prepare("UPDATE community_posts SET comment_count = comment_count + 1 WHERE id = ?");
            $stmt->execute([$post_id]);

            // Get comment details for response
            $stmt = $pdo->prepare("
                SELECT pc.*, u.fullname as author_name, u.profile_image as author_image
                FROM post_comments pc
                LEFT JOIN users u ON pc.author_id = u.id
                WHERE pc.id = ?
            ");
            $stmt->execute([$comment_id]);
            $comment = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'message' => t('comment_added_successfully'),
                'comment' => $comment
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => t('error_adding_comment')]);
        }
        break;

    case 'get_comments':
        $post_id = (int)($_GET['post_id'] ?? 0);

        if (!$post_id) {
            echo json_encode(['success' => false, 'message' => t('invalid_post_id')]);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT pc.*, u.fullname as author_name, u.profile_image as author_image,
                       COUNT(DISTINCT cl.id) as like_count,
                       CASE WHEN my_like.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
                FROM post_comments pc
                LEFT JOIN users u ON pc.author_id = u.id
                LEFT JOIN comment_likes cl ON pc.id = cl.comment_id
                LEFT JOIN comment_likes my_like ON pc.id = my_like.comment_id AND my_like.user_id = ?
                WHERE pc.post_id = ? AND pc.status = 'published'
                GROUP BY pc.id
                ORDER BY pc.created_at ASC
            ");
            $stmt->execute([$current_user_id, $post_id]);
            $comments = $stmt->fetchAll();

            echo json_encode(['success' => true, 'comments' => $comments]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => t('error_loading_comments')]);
        }
        break;

    case 'like_comment':
        $comment_id = (int)($_POST['comment_id'] ?? 0);

        if (!$comment_id) {
            echo json_encode(['success' => false, 'message' => t('invalid_comment_id')]);
            exit;
        }

        try {
            // Check if user already liked this comment
            $stmt = $pdo->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $stmt->execute([$comment_id, $current_user_id]);
            $existing_like = $stmt->fetch();

            if ($existing_like) {
                // Unlike the comment
                $stmt = $pdo->prepare("DELETE FROM comment_likes WHERE id = ?");
                $stmt->execute([$existing_like['id']]);

                // Update like count
                $stmt = $pdo->prepare("UPDATE post_comments SET like_count = like_count - 1 WHERE id = ?");
                $stmt->execute([$comment_id]);

                echo json_encode(['success' => true, 'liked' => false, 'message' => t('comment_unliked')]);
            } else {
                // Like the comment
                $stmt = $pdo->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
                $stmt->execute([$comment_id, $current_user_id]);

                // Update like count
                $stmt = $pdo->prepare("UPDATE post_comments SET like_count = like_count + 1 WHERE id = ?");
                $stmt->execute([$comment_id]);

                echo json_encode(['success' => true, 'liked' => true, 'message' => t('comment_liked')]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => t('error_processing_comment_like')]);
        }
        break;

    case 'delete_post':
        $post_id = (int)($_POST['post_id'] ?? 0);

        if (!$post_id) {
            echo json_encode(['success' => false, 'message' => t('invalid_post_id')]);
            exit;
        }

        try {
            // Check if user can delete this post (author, moderator, or admin)
            $stmt = $pdo->prepare("
                SELECT cp.id, cp.author_id, cm.role
                FROM community_posts cp
                LEFT JOIN community_members cm ON cp.community_id = cm.community_id AND cm.user_id = ?
                WHERE cp.id = ? AND (cp.author_id = ? OR cm.role IN ('admin', 'moderator'))
            ");
            $stmt->execute([$current_user_id, $post_id, $current_user_id]);
            $post = $stmt->fetch();

            if (!$post) {
                echo json_encode(['success' => false, 'message' => t('not_authorized_to_delete')]);
                exit;
            }

            // Soft delete the post
            $stmt = $pdo->prepare("UPDATE community_posts SET status = 'deleted' WHERE id = ?");
            $stmt->execute([$post_id]);

            echo json_encode(['success' => true, 'message' => t('post_deleted_successfully')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => t('error_deleting_post')]);
        }
        break;

    case 'pin_post':
        $post_id = (int)($_POST['post_id'] ?? 0);

        if (!$post_id) {
            echo json_encode(['success' => false, 'message' => t('invalid_post_id')]);
            exit;
        }

        try {
            // Check if user is admin or moderator
            $stmt = $pdo->prepare("
                SELECT cm.role
                FROM community_posts cp
                INNER JOIN community_members cm ON cp.community_id = cm.community_id
                WHERE cp.id = ? AND cm.user_id = ? AND cm.role IN ('admin', 'moderator')
            ");
            $stmt->execute([$post_id, $current_user_id]);
            $member = $stmt->fetch();

            if (!$member) {
                echo json_encode(['success' => false, 'message' => t('not_authorized_to_pin')]);
                exit;
            }

            // Toggle pin status
            $stmt = $pdo->prepare("
                UPDATE community_posts 
                SET is_pinned = NOT is_pinned 
                WHERE id = ?
            ");
            $stmt->execute([$post_id]);

            echo json_encode(['success' => true, 'message' => t('post_pin_toggled')]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => t('error_toggling_pin')]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => t('invalid_action')]);
        break;
}






