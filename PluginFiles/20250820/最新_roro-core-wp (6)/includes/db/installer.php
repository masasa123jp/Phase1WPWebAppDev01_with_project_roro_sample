<?php
/**
 * db/installer.php
 * - 既存 WP DB (default) か、wp-config.php で定義した外部 DB を自動判定
 * - 権限チェックしてから各種 CREATE TABLE/INDEX を実行
 *
 * 例) wp-config.php に以下を追加すると外部 DB モード
 * define( 'RORO_EXT_DB_NAME', 'wp_roro_log' );
 * define( 'RORO_EXT_DB_USER', 'roro_user' );
 * define( 'RORO_EXT_DB_PASS', '********' );
 * define( 'RORO_EXT_DB_HOST', 'localhost' );
 */

namespace RoroCore\Db;
defined( 'ABSPATH' ) || exit;

use wpdb;

final class Installer {

	const VERSION = '1.0.0';
	const DDL_FILE = __DIR__ . '/schema-roro.sql';

	public static function run() : void {
		global $wpdb;

		// ---------- 接続先判定 ----------
		if ( defined( 'RORO_EXT_DB_NAME' ) ) {
			$external = new wpdb(
				RORO_EXT_DB_USER,
				RORO_EXT_DB_PASS,
				RORO_EXT_DB_NAME,
				RORO_EXT_DB_HOST
			);
			$external->query( 'SET NAMES utf8mb4' );
			$conn = $external;
		} else {
			$conn = $wpdb;      // WordPress 標準接続
		}

		// ---------- 既存バージョン判定 ----------
		$ver_opt = 'roro_schema_version';
		if ( get_option( $ver_opt ) === self::VERSION ) {
			return;
		}

		// ---------- ddl 読み込み ----------
		$sql = file_get_contents( self::DDL_FILE );
		$statements = array_filter( array_map( 'trim', explode( ';', $sql ) ) );

		foreach ( $statements as $query ) {
			$conn->query( $query );
		}

		update_option( $ver_opt, self::VERSION );
	}
}
