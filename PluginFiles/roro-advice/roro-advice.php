<?php
/**
 * Plugin Name:       Roro One‑Point Advice
 * Plugin URI:        https://example.com/roro-advice
 * Description:       Displays a random piece of advice from the Roro advice table.  If no
 *                    custom data exists a set of default messages will be used. A
 *                    shortcode is provided to embed the advice in posts or widgets.
 * Version:           1.5.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Roro Team
 * Author URI:        https://example.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       roro-advice
 * Domain Path:       /languages
 *
 * @package RoroAdvice
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RORO_ADVICE_DIR', plugin_dir_path( __FILE__ ) );
define( 'RORO_ADVICE_URL', plugin_dir_url( __FILE__ ) );

require_once RORO_ADVICE_DIR . 'includes/class-roro-advice.php';

/**
 * Perform setup tasks when the plugin is activated.
 *
 * Creates a table to store one‑point advice messages and inserts sample data if empty.
 */
function roro_advice_activate() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table           = $wpdb->prefix . 'roro_one_point_advice_master';
    $sql             = "CREATE TABLE $table (\n"
                    . "id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,\n"
                    . "advice TEXT NOT NULL,\n"
                    . "language VARCHAR(10) DEFAULT 'en',\n"
                    . "created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,\n"
                    . "PRIMARY KEY (id)\n"
                    . ") $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    // Insert sample advice if table is empty
    if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ) ) {
        $samples = array(
            'Remember to spend quality time with your pet every day.',
            'Regular exercise keeps your pet healthy and happy.',
            'Provide fresh water at all times.',
            'Don’t forget annual health checkups.',
        );
        foreach ( $samples as $sample ) {
            $wpdb->insert( $table, array( 'advice' => $sample, 'language' => 'en' ), array( '%s', '%s' ) );
        }
    }
}

register_activation_hook( __FILE__, 'roro_advice_activate' );

function roro_advice_run() {
    $advice = new Roro_Advice_Plugin();
    $advice->run();
}
add_action( 'plugins_loaded', 'roro_advice_run' );