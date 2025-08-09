<?php
/**
 * Child theme setup for RoRo UI – enqueue styles & support plugin assets.
 */
add_action( 'wp_enqueue_scripts', function () {
	$parent = get_template_directory_uri() . '/style.css';
	wp_enqueue_style( 'roro-parent', $parent, [], filemtime( get_template_directory() . '/style.css' ) );

	if ( file_exists( WP_CONTENT_DIR . '/plugins/roro-core/assets/build/index.css' ) ) {
		wp_enqueue_style(
			'roro-plugin-css',
			plugins_url( 'assets/build/index.css', WP_CONTENT_DIR . '/plugins/roro-core/roro-core.php' ),
			[],
			filemtime( WP_CONTENT_DIR . '/plugins/roro-core/assets/build/index.css' )
		);
	}
} );
