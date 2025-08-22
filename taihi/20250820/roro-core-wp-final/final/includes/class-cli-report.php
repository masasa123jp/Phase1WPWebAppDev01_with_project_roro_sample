<?php
namespace RoroCore;

use WP_CLI;

class CLI_Report {

	public static function register() {
		WP_CLI::add_command( 'roro report', [ self::class, 'generate' ] );
	}

	/** wp roro report --week=2025-W27 */
	public static function generate( $args, $assoc ) {
		$week = $assoc['week'] ?? date( 'o-\WW' );
		$file = RORO_CORE_PATH . "/reports/{$week}.pdf";
		$data = file_get_contents( "https://example.com/wp-json/roro/v1/analytics?week=$week" );
		/* Dompdf 等で PDF 出力 … */
		WP_CLI::success( "Generated $file" );
	}
}
/* WP_CLI::add_hook_alias などは roro-core.php で呼び出し */
