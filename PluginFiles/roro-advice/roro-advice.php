<?php
/**
 * Plugin Name: RORO Advice
 * Description: Provides random one‑point advice about pets.  Offers both a simple shortcode for quick tips and a flexible API backed by a custom table.  The plugin supports multiple languages (Japanese, English, Chinese and Korean) and will gracefully fall back to built‑in messages when no database content is available.
 * Version: 2.0.0
 * Author: Roro Team
 * Text Domain: roro-advice
 * Domain Path: /lang
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'RORO_ADV_VERSION', '2.0.0' );
define( 'RORO_ADV_PATH', plugin_dir_path( __FILE__ ) );
define( 'RORO_ADV_URL',  plugin_dir_url( __FILE__ ) );

// Load the core classes used by this plugin.  The admin, REST and service
// modules are separated for clarity and can be extended independently.
require_once RORO_ADV_PATH . 'includes/class-roro-advice-service.php';
require_once RORO_ADV_PATH . 'includes/class-roro-advice-rest.php';
require_once RORO_ADV_PATH . 'includes/class-roro-advice-admin.php';
require_once RORO_ADV_PATH . 'includes/class-roro-advice.php';

// Initialise the plugin's translation files.  When the plugin is loaded the
// language files under the /lang directory will be made available to the
// WordPress i18n functions.
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'roro-advice', false, dirname( plugin_basename( __FILE__ ) ) . '/lang' );
} );

/**
 * Register the shortcodes provided by this plugin.
 *
 * Two shortcodes are available:
 *  - [roro_advice_random]: replicates the original plugin output with a
 *    heading and optional category attribute.  It will show a fallback
 *    “no advice” message when nothing is found.
 *  - [roro_advice]: a simplified shortcode that returns only the advice
 *    message without any surrounding markup.
 */
function roro_advice_register_shortcodes() {
    // [roro_advice_random category="..."]
    add_shortcode( 'roro_advice_random', function( $atts ) {
        $atts = shortcode_atts( [ 'category' => '' ], $atts, 'roro_advice_random' );
        $svc  = new RORO_Advice_Service();
        $lang = $svc->detect_lang();
        $messages = $svc->load_lang( $lang );
        $ad   = $svc->get_random_advice( $atts['category'], $lang );
        ob_start();
        ?>
        <div class="roro-adv">
          <div class="roro-adv-title"><?php echo esc_html( $messages['advice'] ); ?></div>
          <?php if ( ! $ad ) : ?>
            <div class="roro-adv-empty"><?php echo esc_html( $messages['no_advice'] ); ?></div>
          <?php else : ?>
            <div class="roro-adv-body"><?php echo esc_html( $ad ); ?></div>
          <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    } );

    // [roro_advice category="general"]
    add_shortcode( 'roro_advice', function( $atts ) {
        $atts = shortcode_atts( [ 'category' => 'general' ], $atts, 'roro_advice' );
        $svc  = new RORO_Advice_Service();
        $locale = substr( get_locale(), 0, 2 );
        $advice = $svc->get_random_advice( $atts['category'], $locale );
        return '<div class="roro-advice">' . esc_html( $advice ) . '</div>';
    } );
}
add_action( 'init', 'roro_advice_register_shortcodes' );

// Register REST routes when the REST API initialises.
add_action( 'rest_api_init', function() {
    ( new RORO_Advice_REST() )->register_routes();
} );

// Initialise the admin interface.  This provides a small UI for seeding
// categories and tags via the WordPress admin.  It will not load in the
// front end.
if ( is_admin() ) {
    Roro_Advice_Admin::init();
}

// Bootstrap the legacy class to provide backward compatibility for
// integrations that instantiate Roro_Advice_Plugin directly.  This class
// registers its own shortcode internally when run().
add_action( 'init', function() {
    if ( class_exists( 'Roro_Advice_Plugin' ) ) {
        $legacy = new Roro_Advice_Plugin();
        $legacy->run();
    }
} );
