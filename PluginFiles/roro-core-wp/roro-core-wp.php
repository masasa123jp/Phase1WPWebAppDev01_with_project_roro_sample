<?php
/**
 * Plugin Name: RORO Core WP
 * Plugin URI:  https://example.com
 * Description: Base plugin providing common functionality for the RORO Web App. This plugin registers shared custom post types, settings pages, utilities, and enqueues shared assets. It also loads optional modules (magazine, social login) to support other plugins.
 * Version:     0.4.0
 * Author:      RORO Dev Team
 * Text Domain: roro-core-wp
 * Domain Path: /languages
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// -----------------------------------------------------------------------------
// Constants
// -----------------------------------------------------------------------------
if (!defined('RORO_CORE_WP_FILE')) {
    define('RORO_CORE_WP_FILE', __FILE__);
}
if (!defined('RORO_CORE_WP_DIR')) {
    define('RORO_CORE_WP_DIR', plugin_dir_path(__FILE__));
}
if (!defined('RORO_CORE_WP_URL')) {
    define('RORO_CORE_WP_URL', plugin_dir_url(__FILE__));
}
if (!defined('RORO_CORE_WP_VER')) {
    define('RORO_CORE_WP_VER', '0.4.0');
}

// -----------------------------------------------------------------------------
// Required modules
// -----------------------------------------------------------------------------
// Core classes provide CPT/Tax registration, asset registration and i18n utilities.
require_once RORO_CORE_WP_DIR . 'includes/class-roro-core.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-rest.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-shortcodes.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-admin-settings.php';

// Optional modules may be absent. Check existence before loading.
if (file_exists(RORO_CORE_WP_DIR . 'includes/class-roro-magazine.php')) {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-magazine.php';
}
if (file_exists(RORO_CORE_WP_DIR . 'includes/class-roro-social-login.php')) {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-social-login.php';
}

// -----------------------------------------------------------------------------
// Activation / Deactivation / Uninstall Hooks
// -----------------------------------------------------------------------------
/**
 * Perform setup tasks when the plugin is activated.
 *
 * - Set up default options for the core settings if they do not already exist.
 * - Register custom post types and taxonomies so rewrite rules are available immediately.
 * - Trigger activation routines for optional modules (e.g. magazine CPTs).
 */
register_activation_hook(__FILE__, static function (): void {
    // Initialise default core settings
    if (class_exists('RORO_Admin_Settings')) {
        $opt_name = RORO_Admin_Settings::OPTION;
        $defaults = RORO_Admin_Settings::defaults();
        if (get_option($opt_name, null) === null) {
            add_option($opt_name, $defaults);
        } else {
            $cur = get_option($opt_name);
            update_option($opt_name, array_replace($defaults, (array) $cur));
        }
    }

    // Register CPTs and taxonomies via core class
    if (class_exists('RORO_Core')) {
        RORO_Core::register_cpt_and_tax();
    }

    // Activate optional modules
    if (class_exists('RORO_Magazine')) {
        RORO_Magazine::activate();
    }

    // Flush rewrite rules to ensure pretty permalinks work for CPTs
    flush_rewrite_rules(false);
});

/**
 * Deactivation hook cleans up rewrite rules and delegates to optional modules.
 */
register_deactivation_hook(__FILE__, static function (): void {
    // Deactivate optional modules
    if (class_exists('RORO_Magazine')) {
        RORO_Magazine::deactivate();
    }
    // Only flush rewrite rules; we intentionally keep options so they persist across reactivations
    flush_rewrite_rules(false);
});

/**
 * Uninstall hook cleans up persisted options.
 */
register_uninstall_hook(__FILE__, static function (): void {
    // Remove core settings option
    delete_option('roro_core_settings');
    // Remove admin settings option if defined
    if (class_exists('RORO_Admin_Settings')) {
        delete_option(RORO_Admin_Settings::OPTION);
    }
});

// -----------------------------------------------------------------------------
// Initialisation Hooks
// -----------------------------------------------------------------------------
/**
 * Load the plugin text domain and initialise core functionality.
 *
 * We hook into plugins_loaded with priority 0 to ensure other plugins can override
 * translations if needed.
 */
add_action('plugins_loaded', static function (): void {
    // Load .mo files from the languages directory
    load_plugin_textdomain('roro-core-wp', false, dirname(plugin_basename(__FILE__)) . '/languages');

    // Initialise core classes
    if (class_exists('RORO_Core')) {
        RORO_Core::init();
    }
    if (class_exists('RORO_REST')) {
        RORO_REST::init();
    }
    if (class_exists('RORO_Shortcodes')) {
        RORO_Shortcodes::init();
    }
}, 0);

/**
 * Initialise admin and optional modules early in the init sequence.
 *
 * The priority of 5 ensures our admin settings are ready before other plugins
 * attempt to read them during init.
 */
add_action('init', static function (): void {
    if (class_exists('RORO_Admin_Settings')) {
        RORO_Admin_Settings::instance()->init();
    }
    if (class_exists('RORO_Magazine')) {
        RORO_Magazine::instance()->init();
    }
    if (class_exists('RORO_Social_Login')) {
        RORO_Social_Login::instance()->init();
    }
}, 5);