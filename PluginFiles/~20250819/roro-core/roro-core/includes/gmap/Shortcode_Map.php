<?php
/**
 * Google Maps Shortcode
 *
 * @package RoroCore
 */

namespace RoroCore\Gmaps;

class Shortcode_Map {

	public static function init(): void {
		add_shortcode( 'roro_map', [ self::class, 'render' ] );
	}

	public static function render( array $atts ): string {
		$atts = shortcode_atts(
			[
				'lat'  => 0,
				'lng'  => 0,
				'zoom' => 14,
			],
			$atts,
			'roro_map'
		);

		$options = get_option( \RoroCore\Settings\General_Settings::OPTION_KEY );
		$key     = esc_attr( $options['gmaps_key'] ?? '' );

		ob_start(); ?>
		<div id="roro-map" style="width:100%;height:400px"></div>
		<script async src="https://maps.googleapis.com/maps/api/js?key=<?php echo $key; ?>&callback=roroInitMap"></script>
		<script>
			function roroInitMap() {
				const center = {lat: <?php echo (float) $atts['lat']; ?>, lng: <?php echo (float) $atts['lng']; ?>};
				const map = new google.maps.Map(document.getElementById('roro-map'), {
					zoom: <?php echo (int) $atts['zoom']; ?>,
					center
				});
				new google.maps.Marker({position: center, map});
			}
		</script>
		<?php
		return ob_get_clean();
	}
}

Shortcode_Map::init(); // :contentReference[oaicite:11]{index=11}
