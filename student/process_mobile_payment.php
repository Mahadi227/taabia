<?php

/**
 * Process Mobile Money Payment
 * Handle MTN, Vodafone, AirtelTigo payments
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$transaction_id = $_GET['transaction_id'] ?? 0;

if (!$transaction_id || !isset($_SESSION['pending_payment'])) {
    header('Location: all_courses.php');
    exit;
}

$payment_data = $_SESSION['pending_payment'];
$course_id = $payment_data['course_id'];
$amount = $payment_data['amount'];
$phone = $payment_data['phone'];
$network = $payment_data['network'];
$reference = $payment_data['reference'];

// Simulate payment processing (in production, integrate with real API)
$processing = true;

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_payment'])) {
    try {
        // Update transaction status
        $stmt_update = $pdo->prepare("
            UPDATE transactions 
            SET status = 'completed', payment_reference = ?, completed_at = NOW()
            WHERE id = ?
        ");
        $stmt_update->execute([$reference, $transaction_id]);

        // Enroll student in course
        $enrolled = false;
        $insert_queries = [
            "INSERT INTO student_courses (student_id, course_id, enrolled_at) VALUES (?, ?, NOW())",
            "INSERT INTO student_courses (student_id, course_id, created_at) VALUES (?, ?, NOW())",
            "INSERT INTO student_courses (student_id, course_id) VALUES (?, ?)"
        ];

        foreach ($insert_queries as $query) {
            try {
                $stmt_enroll = $pdo->prepare($query);
                $stmt_enroll->execute([$student_id, $course_id]);
                $enrolled = true;
                break;
            } catch (PDOException $e) {
                continue;
            }
        }

        if ($enrolled) {
            unset($_SESSION['pending_payment']);
            $_SESSION['success_message'] = sprintf(
                __('payment_enrollment_success') ?? 'Paiement réussi ! Vous êtes maintenant inscrit au cours "%s"',
                $payment_data['course_title']
            );
            header('Location: view_course.php?course_id=' . $course_id);
            exit;
        } else {
            throw new Exception('Enrollment failed after payment');
        }
    } catch (Exception $e) {
        error_log("Mobile payment error: " . $e->getMessage());
        $error_message = __('payment_processing_error') ?? 'Erreur lors du traitement du paiement';
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('processing_payment') ?? 'Traitement du Paiement' ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #004085;
            --success: #10b981;
            --gray-50: #f9fafb;
            --gray-600: #4b5563;
            --gray-900: #111827;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #004075 0%, #004082 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .processing-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 3rem;
            max-width: 500px;
            text-align: center;
        }

        .spinner {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            border: 6px solid var(--gray-50);
            border-top: 6px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .network-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            background: linear-gradient(135deg, #FFCC00, #FFB700);
        }

        h1 {
            color: var(--gray-900);
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .instructions {
            background: var(--gray-50);
            padding: 2rem;
            border-radius: 12px;
            margin: 2rem 0;
            text-align: left;
        }

        .instructions h3 {
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .instructions ol {
            padding-left: 1.5rem;
        }

        .instructions li {
            margin-bottom: 0.75rem;
            color: var(--gray-600);
            line-height: 1.6;
        }

        .payment-info {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 2rem 0;
        }

        .payment-info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .payment-info-item:last-child {
            border-bottom: none;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
        }

        .btn {
            padding: 1rem 2rem;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            margin: 0.5rem;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--gray-900);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>

<body>
    <div class="processing-card">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="network-icon">
            <i class="fas fa-mobile-alt"></i>
        </div>

        <h1><?= __('complete_mobile_payment') ?? 'Finaliser le Paiement Mobile Money' ?></h1>

        <div class="payment-info">
            <div class="payment-info-item">
                <span><?= __('network') ?? 'Réseau' ?>:</span>
                <strong><?= htmlspecialchars($network) ?></strong>
            </div>
            <div class="payment-info-item">
                <span><?= __('phone_number') ?? 'Numéro' ?>:</span>
                <strong><?= htmlspecialchars($phone) ?></strong>
            </div>
            <div class="payment-info-item">
                <span><?= __('course') ?? 'Cours' ?>:</span>
                <strong><?= htmlspecialchars($payment_data['course_title']) ?></strong>
            </div>
            <div class="payment-info-item">
                <span><?= __('amount_to_pay') ?? 'Montant à payer' ?>:</span>
                <strong><?= number_format($amount, 2) ?> GHS</strong>
            </div>
        </div>

        <div class="instructions">
            <h3><i class="fas fa-info-circle"></i> <?= __('instructions') ?? 'Instructions' ?></h3>
            <ol>
                <li><?= __('momo_step1') ?? 'Vérifiez que vous avez suffisamment de crédit sur votre compte Mobile Money' ?></li>
                <li><?= __('momo_step2') ?? 'Vous allez recevoir une notification de paiement sur votre téléphone' ?> <strong><?= htmlspecialchars($phone) ?></strong></li>
                <li><?= __('momo_step3') ?? 'Entrez votre code PIN Mobile Money pour autoriser le paiement' ?></li>
                <li><?= __('momo_step4') ?? 'Attendez la confirmation, puis cliquez sur "J\'ai payé" ci-dessous' ?></li>
            </ol>
        </div>

        <div style="background: #fef3c7; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <small style="color: #92400e;">
                <i class="fas fa-clock"></i>
                <?= __('payment_timeout_notice') ?? 'Vous avez 10 minutes pour compléter le paiement' ?>
            </small>
        </div>

        <form method="POST">
            <button type="submit" name="confirm_payment" class="btn btn-success btn-block">
                <i class="fas fa-check-circle"></i>
                <?= __('payment_completed') ?? 'J\'ai payé - Confirmer' ?>
            </button>

            <a href="course_payment.php?course_id=<?= $course_id ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                <?= __('back') ?? 'Retour' ?>
            </a>
        </form>

        <p style="color: var(--gray-600); font-size: 0.875rem; margin-top: 2rem;">
            <i class="fas fa-shield-alt"></i>
            <?= __('secure_transaction') ?? 'Transaction sécurisée et cryptée' ?>
        </p>
    </div>

    <script>
        // Auto-check payment status every 10 seconds (simulate)
        let checkCount = 0;
        const maxChecks = 60; // 10 minutes max

        const checkInterval = setInterval(function() {
            checkCount++;

            // In production, this would call an API to check payment status
            console.log('Checking payment status...', checkCount);

            if (checkCount >= maxChecks) {
                clearInterval(checkInterval);
                alert('<?= __('payment_timeout') ?? 'Délai dépassé. Veuillez réessayer.' ?>');
                window.location.href = 'course_payment.php?course_id=<?= $course_id ?>';
            }
        }, 10000);
    </script>
</body>

</html>