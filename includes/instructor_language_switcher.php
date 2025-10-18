<?php

/**
 * Professional Language Switcher for Instructor Dashboard
 * Enhanced version with better styling and functionality
 */

require_once 'i18n.php';

$current_lang = getCurrentLanguage();
$available_langs = getAvailableLanguages();
$current_url = $_SERVER['REQUEST_URI'];
$current_url = preg_replace('/[?&]lang=[^&]*/', '', $current_url);
$separator = strpos($current_url, '?') !== false ? '&' : '?';
?>

<div class="instructor-language-switcher">
    <div class="language-switcher-container">
        <button class="language-switcher-toggle" onclick="toggleLanguageDropdown()" aria-label="<?= __('switch_language') ?>">
            <div class="language-switcher-current">
                <span class="language-flag">
                    <?= $current_lang === 'fr' ? '🇫🇷' : '🇬🇧' ?>
                </span>
                <span class="language-name"><?= __($current_lang === 'fr' ? 'french' : 'english') ?></span>
                <i class="fas fa-chevron-down language-arrow"></i>
            </div>
        </button>

        <div class="language-switcher-dropdown" id="languageDropdown">
            <?php foreach ($available_langs as $lang): ?>
                <a href="<?= $current_url . $separator . 'lang=' . $lang ?>"
                    class="language-switcher-option <?= $lang === $current_lang ? 'active' : '' ?>"
                    data-lang="<?= $lang ?>">
                    <span class="language-flag">
                        <?= $lang === 'fr' ? '🇫🇷' : '🇬🇧' ?>
                    </span>
                    <span class="language-name"><?= __($lang === 'fr' ? 'french' : 'english') ?></span>
                    <?php if ($lang === $current_lang): ?>
                        <i class="fas fa-check language-check"></i>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<style>
    .instructor-language-switcher {
        position: relative;
        display: inline-block;
        z-index: 1000;
    }

    .language-switcher-container {
        position: relative;
    }

    .language-switcher-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        color: white;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        min-width: 140px;
    }

    .language-switcher-toggle:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .language-switcher-current {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
    }

    .language-flag {
        font-size: 18px;
        line-height: 1;
    }

    .language-name {
        flex: 1;
        font-size: 14px;
        font-weight: 500;
    }

    .language-arrow {
        font-size: 12px;
        transition: transform 0.3s ease;
    }

    .language-switcher-toggle.active .language-arrow {
        transform: rotate(180deg);
    }

    .language-switcher-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        min-width: 160px;
        z-index: 1001;
        display: none;
        margin-top: 4px;
        overflow: hidden;
    }

    .language-switcher-dropdown.show {
        display: block;
        animation: slideDown 0.2s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .language-switcher-option {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        text-decoration: none;
        color: #4a5568;
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
        position: relative;
    }

    .language-switcher-option:last-child {
        border-bottom: none;
    }

    .language-switcher-option:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        transform: translateX(2px);
    }

    .language-switcher-option.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-weight: 600;
    }

    .language-switcher-option .language-flag {
        font-size: 16px;
        line-height: 1;
    }

    .language-switcher-option .language-name {
        flex: 1;
        font-size: 14px;
        font-weight: 500;
    }

    .language-check {
        font-size: 12px;
        margin-left: auto;
        color: #48bb78;
    }

    .language-switcher-option.active .language-check,
    .language-switcher-option:hover .language-check {
        color: white;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .language-switcher-toggle {
            padding: 8px 12px;
            min-width: 120px;
            font-size: 13px;
        }

        .language-switcher-dropdown {
            min-width: 140px;
        }

        .language-switcher-option {
            padding: 10px 12px;
            font-size: 13px;
        }
    }

    /* Dark mode support */
    @media (prefers-color-scheme: dark) {
        .language-switcher-dropdown {
            background: #2d3748;
            border-color: #4a5568;
        }

        .language-switcher-option {
            color: #e2e8f0;
            border-bottom-color: #4a5568;
        }

        .language-switcher-option:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    }
</style>

<script>
    function toggleLanguageDropdown() {
        const dropdown = document.getElementById('languageDropdown');
        const toggle = document.querySelector('.language-switcher-toggle');

        dropdown.classList.toggle('show');
        toggle.classList.toggle('active');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const switcher = document.querySelector('.instructor-language-switcher');
        const dropdown = document.getElementById('languageDropdown');
        const toggle = document.querySelector('.language-switcher-toggle');

        if (!switcher.contains(event.target)) {
            dropdown.classList.remove('show');
            toggle.classList.remove('active');
        }
    });

    // Close dropdown on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            const dropdown = document.getElementById('languageDropdown');
            const toggle = document.querySelector('.language-switcher-toggle');

            dropdown.classList.remove('show');
            toggle.classList.remove('active');
        }
    });

    // Add loading state when switching languages
    document.querySelectorAll('.language-switcher-option').forEach(option => {
        option.addEventListener('click', function(e) {
            if (!this.classList.contains('active')) {
                // Add loading indicator
                const toggle = document.querySelector('.language-switcher-toggle');
                toggle.style.opacity = '0.7';
                toggle.style.pointerEvents = 'none';

                // Show loading text
                const languageName = this.querySelector('.language-name');
                const originalText = languageName.textContent;
                languageName.textContent = '<?= __('loading') ?>...';

                // Re-enable after a short delay (in case redirect is slow)
                setTimeout(() => {
                    toggle.style.opacity = '1';
                    toggle.style.pointerEvents = 'auto';
                    languageName.textContent = originalText;
                }, 2000);
            }
        });
    });
</script>