<?php
/**
 * Plugin Name: Roro Core WP (DB Installer included)
 * Description: Project Roro 用のDBスキーマと初期データを有効化時に投入。管理画面から再実行も可能。
 * Version: 1.0.0
 * Author: SAE Marketing One
 * License: GPLv2 or later
 */

// エントリーポイント保護
if (!defined('ABSPATH')) {
    exit;
}

// プラグインのベース定数
define('RORO_CORE_WP_VER', '1.0.0');
define('RORO_CORE_WP_DIR', plugin_dir_path(__FILE__));
define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
define('RORO_DB_SQL_DIR', RORO_CORE_WP_DIR . 'assets/sql/');
define('RORO_DB_LOG_DIR', WP_CONTENT_DIR . '/uploads/roro-core/logs/');

// クラス読み込み
require_once RORO_CORE_WP_DIR . 'includes/class-roro-db.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-activator.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-admin.php';

// プラグイン有効化フック
register_activation_hook(__FILE__, ['Roro_Activator', 'activate']);

// プラグインロード後の初期化
add_action('plugins_loaded', function () {
    // 管理画面でのみ管理メニューを初期化
    if (is_admin()) {
        Roro_Admin::init();
    }
});