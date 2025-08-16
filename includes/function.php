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
