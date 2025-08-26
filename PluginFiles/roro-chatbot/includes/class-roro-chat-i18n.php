<?php
/**
 * Internationalization helper for the RORO Chatbot plugin.
 *
 * This class centralizes the logic for detecting the current language and loading
 * the appropriate translation messages from the plugin's lang directory. By
 * isolating this functionality in its own module we ensure that language
 * detection and message loading can be reused across the admin, shortcode
 * handler and other components without duplicating code. The supported
 * languages correspond to the files located under `lang/` (currently ja,
 * en, zh and ko). If an unknown language is requested the fallback will be
 * English.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

final class RORO_Chat_I18n {

    /**
     * Determine the current UI language.
     *
     * The language is determined based on the following precedence:
     * 1. `roro_lang` query parameter (useful for testing)
     * 2. `roro_lang` cookie (set by the UI language switcher)
     * 3. WordPress locale (determine_locale or get_locale)
     *
     * Only ja, en, zh and ko are currently supported – any other value
     * will default to English.
     *
     * @return string Two–letter language code.
     */
    public static function detect_lang(): string {
        $l = '';
        if (isset($_GET['roro_lang'])) {
            $l = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $l = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            if (strpos($locale, 'ja') === 0) {
                $l = 'ja';
            } elseif (strpos($locale, 'zh') === 0) {
                $l = 'zh';
            } elseif (strpos($locale, 'ko') === 0) {
                $l = 'ko';
            } else {
                $l = 'en';
            }
        }
        return in_array($l, ['ja','en','zh','ko'], true) ? $l : 'en';
    }

    /**
     * Load the translation messages for the given language.
     *
     * Each language file sets `$roro_chat_messages` to an associative array of
     * strings. If the requested file does not exist the English file will
     * be used as a fallback. An empty array is returned if no messages
     * are found.
     *
     * @param string|null $lang Language code or null to autodetect.
     * @return array<string,string> Messages keyed by identifier.
     */
    public static function load_messages(?string $lang = null): array {
        if (!$lang) {
            $lang = self::detect_lang();
        }
        $path = RORO_CHAT_PATH . 'lang/' . $lang . '.php';
        if (!file_exists($path)) {
            $path = RORO_CHAT_PATH . 'lang/en.php';
        }
        $roro_chat_messages = [];
        if (file_exists($path)) {
            require $path;
        }
        return is_array($roro_chat_messages) ? $roro_chat_messages : [];
    }
}