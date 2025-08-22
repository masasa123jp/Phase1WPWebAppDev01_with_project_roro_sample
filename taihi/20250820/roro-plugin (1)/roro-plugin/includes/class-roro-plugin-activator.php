<?php
/**
 * RORO Core Plugin
 *
 * Handles activation tasks such as creating database tables and
 * inserting initial seed data.  This class is invoked via
 * register_activation_hook in roro-plugin.php.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Roro_Plugin_Activator {

    /**
     * Perform activation tasks.
     */
    public static function activate() {
        global $wpdb;

        // Ensure upgrade functions are available.
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create or update database tables.
        self::create_tables( $wpdb );

        // Insert initial data.
        self::import_sql_file( 'events_table_corrected3.sql' );
        // Optionally import other data sets.  The large initial_data_with_latlng
        // file is included for completeness but commented out by default to
        // avoid long-running operations.  Uncomment the following line to
        // import it on activation.
        // self::import_sql_file( 'initial_data_with_latlng.sql' );
    }

    /**
     * Create plugin tables from schema.sql.
     *
     * @param wpdb $wpdb Global WordPress database object.
     */
    protected static function create_tables( $wpdb ) {
        $schema_file = RORO_PLUGIN_DIR . 'sql/schema.sql';
        if ( ! file_exists( $schema_file ) ) {
            return;
        }
        $contents = file_get_contents( $schema_file );
        // Replace placeholder with actual table prefix.
        $prefix = $wpdb->prefix;
        $contents = str_replace( '{$wpdb_prefix}', $prefix, $contents );
        // Split into individual statements on semicolon followed by newline.
        $queries = self::split_sql( $contents );
        foreach ( $queries as $query ) {
            $sql = trim( $query );
            if ( empty( $sql ) ) {
                continue;
            }
            // Use dbDelta for CREATE statements to handle indices properly.
            if ( preg_match( '/^CREATE\s+TABLE/i', $sql ) ) {
                dbDelta( $sql );
            } else {
                $wpdb->query( $sql );
            }
        }
    }

    /**
     * Import SQL statements from a file in the plugin's sql directory.
     *
     * @param string $filename File name located in the sql directory.
     */
    protected static function import_sql_file( $filename ) {
        global $wpdb;
        $file = RORO_PLUGIN_DIR . 'sql/' . $filename;
        if ( ! file_exists( $file ) ) {
            return;
        }
        $sql = file_get_contents( $file );
        // Replace placeholder prefix if present.
        $prefix = $wpdb->prefix;
        $sql = str_replace( '{$wpdb_prefix}', $prefix, $sql );
        $statements = self::split_sql( $sql );
        foreach ( $statements as $statement ) {
            $s = trim( $statement );
            if ( empty( $s ) ) {
                continue;
            }
            // Use $wpdb->query for inserts; errors are ignored.
            $wpdb->query( $s );
        }
    }

    /**
     * Split SQL string into individual statements.
     *
     * This function splits on semicolons followed by a newline.  It does not
     * handle corner cases such as semicolons inside strings, but is
     * adequate for our schema and seed files.
     *
     * @param string $sql Multi-statement SQL string.
     * @return array Array of individual SQL statements.
     */
    protected static function split_sql( $sql ) {
        $queries = preg_split( "/;\s*\n/", $sql );
        return array_filter( $queries );
    }
}