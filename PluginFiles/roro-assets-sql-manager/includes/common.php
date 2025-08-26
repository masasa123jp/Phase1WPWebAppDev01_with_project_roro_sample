<?php
/**
 * Common utilities for the RORO Assets SQL Manager plugin.
 *
 * This file defines a small collection of constants and helper functions that
 * are used throughout the plugin. Keeping these definitions in a single
 * location ensures that the values remain consistent and can easily be
 * modified in future versions without touching the rest of the codebase.
 *
 * The functions defined here have deliberately small, well‑defined
 * responsibilities: initialising options on activation, recording log
 * messages, and returning a monotonic timestamp. When grouped together in a
 * MECE (mutually exclusive, collectively exhaustive) fashion they provide a
 * clear interface for other modules in the plugin to build upon.
 *
 * @package RoroAssetsSQLManager
 */

// Do not allow direct access from a web browser.
if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 *  Core plugin constants
 *
 * These constants mirror those originally defined in the monolithic
 * implementation. They are exposed globally to retain backwards
 * compatibility with code that references them directly.
 */
if (!defined('RORO_SQL_MANAGER_SLUG')) {
    define('RORO_SQL_MANAGER_SLUG', 'roro-assets-sql-manager');
}
if (!defined('RORO_SQL_MANAGER_VER')) {
    define('RORO_SQL_MANAGER_VER', '1.3.0');
}
if (!defined('RORO_SQL_MANAGER_OPT')) {
    // Option key used to store an array of applied migration IDs.
    define('RORO_SQL_MANAGER_OPT', 'roro_sql_applied');
}
if (!defined('RORO_SQL_MANAGER_LOG')) {
    // Option key used to store an array of recent log entries.
    define('RORO_SQL_MANAGER_LOG', 'roro_sql_log');
}
if (!defined('RORO_SQL_MANAGER_LOG_MAX')) {
    // Limit the number of log entries retained in the log option.
    define('RORO_SQL_MANAGER_LOG_MAX', 200);
}

/* -------------------------------------------------------------------------
 *  Convenience wrappers
 *
 * The following functions expose basic information about the plugin. They are
 * intentionally light weight and avoid relying on WordPress functions that
 * might not be available at early bootstrap stages. When the WordPress
 * environment is fully loaded these wrappers could be replaced by
 * plugin_dir_path() or plugin_dir_url(), but using them directly here keeps
 * the implementation self contained.
 */

/**
 * Return the absolute filesystem path to the plugin’s root directory.
 *
 * Because this file resides within the `includes` subdirectory we need to
 * traverse two parent directories to reach the plugin root.
 *
 * @return string Absolute path ending with a trailing slash.
 */
function roro_sql_manager_dir() {
    return dirname(__DIR__) . '/';
}

/**
 * Return the current GMT timestamp formatted for display.
 *
 * We always work in GMT when storing timestamps for reproducibility. If you
 * need a timezone adjusted value you should perform the conversion at the
 * display layer.
 *
 * @return string Timestamp in `Y-m-d H:i:s` format.
 */
function roro_sql_manager_now() {
    return gmdate('Y-m-d H:i:s');
}

/* -------------------------------------------------------------------------
 *  Activation callback
 *
 * On first plugin activation we create the two options used to store state.
 * WordPress will ignore the call to add_option() if the option already
 * exists, ensuring this function is idempotent and safe to run multiple
 * times.
 */

/**
 * Initialise plugin options on activation.
 *
 * This function is called via register_activation_hook() from the main
 * plugin file. It ensures that the options used to track applied
 * migrations and log entries exist before any migrations are executed.
 *
 * @return void
 */
function roro_sql_manager_activation() {
    if (get_option(RORO_SQL_MANAGER_OPT) === false) {
        add_option(RORO_SQL_MANAGER_OPT, array(), false);
    }
    if (get_option(RORO_SQL_MANAGER_LOG) === false) {
        add_option(RORO_SQL_MANAGER_LOG, array(), false);
    }
}

/* -------------------------------------------------------------------------
 *  Logging utilities
 *
 * Logging is centralised through this function to provide consistent
 * formatting and automatic capping of the number of log entries. Each log
 * entry records the UTC timestamp, a severity level, a message and an
 * optional context array. When WP_DEBUG is enabled the message is echoed
 * to error_log() for immediate visibility during development.
 */

/**
 * Append a message to the plugin’s log.
 *
 * @param string $level   Severity level such as INFO, ERROR or WARN.
 * @param string $message Human readable message describing what happened.
 * @param array  $context Additional context to include with the entry.
 * @return void
 */
function roro_sql_manager_log($level, $message, $context = array()) {
    $log = get_option(RORO_SQL_MANAGER_LOG, array());
    $log[] = array(
        'time'  => roro_sql_manager_now(),
        'level' => strtoupper($level),
        'msg'   => $message,
        'ctx'   => $context,
    );
    // Trim the log to the most recent entries if it grows too large.
    if (count($log) > RORO_SQL_MANAGER_LOG_MAX) {
        $log = array_slice($log, -RORO_SQL_MANAGER_LOG_MAX);
    }
    update_option(RORO_SQL_MANAGER_LOG, $log, false);
    // Mirror messages to the PHP error log when debugging is enabled.
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[RORO SQL] ' . strtoupper($level) . ': ' . $message . (empty($context) ? '' : ' ' . wp_json_encode($context)));
    }
}
