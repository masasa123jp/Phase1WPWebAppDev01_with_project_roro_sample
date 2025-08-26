<?php
/**
 * Template part for displaying the event map and filter controls.  This
 * file is included by the roro_events_map shortcode callback.  It
 * renders a form for filtering events, a container for the Google
 * map and a list of results.  All IDs used here correspond to
 * selectors expected by assets/js/roro-map.js.
 */
?>
<div class="roro-events-wrap">
    <div class="roro-events-filters">
        <!-- Keyword search -->
        <input type="text" id="roro-q" placeholder="<?php echo esc_attr__( 'Keyword', 'roro-map' ); ?>" />
        <!-- Category multi-select (populated by JS) -->
        <select id="roro-category" multiple="multiple" aria-label="<?php esc_attr_e( 'Categories', 'roro-map' ); ?>"></select>
        <!-- Date range -->
        <input type="date" id="roro-date-from" aria-label="<?php esc_attr_e( 'Start date', 'roro-map' ); ?>" />
        <input type="date" id="roro-date-to" aria-label="<?php esc_attr_e( 'End date', 'roro-map' ); ?>" />
        <!-- Radius -->
        <input type="number" id="roro-radius" min="1" max="300" value="25" aria-label="<?php esc_attr_e( 'Distance (km)', 'roro-map' ); ?>" />
        <!-- Use my location -->
        <button type="button" id="roro-use-geo" class="button"><?php echo esc_html__( 'Use my location', 'roro-map' ); ?></button>
        <!-- Search & reset -->
        <button type="button" id="roro-search" class="button button-primary"><?php echo esc_html__( 'Search', 'roro-map' ); ?></button>
        <button type="button" id="roro-reset" class="button"><?php echo esc_html__( 'Reset', 'roro-map' ); ?></button>
        <!-- Geolocation status -->
        <span id="roro-geo-status" aria-live="polite"></span>
    </div>
    <div class="roro-events-layout">
        <!-- Map container -->
        <div id="roro-map"></div>
        <!-- Results list -->
        <div id="roro-list" class="roro-list" role="list" aria-live="polite"></div>
    </div>
</div>