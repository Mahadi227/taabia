<?php
// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

// Check if transaction ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = __('transaction_id_required');
    header('Location: transactions.php');
    exit;
}

$transaction_id = (int)$_GET['id'];

// Get current user information
$current_user = null;
try {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([current_user_id()]);
    $current_user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching current user: " . $e->getMessage());
}

// Fetch transaction details
try {
    $stmt = $pdo->prepare("SELECT t.*, u.full_name, u.email 
                          FROM transactions t 
                          LEFT JOIN users u ON t.user_id = u.id 
                          WHERE t.id = ?");
    $stmt->execute([$transaction_id]);
    $transaction = $stmt->fetch();

    if (!$transaction) {
        $_SESSION['error'] = __('transaction_not_found');
        header('Location: transactions.php');
        exit;
    }
} catch (PDOException $e) {
    error_log("Database error in admin/edit_transaction.php: " . $e->getMessage());
    $_SESSION['error'] = __('database_error');
    header('Location: transactions.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = sanitize($_POST['amount'] ?? '');
    $currency = sanitize($_POST['currency'] ?? '');
    $payment_method = sanitize($_POST['payment_method'] ?? '');
    $payment_status = sanitize($_POST['payment_status'] ?? '');
    $type = sanitize($_POST['type'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');

    // Validation
    $errors = [];

    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = __('invalid_amount');
    }

    if (empty($currency)) {
        $errors[] = __('currency_required');
    }

    if (empty($payment_method)) {
        $errors[] = __('payment_method_required');
    }

    if (empty($payment_status)) {
        $errors[] = __('payment_status_required');
    }

    if (empty($type)) {
        $errors[] = __('transaction_type_required');
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE transactions 
                                  SET amount = ?, currency = ?, payment_method = ?, 
                                      payment_status = ?, type = ?, notes = ?, updated_at = NOW() 
                                  WHERE id = ?");
            $stmt->execute([$amount, $currency, $payment_method, $payment_status, $type, $notes, $transaction_id]);

            $_SESSION['success'] = __('transaction_updated_successfully');
            header('Location: transactions.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error updating transaction: " . $e->getMessage());
            $_SESSION['error'] = __('error_updating_transaction');
        }
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_transaction') ?> #<?= $transaction_id ?> | <?= __('admin_panel') ?> | TaaBia</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary-color: #009688;
            --primary-light: #e0f2f1;
            --primary-dark: #00695c;
            --secondary-color: #607d8b;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --error-color: #f44336;
            --info-color: #2196f3;
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #e9ecef;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --text-light: #adb5bd;
            --border-color: #dee2e6;
            --border-radius: 8px;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            margin-left: 280px;
        }

        .header {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title h1 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-title p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: var(--bg-secondary);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }

        .content {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 2rem;
        }

        .form-section {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .form-section h3 {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .form-control:invalid {
            border-color: var(--error-color);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #546e7a;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline {
            background: transparent;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .info-section {
            background: var(--bg-primary);
            border-radius: var(--border-radius);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            height: fit-content;
        }

        .info-section h3 {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--text-secondary);
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 500;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-success {
            background: #e8f5e8;
            color: var(--success-color);
        }

        .badge-warning {
            background: #fff3e0;
            color: var(--warning-color);
        }

        .badge-danger {
            background: #ffebee;
            color: var(--error-color);
        }

        .badge-info {
            background: #e3f2fd;
            color: var(--info-color);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            border-left: 4px solid;
        }

        .alert-success {
            background: #e8f5e8;
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .alert-error {
            background: #ffebee;
            color: var(--error-color);
            border-left-color: var(--error-color);
        }

        /* Admin Language Switcher Styles */
        .admin-language-switcher {
            position: relative;
            display: inline-block;
        }

        .admin-language-dropdown {
            position: relative;
        }

        .admin-language-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            cursor: pointer;
        }

        .admin-language-btn:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-medium);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: #f8f9fa;
        }

        .admin-language-item.active {
            background: var(--primary-light);
            color: var(--primary-color);
        }

        .language-flag {
            font-size: 1rem;
        }

        .language-name {
            flex: 1;
        }

        .admin-language-item i {
            color: var(--success-color);
            font-size: 0.75rem;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--primary-color);
            color: white;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .sidebar-header p {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 0;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--text-primary);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-link:hover {
            background: var(--primary-light);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .nav-link.active {
            background: var(--primary-light);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .nav-link span {
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .content {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> <?= __('edit_transaction') ?> #<?= $transaction_id ?></h1>
                        <p><?= __('modify_transaction_info') ?></p>
                    </div>

                    <div style="display: flex; align-items: center; gap: 20px;">
                        <!-- Language Switcher -->
                        <div class="admin-language-switcher">
                            <div class="admin-language-dropdown">
                                <button class="admin-language-btn" onclick="toggleAdminLanguageDropdown()">
                                    <i class="fas fa-globe"></i>
                                    <span><?= getCurrentLanguage() == 'fr' ? 'Français' : 'English' ?></span>
                                    <i class="fas fa-chevron-down"></i>
                                </button>

                                <div class="admin-language-menu" id="adminLanguageDropdown">
                                    <a href="?lang=fr" class="admin-language-item <?= getCurrentLanguage() == 'fr' ? 'active' : '' ?>">
                                        <span class="language-flag">🇫🇷</span>
                                        <span class="language-name">Français</span>
                                        <?php if (getCurrentLanguage() == 'fr'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                    <a href="?lang=en" class="admin-language-item <?= getCurrentLanguage() == 'en' ? 'active' : '' ?>">
                                        <span class="language-flag">🇬🇧</span>
                                        <span class="language-name">English</span>
                                        <?php if (getCurrentLanguage() == 'en'): ?>
                                            <i class="fas fa-check"></i>
                                        <?php endif; ?>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- User Menu -->
                        <div class="user-menu">
                            <div class="user-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <div style="font-weight: 600; font-size: 0.875rem;"><?= htmlspecialchars($current_user['full_name'] ?? __('administrator')) ?></div>
                                <div style="font-size: 0.75rem; opacity: 0.7;"><?= __('admin_panel') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content -->
        <div class="content">
            <!-- Form Section -->
            <div class="form-section">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= $_SESSION['success'] ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= $_SESSION['error'] ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <h3><i class="fas fa-edit"></i> <?= __('transaction_information') ?></h3>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label"><?= __('amount') ?> *</label>
                        <input type="number" step="0.01" name="amount" class="form-control"
                            value="<?= htmlspecialchars($transaction['amount']) ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('currency') ?> *</label>
                        <select name="currency" class="form-control" required>
                            <option value=""><?= __('select_currency') ?></option>
                            <option value="GHS" <?= $transaction['currency'] === 'GHS' ? 'selected' : '' ?>><?= __('ghs') ?></option>
                            <option value="USD" <?= $transaction['currency'] === 'USD' ? 'selected' : '' ?>><?= __('usd') ?></option>
                            <option value="EUR" <?= $transaction['currency'] === 'EUR' ? 'selected' : '' ?>><?= __('eur') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('payment_method') ?> *</label>
                        <select name="payment_method" class="form-control" required>
                            <option value=""><?= __('select_payment_method') ?></option>
                            <option value="card" <?= $transaction['payment_method'] === 'card' ? 'selected' : '' ?>><?= __('card') ?></option>
                            <option value="bank_transfer" <?= $transaction['payment_method'] === 'bank_transfer' ? 'selected' : '' ?>><?= __('bank_transfer') ?></option>
                            <option value="mobile_money" <?= $transaction['payment_method'] === 'mobile_money' ? 'selected' : '' ?>><?= __('mobile_money') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('payment_status') ?> *</label>
                        <select name="payment_status" class="form-control" required>
                            <option value=""><?= __('select_status') ?></option>
                            <option value="pending" <?= $transaction['payment_status'] === 'pending' ? 'selected' : '' ?>><?= __('pending') ?></option>
                            <option value="success" <?= $transaction['payment_status'] === 'success' ? 'selected' : '' ?>><?= __('success') ?></option>
                            <option value="failed" <?= $transaction['payment_status'] === 'failed' ? 'selected' : '' ?>><?= __('failed') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('transaction_type') ?> *</label>
                        <select name="type" class="form-control" required>
                            <option value=""><?= __('select_type') ?></option>
                            <option value="course" <?= $transaction['type'] === 'course' ? 'selected' : '' ?>><?= __('course') ?></option>
                            <option value="product" <?= $transaction['type'] === 'product' ? 'selected' : '' ?>><?= __('product') ?></option>
                            <option value="subscription" <?= $transaction['type'] === 'subscription' ? 'selected' : '' ?>><?= __('subscription') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('notes') ?></label>
                        <textarea name="notes" class="form-control" rows="4"
                            placeholder="<?= __('transaction_notes_placeholder') ?>"><?= htmlspecialchars($transaction['notes'] ?? '') ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?= __('save_changes') ?>
                        </button>
                        <a href="transactions.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?= __('back') ?>
                        </a>
                        <a href="transactions.php" class="btn btn-outline">
                            <i class="fas fa-times"></i> <?= __('cancel') ?>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Info Section -->
            <div class="info-section">
                <h3><i class="fas fa-info-circle"></i> <?= __('transaction_details') ?></h3>

                <div class="info-item">
                    <span class="info-label"><?= __('transaction_id') ?>:</span>
                    <span class="info-value">#<?= $transaction['id'] ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label"><?= __('user') ?>:</span>
                    <span class="info-value"><?= htmlspecialchars($transaction['full_name'] ?? __('unknown')) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label"><?= __('email') ?>:</span>
                    <span class="info-value"><?= htmlspecialchars($transaction['email'] ?? __('not_available')) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label"><?= __('reference') ?>:</span>
                    <span class="info-value"><?= htmlspecialchars($transaction['reference'] ?? __('not_available')) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label"><?= __('created_date') ?>:</span>
                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label"><?= __('last_updated') ?>:</span>
                    <span class="info-value"><?= isset($transaction['updated_at']) && $transaction['updated_at'] ? date('d/m/Y H:i', strtotime($transaction['updated_at'])) : __('not_updated') ?></span>
                </div>

                <div class="info-item">
                    <span class="info-label"><?= __('current_status') ?>:</span>
                    <span class="info-value">
                        <?php
                        $status_labels = [
                            'success' => [__('success'), 'badge-success'],
                            'pending' => [__('pending'), 'badge-warning'],
                            'failed' => [__('failed'), 'badge-danger']
                        ];
                        $status_info = $status_labels[$transaction['payment_status']] ?? [__('unknown'), 'badge-info'];
                        ?>
                        <span class="badge <?= $status_info[1] ?>"><?= $status_info[0] ?></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const button = document.querySelector('.admin-language-btn');

            if (!button.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const inputs = form.querySelectorAll('input[required], select[required]');

            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.value.trim()) {
                        this.style.borderColor = 'var(--error-color)';
                    } else {
                        this.style.borderColor = 'var(--border-color)';
                    }
                });

                input.addEventListener('input', function() {
                    if (this.value.trim()) {
                        this.style.borderColor = 'var(--success-color)';
                    }
                });
            });

            form.addEventListener('submit', function(e) {
                let isValid = true;

                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        input.style.borderColor = 'var(--error-color)';
                        isValid = false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    alert('<?= __('please_fill_required_fields') ?>');
                }
            });
        });
    </script>
</body>

</html>