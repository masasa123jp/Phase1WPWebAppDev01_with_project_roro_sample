<?php
/**
 * Roro Core – Data Migration Script
 *
 * Usage (inside container or Xserver SSH):
 *   wp eval-file wp-content/plugins/roro-core/scripts/migrate_data.php
 *
 * Pre‑requisites:
 *   1. Composer: "phpoffice/phpspreadsheet": "^1.26"
 *   2. Upload source files to WP root (same paths used below)
 *   3. Run under the same PHP version as WordPress (8.1+ on Xserver)
 *
 * Source files:
 *   ./★【完全版】お店情報マスタ　with vba.xlsm
 *   ./データMatrix.xlsx
 *   ./【完成】ワンポイントアドバイス.xlsx
 *   ./【完成】顧客テーブル.xlsx
 *   ./優先順位テーブル.xlsx
 *   ./犬種別_病気傾向と対策ガイド_全30犬種版.docx
 *
 * Tables populated:
 *   roro_facility, roro_customer, roro_advice, roro_report
 *
 * @package RoroCore
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

define( 'WP_USE_THEMES', false );
require_once dirname( __DIR__, 5 ) . '/wp-load.php'; // adjust depth if necessary
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! class_exists( '\PhpOffice\PhpSpreadsheet\Reader\Xlsx' ) ) {
	echo "PhpSpreadsheet not found. Run `composer install` first.\n";
	exit( 1 );
}

global $wpdb;
$wpdb->query( 'START TRANSACTION' ); // wrap all inserts

try {
	/* ---------------------------------------------------------------
	 * 1. Facility master → roro_facility
	 * ------------------------------------------------------------- */
	import_facility(
		ABSPATH . '★【完全版】お店情報マスタ　with vba.xlsm',
		$wpdb
	);

	/* ---------------------------------------------------------------
	 * 2. Customer master → roro_customer
	 * ------------------------------------------------------------- */
	import_customers(
		ABSPATH . '【完成】顧客テーブル.xlsx',
		$wpdb
	);

	/* ---------------------------------------------------------------
	 * 3. One‑point advice → roro_advice
	 * ------------------------------------------------------------- */
	import_advice(
		ABSPATH . '【完成】ワンポイントアドバイス.xlsx',
		ABSPATH . '優先順位テーブル.xlsx',
		$wpdb
	);

	/* ---------------------------------------------------------------
	 * 4. Breed × Disease Guide → roro_report (JSON template)
	 * ------------------------------------------------------------- */
	import_report_templates(
		ABSPATH . '犬種別_病気傾向と対策ガイド_全30犬種版.docx',
		$wpdb
	); // :contentReference[oaicite:2]{index=2}

	$wpdb->query( 'COMMIT' );
	echo "✔ Data migration finished.\n";

} catch ( Exception $e ) {
	$wpdb->query( 'ROLLBACK' );
	echo "✖ Migration aborted: {$e->getMessage()}\n";
	exit( 1 );
}

/* ******************************************************************
 * Functions
 * *****************************************************************/

/**
 * @param string $path
 * @param wpdb   $db
 */
function import_facility( string $path, wpdb $db ): void {
	$sheet = load_first_sheet( $path );
	foreach ( $sheet->toArray( null, true, true, false ) as $idx => $row ) {
		if ( $idx === 0 ) continue; // header
		[ $name, $cat, $lat, $lng, $addr, $phone ] = array_slice( $row, 0, 6 );
		if ( ! $name ) continue;

		$db->replace(
			"{$db->prefix}roro_facility",
			[
				'name'     => $name,
				'category' => mb_strtolower( $cat ),
				'lat'      => $lat,
				'lng'      => $lng,
				'address'  => $addr,
				'phone'    => $phone,
			],
			[ '%s', '%s', '%f', '%f', '%s', '%s' ]
		);
	}
	echo "  → Facility: {$sheet->getHighestRow()} rows processed\n";
}

/**
 * @param string $path
 * @param wpdb   $db
 */
function import_customers( string $path, wpdb $db ): void {
	$sheet = load_first_sheet( $path );
	foreach ( $sheet->toArray( null, true, true, false ) as $idx => $row ) {
		if ( $idx === 0 ) continue; // header
		[ $name, $email, $phone, $zip, $breed, $birth ] = array_slice( $row, 0, 6 );
		if ( ! $email ) continue;

		$breed_id = (int) $db->get_var(
			$db->prepare( "SELECT breed_id FROM {$db->prefix}roro_dog_breed WHERE name=%s", $breed )
		);

		$db->replace(
			"{$db->prefix}roro_customer",
			[
				'name'  => $name,
				'email' => $email,
				'phone' => $phone,
				'zipcode' => $zip,
				'breed_id' => $breed_id ?: 1,
				'birth_date' => $birth ?: null,
			],
			[ '%s', '%s', '%s', '%s', '%d', '%s' ]
		);
	}
	echo "  → Customers: {$sheet->getHighestRow()} rows processed\n";
}

/**
 * @param string $advicePath
 * @param string $priorityPath
 * @param wpdb   $db
 */
function import_advice( string $advicePath, string $priorityPath, wpdb $db ): void {
	$priSheet = load_first_sheet( $priorityPath );
	$priority = [];
	foreach ( $priSheet->toArray() as $row ) {
		$priority[ $row[0] ] = (int) $row[1];
	}

	$sheet = load_first_sheet( $advicePath );
	foreach ( $sheet->toArray( null, true, true, false ) as $idx => $row ) {
		if ( $idx === 0 ) continue;
		[ $title, $body, $cat ] = array_slice( $row, 0, 3 );
		if ( ! $title ) continue;

		$db->replace(
			"{$db->prefix}roro_advice",
			[
				'title'    => $title,
				'body'     => $body,
				'category' => $cat ?: 'A',
				'priority' => $priority[ $title ] ?? 999
			],
			[ '%s', '%s', '%s', '%d' ]
		);
	}
	echo "  → Advice: {$sheet->getHighestRow()} rows processed\n";
}

/**
 * @param string $docxPath
 * @param wpdb   $db
 */
function import_report_templates( string $docxPath, wpdb $db ): void {
	$xml = extract_docx_text( $docxPath );
	preg_match_all( '/●\s*(.+?)\R(.+?)(?=●|$)/su', $xml, $matches, PREG_SET_ORDER );
	foreach ( $matches as $m ) {
		$breed = trim( $m[1] );
		$content = trim( $m[2] );

		$db->replace(
			"{$db->prefix}roro_report",
			[
				'customer_id' => null,
				'content'     => wp_json_encode( [ 'breed' => $breed, 'template' => $content ], JSON_UNESCAPED_UNICODE ),
			],
			[ '%d', '%s' ]
		);
	}
	echo "  → Report templates: " . count( $matches ) . " breeds processed\n";
}

/* ******************************************************************
 * Helper utilities
 * *****************************************************************/

function load_first_sheet( string $file ): Worksheet {
	if ( ! file_exists( $file ) ) {
		throw new RuntimeException( "File not found: {$file}" );
	}
	$reader = IOFactory::createReaderForFile( $file );
	$reader->setReadDataOnly( true );
	return $reader->load( $file )->getSheet( 0 );
}

/**
 * Extract plain text from .docx (ZIP containing word/document.xml)
 */
function extract_docx_text( string $file ): string {
	if ( ! file_exists( $file ) ) {
		throw new RuntimeException( "File not found: {$file}" );
	}
	$zip = new ZipArchive();
	$zip->open( $file );
	$xml = $zip->getFromName( 'word/document.xml' );
	$zip->close();

	$xml = preg_replace( '/<w:(br|cr)\s*\/>/', "\n", $xml ); // line breaks
	return strip_tags( $xml );
}
