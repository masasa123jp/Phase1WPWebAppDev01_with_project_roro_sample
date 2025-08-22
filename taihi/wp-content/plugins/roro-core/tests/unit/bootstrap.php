<?php
/**
 * PHPUnit bootstrap file for RoRo Core.
 *
 * @package RoroCore\Tests
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = sys_get_temp_dir() . '/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load plugin.
 */
function _roro_load_plugin() {
	require dirname( __DIR__ ) . '/roro-core.php';
}
tests_add_filter( 'muplugins_loaded', '_roro_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
