<?php
/**
 * DB スキーマ自動適用
 *
 * @package RoroCore
 */

namespace RoroCore\DB;

defined( 'ABSPATH' ) || exit;

class Schema_Manager {

	const SQL_FILE = __DIR__ . '/../../schema-roro.sql';

	public static function init(): void {
		register_activation_hook( RORO_CORE_PLUGIN_FILE, [ self::class, 'apply_schema' ] );
	}

	/**
	 * schema‑roro.sql を dbDelta 形式に分割して実行
	 */
	public static function apply_schema(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( ! file_exists( self::SQL_FILE ) ) { return; }
		$sql = file_get_contents( self::SQL_FILE );
		if ( $sql === false ) { return; }
		$queries = array_filter( array_map( 'trim', explode( ';', $sql ) ) );

		foreach ( $queries as $query ) {
			dbDelta( $query . ';' ); // :contentReference[oaicite:6]{index=6}
		}
	}
}

Schema_Manager::init();
