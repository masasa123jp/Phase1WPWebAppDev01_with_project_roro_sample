<?php
/**
 * Plugin Name: Roro Core WP (DB Installer included)
 * Description: Project Roro 用のDBスキーマと初期データを有効化時に投入。管理画面から再実行も可能。
 * Version: 1.0.1
 * Author: SAE Marketing One
 * Text Domain: roro-core-wp
 * Domain Path: /languages
 * License: GPLv2 or later
 */

// エントリーポイント保護
if (!defined('ABSPATH')) {
    exit;
}

// プラグインのベース定数
define('RORO_CORE_WP_VER', '1.0.1');
define('RORO_CORE_WP_DIR', plugin_dir_path(__FILE__));
define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
define('RORO_DB_SQL_DIR', RORO_CORE_WP_DIR . 'assets/sql/');
define('RORO_DB_LOG_DIR', WP_CONTENT_DIR . '/uploads/roro-core/logs/');
define('RORO_CORE_ASSETS_URL', RORO_CORE_WP_URL . 'assets/');

// クラス読み込み
require_once RORO_CORE_WP_DIR . 'includes/class-roro-db.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-activator.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-admin.php';

/*
 * プラグイン有効化フック
 * DBセットアップの成功/失敗をオプションに保存し、管理画面で通知できるようにする。
 */
register_activation_hook(__FILE__, function () {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-db.php';
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-activator.php';
    try {
        Roro_Activator::activate();
        update_option('roro_db_last_activation_status', 'ok', false);
    } catch (\Throwable $e) {
        update_option('roro_db_last_activation_status', 'ng:' . $e->getMessage(), false);
    }
});

// プラグインロード後の初期化
add_action('plugins_loaded', function () {
    // 管理画面でのみ管理メニューを初期化
    if (is_admin()) {
        Roro_Admin::init();
    }
});

/*
 * 有効化直後の状態を管理画面に通知。
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) {
        return;
    }
    $status = get_option('roro_db_last_activation_status');
    if (!$status) {
        return;
    }
    delete_option('roro_db_last_activation_status');
    if (strpos($status, 'ok') === 0) {
        echo '<div class="notice notice-success"><p>Roro Core WP: DBセットアップに成功しました。</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Roro Core WP: DBセットアップに失敗しました（' . esc_html($status) . '）。ログを確認してください。</p></div>';
    }
});