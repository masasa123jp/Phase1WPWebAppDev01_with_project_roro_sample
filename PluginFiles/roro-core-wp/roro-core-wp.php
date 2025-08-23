<?php
/**
 * Plugin Name: RORO Core WP
 * Plugin URI: https://example.com/
 * Description: RORO プロジェクト用のコア機能を提供する WordPress プラグイン
 * Version: 1.0.0
 * Author: SAE Marketing One
 * Author URI: https://example.com/
 * License: GPL2
 * Text Domain: roro-core-wp
 */

declare(strict_types=1);

// WordPress 以外から直接呼び出された場合は終了する
defined('ABSPATH') || exit;

// プラグインのURLやパスを定数で定義（再利用のため）
if (!defined('RORO_CORE_WP_URL')) {
    define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
}
if (!defined('RORO_CORE_WP_PATH')) {
    define('RORO_CORE_WP_PATH', plugin_dir_path(__FILE__));
}

/**
 * プラグイン有効化時の処理
 * - デフォルトオプションを追加（存在しなければ）
 */
register_activation_hook(__FILE__, function (): void {
    if (get_option('roro_core_settings', null) === null) {
        add_option('roro_core_settings', [
            'ai_enabled'   => 0,     // AI機能の有効/無効
            'ai_base_url'  => '',    // AIサービスのBase URL
            'map_api_key'  => '',    // Google Maps API Key
            'public_pages' => [],    // 公開対象ページリスト
        ]);
    }
});

/**
 * プラグイン無効化時の処理
 * - 特に削除処理は行わない（no-op）
 */
register_deactivation_hook(__FILE__, function (): void {
    // 将来的にキャッシュ削除や一時ファイル削除を行う場合はここに記述
});

/**
 * プラグイン削除時の処理
 * - オプションを削除
 */
register_uninstall_hook(__FILE__, function (): void {
    delete_option('roro_core_settings');
});

/**
 * 初期化処理
 * - CSSやJSを事前登録
 */
add_action('init', function (): void {
    // 共通CSS
    wp_register_style(
        'roro-core-style',
        RORO_CORE_WP_URL . 'assets/css/roro-core.css',
        [],
        '1.0.0'
    );

    // 共通JS
    wp_register_script(
        'roro-core-main',
        RORO_CORE_WP_URL . 'assets/js/main.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // ページ別JSを必要に応じて登録
    wp_register_script('roro-core-login',     RORO_CORE_WP_URL . 'assets/js/login.js',     ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-signup',    RORO_CORE_WP_URL . 'assets/js/signup.js',    ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-profile',   RORO_CORE_WP_URL . 'assets/js/profile.js',   ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-magazine',  RORO_CORE_WP_URL . 'assets/js/magazine.js',  ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-map',       RORO_CORE_WP_URL . 'assets/js/map.js',       ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-favorites', RORO_CORE_WP_URL . 'assets/js/favorites.js', ['jquery'], '1.0.0', true);
});

/**
 * フロントエンドにスクリプト・スタイルを実際に読み込む
 */
add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style('roro-core-style');
    wp_enqueue_script('roro-core-main');

    // 現在のページ条件に応じて個別JSをロードする例
    if (is_page('login')) {
        wp_enqueue_script('roro-core-login');
    }
    if (is_page('signup')) {
        wp_enqueue_script('roro-core-signup');
    }
    if (is_page('profile')) {
        wp_enqueue_script('roro-core-profile');
    }
    if (is_page('magazine')) {
        wp_enqueue_script('roro-core-magazine');
    }
    if (is_page('map')) {
        wp_enqueue_script('roro-core-map');
    }
    if (is_page('favorites')) {
        wp_enqueue_script('roro-core-favorites');
    }

    // JS に PHP の値を渡す（REST API の URL など）
    wp_localize_script('roro-core-main', 'roroCoreConfig', [
        'restUrl'     => esc_url_raw(rest_url('roro/v1/')),
        'nonce'       => wp_create_nonce('wp_rest'),
        'homeUrl'     => home_url('/'),
        'isLoggedIn'  => is_user_logged_in(),
        'currentUser' => is_user_logged_in() ? get_current_user_id() : 0,
    ]);
});

/**
 * ショートコードの登録
 * - 各ページ用の表示用ショートコード
 */
add_shortcode('roro_core_app', function (): string {
    ob_start();
    include RORO_CORE_WP_PATH . 'templates/app-index.php';
    return ob_get_clean();
});
