<?php
/**
 * Plugin Name:       Roro Favorites
 * Plugin URI:        https://example.com/roro-favorites
 * Description:       Provides a favourites system for Roro content. Users can save
 *                    and remove favourite items via AJAX and view their saved
 *                    items via a shortcode. This skeleton implementation defines
 *                    the necessary hooks and endpoints but leaves the persistence
 *                    details for you to implement.
 * Version:           1.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Roro Team
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       roro-favorites
 * Domain Path:       /languages
 *
 * @package RoroFavorites
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RORO_FAVORITES_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_FAVORITES_URL', plugin_dir_url( __FILE__ ) );

require_once RORO_FAVORITES_DIR . 'includes/class-roro-favorites.php';

/**
 * Perform setup tasks when the plugin is activated.
 *
 * Creates a custom table for storing favourites across different item types (events, spots, etc.).
 */
function roro_favorites_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table           = $wpdb->prefix . 'roro_favorites';
    $sql             = "CREATE TABLE $table (\n"
                    . "id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                    . "user_id BIGINT(20) UNSIGNED NOT NULL,\n"
                    . "item_id BIGINT(20) UNSIGNED NOT NULL,\n"
                    . "item_type VARCHAR(50) NOT NULL,\n"
                    . "created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,\n"
                    . "PRIMARY KEY  (id),\n"
                    . "KEY user_item (user_id, item_id, item_type)\n"
                    . ") $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

// Register activation hook for favourites table creation.
register_activation_hook( __FILE__, 'roro_favorites_activate' );

function roro_favorites_run() {
    $fav = new Roro_Favorites_Plugin();
    $fav->run();
}
add_action( 'plugins_loaded', 'roro_favorites_run' );