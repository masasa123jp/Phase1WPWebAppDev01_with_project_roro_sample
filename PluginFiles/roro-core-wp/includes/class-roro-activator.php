<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since 1.0.0
 * @package RoroCoreWp
 */
class Roro_Core_Wp_Activator {

    /**
     * Method to execute when the plugin is activated.
     * Creates the necessary database tables and sets default options.
     *
     * @since 1.0.0
     */
    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        /*
         * Create the custom tables needed by the Roro platform.  We define a set
         * of table schemas here rather than relying on external SQL files so
         * that WordPress can perform incremental updates via dbDelta().  When
         * modifying the schema, ensure that field definitions use names and
         * types supported by dbDelta().
         */
        $prefix = $wpdb->prefix;
        $tables = [];

        // Customer table: stores basic account details linked to a WordPress user.
        $tables['customer'] = "CREATE TABLE {$prefix}roro_customer (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " wp_user_id BIGINT UNSIGNED NOT NULL,\n"
            . " name VARCHAR(100) NOT NULL,\n"
            . " email VARCHAR(100) NOT NULL,\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id),\n"
            . " KEY wp_user_id (wp_user_id)\n"
            . ") $charset_collate;";

        // Pets table: one-to-many relationship with customer.
        $tables['pet'] = "CREATE TABLE {$prefix}roro_pet (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " customer_id BIGINT UNSIGNED NOT NULL,\n"
            . " name VARCHAR(100) NOT NULL,\n"
            . " breed VARCHAR(100) DEFAULT NULL,\n"
            . " birthdate DATE DEFAULT NULL,\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id),\n"
            . " KEY customer_id (customer_id)\n"
            . ") $charset_collate;";

        // Event master table: contains events with location and dates.
        $tables['event_master'] = "CREATE TABLE {$prefix}roro_event_master (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " name VARCHAR(255) NOT NULL,\n"
            . " description TEXT DEFAULT NULL,\n"
            . " latitude DOUBLE NOT NULL,\n"
            . " longitude DOUBLE NOT NULL,\n"
            . " start_date DATETIME DEFAULT NULL,\n"
            . " end_date DATETIME DEFAULT NULL,\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id)\n"
            . ") $charset_collate;";

        // Travel spot master table: contains locations for travel spots.
        $tables['travel_spot_master'] = "CREATE TABLE {$prefix}roro_travel_spot_master (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " name VARCHAR(255) NOT NULL,\n"
            . " description TEXT DEFAULT NULL,\n"
            . " latitude DOUBLE NOT NULL,\n"
            . " longitude DOUBLE NOT NULL,\n"
            . " category VARCHAR(100) DEFAULT NULL,\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id)\n"
            . ") $charset_collate;";

        // Map favourites: store user favourites for spots/events.
        $tables['map_favorite'] = "CREATE TABLE {$prefix}roro_map_favorite (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " wp_user_id BIGINT UNSIGNED NOT NULL,\n"
            . " item_id BIGINT UNSIGNED NOT NULL,\n"
            . " item_type VARCHAR(20) NOT NULL,\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id),\n"
            . " KEY wp_user_item (wp_user_id, item_id, item_type)\n"
            . ") $charset_collate;";

        // AI conversation: stores conversation sessions per user.
        $tables['ai_conversation'] = "CREATE TABLE {$prefix}roro_ai_conversation (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " wp_user_id BIGINT UNSIGNED NOT NULL,\n"
            . " started_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id),\n"
            . " KEY wp_user_id (wp_user_id)\n"
            . ") $charset_collate;";

        // AI messages: individual user/bot messages within a conversation.
        $tables['ai_message'] = "CREATE TABLE {$prefix}roro_ai_message (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " conversation_id BIGINT UNSIGNED NOT NULL,\n"
            . " wp_user_id BIGINT UNSIGNED DEFAULT NULL,\n"
            . " role ENUM('user','bot') NOT NULL,\n"
            . " message TEXT NOT NULL,\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id),\n"
            . " KEY conversation_id (conversation_id)\n"
            . ") $charset_collate;";

        // One-point advice: stores advice messages to be displayed to the user.
        $tables['one_point_advice'] = "CREATE TABLE {$prefix}roro_one_point_advice_master (\n"
            . " id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
            . " advice_text TEXT NOT NULL,\n"
            . " language VARCHAR(10) DEFAULT 'ja',\n"
            . " created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n"
            . " PRIMARY KEY  (id)\n"
            . ") $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $tables as $sql ) {
            dbDelta( $sql );
        }

        // After creating core tables, optionally import additional SQL scripts
        // located in the plugin's assets/sql directory.  These scripts may
        // contain seed data or extra table definitions provided alongside
        // this plugin.  If no files are present the importer silently skips.
        self::import_sql_files();
    }

    /**
     * Import all *.sql files found under the plugin's assets/sql directory.
     *
     * This helper reads each SQL file, splits it into individual statements
     * separated by a semicolon followed by a line break, and executes them
     * sequentially. Transactions are used where supported to improve
     * consistency.  Errors encountered while executing a statement are
     * reported via error_log but do not abort the remainder of the import.
     *
     * @since 1.0.0
     * @return void
     */
    protected static function import_sql_files() {
        global $wpdb;
        $sql_dir = trailingslashit( RORO_CORE_WP_DIR ) . 'assets/sql';
        if ( ! is_dir( $sql_dir ) || ! is_readable( $sql_dir ) ) {
            return;
        }
        $files = glob( $sql_dir . '/*.sql' );
        if ( empty( $files ) ) {
            return;
        }

        // Start a transaction when supported.
        $wpdb->query( 'START TRANSACTION' );

        foreach ( $files as $sql_file ) {
            $content = file_get_contents( $sql_file );
            if ( false === $content ) {
                continue;
            }
            // Split queries on semicolon followed by newline.
            $queries = array_filter( array_map( 'trim', preg_split( '/;\s*\n/', $content ) ) );
            foreach ( $queries as $query ) {
                if ( empty( $query ) ) {
                    continue;
                }
                $result = $wpdb->query( $query );
                if ( false === $result ) {
                    error_log( '[roro-core-wp] Failed to execute SQL in ' . basename( $sql_file ) . ': ' . $query );
                }
            }
        }

        $wpdb->query( 'COMMIT' );
    }

    /**
     * Method to execute when the plugin is deactivated.
     * Generally used to clear scheduled events or temporary data.
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        // Deactivation tasks (no-op for now).
    }
}