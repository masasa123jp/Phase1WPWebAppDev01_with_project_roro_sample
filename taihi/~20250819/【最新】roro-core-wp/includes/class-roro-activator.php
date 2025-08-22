<?php
/**
 * Activation and uninstall routines for the RORO Core WP plugin.
 *
 * Responsible for creating database tables on activation and optionally
 * importing seed data.  The uninstall routine cleans up plugin data by
 * removing plugin tables and deleting stored options.  If you wish to
 * preserve data on uninstall, adjust the uninstall() method accordingly.
 *
 * @package RORO_Core_WP
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Roro_Core_Wp_Activator {
    /**
     * Called on plugin activation.
     *
     * Creates or updates database tables and populates initial seed data.  If
     * the initial data import is time‑consuming, consider executing it via
     * WP_Cron or splitting into smaller chunks.
     */
    public static function activate() {
        global $wpdb;
        // Load dbDelta functions.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        self::create_tables();
        // Import seed data from events file.  You can comment this out if you
        // wish to import manually or via a separate tool.
        self::import_sql_file( 'events_table_corrected3.sql' );
        self::import_sql_file( 'initial_data_with_latlng.sql' );    }

    /**
     * Called on plugin uninstall.
     *
     * Drops all plugin tables and deletes options.  Modify this method if you
     * prefer to preserve user data between installations.
     */
    public static function uninstall() {
        global $wpdb;
        $tables = array(
            'roro_customer',
            'roro_user_link_wp',
            'roro_auth_account',
            'roro_auth_session',
            'roro_auth_token',
            'category_master',
            'pet_master',
            'roro_pet',
            'roro_map_favorite',
            'opam',
            'category_data_link',
            'roro_recommendation_log',
            'roro_ai_conversation',
            'roro_ai_message',
            'events'
        );
        foreach ( $tables as $table ) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
        }
        // Remove plugin options.
        delete_option( 'roro_core_wp_settings' );
    }

    /**
     * Create database tables defined in schema.sql.
     *
     * Reads the SQL file, replaces the {$wpdb_prefix} placeholder with the
     * current WordPress table prefix, and executes each statement.  CREATE
     * TABLE statements are run through dbDelta() to ensure indexes are
     * properly handled.
     */
    protected static function create_tables() {
        global $wpdb;
        $schema_file = RORO_CORE_WP_PLUGIN_DIR . 'sql/schema.sql';
        if ( ! file_exists( $schema_file ) ) {
            return;
        }
        $contents = file_get_contents( $schema_file );
        // Replace placeholder prefix.
        $contents = str_replace( '{$wpdb_prefix}', $wpdb->prefix, $contents );
        // Split on semicolon followed by newline.
        $queries = preg_split( "/;\s*[\r\n]+/", $contents );
        foreach ( $queries as $sql ) {
            $query = trim( $sql );
            if ( empty( $query ) ) {
                continue;
            }
            if ( preg_match( '/^CREATE\s+TABLE/i', $query ) ) {
                dbDelta( $query );
            } else {
                $wpdb->query( $query );
            }
        }
    }

    /**
     * Import a SQL file from the plugin's sql directory.
     *
     * @param string $filename Name of the SQL file to import.
     */
    protected static function import_sql_file( $filename ) {
        global $wpdb;
        $path = RORO_CORE_WP_PLUGIN_DIR . 'sql/' . $filename;
        if ( ! file_exists( $path ) ) {
            return;
        }
        $sql = file_get_contents( $path );
        $sql = str_replace( '{$wpdb_prefix}', $wpdb->prefix, $sql );
        $statements = preg_split( "/;\s*[\r\n]+/", $sql );
        foreach ( $statements as $statement ) {
            $query = trim( $statement );
            if ( empty( $query ) ) {
                continue;
            }
            $wpdb->query( $query );
        }
    }
}
