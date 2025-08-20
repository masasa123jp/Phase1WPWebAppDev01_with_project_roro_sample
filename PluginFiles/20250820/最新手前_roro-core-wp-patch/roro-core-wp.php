<?php
/**
 * Plugin Name: RoRo Core WP (Light)
 * Description: 簡易版のRoRo Core プラグイン。PWA/Push機能と画像ヘルパー関数を含みます。
 * Version: 0.1.0
 * Author: Your Name
 * Text Domain: roro-core-wp
 */

defined( 'ABSPATH' ) || exit;

// プラグインの基本定数。
// ルートディレクトリやURL、バージョン番号等の固定値を定義します。定数名が重複しないように接頭辞を付けています。
define( 'RORO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'RORO_CORE_PLUGIN_FILE', __FILE__ );
define( 'RORO_CORE_VERSION', '1.0.0' );

// 画像フォルダのURLを定義します。assets/images ディレクトリに配置した画像を参照する際に使用します。
define( 'RORO_CORE_IMAGES_URL', trailingslashit( RORO_CORE_URL . 'assets/images' ) );

/**
 * 指定した画像ファイル名から URL を返します。
 *
 * @param string $filename 画像ファイル名（拡張子含む）。
 * @return string 画像のURL。
 */
function roro_core_image_url( $filename ) {
    $filename = ltrim( (string) $filename, '/\\' );
    return RORO_CORE_IMAGES_URL . $filename;
}

// オートローダ: 名前空間 RoroCore\ に応じて includes ディレクトリから読み込みます。
spl_autoload_register( function ( $class ) {
    $prefix   = 'RoroCore\\';
    $base_dir = RORO_CORE_DIR . 'includes/';
    $len      = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative_class = substr( $class, $len );
    $file           = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * プラグイン初期化。
 */
function roro_core_init() {
    // Push トークン登録サービス。
    if ( class_exists( '\\RoroCore\\Push\\Service' ) ) {
        new \RoroCore\Push\Service();
    }
    // PWA ローダ。
    if ( class_exists( '\\RoroCore\\Pwa\\Loader' ) ) {
        new \RoroCore\Pwa\Loader();
    }
    // 画像ショートコード登録。
    if ( class_exists( '\\RoroCore\\Shortcode\\ImageShortcode' ) ) {
        new \RoroCore\Shortcode\ImageShortcode();
    }
}
add_action( 'init', 'roro_core_init' );