<?php
/**
 * REST API endpoints for the RORO Assets SQL Manager.
 *
 * These routes expose migration information and actions over the WordPress
 * REST API. Each endpoint is restricted to users with the `manage_options`
 * capability to prevent unauthorized database changes. Keeping the REST
 * definitions in a separate file decouples the transport layer from the
 * migration logic and admin UI.
 *
 * @package RoroAssetsSQLManager
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST routes when the REST API initialises.
 *
 * This callback is hooked into `rest_api_init` and defines three routes:
 * - GET  /roro/v1/db/migrations
 *   Returns the list of all migrations, the list of applied IDs and the
 *   current log tail.
 * - POST /roro/v1/db/apply
 *   Applies one or more migrations identified by the `ids` parameter.
 * - POST /roro/v1/db/rollback
 *   Rolls back one or more migrations identified by the `ids` parameter.
 */
function roro_sql_manager_register_rest_routes() {
    register_rest_route('roro/v1', '/db/migrations', array(
        'methods'  => 'GET',
        'callback' => function (WP_REST_Request $req) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
            }
            return array(
                'applied'  => roro_sql_manager_get_applied(),
                'all'      => array_values(roro_sql_manager_discover_migrations()),
                'log_tail' => get_option(RORO_SQL_MANAGER_LOG, array()),
            );
        },
        'permission_callback' => '__return_true',
    ));
    register_rest_route('roro/v1', '/db/apply', array(
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $req) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
            }
            $ids = $req->get_param('ids');
            $dry = (bool) $req->get_param('dry_run');
            $ids = is_array($ids) ? array_map('sanitize_text_field', $ids) : array();
            $res = roro_sql_manager_apply($ids, $dry);
            if (is_wp_error($res)) {
                return new WP_REST_Response(array('ok' => false, 'error' => $res->get_error_message()), 400);
            }
            return new WP_REST_Response(array('ok' => true), 200);
        },
        'permission_callback' => '__return_true',
    ));
    register_rest_route('roro/v1', '/db/rollback', array(
        'methods'  => 'POST',
        'callback' => function (WP_REST_Request $req) {
            if (!current_user_can('manage_options')) {
                return new WP_Error('forbidden', 'Forbidden', array('status' => 403));
            }
            $ids = $req->get_param('ids');
            $dry = (bool) $req->get_param('dry_run');
            $ids = is_array($ids) ? array_map('sanitize_text_field', $ids) : array();
            $res = roro_sql_manager_rollback($ids, $dry);
            if (is_wp_error($res)) {
                return new WP_REST_Response(array('ok' => false, 'error' => $res->get_error_message()), 400);
            }
            return new WP_REST_Response(array('ok' => true), 200);
        },
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'roro_sql_manager_register_rest_routes');
