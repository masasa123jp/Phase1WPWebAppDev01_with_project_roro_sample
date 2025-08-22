<?php
/**
 * Plugin Name: RORO Core WP
 * Description: Project RORO のコア機能（REST, 短コード, アセット読込 等）
 * Version:     0.1.0
 * Author:      RORO Team
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/** ===== 定数（WP関数依存は define() で） ===== */
if (!defined('RORO_CORE_WP_VER'))  define('RORO_CORE_WP_VER', '0.1.0');
if (!defined('RORO_CORE_WP_FILE')) define('RORO_CORE_WP_FILE', __FILE__);
if (!defined('RORO_CORE_WP_DIR'))  define('RORO_CORE_WP_DIR', __DIR__);
if (!defined('RORO_CORE_WP_URL'))  define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));

/** ===== 必要ファイル ===== */
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-roro-db.php';
require_once __DIR__ . '/includes/class-roro-rest.php';
require_once __DIR__ . '/includes/class-roro-admin.php';

/** ===== ライフサイクル ===== */
register_activation_hook(__FILE__, function (): void {
    // オプション初期化（存在しなければ）
    if (get_option('roro_core_settings', null) === null) {
        add_option('roro_core_settings', [
            'ai_enabled'   => 0,
            'ai_base_url'  => '',
            'map_api_key'  => '',
            'public_pages' => [],
        ]);
    }
});
register_deactivation_hook(__FILE__, function (): void {
    // 今回は特になし
});
register_uninstall_hook(__FILE__, function (): void {
    delete_option('roro_core_settings');
});

/** ===== アセット登録 ===== */
add_action('init', function (): void {
    // CSS
    wp_register_style('roro-core', RORO_CORE_WP_URL . 'assets/css/roro-core.css', [], RORO_CORE_WP_VER);

    // JS（フロントモジュールを WP 配布向けに）
    $deps = ['jquery']; // 余計な未登録依存を避ける
    wp_register_script('roro-main',         RORO_CORE_WP_URL . 'assets/js/main.js',          $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-lang',         RORO_CORE_WP_URL . 'assets/js/lang.js',          $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-login',        RORO_CORE_WP_URL . 'assets/js/login.js',         $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-signup',       RORO_CORE_WP_URL . 'assets/js/signup.js',        $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-profile',      RORO_CORE_WP_URL . 'assets/js/profile.js',       $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-magazine',     RORO_CORE_WP_URL . 'assets/js/magazine.js',      $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-map',          RORO_CORE_WP_URL . 'assets/js/map.js',           $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-favorites',    RORO_CORE_WP_URL . 'assets/js/favorites.js',     $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-dify-switch',  RORO_CORE_WP_URL . 'assets/js/dify-switch.js',   $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-dify-embed',   RORO_CORE_WP_URL . 'assets/js/dify-embed.js',    $deps, RORO_CORE_WP_VER, true);
    wp_register_script('roro-custom-chat',  RORO_CORE_WP_URL . 'assets/js/custom-chat-ui.js',$deps, RORO_CORE_WP_VER, true);

    // REST 環境情報を JS へ
    $restBase = esc_url_raw(rest_url('roro/v1'));
    $nonce    = wp_create_nonce('wp_rest');
    $home     = home_url('/');
    $opts     = (array) get_option('roro_core_settings', []);

    foreach ([
        'roro-main','roro-lang','roro-login','roro-signup','roro-profile','roro-magazine',
        'roro-map','roro-favorites','roro-dify-switch','roro-dify-embed','roro-custom-chat'
    ] as $handle) {
        wp_localize_script($handle, 'RORO_ENV', [
            'restBase'      => $restBase,
            'restNonce'     => $nonce,
            'homeUrl'       => $home,
            'opts'          => $opts,
            'isLoggedIn'    => is_user_logged_in(),
            'currentUserId' => is_user_logged_in() ? get_current_user_id() : 0,
        ]);
    }
});

/** ===== 基本アセットは常時読み込み ===== */
add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style('roro-core');
    wp_enqueue_script('roro-main');
    wp_enqueue_script('roro-lang');
});

/** ===== 短コード（JS を正しく enqueue してコンテナを出す） ===== */
add_shortcode('roro_login', function () {
    wp_enqueue_script('roro-login');
    return '<div id="roro-login"></div>';
});
add_shortcode('roro_signup', function () {
    wp_enqueue_script('roro-signup');
    return '<div id="roro-signup"></div>';
});
add_shortcode('roro_profile', function () {
    wp_enqueue_script('roro-profile');
    return '<div id="roro-profile"></div>';
});
add_shortcode('roro_magazine', function () {
    wp_enqueue_script('roro-magazine');
    return '<div id="roro-magazine"></div>';
});
add_shortcode('roro_map', function () {
    wp_enqueue_script('roro-map');
    return '<div id="roro-map"></div>';
});
add_shortcode('roro_favorites', function () {
    wp_enqueue_script('roro-favorites');
    return '<div id="roro-favorites"></div>';
});
add_shortcode('roro_ai_chat', function () {
    wp_enqueue_script('roro-custom-chat');
    return '<div id="roro-ai-chat"></div>';
});

/** ===== REST ルート／管理画面 ===== */
add_action('rest_api_init', [\Roro\Roro_REST::class, 'register_routes']);
add_action('admin_menu',    [\Roro\Roro_Admin::class, 'register_menu']);
