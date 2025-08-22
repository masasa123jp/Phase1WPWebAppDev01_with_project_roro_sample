<?php
/**
 * Module path: wp-content/plugins/roro-core/roro-core.php
 *
 * Plugin Name: RoRo Core WP (Full Asset)
 * Description: RoRo プラットフォームの中核となる機能を提供します。REST API、認証、多言語設定、通知、管理画面などを包括。
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: roro-core-wp
 *
 * @package RoroCore
 */

defined( 'ABSPATH' ) || exit;

define( 'RORO_CORE_VERSION', '1.0.0' );
define( 'RORO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_CORE_URL', plugin_dir_url( __FILE__ ) );

// -----------------------------------------------------------------------------
// Asset helpers
//
// Load helper functions that live outside of the RoroCore\ namespace.  These
// functions cannot be autoloaded via PSR‑4 because they reside in the
// global namespace.  Including this file here makes functions like
// `roro_core_image_url()` available to all other components (e.g. shortcodes
// and templates) without requiring each consumer to include the file
// themselves.  See `includes/helpers-assets.php` for implementation details.
require_once RORO_CORE_DIR . 'includes/helpers-assets.php';

/**
 * 翻訳ファイルを読み込む。
 */
function roro_core_load_textdomain() {
    load_plugin_textdomain( 'roro-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'roro_core_load_textdomain' );

/**
     * PSR-4対応オートローダ。名称不一致の場合は小文字ファイルを探索します。
     */
spl_autoload_register( function ( $class ) {
    $prefix   = 'RoroCore\\';
    $base_dir = RORO_CORE_DIR . 'includes/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative_class = substr( $class, $len );
    $path           = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );
    // CamelCase ファイルを先に探し、存在しなければ小文字へフォールバック
    $file       = $base_dir . $path . '.php';
    $lower_file = $base_dir . strtolower( $path ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
        return;
    }
    if ( file_exists( $lower_file ) ) {
        require_once $lower_file;
    }
} );

/**
 * プラグイン初期化。
 */
function roro_core_init() {
    // 認証サービス
    new \RoroCore\Auth\Auth_Service();

    // 既存APIエンドポイント
    new \RoroCore\Api\Gacha_Endpoint();
    new \RoroCore\Api\Facility_Search_Endpoint();
    new \RoroCore\Api\Review_Endpoint();
    new \RoroCore\Api\Photo_Upload_Endpoint();
    new \RoroCore\Api\Ai_Advice_Endpoint();
    new \RoroCore\Api\User_Profile_Endpoint();
    new \RoroCore\Api\Analytics_Endpoint();

    // 新規・拡張エンドポイント
    new \RoroCore\Api\Breed_List_Endpoint();
    new \RoroCore\Api\Issues_Endpoint();
    new \RoroCore\Api\Report_Analysis_Endpoint();
    new \RoroCore\Api\Report_Email_Endpoint();
    new \RoroCore\Api\Sponsor_List_Endpoint();
    new \RoroCore\Api\Sponsor_Detail_Endpoint();
    new \RoroCore\Api\Ad_Approval_Endpoint();
    new \RoroCore\Api\Payment_Endpoint();
    new \RoroCore\Api\Facility_DB_Endpoint();
    new \RoroCore\Api\Contact_Endpoint();
    new \RoroCore\Api\Most_Used_Place_Endpoint();
    new \RoroCore\Api\Repeat_Usage_Endpoint();
    new \RoroCore\Api\Flow_Analysis_Endpoint();
    new \RoroCore\Api\Ad_Access_Analysis_Endpoint();
    new \RoroCore\Api\Download_Data_Endpoint();

    // Phase1.5/1.6用集約エンドポイント
    new \RoroCore\Api\Breed_Stats_Endpoint( $GLOBALS['wpdb'] );
    new \RoroCore\Api\Dashboard_Endpoint( $GLOBALS['wpdb'] );
    new \RoroCore\Api\Geocode_Endpoint();
    new \RoroCore\Api\Preference_Endpoint();
    new \RoroCore\Api\Report_Endpoint( $GLOBALS['wpdb'] );

    // 設定・ロケール・多言語
    \RoroCore\Settings\Language_Settings::init();
    \RoroCore\Settings\General_Settings::init();
    \RoroCore\Locale\User_Locale_Manager::init();

    // 管理メニュー
    if ( is_admin() ) {
        new \RoroCore\Admin\Menu();
    }

    // 通知サービス
    new \RoroCore\Notifications\Notification_Service();

    // --- PWA & push services integration ---
    // Include the push notification token service and PWA loader.  These
    // classes live under the RoroCore\Push and RoroCore\Pwa namespaces,
    // respectively.  Instantiating them here ensures the REST route
    // and service worker scripts are registered when the core plugin
    // initialises.
    new \RoroCore\Push\Service();
    new \RoroCore\Pwa\Loader();

    // --- Image shortcode integration ---
    // Register the [roro_image] shortcode that outputs an <img> tag for
    // images stored under assets/images.  The class lives under
    // RoroCore\Shortcode and is autoloaded via PSR‑4.  Instantiating it here
    // ensures the shortcode is registered during plugin initialization.
    new \RoroCore\Shortcode\ImageShortcode();

    // カスタム投稿タイプを登録
    // 写真アップロード用 (roro_photo) とアドバイス記事用 (roro_advice) の2種類を1箇所で管理します。
    \RoroCore\Post_Types::register();
}
add_action( 'init', 'roro_core_init' );
