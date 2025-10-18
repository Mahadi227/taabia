<?php
// Start output buffering to prevent any accidental output
ob_start();

// Load required files
require_once '../includes/db.php';
require_once '../includes/function.php';

// Handle language switching
$language = $_GET['lang'] ?? $_SESSION['language'] ?? 'en';
if (in_array($language, ['en', 'fr'])) {
    $_SESSION['language'] = $language;
}

// Load language file
$lang_file = "../lang/{$language}.php";
if (file_exists($lang_file)) {
    $translations = include $lang_file;
    function __($key)
    {
        global $translations;
        return $translations[$key] ?? $key;
    }
} else {
    function __($key)
    {
        return $key;
    }
}

// Get verification code from URL or form
$verification_code = $_GET['code'] ?? $_POST['verification_code'] ?? '';
$verification_result = null;
$certificate = null;
$verification_logged = false;

// Process verification if code is provided
if (!empty($verification_code)) {
    // Get certificate details
    $cert_query = "
        SELECT cc.*, 
               u.email as student_email,
               c.title as current_course_title,
               c.description as course_description,
               i.full_name as current_instructor_name,
               i.email as instructor_email,
               ct.template_name,
               ct.layout_config
        FROM course_certificates cc
        LEFT JOIN users u ON cc.student_id = u.id
        LEFT JOIN courses c ON cc.course_id = c.id
        LEFT JOIN users i ON c.instructor_id = i.id
        LEFT JOIN certificate_templates ct ON cc.certificate_template = ct.template_name
        WHERE cc.verification_code = ?
    ";

    $cert_stmt = $pdo->prepare($cert_query);
    $cert_stmt->execute([$verification_code]);
    $certificate = $cert_stmt->fetch();

    if ($certificate) {
        // Certificate found - log verification
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check for suspicious activity (multiple failed attempts from same IP)
        $suspicious_check = "
            SELECT COUNT(*) as failed_attempts
            FROM certificate_verifications 
            WHERE ip_address = ? 
            AND verification_status = 'failed' 
            AND verified_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ";
        $suspicious_stmt = $pdo->prepare($suspicious_check);
        $suspicious_stmt->execute([$ip_address]);
        $suspicious_count = $suspicious_stmt->fetch()['failed_attempts'];

        $verification_status = ($suspicious_count >= 5) ? 'suspicious' : 'success';

        // Log verification attempt
        $log_query = "
            INSERT INTO certificate_verifications 
            (certificate_id, verification_code, ip_address, user_agent, verification_status) 
            VALUES (?, ?, ?, ?, ?)
        ";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([
            $certificate['id'],
            $verification_code,
            $ip_address,
            $user_agent,
            $verification_status
        ]);

        $verification_logged = true;
        $verification_result = 'success';
    } else {
        // Certificate not found - log failed attempt
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Log failed verification attempt
        $log_query = "
            INSERT INTO certificate_verifications 
            (certificate_id, verification_code, ip_address, user_agent, verification_status) 
            VALUES (NULL, ?, ?, ?, 'failed')
        ";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$verification_code, $ip_address, $user_agent]);

        $verification_result = 'failed';
    }
}

// Get recent verifications for this certificate (if found)
$recent_verifications = [];
if ($certificate) {
    $recent_query = "
        SELECT verified_at, ip_address, verification_status
        FROM certificate_verifications 
        WHERE certificate_id = ? 
        ORDER BY verified_at DESC 
        LIMIT 5
    ";
    $recent_stmt = $pdo->prepare($recent_query);
    $recent_stmt->execute([$certificate['id']]);
    $recent_verifications = $recent_stmt->fetchAll();
}

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="<?= $language ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('verify_certificate') ?> - <?= __('certificate_verification') ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: rgb(8, 157, 120);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .verification-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: #004085;
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .content {
            padding: 2rem;
        }

        .verification-form {
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            background: #004085;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .result-section {
            margin-top: 2rem;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #28a745;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #dc3545;
        }

        .certificate-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .certificate-details h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: #333;
            font-size: 1rem;
        }

        .certificate-number {
            font-family: monospace;
            background: #e9ecef;
            padding: 0.5rem;
            border-radius: 4px;
            font-weight: 600;
        }

        .grade-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .grade-excellent {
            background: #d4edda;
            color: #155724;
        }

        .grade-good {
            background: #cce5ff;
            color: #004085;
        }

        .grade-satisfactory {
            background: #fff3cd;
            color: #856404;
        }

        .grade-pass {
            background: #f8d7da;
            color: #721c24;
        }

        .grade-fail {
            background: #f5c6cb;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-success {
            background: #28a745;
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .verification-history {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }

        .verification-history h4 {
            color: #333;
            margin-bottom: 1rem;
        }

        .verification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .verification-item:last-child {
            border-bottom: none;
        }

        .verification-date {
            color: #666;
            font-size: 0.9rem;
        }

        .verification-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-success {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-suspicious {
            background: #fff3cd;
            color: #856404;
        }

        .language-switcher {
            position: absolute;
            top: 1rem;
            right: 1rem;
            display: flex;
            gap: 0.5rem;
        }

        .lang-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .lang-btn:hover,
        .lang-btn.active {
            background: rgba(255, 255, 255, 0.3);
        }

        .loading {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0;
            }

            .header h1 {
                font-size: 2rem;
            }

            .content {
                padding: 1rem;
            }

            .actions {
                flex-direction: column;
            }

            .btn-secondary,
            .btn-success {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Language Switcher -->
        <div class="language-switcher">
            <button class="lang-btn <?= $language === 'en' ? 'active' : '' ?>" onclick="switchLanguage('en')">EN</button>
            <button class="lang-btn <?= $language === 'fr' ? 'active' : '' ?>" onclick="switchLanguage('fr')">FR</button>
        </div>

        <div class="verification-card">
            <div class="header">
                <h1><i class="fas fa-certificate"></i> <?= __('verify_certificate') ?></h1>
                <p><?= __('enter_verification_code_to_verify_certificate') ?></p>
            </div>

            <div class="content">
                <!-- Verification Form -->
                <div class="verification-form">
                    <form method="GET" id="verificationForm">
                        <div class="form-group">
                            <label for="verification_code"><?= __('verification_code') ?></label>
                            <input type="text"
                                id="verification_code"
                                name="code"
                                value="<?= htmlspecialchars($verification_code) ?>"
                                placeholder="<?= __('enter_verification_code') ?>"
                                required>
                        </div>
                        <button type="submit" class="btn">
                            <i class="fas fa-search"></i> <?= __('verify_certificate') ?>
                        </button>
                    </form>
                </div>

                <!-- Loading State -->
                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <p><?= __('verifying_certificate') ?>...</p>
                </div>

                <!-- Results -->
                <?php if ($verification_result === 'success' && $certificate): ?>
                    <div class="result-section">
                        <div class="success-message">
                            <i class="fas fa-check-circle"></i>
                            <strong><?= __('certificate_verified_successfully') ?>!</strong>
                            <?= __('this_certificate_is_valid_and_authentic') ?>
                        </div>

                        <div class="certificate-details">
                            <h3><i class="fas fa-certificate"></i> <?= __('certificate_details') ?></h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label"><?= __('certificate_number') ?></div>
                                    <div class="detail-value certificate-number"><?= htmlspecialchars($certificate['certificate_number']) ?></div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label"><?= __('student_name') ?></div>
                                    <div class="detail-value"><?= htmlspecialchars($certificate['student_name']) ?></div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label"><?= __('course_title') ?></div>
                                    <div class="detail-value"><?= htmlspecialchars($certificate['course_title']) ?></div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label"><?= __('instructor_name') ?></div>
                                    <div class="detail-value"><?= htmlspecialchars($certificate['instructor_name']) ?></div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label"><?= __('completion_date') ?></div>
                                    <div class="detail-value"><?= date('F j, Y', strtotime($certificate['completion_date'])) ?></div>
                                </div>

                                <div class="detail-item">
                                    <div class="detail-label"><?= __('issue_date') ?></div>
                                    <div class="detail-value"><?= date('F j, Y', strtotime($certificate['issue_date'])) ?></div>
                                </div>

                                <?php if ($certificate['final_grade']): ?>
                                    <div class="detail-item">
                                        <div class="detail-label"><?= __('final_grade') ?></div>
                                        <div class="detail-value">
                                            <?php
                                            $grade = $certificate['final_grade'];
                                            $grade_class = '';
                                            if ($grade >= 90) $grade_class = 'grade-excellent';
                                            elseif ($grade >= 80) $grade_class = 'grade-good';
                                            elseif ($grade >= 70) $grade_class = 'grade-satisfactory';
                                            elseif ($grade >= 60) $grade_class = 'grade-pass';
                                            else $grade_class = 'grade-fail';
                                            ?>
                                            <span class="grade-badge <?= $grade_class ?>">
                                                <?= round($grade, 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="detail-item">
                                    <div class="detail-label"><?= __('verification_code') ?></div>
                                    <div class="detail-value certificate-number"><?= htmlspecialchars($certificate['verification_code']) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="actions">
                            <a href="../instructor/generate_certificate.php?id=<?= $certificate['id'] ?>"
                                class="btn-success" target="_blank">
                                <i class="fas fa-eye"></i> <?= __('view_certificate') ?>
                            </a>

                            <a href="../instructor/generate_certificate.php?id=<?= $certificate['id'] ?>&download=1"
                                class="btn-secondary" target="_blank">
                                <i class="fas fa-download"></i> <?= __('download_certificate') ?>
                            </a>

                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                class="btn-secondary" target="_blank">
                                <i class="fab fa-linkedin"></i> <?= __('share_on_linkedin') ?>
                            </a>

                            <a href="https://twitter.com/intent/tweet?text=<?= urlencode(__('i_earned_a_certificate_from') . ' ' . htmlspecialchars($certificate['course_title'])) ?>&url=<?= urlencode($_SERVER['REQUEST_URI']) ?>"
                                class="btn-secondary" target="_blank">
                                <i class="fab fa-twitter"></i> <?= __('share_on_twitter') ?>
                            </a>
                        </div>

                        <!-- Recent Verifications -->
                        <?php if (!empty($recent_verifications)): ?>
                            <div class="verification-history">
                                <h4><i class="fas fa-history"></i> <?= __('recent_verifications') ?></h4>
                                <?php foreach ($recent_verifications as $verification): ?>
                                    <div class="verification-item">
                                        <div>
                                            <div class="verification-date">
                                                <?= date('M j, Y g:i A', strtotime($verification['verified_at'])) ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #999;">
                                                IP: <?= htmlspecialchars($verification['ip_address']) ?>
                                            </div>
                                        </div>
                                        <span class="verification-status status-<?= $verification['verification_status'] ?>">
                                            <?= ucfirst($verification['verification_status']) ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($verification_result === 'failed'): ?>
                    <div class="result-section">
                        <div class="error-message">
                            <i class="fas fa-times-circle"></i>
                            <strong><?= __('certificate_not_found') ?></strong>
                            <?= __('the_verification_code_you_entered_is_invalid_or_does_not_exist') ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Language switching
        function switchLanguage(lang) {
            const url = new URL(window.location);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }

        // Form submission with loading state
        document.getElementById('verificationForm').addEventListener('submit', function() {
            document.getElementById('loading').style.display = 'block';
            document.querySelector('.verification-form').style.display = 'none';
        });

        // Auto-focus on verification code input
        document.getElementById('verification_code').focus();

        // Copy verification code to clipboard
        function copyVerificationCode() {
            const code = '<?= htmlspecialchars($certificate['verification_code'] ?? '') ?>';
            if (code) {
                navigator.clipboard.writeText(code).then(function() {
                    alert('<?= __('verification_code_copied') ?>');
                });
            }
        }

        // Add copy button if certificate is verified
        <?php if ($certificate): ?>
            const verificationCodeElement = document.querySelector('.certificate-number');
            if (verificationCodeElement) {
                verificationCodeElement.style.cursor = 'pointer';
                verificationCodeElement.title = '<?= __('click_to_copy') ?>';
                verificationCodeElement.addEventListener('click', copyVerificationCode);
            }
        <?php endif; ?>
    </script>
</body>

</html>