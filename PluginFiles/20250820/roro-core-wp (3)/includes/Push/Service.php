<?php
/**
 * Push notification token service.
 *
 * Provides a REST API endpoint for registering Firebase Cloud
 * Messaging (FCM) tokens.  Tokens are stored as user meta keyed by
 * `roro_fcm_token`.  Only authenticated users may register a token.
 *
 * Integrating this service into the core plugin avoids requiring a
 * separate push/PWA plugin and keeps related functionality in one
 * place.  The endpoint is registered under the `roro/v1` namespace.
 *
 * @package RoroCore\Push
 */

namespace RoroCore\Push;

use WP_REST_Request;
use WP_Error;

/**
 * Class Service
 *
 * Handles registration of a REST route for storing FCM tokens.
 */
class Service {
    /**
     * Constructor. Hooks the route registration into rest_api_init.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register the FCM token endpoint under the roro/v1 namespace.
     */
    public function register_routes() : void {
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
     * Save the FCM token as user meta and return a success response.
     *
     * @param WP_REST_Request $request Incoming request.
     *
     * @return array|WP_Error
     */
    public function save_token( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', __( 'Token is required.', 'roro-core-wp' ), [ 'status' => 400 ] );
        }
        update_user_meta( get_current_user_id(), 'roro_fcm_token', $token );
        return [ 'ok' => true ];
    }
}