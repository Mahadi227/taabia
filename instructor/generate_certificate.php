<?php

/**
 * Certificate PDF Generator
 * Generates a professional certificate PDF for completed courses
 */

require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/language_handler.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die('Access denied');
}

$certificate_id = $_GET['id'] ?? null;

if (!$certificate_id) {
    die('Certificate ID required');
}

// Fetch certificate details
$cert_query = "
    SELECT cc.*, c.title as course_title, c.description as course_description,
           u.full_name as student_name, u.email as student_email
    FROM course_certificates cc
    INNER JOIN courses c ON cc.course_id = c.id
    INNER JOIN users u ON cc.student_id = u.id
    WHERE cc.id = ?
";
$cert_stmt = $pdo->prepare($cert_query);
$cert_stmt->execute([$certificate_id]);
$cert = $cert_stmt->fetch();

if (!$cert) {
    die('Certificate not found');
}

// Set headers for PDF download
header('Content-Type: text/html; charset=utf-8');

// Generate HTML certificate (can be converted to PDF using a library like TCPDF, mPDF, or FPDF)
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate - <?php echo htmlspecialchars($cert['student_name']); ?></title>
    <!-- FontAwesome CSS pour meilleure compatibilité print -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Georgia', serif;
            background: linear-gradient(135deg, rgb(9, 128, 179) 0%, rgb(9, 128, 179) 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 1rem;
        }

        .certificate {
            width: 297mm;
            height: 210mm;
            max-width: 297mm;
            max-height: 210mm;
            background: white;
            border: 15px solid rgba(8, 90, 146, 0.87);
            border-image: linear-gradient(135deg, rgb(3, 109, 132), rgb(3, 109, 132)) 1;
            padding: 1.5cm;
            position: relative;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .certificate::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 15px;
            right: 15px;
            bottom: 15px;
            border: 2px solid rgb(3, 162, 112);
        }

        .header {
            text-align: center;
            margin-bottom: 1rem;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 0.5rem;
            background: linear-gradient(135deg, rgba(4, 133, 158, 0.87), rgba(4, 133, 158, 0.87));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
        }

        .title {
            font-size: 2rem;
            color: rgba(4, 133, 153, 0.87);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
            margin-bottom: 0.3rem;
        }

        .subtitle {
            font-size: 1rem;
            color: rgb(23, 107, 146);
            font-style: italic;
        }

        .content {
            text-align: center;
            margin: 1.5rem 0;
        }

        .awarded-to {
            font-size: 1rem;
            color: rgb(23, 107, 146);
            margin-bottom: 0.5rem;
        }

        .student-name {
            font-size: 2.2rem;
            color: rgb(0, 0, 0);
            font-weight: 700;
            margin: 0.5rem 0 1rem;
            text-transform: capitalize;
            border-bottom: 3px solid rgb(4, 122, 152);
            display: inline-block;
            padding-bottom: 0.3rem;
        }

        .achievement {
            font-size: 0.95rem;
            color: rgb(4, 84, 108);
            line-height: 1.6;
            max-width: 700px;
            margin: 0 auto 1rem;
        }

        .course-name {
            font-size: 1.5rem;
            color: rgb(219, 17, 17);
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .completion-info {
            display: flex;
            justify-content: space-around;
            margin: 1.5rem 0 1rem;
            padding: 1.2rem;
            background: linear-gradient(135deg, rgba(4, 104, 137, 0.81), rgba(4, 104, 137, 0.66));
            border-radius: 0.8rem;
        }

        .info-block {
            text-align: center;
        }

        .info-label {
            font-size: 0.75rem;
            color: rgb(255, 255, 255);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 0.3rem;
        }

        .info-value {
            font-size: 1.1rem;
            color: rgb(255, 255, 255);
            font-weight: 600;
        }

        .footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 1.5rem;
            width: 100%;
            text-align: center;
        }

        .signature-block {
            text-align: center;
        }

        .signature-line {
            width: 180px;
            border-top: 2px solid rgb(193, 79, 13);
            margin: 0 auto 0.3rem;
        }

        .signature-name {
            font-weight: 600;
            color: rgb(0, 0, 0);
            font-size: 0.95rem;
        }

        .signature-title {
            font-size: 0.8rem;
            color: rgb(102, 102, 102);
            font-style: italic;
        }

        .certificate-seal {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, rgba(4, 133, 158, 0.87), rgba(4, 133, 158, 0.87));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.8rem;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .verification-code {
            text-align: center;
            margin-top: 1rem;
            font-size: 0.75rem;
            color: #6c757d;
        }

        .verification-code code {
            background: #f8f9fa;
            padding: 0.3rem 0.8rem;
            border-radius: 0.3rem;
            font-family: 'Courier New', monospace;
            color: rgb(237, 108, 140);
            font-weight: 600;
            font-size: 0.75rem;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }

            html,
            body {
                width: 297mm;
                height: 210mm;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: white !important;
            }

            .certificate {
                width: 297mm !important;
                height: 210mm !important;
                max-width: 297mm !important;
                max-height: 210mm !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
                margin: 0 !important;
                padding: 1.5cm !important;
                border: 15px solid rgba(8, 90, 146, 0.87) !important;
            }

            .certificate::before {
                top: 10px !important;
                left: 10px !important;
                right: 10px !important;
                bottom: 10px !important;
            }

            /* Force l'affichage des couleurs et dégradés */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            /* S'assurer que les icônes FontAwesome s'affichent */
            .logo i,
            .certificate-seal i {
                display: inline-block !important;
                font-family: "Font Awesome 6 Free" !important;
                font-weight: 900 !important;
                -webkit-font-smoothing: antialiased !important;
                font-style: normal !important;
                font-variant: normal !important;
                text-rendering: auto !important;
                line-height: 1 !important;
            }

            .logo i::before,
            .certificate-seal i::before {
                display: inline-block !important;
            }
        }
    </style>
</head>

<body>
    <div class="certificate">
        <div class="header">
            <div class="logo">
                <i class="fas fa-graduation-cap"></i>
            </div>
            <h1 class="title"><?php echo __('certificate_of_completion'); ?></h1>
            <p class="subtitle"><?php echo __('this_is_to_certify_that'); ?></p>
        </div>

        <div class="content">
            <p class="awarded-to"><?php echo __('is_proudly_presented_to'); ?></p>
            <h2 class="student-name"><?php echo htmlspecialchars($cert['student_name']); ?></h2>

            <p class="achievement">
                <?php echo __('for_successfully_completing_the_course'); ?>
            </p>

            <h3 class="course-name"><?php echo htmlspecialchars($cert['course_title']); ?></h3>

            <div class="completion-info">
                <div class="info-block">
                    <div class="info-label"><?php echo __('certificate_number'); ?></div>
                    <div class="info-value"><?php echo htmlspecialchars($cert['certificate_number']); ?></div>
                </div>

                <div class="info-block">
                    <div class="info-label"><?php echo __('completion_date'); ?></div>
                    <div class="info-value"><?php echo date('d F Y', strtotime($cert['completion_date'])); ?></div>
                </div>

                <?php if ($cert['final_grade']): ?>
                    <div class="info-block">
                        <div class="info-label"><?php echo __('final_grade'); ?></div>
                        <div class="info-value"><?php echo round($cert['final_grade'], 1); ?>%</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name"><?php echo htmlspecialchars($cert['instructor_name']); ?></div>
                <div class="signature-title"><?php echo __('course_instructor'); ?></div>
            </div>

            <div class="certificate-seal">
                <i class="fas fa-stamp"></i>
            </div>

            <div class="signature-block">
                <div class="signature-line"></div>
                <div class="signature-name">FAYCAL SAM</div>
                <div class="signature-title"><?php echo __('Director of TaaBia'); ?></div>
            </div>
        </div>

        <div class="verification-code">
            <?php echo __('verify_at'); ?>: <code><?php echo htmlspecialchars($cert['verification_code']); ?></code>
        </div>
    </div>

    <script>
        // Auto-print on load - délai augmenté pour charger les icônes
        window.onload = function() {
            // Attendre que FontAwesome soit complètement chargé
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(function() {
                    setTimeout(function() {
                        window.print();
                    }, 2000);
                });
            } else {
                // Fallback si l'API fonts n'est pas disponible
                setTimeout(function() {
                    window.print();
                }, 2500);
            }
        };
    </script>
</body>

</html>