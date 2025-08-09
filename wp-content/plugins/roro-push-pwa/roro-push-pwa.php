<?php
/**
 * Plugin Name: RoRo Push & PWA
 * Description: Unified plugin providing push notification token registration
 *              and Progressive Web App (PWA) support for the RoRo platform.  
 *              Handles Firebase Cloud Messaging subscription endpoints and
 *              serves a service worker script for offline support.  
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: roro-push-pwa
 */

defined( 'ABSPATH' ) || exit;

define( 'RORO_PUSH_PWA_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_PUSH_PWA_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes under the RoroPushPwa namespace.
spl_autoload_register( function ( $class ) {
    $prefix = 'RoroPushPwa\\';
    $base_dir = RORO_PUSH_PWA_DIR . 'includes/';
    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }
    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// Initialise push and PWA components.
function roro_push_pwa_init() {
    new RoroPushPwa\Push\Service();
    new RoroPushPwa\Pwa\Loader();
}
add_action( 'init', 'roro_push_pwa_init' );
