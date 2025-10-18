<?php

/**
 * Process Credit Card Payment
 * Handle card payments via Paystack/Stripe
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
$reference = $payment_data['reference'];

// Get user details
try {
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$student_id]);
    $user = $stmt_user->fetch();
} catch (PDOException $e) {
    error_log("Error fetching user: " . $e->getMessage());
    header('Location: course_payment.php?course_id=' . $course_id);
    exit;
}

// Paystack Public Key (replace with your actual key)
$paystack_public_key = 'pk_test_your_key_here';
$amount_in_pesewas = $amount * 100; // Convert to pesewas for Paystack

// Handle Paystack callback
if (isset($_GET['reference']) && isset($_GET['status'])) {
    if ($_GET['status'] === 'success') {
        try {
            // Verify payment with Paystack
            // In production: call Paystack API to verify

            // Update transaction
            $stmt_update = $pdo->prepare("
                UPDATE transactions 
                SET status = 'completed', payment_reference = ?, completed_at = NOW()
                WHERE id = ?
            ");
            $stmt_update->execute([$_GET['reference'], $transaction_id]);

            // Enroll student
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
                $_SESSION['success_message'] = __('payment_success_enrolled') ?? 'Paiement réussi ! Vous êtes inscrit au cours.';
                header('Location: view_course.php?course_id=' . $course_id);
                exit;
            }
        } catch (Exception $e) {
            error_log("Card payment error: " . $e->getMessage());
            $error_message = __('payment_error') ?? 'Erreur de paiement';
        }
    } else {
        $_SESSION['error_message'] = __('payment_cancelled') ?? 'Paiement annulé';
        header('Location: course_payment.php?course_id=' . $course_id);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('card_payment') ?? 'Paiement par Carte' ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
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

        .payment-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 3rem;
            max-width: 500px;
            text-align: center;
        }

        .card-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
        }

        h1 {
            color: var(--gray-900);
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .amount-display {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary);
            margin: 1.5rem 0;
        }

        .btn {
            padding: 1.25rem 2.5rem;
            border-radius: 8px;
            border: none;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
            font-size: 1.125rem;
            margin: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: #e5e7eb;
            color: var(--gray-900);
        }

        .secure-icons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            font-size: 2rem;
        }
    </style>
</head>

<body>
    <div class="payment-card">
        <div class="card-icon">
            <i class="fas fa-credit-card"></i>
        </div>

        <h1><?= __('card_payment') ?? 'Paiement par Carte Bancaire' ?></h1>

        <p style="color: var(--gray-600); margin-bottom: 1rem;">
            <?= htmlspecialchars($payment_data['course_title']) ?>
        </p>

        <div class="amount-display">
            <?= number_format($amount, 2) ?> GHS
        </div>

        <p style="color: var(--gray-600); margin-bottom: 2rem;">
            <?= __('secure_payment_gateway') ?? 'Paiement sécurisé via Paystack' ?>
        </p>

        <button onclick="payWithPaystack()" class="btn btn-primary" id="payButton">
            <i class="fas fa-lock"></i>
            <?= __('pay_with_card') ?? 'Payer avec ma Carte' ?>
        </button>

        <a href="course_payment.php?course_id=<?= $course_id ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i>
            <?= __('choose_another_method') ?? 'Autre méthode' ?>
        </a>

        <div class="secure-icons">
            <i class="fab fa-cc-visa" style="color: #1A1F71;"></i>
            <i class="fab fa-cc-mastercard" style="color: #EB001B;"></i>
            <i class="fab fa-cc-amex" style="color: #006FCF;"></i>
            <i class="fas fa-shield-alt" style="color: #10b981;"></i>
        </div>

        <p style="color: var(--gray-600); font-size: 0.875rem; margin-top: 2rem;">
            <i class="fas fa-lock"></i>
            <?= __('ssl_encrypted') ?? 'Toutes les transactions sont cryptées SSL' ?>
        </p>
    </div>

    <script>
        function payWithPaystack() {
            const handler = PaystackPop.setup({
                key: '<?= $paystack_public_key ?>',
                email: '<?= htmlspecialchars($user['email']) ?>',
                amount: <?= $amount_in_pesewas ?>,
                currency: 'GHS',
                ref: '<?= $reference ?>',
                metadata: {
                    custom_fields: [{
                            display_name: "Student Name",
                            variable_name: "student_name",
                            value: "<?= htmlspecialchars($user['full_name']) ?>"
                        },
                        {
                            display_name: "Course",
                            variable_name: "course_title",
                            value: "<?= htmlspecialchars($payment_data['course_title']) ?>"
                        },
                        {
                            display_name: "Transaction ID",
                            variable_name: "transaction_id",
                            value: "<?= $transaction_id ?>"
                        }
                    ]
                },
                onClose: function() {
                    alert('<?= __('payment_cancelled') ?? 'Paiement annulé' ?>');
                },
                callback: function(response) {
                    // Payment successful
                    const reference = response.reference;

                    // Show success message
                    document.getElementById('payButton').innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('verifying') ?? 'Vérification...' ?>';
                    document.getElementById('payButton').disabled = true;

                    // Redirect to verification
                    window.location.href = 'process_card_payment.php?transaction_id=<?= $transaction_id ?>&reference=' + reference + '&status=success';
                }
            });

            handler.openIframe();
        }

        // Alternative: Auto-open Paystack on page load
        // Uncomment if you want automatic popup
        // window.onload = function() {
        //     setTimeout(payWithPaystack, 1000);
        // };
    </script>
</body>

</html>