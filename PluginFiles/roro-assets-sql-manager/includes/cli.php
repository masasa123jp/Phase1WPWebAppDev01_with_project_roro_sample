<?php
/**
 * WP‑CLI commands for the RORO Assets SQL Manager.
 *
 * When run within the WP‑CLI context these commands allow administrators to
 * list migrations and apply or roll them back without visiting the WordPress
 * admin. The commands mirror the functionality of the REST endpoints and
 * admin UI. This file is loaded conditionally when WP_CLI is defined and
 * true.
 *
 * Usage examples:
 *   wp roro-sql list
 *   wp roro-sql apply --ids=20250824001_init_core,20250824003_seed_advice_up
 *   wp roro-sql rollback --ids=20250824003_seed_advice_up
 *
 * @package RoroAssetsSQLManager
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * RORO SQL Manager command definitions.
 */
class Roro_SQL_CLI_Command {
    /**
     * List all migrations and their status.
     *
     * ## EXAMPLES
     *
     *     wp roro-sql list
     *
     * @return void
     */
    public function list($_, $assoc_args) {
        $all     = roro_sql_manager_discover_migrations();
        $applied = roro_sql_manager_get_applied();
        $rows    = array();
        foreach ($all as $id => $mig) {
            $rows[] = array(
                'id'          => $id,
                'description' => isset($mig['description']) ? $mig['description'] : '',
                'group'       => isset($mig['group']) ? $mig['group'] : '',
                'status'      => in_array($id, $applied, true) ? 'applied' : 'pending',
                'depends'     => isset($mig['depends']) && is_array($mig['depends']) ? implode(',', $mig['depends']) : '',
            );
        }
        \WP_CLI\Utils\format_items('table', $rows, array('id','description','group','status','depends'));
    }
    /**
     * Apply one or more migrations.
     *
     * ## OPTIONS
     *
     * [--ids=<ids>]
     * : Comma‑separated list of migration IDs to apply. If omitted all
     *   pending migrations will be applied.
     *
     * [--dry-run]
     * : Perform a dry run without executing any SQL.
     *
     * ## EXAMPLES
     *
     *     wp roro-sql apply --ids=20250824001_init_core,20250824003_seed_advice_up
     *
     * @return void
     */
    public function apply($_, $assoc_args) {
        $ids = array();
        if (!empty($assoc_args['ids'])) {
            $ids = array_map('trim', explode(',', $assoc_args['ids']));
        }
        $dry = !empty($assoc_args['dry-run']);
        $res = roro_sql_manager_apply($ids, $dry);
        if (is_wp_error($res)) {
            \WP_CLI::error($res->get_error_message());
            return;
        }
        \WP_CLI::success($dry ? 'Dry run completed.' : 'Migrations applied.');
    }
    /**
     * Roll back one or more migrations.
     *
     * ## OPTIONS
     *
     * --ids=<ids>
     * : Comma‑separated list of migration IDs to roll back. Required.
     *
     * [--dry-run]
     * : Perform a dry run without executing any SQL.
     *
     * ## EXAMPLES
     *
     *     wp roro-sql rollback --ids=20250824003_seed_advice_up
     *
     * @return void
     */
    public function rollback($_, $assoc_args) {
        if (empty($assoc_args['ids'])) {
            \WP_CLI::error('You must specify the migration IDs to roll back using --ids.');
            return;
        }
        $ids = array_map('trim', explode(',', $assoc_args['ids']));
        $dry = !empty($assoc_args['dry-run']);
        $res = roro_sql_manager_rollback($ids, $dry);
        if (is_wp_error($res)) {
            \WP_CLI::error($res->get_error_message());
            return;
        }
        \WP_CLI::success($dry ? 'Dry run completed.' : 'Migrations rolled back.');
    }
}

\WP_CLI::add_command('roro-sql', 'Roro_SQL_CLI_Command');
