<?php
/**
 * Plugin Name: RORO Auth
 * Description: RORO Core に依存する認証拡張（ログイン時のワンタイムパスコード（OTP）/2FA と管理UIを追加）
 * Version: 1.0.0
 * Author: SAE Marketing One / RORO Team
 * Text Domain: roro-auth
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // 直アクセス防止
}

/**
 * 定数定義
 * - プラグインのバージョン、ディレクトリ/URL定数を定義
 */
if (!defined('RORO_AUTH_VERSION')) {
    define('RORO_AUTH_VERSION', '1.0.0');
}
if (!defined('RORO_AUTH_DIR')) {
    define('RORO_AUTH_DIR', plugin_dir_path(__FILE__));
}
if (!defined('RORO_AUTH_URL')) {
    define('RORO_AUTH_URL', plugin_dir_url(__FILE__));
}

/**
 * 有効化フック
 * - 依存プラグイン（roro-core / roro-core-wp）が有効かを確認
 * - いずれも無効の場合は本プラグインの有効化を中止し、管理者に通知
 */
register_activation_hook(__FILE__, function () {
    if (!function_exists('is_plugin_active')) {
        // 有効化時はこのファイルが読み込まれていない可能性があるため明示的に読み込む
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    // roro-core / roro-core-wp の2系統を許容（いずれか有効ならOK）
    $coreActive = is_plugin_active('roro-core/roro-core.php') || is_plugin_active('roro-core-wp/roro-core-wp.php');

    // すでに読み込み済みなら定数の存在でも判断可能
    if (!$coreActive && !defined('RORO_CORE_WP_DIR')) {
        // 自分を無効化しつつエラーメッセージ表示
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            '「RORO Auth」を有効化するには「RORO Core」プラグインが必要です。<br>先に RORO Core を有効化してください。',
            'RORO Auth: 依存プラグイン未検出',
            ['back_link' => true]
        );
    }
});

/**
 * plugins_loaded
 * - 翻訳読み込み
 * - 依存チェック（コア不在時は管理画面に警告表示し、以降の初期化はスキップ）
 * - クラスファイル読み込みと初期化
 */
add_action('plugins_loaded', function () {

    // 翻訳（/languages フォルダに .mo を配置した場合）
    load_plugin_textdomain('roro-auth', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // 依存チェック：roro-core 側の定数やユーティリティクラスの存在確認
    $core_loaded = defined('RORO_CORE_WP_DIR'); // 最低限の確認（定数）
    if (!$core_loaded) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>RORO Auth は RORO Core が有効でないため初期化できません。RORO Core を先に有効化してください。</p></div>';
        });
        return; // 初期化中止
    }

    // 本プラグインのクラス群を読み込み
    require_once RORO_AUTH_DIR . 'includes/class-roro-auth.php';
    require_once RORO_AUTH_DIR . 'includes/class-roro-auth-admin.php';
    require_once RORO_AUTH_DIR . 'includes/class-roro-auth-login.php';

    // メインクラス初期化（シングルトン）
    RORO_Auth::instance();
}, 10);
