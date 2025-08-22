<?php
/**
 * Database helpers for the RORO Core WP plugin.
 *
 * This class exposes convenience methods for working with WordPress tables
 * created by the plugin.  It is intentionally lightweight; additional
 * abstraction (e.g. repository classes) can be built on top of it as needed.
 *
 * @package RORO_Core_WP
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Safety check.
}

class Roro_Core_Wp_Db {
    /**
     * Return the fully qualified table name including the WordPress prefix.
     *
     * @param string $name Base table name without prefix (e.g. 'roro_customer').
     * @return string Fully prefixed table name.
     */
    public static function table( $name ) {
        global $wpdb;
        return $wpdb->prefix . $name;
    }
}
