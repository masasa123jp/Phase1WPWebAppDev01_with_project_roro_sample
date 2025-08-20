<?php
namespace RoroCore;

class Export_CSV {

	public static function output( string $post_type ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied' );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $post_type . '_' . date('Ymd') . '.csv"' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'ID', 'Title', 'Meta' ] );

		$q = new \WP_Query( [ 'post_type' => $post_type, 'posts_per_page' => -1 ] );
		foreach ( $q->posts as $p ) {
			$meta = json_encode( get_post_meta( $p->ID ) );
			fputcsv( $out, [ $p->ID, $p->post_title, $meta ] );
		}
		fclose( $out );
		exit;
	}
}
/* Admin メニュー等から
add_action( 'admin_post_roro_export', fn() => Export_CSV::output( $_GET['type'] ?? 'dog_breed' ) );
*/
