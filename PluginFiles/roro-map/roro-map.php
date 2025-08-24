<?php
/**
 * Plugin Name: RORO Map (Events Extended)
 * Description: Google Maps 上にイベントを表示し、検索・カテゴリフィルタ・日付範囲・近傍検索を提供します。WP REST API 経由でデータを取得します。
 * Version: 1.1.0
 * Author: Project RORO
 * Text Domain: roro-map
 * Domain Path: /lang
 */
if (!defined('ABSPATH')) { exit; }

define('RORO_MAP_VERSION', '1.1.0');
define('RORO_MAP_PATH', plugin_dir_path(__FILE__));
define('RORO_MAP_URL',  plugin_dir_url(__FILE__));
// Google Maps APIキーは定数または管理画面オプションで設定可能
if (!defined('RORO_GOOGLE_MAPS_API_KEY')) {
    // ここに直接キーを定義するか、wp_optionsの 'roro_map_google_api_key' を使用します。
    define('RORO_GOOGLE_MAPS_API_KEY', '');
}

require_once RORO_MAP_PATH . 'includes/class-roro-events-service.php';
require_once RORO_MAP_PATH . 'includes/class-roro-events-rest.php';

register_activation_hook(__FILE__, function(){
    // 必要に応じてインデックス作成SQLを同梱（roro-assets-sql-manager で実行可能）
    // ここでは特別な処理は行いません。
});

add_action('plugins_loaded', function(){
    load_plugin_textdomain('roro-map', false, dirname(plugin_basename(__FILE__)) . '/lang');
});

// スクリプト／スタイル登録
add_action('wp_enqueue_scripts', function(){
    wp_register_style('roro-events', RORO_MAP_URL . 'assets/css/roro-events.css', [], RORO_MAP_VERSION);
    wp_register_script('roro-events', RORO_MAP_URL . 'assets/js/roro-events.js', [], RORO_MAP_VERSION, true);
});

/**
 * ショートコード: [roro_events_map]
 * - 検索フォーム＋地図＋一覧の複合ビューを出力
 */
add_shortcode('roro_events_map', function($atts){
    $svc = new RORO_Events_Service();
    $lang = $svc->detect_lang();
    $M    = $svc->load_lang($lang);

    // Google Maps API を読み込み
    $apiKey = defined('RORO_GOOGLE_MAPS_API_KEY') && RORO_GOOGLE_MAPS_API_KEY ? RORO_GOOGLE_MAPS_API_KEY : get_option('roro_map_google_api_key', '');
    if (!wp_script_is('google-maps', 'registered')) {
        wp_register_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($apiKey), [], null, true);
    }
    wp_enqueue_script('google-maps');
    wp_enqueue_style('roro-events');
    wp_enqueue_script('roro-events');

    // JSへ初期設定を渡す
    wp_localize_script('roro-events', 'RORO_EVENTS_CFG', [
        'restBase' => esc_url_raw( rest_url('roro/v1') ),
        'nonce'    => wp_create_nonce('wp_rest'),
        'i18n'     => $M,
        'maps'     => ['apiKey' => $apiKey],
        'defaults' => [
            'radiusKm' => 25,
        ]
    ]);

    ob_start();
    include RORO_MAP_PATH . 'templates/events-map.php';
    return ob_get_clean();
});

// REST ルート登録
add_action('rest_api_init', function(){
    (new RORO_Events_REST())->register_routes();
});
