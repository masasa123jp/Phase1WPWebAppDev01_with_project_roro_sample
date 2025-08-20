<?php
namespace RoroCore\Push;

use WP_REST_Request;
use WP_Error;

/**
 * Push Token Service
 *
 * Provides a REST endpoint to save Firebase Cloud Messaging tokens for
 * authenticated users.  Clients should POST to `/wp-json/roro/v1/fcm-token`
 * with a `token` parameter.  Tokens are stored in user meta.
 */
class Service {
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route( 'roro/v1', '/fcm-token', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'save_token' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'token' => [
                    'type'     => 'string',
                    'required' => true,
                ],
            ],
        ] );
    }

    /**
     * Save token for current user.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function save_token( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        if ( empty( $token ) ) {
            return new WP_Error( 'invalid_token', __( 'Token is required.', 'roro-core-wp' ), [ 'status' => 400 ] );
        }
        update_user_meta( get_current_user_id(), 'roro_fcm_token', $token );
        return [ 'success' => true ];
    }
}