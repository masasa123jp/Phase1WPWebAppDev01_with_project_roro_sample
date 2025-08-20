<?php
/**
 * WP‑CLI Commands
 *
 * @package RoroCore
 */

namespace RoroCore\CLI;

use WP_CLI;

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class Commands {

	/**
	 * コマンド登録
	 */
	public static function init(): void {
        WP_CLI::add_command( 'roro', self::class );
	}

	/**
	 * ## OPTIONS
	 *
	 * <subcommand>
	 */
	public function __invoke( $args, $assoc_args ) {
		list( $sub ) = $args;

		switch ( $sub ) {
			case 'import':
				WP_CLI::log( 'Importing initial data…' );
				// 例: CSV 取り込みなど
				break;

			case 'cron':
				WP_CLI::runcommand( 'cron event run roro_core_weekly_push' );
				break;

			default:
				WP_CLI::error( 'Unknown subcommand.' );
		}
	}
}

Commands::init();
