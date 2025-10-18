<?php
// Start session if not already started and no output has been sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
} elseif (session_status() === PHP_SESSION_NONE) {
    // If headers are already sent, we can't start session
    // This is a fallback - the session should be started earlier in the main file
    error_log('Warning: Cannot start session in language switcher - headers already sent');
}

require_once 'i18n.php';

$current_lang = getCurrentLanguage();
$available_langs = getAvailableLanguages();

// Get current URL and clean it
$current_url = $_SERVER['REQUEST_URI'];
$current_url = preg_replace('/[?&]lang=[^&]*/', '', $current_url);
$current_url = rtrim($current_url, '?');

// Determine separator
$separator = strpos($current_url, '?') !== false ? '&' : '?';
?>

<div class="language-switcher">
    <div class="language-switcher-dropdown">
        <button class="language-switcher-btn" onclick="toggleLanguageDropdown()">
            <i class="fas fa-globe"></i>
            <span class="current-language">
                <?= getCurrentLanguage() == 'fr' ? '🇫🇷 Français' : '🇬🇧 English' ?>
            </span>
            <i class="fas fa-chevron-down"></i>
        </button>

        <div class="language-switcher-menu" id="languageDropdown">
            <?php foreach ($available_langs as $lang): ?>
                <a href="<?= $current_url . $separator . 'lang=' . $lang ?>"
                    class="language-switcher-item <?= $lang === $current_lang ? 'active' : '' ?>">
                    <span class="language-flag">
                        <?= $lang === 'fr' ? '🇫🇷' : '🇬🇧' ?>
                    </span>
                    <span class="language-name"><?= $lang === 'fr' ? 'Français' : 'English' ?></span>
                    <?php if ($lang === $current_lang): ?>
                        <i class="fas fa-check"></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    .language-switcher {
        position: relative;
        display: inline-block;
    }

    .language-switcher-dropdown {
        position: relative;
    }

    .language-switcher-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        color: var(--text-primary);
        transition: all 0.2s ease;
        min-width: 120px;
        justify-content: space-between;
    }

    .language-switcher-btn:hover {
        background: var(--bg-secondary);
        border-color: var(--primary-color);
    }

    .language-switcher-menu {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 6px;
        box-shadow: var(--shadow-medium);
        min-width: 150px;
        z-index: 1000;
        display: none;
        margin-top: 4px;
    }

    .language-switcher-menu.show {
        display: block;
    }

    .language-switcher-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 12px;
        text-decoration: none;
        color: var(--text-primary);
        transition: background-color 0.2s ease;
        border-bottom: 1px solid var(--border-color);
    }

    .language-switcher-item:last-child {
        border-bottom: none;
    }

    .language-switcher-item:hover {
        background: var(--bg-secondary);
    }

    .language-switcher-item.active {
        background: var(--primary-color);
        color: var(--text-white);
    }

    .language-flag {
        font-size: 16px;
    }

    .language-name {
        flex: 1;
        font-size: 14px;
    }

    .language-switcher-item i {
        font-size: 12px;
        margin-left: auto;
    }
</style>

<script>
    function toggleLanguageDropdown() {
        const dropdown = document.getElementById('languageDropdown');
        dropdown.classList.toggle('show');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const dropdown = document.getElementById('languageDropdown');
        const switcher = document.querySelector('.language-switcher');

        if (!switcher.contains(event.target)) {
            dropdown.classList.remove('show');
        }
    });
</script>