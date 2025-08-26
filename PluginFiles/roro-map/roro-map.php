<?php
/**
 * Plugin Name: RORO Map
 * Description: Visualise events on a Google map with search, date range and category filters.  This plugin registers a custom post type for events, exposes a REST API for retrieving event data and renders an interactive map via a shortcode.  All user‑facing strings are fully localised and a simple settings page allows you to specify your Google Maps API key.
 * Version: 2.0.0
 * Author: Project RORO
 * Text Domain: roro-map
 * Domain Path: /languages
 */

// Abort if accessed directly.
defined( 'ABSPATH' ) || exit;

// Plugin constants.  These make it easier to refer to files and URLs elsewhere.
define( 'RORO_MAP_VERSION', '2.0.0' );
define( 'RORO_MAP_PATH', plugin_dir_path( __FILE__ ) );
define( 'RORO_MAP_URL',  plugin_dir_url( __FILE__ ) );

// Google Maps API key can be defined in wp-config.php or set via an option.
if ( ! defined( 'RORO_GOOGLE_MAPS_API_KEY' ) ) {
    /**
     * Leave this constant blank by default.  Site owners can either define
     * RORO_GOOGLE_MAPS_API_KEY in wp-config.php or set the 'roro_map_google_api_key'
     * option in the WordPress admin area.
     */
    define( 'RORO_GOOGLE_MAPS_API_KEY', '' );
}

// Include our class definitions.  Keeping functionality in separate classes
// makes the plugin easier to maintain and test.  Each class deals with a
// discrete responsibility (CPT registration, data access, REST endpoints, admin UI).
require_once RORO_MAP_PATH . 'includes/class-roro-map-post-type.php';
require_once RORO_MAP_PATH . 'includes/class-roro-map-service.php';
require_once RORO_MAP_PATH . 'includes/class-roro-map-rest.php';
require_once RORO_MAP_PATH . 'includes/class-roro-map-admin.php';

/**
 * Load the plugin's translated strings.  WordPress will automatically pick
 * up compiled .mo files from the languages directory.  See the /lang folder
 * for the JavaScript translations used by the front‑end map.
 */
add_action( 'plugins_loaded', function() {
    load_plugin_textdomain( 'roro-map', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

/**
 * Register the custom post type and its meta.  Hook into init so that other
 * plugins or themes can modify the registration if needed.
 */
add_action( 'init', [ 'Roro_Map_Post_Type', 'register' ] );

/**
 * Register our REST API endpoints when the REST API initialises.  These
 * endpoints expose event and category data to the front‑end JavaScript.
 */
add_action( 'rest_api_init', [ 'Roro_Map_Rest', 'register_routes' ] );

/**
 * Enqueue the front‑end styles and scripts.  This uses the enqueue method
 * defined on the post type class so that it can register its own handles.
 */
add_action( 'wp_enqueue_scripts', function() {
    Roro_Map_Post_Type::enqueue_assets();
} );

/**
 * Shortcode to display the event map.  Use [roro_events_map] in a post or
 * page to output the map, search controls and results list.  This function
 * also localises configuration data to the JavaScript so that it knows
 * where to fetch data from and what language to display.
 *
 * @param array $atts Unused shortcode attributes.
 * @return string HTML for the map interface.
 */
add_shortcode( 'roro_events_map', function( $atts = [] ) {
    // Instantiate the service to detect the current language and load
    // translation strings for the front‑end script.
    $svc  = new Roro_Map_Service();
    $lang = $svc->detect_lang();
    $i18n = $svc->load_lang( $lang );

    // Determine the API key.  Prefer a constant defined in wp-config.php
    // but fall back to a saved option.  This allows non‑technical users
    // to configure the key without editing code.
    $api_key = defined( 'RORO_GOOGLE_MAPS_API_KEY' ) && RORO_GOOGLE_MAPS_API_KEY
        ? RORO_GOOGLE_MAPS_API_KEY
        : get_option( 'roro_map_google_api_key', '' );

    // Register and enqueue the Google Maps script only once.  The key is
    // embedded in the URL and will be blank if none is configured.  If
    // multiple plugins register this handle it will only be loaded once.
    if ( ! wp_script_is( 'google-maps', 'registered' ) ) {
        wp_register_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . urlencode( $api_key ), [], null, true );
    }
    wp_enqueue_script( 'google-maps' );

    // Enqueue our own assets.  The map script depends on google-maps.
    wp_enqueue_style( 'roro-map' );
    wp_enqueue_script( 'roro-map' );

    // Pass configuration and translations to the front‑end script.  The
    // 'restBase' property tells the script where to find our endpoints.
    // The 'i18n' object provides translated labels for buttons and messages.
    wp_localize_script( 'roro-map', 'RORO_EVENTS_CFG', [
        'restBase' => esc_url_raw( rest_url( 'roro-map/v1' ) ),
        'nonce'    => wp_create_nonce( 'wp_rest' ),
        'i18n'     => $i18n,
        'maps'     => [ 'apiKey' => $api_key ],
        'defaults' => [ 'radiusKm' => 25 ],
    ] );

    // Capture the output of the template.  The template uses the
    // translations loaded above and renders the map container and controls.
    ob_start();
    include RORO_MAP_PATH . 'templates/events-map.php';
    return ob_get_clean();
} );