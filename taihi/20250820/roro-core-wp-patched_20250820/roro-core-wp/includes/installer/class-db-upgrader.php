<?php
/**
 * Handles future DB schema migrations via dbDelta.
 *
 * @package RoroCore\Installer
 */

declare( strict_types = 1 );

namespace RoroCore\Installer;

class DB_Upgrader {

	private const OPTION = 'roro_db_version';
	private const VERSION = 2; // Increment on every schema change.

	public static function init(): void {
		add_action( 'plugins_loaded', [ self::class, 'maybe_upgrade' ] );
	}

	public static function maybe_upgrade(): void {
		$installed = (int) get_option( self::OPTION, 0 );
		if ( $installed >= self::VERSION ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		// Example alter: add index to gacha_log.dist
		$table = $wpdb->prefix . 'roro_gacha_log';
		dbDelta( "ALTER TABLE {$table} ADD INDEX dist_idx (advice_id)" );

		update_option( self::OPTION, self::VERSION );
	}
}
DB_Upgrader::init();
