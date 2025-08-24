<?php
/**
 * Plugin Name: RORO Core WP
 * Plugin URI:  https://example.com/roro
 * Description: RORO Web App core plugin (Magazine / Map / Favorites / AI hooks).
 * Version:     0.2.0
 * Author:      RORO Dev Team
 * Text Domain: roro-core-wp
 * Domain Path: /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// 定数
// -----------------------------------------------------------------------------
if (!defined('RORO_CORE_WP_FILE'))  define('RORO_CORE_WP_FILE', __FILE__);
if (!defined('RORO_CORE_WP_DIR'))   define('RORO_CORE_WP_DIR', plugin_dir_path(__FILE__));
if (!defined('RORO_CORE_WP_URL'))   define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
if (!defined('RORO_CORE_WP_VER'))   define('RORO_CORE_WP_VER', '0.2.0');

// -----------------------------------------------------------------------------
// 必要ファイル読み込み
// -----------------------------------------------------------------------------
require_once RORO_CORE_WP_DIR . 'includes/class-roro-core.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-rest.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-shortcodes.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-settings.php';

require_once RORO_CORE_WP_DIR . 'includes/class-roro-admin-settings.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-magazine.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-social-login.php';

// -----------------------------------------------------------------------------
// 有効化 / 無効化 / アンインストール
// -----------------------------------------------------------------------------
register_activation_hook(__FILE__, static function (): void {
    // デフォルト設定（AI / MAP APIなど）
    $defaults = [
        'ai_enabled'        => 0,
        'ai_provider'       => 'none', // 'openai'|'dify' 等
        'ai_base_url'       => '',
        'ai_api_key'        => '',
        'map_api_key'       => '',
        'supported_locales' => ['ja', 'en', 'zh', 'ko'],
        'public_pages'      => [],
    ];
    if (get_option('roro_core_settings', null) === null) {
        add_option('roro_core_settings', $defaults);
    } else {
        $cur = get_option('roro_core_settings');
        update_option('roro_core_settings', array_replace($defaults, (array)$cur));
    }

    // 管理用設定
    $opt = get_option(\RORO_Admin_Settings::OPTION, null);
    if ($opt === null) {
        add_option(\RORO_Admin_Settings::OPTION, \RORO_Admin_Settings::defaults());
    }

    // CPT 登録 & リライトルール
    RORO_Core::register_cpt_and_tax();
    \RORO_Magazine::activate();
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, static function (): void {
    // リライトルールのみクリア
    \RORO_Magazine::deactivate();
    flush_rewrite_rules(false);
});

register_uninstall_hook(__FILE__, static function (): void {
    // 設定削除（運用方針に応じて調整）
    delete_option('roro_core_settings');
    delete_option(\RORO_Admin_Settings::OPTION);
});

// -----------------------------------------------------------------------------
// 初期化フック
// -----------------------------------------------------------------------------
add_action('plugins_loaded', static function (): void {
    // 多言語
    load_plugin_textdomain('roro-core-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // コア機能
    RORO_Core::init();
    RORO_REST::init();
    RORO_Shortcodes::init();
    RORO_Settings::init();
});

add_action('init', static function (): void {
    \RORO_Admin_Settings::instance()->init();
    \RORO_Magazine::instance()->init();
    \RORO_Social_Login::instance()->init();
}, 5);

// -----------------------------------------------------------------------------
// フロントエンド用スクリプト
// -----------------------------------------------------------------------------
add_action('wp_enqueue_scripts', static function (): void {
    wp_register_script(
        'roro-core-runtime',
        RORO_CORE_WP_URL . 'assets/js/runtime.js',
        [],
        RORO_CORE_WP_VER,
        true
    );

    wp_localize_script('roro-core-runtime', 'RORO_CORE', [
        'restBase' => esc_url_raw(rest_url('roro/v1/')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'home'     => esc_url_raw(home_url('/')),
        'lang'     => determine_locale(),
    ]);

    wp_enqueue_script('roro-core-runtime');
}, 20);
