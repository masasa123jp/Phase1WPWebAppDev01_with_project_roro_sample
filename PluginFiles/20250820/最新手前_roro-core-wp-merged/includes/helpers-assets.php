<?php
/**
 * Asset helper functions for the RoRo Core plugin.
 *
 * This file defines helper functions for working with assets packaged
 * with the plugin.  In particular it exposes a function for
 * generating URLs to images stored under the plugin's `assets/images`
 * directory.  By centralising the logic here we avoid repeating
 * directory calculations throughout templates and shortcode handlers.
 *
 * @package RoroCore
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'roro_core_image_url' ) ) {
    /**
     * Return a fully qualified URL for an image in the plugin's assets.
     *
     * Provide the filename of the image relative to the
     * `assets/images` directory and this function will return the
     * absolute URL.  If the caller passes a leading slash it will be
     * trimmed to avoid generating double slashes in the output.
     *
     * @param string $filename Image filename, e.g. `logo.png` or
     *        `icons/icon.svg`.
     *
     * @return string The absolute URL to the image.
     */
    function roro_core_image_url( string $filename ) : string {
        $file = ltrim( $filename, '/\\' );
        return trailingslashit( RORO_CORE_URL ) . 'assets/images/' . $file;
    }
}