<?php

/**
 * Internationalization (i18n) System for TaaBia Platform
 * Supports French (FR) and English (EN)
 */

class I18n
{
    private static $instance = null;
    private $translations = [];
    private $current_language = 'fr';
    private $fallback_language = 'en';
    private $available_languages = ['fr', 'en'];

    private function __construct()
    {
        $this->detectLanguage();
        $this->loadTranslations();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Detect user's preferred language
     */
    private function detectLanguage()
    {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user has set a language preference in session
        if (isset($_SESSION['user_language']) && in_array($_SESSION['user_language'], $this->available_languages)) {
            $this->current_language = $_SESSION['user_language'];
            return;
        }

        // Check for language parameter in URL (only if not in session)
        if (isset($_GET['lang']) && in_array($_GET['lang'], $this->available_languages)) {
            $this->current_language = $_GET['lang'];
            $_SESSION['user_language'] = $this->current_language;
            return;
        }

        // Check browser language only if no session preference
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (in_array($browser_lang, $this->available_languages)) {
                $this->current_language = $browser_lang;
                $_SESSION['user_language'] = $this->current_language;
                return;
            }
        }

        // Default to French
        $this->current_language = 'fr';
        $_SESSION['user_language'] = $this->current_language;
    }

    /**
     * Load translation files
     */
    private function loadTranslations()
    {
        $lang_file = __DIR__ . "/../lang/{$this->current_language}.php";
        $fallback_file = __DIR__ . "/../lang/{$this->fallback_language}.php";

        // Load current language translations
        if (file_exists($lang_file)) {
            $this->translations = include $lang_file;
        }

        // Load fallback translations for missing keys
        if (file_exists($fallback_file)) {
            $fallback_translations = include $fallback_file;
            $this->translations = array_merge($fallback_translations, $this->translations);
        }
    }

    /**
     * Get current language
     */
    public function getCurrentLanguage()
    {
        return $this->current_language;
    }

    /**
     * Get available languages
     */
    public function getAvailableLanguages()
    {
        return $this->available_languages;
    }

    /**
     * Set language
     */
    public function setLanguage($language)
    {
        if (in_array($language, $this->available_languages)) {
            $this->current_language = $language;
            $_SESSION['user_language'] = $language;
            $this->loadTranslations();
            return true;
        }
        return false;
    }

    /**
     * Translate a key
     */
    public function t($key, $params = [])
    {
        $translation = $this->translations[$key] ?? $key;

        // Replace parameters
        if (!empty($params)) {
            foreach ($params as $param => $value) {
                $translation = str_replace("{{$param}}", $value, $translation);
            }
        }

        return $translation;
    }

    /**
     * Get language direction (LTR/RTL)
     */
    public function getDirection()
    {
        return 'ltr'; // Both French and English are LTR
    }

    /**
     * Get language name
     */
    public function getLanguageName($code = null)
    {
        $code = $code ?: $this->current_language;
        $names = [
            'fr' => 'Français',
            'en' => 'English'
        ];
        return $names[$code] ?? $code;
    }
}

// Global translation function
function __($key, $params = [])
{
    return I18n::getInstance()->t($key, $params);
}

// Get current language
function getCurrentLanguage()
{
    return I18n::getInstance()->getCurrentLanguage();
}

// Set language
function setLanguage($language)
{
    return I18n::getInstance()->setLanguage($language);
}

// Get available languages
function getAvailableLanguages()
{
    return I18n::getInstance()->getAvailableLanguages();
}
