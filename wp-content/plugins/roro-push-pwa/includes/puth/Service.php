<?php
/**
 * Push notification token service.  Exposes a REST endpoint for
 * registering Firebase Cloud Messaging (FCM) tokens associated with
 * authenticated users.  Tokens are stored as user meta.  Additional
 * logic such as token validation or per‑device metadata can be
 * implemented as needed.
 *
 * @package RoroPushPwa\Push
 */

namespace RoroPushPwa\Push;

use WP_REST_Request;
use WP_Error;

class Service {
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
                'token' => [ 'type' => 'string', 'required' => true ],
            ],
        ] );
    }

    /**
     * Save the FCM token as user meta.  Returns a success response.
     *
     * @param WP_REST_Request $request Incoming request.
     *
     * @return array|WP_Error
     */
    public function save_token( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', __( 'Token is required.', 'roro-push-pwa' ), [ 'status' => 400 ] );
        }
        update_user_meta( get_current_user_id(), 'roro_fcm_token', $token );
        return [ 'ok' => true ];
    }
}
