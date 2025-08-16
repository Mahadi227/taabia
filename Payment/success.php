<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/payment_config.php';
require_once '../includes/payment_processor.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
$gateway = $_GET['gateway'] ?? '';
$reference = $_GET['reference'] ?? '';

if (!$payment_id || !$gateway || !$reference) {
    header('Location: ../student/orders.php');
    exit;
}

try {
    // Verify payment
    $processor = new PaymentProcessor($pdo);
    $verification = $processor->verifyPayment($payment_id, $gateway, $reference);
    
    if ($verification['success']) {
        // Update payment status
        $processor->updatePaymentStatus($payment_id, 'completed', $verification['gateway_data']);
        
        // Get payment details
        $stmt = $pdo->prepare("
            SELECT p.*, o.*, c.title as course_title 
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            LEFT JOIN courses c ON o.product_id = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        $success = true;
        $message = "Paiement effectué avec succès !";
        
        // If it's a course purchase, enroll the student
        if ($payment && $payment['course_title']) {
            $course_message = "Vous êtes maintenant inscrit au cours : " . $payment['course_title'];
        }
        
    } else {
        $success = false;
        $message = "Erreur lors de la vérification du paiement.";
    }
    
} catch (Exception $e) {
    error_log("Payment verification error: " . $e->getMessage());
    $success = false;
    $message = "Une erreur est survenue lors du traitement du paiement.";
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement - TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .payment-result {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }

        .payment-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2rem;
        }

        .payment-icon.success {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .payment-icon.error {
            background: #ffebee;
            color: #c62828;
        }

        .payment-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }

        .payment-message {
            color: #666;
            margin-bottom: 2rem;
        }

        .payment-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: left;
        }

        .payment-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .payment-detail:last-child {
            margin-bottom: 0;
            border-top: 1px solid #e0e0e0;
            padding-top: 0.5rem;
            font-weight: 600;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }

        .btn-primary {
            background: #1976d2;
            color: white;
        }

        .btn-primary:hover {
            background: #1565c0;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .course-message {
            background: #e3f2fd;
            color: #1976d2;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid #1976d2;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .payment-result {
            animation: fadeIn 0.5s ease-out;
        }
    </style>
</head>

<body>
    <div class="payment-result">
        <div class="payment-icon <?= $success ? 'success' : 'error' ?>">
            <i class="fas fa-<?= $success ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        </div>
        
        <h1 class="payment-title">
            <?= $success ? 'Paiement réussi !' : 'Erreur de paiement' ?>
        </h1>
        
        <p class="payment-message">
            <?= htmlspecialchars($message) ?>
        </p>
        
        <?php if ($success && isset($payment)): ?>
            <div class="payment-details">
                <div class="payment-detail">
                    <span>Référence:</span>
                    <span><?= htmlspecialchars($reference) ?></span>
                </div>
                <div class="payment-detail">
                    <span>Montant:</span>
                    <span><?= formatCurrency($payment['amount'], $payment['currency']) ?></span>
                </div>
                <div class="payment-detail">
                    <span>Méthode:</span>
                    <span><?= getPaymentMethodName($payment['payment_method']) ?></span>
                </div>
                <div class="payment-detail">
                    <span>Date:</span>
                    <span><?= date('d/m/Y H:i', strtotime($payment['created_at'])) ?></span>
                </div>
            </div>
            
            <?php if (isset($course_message)): ?>
                <div class="course-message">
                    <i class="fas fa-graduation-cap"></i>
                    <?= htmlspecialchars($course_message) ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div>
            <?php if ($success): ?>
                <a href="../student/my_courses.php" class="btn btn-primary">
                    <i class="fas fa-graduation-cap"></i>
                    Voir mes cours
                </a>
            <?php endif; ?>
            
            <a href="../student/orders.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                Retour aux commandes
            </a>
        </div>
    </div>

    <script>
        // Auto-redirect after 10 seconds
        setTimeout(function() {
            window.location.href = '../student/orders.php';
        }, 10000);
    </script>
</body>
</html>
