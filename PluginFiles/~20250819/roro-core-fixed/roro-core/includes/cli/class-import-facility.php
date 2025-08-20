<?php
/**
 * WP‑CLI command: wp roro import-facility <csv>
 *
 * @package RoroCore\CLI
 */

declare( strict_types = 1 );

namespace RoroCore\CLI;

use WP_CLI;
use WP_CLI\ExitException;

class Import_Facility {

	private string $table;

	public function __construct( \wpdb $wpdb ) {
		$this->table = $wpdb->prefix . 'roro_facility';
		WP_CLI::add_command( 'roro import-facility', [ $this, 'import' ] );
	}

	/**
	 * Import facilities from CSV (id,name,lat,lng,genre).
	 *
	 * ## OPTIONS
	 * <file>
	 * : Path to CSV file.
	 *
	 * ## EXAMPLES
	 *     wp roro import-facility data.csv
	 *
	 * @throws ExitException When file is not readable.
	 */
	public function import( array $args ): void {
		$csv = $args[0];
		if ( ! is_readable( $csv ) ) {
			WP_CLI::error( 'File not readable.' );
		}
		global $wpdb;
		$handle = fopen( $csv, 'r' );
		$count  = 0;

		while ( ( $row = fgetcsv( $handle ) ) ) {
			[ $id, $name, $lat, $lng, $genre ] = $row;
			$wpdb->replace(
				$this->table,
				[
					'id'    => (int) $id,
					'name'  => $name,
					'lat'   => (float) $lat,
					'lng'   => (float) $lng,
					'genre' => (int) $genre,
				],
				[ '%d', '%s', '%f', '%f', '%d' ]
			);
			++ $count;
		}
		fclose( $handle );
		WP_CLI::success( "Imported {$count} rows." );
	}
}
new Import_Facility( $GLOBALS['wpdb'] );
