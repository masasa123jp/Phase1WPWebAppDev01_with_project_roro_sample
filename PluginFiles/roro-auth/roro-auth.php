<?php
/**
 * Plugin Name: RORO Auth (MECE)
 * Description: Provides user authentication, registration, pet management and multilingual support.  Shortcodes render accessible login and sign‑up forms and REST endpoints handle the server side logic.  This MECE version reinstates missing features from the original RORO Auth plugin while remaining lean and self‑contained.
 * Version: 1.0.0
 * Author: Project RORO
 * Text Domain: roro-auth
 * Domain Path: /lang
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants to simplify path and URL access.
if (!defined('RORO_AUTH_VER')) {
    define('RORO_AUTH_VER', '1.0.0');
}
if (!defined('RORO_AUTH_DIR')) {
    define('RORO_AUTH_DIR', plugin_dir_path(__FILE__));
}
if (!defined('RORO_AUTH_URL')) {
    define('RORO_AUTH_URL', plugin_dir_url(__FILE__));
}

// Load core classes.  These files provide translation helpers, pet management,
// REST API endpoints, shortcode rendering and a simple social login stub.
require_once RORO_AUTH_DIR . 'includes/class-roro-auth-i18n.php';
require_once RORO_AUTH_DIR . 'includes/class-roro-auth-pets.php';
require_once RORO_AUTH_DIR . 'includes/class-roro-auth-rest.php';
require_once RORO_AUTH_DIR . 'includes/class-roro-auth-shortcodes.php';
require_once RORO_AUTH_DIR . 'includes/class-roro-auth-social.php';

/**
 * Initialise the plugin when all other plugins have loaded.
 *
 * We defer registration of REST routes and shortcodes until plugins_loaded
 * so that any dependencies (such as RORO Core) have a chance to load.
 */
add_action('plugins_loaded', function () {
    // Prime the translation arrays.  This call detects the current locale
    // and merges in the appropriate language file, falling back to English.
    Roro_Auth_I18n::load_messages();

    // Register REST endpoints.  This sets up login, registration, breed
    // lookup and pet management routes under the roro/v1 namespace.
    (new Roro_Auth_REST())->register_routes();

    // Register shortcodes for login, sign‑up and profile.  These
    // shortcodes also enqueue the necessary scripts and styles.
    Roro_Auth_Shortcodes::register();
});

/**
 * Activation hook.  Currently no migration is required but this hook
 * remains for future expansions (e.g. creating custom database tables).
 */
register_activation_hook(__FILE__, function () {
    // Reserved for future use.
});

/**
 * Deactivation hook.  No clean up is performed at present because
 * authentication cookies expire naturally and we do not store options.
 */
register_deactivation_hook(__FILE__, function () {
    // Reserved for future use.
});