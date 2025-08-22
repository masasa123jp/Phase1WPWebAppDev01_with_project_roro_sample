<?php
/**
 * Plugin Name: RORO Core WP
 * Plugin URI:  https://example.com/
 * Description: Core functionality for the RORO application. Provides database setup, API credential management, Google Maps integration and a simple recommendation framework.
 * Version:     1.0.0
 * Author:      RORO Project
 * Text Domain: roro-core-wp
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin constants.
 */
define( 'RORO_CORE_WP_VERSION', '1.0.0' );
define( 'RORO_CORE_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_CORE_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required classes.
require_once RORO_CORE_WP_PLUGIN_DIR . 'includes/class-roro-db.php';
require_once RORO_CORE_WP_PLUGIN_DIR . 'includes/class-roro-activator.php';
require_once RORO_CORE_WP_PLUGIN_DIR . 'includes/class-roro-admin.php';
require_once RORO_CORE_WP_PLUGIN_DIR . 'includes/class-roro-recommender.php';

/**
 * Retrieve a configuration value from a constant, environment variable or database option.
 *
 * This helper first checks whether a PHP constant of the given name exists and has
 * a non‑empty value. Next it attempts to read the value from the process environment
 * via getenv(). Finally it falls back to the options table using the lowercased
 * option key without the "RORO_" prefix (e.g. RORO_GOOGLE_MAPS_API_KEY → google_maps_api_key).
 *
 * @param string $name    Fully qualified option constant name (e.g. RORO_GOOGLE_MAPS_API_KEY).
 * @param mixed  $default Default value to return if no value is found.
 * @return mixed          Resolved value or default.
 */
function roro_core_wp_get_env( $name, $default = '' ) {
    // Constant defined in wp-config.php or elsewhere.
    if ( defined( $name ) && constant( $name ) ) {
        return constant( $name );
    }
    // Environment variable (for container deployments).
    $env = getenv( $name );
    if ( $env ) {
        return $env;
    }
    // Look up in plugin options.  Keys are stored in lower case without the RORO_ prefix.
    $options = get_option( 'roro_core_wp_settings', array() );
    $key     = strtolower( str_replace( 'RORO_', '', $name ) );
    if ( isset( $options[ $key ] ) && '' !== $options[ $key ] ) {
        return $options[ $key ];
    }
    return $default;
}

/**
 * Register activation and uninstall hooks.
 */
register_activation_hook( __FILE__, array( 'Roro_Core_Wp_Activator', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'Roro_Core_Wp_Activator', 'uninstall' ) );

/**
 * Register admin functionality.
 */
add_action( 'admin_menu', array( 'Roro_Core_Wp_Admin', 'add_admin_menu' ) );
add_action( 'admin_init', array( 'Roro_Core_Wp_Admin', 'register_settings' ) );

/**
 * Load plugin text domain for translations.
 */
add_action( 'init', function () {
    load_plugin_textdomain( 'roro-core-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Enqueue public scripts and styles.
 *
 * Registers and enqueues the plugin’s CSS and JavaScript for the front‑end.  The Google
 * Maps API key is passed to the map script via wp_localize_script().  Scripts are
 * registered here and can be safely enqueued when shortcodes are used.
 */
function roro_core_wp_enqueue_assets() {
    // Enqueue public stylesheet.
    wp_register_style(
        'roro-core-wp-public',
        RORO_CORE_WP_PLUGIN_URL . 'public/css/roro-public.css',
        array(),
        RORO_CORE_WP_VERSION
    );
    wp_enqueue_style( 'roro-core-wp-public' );

    // Enqueue public script.
    wp_register_script(
        'roro-core-wp-public',
        RORO_CORE_WP_PLUGIN_URL . 'public/js/roro-public.js',
        array( 'jquery' ),
        RORO_CORE_WP_VERSION,
        true
    );
    wp_enqueue_script( 'roro-core-wp-public' );

    // Enqueue Google Maps related script.
    wp_register_script(
        'roro-core-wp-map',
        RORO_CORE_WP_PLUGIN_URL . 'public/js/roro-map.js',
        array(),
        RORO_CORE_WP_VERSION,
        true
    );
    $api_key = roro_core_wp_get_env( 'RORO_GOOGLE_MAPS_API_KEY' );
    wp_localize_script( 'roro-core-wp-map', 'RORO_MAP_CFG', array( 'apiKey' => $api_key ) );
    wp_enqueue_script( 'roro-core-wp-map' );
}
add_action( 'wp_enqueue_scripts', 'roro_core_wp_enqueue_assets' );

/**
 * Shortcode to render a Google Map search box and map container.
 *
 * Usage: [roro_map]
 *
 * @param array  $atts    Shortcode attributes (unused).
 * @param string $content Enclosed content (unused).
 * @return string HTML output for the map and search form.
 */
function roro_core_wp_shortcode_map( $atts = array(), $content = '' ) {
    ob_start();
    ?>
    <div id="roro-map" style="width:100%;height:400px;"></div>
    <div class="roro-map-search">
        <input id="roro-map-search" type="text" placeholder="<?php echo esc_attr( __( 'Search places…', 'roro-core-wp' ) ); ?>" style="max-width:400px;width:100%;margin-top:8px;" />
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'roro_map', 'roro_core_wp_shortcode_map' );

/**
 * Shortcode to embed AI chat widget.
 *
 * This displays an iframe pointing at the configured Dify chat URL.  If no URL
 * has been set in the plugin settings or via a constant, nothing is rendered.
 *
 * Usage: [roro_chat]
 *
 * @param array  $atts    Shortcode attributes (unused).
 * @param string $content Enclosed content (unused).
 * @return string HTML output for the iframe or an empty string.
 */
function roro_core_wp_shortcode_chat( $atts = array(), $content = '' ) {
    $url = roro_core_wp_get_env( 'RORO_DIFY_CHAT_URL' );
    if ( empty( $url ) ) {
        return '';
    }
    return '<iframe src="' . esc_url( $url ) . '" width="100%" height="500" style="border:0;"></iframe>';
}
add_shortcode( 'roro_chat', 'roro_core_wp_shortcode_chat' );

/**
 * Shortcode to display personalised recommendations.
 *
 * In a real implementation this would generate suggestions based on the user’s
 * history and the CATEGORY_DATA_LINK and RORO_RECOMMENDATION_LOG tables.  For
 * now it returns a placeholder message via the recommender class.
 *
 * Usage: [roro_recommendation]
 *
 * @param array  $atts    Shortcode attributes.
 * @param string $content Enclosed content (unused).
 * @return string HTML output with recommendations.
 */
function roro_core_wp_shortcode_recommendation( $atts = array(), $content = '' ) {
    return Roro_Core_Wp_Recommender::shortcode_recommendation( $atts );
}
add_shortcode( 'roro_recommendation', 'roro_core_wp_shortcode_recommendation' );

// The end of roro-core-wp.php