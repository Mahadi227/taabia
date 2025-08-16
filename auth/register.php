<?php
require_once '../includes/i18n.php';
require_once '../includes/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['full_name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role']; // 'admin', 'instructor', 'student', 'vendor'

    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $email, $password, $role]);

    header("Location: login.php");
}
?>

<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('register') ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(to right, #ffb300, #009688);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .register-container {
            background-color: #fff;
            padding: 3rem 2rem;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
        }
        .register-container h2 {
            text-align: center;
            color: #333;
            margin-bottom: 1.5rem;
        }
        .register-container form {
            display: flex;
            flex-direction: column;
        }
        .register-container input, .register-container select {
            padding: 0.8rem;
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        .register-container button {
            padding: 0.9rem;
            background-color: #009688;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .register-container button:hover {
            background-color: #00796b;
        }
        .register-container .link {
            margin-top: 1rem;
            text-align: center;
            font-size: 0.9rem;
        }
        .register-container .link a {
            color: #009688;
            text-decoration: none;
        }
    </style>
</head>
<body>
        <div class="register-container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2><?= __('create_account') ?></h2>
            <?php include '../includes/public_language_switcher.php'; ?>
        </div>
        <form method="POST" action="register_action.php">
                         <input type="text" name="full_name" placeholder="<?= __('full_name') ?>" required>
             <input type="email" name="email" placeholder="<?= __('email') ?>" required>
             <input type="password" name="password" placeholder="<?= __('password') ?>" required>
            
            <select name="role" required>
                                 <option value="">-- <?= __('choose_role') ?> --</option>
                 <option value="student"><?= __('student') ?></option>
                 <option value="vendor"><?= __('vendor') ?></option>
                 <option value="instructor"><?= __('instructor') ?></option>
                 <option value="admin"><?= __('admin') ?></option>
            </select>

                         <button type="submit"><?= __('register') ?></button>
        </form>
        <div class="link">
                         <?= __('deja_compte') ?> <a href="login.php"><?= __('se_connecter') ?></a>
        </div>
    </div>
</body>
</html>
