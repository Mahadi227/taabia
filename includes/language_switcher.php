<?php
require_once 'i18n.php';

$current_lang = getCurrentLanguage();
$available_langs = getAvailableLanguages();
$current_url = $_SERVER['REQUEST_URI'];
$current_url = preg_replace('/[?&]lang=[^&]*/', '', $current_url);
$separator = strpos($current_url, '?') !== false ? '&' : '?';
?>

<div class="language-switcher">
    <div class="language-switcher-dropdown">
        <button class="language-switcher-btn" onclick="toggleLanguageDropdown()">
            <i class="fas fa-globe"></i>
            <span><?= __(getCurrentLanguage() == 'fr' ? 'french' : 'english') ?></span>
            <i class="fas fa-chevron-down"></i>
        </button>
        
        <div class="language-switcher-menu" id="languageDropdown">
            <?php foreach ($available_langs as $lang): ?>
                <a href="<?= $current_url . $separator . 'lang=' . $lang ?>" 
                   class="language-switcher-item <?= $lang === $current_lang ? 'active' : '' ?>">
                    <span class="language-flag">
                        <?= $lang === 'fr' ? '🇫🇷' : '🇬🇧' ?>
                    </span>
                    <span class="language-name"><?= __($lang === 'fr' ? 'french' : 'english') ?></span>
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
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    color: var(--gray-700);
    transition: all 0.2s ease;
}

.language-switcher-btn:hover {
    background: var(--gray-50);
    border-color: var(--gray-300);
}

.language-switcher-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 6px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
    color: var(--gray-700);
    transition: background-color 0.2s ease;
    border-bottom: 1px solid var(--gray-100);
}

.language-switcher-item:last-child {
    border-bottom: none;
}

.language-switcher-item:hover {
    background: var(--gray-50);
}

.language-switcher-item.active {
    background: var(--primary-color);
    color: var(--white);
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