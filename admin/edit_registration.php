<?php
/**
 * Modern Event Registration Editor
 * TaaBia Platform - Professional Registration Management
 * 
 * Features:
 * - Class-based architecture with proper separation of concerns
 * - Comprehensive input validation and sanitization
 * - CSRF protection and security headers
 * - Phone field support
 * - Real-time form validation
 * - Responsive and accessible UI
 * - Proper error handling and logging
 * - Audit trail and change tracking
 * - Full bilingual support (French/English)
 */

// Start output buffering to prevent any accidental output
ob_start();

// Handle language switching first
require_once 'language_handler.php';

// Now load the session and other includes
require_once '../includes/db.php';
require_once '../includes/session.php';
require_once '../includes/function.php';
require_once '../includes/i18n.php';
require_role('admin');

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * Registration Editor Handler Class
 */
class RegistrationEditor {
    private $pdo;
    private $registration_id;
    private $registration_data;
    private $events;
    private $errors = [];
    private $success_message = '';
    private $current_user;
    
    public function __construct($pdo, $registration_id) {
        $this->pdo = $pdo;
        $this->registration_id = $registration_id;
        $this->validateRegistrationId();
        $this->loadCurrentUser();
        $this->loadRegistrationData();
        $this->loadEvents();
    }
    
    /**
     * Validate registration ID
     */
    private function validateRegistrationId() {
        if (!$this->registration_id || $this->registration_id <= 0) {
            $this->redirectToRegistrations();
        }
    }
    
    /**
     * Load current user info
     */
    private function loadCurrentUser() {
        try {
            $stmt = $this->pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
            $this->current_user = $stmt->fetch();
} catch (PDOException $e) {
    error_log("Error fetching current user: " . $e->getMessage());
            $this->current_user = ['full_name' => 'Admin', 'email' => 'admin@taabia.com'];
        }
}

    /**
     * Load registration data from database
     */
    private function loadRegistrationData() {
try {
            $query = "
                SELECT er.*, e.title as event_title, e.event_date, e.status as event_status
        FROM event_registrations er
        JOIN events e ON er.event_id = e.id
        WHERE er.id = ?
            ";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$this->registration_id]);
            $this->registration_data = $stmt->fetch();
            
            if (!$this->registration_data) {
                $this->redirectToRegistrations();
            }
            
        } catch (PDOException $e) {
            error_log("Registration data loading error: " . $e->getMessage());
            $this->redirectToRegistrations();
        }
    }
    
    /**
     * Load events for dropdown
     */
    private function loadEvents() {
        try {
            $stmt = $this->pdo->query("SELECT id, title, event_date, status FROM events ORDER BY event_date DESC");
            $this->events = $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Events loading error: " . $e->getMessage());
            $this->events = [];
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     */
    private function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Sanitize input data
     */
    private function sanitizeInput($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email format
     */
    private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate phone number (basic validation)
     */
    private function validatePhone($phone) {
        if (empty($phone)) return true; // Phone is optional
        return preg_match('/^[\+]?[0-9\s\-\(\)]{8,20}$/', $phone);
    }
    
    /**
     * Check if email is already registered for another registration
     */
    private function isEmailRegisteredElsewhere($email, $event_id) {
        try {
            $query = "SELECT id FROM event_registrations WHERE event_id = ? AND email = ? AND id != ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$event_id, $email, $this->registration_id]);
            return $stmt->fetch() !== false;
} catch (PDOException $e) {
            error_log("Email registration check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Process form submission
     */
    public function processUpdate() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return false;
        }
        
        // Validate CSRF token
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!$this->validateCSRFToken($csrf_token)) {
            $this->errors[] = "Token de sécurité invalide. Veuillez réessayer.";
            return false;
        }
        
        // Get and sanitize form data
        $name = $this->sanitizeInput($_POST['name'] ?? '');
        $email = $this->sanitizeInput($_POST['email'] ?? '');
        $phone = $this->sanitizeInput($_POST['phone'] ?? '');
        $event_id = (int)($_POST['event_id'] ?? 0);
    
    // Validation
        if (empty($name)) {
            $this->errors[] = "Le nom est obligatoire.";
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $this->errors[] = "Le nom doit contenir entre 2 et 100 caractères.";
        }
        
        if (empty($email)) {
            $this->errors[] = "L'adresse email est obligatoire.";
        } elseif (!$this->validateEmail($email)) {
            $this->errors[] = "Veuillez saisir une adresse email valide.";
        } elseif ($this->isEmailRegisteredElsewhere($email, $event_id)) {
            $this->errors[] = "Cette adresse email est déjà inscrite à cet événement.";
        }
        
        if (!empty($phone) && !$this->validatePhone($phone)) {
            $this->errors[] = "Veuillez saisir un numéro de téléphone valide.";
        }
        
        if ($event_id <= 0) {
            $this->errors[] = "Veuillez sélectionner un événement valide.";
        }
        
        // If no errors, proceed with update
        if (empty($this->errors)) {
            return $this->updateRegistration($name, $email, $phone, $event_id);
        }
        
        return false;
    }
    
    /**
     * Update registration in database
     */
    private function updateRegistration($name, $email, $phone, $event_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Log the changes for audit trail
            $this->logChanges($name, $email, $phone, $event_id);
            
            $query = "
                UPDATE event_registrations 
                SET name = ?, email = ?, phone = ?, event_id = ?
                WHERE id = ?
            ";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$name, $email, $phone, $event_id, $this->registration_id]);
            
            $this->pdo->commit();
            
            // Update local data for display
            $this->registration_data['name'] = $name;
            $this->registration_data['email'] = $email;
            $this->registration_data['phone'] = $phone;
            $this->registration_data['event_id'] = $event_id;
            
            $this->success_message = "✅ Inscription mise à jour avec succès !";
            return true;
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Registration update error: " . $e->getMessage());
            $this->errors[] = "Une erreur est survenue lors de la mise à jour. Veuillez réessayer.";
            return false;
        }
    }
    
    /**
     * Log changes for audit trail
     */
    private function logChanges($name, $email, $phone, $event_id) {
        $changes = [];
        
        if ($this->registration_data['name'] !== $name) {
            $changes[] = "Nom: '{$this->registration_data['name']}' → '{$name}'";
        }
        if ($this->registration_data['email'] !== $email) {
            $changes[] = "Email: '{$this->registration_data['email']}' → '{$email}'";
        }
        if ($this->registration_data['phone'] !== $phone) {
            $changes[] = "Téléphone: '{$this->registration_data['phone']}' → '{$phone}'";
        }
        if ($this->registration_data['event_id'] != $event_id) {
            $changes[] = "Événement: ID {$this->registration_data['event_id']} → ID {$event_id}";
        }
        
        if (!empty($changes)) {
            $change_log = implode(', ', $changes);
            error_log("Registration #{$this->registration_id} updated by user #{$_SESSION['user_id']}: {$change_log}");
        }
    }
    
    /**
     * Get registration data
     */
    public function getRegistrationData() {
        return $this->registration_data;
    }
    
    /**
     * Get events
     */
    public function getEvents() {
        return $this->events;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        return $this->current_user;
    }
    
    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Get success message
     */
    public function getSuccessMessage() {
        return $this->success_message;
    }
    
    /**
     * Redirect to registrations page
     */
    private function redirectToRegistrations() {
        header('Location: event_registrations.php');
        exit;
    }
}

// Initialize editor
$registration_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$editor = new RegistrationEditor($pdo, $registration_id);

// Process form submission
$update_success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $update_success = $editor->processUpdate();
}

$registration = $editor->getRegistrationData();
$events = $editor->getEvents();
$current_user = $editor->getCurrentUser();
$errors = $editor->getErrors();
$success_message = $editor->getSuccessMessage();
$csrf_token = $editor->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['user_language'] ?? 'fr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('edit_registration') ?> | <?= __('admin_panel') ?> | TaaBia</title>
    <meta name="description" content="<?= __('edit_registration_description') ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" as="style">
    
    <!-- Stylesheets -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
    
    <style>
        :root {
            --primary-color: #009688;
            --primary-light: #4db6ac;
            --primary-dark: #00695c;
            --secondary-color: #00bcd4;
            --success-color: #4caf50;
            --warning-color: #ff9800;
            --danger-color: #f44336;
            --info-color: #2196f3;
            --text-primary: #212121;
            --text-secondary: #757575;
            --text-white: #ffffff;
            --bg-primary: #ffffff;
            --bg-secondary: #fafafa;
            --bg-tertiary: #f5f5f5;
            --border-color: #e0e0e0;
            --border-color-focus: #009688;
            --border-radius: 12px;
            --border-radius-sm: 6px;
            --border-radius-lg: 16px;
            --shadow-light: 0 2px 4px rgba(0,0,0,0.1);
            --shadow-medium: 0 4px 8px rgba(0,0,0,0.12);
            --shadow-heavy: 0 8px 16px rgba(0,0,0,0.15);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s ease;
            --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --spacing-xs: 0.25rem;
            --spacing-sm: 0.5rem;
            --spacing-md: 1rem;
            --spacing-lg: 1.5rem;
            --spacing-xl: 2rem;
            --spacing-2xl: 3rem;
        }

        /* Enhanced form styling */
        .form-group {
            margin-bottom: var(--spacing-lg);
        }

        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--bg-primary);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--border-color-focus);
            box-shadow: 0 0 0 3px rgba(0, 150, 136, 0.1);
        }

        .form-control.error {
            border-color: var(--danger-color);
        }

        .form-control.success {
            border-color: var(--success-color);
        }

        .form-help {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-top: var(--spacing-xs);
        }

        .form-error {
            font-size: 0.75rem;
            color: var(--danger-color);
            margin-top: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .form-success {
            font-size: 0.75rem;
            color: var(--success-color);
            margin-top: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        /* Enhanced alert styling */
        .alert {
            padding: var(--spacing-lg);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-xl);
            border: 1px solid;
            display: flex;
            align-items: flex-start;
            gap: var(--spacing-md);
        }

        .alert-danger {
            background: #ffebee;
            border-color: var(--danger-color);
            color: #c62828;
        }

        .alert-success {
            background: #e8f5e8;
            border-color: var(--success-color);
            color: #2e7d32;
        }

        .alert-icon {
            font-size: 1.25rem;
            margin-top: 2px;
        }

        .alert-content h4 {
            margin-bottom: var(--spacing-sm);
            font-weight: 600;
        }

        .alert-content ul {
            margin: var(--spacing-sm) 0 0 0;
            padding-left: var(--spacing-lg);
        }

        .alert-content li {
            margin-bottom: var(--spacing-xs);
        }

        /* Enhanced button styling */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
            padding: var(--spacing-sm) var(--spacing-lg);
            border: none;
            border-radius: var(--border-radius-sm);
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-primary:hover:not(:disabled) {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        .btn-secondary {
            background: transparent;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            color: var(--text-white);
        }

        .btn-danger {
            background: var(--danger-color);
            color: var(--text-white);
        }

        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-1px);
            box-shadow: var(--shadow-medium);
        }

        /* Loading states */
        .btn.loading {
            position: relative;
            color: transparent;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Info card styling */
        .info-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-lg);
        }

        .info-card h4 {
            color: var(--text-primary);
            margin-bottom: var(--spacing-md);
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm) 0;
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

        /* Responsive design */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                justify-content: center;
            }
        }

        /* Accessibility */
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        /* Focus styles for better accessibility */
        .btn:focus,
        .form-control:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Hamburger Menu Styles */
        .hamburger-menu {
            display: none;
            flex-direction: column;
            justify-content: space-around;
            width: 30px;
            height: 30px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            z-index: 1001;
            transition: all 0.3s ease;
        }
        
        .hamburger-line {
            width: 100%;
            height: 3px;
            background-color: var(--text-primary);
            border-radius: 2px;
            transition: all 0.3s ease;
            transform-origin: center;
        }
        
        /* Hamburger menu animation */
        .hamburger-menu.active .hamburger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(2) {
            opacity: 0;
        }
        
        .hamburger-menu.active .hamburger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }
        
        /* Sidebar overlay for mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .hamburger-menu {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }
            
            .sidebar-overlay {
                display: block;
            }

            .main-content {
                margin-left: 0;
            }

            .content-header {
                padding-left: 20px;
            }
        }

        /* Admin Language Switcher */
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
            background: var(--light-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            cursor: pointer;
            font-size: 14px;
            color: var(--dark-color);
            transition: var(--transition);
        }

        .admin-language-btn:hover {
            background: white;
            border-color: var(--primary-color);
        }

        .admin-language-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            box-shadow: var(--shadow-lg);
            min-width: 150px;
            z-index: 1000;
            display: none;
            margin-top: 4px;
        }

        .admin-language-menu.show {
            display: block;
        }

        .admin-language-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 12px;
            text-decoration: none;
            color: var(--dark-color);
            transition: var(--transition);
            border-bottom: 1px solid var(--border-color);
        }

        .admin-language-item:last-child {
            border-bottom: none;
        }

        .admin-language-item:hover {
            background: var(--light-color);
        }

        .admin-language-item.active {
            background: var(--primary-color);
            color: white;
        }

        .language-flag {
            font-size: 16px;
        }

        .language-name {
            flex: 1;
            font-size: 14px;
        }

        .admin-language-item i {
            font-size: 12px;
            margin-left: auto;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar Overlay for Mobile -->
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="content-header">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div class="page-title">
                        <h1><i class="fas fa-edit"></i> <?= __('edit_registration') ?></h1>
                        <p style="color: var(--text-secondary); margin-top: var(--spacing-sm);">
                            <?= __('modify_registration_details') ?> #<?= $registration_id ?>
                        </p>
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

                        <a href="event_registrations.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> <?= __('back_to_registrations') ?>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <i class="fas fa-check-circle alert-icon" aria-hidden="true"></i>
                    <div class="alert-content">
                        <h4><?= __('success') ?></h4>
                        <p><?= htmlspecialchars($success_message) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <i class="fas fa-exclamation-triangle alert-icon" aria-hidden="true"></i>
                    <div class="alert-content">
                        <h4><?= __('validation_errors') ?></h4>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Info Card -->
            <div class="info-card">
                <h4><i class="fas fa-info-circle"></i> Informations actuelles</h4>
                <div class="info-item">
                    <span class="info-label">Événement actuel</span>
                    <span class="info-value"><?= htmlspecialchars($registration['event_title']) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date de l'événement</span>
                    <span class="info-value"><?= date('d/m/Y à H:i', strtotime($registration['event_date'])) ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Statut de l'événement</span>
                    <span class="info-value">
                        <?php
                        $status_labels = [
                            'upcoming' => 'À venir',
                            'ongoing' => 'En cours',
                            'completed' => 'Terminé',
                            'cancelled' => 'Annulé'
                        ];
                        echo $status_labels[$registration['event_status']] ?? 'Inconnu';
                        ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date d'inscription</span>
                    <span class="info-value"><?= date('d/m/Y à H:i', strtotime($registration['registered_at'])) ?></span>
                </div>
            </div>

            <!-- Form -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Modifier les détails</h3>
                    <p style="color: var(--text-secondary); margin: 0;">Tous les champs marqués d'un astérisque (*) sont obligatoires.</p>
                </div>
                <div class="card-body">
                    <form method="POST" class="form" id="editForm" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name"><?= __('participant_name') ?> *</label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($registration['name']) ?>" 
                                       required 
                                       autocomplete="name"
                                       aria-describedby="name-help"
                                       minlength="2"
                                       maxlength="100">
                                <div class="form-help" id="name-help"><?= __('enter_full_name_help') ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="email"><?= __('email_address') ?> *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($registration['email']) ?>" 
                                       required 
                                       autocomplete="email"
                                       aria-describedby="email-help">
                                <div class="form-help" id="email-help"><?= __('email_confirmation_help') ?></div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone"><?= __('phone_number') ?></label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($registration['phone'] ?? '') ?>" 
                                       autocomplete="tel"
                                       aria-describedby="phone-help"
                                       pattern="[\+]?[0-9\s\-\(\)]{8,20}">
                                <div class="form-help" id="phone-help"><?= __('phone_optional_format') ?></div>
                            </div>
                            
                            <div class="form-group">
                                <label for="event_id"><?= __('event') ?> *</label>
                                <select id="event_id" name="event_id" class="form-control" required>
                                    <option value=""><?= __('select_event') ?></option>
                                    <?php foreach ($events as $event): ?>
                                        <option value="<?= $event['id'] ?>" 
                                                <?= $registration['event_id'] == $event['id'] ? 'selected' : '' ?>
                                                data-date="<?= date('d/m/Y', strtotime($event['event_date'])) ?>"
                                                data-status="<?= $event['status'] ?>">
                                            <?= htmlspecialchars($event['title']) ?>
                                            (<?= date('d/m/Y', strtotime($event['event_date'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-help" id="event-help"><?= __('select_event_for_registration') ?></div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> <?= __('update_registration') ?>
                            </button>
                            <a href="event_registrations.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> <?= __('cancel') ?>
                            </a>
                            <button type="button" class="btn btn-danger" id="deleteBtn" onclick="confirmDelete()">
                                <i class="fas fa-trash"></i> <?= __('delete') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('editForm');
            const submitBtn = document.getElementById('submitBtn');
            
            if (form && submitBtn) {
                // Real-time validation
                const inputs = form.querySelectorAll('input[required], select[required]');
                
                inputs.forEach(input => {
                    input.addEventListener('blur', validateField);
                    input.addEventListener('input', clearFieldError);
                });
                
                // Form submission
                form.addEventListener('submit', function(e) {
                    if (!validateForm()) {
                        e.preventDefault();
                        return false;
                    }
                    
                    // Show loading state
                    submitBtn.classList.add('loading');
                    submitBtn.disabled = true;
                });
                
                function validateField(e) {
                    const field = e.target;
                    const value = field.value.trim();
                    const fieldGroup = field.closest('.form-group');
                    
                    // Remove existing error/success classes
                    field.classList.remove('error', 'success');
                    const existingError = fieldGroup.querySelector('.form-error');
                    const existingSuccess = fieldGroup.querySelector('.form-success');
                    if (existingError) existingError.remove();
                    if (existingSuccess) existingSuccess.remove();
                    
                    let isValid = true;
                    let errorMessage = '';
                    
                    // Required field validation
                    if (field.hasAttribute('required') && !value) {
                        isValid = false;
                        errorMessage = 'Ce champ est obligatoire.';
                    }
                    
                    // Specific field validations
                    if (value && field.type === 'email') {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(value)) {
                            isValid = false;
                            errorMessage = 'Veuillez saisir une adresse email valide.';
                        }
                    }
                    
                    if (value && field.type === 'text' && field.name === 'name') {
                        if (value.length < 2) {
                            isValid = false;
                            errorMessage = 'Le nom doit contenir au moins 2 caractères.';
                        } else if (value.length > 100) {
                            isValid = false;
                            errorMessage = 'Le nom ne peut pas dépasser 100 caractères.';
                        }
                    }
                    
                    if (value && field.type === 'tel') {
                        const phoneRegex = /^[\+]?[0-9\s\-\(\)]{8,20}$/;
                        if (!phoneRegex.test(value)) {
                            isValid = false;
                            errorMessage = 'Veuillez saisir un numéro de téléphone valide.';
                        }
                    }
                    
                    if (value && field.name === 'event_id') {
                        if (parseInt(value) <= 0) {
                            isValid = false;
                            errorMessage = 'Veuillez sélectionner un événement valide.';
                        }
                    }
                    
                    // Show validation result
                    if (isValid && value) {
                        field.classList.add('success');
                        const successDiv = document.createElement('div');
                        successDiv.className = 'form-success';
                        successDiv.innerHTML = '<i class="fas fa-check"></i> Valide';
                        fieldGroup.appendChild(successDiv);
                    } else if (!isValid) {
                        field.classList.add('error');
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'form-error';
                        errorDiv.innerHTML = '<i class="fas fa-times"></i> ' + errorMessage;
                        fieldGroup.appendChild(errorDiv);
                    }
                    
                    return isValid;
                }
                
                function clearFieldError(e) {
                    const field = e.target;
                    const fieldGroup = field.closest('.form-group');
                    const existingError = fieldGroup.querySelector('.form-error');
                    if (existingError) existingError.remove();
                    field.classList.remove('error');
                }
                
                function validateForm() {
                    let isFormValid = true;
                    
                    inputs.forEach(input => {
                        const event = { target: input };
                        if (!validateField(event)) {
                            isFormValid = false;
                        }
                    });
                    
                    return isFormValid;
                }
            }
            
            // Accessibility improvements
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.setAttribute('role', 'alert');
                alert.setAttribute('aria-live', 'polite');
            });
            
            // Focus management
            const firstInput = document.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
        
        function confirmDelete() {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette inscription ? Cette action est irréversible.')) {
                window.location.href = 'delete_registration.php?id=<?= $registration_id ?>';
            }
        }

        // Hamburger menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.querySelector('.sidebar');
            const sidebarOverlay = document.getElementById('sidebarOverlay');
            
            function toggleSidebar() {
                hamburgerMenu.classList.toggle('active');
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                
                // Prevent body scroll when sidebar is open
                if (sidebar.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
            
            function closeSidebar() {
                hamburgerMenu.classList.remove('active');
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            // Event listeners for hamburger menu
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('click', toggleSidebar);
            }
            
            // Close sidebar when clicking overlay
            if (sidebarOverlay) {
                sidebarOverlay.addEventListener('click', closeSidebar);
            }
            
            // Close sidebar when clicking on nav links (mobile)
            const navLinks = document.querySelectorAll('.nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth <= 768) {
                        closeSidebar();
                    }
                });
            });
            
            // Close sidebar on window resize if screen becomes larger
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    closeSidebar();
                }
            });
            
            // Keyboard navigation for hamburger menu
            if (hamburgerMenu) {
                hamburgerMenu.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        toggleSidebar();
                    }
                });
            }
        });

        // Admin Language Switcher
        function toggleAdminLanguageDropdown() {
            const dropdown = document.getElementById('adminLanguageDropdown');
            dropdown.classList.toggle('show');
        }

        // Close admin language dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('adminLanguageDropdown');
            const switcher = document.querySelector('.admin-language-switcher');

            if (switcher && !switcher.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });
    </script>
</body>
</html>
