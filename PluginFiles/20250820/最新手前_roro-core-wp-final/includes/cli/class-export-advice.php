<?php
/**
 * CLI: wp roro export-advice  (exports last N advice posts as CSV)
 *
 * @package RoroCore\CLI
 */

declare( strict_types = 1 );

namespace RoroCore\CLI;

use WP_CLI;

class Export_Advice {

	public function __construct() {
		WP_CLI::add_command( 'roro export-advice', [ $this, 'export' ] );
	}

	/**
	 * Export last N advice posts.
	 *
	 * ## OPTIONS
	 * [--count=<number>]
	 * : Number of posts to export. Default 20.
	 */
	public function export( array $args, array $assoc ): void {
		$count = isset( $assoc['count'] ) ? (int) $assoc['count'] : 20;

		$q = new \WP_Query(
			[
				'post_type'      => 'roro_advice',
				'posts_per_page' => $count,
				'order'          => 'DESC',
			]
		);

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'id', 'title', 'excerpt' ] );

		while ( $q->have_posts() ) {
			$q->the_post();
			fputcsv(
				$out,
				[
					get_the_ID(),
					get_the_title(),
					wp_strip_all_tags( get_the_excerpt() ),
				]
			);
		}
		fclose( $out );
	}
}
new Export_Advice(); // Auto-register.

