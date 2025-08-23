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

// 直アクセス防止（実行時セキュリティ）
defined('ABSPATH') || exit;

// ─────────────────────────────────────────────────────────────
// 定数定義（WP 関数に依存する値はここで define）
// ─────────────────────────────────────────────────────────────
if (!defined('RORO_CORE_WP_URL')) {
    // IDE 偽陽性は A/B/C のいずれかで解消（実行時は WP により定義済み）
    define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
}
if (!defined('RORO_CORE_WP_PATH')) {
    define('RORO_CORE_WP_PATH', plugin_dir_path(__FILE__));
}

// ─────────────────────────────────────────────────────────────
// ライフサイクルフック（有効化/無効化/アンインストール）
// ─────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, function (): void {
    // 既定オプションを作成（未作成の場合のみ）
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
    // ここにキャッシュ削除・一時ファイル削除などがあれば記載
});

register_uninstall_hook(__FILE__, function (): void {
    // プラグイン完全削除時にオプションを削除
    delete_option('roro_core_settings');
});

// ─────────────────────────────────────────────────────────────
// 初期化（CSS/JS を事前登録）
// ─────────────────────────────────────────────────────────────
add_action('init', function (): void {
    // 共通 CSS
    wp_register_style(
        'roro-core-style',
        RORO_CORE_WP_URL . 'assets/css/roro-core.css',
        [],
        '1.0.0'
    );

    // 共通 JS
    wp_register_script(
        'roro-core-main',
        RORO_CORE_WP_URL . 'assets/js/main.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // ページ別 JS（必要に応じて）
    wp_register_script('roro-core-login',     RORO_CORE_WP_URL . 'assets/js/login.js',     ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-signup',    RORO_CORE_WP_URL . 'assets/js/signup.js',    ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-profile',   RORO_CORE_WP_URL . 'assets/js/profile.js',   ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-magazine',  RORO_CORE_WP_URL . 'assets/js/magazine.js',  ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-map',       RORO_CORE_WP_URL . 'assets/js/map.js',       ['jquery'], '1.0.0', true);
    wp_register_script('roro-core-favorites', RORO_CORE_WP_URL . 'assets/js/favorites.js', ['jquery'], '1.0.0', true);
});

// ─────────────────────────────────────────────────────────────
// フロントエンド読込（実際に enqueue）
// ─────────────────────────────────────────────────────────────
add_action('wp_enqueue_scripts', function (): void {
    // 共通
    wp_enqueue_style('roro-core-style');
    wp_enqueue_script('roro-core-main');

    // 条件付き（固定ページスラッグ例）
    if (function_exists('is_page')) {
        if (is_page('login'))    { wp_enqueue_script('roro-core-login'); }
        if (is_page('signup'))   { wp_enqueue_script('roro-core-signup'); }
        if (is_page('profile'))  { wp_enqueue_script('roro-core-profile'); }
        if (is_page('magazine')) { wp_enqueue_script('roro-core-magazine'); }
        if (is_page('map'))      { wp_enqueue_script('roro-core-map'); }
        if (is_page('favorites')){ wp_enqueue_script('roro-core-favorites'); }
    }

    // JS へ安全に値を受け渡し
    wp_localize_script('roro-core-main', 'roroCoreConfig', [
        'restUrl'     => esc_url_raw(rest_url('roro/v1/')),
        'nonce'       => wp_create_nonce('wp_rest'),
        'homeUrl'     => home_url('/'),
        'isLoggedIn'  => is_user_logged_in(),
        'currentUser' => is_user_logged_in() ? get_current_user_id() : 0,
    ]);
});

// ─────────────────────────────────────────────────────────────
// ショートコード（アプリエントリを出力）
// ─────────────────────────────────────────────────────────────
add_shortcode('roro_core_app', function (): string {
    ob_start();
    // テンプレート（app-index.php は別途用意）
    include RORO_CORE_WP_PATH . 'templates/app-index.php';
    return ob_get_clean();
});
