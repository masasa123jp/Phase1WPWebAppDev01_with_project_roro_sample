<?php
/**
 * SQL Logger column translations for the RORO Assets SQL Manager.
 *
 * This class hooks into the RORO SQL manager plugin to translate the column
 * labels shown in the SQL log table. It relies on the plugin’s text
 * domain and translation files to render the appropriate language. If the
 * underlying SQL manager exists the filter will override the default
 * column labels; otherwise it simply returns the provided array.
 *
 * @package RoroAssetsSQLManager
 */

// Prevent direct access.
defined('ABSPATH') || exit;

/**
 * Class Roro_SQL_Logger
 */
class Roro_SQL_Logger {
    /**
     * Attach the filter on initialisation.
     *
     * This static function should be called when plugins are loaded. It
     * registers our callback on the `roro_sql_logger_columns` filter,
     * ensuring that our translations take precedence.
     */
    public static function init() {
        if (function_exists('add_filter')) {
            add_filter('roro_sql_logger_columns', array(__CLASS__, 'filter_columns'));
        }
    }

    /**
     * Replace column labels with translated strings.
     *
     * @param array $columns Original column definitions.
     * @return array Modified column definitions.
     */
    public static function filter_columns($columns) {
        if (!is_array($columns)) {
            $columns = array();
        }
        $columns['query']       = __('Query', 'roro-assets-sql-manager');
        $columns['duration']    = __('Execution Time (ms)', 'roro-assets-sql-manager');
        $columns['executed_at'] = __('Executed At', 'roro-assets-sql-manager');
        return $columns;
    }
}

// Kick off the logger initialisation immediately when this file is loaded.
Roro_SQL_Logger::init();
