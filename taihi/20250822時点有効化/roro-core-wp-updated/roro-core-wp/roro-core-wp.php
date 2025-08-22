<?php
/**
 * Plugin Name:       Roro Core WP
 * Plugin URI:        https://example.com/roro-core-wp
 * Description:       Provides the core functionality for the Roro project.  This plugin
 *                    creates the required database tables on activation and exposes a
 *                    management interface in the WordPress dashboard.  It is designed
 *                    as the foundation upon which the other Roro modules are built.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Roro Team
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       roro-core-wp
 * Domain Path:       /languages
 *
 * @package RoroCoreWp
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'RORO_CORE_WP_VERSION', '1.0.0' );
define( 'RORO_CORE_WP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_CORE_WP_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-roro-activator.php.
 */
function activate_roro_core_wp() {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-activator.php';
    Roro_Core_Wp_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-roro-activator.php.
 */
function deactivate_roro_core_wp() {
    require_once RORO_CORE_WP_DIR . 'includes/class-roro-activator.php';
    Roro_Core_Wp_Activator::deactivate();
}

register_activation_hook( __FILE__, 'activate_roro_core_wp' );
register_deactivation_hook( __FILE__, 'deactivate_roro_core_wp' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once RORO_CORE_WP_DIR . 'includes/class-roro-admin.php';
require_once RORO_CORE_WP_DIR . 'includes/class-roro-db.php';

/**
 * -----------------------------------------------------------------------------
 * Image shortcode helper
 *
 * The following code registers a shortcode [roro_image] that outputs an
 * <img> tag referencing an image from the theme's `project-roro/assets/images`
 * directory by default.  If that directory doesn't exist the shortcode
 * falls back to the theme's root `assets/images` directory.  This allows
 * external images provided alongside the RoRo project to be displayed via a
 * simple shortcode in posts and pages.
 *
 * Usage: [roro_image file="filename.png" alt="Description" class="css-classes" width="300" height="200"]
 */
if ( ! function_exists( 'roro_core_wp_shortcode_image' ) ) {
    function roro_core_wp_shortcode_image( $atts = [] ) {
        $atts = shortcode_atts( [
            'file'   => '',
            'alt'    => '',
            'class'  => '',
            'width'  => '',
            'height' => '',
        ], $atts, 'roro_image' );
        if ( empty( $atts['file'] ) ) {
            return '';
        }
        $file = basename( $atts['file'] );
        // Determine image directory: use project‑roro if exists, else assets/images.
        $theme_dir = get_stylesheet_directory();
        $project_dir = $theme_dir . '/project-roro';
        if ( is_dir( $project_dir ) ) {
            $img_dir = $project_dir . '/assets/images';
            $img_url = get_stylesheet_directory_uri() . '/project-roro/assets/images/' . rawurlencode( $file );
        } else {
            $img_dir = $theme_dir . '/assets/images';
            $img_url = get_stylesheet_directory_uri() . '/assets/images/' . rawurlencode( $file );
        }
        if ( ! file_exists( $img_dir . '/' . $file ) ) {
            return '';
        }
        $class_attr  = $atts['class'] ? ' class="' . esc_attr( $atts['class'] ) . '"' : '';
        $width_attr  = $atts['width'] ? ' width="' . intval( $atts['width'] ) . '"' : '';
        $height_attr = $atts['height'] ? ' height="' . intval( $atts['height'] ) . '"' : '';
        return sprintf( '<img src="%s" alt="%s"%s%s%s />', esc_url( $img_url ), esc_attr( $atts['alt'] ), $class_attr, $width_attr, $height_attr );
    }
    add_shortcode( 'roro_image', 'roro_core_wp_shortcode_image' );
}

// Initialize the admin part of the plugin.
function roro_core_wp_run() {
    $plugin = new Roro_Core_Wp_Admin();
    $plugin->run();
}
add_action( 'plugins_loaded', 'roro_core_wp_run' );

/**
 * Clean up plugin data upon uninstallation.
 *
 * @return void
 */
function roro_core_wp_uninstall() {
    // Place cleanup tasks here. For example: drop custom tables or remove options.
}

register_uninstall_hook( __FILE__, 'roro_core_wp_uninstall' );