<?php
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $post_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->execute([$post_id]);
        $success_message = "Article supprimé avec succès.";
    } catch (PDOException $e) {
        $error_message = "Erreur lors de la suppression de l'article.";
    }
}

// Get blog posts with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Get total count
    $total_posts = $pdo->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();
    $total_pages = ceil($total_posts / $limit);

    // Get posts with author and category info
    $posts_query = "
        SELECT bp.*, bc.name as category_name, u.full_name as author_name
        FROM blog_posts bp 
        LEFT JOIN blog_categories bc ON bp.category_id = bc.id 
        LEFT JOIN users u ON bp.author_id = u.id 
        ORDER BY bp.created_at DESC 
        LIMIT ? OFFSET ?
    ";
    
    $stmt = $pdo->prepare($posts_query);
    $stmt->execute([$limit, $offset]);
    $blog_posts = $stmt->fetchAll();

} catch (PDOException $e) {
    $blog_posts = [];
    $total_pages = 0;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Articles | Admin TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <h1><i class="fas fa-newspaper"></i> Gestion des Articles</h1>
                <a href="add_blog_post.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nouvel Article
                </a>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success_message ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Blog Posts Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Auteur</th>
                            <th>Statut</th>
                            <th>Date de création</th>
                            <th>Vues</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($blog_posts)): ?>
                            <tr>
                                <td colspan="7" class="text-center">
                                    <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--text-secondary); margin-bottom: var(--spacing-md);"></i>
                                    <p>Aucun article trouvé</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($blog_posts as $post): ?>
                                <tr>
                                    <td>
                                        <div class="post-title">
                                            <strong><?= htmlspecialchars($post['title']) ?></strong>
                                            <small class="text-muted"><?= htmlspecialchars($post['slug']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($post['category_name']): ?>
                                            <span class="badge badge-primary"><?= htmlspecialchars($post['category_name']) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Aucune catégorie</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($post['author_name']) ?></td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($post['status']) {
                                            case 'published':
                                                $status_class = 'badge-success';
                                                $status_text = 'Publié';
                                                break;
                                            case 'draft':
                                                $status_class = 'badge-warning';
                                                $status_text = 'Brouillon';
                                                break;
                                            case 'archived':
                                                $status_class = 'badge-danger';
                                                $status_text = 'Archivé';
                                                break;
                                        }
                                        ?>
                                        <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($post['created_at'])) ?></td>
                                    <td><?= $post['view_count'] ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_blog_post.php?id=<?= $post['id'] ?>" class="btn btn-sm btn-outline" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../public/main_site/view_blog.php?slug=<?= $post['slug'] ?>" target="_blank" class="btn btn-sm btn-outline" title="Voir">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="?delete=<?= $post['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" 
                                               onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet article ?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="btn <?= $i == $page ? 'btn-primary' : 'btn-outline' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="btn btn-outline">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 