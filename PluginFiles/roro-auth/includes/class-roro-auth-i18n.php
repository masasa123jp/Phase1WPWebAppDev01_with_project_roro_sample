<?php
/**
 * Internationalisation helper for the MECE RORO Auth plugin.
 *
 * This class loads simple PHP arrays from the lang directory and exposes
 * a lookup method for retrieving translated strings.  The language to use
 * is determined from WordPress' locale via get_locale() but can be
 * overridden using the `roro_lang` query parameter for debugging.  If
 * a translation key is missing for the active language it falls back
 * to the English string.  Developers should use Roro_Auth_I18n::t($key)
 * rather than the WordPress __() function because translations are
 * bundled with this plugin rather than in MO/PO files.
 */
class Roro_Auth_I18n {
    /**
     * Cached translation messages for the active language.
     *
     * @var array<string, string>
     */
    private static $messages = [];

    /**
     * Determine the two letter language code to use for translations.
     *
     * The locale returned by get_locale() can include a region (e.g. ja_JP).
     * We only care about the first two characters.  If an unsupported
     * language is requested we fall back to English.
     *
     * @return string One of 'ja', 'en', 'zh', 'ko'.
     */
    private static function detect_lang(): string {
        // Query parameter override for debugging.
        if (!empty($_GET['roro_lang'])) {
            $candidate = sanitize_key($_GET['roro_lang']);
            if (in_array($candidate, ['ja', 'en', 'zh', 'ko'], true)) {
                return $candidate;
            }
        }
        $locale = get_locale();
        $prefix = strtolower(substr($locale, 0, 2));
        return in_array($prefix, ['ja', 'en', 'zh', 'ko'], true) ? $prefix : 'en';
    }

    /**
     * Load translation messages into the static cache.
     *
     * This method first loads the English file to provide a complete set
     * of keys, then if another language is selected it merges in any
     * translated overrides.  The loaded messages remain in memory for
     * the lifetime of the request.  It is safe to call this multiple
     * times; it only reloads if the messages array is empty.
     *
     * @return void
     */
    public static function load_messages(): void {
        if (!empty(self::$messages)) {
            return;
        }
        $base = trailingslashit(RORO_AUTH_DIR) . 'lang/';
        $files = [
            'en' => $base . 'en.php',
            'ja' => $base . 'ja.php',
            'zh' => $base . 'zh.php',
            'ko' => $base . 'ko.php',
        ];
        // Always load English first for baseline keys.
        $loaded = [];
        if (file_exists($files['en'])) {
            $roro_auth_messages = [];
            include $files['en'];
            if (is_array($roro_auth_messages)) {
                $loaded = $roro_auth_messages;
            }
        }
        $lang = self::detect_lang();
        if ($lang !== 'en' && file_exists($files[$lang])) {
            $roro_auth_messages = [];
            include $files[$lang];
            if (is_array($roro_auth_messages)) {
                // Merge overrides on top of the English baseline.
                $loaded = array_merge($loaded, $roro_auth_messages);
            }
        }
        self::$messages = $loaded;
    }

    /**
     * Retrieve a translated string.
     *
     * If the key does not exist in the loaded messages the key itself is
     * returned.  Simple placeholder substitution is supported via an
     * associative array where {key} in the message will be replaced
     * with the corresponding value.
     *
     * @param string $key
     * @param array<string,string|int|float> $repl
     * @return string
     */
    public static function t(string $key, array $repl = []): string {
        // Ensure messages are loaded before lookup.
        if (empty(self::$messages)) {
            self::load_messages();
        }
        $msg = self::$messages[$key] ?? $key;
        if (!empty($repl)) {
            foreach ($repl as $k => $v) {
                $msg = str_replace('{' . $k . '}', (string) $v, $msg);
            }
        }
        return $msg;
    }

    /**
     * Expose translation messages for use in JavaScript.
     *
     * The returned array can be passed to wp_localize_script so that
     * front‑end scripts have access to the same translation keys.
     *
     * @return array<string,string>
     */
    public static function messages_for_js(): array {
        if (empty(self::$messages)) {
            self::load_messages();
        }
        return self::$messages;
    }
}