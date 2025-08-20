<?php
/**
 * Plugin Name: RORO Core Plugin
 * Plugin URI:  https://example.com/
 * Description: Provides pet-related database tables, settings and seed data for the RORO web application.  On activation the plugin creates necessary tables and optionally loads initial data.  Administrators can configure external API keys (Google Maps, Dify, social logins) via a settings page.
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: roro-plugin
 * Domain Path: /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants for version and directories.
define( 'RORO_PLUGIN_VERSION', '1.0.0' );
define( 'RORO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load translations.
function roro_load_textdomain() {
    load_plugin_textdomain( 'roro-plugin', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'roro_load_textdomain' );

// Include class files.
require_once RORO_PLUGIN_DIR . 'includes/class-roro-plugin-activator.php';
require_once RORO_PLUGIN_DIR . 'includes/class-roro-plugin-admin.php';

// Activation hook.
register_activation_hook( __FILE__, array( 'Roro_Plugin_Activator', 'activate' ) );

// Admin menus and settings.
add_action( 'admin_menu', array( 'Roro_Plugin_Admin', 'add_admin_menu' ) );
add_action( 'admin_init', array( 'Roro_Plugin_Admin', 'register_settings' ) );

// Future enhancements (e.g. shortcodes, widgets) can be added here.