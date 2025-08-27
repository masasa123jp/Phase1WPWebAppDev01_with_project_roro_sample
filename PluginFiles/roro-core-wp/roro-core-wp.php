<?php
/**
 * Plugin Name: RORO Core WP
 * Plugin URI:  https://example.com/roro-core-wp
 * Description: RORO プロジェクトのコア機能（CPT/Tax、REST、ショートコード、管理設定、ツール、DB 初期化 等）
 * Version:     0.4.0
 * Author:      SAE Marketing One
 * License:     GPL-2.0+
 * Text Domain: roro-core-wp
 * Domain Path: /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
if (!defined('RORO_CORE_WP_FILE')) define('RORO_CORE_WP_FILE', __FILE__);
if (!defined('RORO_CORE_WP_DIR'))  define('RORO_CORE_WP_DIR', plugin_dir_path(__FILE__));
if (!defined('RORO_CORE_WP_URL'))  define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
if (!defined('RORO_CORE_WP_VER'))  define('RORO_CORE_WP_VER', '0.4.0');

// -----------------------------------------------------------------------------
// Required / Optional modules
// -----------------------------------------------------------------------------
require_once RORO_CORE_WP_DIR . 'includes/class-roro-core.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-rest.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-shortcodes.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-admin-settings.php';
if (file_exists(RORO_CORE_WP_DIR . 'includes/class-roro-magazine.php')) {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-magazine.php';
}
if (file_exists(RORO_CORE_WP_DIR . 'includes/class-roro-social-login.php')) {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-social-login.php';
}
if (file_exists(RORO_CORE_WP_DIR . 'admin/class-roro-admin-tools.php')) {
    require_once RORO_CORE_WP_DIR . 'admin/class-roro-admin-tools.php';
}
if (file_exists(RORO_CORE_WP_DIR . 'includes/class-roro-db.php')) {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-db.php';
}

// -----------------------------------------------------------------------------
// Activation / Deactivation / Uninstall
// -----------------------------------------------------------------------------
register_activation_hook(__FILE__, static function (): void {
    // 初期オプション
    if (class_exists('RORO_Admin_Settings')) {
        $opt      = RORO_Admin_Settings::OPTION;
        $defaults = RORO_Admin_Settings::defaults();
        $exists   = get_option($opt, null);
        if ($exists === null) {
            add_option($opt, $defaults, '', false);
        } else {
            // 既存値に不足キーを補完しつつ上書き（defaults を優先基準に array_replace）
            update_option($opt, array_replace($defaults, (array) $exists), false);
        }
    }

    // CPT/Tax 登録（初回有効化時の rewrite 生成のため）
    if (class_exists('RORO_Core')) {
        RORO_Core::register_cpt_and_tax();
    }

    // Optional modules の状態確認
    if (class_exists('RORO_Admin_Settings')) {
        $opts = get_option(RORO_Admin_Settings::OPTION, RORO_Admin_Settings::defaults());
    } else {
        $opts = [];
    }

    // 雑誌機能が有効なら必要なセットアップを実行
    if (!isset($opts['magazine_enable']) || (bool)$opts['magazine_enable']) {
        if (class_exists('RORO_Magazine')) {
            RORO_Magazine::activate();
        }
    }

    // パーマリンクを再生成（フラグ false = .htaccess を重く書き換えない）
    flush_rewrite_rules(false);
});

register_deactivation_hook(__FILE__, static function (): void {
    if (class_exists('RORO_Magazine')) {
        RORO_Magazine::deactivate();
    }
    flush_rewrite_rules(false);
});

// ★ Uninstall は Closure 禁止（WP がシリアライズするため）
register_uninstall_hook(__FILE__, 'roro_core_wp_uninstall');
function roro_core_wp_uninstall(): void {
    // 互換のため双方消す（過去版で 'roro_core_settings' を使っていた可能性）
    delete_option('roro_core_settings');
    if (class_exists('RORO_Admin_Settings')) {
        delete_option(RORO_Admin_Settings::OPTION);
    }
}

// -----------------------------------------------------------------------------
// Bootstrap
// -----------------------------------------------------------------------------
// 重要: 親メニュー（設定）をサブメニュー（Tools / DB）よりも先に登録させるため、
// `RORO_Admin_Settings::instance()->init()` を最初に呼ぶ。
// その後に Tools / DB 側の init を呼ぶことで、`admin_menu` 実行時に
// add_menu_page（親） → add_submenu_page（子）の順が保証される。
add_action('plugins_loaded', static function (): void {
    // 翻訳の読み込み
    load_plugin_textdomain('roro-core-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // 基本機能の初期化
    if (class_exists('RORO_Core'))       { RORO_Core::init(); }
    if (class_exists('RORO_REST'))       { RORO_REST::init(); }
    if (class_exists('RORO_Shortcodes')) { RORO_Shortcodes::init(); }

    // --- 管理系の初期化順序を明示的に制御 ---
    // 1) 親メニュー（設定）: 先に admin_menu へ登録されるよう、ここで init しておく
    if (is_admin() && class_exists('RORO_Admin_Settings')) {
        // 既存実装が admin_menu にハンドラを追加するなら、ここで登録される（=親が先）
        RORO_Admin_Settings::instance()->init();
    }

    // 2) サブメニュー: 親登録の後に init する
    if (is_admin() && class_exists('RORO_Admin_Tools')) {
        RORO_Admin_Tools::init();
    }
    if (is_admin() && class_exists('RORO_DB')) {
        RORO_DB::init();
    }
}, 0);

// Optional 機能は従来通り init フェーズで初期化（UI とは独立しているため順序影響が少ない）
add_action('init', static function (): void {
    // ※ Admin_Settings は plugins_loaded で init 済み（親メニュー優先のため）
    if (class_exists('RORO_Admin_Settings')) {
        // 必要であれば設定 API 登録などをここで追加する設計でも可
        // 既存クラスが二重で add_action 登録しない実装であることを前提に、
        // ここでは改めて呼ばない（重複回避）
        // RORO_Admin_Settings::instance()->init();
    }

    // オプション取得（雑誌機能の有効判定）
    if (class_exists('RORO_Admin_Settings')) {
        $opts = get_option(RORO_Admin_Settings::OPTION, RORO_Admin_Settings::defaults());
    } else {
        $opts = [];
    }

    // 雑誌機能
    if (!isset($opts['magazine_enable']) || (bool)$opts['magazine_enable']) {
        if (class_exists('RORO_Magazine')) {
            RORO_Magazine::instance()->init();
        }
    }

    // ソーシャルログイン
    if (class_exists('RORO_Social_Login')) {
        RORO_Social_Login::instance()->init();
    }
}, 5);
