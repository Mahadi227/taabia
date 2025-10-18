<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/unauthorized.php');
    exit();
}

$certificate_id = $_GET['id'] ?? null;

if (!$certificate_id) {
    header('Location: certificates.php');
    exit();
}

// Get certificate details
$cert_query = "
    SELECT cc.*, 
           u.email as student_email,
           u.full_name as current_student_name,
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
    WHERE cc.id = ?
";

$cert_stmt = $pdo->prepare($cert_query);
$cert_stmt->execute([$certificate_id]);
$cert = $cert_stmt->fetch();

if (!$cert) {
    header('Location: certificates.php');
    exit();
}

// Get verification history
$verification_query = "
    SELECT * FROM certificate_verifications 
    WHERE certificate_id = ? 
    ORDER BY verified_at DESC
    LIMIT 10
";
$verification_stmt = $pdo->prepare($verification_query);
$verification_stmt->execute([$certificate_id]);
$verifications = $verification_stmt->fetchAll();

// Get sharing history
$sharing_query = "
    SELECT * FROM certificate_shares 
    WHERE certificate_id = ? 
    ORDER BY shared_at DESC
    LIMIT 10
";
$sharing_stmt = $pdo->prepare($sharing_query);
$sharing_stmt->execute([$certificate_id]);
$shares = $sharing_stmt->fetchAll();

ob_end_clean();
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'en' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('certificate_details') ?> - <?= htmlspecialchars($cert['certificate_number']) ?></title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .details-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .details-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .certificate-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .info-section {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .info-section h3 {
            margin: 0 0 1rem 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .info-item {
            margin-bottom: 1rem;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #333;
            font-size: 1.1rem;
        }

        .verification-code {
            font-family: monospace;
            background: #e3f2fd;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 1rem;
            color: #1976d2;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .certificate-number {
            font-family: monospace;
            background: #f8f9fa;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 1.1rem;
            display: inline-block;
            margin-top: 0.5rem;
        }

        .grade-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            color: white;
        }

        .grade-excellent {
            background: #38a169;
        }

        .grade-good {
            background: #3182ce;
        }

        .grade-pass {
            background: #ed8936;
        }

        .actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5a67d8;
        }

        .btn-success {
            background: #38a169;
            color: white;
        }

        .btn-success:hover {
            background: #2f855a;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .history-table th,
        .history-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        .history-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .status-success {
            color: #38a169;
        }

        .status-failed {
            color: #e53e3e;
        }

        .status-suspicious {
            color: #ed8936;
        }

        .platform-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .platform-linkedin {
            background: #0077b5;
            color: white;
        }

        .platform-facebook {
            background: #4267b2;
            color: white;
        }

        .platform-twitter {
            background: #1da1f2;
            color: white;
        }

        .platform-email {
            background: #6c757d;
            color: white;
        }

        .platform-download {
            background: #28a745;
            color: white;
        }

        .platform-other {
            background: #6c757d;
            color: white;
        }

        .metadata {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .back-link:hover {
            color: #5a67d8;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <div class="content-header">
                <a href="certificates.php" class="back-link">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('back_to_certificates') ?>
                </a>
                <h1><?= __('certificate_details') ?></h1>
                <p><?= htmlspecialchars($cert['certificate_number']) ?></p>
            </div>

            <div class="details-container">
                <!-- Certificate Information -->
                <div class="info-section">
                    <h3><i class="fas fa-certificate"></i> <?= __('certificate_information') ?></h3>
                    <div class="certificate-info">
                        <div>
                            <div class="info-item">
                                <div class="info-label"><?= __('certificate_number') ?></div>
                                <div class="certificate-number"><?= htmlspecialchars($cert['certificate_number']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><?= __('student_name') ?></div>
                                <div class="info-value"><?= htmlspecialchars($cert['student_name']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><?= __('student_email') ?></div>
                                <div class="info-value"><?= htmlspecialchars($cert['student_email']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><?= __('course_title') ?></div>
                                <div class="info-value"><?= htmlspecialchars($cert['current_course_title']) ?></div>
                            </div>
                        </div>

                        <div>
                            <div class="info-item">
                                <div class="info-label"><?= __('instructor_name') ?></div>
                                <div class="info-value"><?= htmlspecialchars($cert['current_instructor_name']) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><?= __('completion_date') ?></div>
                                <div class="info-value"><?= date('F j, Y', strtotime($cert['completion_date'])) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><?= __('issue_date') ?></div>
                                <div class="info-value"><?= date('F j, Y g:i A', strtotime($cert['issue_date'])) ?></div>
                            </div>

                            <div class="info-item">
                                <div class="info-label"><?= __('final_grade') ?></div>
                                <div class="info-value">
                                    <?php if ($cert['final_grade']): ?>
                                        <?php
                                        $grade = $cert['final_grade'];
                                        $badge_class = 'grade-pass';
                                        if ($grade >= 90) $badge_class = 'grade-excellent';
                                        elseif ($grade >= 80) $badge_class = 'grade-good';
                                        ?>
                                        <span class="grade-badge <?= $badge_class ?>">
                                            <?= round($grade, 1) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= __('not_available') ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-label"><?= __('verification_code') ?></div>
                        <div class="verification-code"><?= htmlspecialchars($cert['verification_code']) ?></div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="info-section">
                    <h3><i class="fas fa-tools"></i> <?= __('actions') ?></h3>
                    <div class="actions">
                        <a href="../instructor/generate_certificate.php?id=<?= $cert['id'] ?>"
                            class="btn btn-primary" target="_blank">
                            <i class="fas fa-eye"></i> <?= __('view_certificate') ?>
                        </a>

                        <a href="../instructor/generate_certificate.php?id=<?= $cert['id'] ?>&download=1"
                            class="btn btn-success" target="_blank">
                            <i class="fas fa-download"></i> <?= __('download_certificate') ?>
                        </a>

                        <a href="../public/verify_certificate.php?code=<?= urlencode($cert['verification_code']) ?>"
                            class="btn btn-secondary" target="_blank">
                            <i class="fas fa-check-circle"></i> <?= __('verify_certificate') ?>
                        </a>
                    </div>
                </div>

                <!-- Template Information -->
                <?php if ($cert['template_name']): ?>
                    <div class="info-section">
                        <h3><i class="fas fa-palette"></i> <?= __('template_information') ?></h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label"><?= __('template_name') ?></div>
                                <div class="info-value"><?= htmlspecialchars($cert['template_name']) ?></div>
                            </div>

                            <?php if ($cert['layout_config']): ?>
                                <div class="info-item">
                                    <div class="info-label"><?= __('layout_configuration') ?></div>
                                    <div class="metadata"><?= htmlspecialchars($cert['layout_config']) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Verification History -->
                <?php if (!empty($verifications)): ?>
                    <div class="info-section">
                        <h3><i class="fas fa-search"></i> <?= __('verification_history') ?></h3>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th><?= __('date_time') ?></th>
                                    <th><?= __('status') ?></th>
                                    <th><?= __('ip_address') ?></th>
                                    <th><?= __('user_agent') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($verifications as $verification): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime($verification['verified_at'])) ?></td>
                                        <td>
                                            <span class="status-<?= $verification['verification_status'] ?>">
                                                <?= ucfirst($verification['verification_status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($verification['ip_address']) ?></td>
                                        <td>
                                            <?php
                                            $user_agent = $verification['user_agent'];
                                            echo htmlspecialchars(substr($user_agent, 0, 50)) . (strlen($user_agent) > 50 ? '...' : '');
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Sharing History -->
                <?php if (!empty($shares)): ?>
                    <div class="info-section">
                        <h3><i class="fas fa-share-alt"></i> <?= __('sharing_history') ?></h3>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th><?= __('date_time') ?></th>
                                    <th><?= __('platform') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($shares as $share): ?>
                                    <tr>
                                        <td><?= date('M j, Y g:i A', strtotime($share['shared_at'])) ?></td>
                                        <td>
                                            <span class="platform-badge platform-<?= $share['platform'] ?>">
                                                <?= ucfirst($share['platform']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Metadata -->
                <?php if ($cert['metadata']): ?>
                    <div class="info-section">
                        <h3><i class="fas fa-info-circle"></i> <?= __('metadata') ?></h3>
                        <div class="metadata"><?= htmlspecialchars($cert['metadata']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>

</html>
