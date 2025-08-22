<?php
/**
 * Very thin PSR‑3–like logger – writes to error_log() and can be filtered.
 *
 * @package RoroCore\Log
 */

declare( strict_types = 1 );

namespace RoroCore\Log;

class Logger {

	public const LEVEL_INFO  = 'info';
	public const LEVEL_WARN  = 'warning';
	public const LEVEL_ERROR = 'error';

	/**
	 * Write a log line.
	 *
	 * @param string $level   Log level. One of the LEVEL_* constants.
	 * @param string $message Message to write.
	 * @param array  $context Extra context.
	 */
	public static function log( string $level, string $message, array $context = [] ): void {
		if ( ! WP_DEBUG ) {
			return; // Never log unless WP_DEBUG is on.
		}
		$line = sprintf(
			'[RoRo][%s] %s %s',
			strtoupper( $level ),
			gmdate( 'c' ),
			$message . ( $context ? ' ' . wp_json_encode( $context ) : '' )
		);
		error_log( apply_filters( 'roro_logger_line', $line, $level, $context ) );
	}
}
