<?php
/**
 * PHPUnit bootstrap for RoRo Core
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: sys_get_temp_dir() . '/wordpress-tests-lib';

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__, 2 ) . '/roro-core.php'; // プラグイン読み込み
	}
);

require $_tests_dir . '/includes/bootstrap.php'; // :contentReference[oaicite:5]{index=5}
