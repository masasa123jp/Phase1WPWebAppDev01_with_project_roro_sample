<?php
/**
 * Template for displaying a single event.  This can be used by themes
 * that support template loading via the single_template filter or
 * manually included by a shortcode.  It outputs the event title,
 * category, date range, location coordinates and content.  You may
 * customise this file to suit your theme.  Translation functions are
 * used for static labels.
 *
 * Variables:
 *   $post WP_Post The event post being displayed.
 */
if ( ! isset( $post ) || ! $post instanceof WP_Post ) {
    return;
}
$start   = get_post_meta( $post->ID, 'roro_start', true );
$end     = get_post_meta( $post->ID, 'roro_end', true );
$lat     = get_post_meta( $post->ID, 'roro_lat', true );
$lng     = get_post_meta( $post->ID, 'roro_lng', true );
$cat     = get_post_meta( $post->ID, 'roro_cat', true );
$address = get_post_meta( $post->ID, 'roro_address', true );
?>
<article class="roro-event-detail">
    <h2><?php echo esc_html( get_the_title( $post ) ); ?></h2>
    <?php if ( $cat ) : ?>
        <p><strong><?php esc_html_e( 'Category', 'roro-map' ); ?>:</strong> <?php echo esc_html( $cat ); ?></p>
    <?php endif; ?>
    <?php if ( $start || $end ) : ?>
        <p><strong><?php esc_html_e( 'Period', 'roro-map' ); ?>:</strong>
        <?php
            $start_str = $start ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $start ) ) : '';
            $end_str   = $end   ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $end ) )   : '';
            echo esc_html( trim( $start_str . ' – ' . $end_str, ' –' ) );
        ?></p>
    <?php endif; ?>
    <?php if ( $address ) : ?>
        <p><strong><?php esc_html_e( 'Address', 'roro-map' ); ?>:</strong> <?php echo esc_html( $address ); ?></p>
    <?php endif; ?>
    <?php if ( $lat && $lng ) : ?>
        <p><strong><?php esc_html_e( 'Coordinates', 'roro-map' ); ?>:</strong> <?php echo esc_html( sprintf( '%.6f, %.6f', (float) $lat, (float) $lng ) ); ?></p>
    <?php endif; ?>
    <div class="content">
        <?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); ?>
    </div>
</article>