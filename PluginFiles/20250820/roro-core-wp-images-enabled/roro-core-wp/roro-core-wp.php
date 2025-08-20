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
define( 'RORO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_CORE_URL', plugin_dir_url( __FILE__ ) );
define( 'RORO_CORE_VERSION', '0.1.0' );

// 画像フォルダのURLを定数として定義。assets/images フォルダに画像を置くだけで利用可能。
define( 'RORO_CORE_IMAGES_URL', RORO_CORE_URL . 'assets/images' );

/**
 * 指定した画像ファイル名から URL を返します。
 *
 * @param string $filename 画像ファイル名（拡張子含む）。
 * @return string 画像のURL。
 */
function roro_core_image_url( $filename ) {
    return trailingslashit( RORO_CORE_IMAGES_URL ) . ltrim( $filename, '/\\' );
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
    new \RoroCore\Push\Service();
    // PWA ローダ。
    new \RoroCore\Pwa\Loader();
}
add_action( 'init', 'roro_core_init' );