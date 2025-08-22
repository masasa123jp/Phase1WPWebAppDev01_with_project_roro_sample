<?php
namespace RoroCore\Shortcode;

use function roro_core_image_url;

/**
 * Shortcode for embedding images bundled with the plugin.
 *
 * Registers a `[roro_image]` shortcode that outputs an `<img>` tag
 * referencing a file under the plugin's `assets/images` directory.
 * This avoids hard‑coding plugin URLs in post content and allows
 * editors to embed images simply by specifying the filename.
 *
 * Example usage: `[roro_image file="logo.png" alt="My Logo" class="w-32"]`.
 *
 * @package RoroCore\Shortcode
 */
class ImageShortcode {
    /**
     * Register the shortcode on construction.
     */
    public function __construct() {
        add_shortcode( 'roro_image', [ $this, 'render' ] );
    }

    /**
     * Render the shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML markup for the image tag or an empty string on
     *               invalid input.
     */
    public function render( array $atts ) : string {
        $atts = shortcode_atts( [
            'file'  => '',
            'alt'   => '',
            'class' => '',
        ], $atts, 'roro_image' );

        $file = sanitize_file_name( $atts['file'] );
        if ( empty( $file ) ) {
            return '';
        }
        $url   = esc_url( roro_core_image_url( $file ) );
        $alt   = esc_attr( $atts['alt'] );
        $class = trim( $atts['class'] );
        $class_attr = $class !== '' ? ' class="' . esc_attr( $class ) . '"' : '';
        return '<img src="' . $url . '" alt="' . $alt . '"' . $class_attr . ' />';
    }
}