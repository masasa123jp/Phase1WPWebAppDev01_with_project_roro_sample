<?php
/**
 * Plugin Name: RoRo Assets and SQL Manager
 * Plugin URI:  https://example.com/roro-assets-sql-manager
 * Description: Provides helper functions to display images stored in the theme's assets folder and to execute SQL scripts located in a configurable directory. This plugin is designed to accompany the RoRo project and ensures that images and SQL files managed outside of the plugin can be utilised safely.  Place your image files inside the theme at `wp-content/themes/project-roro/assets/images` and your SQL files inside the `DB_sql` directory in the WordPress root.
 * Version:     1.0.0
 * Author:      RoRo Development Team
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: roro-assets-sql-manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define constants for paths to assets and SQL directories.
 *
 * Developers can override these constants in wp-config.php before activating
 * the plugin if the directories are located elsewhere. By default the plugin
 * assumes the images are under the active theme's `project-roro/assets/images`
 * directory and SQL files reside in a top‑level `DB_sql` directory (a sibling
 * of wp-content).
 */
if ( ! defined( 'RORO_IMAGES_DIR' ) ) {
    // Path to the theme's image directory on the filesystem.
    $theme  = wp_get_theme();
    $stylesheet_dir = get_stylesheet_directory();
    // Attempt to locate project‑roro within the active theme. If it doesn't
    // exist the constant will still point to assets/images for developer
    // convenience.
    $project_roro_dir = $stylesheet_dir . '/project-roro';
    if ( ! is_dir( $project_roro_dir ) ) {
        $project_roro_dir = $stylesheet_dir;
    }
    define( 'RORO_IMAGES_DIR', trailingslashit( $project_roro_dir ) . 'assets/images' );
}

if ( ! defined( 'RORO_IMAGES_URL' ) ) {
    // URL to the theme's image directory.
    $stylesheet_uri = get_stylesheet_directory_uri();
    $project_roro_uri = $stylesheet_uri . '/project-roro';
    // Use project-roro if it exists otherwise fallback to stylesheet root.
    if ( ! is_dir( get_stylesheet_directory() . '/project-roro' ) ) {
        $project_roro_uri = $stylesheet_uri;
    }
    define( 'RORO_IMAGES_URL', trailingslashit( $project_roro_uri ) . 'assets/images' );
}

if ( ! defined( 'RORO_SQL_DIR' ) ) {
    // Assume DB_sql directory sits alongside wp‑content. Use ABSPATH to build path.
    // ABSPATH always has a trailing slash.
    define( 'RORO_SQL_DIR', ABSPATH . 'DB_sql' );
}

/**
 * Activation hook – ensure SQL directory exists.
 *
 * When the plugin is activated WordPress will call this function. It simply
 * checks whether the SQL directory exists and is readable. If it isn't
 * available a notice is logged. The actual import happens via the admin
 * interface or can be triggered manually.
 */
function roro_assets_sql_manager_activate() {
    if ( ! is_dir( RORO_SQL_DIR ) ) {
        // Log an error to debug.log for administrators. We avoid halting
        // activation as the directory may be created later.
        error_log( sprintf( '[RoRo Assets SQL Manager] SQL directory not found at %s. Create this directory and place your SQL files inside to enable import.', RORO_SQL_DIR ) );
    }
}
register_activation_hook( __FILE__, 'roro_assets_sql_manager_activate' );

/**
 * Shortcode to output an image from the theme's assets folder.
 *
 * Usage: [roro_image file="filename.png" alt="Alternative text" class="my-class"]
 *
 * @param array $atts Shortcode attributes.
 * @return string HTML image tag or empty string on failure.
 */
function roro_assets_sql_manager_shortcode_image( $atts = [] ) {
    $atts = shortcode_atts(
        [
            'file'  => '',
            'alt'   => '',
            'class' => '',
            'width' => '',
            'height'=> '',
        ],
        $atts,
        'roro_image'
    );

    if ( empty( $atts['file'] ) ) {
        return '';
    }

    // Sanitise filename to prevent directory traversal.
    $file = basename( $atts['file'] );
    $image_path = RORO_IMAGES_DIR . '/' . $file;
    if ( ! file_exists( $image_path ) ) {
        return '';
    }
    $image_url = RORO_IMAGES_URL . '/' . rawurlencode( $file );

    $classes = array_map( 'sanitize_html_class', explode( ' ', $atts['class'] ) );
    $class_attr = $classes ? ' class="' . esc_attr( implode( ' ', $classes ) ) . '"' : '';
    $width_attr = $atts['width'] ? ' width="' . intval( $atts['width'] ) . '"' : '';
    $height_attr = $atts['height'] ? ' height="' . intval( $atts['height'] ) . '"' : '';

    return sprintf(
        '<img src="%s" alt="%s"%s%s%s />',
        esc_url( $image_url ),
        esc_attr( $atts['alt'] ),
        $class_attr,
        $width_attr,
        $height_attr
    );
}
add_shortcode( 'roro_image', 'roro_assets_sql_manager_shortcode_image' );

/**
 * Import all SQL files from the configured directory.
 *
 * This function reads every *.sql file within the SQL directory and executes
 * each statement using $wpdb->query(). Complex SQL statements containing
 * multiple queries separated by semicolons are supported. The function runs
 * inside a transaction when supported by the database. Errors are logged but
 * do not halt execution of subsequent files.
 *
 * @return array An array containing counts of successful and failed statements.
 */
function roro_assets_sql_manager_import_sql_files() {
    global $wpdb;
    $results = [ 'success' => 0, 'fail' => 0 ];

    if ( ! is_dir( RORO_SQL_DIR ) || ! is_readable( RORO_SQL_DIR ) ) {
        return $results;
    }

    $files = glob( RORO_SQL_DIR . '/*.sql' );
    if ( empty( $files ) ) {
        return $results;
    }

    // Begin transaction if supported (for MySQL InnoDB). Some DBs may not
    // support transactions on DDL statements; ignore errors.
    $wpdb->query( 'START TRANSACTION' );

    foreach ( $files as $sql_file ) {
        $sql_content = file_get_contents( $sql_file );
        if ( false === $sql_content ) {
            continue;
        }
        // Remove comments and split by semicolon on line breaks. This is a
        // simple parser; it will not handle semicolons inside strings but is
        // sufficient for typical DDL/DML scripts used by this project.
        $queries = array_filter( array_map( 'trim', preg_split( '/;\s*\n/', $sql_content ) ) );
        foreach ( $queries as $query ) {
            if ( empty( $query ) ) {
                continue;
            }
            $result = $wpdb->query( $query );
            if ( false === $result ) {
                $results['fail']++;
                error_log( sprintf( '[RoRo Assets SQL Manager] Failed to execute query from %s: %s', basename( $sql_file ), $query ) );
            } else {
                $results['success']++;
            }
        }
    }
    // Commit transaction.
    $wpdb->query( 'COMMIT' );

    return $results;
}

/**
 * Register a simple admin page under Tools for running SQL imports.
 */
function roro_assets_sql_manager_admin_menu() {
    add_management_page(
        __( 'RoRo SQL Import', 'roro-assets-sql-manager' ),
        __( 'RoRo SQL Import', 'roro-assets-sql-manager' ),
        'manage_options',
        'roro-sql-import',
        'roro_assets_sql_manager_render_admin_page'
    );
}
add_action( 'admin_menu', 'roro_assets_sql_manager_admin_menu' );

/**
 * Render the admin page content.
 */
function roro_assets_sql_manager_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'RoRo SQL Import', 'roro-assets-sql-manager' ) . '</h1>';
    if ( isset( $_POST['roro_import_sql_nonce'] ) && wp_verify_nonce( $_POST['roro_import_sql_nonce'], 'roro_import_sql' ) ) {
        $results = roro_assets_sql_manager_import_sql_files();
        echo '<div class="updated notice"><p>' . sprintf( esc_html__( 'Import complete. %1$d queries succeeded, %2$d queries failed.', 'roro-assets-sql-manager' ), intval( $results['success'] ), intval( $results['fail'] ) ) . '</p></div>';
    }
    echo '<form method="post">';
    wp_nonce_field( 'roro_import_sql', 'roro_import_sql_nonce' );
    echo '<p>' . esc_html__( 'Click the button below to import all SQL files from the configured directory.', 'roro-assets-sql-manager' ) . '</p>';
    submit_button( __( 'Run Import', 'roro-assets-sql-manager' ), 'primary', 'roro-import-sql' );
    echo '</form>';
    echo '</div>';
}

/**
 * Helper function to enqueue the plugin’s stylesheet (if needed in the future).
 * Currently unused but reserved for future enhancements. Developers can add a
 * CSS file inside the `assets` directory of this plugin and uncomment the
 * lines below to enqueue it.
 */
/*
function roro_assets_sql_manager_enqueue_assets() {
    wp_enqueue_style(
        'roro-assets-sql-manager',
        plugins_url( 'assets/css/style.css', __FILE__ ),
        [],
        '1.0.0'
    );
}
add_action( 'admin_enqueue_scripts', 'roro_assets_sql_manager_enqueue_assets' );
*/
