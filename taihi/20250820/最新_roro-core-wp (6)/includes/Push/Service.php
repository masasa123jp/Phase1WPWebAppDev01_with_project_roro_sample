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
     * Constructor. Registers REST routes and cleanup hooks.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        // Clean up meta when a user is deleted.  We intentionally do
        // not remove tokens on logout so that devices remain subscribed
        // across sessions.  Unwanted tokens can be explicitly removed
        // via the REST API.
        add_action( 'delete_user', [ $this, 'cleanup_user_meta' ] );
    }

    /**
     * Register the FCM token endpoint under the roro/v1 namespace.
     */
    public function register_routes() : void {
        // Register endpoint to add a device token
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

        // Register endpoint to remove a device token
        register_rest_route( 'roro/v1', '/fcm-token/remove', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'remove_token' ],
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

        // Register endpoint to subscribe to a topic
        register_rest_route( 'roro/v1', '/fcm-topic', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'subscribe_topic' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'topic' => [
                    'type'     => 'string',
                    'required' => true,
                ],
            ],
        ] );

        // Register endpoint to unsubscribe from a topic
        register_rest_route( 'roro/v1', '/fcm-topic', [
            'methods'             => 'DELETE',
            'callback'            => [ $this, 'unsubscribe_topic' ],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
            'args'                => [
                'topic' => [
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
    /**
     * Save a device token for the current user.
     *
     * If the provided token does not already exist in the user's list
     * of tokens it will be appended.  Tokens are stored as an array
     * under the `roro_fcm_tokens` user meta key.  A response
     * containing `success => true` is returned on success or a
     * WP_Error on failure.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return array|WP_Error
     */
    public function save_token( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', __( 'Token is required.', 'roro-core' ), [ 'status' => 400 ] );
        }
        $user_id = get_current_user_id();
        $tokens  = (array) get_user_meta( $user_id, 'roro_fcm_tokens', true );
        if ( ! in_array( $token, $tokens, true ) ) {
            $tokens[] = $token;
            update_user_meta( $user_id, 'roro_fcm_tokens', $tokens );
        }
        return [ 'success' => true ];
    }

    /**
     * Remove a device token for the current user.
     *
     * If the token exists in the user's list it will be removed.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return array|WP_Error
     */
    public function remove_token( WP_REST_Request $request ) {
        $token = sanitize_text_field( $request->get_param( 'token' ) );
        if ( empty( $token ) ) {
            return new WP_Error( 'no_token', __( 'Token is required.', 'roro-core' ), [ 'status' => 400 ] );
        }
        $user_id = get_current_user_id();
        $tokens  = (array) get_user_meta( $user_id, 'roro_fcm_tokens', true );
        $new     = [];
        foreach ( $tokens as $t ) {
            if ( $t !== $token ) {
                $new[] = $t;
            }
        }
        update_user_meta( $user_id, 'roro_fcm_tokens', $new );
        return [ 'success' => true ];
    }

    /**
     * Subscribe the current user to a topic.
     *
     * Topics allow clients to categorise notifications.  Topics are
     * stored as an array under `roro_fcm_topics` user meta.  The
     * topic string is sanitised with sanitize_key().
     *
     * @param WP_REST_Request $request Request.
     * @return array|WP_Error
     */
    public function subscribe_topic( WP_REST_Request $request ) {
        $topic = sanitize_key( $request->get_param( 'topic' ) );
        if ( empty( $topic ) ) {
            return new WP_Error( 'no_topic', __( 'Topic is required.', 'roro-core' ), [ 'status' => 400 ] );
        }
        $user_id = get_current_user_id();
        $topics  = (array) get_user_meta( $user_id, 'roro_fcm_topics', true );
        if ( ! in_array( $topic, $topics, true ) ) {
            $topics[] = $topic;
            update_user_meta( $user_id, 'roro_fcm_topics', $topics );
        }
        return [ 'success' => true ];
    }

    /**
     * Unsubscribe the current user from a topic.
     *
     * @param WP_REST_Request $request Request.
     * @return array|WP_Error
     */
    public function unsubscribe_topic( WP_REST_Request $request ) {
        $topic = sanitize_key( $request->get_param( 'topic' ) );
        if ( empty( $topic ) ) {
            return new WP_Error( 'no_topic', __( 'Topic is required.', 'roro-core' ), [ 'status' => 400 ] );
        }
        $user_id = get_current_user_id();
        $topics  = (array) get_user_meta( $user_id, 'roro_fcm_topics', true );
        $new     = [];
        foreach ( $topics as $t ) {
            if ( $t !== $topic ) {
                $new[] = $t;
            }
        }
        update_user_meta( $user_id, 'roro_fcm_topics', $new );
        return [ 'success' => true ];
    }

    /**
     * Cleanup user meta when a user is deleted.
     *
     * Removes both token and topic lists from user meta on user
     * deletion.  WordPress passes the deleted user ID as a parameter.
     *
     * @param int $user_id The ID of the deleted user.
     * @return void
     */
    public function cleanup_user_meta( int $user_id ) : void {
        delete_user_meta( $user_id, 'roro_fcm_tokens' );
        delete_user_meta( $user_id, 'roro_fcm_topics' );
    }
}