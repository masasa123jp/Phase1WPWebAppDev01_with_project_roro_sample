<?php
if (!defined('ABSPATH')) { exit; }

class RORO_Advice_Service {

    public function detect_lang(){
        if (isset($_GET['roro_lang'])) {
            $l = sanitize_text_field($_GET['roro_lang']);
        } elseif (isset($_COOKIE['roro_lang'])) {
            $l = sanitize_text_field($_COOKIE['roro_lang']);
        } else {
            $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
            if (strpos($locale, 'ja') === 0) $l = 'ja';
            elseif (strpos($locale, 'zh') === 0) $l = 'zh';
            elseif (strpos($locale, 'ko') === 0) $l = 'ko';
            else $l = 'en';
        }
        return in_array($l, ['ja','en','zh','ko'], true) ? $l : 'en';
    }

    public function load_lang($lang){
        $file = RORO_ADV_PATH . "lang/{$lang}.php";
        if (file_exists($file)) { require $file; if(isset($roro_adv_messages)) return $roro_adv_messages; }
        require RORO_ADV_PATH . "lang/en.php";
        return $roro_adv_messages;
    }

    /**
     * カテゴリ別 or 全体からランダム1件
     * - テーブル: RORO_ONE_POINT_ADVICE_MASTER (advice_text, category_code)
     * - 連携マスタ: RORO_CATEGORY_DATA_LINK_MASTERを使う場合は category_code 経由で絞り込み
     */
    public function get_random_advice($category=''){
        global $wpdb;
        $tbl = $wpdb->prefix . 'RORO_ONE_POINT_ADVICE_MASTER';
        if ($category) {
            $sql = $wpdb->prepare("SELECT advice_text FROM {$tbl} WHERE category_code=%s ORDER BY RAND() LIMIT 1", sanitize_text_field($category));
        } else {
            $sql = "SELECT advice_text FROM {$tbl} ORDER BY RAND() LIMIT 1";
        }
        $text = $wpdb->get_var($sql);
        return $text ? $text : null;
    }
}
