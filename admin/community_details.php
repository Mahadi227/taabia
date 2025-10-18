<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Check if user is logged in and has admin or instructor role
if (!is_logged_in() || (!has_role('admin') && !has_role('instructor'))) {
    redirect('../auth/unauthorized.php');
}

$community_id = (int)($_GET['id'] ?? 0);
if (!$community_id) {
    redirect('communities.php');
}

$current_user_id = current_user_id();

// Get community details
$stmt = $pdo->prepare("
    SELECT c.*, u.fullname as creator_name
    FROM communities c
    LEFT JOIN users u ON c.created_by = u.id
    WHERE c.id = ?
");
$stmt->execute([$community_id]);
$community = $stmt->fetch();

if (!$community) {
    redirect('communities.php');
}

// Check if user can manage this community
if (!has_role('admin') && $community['created_by'] != $current_user_id) {
    redirect('communities.php');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_community':
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $privacy = sanitize($_POST['privacy']);
                $status = sanitize($_POST['status']);

                try {
                    $stmt = $pdo->prepare("
                        UPDATE communities 
                        SET name = ?, description = ?, privacy = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $privacy, $status, $community_id]);
                    $success_message = t('community_updated_successfully');

                    // Refresh community data
                    $stmt = $pdo->prepare("
                        SELECT c.*, u.fullname as creator_name
                        FROM communities c
                        LEFT JOIN users u ON c.created_by = u.id
                        WHERE c.id = ?
                    ");
                    $stmt->execute([$community_id]);
                    $community = $stmt->fetch();
                } catch (Exception $e) {
                    $error_message = t('error_updating_community') . ': ' . $e->getMessage();
                }
                break;

            case 'update_member_role':
                $member_id = (int)$_POST['member_id'];
                $new_role = sanitize($_POST['new_role']);

                try {
                    $stmt = $pdo->prepare("
                        UPDATE community_members 
                        SET role = ? 
                        WHERE id = ? AND community_id = ?
                    ");
                    $stmt->execute([$new_role, $member_id, $community_id]);
                    $success_message = t('member_role_updated_successfully');
                } catch (Exception $e) {
                    $error_message = t('error_updating_member_role') . ': ' . $e->getMessage();
                }
                break;

            case 'remove_member':
                $member_id = (int)$_POST['member_id'];

                try {
                    $stmt = $pdo->prepare("
                        UPDATE community_members 
                        SET status = 'left' 
                        WHERE id = ? AND community_id = ?
                    ");
                    $stmt->execute([$member_id, $community_id]);
                    $success_message = t('member_removed_successfully');
                } catch (Exception $e) {
                    $error_message = t('error_removing_member') . ': ' . $e->getMessage();
                }
                break;

            case 'ban_member':
                $member_id = (int)$_POST['member_id'];

                try {
                    $stmt = $pdo->prepare("
                        UPDATE community_members 
                        SET status = 'banned' 
                        WHERE id = ? AND community_id = ?
                    ");
                    $stmt->execute([$member_id, $community_id]);
                    $success_message = t('member_banned_successfully');
                } catch (Exception $e) {
                    $error_message = t('error_banning_member') . ': ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get community members
$stmt = $pdo->prepare("
    SELECT cm.*, u.fullname, u.email, u.profile_image, u.role as user_role
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
");
$stmt->execute([$community_id]);
$members = $stmt->fetchAll();

// Get community posts
$stmt = $pdo->prepare("
    SELECT cp.*, u.fullname as author_name, u.profile_image as author_image
    FROM community_posts cp
    LEFT JOIN users u ON cp.author_id = u.id
    WHERE cp.community_id = ?
    ORDER BY cp.is_pinned DESC, cp.created_at DESC
    LIMIT 10
");
$stmt->execute([$community_id]);
$recent_posts = $stmt->fetchAll();

// Get community statistics
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
$stats = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($community['name']); ?> - <?php echo t('community_details'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .community-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }

        .member-card {
            transition: transform 0.2s;
        }

        .member-card:hover {
            transform: translateY(-2px);
        }

        .post-card {
            border-left: 4px solid #007bff;
        }
    </style>
</head>

<body>
    <?php include '../includes/modern_ui.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo htmlspecialchars($community['name']); ?></h1>
                    <div>
                        <a href="communities.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> <?php echo t('back_to_communities'); ?>
                        </a>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editCommunityModal">
                            <i class="fas fa-edit"></i> <?php echo t('edit_community'); ?>
                        </button>
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

                <!-- Community Header -->
                <div class="community-header p-4 mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><?php echo htmlspecialchars($community['name']); ?></h2>
                            <p class="mb-2"><?php echo htmlspecialchars($community['description']); ?></p>
                            <div class="d-flex align-items-center">
                                <span class="badge bg-light text-dark me-2">
                                    <i class="fas fa-<?php echo $community['privacy'] === 'public' ? 'globe' : ($community['privacy'] === 'private' ? 'lock' : 'envelope'); ?>"></i>
                                    <?php echo t($community['privacy']); ?>
                                </span>
                                <span class="badge bg-<?php echo $community['status'] === 'active' ? 'success' : 'secondary'; ?> me-2">
                                    <?php echo t($community['status']); ?>
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
                                        <h4><?php echo $stats['member_count']; ?></h4>
                                        <small><?php echo t('members'); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $stats['post_count']; ?></h4>
                                        <small><?php echo t('posts'); ?></small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="stats-card p-3">
                                        <h4><?php echo $stats['comment_count']; ?></h4>
                                        <small><?php echo t('comments'); ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs" id="communityTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
                            <i class="fas fa-users"></i> <?php echo t('members'); ?> (<?php echo count($members); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="posts-tab" data-bs-toggle="tab" data-bs-target="#posts" type="button" role="tab">
                            <i class="fas fa-comments"></i> <?php echo t('recent_posts'); ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings" type="button" role="tab">
                            <i class="fas fa-cog"></i> <?php echo t('settings'); ?>
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="communityTabContent">
                    <!-- Members Tab -->
                    <div class="tab-pane fade show active" id="members" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo t('community_members'); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($members)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5><?php echo t('no_members_yet'); ?></h5>
                                        <p class="text-muted"><?php echo t('members_will_appear_here'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($members as $member): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card member-card">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-center">
                                                            <div class="me-3">
                                                                <?php if ($member['profile_image']): ?>
                                                                    <img src="../uploads/<?php echo htmlspecialchars($member['profile_image']); ?>"
                                                                        class="rounded-circle" width="40" height="40" alt="Profile">
                                                                <?php else: ?>
                                                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center"
                                                                        style="width: 40px; height: 40px;">
                                                                        <i class="fas fa-user text-white"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($member['fullname']); ?></h6>
                                                                <small class="text-muted"><?php echo t($member['role']); ?></small>
                                                                <br><small class="text-muted"><?php echo format_date($member['joined_at']); ?></small>
                                                            </div>
                                                            <div class="dropdown">
                                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                    <i class="fas fa-ellipsis-v"></i>
                                                                </button>
                                                                <ul class="dropdown-menu">
                                                                    <li>
                                                                        <form method="POST" class="d-inline">
                                                                            <input type="hidden" name="action" value="update_member_role">
                                                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                                            <select name="new_role" class="form-select form-select-sm" onchange="this.form.submit()">
                                                                                <option value="member" <?php echo $member['role'] === 'member' ? 'selected' : ''; ?>><?php echo t('member'); ?></option>
                                                                                <option value="moderator" <?php echo $member['role'] === 'moderator' ? 'selected' : ''; ?>><?php echo t('moderator'); ?></option>
                                                                                <?php if ($member['user_id'] != $community['created_by']): ?>
                                                                                    <option value="admin" <?php echo $member['role'] === 'admin' ? 'selected' : ''; ?>><?php echo t('admin'); ?></option>
                                                                                <?php endif; ?>
                                                                            </select>
                                                                        </form>
                                                                    </li>
                                                                    <li>
                                                                        <hr class="dropdown-divider">
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo t('confirm_remove_member'); ?>')">
                                                                            <input type="hidden" name="action" value="remove_member">
                                                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                                            <button type="submit" class="dropdown-item text-warning">
                                                                                <i class="fas fa-user-minus"></i> <?php echo t('remove_member'); ?>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                    <li>
                                                                        <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo t('confirm_ban_member'); ?>')">
                                                                            <input type="hidden" name="action" value="ban_member">
                                                                            <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
                                                                            <button type="submit" class="dropdown-item text-danger">
                                                                                <i class="fas fa-ban"></i> <?php echo t('ban_member'); ?>
                                                                            </button>
                                                                        </form>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Posts Tab -->
                    <div class="tab-pane fade" id="posts" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo t('recent_posts'); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_posts)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                                        <h5><?php echo t('no_posts_yet'); ?></h5>
                                        <p class="text-muted"><?php echo t('posts_will_appear_here'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_posts as $post): ?>
                                        <div class="card post-card mb-3">
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
                                                            <?php if ($post['is_pinned']): ?>
                                                                <span class="badge bg-warning">
                                                                    <i class="fas fa-thumbtack"></i> <?php echo t('pinned'); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($post['title']): ?>
                                                            <h6 class="mt-2"><?php echo htmlspecialchars($post['title']); ?></h6>
                                                        <?php endif; ?>
                                                        <p class="mb-2"><?php echo htmlspecialchars(substr($post['content'], 0, 200)) . (strlen($post['content']) > 200 ? '...' : ''); ?></p>
                                                        <div class="d-flex align-items-center">
                                                            <small class="text-muted me-3">
                                                                <i class="fas fa-heart"></i> <?php echo $post['like_count']; ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <i class="fas fa-comment"></i> <?php echo $post['comment_count']; ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade" id="settings" role="tabpanel">
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo t('community_settings'); ?></h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_community">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label"><?php echo t('community_name'); ?> *</label>
                                                <input type="text" class="form-control" id="name" name="name"
                                                    value="<?php echo htmlspecialchars($community['name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="status" class="form-label"><?php echo t('status'); ?> *</label>
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="active" <?php echo $community['status'] === 'active' ? 'selected' : ''; ?>><?php echo t('active'); ?></option>
                                                    <option value="archived" <?php echo $community['status'] === 'archived' ? 'selected' : ''; ?>><?php echo t('archived'); ?></option>
                                                    <option value="suspended" <?php echo $community['status'] === 'suspended' ? 'selected' : ''; ?>><?php echo t('suspended'); ?></option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label"><?php echo t('description'); ?></label>
                                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($community['description']); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="privacy" class="form-label"><?php echo t('privacy'); ?> *</label>
                                        <select class="form-select" id="privacy" name="privacy" required>
                                            <option value="public" <?php echo $community['privacy'] === 'public' ? 'selected' : ''; ?>><?php echo t('public'); ?></option>
                                            <option value="private" <?php echo $community['privacy'] === 'private' ? 'selected' : ''; ?>><?php echo t('private'); ?></option>
                                            <option value="invite_only" <?php echo $community['privacy'] === 'invite_only' ? 'selected' : ''; ?>><?php echo t('invite_only'); ?></option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo t('save_changes'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>





