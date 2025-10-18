<?php

/**
 * View Certificate - Display certificate online
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';

// Get certificate ID
$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$certificate_id) {
    flash_message(__('invalid_certificate'), 'error');
    redirect('certificates.php');
    exit;
}

try {
    // Fetch certificate details
    $stmt = $pdo->prepare("
        SELECT 
            cc.*,
            c.title as course_title,
            c.description as course_description,
            u.full_name as student_name,
            u.email as student_email,
            i.full_name as instructor_name,
            i.email as instructor_email
        FROM course_certificates cc
        INNER JOIN courses c ON cc.course_id = c.id
        INNER JOIN users u ON cc.student_id = u.id
        INNER JOIN users i ON c.instructor_id = i.id
        WHERE cc.id = ?
    ");
    $stmt->execute([$certificate_id]);
    $certificate = $stmt->fetch();

    if (!$certificate) {
        flash_message(__('certificate_not_found'), 'error');
        redirect('certificates.php');
        exit;
    }

    // Check if user is authorized (instructor, student, or admin)
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['role'];

    $is_authorized = false;

    if ($user_role === 'admin') {
        $is_authorized = true;
    } elseif ($user_role === 'instructor') {
        // Check if it's instructor's course
        $check_stmt = $pdo->prepare("
            SELECT id FROM courses 
            WHERE id = ? AND instructor_id = ?
        ");
        $check_stmt->execute([$certificate['course_id'], $user_id]);
        $is_authorized = $check_stmt->fetch() !== false;
    } elseif ($user_role === 'student') {
        // Check if it's student's certificate
        $is_authorized = ($certificate['student_id'] == $user_id);
    }

    if (!$is_authorized) {
        flash_message(__('access_denied'), 'error');
        redirect('../auth/unauthorized.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Certificate view error: " . $e->getMessage());
    flash_message(__('database_error'), 'error');
    redirect('certificates.php');
    exit;
}

$page_title = __('view_certificate');
?>
<!DOCTYPE html>
<html lang="<?php echo $_SESSION['lang'] ?? 'fr'; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo htmlspecialchars($certificate['certificate_number']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/view-certificate.css">
</head>

<body>
    <div class="certificate-viewer">
        <!-- Header Actions -->
        <div class="viewer-header no-print">
            <div class="header-left">
                <a href="certificates.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i>
                    <?php echo __('back_to_certificates'); ?>
                </a>
            </div>

            <div class="header-actions">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i>
                    <?php echo __('print'); ?>
                </button>

                <a href="generate_certificate.php?id=<?php echo $certificate_id; ?>" class="btn btn-success"
                    target="_blank">
                    <i class="fas fa-download"></i>
                    <?php echo __('download_pdf'); ?>
                </a>

                <button onclick="shareCertificate()" class="btn btn-info">
                    <i class="fas fa-share-alt"></i>
                    <?php echo __('share'); ?>
                </button>

                <a href="../public/verify_certificate.php?code=<?php echo $certificate['verification_code']; ?>"
                    class="btn btn-secondary" target="_blank">
                    <i class="fas fa-check-circle"></i>
                    <?php echo __('verify'); ?>
                </a>
            </div>
        </div>

        <!-- Certificate Display -->
        <div class="certificate-container" id="certificate">
            <div class="certificate-border">
                <div class="certificate-inner">
                    <!-- Decorative Elements -->
                    <div class="certificate-decoration top-left"></div>
                    <div class="certificate-decoration top-right"></div>
                    <div class="certificate-decoration bottom-left"></div>
                    <div class="certificate-decoration bottom-right"></div>

                    <!-- Header -->
                    <div class="certificate-header">
                        <div class="certificate-logo"></div>
                        <h1 class="platform-name">TaaBia Skills</h1>
                        <p class="platform-tagline"><?php echo __('online_learning_platform'); ?></p>
                    </div>

                    <!-- Certificate Title -->
                    <div class="certificate-title">
                        <h2><?php echo __('certificate_of_completion'); ?></h2>
                        <div class="title-underline"></div>
                    </div>

                    <!-- Certificate Body -->
                    <div class="certificate-body">
                        <p class="certify-text"><?php echo __('this_is_to_certify_that'); ?></p>

                        <div class="student-name">
                            <?php echo htmlspecialchars($certificate['student_name']); ?>
                        </div>
                        <div class="name-underline"></div>

                        <p class="completion-text">
                            <?php echo __('has_successfully_completed_the_course'); ?>
                        </p>

                        <div class="course-title">
                            <?php echo htmlspecialchars($certificate['course_title']); ?>
                        </div>

                        <?php if ($certificate['final_grade']): ?>
                        <div class="grade-box">
                            <span class="grade-label"><?php echo __('final_grade'); ?>:</span>
                            <span class="grade-value"><?php echo round($certificate['final_grade'], 1); ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Certificate Info -->
                    <div class="certificate-info-grid">
                        <div class="info-item">
                            <div class="info-label"><?php echo __('certificate_number'); ?></div>
                            <div class="info-value"><?php echo htmlspecialchars($certificate['certificate_number']); ?>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-label"><?php echo __('completion_date'); ?></div>
                            <div class="info-value">
                                <?php echo date('d F Y', strtotime($certificate['completion_date'])); ?></div>
                        </div>

                        <div class="info-item">
                            <div class="info-label"><?php echo __('issue_date'); ?></div>
                            <div class="info-value"><?php echo date('d F Y', strtotime($certificate['issue_date'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Signatures -->
                    <div class="signatures-section">
                        <div class="signature-block">
                            <div class="signature-line"></div>
                            <div class="signature-name"><?php echo htmlspecialchars($certificate['instructor_name']); ?>
                            </div>
                            <div class="signature-title"><?php echo __('course_instructor'); ?></div>
                        </div>

                        <div class="signature-seal">
                            <div class="seal-circle">
                                <div class="seal-main">
                                    <div class="seal-inner">
                                        <div class="seal-text">BEST AWARD</div>
                                        <div class="seal-stars">★★★★★</div>
                                    </div>
                                </div>
                                <div class="seal-ribbons">
                                    <div class="ribbon"></div>
                                    <div class="ribbon"></div>
                                </div>
                            </div>
                            <div class="seal-text"><?php echo __('verified'); ?></div>
                        </div>

                        <div class="signature-block">
                            <div class="signature-line"></div>
                            <div class="signature-name">TaaBia Skills</div>
                            <div class="signature-title"><?php echo __('platform_director'); ?></div>
                        </div>
                    </div>

                    <!-- Verification Code -->
                    <div class="verification-section">
                        <p class="verification-text">
                            <i class="fas fa-shield-alt"></i>
                            <?php echo __('verification_code'); ?>:
                            <code
                                class="verification-code"><?php echo htmlspecialchars($certificate['verification_code']); ?></code>
                        </p>
                        <p class="verification-url">
                            <?php echo __('verify_at'); ?>:
                            <span class="url">
                                <?php
                                $verify_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/verify_certificate.php?code=' . $certificate['verification_code'];
                                echo htmlspecialchars($verify_url);
                                ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Certificate Details (No Print) -->
        <div class="certificate-details no-print">
            <div class="details-card">
                <h3><i class="fas fa-info-circle"></i> <?php echo __('certificate_details'); ?></h3>

                <div class="details-grid">
                    <div class="detail-item">
                        <span class="detail-label"><?php echo __('student'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($certificate['student_name']); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><?php echo __('email'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($certificate['student_email']); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><?php echo __('course'); ?>:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($certificate['course_title']); ?></span>
                    </div>

                    <div class="detail-item">
                        <span class="detail-label"><?php echo __('instructor'); ?>:</span>
                        <span
                            class="detail-value"><?php echo htmlspecialchars($certificate['instructor_name']); ?></span>
                    </div>

                    <?php if ($certificate['final_grade']): ?>
                    <div class="detail-item">
                        <span class="detail-label"><?php echo __('final_grade'); ?>:</span>
                        <span
                            class="detail-value grade-badge"><?php echo round($certificate['final_grade'], 1); ?>%</span>
                    </div>
                    <?php endif; ?>

                    <div class="detail-item">
                        <span class="detail-label"><?php echo __('status'); ?>:</span>
                        <span class="detail-value">
                            <?php if ($certificate['is_verified']): ?>
                            <span class="badge badge-success">
                                <i class="fas fa-check-circle"></i> <?php echo __('verified'); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge badge-danger">
                                <i class="fas fa-times-circle"></i> <?php echo __('revoked'); ?>
                            </span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Share Options -->
            <div class="share-card">
                <h3><i class="fas fa-share-nodes"></i> <?php echo __('share_certificate'); ?></h3>

                <div class="share-buttons">
                    <button onclick="shareOn('linkedin')" class="share-btn linkedin">
                        <i class="fab fa-linkedin"></i>
                        LinkedIn
                    </button>

                    <button onclick="shareOn('facebook')" class="share-btn facebook">
                        <i class="fab fa-facebook"></i>
                        Facebook
                    </button>

                    <button onclick="shareOn('twitter')" class="share-btn twitter">
                        <i class="fab fa-twitter"></i>
                        Twitter
                    </button>

                    <button onclick="copyLink()" class="share-btn copy">
                        <i class="fas fa-link"></i>
                        <?php echo __('copy_link'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Share certificate
    function shareCertificate() {
        if (navigator.share) {
            navigator.share({
                title: '<?php echo addslashes($certificate['certificate_number']); ?>',
                text: '<?php echo addslashes(__('i_completed')); ?> <?php echo addslashes($certificate['course_title']); ?>',
                url: window.location.href
            }).catch(console.error);
        } else {
            alert('<?php echo __('share_not_supported'); ?>');
        }
    }

    // Share on specific platform
    function shareOn(platform) {
        const url = encodeURIComponent(window.location.href);
        const text = encodeURIComponent(
            '<?php echo addslashes(__('i_completed')); ?> <?php echo addslashes($certificate['course_title']); ?> - <?php echo addslashes($certificate['certificate_number']); ?>'
            );

        let shareUrl = '';

        switch (platform) {
            case 'linkedin':
                shareUrl = `https://www.linkedin.com/sharing/share-offsite/?url=${url}`;
                break;
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                break;
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?text=${text}&url=${url}`;
                break;
        }

        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');

            // Log share action
            fetch('log_certificate_share.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `certificate_id=<?php echo $certificate_id; ?>&platform=${platform}`
            });
        }
    }

    // Copy link
    function copyLink() {
        const url = window.location.href;

        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(() => {
                alert('<?php echo __('link_copied'); ?>');
            }).catch(err => {
                fallbackCopyTextToClipboard(url);
            });
        } else {
            fallbackCopyTextToClipboard(url);
        }
    }

    function fallbackCopyTextToClipboard(text) {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.top = 0;
        textArea.style.left = 0;
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            document.execCommand('copy');
            alert('<?php echo __('link_copied'); ?>');
        } catch (err) {
            alert('<?php echo __('copy_failed'); ?>');
        }

        document.body.removeChild(textArea);
    }

    // Print handling
    window.onbeforeprint = function() {
        document.title = '<?php echo addslashes($certificate['certificate_number']); ?>';
    };

    window.onafterprint = function() {
        document.title =
            '<?php echo addslashes($page_title); ?> - <?php echo addslashes($certificate['certificate_number']); ?>';
    };
    </script>
</body>

</html>