<?php
require '../includes/db.php';
require '../includes/auth_instructor.php';

$user_id = $_SESSION['user']['id'];
$sql = "SELECT * FROM earnings WHERE instructor_id = $user_id";
$res = mysqli_query($conn, $sql);
$total = 0;
?>

<h2>Your Earnings</h2>
<table border="1">
  <tr>
    <th>Course</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Date</th>
  </tr>
  <?php while($row = mysqli_fetch_assoc($res)): 
      $total += $row['amount']; ?>
    <tr>
      <td><?= $row['course_name'] ?></td>
      <td><?= number_format($row['amount'], 2) ?></td>
      <td><?= $row['status'] ?></td>
      <td><?= $row['created_at'] ?></td>
    </tr>
  <?php endwhile; ?>
</table>
<p><strong>Total Earnings:</strong> $<?= number_format($total, 2) ?></p>