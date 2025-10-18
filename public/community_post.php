<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$post_id = (int)($_GET['id'] ?? 0);
if (!$post_id) {
    redirect('communities.php');
}

$current_user_id = current_user_id();

// Get post details
$stmt = $pdo->prepare("
    SELECT cp.*, c.name as community_name, c.id as community_id,
           u.fullname as author_name, u.profile_image as author_image,
           COUNT(DISTINCT pc.id) as comment_count,
           COUNT(DISTINCT pl.id) as like_count,
           CASE WHEN my_like.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
    FROM community_posts cp
    LEFT JOIN communities c ON cp.community_id = c.id
    LEFT JOIN users u ON cp.author_id = u.id
    LEFT JOIN post_comments pc ON cp.id = pc.post_id AND pc.status = 'published'
    LEFT JOIN post_likes pl ON cp.id = pl.post_id
    LEFT JOIN post_likes my_like ON cp.id = my_like.post_id AND my_like.user_id = ?
    WHERE cp.id = ? AND cp.status = 'published'
    GROUP BY cp.id
");
$stmt->execute([$current_user_id, $post_id]);
$post = $stmt->fetch();

if (!$post) {
    redirect('communities.php');
}

// Check if user is member of the community
$stmt = $pdo->prepare("
    SELECT cm.role
    FROM community_members cm
    WHERE cm.community_id = ? AND cm.user_id = ? AND cm.status = 'active'
");
$stmt->execute([$post['community_id'], $current_user_id]);
$membership = $stmt->fetch();

$is_member = !empty($membership);
$user_role = $membership['role'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_comment':
            if ($is_member) {
                $content = sanitize($_POST['content']);
                $parent_id = (int)($_POST['parent_id'] ?? 0);

                if ($content) {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO post_comments (post_id, author_id, content, parent_id) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$post_id, $current_user_id, $content, $parent_id ?: null]);

                        // Update comment count
                        $stmt = $pdo->prepare("UPDATE community_posts SET comment_count = comment_count + 1 WHERE id = ?");
                        $stmt->execute([$post_id]);

                        $success_message = t('comment_added_successfully');
                    } catch (Exception $e) {
                        $error_message = t('error_adding_comment') . ': ' . $e->getMessage();
                    }
                }
            }
            break;
    }
}

// Get comments
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

// Update view count
$stmt = $pdo->prepare("UPDATE community_posts SET view_count = view_count + 1 WHERE id = ?");
$stmt->execute([$post_id]);
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title'] ?: 'Post'); ?> - <?php echo htmlspecialchars($post['community_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .post-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .post-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }

        .post-card:hover {
            transform: translateY(-1px);
        }

        .comment-card {
            border-left: 3px solid #28a745;
            margin-bottom: 1rem;
        }

        .reply-card {
            border-left: 3px solid #ffc107;
            margin-left: 2rem;
            margin-bottom: 1rem;
        }

        .pinned-post {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
    </style>
</head>

<body>
    <?php include '../includes/modern_ui.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/public_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Post Header -->
                <div class="post-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1><?php echo htmlspecialchars($post['title'] ?: t('post')); ?></h1>
                            <div class="d-flex align-items-center">
                                <a href="community.php?id=<?php echo $post['community_id']; ?>" class="text-light me-3">
                                    <i class="fas fa-arrow-left"></i> <?php echo htmlspecialchars($post['community_name']); ?>
                                </a>
                                <small class="text-light">
                                    <i class="fas fa-user"></i> <?php echo t('by'); ?> <?php echo htmlspecialchars($post['author_name']); ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $post['like_count']; ?></h4>
                                        <small><?php echo t('likes'); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $post['comment_count']; ?></h4>
                                        <small><?php echo t('comments'); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $post['view_count']; ?></h4>
                                        <small><?php echo t('views'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <!-- Post Content -->
                        <div class="card post-card mb-4 <?php echo $post['is_pinned'] ? 'pinned-post' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <?php if ($post['author_image']): ?>
                                            <img src="../uploads/<?php echo htmlspecialchars($post['author_image']); ?>"
                                                class="rounded-circle" width="50" height="50" alt="Profile">
                                        <?php else: ?>
                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                style="width: 50px; height: 50px;">
                                                <i class="fas fa-user text-white"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($post['author_name']); ?></h6>
                                                <small class="text-muted"><?php echo timeAgo($post['created_at']); ?></small>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <?php if ($post['is_pinned']): ?>
                                                    <span class="badge bg-warning me-2">
                                                        <i class="fas fa-thumbtack"></i> <?php echo t('pinned'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($post['is_announcement']): ?>
                                                    <span class="badge bg-info me-2">
                                                        <i class="fas fa-bullhorn"></i> <?php echo t('announcement'); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($is_member && ($user_role === 'admin' || $user_role === 'moderator' || $post['author_id'] == $current_user_id)): ?>
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </button>
                                                        <ul class="dropdown-menu">
                                                            <?php if ($user_role === 'admin' || $user_role === 'moderator'): ?>
                                                                <li>
                                                                    <button class="dropdown-item" onclick="togglePin(<?php echo $post['id']; ?>)">
                                                                        <i class="fas fa-thumbtack"></i> <?php echo $post['is_pinned'] ? t('unpin') : t('pin'); ?>
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                            <?php if ($post['author_id'] == $current_user_id || $user_role === 'admin' || $user_role === 'moderator'): ?>
                                                                <li>
                                                                    <button class="dropdown-item text-danger" onclick="deletePost(<?php echo $post['id']; ?>)">
                                                                        <i class="fas fa-trash"></i> <?php echo t('delete'); ?>
                                                                    </button>
                                                                </li>
                                                            <?php endif; ?>
                                                        </ul>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                            <div class="post-content">
                                                <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center mt-3">
                                            <button class="btn btn-sm btn-outline-<?php echo $post['is_liked'] ? 'danger' : 'secondary'; ?> me-2"
                                                onclick="toggleLike(<?php echo $post['id']; ?>)">
                                                <i class="fas fa-heart"></i> <?php echo $post['like_count']; ?>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary me-2"
                                                onclick="scrollToComments()">
                                                <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?>
                                            </button>
                                            <small class="text-muted">
                                                <i class="fas fa-eye"></i> <?php echo $post['view_count']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Comments Section -->
                        <div class="card" id="comments-section">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-comments"></i> <?php echo t('comments'); ?>
                                    <span class="badge bg-primary ms-2"><?php echo count($comments); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!$is_member): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                                        <h5><?php echo t('join_to_comment'); ?></h5>
                                        <p class="text-muted"><?php echo t('join_community_to_comment'); ?></p>
                                        <a href="community.php?id=<?php echo $post['community_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> <?php echo t('join_community'); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <!-- Add Comment Form -->
                                    <form method="POST" class="mb-4">
                                        <input type="hidden" name="action" value="add_comment">
                                        <div class="mb-3">
                                            <textarea class="form-control" name="content" rows="3"
                                                placeholder="<?php echo t('write_a_comment'); ?>" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane"></i> <?php echo t('add_comment'); ?>
                                        </button>
                                    </form>

                                    <!-- Comments List -->
                                    <?php if (empty($comments)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                            <h5><?php echo t('no_comments_yet'); ?></h5>
                                            <p class="text-muted"><?php echo t('be_first_to_comment'); ?></p>
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($comments as $comment): ?>
                                            <div class="card comment-card">
                                                <div class="card-body">
                                                    <div class="d-flex align-items-start">
                                                        <div class="me-3">
                                                            <?php if ($comment['author_image']): ?>
                                                                <img src="../uploads/<?php echo htmlspecialchars($comment['author_image']); ?>"
                                                                    class="rounded-circle" width="40" height="40" alt="Profile">
                                                            <?php else: ?>
                                                                <div class="bg-success rounded-circle d-flex align-items-center justify-content-center"
                                                                    style="width: 40px; height: 40px;">
                                                                    <i class="fas fa-user text-white"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <h6 class="mb-1"><?php echo htmlspecialchars($comment['author_name']); ?></h6>
                                                                    <small class="text-muted"><?php echo timeAgo($comment['created_at']); ?></small>
                                                                </div>
                                                                <div class="d-flex align-items-center">
                                                                    <button class="btn btn-sm btn-outline-<?php echo $comment['is_liked'] ? 'danger' : 'secondary'; ?> me-2"
                                                                        onclick="toggleCommentLike(<?php echo $comment['id']; ?>)">
                                                                        <i class="fas fa-heart"></i> <?php echo $comment['like_count']; ?>
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-secondary"
                                                                        onclick="showReplyForm(<?php echo $comment['id']; ?>)">
                                                                        <i class="fas fa-reply"></i> <?php echo t('reply'); ?>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                            <p class="mt-2 mb-0"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></p>
                                                        </div>
                                                    </div>

                                                    <!-- Reply Form (hidden by default) -->
                                                    <div id="reply-form-<?php echo $comment['id']; ?>" class="mt-3" style="display: none;">
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="add_comment">
                                                            <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                                            <div class="mb-2">
                                                                <textarea class="form-control" name="content" rows="2"
                                                                    placeholder="<?php echo t('write_a_reply'); ?>" required></textarea>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="submit" class="btn btn-primary btn-sm">
                                                                    <i class="fas fa-paper-plane"></i> <?php echo t('reply'); ?>
                                                                </button>
                                                                <button type="button" class="btn btn-secondary btn-sm"
                                                                    onclick="hideReplyForm(<?php echo $comment['id']; ?>)">
                                                                    <?php echo t('cancel'); ?>
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Post Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><?php echo t('actions'); ?></h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="community.php?id=<?php echo $post['community_id']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left"></i> <?php echo t('back_to_community'); ?>
                                    </a>
                                    <button class="btn btn-outline-primary" onclick="sharePost()">
                                        <i class="fas fa-share"></i> <?php echo t('share_post'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Community Info -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle"></i> <?php echo t('community_info'); ?>
                                </h6>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($post['community_name']); ?></h6>
                                <p class="text-muted small"><?php echo t('post_in_community'); ?></p>
                                <div class="d-grid">
                                    <a href="community.php?id=<?php echo $post['community_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-eye"></i> <?php echo t('view_community'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleLike(postId) {
            fetch('../api/community_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=like_post&post_id=' + postId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
        }

        function toggleCommentLike(commentId) {
            fetch('../api/community_actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=like_comment&comment_id=' + commentId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                });
        }

        function showReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).style.display = 'block';
        }

        function hideReplyForm(commentId) {
            document.getElementById('reply-form-' + commentId).style.display = 'none';
        }

        function scrollToComments() {
            document.getElementById('comments-section').scrollIntoView({
                behavior: 'smooth'
            });
        }

        function sharePost() {
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($post['title']); ?>',
                    text: '<?php echo addslashes(substr($post['content'], 0, 100)); ?>...',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('<?php echo t('link_copied_to_clipboard'); ?>');
                });
            }
        }

        function togglePin(postId) {
            if (confirm('<?php echo t('confirm_toggle_pin'); ?>')) {
                fetch('../api/community_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=pin_post&post_id=' + postId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }

        function deletePost(postId) {
            if (confirm('<?php echo t('confirm_delete_post'); ?>')) {
                fetch('../api/community_actions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=delete_post&post_id=' + postId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'community.php?id=<?php echo $post['community_id']; ?>';
                        } else {
                            alert(data.message);
                        }
                    });
            }
        }
    </script>
</body>

</html>





