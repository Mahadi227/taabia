<?php
require '../includes/db.php';
require '../includes/auth_admin.php';

if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    mysqli_query($conn, "UPDATE payouts SET status = 'approved' WHERE id = $id");
}

$result = mysqli_query($conn, "SELECT p.*, u.full_name FROM payouts p 
                               JOIN users u ON u.id = p.instructor_id 
                               ORDER BY p.created_at DESC");
?>

<h2>Payout Requests</h2>
<table border="1">
  <tr>
    <th>ID</th>
    <th>Instructor</th>
    <th>Amount</th>
    <th>Method</th>
    <th>Status</th>
    <th>Action</th>
  </tr>
  <?php while($row = mysqli_fetch_assoc($result)): ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td><?= $row['full_name'] ?></td>
      <td><?= number_format($row['amount'], 2) ?></td>
      <td><?= $row['method'] ?></td>
      <td><?= $row['status'] ?></td>
      <td>
        <?php if ($row['status'] === 'pending'): ?>
          <a href="?approve=<?= $row['id'] ?>">Approve</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endwhile; ?>
</table>