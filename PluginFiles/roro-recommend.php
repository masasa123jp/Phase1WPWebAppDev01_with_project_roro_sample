<?php
/**
 * Plugin Name: RORO Recommend
 * Description: ユーザーごとの「今日のおすすめ」を生成し表示します（スポット＋ワンポイントアドバイス）。結果はログに保存されます。
 * Version: 1.1.0
 * Author: Project RORO
 * Text Domain: roro-recommend
 * Domain Path: /lang
 */
if (!defined('ABSPATH')) { exit; }

define('RORO_REC_VERSION', '1.1.0');
define('RORO_REC_PATH', plugin_dir_path(__FILE__));
define('RORO_REC_URL',  plugin_dir_url(__FILE__));

// プラグインの主要クラスを読み込む
require_once RORO_REC_PATH . 'includes/class-roro-recommend-service.php';
require_once RORO_REC_PATH . 'includes/class-roro-recommend-rest.php';
require_once RORO_REC_PATH . 'includes/class-roro-recommend-admin.php';

// プラグインのテキストドメインをロード（翻訳ファイルの読み込み）
add_action('plugins_loaded', function() {
    load_plugin_textdomain('roro-recommend', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

// ショートコード [roro_recommend] （エイリアス [roro_recommend_today]）を登録
add_shortcode('roro_recommend', 'roro_recommend_widget');
add_shortcode('roro_recommend_today', 'roro_recommend_widget');

/**
 * ショートコードコールバック関数: おすすめウィジェットを表示
 */
function roro_recommend_widget($atts = array()) {
    // 言語を検出し、対応するメッセージ配列をロード
    $service  = new RORO_Recommend_Service();
    $lang     = $service->detect_lang();
    $messages = $service->load_lang($lang);

    // フロント用スクリプトをエンキューし、グローバルJSオブジェクトに設定値を渡す
    wp_enqueue_script('roro-recommend-js', RORO_REC_URL . 'assets/js/recommend.js', array(), RORO_REC_VERSION, true);
    wp_localize_script('roro-recommend-js', 'roroRecommend', array(
        'restBase' => esc_url_raw(rest_url('roro/v1')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'lang'     => $lang,
        'i18n'     => $messages
    ));

    // テンプレートに渡すデータを用意し、出力をバッファリング
    $data = array(
        'messages' => $messages,
        'lang'     => $lang
    );
    ob_start();
    include RORO_REC_PATH . 'templates/recommend-widget.php';
    return ob_get_clean();
}

// REST APIルートを登録
add_action('rest_api_init', function() {
    $rest = new RORO_Recommend_REST();
    $rest->register_routes();
});

// 管理メニューにプラグイン項目を追加
add_action('admin_menu', function() {
    $admin = new RORO_Recommend_Admin();
    $admin->register_menu();
});

// プラグイン有効化フック: 必要なデータベーステーブルを作成
register_activation_hook(__FILE__, function() {
    $service = new RORO_Recommend_Service();
    $service->install();
    // ※初期データの投入は管理画面から手動実行できるため、自動では行わない
});
