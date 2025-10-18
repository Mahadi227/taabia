<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$current_user_id = current_user_id();
$current_role = $_SESSION['role'];

// Handle join/leave community actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $community_id = (int)$_POST['community_id'];

    switch ($_POST['action']) {
        case 'join_community':
            try {
                // Check if user is already a member
                $stmt = $pdo->prepare("SELECT id FROM community_members WHERE community_id = ? AND user_id = ?");
                $stmt->execute([$community_id, $current_user_id]);

                if (!$stmt->fetch()) {
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
                } else {
                    $error_message = t('already_member_of_community');
                }
            } catch (Exception $e) {
                $error_message = t('error_joining_community') . ': ' . $e->getMessage();
            }
            break;

        case 'leave_community':
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
            } catch (Exception $e) {
                $error_message = t('error_leaving_community') . ': ' . $e->getMessage();
            }
            break;
    }
}

// Get search and filter parameters
$search = sanitize($_GET['search'] ?? '');
$category = (int)($_GET['category'] ?? 0);
$privacy = sanitize($_GET['privacy'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'newest');

// Build query
$where_conditions = ["c.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(c.name LIKE ? OR c.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $where_conditions[] = "cca.category_id = ?";
    $params[] = $category;
}

if ($privacy) {
    $where_conditions[] = "c.privacy = ?";
    $params[] = $privacy;
}

$where_clause = implode(' AND ', $where_conditions);

// Get sort order
$order_by = match ($sort) {
    'newest' => 'c.created_at DESC',
    'oldest' => 'c.created_at ASC',
    'most_members' => 'member_count DESC',
    'most_posts' => 'post_count DESC',
    'name' => 'c.name ASC',
    default => 'c.created_at DESC'
};

// Get communities
$stmt = $pdo->prepare("
    SELECT c.*, 
           u.fullname as creator_name,
           COUNT(DISTINCT cm.user_id) as member_count,
           COUNT(DISTINCT cp.id) as post_count,
           CASE WHEN my_membership.id IS NOT NULL THEN 1 ELSE 0 END as is_member,
           my_membership.role as my_role
    FROM communities c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.status = 'active'
    LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
    LEFT JOIN community_members my_membership ON c.id = my_membership.community_id AND my_membership.user_id = ? AND my_membership.status = 'active'
    LEFT JOIN community_category_assignments cca ON c.id = cca.community_id
    WHERE $where_clause
    GROUP BY c.id
    ORDER BY $order_by
    LIMIT 20
");

$params = array_merge([$current_user_id], $params);
$stmt->execute($params);
$communities = $stmt->fetchAll();

// Get user's joined communities
$stmt = $pdo->prepare("
    SELECT c.*, cm.role, cm.joined_at
    FROM communities c
    INNER JOIN community_members cm ON c.id = cm.community_id
    WHERE cm.user_id = ? AND cm.status = 'active' AND c.status = 'active'
    ORDER BY cm.joined_at DESC
");
$stmt->execute([$current_user_id]);
$my_communities = $stmt->fetchAll();

// Get community categories
$stmt = $pdo->prepare("SELECT * FROM community_categories WHERE is_active = 1 ORDER BY sort_order, name");
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="<?php echo $current_language; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('communities'); ?> - TaaBia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .community-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .community-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .privacy-badge {
            font-size: 0.8em;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }

        .filter-card {
            background: #f8f9fa;
            border-radius: 10px;
        }

        .search-box {
            border-radius: 25px;
        }
    </style>
</head>

<body>
    <?php include '../includes/modern_ui.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/public_sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo t('communities'); ?></h1>
                    <div>
                        <a href="community_create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> <?php echo t('create_community'); ?>
                        </a>
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

                <!-- My Communities -->
                <?php if (!empty($my_communities)): ?>
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-users"></i> <?php echo t('my_communities'); ?>
                                        <span class="badge bg-primary ms-2"><?php echo count($my_communities); ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <?php foreach ($my_communities as $community): ?>
                                            <div class="col-md-6 col-lg-4 mb-3">
                                                <div class="card community-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($community['name']); ?></h6>
                                                            <span class="badge bg-<?php echo $community['role'] === 'admin' ? 'danger' : ($community['role'] === 'moderator' ? 'warning' : 'success'); ?>">
                                                                <?php echo t($community['role']); ?>
                                                            </span>
                                                        </div>
                                                        <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($community['description'], 0, 100)) . (strlen($community['description']) > 100 ? '...' : ''); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <i class="fas fa-users"></i> <?php echo $community['member_count']; ?>
                                                                <i class="fas fa-comments ms-2"></i> <?php echo $community['post_count']; ?>
                                                            </small>
                                                            <a href="community.php?id=<?php echo $community['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i> <?php echo t('view'); ?>
                                                            </a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Search and Filters -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card filter-card">
                            <div class="card-body">
                                <form method="GET" class="row g-3">
                                    <div class="col-md-4">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" class="form-control search-box" name="search"
                                                placeholder="<?php echo t('search_communities'); ?>"
                                                value="<?php echo htmlspecialchars($search); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" name="category">
                                            <option value=""><?php echo t('all_categories'); ?></option>
                                            <?php foreach ($categories as $cat): ?>
                                                <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($cat['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="privacy">
                                            <option value=""><?php echo t('all_privacy'); ?></option>
                                            <option value="public" <?php echo $privacy === 'public' ? 'selected' : ''; ?>><?php echo t('public'); ?></option>
                                            <option value="private" <?php echo $privacy === 'private' ? 'selected' : ''; ?>><?php echo t('private'); ?></option>
                                            <option value="invite_only" <?php echo $privacy === 'invite_only' ? 'selected' : ''; ?>><?php echo t('invite_only'); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <select class="form-select" name="sort">
                                            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>><?php echo t('newest'); ?></option>
                                            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>><?php echo t('oldest'); ?></option>
                                            <option value="most_members" <?php echo $sort === 'most_members' ? 'selected' : ''; ?>><?php echo t('most_members'); ?></option>
                                            <option value="most_posts" <?php echo $sort === 'most_posts' ? 'selected' : ''; ?>><?php echo t('most_posts'); ?></option>
                                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>><?php echo t('name'); ?></option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-filter"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- All Communities -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-globe"></i> <?php echo t('all_communities'); ?>
                                    <span class="badge bg-secondary ms-2"><?php echo count($communities); ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($communities)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5><?php echo t('no_communities_found'); ?></h5>
                                        <p class="text-muted"><?php echo t('try_adjusting_filters'); ?></p>
                                        <a href="communities.php" class="btn btn-primary">
                                            <i class="fas fa-refresh"></i> <?php echo t('clear_filters'); ?>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($communities as $community): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card community-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($community['name']); ?></h6>
                                                            <div class="d-flex flex-column align-items-end">
                                                                <span class="badge privacy-badge bg-<?php echo $community['privacy'] === 'public' ? 'success' : ($community['privacy'] === 'private' ? 'warning' : 'info'); ?> mb-1">
                                                                    <?php echo t($community['privacy']); ?>
                                                                </span>
                                                                <?php if ($community['is_member']): ?>
                                                                    <span class="badge bg-primary">
                                                                        <i class="fas fa-check"></i> <?php echo t('joined'); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($community['description'], 0, 120)) . (strlen($community['description']) > 120 ? '...' : ''); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <small class="text-muted">
                                                                <i class="fas fa-users"></i> <?php echo $community['member_count']; ?>
                                                                <i class="fas fa-comments ms-2"></i> <?php echo $community['post_count']; ?>
                                                            </small>
                                                            <small class="text-muted">
                                                                <?php echo t('by'); ?> <?php echo htmlspecialchars($community['creator_name']); ?>
                                                            </small>
                                                        </div>
                                                        <div class="d-flex gap-2">
                                                            <a href="community.php?id=<?php echo $community['id']; ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                                                <i class="fas fa-eye"></i> <?php echo t('view'); ?>
                                                            </a>
                                                            <?php if (!$community['is_member']): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="join_community">
                                                                    <input type="hidden" name="community_id" value="<?php echo $community['id']; ?>">
                                                                    <button type="submit" class="btn btn-success btn-sm">
                                                                        <i class="fas fa-plus"></i> <?php echo t('join'); ?>
                                                                    </button>
                                                                </form>
                                                            <?php else: ?>
                                                                <form method="POST" class="d-inline" onsubmit="return confirm('<?php echo t('confirm_leave_community'); ?>')">
                                                                    <input type="hidden" name="action" value="leave_community">
                                                                    <input type="hidden" name="community_id" value="<?php echo $community['id']; ?>">
                                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                                        <i class="fas fa-sign-out-alt"></i> <?php echo t('leave'); ?>
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
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
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>





