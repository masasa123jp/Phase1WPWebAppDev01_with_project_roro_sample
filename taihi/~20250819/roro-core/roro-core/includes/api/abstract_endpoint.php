<?php
/**
 * Abstract REST API endpoint.  Concrete endpoints should extend this
 * class and implement a `register()` method that calls
 * `register_rest_route()`.  The base class provides a default
 * permission callback which ensures the user is logged in and holds a
 * valid nonce.  Public endpoints may override this by providing
 * their own permission callback.
 *
 * @package RoroCore\Api
 */

namespace RoroCore\Api;

use WP_REST_Request;
use WP_Error;

abstract class Abstract_Endpoint {

    /**
     * Register the route with the REST API.  Implementations should call
     * `register_rest_route()` inside this method.  See WordPress
     * documentation for details.
     */
    abstract public static function register(): void;

    /**
     * Default permission callback.  Ensures the user is logged in and
     * verifies the REST nonce.  If either check fails, a WP_Error is
     * returned which WordPress converts to an appropriate HTTP response.
     *
     * @param WP_REST_Request $request The REST request.
     *
     * @return bool|WP_Error True if allowed, otherwise a WP_Error.
     */
    public static function permission_callback( WP_REST_Request $request ) {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_forbidden', __( 'Authentication required.', 'roro-core' ), [ 'status' => 401 ] );
        }
        // Nonce is sent via X-WP-Nonce header by default.
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error( 'rest_invalid_nonce', __( 'Invalid nonce.', 'roro-core' ), [ 'status' => 403 ] );
        }
        // Additional capability checks could go here (e.g. current_user_can()).
        return true;
    }
}
