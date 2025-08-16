<?php
require_once '../includes/db.php';
require_once '../includes/payment_config.php';
require_once '../includes/payment_processor.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get the raw POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Get gateway from query parameter
$gateway = $_GET['gateway'] ?? '';

// Log webhook data for debugging
error_log("Webhook received from $gateway: " . $input);

try {
    $processor = new PaymentProcessor($pdo);
    
    switch ($gateway) {
        case 'paystack':
            $result = handlePaystackWebhook($data, $processor);
            break;
        case 'flutterwave':
            $result = handleFlutterwaveWebhook($data, $processor);
            break;
        case 'stripe':
            $result = handleStripeWebhook($data, $processor);
            break;
        case 'paypal':
            $result = handlePayPalWebhook($data, $processor);
            break;
        case 'mtn_momo':
            $result = handleMTNMomoWebhook($data, $processor);
            break;
        default:
            throw new Exception("Unknown gateway: $gateway");
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Webhook processed successfully']);
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

/**
 * Handle Paystack webhook
 */
function handlePaystackWebhook($data, $processor) {
    // Verify webhook signature
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    $expected_signature = hash_hmac('sha512', file_get_contents('php://input'), PaymentConfig::$PAYSTACK['webhook_secret']);
    
    if (!hash_equals($expected_signature, $signature)) {
        throw new Exception("Invalid Paystack webhook signature");
    }
    
    $event = $data['event'];
    $transaction = $data['data'];
    
    if ($event === 'charge.success') {
        // Extract payment ID from reference
        $reference = $transaction['reference'];
        $payment_id = extractPaymentIdFromReference($reference);
        
        if ($payment_id) {
            // Verify payment with Paystack
            $verification = $processor->verifyPayment($payment_id, 'paystack', $reference);
            
            if ($verification['success']) {
                $processor->updatePaymentStatus($payment_id, 'completed', $verification['gateway_data']);
                
                // Enroll student in course if it's a course purchase
                enrollStudentInCourse($payment_id);
            }
        }
    }
}

/**
 * Handle Flutterwave webhook
 */
function handleFlutterwaveWebhook($data, $processor) {
    // Verify webhook signature
    $signature = $_SERVER['HTTP_VERIF_HASH'] ?? '';
    $expected_signature = PaymentConfig::$FLUTTERWAVE['webhook_secret'];
    
    if ($signature !== $expected_signature) {
        throw new Exception("Invalid Flutterwave webhook signature");
    }
    
    $status = $data['status'];
    $tx_ref = $data['tx_ref'];
    
    if ($status === 'successful') {
        // Extract payment ID from reference
        $payment_id = extractPaymentIdFromReference($tx_ref);
        
        if ($payment_id) {
            // Verify payment with Flutterwave
            $verification = $processor->verifyPayment($payment_id, 'flutterwave', $tx_ref);
            
            if ($verification['success']) {
                $processor->updatePaymentStatus($payment_id, 'completed', $verification['gateway_data']);
                
                // Enroll student in course if it's a course purchase
                enrollStudentInCourse($payment_id);
            }
        }
    }
}

/**
 * Handle Stripe webhook
 */
function handleStripeWebhook($data, $processor) {
    // Verify webhook signature
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $endpoint_secret = PaymentConfig::$STRIPE['webhook_secret'];
    
    try {
        $event = \Stripe\Webhook::constructEvent(
            file_get_contents('php://input'),
            $signature,
            $endpoint_secret
        );
    } catch (\UnexpectedValueException $e) {
        throw new Exception("Invalid Stripe webhook payload");
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        throw new Exception("Invalid Stripe webhook signature");
    }
    
    if ($event->type === 'payment_intent.succeeded') {
        $payment_intent = $event->data->object;
        $payment_id = $payment_intent->metadata->payment_id ?? null;
        
        if ($payment_id) {
            // Verify payment with Stripe
            $verification = $processor->verifyPayment($payment_id, 'stripe', $payment_intent->id);
            
            if ($verification['success']) {
                $processor->updatePaymentStatus($payment_id, 'completed', $verification['gateway_data']);
                
                // Enroll student in course if it's a course purchase
                enrollStudentInCourse($payment_id);
            }
        }
    }
}

/**
 * Handle PayPal webhook
 */
function handlePayPalWebhook($data, $processor) {
    // Verify webhook signature (PayPal uses a different verification method)
    $webhook_id = PaymentConfig::$PAYPAL['webhook_secret'];
    
    // For PayPal, we need to verify the webhook with PayPal's API
    $verified = verifyPayPalWebhook($data, $webhook_id);
    
    if (!$verified) {
        throw new Exception("Invalid PayPal webhook");
    }
    
    $event_type = $data['event_type'];
    
    if ($event_type === 'CHECKOUT.ORDER.APPROVED') {
        $order_id = $data['resource']['id'];
        
        // Find payment by PayPal order ID
        $payment_id = findPaymentByPayPalOrderId($order_id);
        
        if ($payment_id) {
            // Verify payment with PayPal
            $verification = $processor->verifyPayment($payment_id, 'paypal', $order_id);
            
            if ($verification['success']) {
                $processor->updatePaymentStatus($payment_id, 'completed', $verification['gateway_data']);
                
                // Enroll student in course if it's a course purchase
                enrollStudentInCourse($payment_id);
            }
        }
    }
}

/**
 * Handle MTN MoMo webhook
 */
function handleMTNMomoWebhook($data, $processor) {
    // MTN MoMo webhook verification would be implemented here
    $status = $data['status'] ?? '';
    $external_id = $data['externalId'] ?? '';
    
    if ($status === 'SUCCESSFUL') {
        // Extract payment ID from external ID
        $payment_id = extractPaymentIdFromReference($external_id);
        
        if ($payment_id) {
            // Verify payment with MTN MoMo
            $verification = $processor->verifyPayment($payment_id, 'mtn_momo', $external_id);
            
            if ($verification['success']) {
                $processor->updatePaymentStatus($payment_id, 'completed', $verification['gateway_data']);
                
                // Enroll student in course if it's a course purchase
                enrollStudentInCourse($payment_id);
            }
        }
    }
}

/**
 * Extract payment ID from reference
 */
function extractPaymentIdFromReference($reference) {
    if (preg_match('/TAA_(\d+)_/', $reference, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Find payment by PayPal order ID
 */
function findPaymentByPayPalOrderId($order_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id FROM payments 
        WHERE gateway_data LIKE ? 
        AND status = 'pending'
        LIMIT 1
    ");
    $stmt->execute(['%"order_id":"' . $order_id . '"%']);
    
    return $stmt->fetchColumn();
}

/**
 * Verify PayPal webhook
 */
function verifyPayPalWebhook($data, $webhook_id) {
    $config = PaymentConfig::$PAYPAL;
    
    $base_url = $config['environment'] === 'production' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$base_url/v1/notifications/verify-webhook-signature",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'auth_algo' => $_SERVER['HTTP_PAYPAL_AUTH_ALGO'] ?? '',
            'cert_url' => $_SERVER['HTTP_PAYPAL_CERT_URL'] ?? '',
            'transmission_id' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'] ?? '',
            'transmission_sig' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'] ?? '',
            'transmission_time' => $_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'] ?? '',
            'webhook_id' => $webhook_id,
            'webhook_event' => $data
        ]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getPayPalToken(),
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    return $result['verification_status'] === 'SUCCESS';
}

/**
 * Get PayPal access token
 */
function getPayPalToken() {
    $config = PaymentConfig::$PAYPAL;
    
    $base_url = $config['environment'] === 'production' 
        ? 'https://api-m.paypal.com' 
        : 'https://api-m.sandbox.paypal.com';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $base_url . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret']),
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? '';
}

/**
 * Enroll student in course after successful payment
 */
function enrollStudentInCourse($payment_id) {
    global $pdo;
    
    try {
        // Get payment details
        $stmt = $pdo->prepare("
            SELECT p.*, o.product_id, o.buyer_id 
            FROM payments p 
            JOIN orders o ON p.order_id = o.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            return;
        }
        
        // Check if this is a course purchase
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$payment['product_id']]);
        $course = $stmt->fetch();
        
        if ($course) {
            // Check if student is already enrolled
            $stmt = $pdo->prepare("
                SELECT id FROM student_courses 
                WHERE student_id = ? AND course_id = ?
            ");
            $stmt->execute([$payment['buyer_id'], $payment['product_id']]);
            
            if (!$stmt->fetch()) {
                // Enroll student in course
                $stmt = $pdo->prepare("
                    INSERT INTO student_courses (student_id, course_id, enrolled_at, progress_percent) 
                    VALUES (?, ?, NOW(), 0)
                ");
                $stmt->execute([$payment['buyer_id'], $payment['product_id']]);
                
                // Send enrollment confirmation email
                sendEnrollmentConfirmation($payment['buyer_id'], $course['id']);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error enrolling student in course: " . $e->getMessage());
    }
}

/**
 * Send enrollment confirmation email
 */
function sendEnrollmentConfirmation($student_id, $course_id) {
    global $pdo;
    
    try {
        // Get student and course details
        $stmt = $pdo->prepare("
            SELECT u.email, u.full_name, c.title 
            FROM users u 
            JOIN courses c ON c.id = ? 
            WHERE u.id = ?
        ");
        $stmt->execute([$course_id, $student_id]);
        $data = $stmt->fetch();
        
        if ($data) {
            // Send email (implement your email sending logic here)
            $to = $data['email'];
            $subject = "Confirmation d'inscription - " . $data['title'];
            $message = "
                Bonjour {$data['full_name']},
                
                Votre inscription au cours '{$data['title']}' a été confirmée.
                
                Vous pouvez maintenant accéder au cours depuis votre espace étudiant.
                
                Cordialement,
                L'équipe TaaBia
            ";
            
            // mail($to, $subject, $message); // Uncomment when email is configured
        }
        
    } catch (Exception $e) {
        error_log("Error sending enrollment confirmation: " . $e->getMessage());
    }
}
?>
