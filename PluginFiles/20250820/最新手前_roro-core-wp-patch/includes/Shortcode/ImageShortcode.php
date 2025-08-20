<?php
namespace RoroCore\Shortcode;

use function RoroCore\roro_core_image_url;

/**
 * ImageShortcode
 *
 * Registers a `[roro_image]` shortcode that outputs an img tag for a given file
 * stored in the plugin's assets/images directory. This allows editors to
 * embed plugin-provided images without hard-coding URLs. Usage example:
 * `[roro_image file="logo.png" alt="My logo"]`.
 */
class ImageShortcode {
    /**
     * Construct and register the shortcode.
     */
    public function __construct() {
        add_shortcode( 'roro_image', [ $this, 'render' ] );
    }

    /**
     * Shortcode callback. Returns an HTML image tag.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function render( $atts ) {
        $atts = shortcode_atts( [
            'file' => '',
            'alt'  => '',
            'class' => '',
        ], $atts, 'roro_image' );
        $file = sanitize_file_name( $atts['file'] );
        if ( empty( $file ) ) {
            return '';
        }
        $url   = esc_url( roro_core_image_url( $file ) );
        $alt   = esc_attr( $atts['alt'] );
        $class = esc_attr( $atts['class'] );
        $class_attr = $class ? ' class="' . $class . '"' : '';
        return '<img src="' . $url . '" alt="' . $alt . '"' . $class_attr . ' />';
    }
}