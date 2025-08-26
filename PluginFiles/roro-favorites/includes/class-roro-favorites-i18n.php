<?php
/**
 * 国際化関連のヘルパークラス。
 *
 * このクラスは言語検出と翻訳メッセージの読み込みを担当します。
 * 言語検出はクエリパラメータ（roro_lang）→Cookie→WordPressのロケールの順で評価し、
 * 対応する言語コードを返します。
 * 翻訳メッセージはlangディレクトリ内の配列ファイルから読み込みます。
 */
if (!defined('ABSPATH')) {
    exit;
}

class RORO_Favorites_I18n {
    /**
     * サポートしている言語コードのリスト。
     * @var array
     */
    public static $supported = ['ja', 'en', 'zh', 'ko'];

    /**
     * ユーザーの希望言語を検出します。
     *
     * クエリパラメータ? roro_lang=xx → Cookie → WordPressロケールの順に判定します。
     * サポートしていない言語の場合は英語を返します。
     *
     * @return string 言語コード
     */
    public static function detect_lang(): string {
        $lang = null;
        // クエリで指定されている場合が最優先
        if (isset($_GET['roro_lang'])) {
            $lang = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $lang = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            // WordPressのロケールから判定
            $locale = determine_locale();
            if (strpos($locale, 'ja') === 0) {
                $lang = 'ja';
            } elseif (strpos($locale, 'zh') === 0) {
                $lang = 'zh';
            } elseif (strpos($locale, 'ko') === 0) {
                $lang = 'ko';
            } else {
                $lang = 'en';
            }
        }
        return in_array($lang, self::$supported, true) ? $lang : 'en';
    }

    /**
     * 翻訳メッセージを読み込みます。
     *
     * langディレクトリ配下の{lang}.phpを読み込み、
     * $roro_fav_messages配列を返します。指定言語のファイルがない場合は英語を読み込みます。
     *
     * @param string $lang 言語コード
     * @return array 翻訳メッセージ
     */
    public static function load_messages(string $lang): array {
        $messages = [];
        $file = RORO_FAV_PATH . 'lang/' . $lang . '.php';
        if (file_exists($file)) {
            include $file;
            if (isset($roro_fav_messages) && is_array($roro_fav_messages)) {
                $messages = $roro_fav_messages;
            }
        }
        // Fallback to English if empty
        if (empty($messages)) {
            include RORO_FAV_PATH . 'lang/en.php';
            $messages = $roro_fav_messages;
        }
        return $messages;
    }
}
