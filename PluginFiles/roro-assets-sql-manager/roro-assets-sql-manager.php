<?php
/**
 * Plugin Name: RORO Assets SQL Manager
 * Description: Provides a comprehensive SQL migration manager for the RORO project along with a multilingual SQL log viewer. The plugin discovers SQL and PHP migration files, executes them with dependency resolution, offers apply and rollback actions via an admin interface, REST API and WP‑CLI, and translates the SQL log column headers into multiple languages.
 * Version: 1.3.0
 * Author: RORO Dev Team
 * Text Domain: roro-assets-sql-manager
 * Domain Path: /languages
 *
 * The code in this file serves as the bootstrap for the plugin. It loads the
 * translation files, registers activation hooks and pulls in the modular
 * implementation spread across the files in the includes/ directory.
 */

// Exit immediately if WordPress is not loaded.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load the plugin’s translated strings.
 *
 * WordPress calls the `plugins_loaded` action once all active plugins have
 * been included. At this point we can safely load our translation files
 * from the languages/ directory. Keeping this in a separate function makes
 * it easy to test and to override via hooks if necessary.
 */
function roro_assets_sql_manager_load_textdomain() {
    load_plugin_textdomain(
        'roro-assets-sql-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}
add_action('plugins_loaded', 'roro_assets_sql_manager_load_textdomain');

// Pull in the modular pieces of the plugin. Each file encapsulates a single
// concern (common utilities, migrations, admin UI, REST API and logging UI).
require_once __DIR__ . '/includes/common.php';
require_once __DIR__ . '/includes/migrations.php';
require_once __DIR__ . '/includes/admin-page.php';
require_once __DIR__ . '/includes/rest-api.php';
require_once __DIR__ . '/includes/class-roro-sql-logger.php';

// Load WP‑CLI commands when run in the CLI context. This conditional include
// prevents the CLI definitions from being loaded during normal web requests.
if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/includes/cli.php';
}

// Register the activation hook to prime our options on first install. The
// activation callback itself lives in includes/common.php.
register_activation_hook(__FILE__, 'roro_sql_manager_activation');
