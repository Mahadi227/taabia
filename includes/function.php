<?php

// 🔒 Vérifie si un utilisateur est connecté
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// 🔐 Récupère l'ID de l'utilisateur connecté
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// 🎭 Vérifie si l'utilisateur a un rôle spécifique
function has_role($role) {
    return (isset($_SESSION['role']) && $_SESSION['role'] === $role);
}

// ✅ Vérifie qu'un utilisateur a un rôle donné, sinon redirige
function require_role($role, $redirectTo = '../auth/unauthorized.php') {
    if (!has_role($role)) {
        redirect($redirectTo);
    }
}

// 🔁 Redirige vers une page donnée
function redirect($url) {
    if (!headers_sent()) {
        header("Location: $url");
        exit();
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

// 🧹 Échappe les caractères pour éviter les injections HTML
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// 📩 Affiche un message flash
function flash_message($message, $type = 'success') {
    echo "<div class='flash-message $type'>$message</div>";
}

// 🕓 Formate une date lisible
function format_date($datetime) {
    return date("d/m/Y à H:i", strtotime($datetime));
}

// ⏰ Affiche le temps écoulé depuis une date
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) {
        return "À l'instant";
    } elseif ($time < 3600) {
        $minutes = floor($time / 60);
        return "Il y a $minutes minute" . ($minutes > 1 ? 's' : '');
    } elseif ($time < 86400) {
        $hours = floor($time / 3600);
        return "Il y a $hours heure" . ($hours > 1 ? 's' : '');
    } elseif ($time < 2592000) {
        $days = floor($time / 86400);
        return "Il y a $days jour" . ($days > 1 ? 's' : '');
    } elseif ($time < 31536000) {
        $months = floor($time / 2592000);
        return "Il y a $months mois";
    } else {
        $years = floor($time / 31536000);
        return "Il y a $years an" . ($years > 1 ? 's' : '');
    }
}

// 🔧 Site Settings Functions

// 📋 Get a system setting value
function get_setting($key, $default = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// 💾 Update a system setting
function update_setting($key, $value, $description = null) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, description) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value), 
            description = COALESCE(VALUES(description), description),
            updated_at = CURRENT_TIMESTAMP
        ");
        return $stmt->execute([$key, $value, $description]);
    } catch (PDOException $e) {
        return false;
    }
}

// 📊 Get all system settings
function get_all_settings() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, description FROM system_settings ORDER BY setting_key");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['description']
            ];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

// 🎨 Get commission settings
function get_commission_settings() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM commission_settings");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

// 💰 Update commission settings
function update_commission_settings($instructor_rate, $vendor_rate) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        // Update instructor commission rate
        $stmt = $pdo->prepare("UPDATE commission_settings SET setting_value = ? WHERE setting_key = 'instructor_commission_rate'");
        $stmt->execute([$instructor_rate]);
        
        // Update vendor commission rate
        $stmt = $pdo->prepare("UPDATE commission_settings SET setting_value = ? WHERE setting_key = 'vendor_commission_rate'");
        $stmt->execute([$vendor_rate]);
        
        // Update existing order items with new rates
        $stmt = $pdo->prepare("UPDATE order_items SET platform_commission_rate = ? WHERE instructor_id IS NOT NULL");
        $stmt->execute([$instructor_rate]);
        
        $stmt = $pdo->prepare("UPDATE order_items SET platform_commission_rate = ? WHERE vendor_id IS NOT NULL");
        $stmt->execute([$vendor_rate]);
        
        $pdo->commit();
        return true;
    } catch (PDOException $e) {
        $pdo->rollBack();
        return false;
    }
}

// 🖼️ Upload and manage site images
function upload_site_image($file, $type, $max_size = 5242880) {
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir === false) {
        return ['success' => false, 'error' => 'Upload directory not found'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error (code: ' . (int)$file['error'] . ')'];
    }
    
    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        return ['success' => false, 'error' => 'Unsupported format. Accepted: JPG, PNG, WEBP'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'File too large (max ' . ($max_size / 1024 / 1024) . 'MB)'];
    }
    
    // Remove existing files of this type
    foreach (['jpg', 'png', 'webp'] as $ext) {
        $candidate = $uploadsDir . DIRECTORY_SEPARATOR . $type . '.' . $ext;
        if (is_file($candidate)) {
            @unlink($candidate);
        }
    }
    
    $targetExt = $allowedMime[$mime];
    $targetPath = $uploadsDir . DIRECTORY_SEPARATOR . $type . '.' . $targetExt;
    
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Unable to save file'];
    }
    
    @chmod($targetPath, 0644);
    return ['success' => true, 'path' => 'uploads/' . $type . '.' . $targetExt];
}

// 🔍 Get current site image
function get_site_image($type) {
    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir === false) return null;
    
    foreach (['jpg', 'png', 'webp'] as $ext) {
        $path = $uploadsDir . DIRECTORY_SEPARATOR . $type . '.' . $ext;
        if (is_file($path)) {
            return 'uploads/' . $type . '.' . $ext;
        }
    }
    return null;
}
