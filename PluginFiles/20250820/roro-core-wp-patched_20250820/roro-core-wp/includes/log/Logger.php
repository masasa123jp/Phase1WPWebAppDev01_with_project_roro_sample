<?php
/**
 * シンプルファイルロガー
 *
 * @package RoroCore
 */

namespace RoroCore\Log;

class Logger {

	const LEVELS = [ 'debug', 'info', 'warning', 'error' ];

	public static function log( string $level, string $message ): void {
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'info';
		}

		$line = sprintf(
			"[%-7s] %s %s\n",
			strtoupper( $level ),
			wp_date( 'Y-m-d H:i:s' ),
			$message
		);

        error_log( $line, 3, WP_CONTENT_DIR . '/roro-debug.log' );
	}
}
