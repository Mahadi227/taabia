<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

require_role('instructor');

$user_id = $_SESSION['user_id'] ?? 0;
$success = '';
$error = '';

// Handle language update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $language = $_POST['language'] ?? 'fr';
    try {
        $stmt = $pdo->prepare("UPDATE users SET language = ? WHERE id = ?");
        $stmt->execute([$language, $user_id]);
        $success = "Votre préférence de langue a été mise à jour avec succès.";
    } catch (PDOException $e) {
        $error = "Une erreur est survenue lors de la mise à jour de votre langue.";
    }
}

// Get current language
$current_language = 'fr';
try {
    $stmt = $pdo->prepare("SELECT language FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $current_language = $stmt->fetchColumn() ?: 'fr';
} catch (PDOException $e) {
}

$languages = [
    'fr' => 'Français',
    'en' => 'English',
    'ar' => 'العربية'
];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Préférences de langue | Espace Instructeur | TaaBia</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Inter', Arial, sans-serif;
        background: #f4f6f8;
        margin: 0;
    }

    .container {
        max-width: 480px;
        margin: 48px auto;
        background: #fff;
        padding: 40px 32px 32px 32px;
        border-radius: 16px;
        box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
    }

    h2 {
        font-size: 1.6rem;
        font-weight: 600;
        margin-bottom: 28px;
        color: #222;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .form-group {
        margin-bottom: 24px;
    }

    label {
        font-weight: 500;
        color: #444;
        margin-bottom: 8px;
        display: block;
    }

    select {
        width: 100%;
        padding: 10px;
        border-radius: 8px;
        border: 1px solid #d1d5db;
        font-size: 1rem;
        background: #f9fafb;
        transition: border-color 0.2s;
    }

    select:focus {
        border-color: #007bff;
        outline: none;
    }

    .btn {
        background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
        color: #fff;
        border: none;
        padding: 12px 32px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(0, 123, 255, 0.08);
        transition: background 0.2s;
    }

    .btn:hover {
        background: linear-gradient(90deg, #0056b3 0%, #007bff 100%);
    }

    .alert {
        padding: 12px;
        border-radius: 8px;
        margin-bottom: 22px;
        font-size: 1rem;
    }

    .alert-success {
        background: #e6f7ee;
        color: #218838;
        border: 1px solid #b7e4c7;
    }

    .alert-danger {
        background: #fbeaea;
        color: #c82333;
        border: 1px solid #f5c6cb;
    }
    </style>
</head>

<body>
    <div class="container">
        <h2><i class="fas fa-language"></i> Préférences de langue</h2>
        <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="form-group">
                <label for="language">Sélectionnez votre langue préférée :</label>
                <select name="language" id="language">
                    <?php foreach ($languages as $code => $label): ?>
                    <option value="<?= $code ?>" <?= $current_language === $code ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn"><i class="fas fa-save"></i> Enregistrer</button>
        </form>
    </div>
</body>

</html>