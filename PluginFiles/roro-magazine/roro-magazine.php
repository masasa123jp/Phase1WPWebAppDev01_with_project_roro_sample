<?php
/**
 * Plugin Name: RORO Magazine
 * Description: Provides a monthly digital magazine experience for the RORO project.  
 *              It registers custom post types for magazine issues and articles,  
 *              offers a simple administration interface for managing multilingual  
 *              page and advertisement content, exposes a REST API for consuming  
 *              magazine data, and supplies user‑facing shortcodes for listing  
 *              issues, rendering a page turning viewer and displaying individual  
 *              articles.  
 * Version:     1.0.0
 * Author:      Project RORO
 * Text Domain: roro-magazine
 * Domain Path: /lang
 */

// Guard against direct access.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants for versioning and path helpers.
define('RORO_MAG_PLUGIN_VERSION', '1.0.0');
define('RORO_MAG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RORO_MAG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Pull in our class definitions.  Splitting responsibilities across
// separate files keeps the codebase maintainable and makes it clear
// which module does what.
require_once RORO_MAG_PLUGIN_DIR . 'includes/class-service.php';
require_once RORO_MAG_PLUGIN_DIR . 'includes/class-rest.php';
require_once RORO_MAG_PLUGIN_DIR . 'includes/class-admin.php';
require_once RORO_MAG_PLUGIN_DIR . 'includes/class-shortcodes.php';

// On activation we register our custom post types and flush rewrite
// rules so that pretty permalinks work immediately.  If the plugin is
// deactivated we simply flush the rules again.
register_activation_hook(__FILE__, function () {
    $svc = new RORO_Mag_Service();
    $svc->register_cpts();
    flush_rewrite_rules();
});
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});

// During init we register post types and load the plugin text domain
// so that any strings passed through WordPress' translation functions
// can be localised if a corresponding `.mo` file exists in the
// languages directory.  Note that the frontend messages used by this
// plugin are mostly provided via our own `lang/` PHP files.
add_action('init', function () {
    $svc = new RORO_Mag_Service();
    $svc->register_cpts();
    // Load .mo files for backwards compatibility with WordPress l10n.
    load_plugin_textdomain('roro-magazine', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Register our REST endpoints once the REST API is ready.
add_action('rest_api_init', function () {
    $rest = new RORO_Mag_Rest();
    $rest->register_routes();
});

// Hook our admin UI.  The static methods in the admin class manage
// registering meta boxes and saving the associated post metadata.  See
// includes/class-admin.php for implementation details.
add_action('add_meta_boxes', [ 'RORO_Mag_Admin', 'add_boxes' ]);
add_action('save_post', [ 'RORO_Mag_Admin', 'save' ], 10, 2);

// Register our front end assets.  Shortcodes will enqueue the
// appropriate styles and scripts on demand but registering here
// centralises version numbers and file locations.  See
// includes/class-shortcodes.php for details.
add_action('wp_enqueue_scripts', [ 'RORO_Mag_Shortcodes', 'register_assets' ]);

// Initialise our shortcodes.  Each shortcode is bound to a
// corresponding static method in RORO_Mag_Shortcodes.  Calling
// init() here ensures the shortcodes are available as soon as
// WordPress finishes loading plugins.
RORO_Mag_Shortcodes::init();