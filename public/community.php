<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$community_id = (int)($_GET['id'] ?? 0);
if (!$community_id) {
    redirect('communities.php');
}

$current_user_id = current_user_id();

// Get community details
$stmt = $pdo->prepare("
    SELECT c.*, u.fullname as creator_name, u.profile_image as creator_image
    FROM communities c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = ? AND c.status = 'active'
");
$stmt->execute([$community_id]);
$community = $stmt->fetch();

if (!$community) {
    redirect('communities.php');
}

// Check if user is a member
$stmt = $pdo->prepare("
    SELECT cm.*, u.fullname, u.profile_image
    FROM community_members cm
    LEFT JOIN users u ON cm.user_id = u.id
    WHERE cm.community_id = ? AND cm.user_id = ? AND cm.status = 'active'
");
$stmt->execute([$community_id, $current_user_id]);
$membership = $stmt->fetch();

$is_member = !empty($membership);
$user_role = $membership['role'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'join_community':
            if (!$is_member) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO community_members (community_id, user_id, role) 
                        VALUES (?, ?, 'member')
                    ");
                    $stmt->execute([$community_id, $current_user_id]);

                    // Update member count
                    $stmt = $pdo->prepare("
                        UPDATE communities 
                        SET member_count = member_count + 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$community_id]);

                    $success_message = t('joined_community_successfully');
                    $is_member = true;
                    $user_role = 'member';
                } catch (Exception $e) {
                    $error_message = t('error_joining_community') . ': ' . $e->getMessage();
                }
            }
            break;

        case 'leave_community':
            if ($is_member && $user_role !== 'admin') {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE community_members 
                        SET status = 'left' 
                        WHERE community_id = ? AND user_id = ?
                    ");
                    $stmt->execute([$community_id, $current_user_id]);

                    // Update member count
                    $stmt = $pdo->prepare("
                        UPDATE communities 
                        SET member_count = member_count - 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$community_id]);

                    $success_message = t('left_community_successfully');
                    $is_member = false;
                    $user_role = null;
                } catch (Exception $e) {
                    $error_message = t('error_leaving_community') . ': ' . $e->getMessage();
                }
            }
            break;

        case 'create_post':
            if ($is_member) {
                $title = sanitize($_POST['title']);
                $content = sanitize($_POST['content']);
                $post_type = sanitize($_POST['post_type']);

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO community_posts (community_id, author_id, title, content, post_type) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$community_id, $current_user_id, $title, $content, $post_type]);

                    // Update post count
                    $stmt = $pdo->prepare("
                        UPDATE communities 
                        SET post_count = post_count + 1 
                        WHERE id = ?
                    ");
                    $stmt->execute([$community_id]);

                    $success_message = t('post_created_successfully');
                } catch (Exception $e) {
                    $error_message = t('error_creating_post') . ': ' . $e->getMessage();
                }
            }
            break;
    }
}

// Get community posts
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT cp.*, u.fullname as author_name, u.profile_image as author_image,
           COUNT(DISTINCT pc.id) as comment_count,
           COUNT(DISTINCT pl.id) as like_count,
           CASE WHEN my_like.id IS NOT NULL THEN 1 ELSE 0 END as is_liked
    FROM community_posts cp
    LEFT JOIN users u ON cp.author_id = u.id
    LEFT JOIN post_comments pc ON cp.id = pc.post_id AND pc.status = 'published'
    LEFT JOIN post_likes pl ON cp.id = pl.post_id
    LEFT JOIN post_likes my_like ON cp.id = my_like.post_id AND my_like.user_id = ?
    WHERE cp.community_id = ? AND cp.status = 'published'
    GROUP BY cp.id
    ORDER BY cp.is_pinned DESC, cp.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$current_user_id, $community_id, $limit, $offset]);
$posts = $stmt->fetchAll();

// Get total posts count for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM community_posts 
    WHERE community_id = ? AND status = 'published'
");
$stmt->execute([$community_id]);
$total_posts = $stmt->fetch()['total'];
$total_pages = ceil($total_posts / $limit);

// Get community members (limited)
$stmt = $pdo->prepare("
    SELECT cm.*, u.fullname, u.profile_image
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
    LIMIT 10
");
$stmt->execute([$community_id]);
$members = $stmt->fetchAll();

// Get total member count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM community_members 
    WHERE community_id = ? AND status = 'active'
");
$stmt->execute([$community_id]);
$total_members = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['name']); ?> - TaaBia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .community-header {
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

        .member-card {
            transition: transform 0.2s;
        }

        .member-card:hover {
            transform: translateY(-2px);
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }

        .pinned-post {
            border-left-color: #ffc107;
            background-color: #fff3cd;
        }
    </style>
</head>

<body>
    <?php include '../includes/modern_ui.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/public_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Community Header -->
                <div class="community-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1><?php echo htmlspecialchars($community['name']); ?></h1>
                            <p class="mb-2"><?php echo htmlspecialchars($community['description']); ?></p>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-<?php echo $community['privacy'] === 'public' ? 'globe' : ($community['privacy'] === 'private' ? 'lock' : 'envelope'); ?>"></i>
                                    <?php echo t($community['privacy']); ?>
                                </span>
                                <small class="text-light">
                                    <i class="fas fa-user"></i> <?php echo t('created_by'); ?>: <?php echo htmlspecialchars($community['creator_name']); ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="row text-center">
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $total_members; ?></h4>
                                        <small><?php echo t('members'); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $total_posts; ?></h4>
                                        <small><?php echo t('posts'); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $community['member_count']; ?></h4>
                                        <small><?php echo t('total'); ?></small>
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
                        <!-- Join/Leave Community -->
                        <?php if (!$is_member): ?>
                            <div class="card mb-4">
                                <div class="card-body text-center">
                                    <h5><?php echo t('join_this_community'); ?></h5>
                                    <p class="text-muted"><?php echo t('join_community_description'); ?></p>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="join_community">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> <?php echo t('join_community'); ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <!-- Create Post -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-plus"></i> <?php echo t('create_post'); ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="create_post">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="title" placeholder="<?php echo t('post_title'); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <textarea class="form-control" name="content" rows="3" placeholder="<?php echo t('what_do_you_think'); ?>" required></textarea>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <select class="form-select" name="post_type" style="width: auto;">
                                                <option value="text"><?php echo t('text_post'); ?></option>
                                                <option value="image"><?php echo t('image_post'); ?></option>
                                                <option value="video"><?php echo t('video_post'); ?></option>
                                                <option value="link"><?php echo t('link_post'); ?></option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-paper-plane"></i> <?php echo t('post'); ?>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Posts -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-comments"></i> <?php echo t('community_posts'); ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($posts)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                        <h5><?php echo t('no_posts_yet'); ?></h5>
                                        <p class="text-muted"><?php echo t('be_first_to_post'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($posts as $post): ?>
                                        <div class="card post-card mb-3 <?php echo $post['is_pinned'] ? 'pinned-post' : ''; ?>">
                                            <div class="card-body">
                                                <div class="d-flex align-items-start">
                                                    <div class="me-3">
                                                        <?php if ($post['author_image']): ?>
                                                            <img src="../uploads/<?php echo htmlspecialchars($post['author_image']); ?>"
                                                                class="rounded-circle" width="40" height="40" alt="Profile">
                                                        <?php else: ?>
                                                            <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                                style="width: 40px; height: 40px;">
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
                                                                    <span class="badge bg-info">
                                                                        <i class="fas fa-bullhorn"></i> <?php echo t('announcement'); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <?php if ($post['title']): ?>
                                                            <h6 class="mt-2"><?php echo htmlspecialchars($post['title']); ?></h6>
                                                        <?php endif; ?>
                                                        <p class="mb-2"><?php echo nl2br(htmlspecialchars($post['content'])); ?></p>
                                                        <div class="d-flex align-items-center">
                                                            <button class="btn btn-sm btn-outline-<?php echo $post['is_liked'] ? 'danger' : 'secondary'; ?> me-2"
                                                                onclick="toggleLike(<?php echo $post['id']; ?>)">
                                                                <i class="fas fa-heart"></i> <?php echo $post['like_count']; ?>
                                                            </button>
                                                            <button class="btn btn-sm btn-outline-secondary me-2"
                                                                onclick="showComments(<?php echo $post['id']; ?>)">
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
                                    <?php endforeach; ?>

                                    <!-- Pagination -->
                                    <?php if ($total_pages > 1): ?>
                                        <nav aria-label="Posts pagination">
                                            <ul class="pagination justify-content-center">
                                                <?php if ($page > 1): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?id=<?php echo $community_id; ?>&page=<?php echo $page - 1; ?>">
                                                            <i class="fas fa-chevron-left"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>

                                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                        <a class="page-link" href="?id=<?php echo $community_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                    </li>
                                                <?php endfor; ?>

                                                <?php if ($page < $total_pages): ?>
                                                    <li class="page-item">
                                                        <a class="page-link" href="?id=<?php echo $community_id; ?>&page=<?php echo $page + 1; ?>">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </nav>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Community Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0"><?php echo t('actions'); ?></h6>
                            </div>
                            <div class="card-body">
                                <?php if ($is_member): ?>
                                    <div class="d-grid gap-2">
                                        <a href="communities.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left"></i> <?php echo t('back_to_communities'); ?>
                                        </a>
                                        <?php if ($user_role !== 'admin'): ?>
                                            <form method="POST" onsubmit="return confirm('<?php echo t('confirm_leave_community'); ?>')">
                                                <input type="hidden" name="action" value="leave_community">
                                                <button type="submit" class="btn btn-outline-danger w-100">
                                                    <i class="fas fa-sign-out-alt"></i> <?php echo t('leave_community'); ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="d-grid">
                                        <a href="communities.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-arrow-left"></i> <?php echo t('back_to_communities'); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Community Members -->
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-users"></i> <?php echo t('members'); ?>
                                    <span class="badge bg-primary ms-2"><?php echo $total_members; ?></span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($members)): ?>
                                    <p class="text-muted"><?php echo t('no_members_yet'); ?></p>
                                <?php else: ?>
                                    <?php foreach ($members as $member): ?>
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-3">
                                                <?php if ($member['profile_image']): ?>
                                                    <img src="../uploads/<?php echo htmlspecialchars($member['profile_image']); ?>"
                                                        class="rounded-circle" width="32" height="32" alt="Profile">
                                                <?php else: ?>
                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                        style="width: 32px; height: 32px;">
                                                        <i class="fas fa-user text-white" style="font-size: 0.8em;"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <small class="fw-bold"><?php echo htmlspecialchars($member['fullname']); ?></small>
                                                        <br><small class="text-muted"><?php echo t($member['role']); ?></small>
                                                    </div>
                                                    <span class="badge bg-<?php echo $member['role'] === 'admin' ? 'danger' : ($member['role'] === 'moderator' ? 'warning' : 'success'); ?>">
                                                        <?php echo t($member['role']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if ($total_members > 10): ?>
                                        <div class="text-center mt-3">
                                            <small class="text-muted">
                                                <?php echo t('and_more_members', ['count' => $total_members - 10]); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
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
            // Implement like functionality
            console.log('Toggle like for post:', postId);
        }

        function showComments(postId) {
            // Implement comments functionality
            console.log('Show comments for post:', postId);
        }
    </script>
</body>

</html>





