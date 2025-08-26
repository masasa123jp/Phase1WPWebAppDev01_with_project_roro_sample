<?php
/**
 * REST controller for the RORO Chatbot plugin.
 *
 * Defines a single endpoint `/roro/v1/chat` which accepts a POST request
 * containing the user message and an optional conversation_id. Nonce
 * validation is performed using the `X-WP-Nonce` header. On success the
 * controller invokes the chat service and returns the structured
 * response. Errors are returned with appropriate HTTP status codes.
 *
 * @package RORO_Chatbot
 */

defined('ABSPATH') || exit;

if (!class_exists('RORO_Chat_REST')) :
final class RORO_Chat_REST {
    /**
     * Register the REST routes for chat functionality.
     *
     * @return void
     */
    public function register_routes(): void {
        register_rest_route('roro/v1', '/chat', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'chat'],
            'permission_callback' => '__return_true',
            'args'                => [
                'message'        => ['type' => 'string', 'required' => true],
                'conversation_id'=> ['type' => 'integer', 'required' => false],
            ],
        ]);
    }

    /**
     * Handle incoming chat requests.
     *
     * @param \WP_REST_Request $req REST request object.
     * @return \WP_REST_Response
     */
    public function chat(\WP_REST_Request $req) {
        // Check nonce for REST security; header key must be lowercase
        if (!wp_verify_nonce($req->get_header('x-wp-nonce'), 'wp_rest')) {
            return new \WP_REST_Response(['error' => 'invalid_nonce'], 403);
        }
        $message = (string) $req->get_param('message');
        $conv_id = (int) $req->get_param('conversation_id');
        $user_id = get_current_user_id();
        $service = new RORO_Chat_Service();
        $res     = $service->handle_user_message($message, $conv_id, $user_id);
        if (!empty($res['error'])) {
            return new \WP_REST_Response($res, 400);
        }
        return new \WP_REST_Response($res, 200);
    }
}
endif;