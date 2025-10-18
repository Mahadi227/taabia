<?php

/**
 * Course Payment Page
 * Complete payment integration for Mobile Money and Credit Cards
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';
require_role('student');

$student_id = $_SESSION['user_id'];
$course_id = $_GET['course_id'] ?? 0;

if (!$course_id) {
    header('Location: all_courses.php');
    exit;
}

// Get course details
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as instructor_name
        FROM courses c
        JOIN users u ON c.instructor_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: all_courses.php');
        exit;
    }

    // Check if already enrolled
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM student_courses WHERE student_id = ? AND course_id = ?");
    $stmt_check->execute([$student_id, $course_id]);
    if ($stmt_check->fetchColumn() > 0) {
        header('Location: view_course.php?course_id=' . $course_id);
        exit;
    }

    // Get user details
    $stmt_user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_user->execute([$student_id]);
    $user = $stmt_user->fetch();
} catch (PDOException $e) {
    error_log("Error in course_payment: " . $e->getMessage());
    header('Location: all_courses.php');
    exit;
}

// Process payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $network = $_POST['network'] ?? '';

    if (empty($payment_method)) {
        $error_message = __('select_payment_method') ?? 'Veuillez sélectionner une méthode de paiement';
    } else {
        // Create transaction record
        try {
            $transaction_ref = 'TXN-' . strtoupper(uniqid());

            // Insert payment record
            $stmt_payment = $pdo->prepare("
                INSERT INTO transactions (
                    student_id, 
                    instructor_id, 
                    course_id, 
                    type, 
                    amount, 
                    status, 
                    payment_method,
                    reference_number,
                    created_at
                ) VALUES (?, ?, ?, 'course_purchase', ?, 'pending', ?, ?, NOW())
            ");
            $stmt_payment->execute([
                $student_id,
                $course['instructor_id'],
                $course_id,
                $course['price'],
                $payment_method,
                $transaction_ref
            ]);
            $transaction_id = $pdo->lastInsertId();

            error_log("Created transaction #$transaction_id - Method: $payment_method, Ref: $transaction_ref");

            // Handle different payment methods
            switch ($payment_method) {
                case 'mtn_momo':
                case 'vodafone':
                case 'airteltigo':
                    // Mobile Money payment
                    if (empty($phone_number)) {
                        $error_message = __('phone_required') ?? 'Numéro de téléphone requis';
                    } else {
                        // Store payment details in session
                        $_SESSION['pending_payment'] = [
                            'transaction_id' => $transaction_id,
                            'course_id' => $course_id,
                            'course_title' => $course['title'],
                            'amount' => $course['price'],
                            'method' => $payment_method,
                            'phone' => $phone_number,
                            'network' => $network,
                            'reference' => $transaction_ref
                        ];

                        // Redirect to Mobile Money processing
                        header('Location: process_mobile_payment.php?transaction_id=' . $transaction_id);
                        exit;
                    }
                    break;

                case 'credit_card':
                    // Credit card payment - redirect to card processing
                    $_SESSION['pending_payment'] = [
                        'transaction_id' => $transaction_id,
                        'course_id' => $course_id,
                        'course_title' => $course['title'],
                        'amount' => $course['price'],
                        'method' => 'credit_card',
                        'reference' => $transaction_ref
                    ];

                    header('Location: process_card_payment.php?transaction_id=' . $transaction_id);
                    exit;

                case 'demo_free':
                    // Demo mode - instant enrollment
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
                        // Update transaction to completed
                        $stmt_update = $pdo->prepare("UPDATE transactions SET status = 'completed' WHERE id = ?");
                        $stmt_update->execute([$transaction_id]);

                        $_SESSION['success_message'] = __('enrollment_success') ?? 'Inscription réussie !';
                        header('Location: view_course.php?course_id=' . $course_id);
                        exit;
                    } else {
                        $error_message = __('enrollment_failed') ?? 'Erreur d\'inscription';
                    }
                    break;

                default:
                    $error_message = __('invalid_payment_method') ?? 'Méthode de paiement invalide';
            }
        } catch (PDOException $e) {
            error_log("Payment processing error: " . $e->getMessage());
            $error_message = __('payment_error') ?? 'Erreur lors du traitement du paiement: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('payment') ?? 'Paiement' ?> | TaaBia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #004075;
            --secondary: #004085;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-600: #4b5563;
            --gray-700: #374151;
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
            padding: 2rem;
        }

        .payment-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .card-header h1 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .course-summary {
            padding: 1.5rem;
            background: var(--gray-50);
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .course-summary h3 {
            color: var(--gray-900);
            margin-bottom: 0.75rem;
        }

        .price-box {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            color: white;
            margin-bottom: 2rem;
        }

        .price-box .amount {
            font-size: 3rem;
            font-weight: 800;
            margin: 0.5rem 0;
        }

        .payment-methods {
            margin-bottom: 2rem;
        }

        .payment-method {
            padding: 1.5rem;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method:hover {
            border-color: var(--primary);
            background: var(--gray-50);
        }

        .payment-method.selected {
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.05);
        }

        .payment-method-header {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .payment-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .payment-icon.momo {
            background: linear-gradient(135deg, #FFCC00, #FFB700);
        }

        .payment-icon.vodafone {
            background: linear-gradient(135deg, #E60000, #CC0000);
        }

        .payment-icon.airteltigo {
            background: linear-gradient(135deg, #ED1C24, #D71920);
        }

        .payment-icon.card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
        }

        .payment-icon.demo {
            background: linear-gradient(135deg, var(--success), #059669);
        }

        .payment-details {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--gray-200);
            display: none;
        }

        .payment-details.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
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
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid var(--primary);
        }

        .secure-badge {
            text-align: center;
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 8px;
            margin-top: 2rem;
        }

        .secure-badge i {
            color: var(--success);
            font-size: 1.25rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .price-box .amount {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="payment-container">
        <!-- Back Button -->
        <a href="enroll.php?course_id=<?= $course_id ?>" style="display: inline-flex; align-items: center; gap: 0.5rem; color: white; text-decoration: none; margin-bottom: 1.5rem; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> <?= __('back') ?? 'Retour' ?>
        </a>

        <!-- Course Info Card -->
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-credit-card"></i> <?= __('payment') ?? 'Paiement du Cours' ?></h1>
                <p><?= __('complete_payment_enroll') ?? 'Complétez le paiement pour vous inscrire au cours' ?></p>
            </div>

            <div class="card-body">
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle" style="font-size: 1.5rem;"></i>
                        <div><?= htmlspecialchars($error_message) ?></div>
                    </div>
                <?php endif; ?>

                <!-- Course Summary -->
                <div class="course-summary">
                    <h3><?= htmlspecialchars($course['title']) ?></h3>
                    <p style="color: var(--gray-600); margin-bottom: 0.5rem;">
                        <i class="fas fa-user"></i> <?= __('by') ?? 'Par' ?> <?= htmlspecialchars($course['instructor_name']) ?>
                    </p>
                    <p style="color: var(--gray-700); font-size: 0.9rem;">
                        <?= htmlspecialchars(substr($course['description'] ?? '', 0, 200)) ?>...
                    </p>
                </div>

                <!-- Price Display -->
                <div class="price-box">
                    <div style="font-size: 1rem; opacity: 0.9;">
                        <?= __('total_amount') ?? 'Montant Total' ?>
                    </div>
                    <div class="amount"><?= number_format($course['price'], 2) ?> GHS</div>
                    <div style="font-size: 0.875rem; opacity: 0.9;">
                        <?= __('one_time_payment') ?? 'Paiement unique - Accès à vie' ?>
                    </div>
                </div>

                <!-- Payment Form -->
                <form method="POST" id="paymentForm">
                    <h3 style="margin-bottom: 1.5rem; color: var(--gray-900);">
                        <i class="fas fa-wallet"></i> <?= __('select_payment_method') ?? 'Sélectionnez votre méthode de paiement' ?>
                    </h3>

                    <!-- Mobile Money - MTN -->
                    <div class="payment-method" data-method="mtn_momo">
                        <div class="payment-method-header">
                            <div class="payment-icon momo">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--gray-900);">MTN Mobile Money</strong>
                                <small style="color: var(--gray-600);">*170# • <?= __('instant_payment') ?? 'Paiement instantané' ?></small>
                            </div>
                            <input type="radio" name="payment_method" value="mtn_momo" id="mtn_momo">
                        </div>
                        <div class="payment-details" id="mtn_momo_details">
                            <div class="form-group">
                                <label for="mtn_phone"><?= __('mtn_phone_number') ?? 'Numéro MTN Mobile Money' ?></label>
                                <input type="tel" id="mtn_phone" name="phone_number" placeholder="024XXXXXXX" pattern="0[2-5][0-9]{8}">
                                <small style="color: var(--gray-600); display: block; margin-top: 0.5rem;">
                                    <?= __('enter_mtn_number') ?? 'Entrez votre numéro MTN (024, 025, 053, 054, 055, 059)' ?>
                                </small>
                            </div>
                            <input type="hidden" name="network" value="MTN">
                        </div>
                    </div>

                    <!-- Mobile Money - Vodafone -->
                    <div class="payment-method" data-method="vodafone">
                        <div class="payment-method-header">
                            <div class="payment-icon vodafone">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--gray-900);">Vodafone Cash</strong>
                                <small style="color: var(--gray-600);">*110# • <?= __('instant_payment') ?? 'Paiement instantané' ?></small>
                            </div>
                            <input type="radio" name="payment_method" value="vodafone" id="vodafone">
                        </div>
                        <div class="payment-details" id="vodafone_details">
                            <div class="form-group">
                                <label for="vodafone_phone"><?= __('vodafone_phone_number') ?? 'Numéro Vodafone Cash' ?></label>
                                <input type="tel" id="vodafone_phone" name="phone_number" placeholder="020XXXXXXX" pattern="0[2-5][0-9]{8}">
                                <small style="color: var(--gray-600); display: block; margin-top: 0.5rem;">
                                    <?= __('enter_vodafone_number') ?? 'Entrez votre numéro Vodafone (020, 050)' ?>
                                </small>
                            </div>
                            <input type="hidden" name="network" value="Vodafone">
                        </div>
                    </div>

                    <!-- Mobile Money - AirtelTigo -->
                    <div class="payment-method" data-method="airteltigo">
                        <div class="payment-method-header">
                            <div class="payment-icon airteltigo">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--gray-900);">AirtelTigo Money</strong>
                                <small style="color: var(--gray-600);">*110# • <?= __('instant_payment') ?? 'Paiement instantané' ?></small>
                            </div>
                            <input type="radio" name="payment_method" value="airteltigo" id="airteltigo">
                        </div>
                        <div class="payment-details" id="airteltigo_details">
                            <div class="form-group">
                                <label for="airteltigo_phone"><?= __('airteltigo_phone_number') ?? 'Numéro AirtelTigo Money' ?></label>
                                <input type="tel" id="airteltigo_phone" name="phone_number" placeholder="027XXXXXXX" pattern="0[2-5][0-9]{8}">
                                <small style="color: var(--gray-600); display: block; margin-top: 0.5rem;">
                                    <?= __('enter_airteltigo_number') ?? 'Entrez votre numéro AirtelTigo (026, 027, 056, 057)' ?>
                                </small>
                            </div>
                            <input type="hidden" name="network" value="AirtelTigo">
                        </div>
                    </div>

                    <!-- Credit Card -->
                    <div class="payment-method" data-method="credit_card">
                        <div class="payment-method-header">
                            <div class="payment-icon card">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--gray-900);"><?= __('credit_card') ?? 'Carte Bancaire' ?></strong>
                                <small style="color: var(--gray-600);">Visa, Mastercard, American Express</small>
                            </div>
                            <input type="radio" name="payment_method" value="credit_card" id="credit_card">
                        </div>
                        <div class="payment-details" id="credit_card_details">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <div><?= __('card_redirect_notice') ?? 'Vous serez redirigé vers une page de paiement sécurisée' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Demo Mode -->
                    <div class="payment-method" data-method="demo_free">
                        <div class="payment-method-header">
                            <div class="payment-icon demo">
                                <i class="fas fa-gift"></i>
                            </div>
                            <div style="flex: 1;">
                                <strong style="display: block; color: var(--gray-900);"><?= __('demo_mode') ?? 'Mode Démo' ?></strong>
                                <small style="color: var(--gray-600);"><?= __('test_enrollment') ?? 'Inscription gratuite pour tests' ?></small>
                            </div>
                            <input type="radio" name="payment_method" value="demo_free" id="demo_free">
                        </div>
                        <div class="payment-details" id="demo_free_details">
                            <div class="alert alert-info">
                                <i class="fas fa-flask"></i>
                                <div><?= __('demo_notice') ?? 'Mode test : Vous serez inscrit immédiatement sans paiement réel' ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" name="process_payment" class="btn btn-primary btn-block" style="margin-top: 2rem;">
                        <i class="fas fa-lock"></i>
                        <?= __('proceed_payment') ?? 'Procéder au paiement' ?>
                    </button>

                    <a href="enroll.php?course_id=<?= $course_id ?>" class="btn btn-secondary btn-block" style="margin-top: 1rem;">
                        <i class="fas fa-times"></i>
                        <?= __('cancel') ?? 'Annuler' ?>
                    </a>
                </form>

                <!-- Secure Badge -->
                <div class="secure-badge">
                    <i class="fas fa-shield-alt"></i>
                    <strong style="color: var(--gray-900); display: block; margin: 0.5rem 0;">
                        <?= __('secure_payment') ?? 'Paiement 100% Sécurisé' ?>
                    </strong>
                    <small style="color: var(--gray-600);">
                        <?= __('ssl_encrypted') ?? 'Vos informations sont protégées par cryptage SSL' ?>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function(e) {
                // Don't trigger if clicking on input directly
                if (e.target.tagName === 'INPUT') return;

                const radio = this.querySelector('input[type="radio"]');
                const methodName = this.dataset.method;

                // Remove selected class from all
                document.querySelectorAll('.payment-method').forEach(m => {
                    m.classList.remove('selected');
                });

                // Hide all details
                document.querySelectorAll('.payment-details').forEach(d => {
                    d.classList.remove('active');
                });

                // Select this one
                this.classList.add('selected');
                radio.checked = true;

                // Show details for this method
                const details = document.getElementById(methodName + '_details');
                if (details) {
                    details.classList.add('active');
                }
            });
        });

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');

            if (!selectedMethod) {
                e.preventDefault();
                alert('<?= __('select_payment_method') ?? 'Veuillez sélectionner une méthode de paiement' ?>');
                return false;
            }

            const method = selectedMethod.value;

            // Validate phone number for mobile money
            if (method === 'mtn_momo' || method === 'vodafone' || method === 'airteltigo') {
                const phoneInput = document.querySelector(`#${method}_details input[name="phone_number"]`);
                if (phoneInput && !phoneInput.value) {
                    e.preventDefault();
                    alert('<?= __('phone_required') ?? 'Veuillez entrer votre numéro de téléphone' ?>');
                    phoneInput.focus();
                    return false;
                }

                // Validate phone format
                if (phoneInput && !/^0[2-5][0-9]{8}$/.test(phoneInput.value)) {
                    e.preventDefault();
                    alert('<?= __('invalid_phone_format') ?? 'Format de numéro invalide. Utilisez le format: 0XXXXXXXXX' ?>');
                    phoneInput.focus();
                    return false;
                }
            }

            // Confirm payment
            if (method !== 'demo_free') {
                const amount = '<?= number_format($course['price'], 2) ?> GHS';
                if (!confirm(`<?= __('confirm_payment') ?? 'Confirmer le paiement de' ?> ${amount} ?`)) {
                    e.preventDefault();
                    return false;
                }
            }

            // Show loading
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?= __('processing') ?? 'Traitement en cours...' ?>';
        });

        // Copy phone number between fields when switching networks
        let lastPhoneNumber = '';
        document.querySelectorAll('input[type="tel"]').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value) {
                    lastPhoneNumber = this.value;
                }
            });

            input.addEventListener('focus', function() {
                if (!this.value && lastPhoneNumber) {
                    this.value = lastPhoneNumber;
                }
            });
        });
    </script>
</body>

</html>