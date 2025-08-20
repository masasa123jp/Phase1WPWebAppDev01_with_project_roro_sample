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
);

// This file registers the Advice Card block server‑side using block.json
// configuration.  The render callback outputs a simple wrapper with the
// post excerpt.  Removed leftover citation markers from earlier
// discussions to avoid confusion.
