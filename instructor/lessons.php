<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_role('instructor');

$instructor_id = $_SESSION['user_id'];

// Get all lessons for this instructor with course information
try {
    $stmt = $pdo->prepare("
        SELECT l.*, c.title AS course_title 
        FROM lessons l 
        JOIN courses c ON l.course_id = c.id 
        WHERE c.instructor_id = ? 
        ORDER BY c.title, l.order_index
    ");
    $stmt->execute([$instructor_id]);
    $lessons = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Database error in lessons.php: " . $e->getMessage());
    $lessons = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mes Leçons | TaaBia</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: #f0f2f5;
      color: #333;
      padding: 2rem;
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
    }
    
    h1 {
      color: #1976d2;
      font-size: 2rem;
    }
    
    .btn {
      display: inline-block;
      padding: 0.8rem 1.5rem;
      background: #1976d2;
      color: white;
      text-decoration: none;
      border-radius: 8px;
      font-weight: 500;
      transition: background-color 0.3s;
    }
    
    .btn:hover {
      background: #1565c0;
    }
    
    .btn-success {
      background: #28a745;
    }
    
    .btn-success:hover {
      background: #218838;
    }
    
    .btn-danger {
      background: #dc3545;
    }
    
    .btn-danger:hover {
      background: #c82333;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    th, td {
      padding: 1rem;
      text-align: left;
      border-bottom: 1px solid #eee;
    }
    
    th {
      background: #1976d2;
      color: white;
      font-weight: 600;
    }
    
    tr:hover {
      background: #f8f9fa;
    }
    
    .lesson-type {
      display: inline-block;
      padding: 0.3rem 0.8rem;
      border-radius: 15px;
      font-size: 0.8rem;
      font-weight: 600;
    }
    
    .type-video {
      background: #e3f2fd;
      color: #1976d2;
    }
    
    .type-pdf {
      background: #fff3e0;
      color: #f57c00;
    }
    
    .type-text {
      background: #f3e5f5;
      color: #7b1fa2;
    }
    
    .type-quiz {
      background: #e8f5e8;
      color: #2e7d32;
    }
    
    .actions {
      display: flex;
      gap: 0.5rem;
    }
    
    .empty-state {
      text-align: center;
      padding: 3rem;
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }
    
    .empty-state i {
      font-size: 3rem;
      color: #ccc;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1><i class="fas fa-book-open"></i> Mes Leçons</h1>
      <a href="add_lesson.php" class="btn">
        <i class="fas fa-plus"></i> Ajouter une leçon
      </a>
    </div>

    <?php if (empty($lessons)): ?>
      <div class="empty-state">
        <i class="fas fa-book-open"></i>
        <h3>Aucune leçon trouvée</h3>
        <p>Vous n'avez pas encore créé de leçons.</p>
        <a href="add_lesson.php" class="btn">Créer votre première leçon</a>
      </div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Titre</th>
            <th>Cours</th>
            <th>Type</th>
            <th>Ordre</th>
            <th>Date de création</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lessons as $lesson): ?>
            <tr>
              <td><strong><?= htmlspecialchars($lesson['title']) ?></strong></td>
              <td><?= htmlspecialchars($lesson['course_title']) ?></td>
              <td>
                <span class="lesson-type type-<?= $lesson['content_type'] ?>">
                  <?= ucfirst($lesson['content_type']) ?>
                </span>
              </td>
              <td><?= $lesson['order_index'] ?></td>
              <td><?= date('d/m/Y H:i', strtotime($lesson['created_at'])) ?></td>
              <td class="actions">
                <a href="view_lesson.php?id=<?= $lesson['id'] ?>" class="btn btn-success">
                  <i class="fas fa-eye"></i> Voir
                </a>
                <a href="lesson_edit.php?id=<?= $lesson['id'] ?>" class="btn">
                  <i class="fas fa-edit"></i> Modifier
                </a>
                <a href="lesson_delete.php?id=<?= $lesson['id'] ?>" class="btn btn-danger" 
                   onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette leçon ?')">
                  <i class="fas fa-trash"></i> Supprimer
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>

