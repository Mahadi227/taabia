<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';

// Check if user is logged in and has admin or instructor role
if (!is_logged_in() || (!has_role('admin') && !has_role('instructor'))) {
    redirect('../auth/unauthorized.php');
}

$current_user_id = current_user_id();
$current_role = $_SESSION['role'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_community':
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $privacy = sanitize($_POST['privacy']);
                $categories = isset($_POST['categories']) ? $_POST['categories'] : [];

                try {
                    $pdo->beginTransaction();

                    // Create community
                    $stmt = $pdo->prepare("
                        INSERT INTO communities (name, description, created_by, privacy) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $description, $current_user_id, $privacy]);
                    $community_id = $pdo->lastInsertId();

                    // Add creator as admin
                    $stmt = $pdo->prepare("
                        INSERT INTO community_members (community_id, user_id, role) 
                        VALUES (?, ?, 'admin')
                    ");
                    $stmt->execute([$community_id, $current_user_id]);

                    // Assign categories
                    if (!empty($categories)) {
                        $stmt = $pdo->prepare("
                            INSERT INTO community_category_assignments (community_id, category_id) 
                            VALUES (?, ?)
                        ");
                        foreach ($categories as $category_id) {
                            $stmt->execute([$community_id, $category_id]);
                        }
                    }

                    $pdo->commit();
                    $success_message = t('community_created_successfully');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error_message = t('error_creating_community') . ': ' . $e->getMessage();
                }
                break;

            case 'update_community':
                $community_id = (int)$_POST['community_id'];
                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $privacy = sanitize($_POST['privacy']);
                $status = sanitize($_POST['status']);

                try {
                    $stmt = $pdo->prepare("
                        UPDATE communities 
                        SET name = ?, description = ?, privacy = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ? AND created_by = ?
                    ");
                    $stmt->execute([$name, $description, $privacy, $status, $community_id, $current_user_id]);
                    $success_message = t('community_updated_successfully');
                } catch (Exception $e) {
                    $error_message = t('error_updating_community') . ': ' . $e->getMessage();
                }
                break;

            case 'delete_community':
                $community_id = (int)$_POST['community_id'];

                try {
                    $stmt = $pdo->prepare("
                        UPDATE communities 
                        SET status = 'archived' 
                        WHERE id = ? AND created_by = ?
                    ");
                    $stmt->execute([$community_id, $current_user_id]);
                    $success_message = t('community_archived_successfully');
                } catch (Exception $e) {
                    $error_message = t('error_archiving_community') . ': ' . $e->getMessage();
                }
                break;
        }
    }
}

// Get communities created by current user
$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(DISTINCT cm.user_id) as member_count,
           COUNT(DISTINCT cp.id) as post_count
    FROM communities c
    LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.status = 'active'
    LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
    WHERE c.created_by = ?
    GROUP BY c.id
    ORDER BY c.created_at DESC
");
$stmt->execute([$current_user_id]);
$user_communities = $stmt->fetchAll();

// Get all communities for admin
$all_communities = [];
if (has_role('admin')) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               u.fullname as creator_name,
               COUNT(DISTINCT cm.user_id) as member_count,
               COUNT(DISTINCT cp.id) as post_count
        FROM communities c
        LEFT JOIN users u ON c.created_by = u.id
        LEFT JOIN community_members cm ON c.id = cm.community_id AND cm.status = 'active'
        LEFT JOIN community_posts cp ON c.id = cp.community_id AND cp.status = 'published'
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    $stmt->execute();
    $all_communities = $stmt->fetchAll();
}

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
    <title><?php echo t('community_management'); ?> - TaaBia</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .community-card {
            transition: transform 0.2s;
        }

        .community-card:hover {
            transform: translateY(-2px);
        }

        .privacy-badge {
            font-size: 0.8em;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
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
                    <h1 class="h2"><?php echo t('community_management'); ?></h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCommunityModal">
                        <i class="fas fa-plus"></i> <?php echo t('create_community'); ?>
                    </button>
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-users fa-2x mb-2"></i>
                                <h4><?php echo count($user_communities); ?></h4>
                                <p class="mb-0"><?php echo t('my_communities'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-comments fa-2x mb-2"></i>
                                <h4><?php echo array_sum(array_column($user_communities, 'post_count')); ?></h4>
                                <p class="mb-0"><?php echo t('total_posts'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-friends fa-2x mb-2"></i>
                                <h4><?php echo array_sum(array_column($user_communities, 'member_count')); ?></h4>
                                <p class="mb-0"><?php echo t('total_members'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-2x mb-2"></i>
                                <h4><?php echo count($all_communities); ?></h4>
                                <p class="mb-0"><?php echo t('all_communities'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- My Communities -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo t('my_communities'); ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($user_communities)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                        <h5><?php echo t('no_communities_yet'); ?></h5>
                                        <p class="text-muted"><?php echo t('create_your_first_community'); ?></p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCommunityModal">
                                            <i class="fas fa-plus"></i> <?php echo t('create_community'); ?>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($user_communities as $community): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="card community-card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h6 class="card-title"><?php echo htmlspecialchars($community['name']); ?></h6>
                                                            <span class="badge privacy-badge bg-<?php echo $community['privacy'] === 'public' ? 'success' : ($community['privacy'] === 'private' ? 'warning' : 'info'); ?>">
                                                                <?php echo t($community['privacy']); ?>
                                                            </span>
                                                        </div>
                                                        <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($community['description'], 0, 100)) . (strlen($community['description']) > 100 ? '...' : ''); ?></p>
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                <i class="fas fa-users"></i> <?php echo $community['member_count']; ?>
                                                                <i class="fas fa-comments ms-2"></i> <?php echo $community['post_count']; ?>
                                                            </small>
                                                            <span class="badge bg-<?php echo $community['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo t($community['status']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="card-footer bg-transparent">
                                                        <div class="btn-group w-100" role="group">
                                                            <a href="community_details.php?id=<?php echo $community['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye"></i> <?php echo t('view'); ?>
                                                            </a>
                                                            <button class="btn btn-outline-secondary btn-sm" onclick="editCommunity(<?php echo htmlspecialchars(json_encode($community)); ?>)">
                                                                <i class="fas fa-edit"></i> <?php echo t('edit'); ?>
                                                            </button>
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

                <!-- All Communities (Admin only) -->
                <?php if (has_role('admin') && !empty($all_communities)): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><?php echo t('all_communities'); ?></h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th><?php echo t('name'); ?></th>
                                                    <th><?php echo t('creator'); ?></th>
                                                    <th><?php echo t('privacy'); ?></th>
                                                    <th><?php echo t('members'); ?></th>
                                                    <th><?php echo t('posts'); ?></th>
                                                    <th><?php echo t('status'); ?></th>
                                                    <th><?php echo t('created'); ?></th>
                                                    <th><?php echo t('actions'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($all_communities as $community): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo htmlspecialchars($community['name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($community['description'], 0, 50)) . (strlen($community['description']) > 50 ? '...' : ''); ?></small>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($community['creator_name']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $community['privacy'] === 'public' ? 'success' : ($community['privacy'] === 'private' ? 'warning' : 'info'); ?>">
                                                                <?php echo t($community['privacy']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $community['member_count']; ?></td>
                                                        <td><?php echo $community['post_count']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $community['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                                <?php echo t($community['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo format_date($community['created_at']); ?></td>
                                                        <td>
                                                            <a href="community_details.php?id=<?php echo $community['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Create Community Modal -->
    <div class="modal fade" id="createCommunityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create_community">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo t('create_community'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label"><?php echo t('community_name'); ?> *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label"><?php echo t('description'); ?></label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="privacy" class="form-label"><?php echo t('privacy'); ?> *</label>
                            <select class="form-select" id="privacy" name="privacy" required>
                                <option value="public"><?php echo t('public'); ?> - <?php echo t('anyone_can_join'); ?></option>
                                <option value="private"><?php echo t('private'); ?> - <?php echo t('approval_required'); ?></option>
                                <option value="invite_only"><?php echo t('invite_only'); ?> - <?php echo t('invitation_required'); ?></option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?php echo t('categories'); ?></label>
                            <div class="row">
                                <?php foreach ($categories as $category): ?>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="categories[]" value="<?php echo $category['id']; ?>" id="cat_<?php echo $category['id']; ?>">
                                            <label class="form-check-label" for="cat_<?php echo $category['id']; ?>">
                                                <i class="<?php echo $category['icon']; ?>" style="color: <?php echo $category['color']; ?>"></i>
                                                <?php echo htmlspecialchars($category['name']); ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo t('create_community'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Community Modal -->
    <div class="modal fade" id="editCommunityModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_community">
                    <input type="hidden" name="community_id" id="edit_community_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo t('edit_community'); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label"><?php echo t('community_name'); ?> *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label"><?php echo t('description'); ?></label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_privacy" class="form-label"><?php echo t('privacy'); ?> *</label>
                                    <select class="form-select" id="edit_privacy" name="privacy" required>
                                        <option value="public"><?php echo t('public'); ?></option>
                                        <option value="private"><?php echo t('private'); ?></option>
                                        <option value="invite_only"><?php echo t('invite_only'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label"><?php echo t('status'); ?> *</label>
                                    <select class="form-select" id="edit_status" name="status" required>
                                        <option value="active"><?php echo t('active'); ?></option>
                                        <option value="archived"><?php echo t('archived'); ?></option>
                                        <option value="suspended"><?php echo t('suspended'); ?></option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo t('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo t('update_community'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCommunity(community) {
            document.getElementById('edit_community_id').value = community.id;
            document.getElementById('edit_name').value = community.name;
            document.getElementById('edit_description').value = community.description;
            document.getElementById('edit_privacy').value = community.privacy;
            document.getElementById('edit_status').value = community.status;

            new bootstrap.Modal(document.getElementById('editCommunityModal')).show();
        }
    </script>
</body>

</html>





