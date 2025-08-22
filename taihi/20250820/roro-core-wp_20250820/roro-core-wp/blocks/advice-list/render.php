<?php
/**
 * Dynamic render for Advice List block.
 *
 * @package RoroCore\Blocks
 */

declare( strict_types = 1 );

namespace RoroCore\Blocks;

register_block_type(
	__DIR__,
	[
		'render_callback' => function () {
			$q = new \WP_Query(
				[
					'post_type'      => 'roro_advice',
					'posts_per_page' => 5,
				]
			);

			if ( ! $q->have_posts() ) {
				return '<p>' . esc_html__( 'No advice yet.', 'roro-core' ) . '</p>';
			}

			ob_start(); ?>
			<ul class="roro-advice-list">
				<?php
				while ( $q->have_posts() ) :
					$q->the_post();
					?>
					<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
				<?php endwhile; ?>
			</ul>
			<?php
			wp_reset_postdata();
			return ob_get_clean();
		},
	]
);
