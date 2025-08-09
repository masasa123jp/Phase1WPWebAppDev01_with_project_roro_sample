<?php
register_block_type(
	__DIR__,
	[
		'render_callback' => function ( array $atts, string $content, $block ) {
			$post = get_post();
			return sprintf(
				'<div class="roro-advice-card"><strong>%s</strong><p>%s</p></div>',
				esc_html__( 'Advice:', 'roro-core' ),
				wp_kses_post( get_the_excerpt( $post ) )
			);
		},
	]
); // block.json + register_block_type pattern :contentReference[oaicite:9]{index=9}
