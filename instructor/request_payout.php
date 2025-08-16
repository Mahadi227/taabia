<?php
require '../includes/db.php';
require '../includes/auth_instructor.php';

$instructor_id = $_SESSION['user']['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = $_POST['amount'];
    $method = $_POST['method'];

    $sql = "INSERT INTO payouts (instructor_id, amount, method, status)
            VALUES ($instructor_id, $amount, '$method', 'pending')";
    if (mysqli_query($conn, $sql)) {
        echo "Payout request sent!";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<h2>Request Payout</h2>
<form method="post">
  Amount: <input type="number" name="amount" step="0.01" required><br>
  Payment Method:
  <select name="method">
    <option value="mobile_money">Mobile Money</option>
    <option value="bank_transfer">Bank Transfer</option>
  </select><br>
  <button type="submit">Submit Request</button>
</form>